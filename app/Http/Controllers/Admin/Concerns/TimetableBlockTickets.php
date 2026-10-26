<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\TimetableBlockTicket;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableChangeRequest;
use App\Services\Timetable\BlockTicketTelegram;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HEMIS solishtirishdagi to'siqlar bo'yicha so'rovlar.
 *
 *  - Registrator ofisi to'siqli darslar uchun so'rov yuboradi: dars o'qituvchisi va kafedra mudiriga, xona band
 *    bo'lsa — xonani band qilgan dars o'qituvchisiga ham (Telegram, "Qabul qildim / Rad etaman" tugmalari).
 *  - Kafedra mudiri faqat shu so'rov doirasida (so'rov ochiq ekan) shu darsni o'zgartiradi:
 *      xona — darhol LMS'da; vaqt (shu kunning boshqa parasi) — darhol LMS'da;
 *      kun — registrator ofisi tasdig'i bilan (o'zgarish so'rovi).
 */
trait TimetableBlockTickets
{
    private function blockTicketsReady(): bool
    {
        return Schema::hasTable('timetable_block_tickets');
    }

    private function isTicketAdmin(Request $request): bool
    {
        return in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true);
    }

    /** Kartaning kafedrasi mudiri (mark'dagi "kafedra_mudiri" roli, shu kafedraga biriktirilgan). */
    private function ticketHeadFor(TimetableCard $card): ?Teacher
    {
        $name = trim((string) $card->kafedra_name);
        if ($name === '') {
            return null;
        }
        $dept = Department::where('name', $name)->where('structure_type_code', '!=', 11)->first()
            ?? Department::where('name', 'like', '%' . $name . '%')->where('structure_type_code', '!=', 11)->first();
        if (!$dept) {
            return null;
        }

        return Teacher::headsOfDepartment($dept->department_hemis_id)->where('is_active', true)->first();
    }

    private function ticketTeacherByName(?string $name): ?Teacher
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return Teacher::where('is_active', true)->where(fn ($q) => $q->where('full_name', $name)->orWhere('short_name', $name))->first();
    }

    /** Kartaning shu haftadagi samarali joyi: [kun, yarim-slot, start_half] yoki null. */
    private function ticketPlace(TimetableCard $card, int $week): ?array
    {
        $o = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        if ($o) {
            return $o->cancelled ? null : [(int) $o->day, (int) $o->pair, (int) ($o->start_half ?? 0)];
        }

        return $card->day && $card->pair ? [(int) $card->day, (int) $card->pair, (int) ($card->start_half ?? 0)] : null;
    }

    /** Registrator: to'siqli darslar bo'yicha so'rov yaratish va Telegram'ga yuborish. */
    public function blockTicketsSend(Request $request, TimetableBoard $board)
    {
        abort_unless($this->isTicketAdmin($request), 403, 'Faqat registrator ofisi uchun.');
        abort_unless($this->blockTicketsReady(), 422, 'To\'siq so\'rovlari jadvali yo\'q (migratsiya).');
        $data = $request->validate([
            'items'                 => 'required|array|max:500',
            'items.*.card_id'       => 'required|integer',
            'items.*.week'          => 'required|integer|min:1|max:60',
            'items.*.date'          => 'required|date',
            'items.*.start'         => 'required|string|max:5',
            'items.*.end'           => 'required|string|max:5',
            'items.*.type'          => 'required|in:room,teacher,other',
            'items.*.text'          => 'nullable|string|max:1000',
            'items.*.blocker'       => 'nullable|array',
            'items.*.group'         => 'nullable|string|max:255',
        ]);
        $actor = $this->timetableActor($request);
        $tg = app(BlockTicketTelegram::class);
        $made = 0;
        $skipped = 0;
        $noTelegram = [];
        $sent = 0;
        foreach ($data['items'] as $it) {
            $card = TimetableCard::where('board_id', $board->id)->find($it['card_id']);
            if (!$card || $it['date'] <= now()->toDateString()) {
                $skipped++;
                continue;
            }
            $exists = TimetableBlockTicket::where('card_id', $card->id)->where('week', $it['week'])->where('block_type', $it['type'])
                ->whereIn('status', [TimetableBlockTicket::OPEN, TimetableBlockTicket::PENDING_REGISTRAR])->exists();
            if ($exists) {
                $skipped++;
                continue;
            }
            $blocker = array_intersect_key((array) ($it['blocker'] ?? []), array_flip(['source', 'group', 'subject', 'teacher', 'room', 'time', 'text']));
            $teacher = $card->teacher_id ? Teacher::find($card->teacher_id) : null;
            $head = $this->ticketHeadFor($card);
            $people = [];
            $add = function (?Teacher $t, string $role) use (&$people) {
                if ($t && !isset($people[$t->id])) {
                    $people[$t->id] = ['teacher_id' => $t->id, 'name' => $t->full_name, 'role' => $role,
                        'chat_id' => $t->telegram_chat_id ?: null, 'message_id' => null, 'response' => null, 'note' => null, 'at' => null];
                }
            };
            $add($teacher, 'teacher');
            $add($head, 'head');
            if ($it['type'] === 'room') {
                $add($this->ticketTeacherByName($blocker['teacher'] ?? null), 'blocker');
            }
            $t = TimetableBlockTicket::create([
                'board_id' => $board->id, 'card_id' => $card->id, 'week' => (int) $it['week'], 'lesson_date' => $it['date'],
                'start_time' => substr($it['start'], 0, 5), 'end_time' => substr($it['end'], 0, 5), 'block_type' => $it['type'],
                'block_text' => $it['text'] ?? null, 'blocker' => $blocker,
                'group_name' => $it['group'] ?? $card->group_name ?? implode(', ', $card->occupiedGroups()),
                'subject_name' => $card->subject_name, 'kafedra_name' => $card->kafedra_name,
                'status' => TimetableBlockTicket::OPEN, 'teacher_id' => $teacher?->id, 'head_id' => $head?->id,
                'recipients' => array_values($people),
                'created_by_id' => $actor?->id, 'created_by_name' => $actor?->full_name ?? $actor?->name,
            ]);
            $made++;
            $recipients = (array) $t->recipients;
            foreach ($recipients as &$r) {
                if (empty($r['chat_id'])) {
                    $noTelegram[$r['name']] = true;
                    continue;
                }
                $r['message_id'] = $tg->send((string) $r['chat_id'], $tg->text($t, $r['role']), $tg->keyboard($t));
                $sent += $r['message_id'] ? 1 : 0;
            }
            unset($r);
            if (!$head) {
                $noTelegram['(kafedra mudiri topilmadi: ' . $card->kafedra_name . ')'] = true;
            }
            $t->update(['recipients' => $recipients]);
        }

        return response()->json(['ok' => true, 'created' => $made, 'skipped' => $skipped, 'telegram_sent' => $sent,
            'without_telegram' => array_keys($noTelegram)]);
    }

    /** So'rovni ko'rishga ruxsat: admin/registrator, qabul qiluvchilar, shu kafedra mudiri. */
    private function ticketAccess(Request $request, TimetableBlockTicket $t): array
    {
        $admin = $this->isTicketAdmin($request);
        $actor = $this->timetableActor($request);
        $tid = $this->timetableTeacherId($request);
        $recipient = collect((array) $t->recipients)->firstWhere('teacher_id', $tid);
        $kafs = $this->timetableActiveRole($request) === 'kafedra_mudiri' ? ($this->departmentHeadKafedra($request) ?? []) : [];
        $head = ($tid > 0 && (int) $t->head_id === $tid) || ($kafs && $this->sameKafedra($t->kafedra_name, $kafs));

        return ['admin' => $admin, 'recipient' => $recipient, 'head' => $head || $admin, 'view' => $admin || $recipient || $head, 'actor' => $actor];
    }

    /** O'zgarish so'rovi tasdiqlangan/rad etilgan bo'lsa — so'rov holatini yangilaydi. */
    private function syncTicketStatus(TimetableBlockTicket $t): void
    {
        if ($t->status !== TimetableBlockTicket::PENDING_REGISTRAR || !$t->change_request_id) {
            return;
        }
        $cr = TimetableChangeRequest::find($t->change_request_id);
        if (!$cr) {
            return;
        }
        if (in_array($cr->status, [TimetableChangeRequest::APPROVED, TimetableChangeRequest::APPLIED], true)) {
            $t->update(['status' => TimetableBlockTicket::RESOLVED, 'resolved_at' => $cr->reviewed_at ?? now()]);
        } elseif (in_array($cr->status, [TimetableChangeRequest::REJECTED, TimetableChangeRequest::FAILED, TimetableChangeRequest::CANCELLED], true)) {
            $t->update(['status' => TimetableBlockTicket::OPEN, 'resolution' => array_merge((array) $t->resolution,
                ['registrar' => $cr->status, 'registrar_note' => $cr->review_note])]);
        }
    }

    private function ticketArray(TimetableBlockTicket $t, array $access = []): array
    {
        $card = $t->card;

        return $t->only(['id', 'board_id', 'card_id', 'week', 'start_time', 'end_time', 'block_type', 'block_text', 'blocker',
                'group_name', 'subject_name', 'kafedra_name', 'status', 'recipients', 'resolution', 'change_request_id', 'created_by_name'])
            + ['lesson_date' => $t->lesson_date?->format('Y-m-d'), 'created_at' => $t->created_at?->toDateTimeString(),
                'card' => $card ? ['teacher_name' => $card->teacher_name, 'auditorium_name' => $card->auditorium_name,
                    'training_type' => $card->training_type, 'len_half' => $card->lenHalf()] : null,
                'can_resolve' => !empty($access['head']) && $t->isOpen(),
                'can_respond' => !empty($access['recipient']) && $t->isOpen()];
    }

    /** Ro'yxat: registrator — hammasi; kafedra mudiri — o'z kafedrasiniki; o'qituvchi — o'ziga yuborilganlar. */
    public function blockTicketsIndex(Request $request, TimetableBoard $board)
    {
        if (!$this->blockTicketsReady()) {
            return response()->json(['tickets' => []]);
        }
        $q = TimetableBlockTicket::with('card')->where('board_id', $board->id)->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->input('status'));
        }
        $out = [];
        foreach ($q->limit(1000)->get() as $t) {
            $this->syncTicketStatus($t);
            $acc = $this->ticketAccess($request, $t);
            if ($acc['view']) {
                $out[] = $this->ticketArray($t, $acc);
            }
        }

        return response()->json(['tickets' => $out]);
    }

    /** Bitta so'rov + hal qilish variantlari: shu kunning bo'sh paralari va bo'sh xonalar. */
    public function blockTicketShow(Request $request, TimetableBlockTicket $ticket)
    {
        $this->syncTicketStatus($ticket);
        $acc = $this->ticketAccess($request, $ticket);
        abort_unless($acc['view'], 403);
        $out = $this->ticketArray($ticket, $acc);
        $card = $ticket->card;
        $place = $card ? $this->ticketPlace($card, $ticket->week) : null;
        $out['place'] = $place ? ['day' => $place[0], 'pair' => $place[1]] : null;
        if ($out['can_resolve'] && $card && $place) {
            $board = $card->board;
            $slots = $this->hemisSlotTimes($board);
            $day = (int) ($request->input('day') ?: $place[0]);
            $pairs = [];
            foreach ($slots as $p => [$st, $en]) {
                if ($p + $card->lenHalf() - 1 > count($slots) || ($card->lenHalf() >= 2 && $p % 2 === 0)) {
                    continue;
                }
                if ($day === $place[0] && $p === $place[1]) {
                    continue;
                }
                if (!$this->findWeekConflicts($card, $ticket->week, $day, $p)) {
                    $pairs[] = ['pair' => $p, 'time' => $st . '–' . ($slots[$p + $card->lenHalf() - 1][1] ?? $en)];
                }
            }
            $out['free_pairs'] = $pairs;
            $out['free_pairs_day'] = $day;
            $out['free_rooms'] = $this->ticketFreeRooms($ticket, $card, $place);
        }

        return response()->json(['ticket' => $out]);
    }

    /** Shu dars vaqtida bo'sh va kartaga ruxsat etilgan xonalar (doska + HEMIS bandligi hisobga olinadi). */
    private function ticketFreeRooms(TimetableBlockTicket $t, TimetableCard $card, array $place): array
    {
        [$day, $pair] = $place;
        $range = [$pair, $pair + $card->lenHalf()];
        $busy = [];
        $ovr = TimetableCardOverride::whereHas('card', fn ($q) => $q->where('board_id', $card->board_id))->where('week', $t->week)->get()->keyBy('card_id');
        foreach (TimetableCard::where('board_id', $card->board_id)->where('id', '!=', $card->id)->get() as $o) {
            $ov = $ovr->get($o->id);
            if ($ov && $ov->cancelled) {
                continue;
            }
            $od = $ov ? $ov->day : $o->day;
            $op = $ov ? $ov->pair : $o->pair;
            $room = ($ov && $ov->auditorium_code) ? $ov->auditorium_code : $o->auditorium_code;
            if ($od == $day && $op && $room && $op < $range[1] && $range[0] < $op + $o->lenHalf()) {
                $busy[(string) $room] = true;
            }
        }
        // HEMIS'dagi boshqa darslar (shu sana va vaqt).
        foreach (DB::table('schedules')->whereNull('deleted_at')->whereDate('lesson_date', $t->lesson_date)
            ->where('lesson_pair_start_time', '<', $t->end_time . ':59')->where('lesson_pair_end_time', '>', $t->start_time . ':00')
            ->pluck('auditorium_code') as $code) {
            $busy[(string) $code] = true;
        }
        $map = $this->auditoriumTeacherMap();

        return Auditorium::where('active', true)->orderBy('name')->get(['code', 'name', 'volume', 'department_hemis_id', 'building_name'])
            ->filter(fn ($a) => (!isset($busy[(string) $a->code]) || $this->isSharedRoom($a->name)) && $this->auditoriumAllowedForCard($a, $card, $map))
            ->map(fn ($a) => ['code' => $a->code, 'name' => $a->name, 'volume' => $a->volume, 'building' => $a->building_name])
            ->values()->all();
    }

    /** Qabul qiluvchi (veb orqali) javobi. */
    public function blockTicketRespond(Request $request, TimetableBlockTicket $ticket)
    {
        $data = $request->validate(['response' => 'required|in:accepted,declined', 'note' => 'nullable|string|max:500']);
        $msg = app(BlockTicketTelegram::class)->respond($ticket->id, $this->timetableTeacherId($request), $data['response'], $data['note'] ?? null);
        abort_if($msg === null, 403, 'Siz bu so\'rovga javob bera olmaysiz.');

        return response()->json(['ok' => true, 'message' => $msg]);
    }

    /**
     * Kafedra mudiri to'siqni hal qiladi (faqat ochiq so'rov doirasida):
     *   room — shu darsga boshqa xona (darhol); time — shu kunning boshqa parasi (darhol);
     *   day — boshqa kun (registrator ofisi tasdig'iga yuboriladi).
     */
    public function blockTicketResolve(Request $request, TimetableBlockTicket $ticket)
    {
        $acc = $this->ticketAccess($request, $ticket);
        abort_unless($acc['head'], 403, 'Faqat shu kafedra mudiri hal qila oladi.');
        abort_unless($ticket->isOpen(), 422, 'So\'rov ochiq emas — o\'zgartirib bo\'lmaydi.');
        $data = $request->validate([
            'kind'            => 'required|in:room,time,day',
            'auditorium_code' => 'required_if:kind,room|nullable|string|max:50',
            'pair'            => 'required_if:kind,time,day|nullable|integer|min:1|max:24',
            'day'             => 'required_if:kind,day|nullable|integer|min:1|max:7',
            'note'            => 'nullable|string|max:500',
        ]);
        $card = $ticket->card;
        abort_unless($card, 404);
        $place = $this->ticketPlace($card, $ticket->week);
        abort_unless($place, 422, 'Dars shu haftada ichki jadvalda joylashmagan.');
        [$day, $pair, $startHalf] = $place;
        $actor = $acc['actor'];
        $who = $actor?->full_name ?? $actor?->name;
        $tg = app(BlockTicketTelegram::class);

        if ($data['kind'] === 'day') {
            abort_if((int) $data['day'] === $day, 422, 'Kun o\'zgarmagan — vaqtni o\'zgartirish tanlang.');
            $payload = ['week' => $ticket->week, 'action' => 'move', 'day' => (int) $data['day'], 'pair' => (int) $data['pair'], 'layer' => 'internal'];
            $route = 'admin.timetable.cards.week-override';
            $req = app(TimetableChangeApproval::class)->submit([
                'board_id' => $card->board_id, 'requester_id' => $actor?->getKey(), 'requester_type' => $actor?->getMorphClass(),
                'requester_name' => $who, 'requester_role' => 'kafedra_mudiri', 'kafedra_name' => $card->kafedra_name,
                'route_name' => $route, 'target_key' => mb_substr($route . '#' . md5('card:' . $card->id . ':w' . $ticket->week), 0, 191),
                'params' => ['board' => $card->board_id, 'card' => $card->id], 'payload' => $payload, 'kind' => 'move',
                'subject_name' => $card->subject_name,
                'summary' => 'To\'siq №' . $ticket->id . ': ' . $ticket->group_name . ' · ' . $card->subject_name . ' · ' . $ticket->week
                    . '-hafta — ' . (BlockTicketTelegram::DAY_NAMES[(int) $data['day']] ?? $data['day']) . 'ga ko\'chirish'
                    . (!empty($data['note']) ? ' — ' . $data['note'] : ''),
                'details' => [],
            ]);
            $ticket->update(['status' => TimetableBlockTicket::PENDING_REGISTRAR, 'change_request_id' => $req->id,
                'resolution' => ['kind' => 'day', 'day' => (int) $data['day'], 'pair' => (int) $data['pair'], 'by' => $who, 'at' => now()->toDateTimeString(), 'note' => $data['note'] ?? null]]);
            $tg->broadcast($ticket, "To'siq №{$ticket->id}: kafedra mudiri darsni boshqa kunga ko'chirishni so'radi — registrator ofisi tasdig'ini kutmoqda.");

            return response()->json(['ok' => true, 'status' => $ticket->status, 'change_request_id' => $req->id]);
        }

        $roomName = null;
        try {
            // O'zgarish yoziladi va shu haftadagi to'qnashuv tekshiriladi; to'qnashuv bo'lsa — hammasi bekor (rollback).
            DB::transaction(function () use ($data, $card, $ticket, $day, $pair, $startHalf, &$roomName) {
                $ov = TimetableCardOverride::firstOrNew(['card_id' => $card->id, 'week' => $ticket->week]);
                $ov->cancelled = false;
                $ov->day = $day;
                $ov->pair = $data['kind'] === 'time' ? (int) $data['pair'] : $pair;
                $ov->start_half = $data['kind'] === 'time' ? 0 : $startHalf;
                if ($data['kind'] === 'room') {
                    $a = Auditorium::where('code', $data['auditorium_code'])->first();
                    if (!$a) {
                        throw new \RuntimeException('Xona topilmadi.');
                    }
                    if (!$this->auditoriumAllowedForCard($a, $card, $this->auditoriumTeacherMap())) {
                        throw new \RuntimeException('Bu xona shu dars o\'qituvchisiga biriktirilmagan.');
                    }
                    $ov->auditorium_code = $a->code;
                    $ov->auditorium_name = $a->name;
                    $roomName = $a->name;
                }
                $ov->save();
                $conf = $this->findWeekConflicts($card, $ticket->week, (int) $ov->day, (int) $ov->pair, (int) $ov->start_half);
                if ($conf) {
                    throw new \RuntimeException(implode(' · ', $conf));
                }
            });
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $slots = $this->hemisSlotTimes($card->board);
        $res = $data['kind'] === 'room'
            ? ['kind' => 'room', 'auditorium' => $roomName]
            : ['kind' => 'time', 'pair' => (int) $data['pair'], 'time' => ($slots[(int) $data['pair']][0] ?? '') . '–' . ($slots[(int) $data['pair'] + $card->lenHalf() - 1][1] ?? '')];
        $ticket->update(['status' => TimetableBlockTicket::RESOLVED, 'resolved_at' => now(),
            'resolution' => $res + ['by' => $who, 'at' => now()->toDateTimeString(), 'note' => $data['note'] ?? null]]);
        $tg->broadcast($ticket, "✅ To'siq №{$ticket->id} hal qilindi ({$ticket->group_name} · {$ticket->subject_name}, "
            . $ticket->lesson_date->format('d.m.Y') . '): '
            . ($res['kind'] === 'room' ? 'yangi xona — ' . $roomName : 'yangi vaqt — ' . $res['time']) . '.');

        return response()->json(['ok' => true, 'status' => $ticket->status, 'resolution' => $ticket->resolution]);
    }

    /** Registrator: so'rovni bekor qilish. */
    public function blockTicketCancel(Request $request, TimetableBlockTicket $ticket)
    {
        abort_unless($this->isTicketAdmin($request), 403);
        $ticket->update(['status' => TimetableBlockTicket::CANCELLED]);

        return response()->json(['ok' => true]);
    }
}

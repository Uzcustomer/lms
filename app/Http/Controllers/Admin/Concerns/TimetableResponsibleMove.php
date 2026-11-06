<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Admin\TimetableChangeRequestController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fan mas'uli (faol rol — fan_masuli): mas'ul fani kartasini HAFTALIK ichki jadvalda shu haftaga ko'chirish — kun, boshlanish
 * (para chegarasi), xona. Gibrid qoida (rejim har doim serverda aniqlanadi, mijozga ishonilmaydi):
 *   - "fix" — dars shu haftada to'qnashuvda (qizil: o'qituvchi / guruh band, amaliyot ma'ruza bilan ustma-ust; sariq ham: xona band,
 *     xona sig'maydi yoki nofaol) yoki joylanmagan VA yangi joy to'liq toza → darhol (Takliflar bajaruvchisi runSuggestionActions),
 *     o'zgarishlar tarixiga "To'qnashuvni o'qituvchi hal qildi (fan mas'uli)" bilan, fan kafedrasi mudiriga xabar;
 *   - "request" — to'qnashuvsiz joylashgan dars → jadval o'zgarmaydi, Takliflar so'rovi (SUGGESTION_ROUTE, sabab majburiy):
 *     kafedra mudiri → registrator ofisi; har bosqichda huquq (karta hamon mas'ul fanda) va bandlik qayta tekshiriladi;
 *   - yangi joy band bo'lsa — hech qaysi rejim yo'q: 422, aniq sabab bilan.
 * Faqat shu hafta (hafta o'zgarishi; shablon va kartaning o'z xonasi o'zgarmaydi), ko'p bo'lakli dars (2 + 2 soat) — yaxlit, bo'laklar
 * tanlangan paradan ketma-ket. Ma'ruza — faqat kafedra mudiri va adminlar. Bugungi va o'tgan kunlar (eski va yangi) — yopiq.
 * Xona: berilsa — faol, sig'adi, auditoriumAllowedForCard; berilmasa — har bo'lak shu haftadagi o'z xonasida (u nofaol yoki sig'masa —
 * "Xona tanlang"). Xonalar ro'yxati check javobida (admin.timetable.auditoriums / teachers fan mas'uliga ochilmaydi).
 */
trait TimetableResponsibleMove
{
    /** POST cards/{card}/responsible-move/check {week, day, pair, auditorium_code?} → {mode: fix|request|null, message, conflicts, rooms}. */
    public function responsibleMoveCheck(Request $request, TimetableCard $card)
    {
        $ev = $this->responsibleMoveEvaluate($request, $card, true);

        return response()->json($ev['body'], $ev['status']);
    }

    /** POST cards/{card}/responsible-move {week, day, pair, auditorium_code?, reason?}: "fix" — darhol, "request" — so'rov. */
    public function responsibleMove(Request $request, TimetableCard $card)
    {
        $ev = $this->responsibleMoveEvaluate($request, $card, false);
        if ($ev['body']['mode'] === null) {
            return response()->json($ev['body'] + ['error' => $ev['body']['message']], $ev['status']);
        }
        $ctx = $ev['ctx'];
        $board = $ctx['board'];
        $payload = ['week' => $ctx['week'], 'card_id' => (int) $card->id, 'type' => 'move', 'title' => "Vaqt va xonani o'zgartirish (fan mas'uli)",
            'actions' => $ctx['acts']];
        if ($ev['body']['mode'] === 'request') {
            $reason = trim((string) $request->input('reason', ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => "So'rov sababini kiriting."]);
            }
            // Takliflar so'rovi — faol rol (fan mas'uli) huquqi bilan, tekshiruvdagidek: kafedra mudiri → registrator ofisi (boshqa
            // rollari — admin / kafedra mudiri — zanjirni o'zgartirmaydi); har bosqichda qayta tekshiruv, tasdiqdan keyin qo'llash.
            $res = app(TimetableChangeRequestController::class)->proposeResponsibleSuggestion($request, $board, $payload, $reason,
                app(TimetableChangeApproval::class));
            $j = $res->getData(true);

            return response()->json($j + ['mode' => 'request'], $res->getStatusCode());
        }

        // "fix": tarix yozuvi amaldan OLDIN (qayerdan → qayerga), so'ng doska qulfi ostida qayta baholash va bajarish.
        $svc = app(TimetableChangeApproval::class);
        $actor = $this->timetableActor($request);
        $draft = null;
        try {
            $draft = $svc->draft($this->responsibleSubRequest($board, $payload), $actor, $board, 'fan_masuli');
        } catch (\Throwable $e) {
            Log::warning('[ResponsibleMove] tarix: ' . $e->getMessage());
        }
        $error = null;
        try {
            DB::transaction(function () use ($request, $card, $board, &$error) {
                TimetableBoard::whereKey($board->id)->lockForUpdate()->first();
                // Parallel o'zgarish: qulf ostida qayta — rejim hamon "fix" va yangi joy toza bo'lishi kerak.
                $again = $this->responsibleMoveEvaluate($request, $card, false);
                if ($again['body']['mode'] !== 'fix') {
                    $error = $again['body']['mode'] === 'request'
                        ? "Dars endi to'qnashuvsiz — o'zgarish so'rov orqali (sababini kiriting)." : $again['body']['message'];
                    throw new \RuntimeException('responsible-move-failed');
                }
                if ($error = $this->runSuggestionActions($board, $again['ctx']['acts'])) {
                    throw new \RuntimeException('responsible-move-failed');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'responsible-move-failed') {
                throw $e;
            }
        }
        if ($error !== null) {
            return response()->json(['ok' => false, 'mode' => null, 'error' => $error, 'message' => $error], 422);
        }
        $note = self::teacherFixNote() . " (fan mas'uli)";
        if ($draft) {
            try {
                $draft['summary'] = trim((string) $draft['summary']) . ' — ' . $note;
                $draft['details'] = (array) ($draft['details'] ?? []) + ['flags' => [$note]];
                $svc->record($draft);
            } catch (\Throwable $e) {
                Log::warning('[ResponsibleMove] tarix: ' . $e->getMessage());
            }
        }
        $notified = $this->teacherConflictFixDone($request, $board, $ctx['acts']);

        return response()->json(['ok' => true, 'mode' => 'fix', 'fixed' => true, 'notified' => $notified,
            'message' => "To'qnashuvdagi dars darhol joylandi (faqat shu hafta)."]);
    }

    /**
     * Kiritma, huquq, bo'laklar, xona va rejim. Qaytaradi: ['status', 'body' => [ok, mode, message, conflicts, rooms, old, new, parts],
     * 'ctx' => [board, week, acts, ...]]. $withRooms — xonalar ro'yxati (check oynasi uchun).
     */
    private function responsibleMoveEvaluate(Request $request, TimetableCard $card, bool $withRooms): array
    {
        $actor = $this->timetableActor($request);
        abort_unless($this->timetableActiveRole($request) === 'fan_masuli' && $actor instanceof Teacher, 403,
            "Vaqt va xonani o'zgartirish oynasi — fan mas'uli uchun.");
        $board = TimetableBoard::findOrFail($card->board_id);
        $card->setRelation('board', $board);
        $grid = $this->cardGrid($board, $card);
        $data = $request->validate([
            'week' => 'required|integer|min:1|max:' . max(1, (int) $grid['weeks']),
            'day' => 'required|integer|min:1|max:' . max(1, (int) $grid['days']),
            'pair' => 'required|integer|min:1|max:' . max(1, (int) $grid['pairs']),
            'auditorium_code' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:1000',
        ]);
        $week = (int) $data['week'];
        $day = (int) $data['day'];
        $pair = (int) $data['pair'];
        abort_if($card->training_type === 'lecture', 403, "Ma'ruzani surish — faqat kafedra mudiri va adminlar.");
        $keys = $this->responsibleSubjectKeysFor((int) $actor->id);
        $cards = $this->weeklyUnitCards($card);
        foreach ($cards as $c) {
            $c->setRelation('board', $board);
            abort_unless($keys && $this->responsibleMatch($keys, $c), 403, "Faqat mas'ul faningizdagi darsni o'zgartira olasiz.");
        }
        $no = fn (int $status, string $msg, array $conflicts = [], array $extra = []) => ['status' => $status,
            'body' => ['ok' => false, 'mode' => null, 'message' => $msg, 'conflicts' => $conflicts ?: [$msg]] + $extra,
            'ctx' => ['board' => $board, 'week' => $week, 'acts' => []]];

        // Bo'laklar: shu haftada o'tiladiganlari, tanlangan paradan ketma-ket (unitChain tartibi).
        [$chain] = $this->unitChain($this->unitPieces($cards, $week));
        if (!$chain) {
            return $no(422, "Bu haftada bu dars o'tilmaydi.");
        }
        $moves = [];
        foreach ($chain as [$piece, $off]) {
            $p = $pair + $off;
            if ($p + $piece->lenHalf() - 1 > (int) $grid['pairs']) {
                return $no(422, 'Tanlangan vaqt jadval chegarasidan tashqarida (dars ' . $this->unitHours(array_sum(array_map(fn ($x) => $x[0]->lenHalf(), $chain)))
                    . ').');
            }
            $moves[] = [$piece, $p];
        }
        $parts = collect(array_map(fn ($m) => $m[0], $moves));
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1)->toDateString();
        $labels = $this->responsibleMoveLabels($board, $moves, $week, $day);
        $info = ['old' => $labels['old'], 'new' => $labels['new'], 'date' => $date,
            'parts' => array_map(fn ($m) => ['card_id' => (int) $m[0]->id, 'pair' => $m[1], 'len_half' => $m[0]->lenHalf()], $moves)];
        $rooms = $withRooms ? $this->responsibleMoveRooms($board, $moves, $week, $day, $date) : [];

        // Xona: berilgan — faol, sig'adi, o'qituvchiga ruxsat; berilmagan — har bo'lak o'z (shu haftadagi) xonasida, u yaroqli bo'lishi kerak.
        $room = null;
        $code = trim((string) ($data['auditorium_code'] ?? ''));
        $roomErrors = [];
        if ($code !== '') {
            $room = Auditorium::where('code', $code)->first();
            if (!$room) {
                $roomErrors[] = 'Xona topilmadi.';
            } else {
                $map = $this->auditoriumTeacherMap();
                foreach ($parts as $c) {
                    if (!$this->auditoriumAllowedForCard($room, $c, $map)) {
                        $roomErrors[] = 'Bu xona darsning o‘qituvchisiga biriktirilmagan: ' . ($room->name ?: $room->code) . '.';
                        break;
                    }
                    if ($msg = $this->suggestionRoomProblem($board, $c, $room)) {
                        $roomErrors[] = ucfirst($msg);
                        break;
                    }
                }
            }
        } else {
            foreach ($parts as $c) {
                $ov = TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->first();
                $cur = (string) ($ov?->auditorium_code ?: $c->auditorium_code);
                if ($cur === '') {
                    continue;
                }
                $curRoom = Auditorium::where('code', $cur)->first();
                $msg = $curRoom ? $this->suggestionRoomProblem($board, $c, $curRoom) : 'xona ro‘yxatda yo‘q';
                if ($msg) {
                    $roomErrors[] = 'Xona tanlang: hozirgi xona ' . ($curRoom ? ($curRoom->name ?: $curRoom->code) : $cur) . ' — ' . preg_replace('/^xona nofaol — boshqa xona tanlang \(.*\)\.$/u', 'nofaol', $msg) . '.';
                    break;
                }
            }
        }
        if ($roomErrors) {
            return $no(422, implode(' ', $roomErrors), $roomErrors, $info + ['rooms' => $rooms]);
        }
        // Ko'p bo'lakli dars: avval hamma bo'lak shu haftada bo'shatiladi ("cancel"), so'ng ketma-ket qo'yiladi — aks holda bo'lak o'z
        // blokining eski joyi bilan to'qnashardi (bir kunda surilganda, masalan 1 va 3 → 3 va 5). "cancel" shu haftaning xonasini o'chiradi —
        // xona tanlanmagan bo'lsa, bo'lakning shu haftadagi xonasi qayta beriladi; yakuniy holat runSuggestionActions oxirida tekshiriladi.
        $multi = count($moves) > 1;
        $acts = $multi ? array_map(fn ($m) => ['op' => 'cancel', 'card_id' => (int) $m[0]->id, 'week' => $week], $moves) : [];
        foreach ($moves as [$c, $p]) {
            $code = $room ? (string) $room->code
                : ($multi ? (string) (TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->value('auditorium_code') ?? '') : '');
            $acts[] = ['op' => 'move', 'card_id' => (int) $c->id, 'week' => $week, 'day' => $day, 'pair' => $p] + ($code !== '' ? ['auditorium_code' => $code] : []);
        }

        // Huquq, sana (eski va yangi — bugun / o'tgan yopiq), kartadagi kutilayotgan so'rov — Takliflar qoidasi (o'qituvchi darajasi).
        $rights = ['level' => 'teacher', 'view' => false, 'tid' => (int) $actor->id, 'kafedra' => null, 'keys' => $keys];
        $chk = $this->suggestionApplyCheck($rights, $board, ['card_id' => (int) $card->id, 'actions' => $acts]);
        if ($chk['mode'] === null) {
            return $no($chk['code'], (string) $chk['why'], [], $info + ['rooms' => $rooms]);
        }
        // Rejim: shu haftada to'qnashuvda (qizil yoki sariq) yoki joylanmagan bo'lsa — "fix", aks holda — "request" (yangi joy holatidan oldin).
        $needsFix = $parts->contains(fn (TimetableCard $c) => $this->responsibleNeedsFix($board, $c, $week));
        // Yangi joy to'liq toza (barcha bo'laklar ko'chgan holatda, sinab — rollback): guruh, o'qituvchi, xona, sig'im, bronlar, blok yaxlitligi.
        $errors = $this->responsibleMoveErrors($board, $moves, $week, $day, $room);
        if ($errors) {
            return $no(422, implode(' · ', $errors), $errors, $info + ['rooms' => $rooms]);
        }
        $mode = $needsFix ? 'fix' : 'request';

        return ['status' => 200, 'body' => ['ok' => true, 'mode' => $mode, 'conflicts' => [], 'rooms' => $rooms,
            'message' => $mode === 'fix' ? "Darhol qo'llanadi (to'qnashuvni tuzatish, faqat shu hafta)."
                : "So'rov kafedra mudiri va registrator ofisiga yuboriladi (dars to'qnashuvsiz joylashgan)."] + $info,
            'ctx' => ['board' => $board, 'week' => $week, 'acts' => $acts]];
    }

    /**
     * Dars shu haftada "to'qnashuvda" (fan mas'uli oqimi): joylanmagan (bekor qilinmagan) yoki qizil (weeklyLessonNeedsFix — o'qituvchi /
     * guruh band, sikl bilan o'qituvchi band) yoki sariq: xona band (haftalik dars yoki sikl darsi bilan), hozirgi xona sig'maydi yoki nofaol.
     */
    private function responsibleNeedsFix(TimetableBoard $board, TimetableCard $card, int $week): bool
    {
        if ($this->weeklyLessonNeedsFix($board, $card, $week)) {
            return true;
        }
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        if ($ov && $ov->cancelled) {
            return false;
        }
        $place = $this->suggestionPlace($card, $week);
        if (!$place) {
            return false;
        }
        foreach ((array) ($this->weeklyConflicts($board, null, [(int) $card->id => true], $week, true)[$card->id][$week]['items'] ?? []) as $it) {
            if (($it['kind'] ?? '') === 'room_busy') {
                return true;
            }
        }
        $date = $this->weekStartDate($board, $card, $week)->addDays($place[0] - 1)->toDateString();
        foreach ($this->weeklyProposalBusy($card, $week, $place[0], $place[1], $date) as $m) {
            if (str_starts_with($m, 'Xona ')) {
                return true;
            }
        }
        $code = (string) ($ov?->auditorium_code ?: $card->auditorium_code);
        $room = $code !== '' ? Auditorium::where('code', $code)->first() : null;

        return $code !== '' && (!$room || $this->suggestionRoomProblem($board, $card, $room) !== null);
    }

    /** Yangi joy xatolari: barcha bo'laklar ko'chgan holatda (sinab — rollback) agendaWeekErrors, blok yaxlitligi (eski va yangi kun). */
    private function responsibleMoveErrors(TimetableBoard $board, array $moves, int $week, int $day, ?Auditorium $room): array
    {
        $orig = [];
        $sugMoves = [];
        foreach ($moves as [$c, $p]) {
            $orig[(int) $c->id] = $this->suggestionPlace($c, $week);
            $sugMoves[] = [$c, $week, $day, $p];
        }
        foreach ($moves as [$c]) {
            if ($why = $this->teacherFixBlockPart($board, $c, $week, $orig[(int) $c->id], $sugMoves)) {
                return [$why];
            }
        }
        $errors = [];
        DB::beginTransaction();
        try {
            foreach ($moves as [$c, $p]) {
                TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week],
                    ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]
                        + ($room ? ['auditorium_code' => $room->code, 'auditorium_name' => $room->name] : []));
            }
            foreach ($moves as [$c, $p]) {
                [$e, $roomCode] = $this->agendaWeekErrors($board, $c, $week, $day, $p, $room, false);
                // Umumiy (raqamsiz) xona — sig'im tekshirilmaydi (umumiy bajaruvchidagi suggestionRoomProblem bilan bir xil).
                $eff = $room ?? ($roomCode !== '' ? Auditorium::where('code', $roomCode)->first() : null);
                if ($eff && $this->isSharedRoom((string) $eff->name)) {
                    $e = array_values(array_filter($e, fn ($m) => !str_starts_with((string) $m, "Xona sig'maydi")));
                }
                $errors = array_merge($errors, $e,
                    $this->practiceDaySplits($c, $week, array_values(array_unique(array_filter([$orig[(int) $c->id][0] ?? null, $day])))));
            }
        } finally {
            DB::rollBack();
        }

        return array_values(array_unique($errors));
    }

    /**
     * Tanlangan vaqt uchun xonalar (check oynasi): faol, sig'imi yetadi (umumiy xona / bandlik tekshirilmaydigan karta — sig'imsiz),
     * har bo'lak o'qituvchisiga ruxsat; yaqinlik bo'yicha (rankRooms), bo'shlari tepada, bandlari sababi bilan (tanlab bo'lmaydi).
     * Bandlik — barcha bo'laklar shu vaqtga ko'chgan holatda (sinab) unitBusy: haftalik va sikl darslari bilan.
     */
    private function responsibleMoveRooms(TimetableBoard $board, array $moves, int $week, int $day, string $date): array
    {
        $this->roomRankReset();
        $base = $this->roomRankBase();
        $parts = collect(array_map(fn ($m) => $m[0], $moves));
        $first = $parts->first();
        $ov = TimetableCardOverride::where('card_id', $first->id)->where('week', $week)->first();
        $current = (string) ($ov?->auditorium_code ?: $first->auditorium_code);
        $fit = $base['active']->filter(fn ($r) => $parts->every(fn (TimetableCard $c) => $this->auditoriumAllowedForCard($r, $c, $base['teacher'])
            && $this->suggestionRoomProblem($board, $c, $r) === null));
        $busy = ['rooms' => []];
        DB::beginTransaction();
        try {
            foreach ($moves as [$c, $p]) {
                TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week], ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]);
            }
            $busy = $this->unitBusy($board, TimetableCard::whereIn('id', $parts->pluck('id'))->get()->each(fn ($c) => $c->setRelation('board', $board)),
                [$date => true]);
        } catch (\Throwable $e) {
            Log::warning('[ResponsibleMove] xona bandligi: ' . $e->getMessage());
        } finally {
            DB::rollBack();
        }
        $out = [];
        foreach ($this->rankRooms($first, $fit, $current !== '' ? $current : null, $this->roomHabitForCard($board, $first)) as [$r, $why]) {
            $b = $busy['rooms'][(string) $r->code] ?? null;
            $out[] = ['code' => (string) $r->code, 'name' => (string) ($r->name ?: $r->code), 'volume' => (int) $r->volume, 'why' => $why,
                'current' => (string) $r->code === $current, 'free' => $b === null,
                'busy' => $b !== null ? self::roomBusyText('Band: ', (string) $b) : null];
        }
        usort($out, fn ($a, $b) => [$a['free'] ? 0 : 1] <=> [$b['free'] ? 0 : 1]);

        return array_slice($out, 0, 120);
    }

    /** Eski va yangi joy matni: "Seshanba, 10.11.2026 10:00–11:20" (ko'p bo'lakli — birinchi bo'lak boshidan oxirgisi oxirigacha). */
    private function responsibleMoveLabels(TimetableBoard $board, array $moves, int $week, int $day): array
    {
        $first = $moves[0][0];
        $old = $this->suggestionSlot($board, $first, $week);
        $names = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $last = end($moves);
        $from = $this->rangeFor($first, $moves[0][1], 0);
        $to = $this->rangeFor($last[0], $last[1], 0);
        $date = $this->weekStartDate($board, $first, $week)->addDays($day - 1);

        return ['old' => $old['text'] . ($old['room'] !== '' ? ', xona ' . $old['room'] : ''),
            'new' => ($names[$day - 1] ?? $day . '-kun') . ', ' . $date->format('d.m.Y') . ' ' . $this->halfRangeTime($board, [$from[0], $to[1]])];
    }

    /** Takliflar route'i (SUGGESTION_ROUTE) bilan ichki so'rov — tarix yozuvi (draft) uchun. */
    private function responsibleSubRequest(TimetableBoard $board, array $payload): Request
    {
        $sub = Request::create('/', 'POST', $payload);
        $route = (new \Illuminate\Routing\Route('POST', '/', []))->name(self::SUGGESTION_ROUTE);
        $route->bind($sub);
        $route->setParameter('board', $board);
        $sub->setRouteResolver(fn () => $route);

        return $sub;
    }
}

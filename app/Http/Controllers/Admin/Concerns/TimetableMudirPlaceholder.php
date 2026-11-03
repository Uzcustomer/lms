<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Department;
use App\Models\Teacher;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Vaqtincha kafedra mudiriga yozish" — to'qnashuv hal bo'lguncha dars kafedra mudiri nomiga, «Kafedra mudiri xonasi»da
 * joylanadi (tashqi jadval joyida yoki tanlangan katakda; tanlangan hafta yoki shu va keyingi haftalar).
 *
 * - O'qituvchi — fan kafedrasining mudiri, xona — "Kafedra mudiri xonasi" (HEMIS solishtirishdagi hemisMudirFor /
 *   hemisFallbackRoomFor bilan bir xil manba, fanning kafedrasi ustuvor). Topilmasa — yozib bo'lmaydi (sababi bilan).
 * - Kartaga mudir_placeholder (asl o'qituvchi/xona, sabab, kim/qachon, haftalar) yoziladi; to'qnashuv tekshirilmaydi va bu karta
 *   boshqalarni ham to'qnashuvli qilmaydi (clashWith, weeklyConflicts).
 * - "Asl o'qituvchiga qaytarish" — bandlik tekshiriladi: bo'sh bo'lsa asl o'qituvchi va xona tiklanadi, aks holda sababi.
 * - Huquq: admin — darhol; kafedra mudiri — faqat o'z kafedrasi fanlari (weeklyLayerDenied). Tarix — TimetableChangeApproval::LOGGED.
 */
trait TimetableMudirPlaceholder
{
    /** Fan kafedrasi (kartadagi kafedra nomi) → HEMIS kafedra id'si. */
    private function cardDepartmentId(TimetableCard $card): ?int
    {
        $name = trim((string) $card->kafedra_name);
        if ($name === '') {
            return null;
        }
        $dept = Department::where('name', $name)->where('structure_type_code', '!=', 11)->first()
            ?? Department::where('name', 'like', '%' . $name . '%')->where('structure_type_code', '!=', 11)->first();

        return $dept ? (int) $dept->department_hemis_id : null;
    }

    /** Kafedra mudiri (hemisMudirFor manbasi: "kafedra_mudiri" roli, shu kafedra; HEMIS id'si borlari ustuvor). */
    private function kafedraMudirTeacher(?int $deptId): ?Teacher
    {
        if (!$deptId) {
            return null;
        }
        $q = Teacher::headsOfDepartment($deptId)->where('is_active', true);

        return (clone $q)->whereNotNull('hemis_id')->where('hemis_id', '>', 0)->first() ?? $q->first();
    }

    /** Karta uchun mudir va mudir xonasi: ['teacher' => Teacher|null, 'room' => [kod, nomi]|null, 'why' => sabab|null]. */
    private function mudirPlaceholderTarget(TimetableCard $card): array
    {
        $dept = $this->cardDepartmentId($card);
        $teacher = $this->kafedraMudirTeacher($dept);
        $room = $this->hemisFallbackRoomFor($dept);
        $why = !$dept ? 'Fanning kafedrasi aniqlanmadi («' . trim((string) $card->kafedra_name) . '»).'
            : (!$teacher ? 'Kafedra mudiri topilmadi (shu kafedraga «kafedra_mudiri» roli berilgan xodim yo\'q).'
                : (!$room ? '«Kafedra mudiri xonasi» auditoriyasi topilmadi.' : null));

        return ['teacher' => $teacher, 'room' => $room, 'why' => $why];
    }

    /** Joy: so'rovdagi kun/para, bo'lmasa tashqi jadvaldagi joyi. [kun, para] | null. */
    private function mudirPlaceholderSlot(TimetableCard $card, array $data): ?array
    {
        if (!empty($data['day']) && !empty($data['pair'])) {
            return [(int) $data['day'], (int) $data['pair']];
        }

        return $card->ext_day && $card->ext_pair ? [(int) $card->ext_day, (int) $card->ext_pair] : null;
    }

    private function mudirPlaceholderDenied(Request $request, Collection $cards)
    {
        foreach ($cards as $x) {
            if ($denied = $this->weeklyLayerDenied($request, 'internal', $x)) {
                return $denied;
            }
        }

        return null;
    }

    /** GET cards/{card}/mudir-placeholder?week=&day=&pair= — tugma holati: mudir, xona, joy, sabab (o'chiq bo'lsa). */
    public function mudirPlaceholderInfo(Request $request, TimetableCard $card)
    {
        $data = $request->validate(['week' => 'nullable|integer|min:1|max:30', 'day' => 'nullable|integer|min:1|max:7', 'pair' => 'nullable|integer|min:1|max:24']);
        $t = $this->mudirPlaceholderTarget($card);
        $slot = $this->mudirPlaceholderSlot($card, $data);
        $cards = $this->weeklyUnitCards($card);
        $len = array_sum($cards->map(fn ($c) => $c->lenHalf())->all());
        $days = $card->board->day_names ?: \App\Models\TimetableBoard::DEFAULT_DAY_NAMES;
        $times = $this->hemisSlotTimes($card->board);
        $denied = $this->mudirPlaceholderDenied($request, $cards);

        return response()->json([
            'ok' => !$t['why'] && $slot && !$denied,
            'why' => $t['why'] ?? (!$slot ? 'Tashqi jadvalda joyi yo\'q — avval jadvaldan katak tanlang.' : ($denied ? ($denied->getData(true)['error'] ?? 'Huquq yo\'q') : null)),
            'mudir' => $t['teacher'] ? ['id' => (int) $t['teacher']->id, 'name' => (string) $t['teacher']->full_name, 'hemis_id' => $t['teacher']->hemis_id ? (int) $t['teacher']->hemis_id : null] : null,
            'room' => $t['room'] ? ['code' => $t['room'][0], 'name' => $t['room'][1]] : null,
            'slot' => $slot ? ['day' => $slot[0], 'pair' => $slot[1], 'text' => ($days[$slot[0] - 1] ?? ($slot[0] . '-kun')) . ' (' . ($times[$slot[1]][0] ?? '')
                . '–' . ($times[$slot[1] + $len - 1][1] ?? '') . ')'] : null,
            'len' => $len,
            'placeholder' => $card->mudir_placeholder,
        ]);
    }

    /**
     * POST cards/{card}/mudir-placeholder {week, scope: one|rest, day?, pair?, reason?}: birlik (barcha bo'laklari, ketma-ket) mudir va
     * mudir xonasi bilan shu joyga — to'qnashuv tekshirilmaydi. Bugungi va o'tgan darslar (admin bo'lmasa) — yozilmaydi.
     */
    public function mudirPlaceholderApply(Request $request, TimetableCard $card)
    {
        $grid = $this->cardGrid($card->board, $card);
        $data = $request->validate(['week' => 'required|integer|min:1|max:' . max(1, (int) $grid['weeks']), 'scope' => 'nullable|in:one,rest',
            'day' => 'nullable|integer|min:1|max:' . $grid['days'], 'pair' => 'nullable|integer|min:1|max:' . max(1, (int) $grid['pairs']),
            'reason' => 'nullable|string|max:500']);
        $cards = $this->weeklyUnitCards($card);
        if ($denied = $this->mudirPlaceholderDenied($request, $cards)) {
            return $denied;
        }
        $t = $this->mudirPlaceholderTarget($card);
        if ($t['why']) {
            return response()->json(['error' => $t['why']], 422);
        }
        $slot = $this->mudirPlaceholderSlot($card, $data);
        if (!$slot) {
            return response()->json(['error' => 'Tashqi jadvalda joyi yo\'q — jadvaldan katak tanlang.'], 422);
        }
        [$day, $pair] = $slot;
        $W = (int) $data['week'];
        [$chain, $total] = $this->unitChain($this->unitPieces($cards, $W));
        if (!$chain) {
            return response()->json(['error' => 'Bu haftada bu dars o\'tilmaydi.'], 422);
        }
        if ($pair + $total - 1 > (int) $grid['pairs']) {
            return response()->json(['error' => $total . " soat sig'maydi: kun oxiri."], 422);
        }
        $admin = in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'], true);
        $today = self::proposalToday();
        $weeks = ($data['scope'] ?? 'one') === 'rest' ? range($W, (int) $grid['weeks']) : [$W];
        $weeks = array_values(array_filter($weeks, fn ($w) => $admin || $this->weekStartDate($card->board, $card, $w)->addDays($day - 1)->toDateString() > $today));
        if (!$weeks) {
            return response()->json(['error' => 'Bugungi va o\'tgan darslar o\'zgartirilmaydi.'], 422);
        }
        $actor = $this->timetableActor($request);
        $mudir = $t['teacher'];
        [$roomCode, $roomName] = $t['room'];
        DB::transaction(function () use ($cards, $chain, $weeks, $day, $pair, $mudir, $roomCode, $roomName, $data, $actor) {
            $ovs = $this->unitOverrides($cards);
            foreach ($chain as [$piece, $off]) {
                $ph = $piece->mudir_placeholder ?: ['teacher_id' => $piece->teacher_id, 'teacher_name' => $piece->teacher_name,
                    'auditorium_code' => $piece->auditorium_code, 'auditorium_name' => $piece->auditorium_name, 'weeks' => []];
                $placed = [];
                foreach ($weeks as $w) {
                    // Shu haftada o'tilmaydigan bo'lak (bekor qilingan) — tegilmaydi.
                    if (($ovs[$piece->id][$w] ?? null)?->cancelled) {
                        continue;
                    }
                    TimetableCardOverride::updateOrCreate(['card_id' => $piece->id, 'week' => $w],
                        ['day' => $day, 'pair' => $pair + $off, 'start_half' => 0, 'cancelled' => false, 'auditorium_code' => $roomCode, 'auditorium_name' => $roomName]);
                    $placed[] = $w;
                }
                $ph['weeks'] = array_values(array_unique(array_merge($ph['weeks'] ?? [], $placed)));
                sort($ph['weeks']);
                $ph += ['reason' => null];
                $ph['reason'] = trim((string) ($data['reason'] ?? '')) ?: $ph['reason'];
                $ph['by_id'] = $actor?->getKey();
                $ph['by_name'] = $actor?->full_name ?? $actor?->name;
                $ph['at'] = now()->toDateTimeString();
                $ph['mudir_name'] = $mudir->full_name;
                $piece->forceFill(['teacher_id' => $mudir->id, 'teacher_name' => $mudir->short_name ?: $mudir->full_name,
                    'auditorium_code' => $roomCode, 'auditorium_name' => $roomName, 'mudir_placeholder' => $ph])->save();
            }
        });

        return response()->json(['ok' => true, 'weeks' => $weeks, 'unit' => $this->unitPayload($this->weeklyUnitCards($card->fresh()), $W, false),
            'cards' => TimetableCard::whereIn('id', $cards->pluck('id'))->get(['id', 'teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name', 'mudir_placeholder'])]);
    }

    /**
     * POST cards/{card}/mudir-placeholder/revert: asl o'qituvchi va xona tiklanadi — yozilgan haftalarda bandlik tekshiriladi
     * (birlikning o'z bo'laklari hisobga olinmaydi); band bo'lsa — hech narsa o'zgarmaydi, sababi bilan.
     */
    public function mudirPlaceholderRevert(Request $request, TimetableCard $card)
    {
        $cards = $this->weeklyUnitCards($card)->filter(fn ($c) => !empty($c->mudir_placeholder))->values();
        if ($cards->isEmpty()) {
            return response()->json(['error' => 'Bu dars vaqtincha kafedra mudirida emas.'], 422);
        }
        if ($denied = $this->mudirPlaceholderDenied($request, $cards)) {
            return $denied;
        }
        $ignore = array_fill_keys($this->weeklyUnitCards($card)->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $errors = [];
        $weeksOf = $cards->mapWithKeys(fn ($c) => [$c->id => (array) ($c->mudir_placeholder['weeks'] ?? [])])->all();
        DB::beginTransaction();
        try {
            foreach ($cards as $c) {
                $ph = $c->mudir_placeholder;
                foreach ($ph['weeks'] ?? [] as $w) {
                    $o = TimetableCardOverride::where('card_id', $c->id)->where('week', $w)->first();
                    if ($o && (string) $o->auditorium_code === (string) $c->auditorium_code) {
                        $o->forceFill(['auditorium_code' => null, 'auditorium_name' => null])->save();
                    }
                }
                $c->forceFill(['teacher_id' => $ph['teacher_id'] ?? null, 'teacher_name' => $ph['teacher_name'] ?? null,
                    'auditorium_code' => $ph['auditorium_code'] ?? null, 'auditorium_name' => $ph['auditorium_name'] ?? null, 'mudir_placeholder' => null])->save();
            }
            foreach ($cards as $c) {
                $c->refresh();
                foreach ($weeksOf[$c->id] ?? [] as $w) {
                    $o = TimetableCardOverride::where('card_id', $c->id)->where('week', $w)->first();
                    if (!$o || $o->cancelled || !$o->day || !$o->pair) {
                        continue;
                    }
                    $state = $this->calendarConflictState($c);
                    $state['ignore'] = $ignore;
                    foreach ($this->findWeekConflicts($c, (int) $w, (int) $o->day, (int) $o->pair, 0, $state) as $e) {
                        $errors[] = $w . '-hafta: ' . $e;
                    }
                }
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        if ($errors) {
            DB::rollBack();

            return response()->json(['error' => "Asl o'qituvchiga qaytarib bo'lmaydi — band: " . implode(' · ', array_values(array_unique($errors)))], 422);
        }
        DB::commit();

        return response()->json(['ok' => true, 'cards' => TimetableCard::whereIn('id', $cards->pluck('id'))->get(['id', 'teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name', 'mudir_placeholder'])]);
    }
}

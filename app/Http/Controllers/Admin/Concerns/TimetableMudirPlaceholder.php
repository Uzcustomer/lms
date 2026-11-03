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
 * - Faqat tanlangan hafta(lar)ning yozuviga (timetable_card_overrides.mudir_placeholder: mudir, shu haftaning asl xonasi, asl
 *   o'qituvchi, sabab, kim/qachon) — karta asosiy o'qituvchisi/xonasi boshqa haftalar uchun o'zgarmaydi. Shu haftada to'qnashuv
 *   tekshirilmaydi va boshqalarni ham to'qnashuvli qilmaydi (weekClashes, weeklyConflicts).
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
            'placeholder' => !empty($data['week']) ? $this->ovPlaceholder(TimetableCardOverride::where('card_id', $card->id)->where('week', (int) $data['week'])->first() ?? (object) []) : null,
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
        // Faqat tanlangan hafta(lar)ning yozuvi o'zgaradi (karta asosiy o'qituvchisi va xonasi — boshqa haftalar uchun — o'zgarmaydi):
        // mudir va shu haftaning asl xonasi (override) mudir_placeholder'da saqlanadi — qaytarishda aynan tiklanadi.
        DB::transaction(function () use ($cards, $chain, $weeks, $day, $pair, $mudir, $roomCode, $roomName, $data, $actor) {
            $ovs = $this->unitOverrides($cards);
            foreach ($chain as [$piece, $off]) {
                foreach ($weeks as $w) {
                    $prev = $ovs[$piece->id][$w] ?? null;
                    // Shu haftada o'tilmaydigan bo'lak (bekor qilingan) — tegilmaydi.
                    if ($prev?->cancelled) {
                        continue;
                    }
                    $old = $prev ? $this->ovPlaceholder($prev) : null;
                    $ph = [
                        'teacher_id' => (int) $mudir->id, 'teacher_name' => (string) ($mudir->short_name ?: $mudir->full_name),
                        'mudir_name' => (string) $mudir->full_name, 'hemis_id' => $mudir->hemis_id ? (int) $mudir->hemis_id : null,
                        // Asl holat: karta o'qituvchisi va shu haftaning xonasi (haftalik yozuvdagi — bo'lmasa kartaniki).
                        'orig_teacher_id' => $piece->teacher_id ? (int) $piece->teacher_id : null, 'orig_teacher_name' => $piece->teacher_name,
                        'orig_room_code' => $old ? ($old['orig_room_code'] ?? null) : ($prev?->auditorium_code ?: null),
                        'orig_room_name' => $old ? ($old['orig_room_name'] ?? null) : ($prev?->auditorium_name ?: null),
                        'orig_day' => $old ? ($old['orig_day'] ?? null) : ($prev && $prev->day ? (int) $prev->day : null),
                        'orig_pair' => $old ? ($old['orig_pair'] ?? null) : ($prev && $prev->pair ? (int) $prev->pair : null),
                        'had_override' => $old ? (bool) ($old['had_override'] ?? false) : (bool) $prev,
                        'reason' => trim((string) ($data['reason'] ?? '')) ?: ($old['reason'] ?? null),
                        'by_id' => $actor?->getKey(), 'by_name' => $actor?->full_name ?? $actor?->name, 'at' => now()->toDateTimeString(),
                    ];
                    TimetableCardOverride::updateOrCreate(['card_id' => $piece->id, 'week' => $w],
                        ['day' => $day, 'pair' => $pair + $off, 'start_half' => 0, 'cancelled' => false, 'auditorium_code' => $roomCode,
                            'auditorium_name' => $roomName, 'mudir_placeholder' => $ph]);
                }
            }
        });

        return response()->json(['ok' => true, 'weeks' => $weeks, 'unit' => $this->unitPayload($cards, $W, false)]);
    }

    /**
     * POST cards/{card}/mudir-placeholder/revert: asl o'qituvchi va xona tiklanadi — yozilgan haftalarda bandlik tekshiriladi
     * (birlikning o'z bo'laklari hisobga olinmaydi); band bo'lsa — hech narsa o'zgarmaydi, sababi bilan.
     */
    public function mudirPlaceholderRevert(Request $request, TimetableCard $card)
    {
        $data = $request->validate(['week' => 'nullable|integer|min:1|max:60']);
        // Birlik — kartaning o'z (asl) o'qituvchisi bo'yicha: karta asosiy maydonlari o'zgarmagan, shuning uchun turli asl o'qituvchili
        // darslar mudirda birlashib ketmaydi.
        $cards = $this->weeklyUnitCards($card);
        if ($denied = $this->mudirPlaceholderDenied($request, $cards)) {
            return $denied;
        }
        $q = TimetableCardOverride::whereIn('card_id', $cards->pluck('id'))->whereNotNull('mudir_placeholder');
        if (!empty($data['week'])) {
            $q->where('week', (int) $data['week']);
        }
        $rows = $q->get()->filter(fn ($o) => $this->ovPlaceholder($o));
        if ($rows->isEmpty()) {
            return response()->json(['error' => 'Bu dars vaqtincha kafedra mudirida emas.'], 422);
        }
        $ignore = array_fill_keys($cards->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $byId = $cards->keyBy('id');
        $errors = [];
        DB::beginTransaction();
        try {
            foreach ($rows as $o) {
                $ph = $this->ovPlaceholder($o);
                // Shu haftaning asl xonasi tiklanadi; joy — mudirga yozilgan joyda qoladi (to'qnashuv hal bo'lgan joy).
                $o->forceFill(['auditorium_code' => $ph['orig_room_code'] ?? null, 'auditorium_name' => $ph['orig_room_name'] ?? null,
                    'mudir_placeholder' => null])->save();
            }
            foreach ($rows as $o) {
                $c = $byId[$o->card_id] ?? TimetableCard::find($o->card_id);
                if (!$c || !$o->day || !$o->pair) {
                    continue;
                }
                $state = $this->calendarConflictState($c);
                $state['ignore'] = $ignore;
                foreach ($this->findWeekConflicts($c, (int) $o->week, (int) $o->day, (int) $o->pair, 0, $state) as $e) {
                    $errors[] = $o->week . '-hafta: ' . $e;
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

        return response()->json(['ok' => true, 'weeks' => $rows->pluck('week')->map(fn ($w) => (int) $w)->sort()->values()]);
    }
}

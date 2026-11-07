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
 *   hemisFallbackRoomFor bilan bir xil manba). Xona kafedraga bog'lanmaydi — bitta umumiy vaqtinchalik xona, dars xonasi
 *   aniqlashtirilguncha. Topilmasa — yozib bo'lmaydi (sababi bilan).
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
        $q = Teacher::headsOfDepartment($deptId)->where('is_active', true)->primaryHeadFirst();

        return (clone $q)->whereNotNull('hemis_id')->where('hemis_id', '>', 0)->first() ?? $q->first();
    }

    /** Karta uchun mudir va mudir xonasi: ['teacher' => Teacher|null, 'room' => [kod, nomi]|null, 'why' => sabab|null]. */
    private function mudirPlaceholderTarget(TimetableCard $card): array
    {
        $dept = $this->cardDepartmentId($card);
        $teacher = $this->kafedraMudirTeacher($dept);
        $room = $this->hemisFallbackRoomFor();
        $why = !$dept ? 'Fanning kafedrasi aniqlanmadi («' . trim((string) $card->kafedra_name) . '»).'
            : (!$teacher ? 'Kafedra mudiri topilmadi (shu kafedraga «kafedra_mudiri» roli berilgan xodim yo\'q).'
                : (!$room ? $this->hemisMissingRoomWarning() : null));

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
        // Bildirishnoma uchun sabab — shu haftadagi joridagi to'qnashuvlar (yozishdan oldin).
        $ov0 = TimetableCardOverride::where('card_id', $card->id)->where('week', $W)->first();
        [$d0, $p0] = $ov0 && $ov0->day && $ov0->pair ? [(int) $ov0->day, (int) $ov0->pair] : [(int) $card->day, (int) $card->pair];
        try {
            $clash = $d0 && $p0 ? $this->findWeekConflicts($card, $W, $d0, $p0) : [];
        } catch (\Throwable $e) {
            $clash = [];
        }
        $origRoom = $ov0?->auditorium_name ?: ($ov0?->auditorium_code ?: ($card->auditorium_name ?: $card->auditorium_code));
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

        $when = count($weeks) > 1 ? $weeks[0] . '–' . end($weeks) . '-haftalar' : $weeks[0] . '-hafta';
        $notified = $this->mudirPlaceholderNotify($card, (string) ($card->group_name ?: implode(', ', (array) $card->group_names)), $when, $clash,
            (string) $card->teacher_name, (string) $origRoom, $data['reason'] ?? null);

        return response()->json(['ok' => true, 'weeks' => $weeks, 'unit' => $this->unitPayload($cards, $W, false), 'notified' => $notified]);
    }

    /**
     * Yozilgach fan kafedrasi mudiri(lar)iga bildirishnoma (to'qnashuvni hal qilish — kafedraning ishi). Yuborilmasa — faqat logda,
     * yozish bekor qilinmaydi. Yuborilganlar F.I.Sh.
     */
    private function mudirPlaceholderNotify(TimetableCard $card, string $who, string $when, array $reasons, string $origTeacher, string $origRoom, ?string $note): array
    {
        try {
            $reasons = array_values(array_unique(array_filter(array_map('trim', $reasons))));
            $msg = trim((string) $card->subject_name) . ' — ' . $who . ', ' . $when . '.'
                . ' Sabab: ' . ($reasons ? implode('; ', $reasons) : 'to\'qnashuv') . '.'
                . ' Asl o\'qituvchi: ' . ($origTeacher !== '' ? $origTeacher : 'biriktirilmagan') . ', asl xona: ' . ($origRoom !== '' ? $origRoom : 'biriktirilmagan') . '.'
                . ' Iltimos, to\'g\'ri o\'qituvchi/xonani belgilang.' . (trim((string) $note) !== '' ? ' Izoh: ' . trim((string) $note) . '.' : '');

            return app(\App\Services\Timetable\TimetableChangeApproval::class)->notifyDepartmentHeadsNow($this->cardDepartmentId($card),
                'Dars vaqtincha sizning nomingizga yozildi — hal qilish kerak', $msg, (int) $card->board_id, 'kafedra mudiriga yozildi');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[MudirPlaceholder] bildirishnoma: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * POST cards/{card}/mudir-placeholder/revert: asl o'qituvchi va xona tiklanadi — yozilgan haftalarda bandlik tekshiriladi
     * (birlikning o'z bo'laklari hisobga olinmaydi); band bo'lsa — hech narsa o'zgarmaydi, sababi bilan.
     */
    public function mudirPlaceholderRevert(Request $request, TimetableCard $card)
    {
        // mode: original (sukut) — har hafta mudirga yozilishidan OLDINGI holatiga, bandlik tekshirilmaydi; here — shu joyda asl
        // o'qituvchi va xonaga (bandlik tekshiriladi, band bo'lsa rad etiladi). week berilsa — faqat shu hafta, aks holda barcha haftalar.
        $data = $request->validate(['week' => 'nullable|integer|min:1|max:60', 'mode' => 'nullable|in:original,here']);
        $mode = $data['mode'] ?? 'original';
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
        $weeks = $rows->pluck('week')->map(fn ($w) => (int) $w)->unique()->sort()->values();
        if ($mode === 'original') {
            DB::transaction(function () use ($rows) {
                foreach ($rows as $o) {
                    $ph = $this->ovPlaceholder($o);
                    if (empty($ph['had_override'])) {
                        // Oldin shu haftada yozuv yo'q edi — o'chiriladi: karta shablondagi (asl) joyiga yoki "Joylanmagan"ga qaytadi.
                        $o->delete();
                        continue;
                    }
                    // Oldingi yozuv aynan tiklanadi: kun/para (bo'sh bo'lsa — joylanmagan), shu haftaning xonasi; to'qnashuv bo'lsa ham.
                    $o->forceFill(['day' => $ph['orig_day'] ?? null, 'pair' => $ph['orig_pair'] ?? null, 'start_half' => 0, 'cancelled' => false,
                        'auditorium_code' => $ph['orig_room_code'] ?? null, 'auditorium_name' => $ph['orig_room_name'] ?? null, 'mudir_placeholder' => null])->save();
                }
            });

            return response()->json(['ok' => true, 'mode' => 'original', 'weeks' => $weeks]);
        }
        $ignore = array_fill_keys($cards->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $byId = $cards->keyBy('id');
        $errors = [];
        DB::beginTransaction();
        try {
            foreach ($rows as $o) {
                $ph = $this->ovPlaceholder($o);
                // Shu joyda: shu haftaning asl xonasi tiklanadi; joy — mudirga yozilgan joyda qoladi (to'qnashuv hal bo'lgan joy).
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
            $errors = array_values(array_unique($errors));

            return response()->json(['error' => "Shu joyda asl o'qituvchiga qaytarib bo'lmaydi — band: " . implode(' · ', $errors), 'reasons' => $errors], 422);
        }
        DB::commit();

        return response()->json(['ok' => true, 'mode' => 'here', 'weeks' => $weeks]);
    }

    // ===== Sikl: "Vaqtincha kafedra mudiriga yozish" (butun blok yoki faqat to'qnashuv sanalari) =====
    // Belgi joylashuvda: lecture_slots.mudir_ph[guruhcha] = {dates: null (butun blok) | [Y-m-d, ...], teacher_id, teacher_name, mudir_name,
    // hemis_id, room_code, room_name, orig_teacher_name, reason, by_*, at}. Karta o'qituvchisi/xonasi va smena o'zgarmaydi — qaytarish
    // belgini olib tashlaydi (aynan oldingi holat). Shu sanalardagi amaliy darslar mudir va mudir xonasida (cycleOccupancy), to'qnashuv
    // sanalmaydi (cycleConflicts), HEMIS'ga mudir nomiga (hemisCycleLessons).

    // Oqim ma'ruzasi: lecture_slots.mudir_ph_lec = {dates, teacher_id, teacher_name, mudir_name, hemis_id, room_code, room_name,
    // orig_teacher_name, orig_room_code, orig_room_name, lecture_ids (ma'ruza kartalari), units (oqim guruhchalari), reason, by_*, at} —
    // yozilgan blok joylashuvida; oqimdagi barcha guruhchalar uchun (lecture_ids bo'yicha — guruhchalar alohida joylangan bo'lsa ham).

    /** Oqim ma'ruzasi belgilari: ma'ruza kartasi id => belgi (+ placement_id) — doskadagi barcha joylashuvlardan. */
    private function cycleMudirPhLecMap(int $boardId): array
    {
        $map = [];
        foreach (\App\Models\TimetableCyclePlacement::where('board_id', $boardId)->where('lecture_slots', 'like', '%mudir_ph_lec%')->get(['id', 'lecture_slots']) as $p) {
            $ph = ((array) ($p->lecture_slots ?? []))['mudir_ph_lec'] ?? null;
            if (!is_array($ph) || !$ph) {
                continue;
            }
            foreach ((array) ($ph['lecture_ids'] ?? []) as $id) {
                $map[(int) $id] ??= $ph + ['placement_id' => (int) $p->id];
            }
        }

        return $map;
    }

    /**
     * Guruhcha oqimi ma'ruzasining belgisi: guruhcha belgining units ro'yxatida va fan (yo'nalish, kurs) bir xil — guruhchaning boshqa
     * sikl fanlari bu belgini olmaydi.
     */
    private function cycleMudirPhLecForUnit(array $map, string $unit, string $subject, string $specialty, int $course): ?array
    {
        $lc = fn ($v) => mb_strtolower(trim((string) $v));
        foreach ($map as $ph) {
            if (in_array($unit, array_map('strval', (array) ($ph['units'] ?? [])), true) && $lc($ph['subject'] ?? '') === $lc($subject)
                && $lc($ph['specialty'] ?? '') === $lc($specialty) && (int) ($ph['course'] ?? 0) === $course) {
                return $ph;
            }
        }

        return null;
    }

    /** Guruhcha (shift_g kaliti) uchun belgi. */
    private function cycleMudirPhFor($placement, string $unit): ?array
    {
        $ph = ((array) (((array) ($placement?->lecture_slots ?? []))['mudir_ph'] ?? []))[$unit] ?? null;

        return is_array($ph) && $ph ? $ph : null;
    }

    /** Belgi shu sanaga tegishlimi (dates bo'sh — butun blok). */
    private static function cyclePhOn(?array $ph, string $date): bool
    {
        return $ph !== null && (empty($ph['dates']) || in_array($date, (array) $ph['dates'], true));
    }

    /** Sikl birligi (key bo'yicha) va huquq: admin/registrator/kichik admin; kafedra mudiri — o'z kafedrasi. [birliklar, kalendar, indeks] | javob. */
    private function cycleMudirUnit(Request $request, $board, string $key)
    {
        $role = $this->timetableActiveRole($request);
        if ($this->timetableActor($request) && !in_array($role, ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin', 'kafedra_mudiri'], true)) {
            return response()->json(['error' => "Vaqtincha kafedra mudiriga yozish — admin, registrator va kafedra mudiri uchun."], 403);
        }
        [$units, $calendar] = $this->placedCycleUnits($board);
        $i = null;
        foreach ($units as $k => $u) {
            if ($u['key'] === $key) {
                $i = $k;
                break;
            }
        }
        if ($i === null || empty($units[$i]['cards'])) {
            return response()->json(['error' => 'Sikl bloki topilmadi (yoki joylanmagan).'], 404);
        }
        $cards = collect($units[$i]['cards'])->map(fn ($c) => $c instanceof TimetableCard ? $c : (new TimetableCard())->forceFill((array) $c));
        if ($denied = $this->denyForeignKafedra($request, $cards)) {
            return $denied;
        }

        return [$units, $calendar, $i, $cards];
    }

    /** POST boards/{board}/cycle-mudir-placeholder {key, scope: all|conflicts, reason?}. */
    public function cycleMudirPlaceholder(Request $request, \App\Models\TimetableBoard $board)
    {
        $data = $request->validate(['key' => 'required|string|max:500', 'scope' => 'nullable|in:all,conflicts', 'reason' => 'nullable|string|max:500']);
        $r = $this->cycleMudirUnit($request, $board, $data['key']);
        if (!is_array($r)) {
            return $r;
        }
        [$units, $calendar, $i, $cards] = $r;
        $u = $units[$i];
        $t = $this->mudirPlaceholderTarget($cards->first());
        if ($t['why']) {
            return response()->json(['error' => $t['why']], 422);
        }
        // Oqim ma'ruzasi (bo'lsa) — kartalari va oqim guruhchalari (belgi butun oqim uchun).
        $lecCards = $this->cycleFlowLectureCards($board, $u);
        $lecIds = $lecCards->pluck('id')->map(fn ($x) => (int) $x)->values()->all();
        $lc = fn ($v) => mb_strtolower(trim((string) $v));
        $flowUnits = array_values(array_unique(array_map(fn ($x) => (string) $x['unit'], array_filter($units, fn ($x) => $lc($x['specialty']) === $lc($u['specialty'])
            && (int) $x['course'] === (int) $u['course'] && $lc($x['parent']) === $lc($u['parent']) && $lc($x['subject']) === $lc($u['subject'])))));
        $items = $this->cycleConflicts($board, $units, $calendar)[$u['key']]['items'] ?? [];
        $scopeAll = ($data['scope'] ?? 'all') !== 'conflicts';
        $today = self::proposalToday();
        // Amaliy va ma'ruza sanalari: null — butun blok; [] — yozilmaydi.
        $pDates = $scopeAll ? null : [];
        $lDates = $scopeAll && $lecIds ? null : [];
        $lines = [];
        foreach ($items as $it) {
            if (!empty($it['past']) || empty($it['date_from'])) {
                continue;
            }
            $lines[] = (string) ($it['line'] ?? '');
            if ($scopeAll) {
                continue;
            }
            $lecture = !empty($it['lecture']);
            if ($lecture && !$lecIds) {
                continue;
            }
            foreach ($this->cycleDatesBetween($calendar, $it['date_from'], $it['date_to'], $u) as $d) {
                if ($d >= $today && $lecture) {
                    $lDates[$d] = true;
                } elseif ($d >= $today) {
                    $pDates[$d] = true;
                }
            }
        }
        if (!$scopeAll) {
            $pDates = array_keys($pDates);
            $lDates = array_keys($lDates);
            sort($pDates);
            sort($lDates);
            if (!$pDates && !$lDates) {
                return response()->json(['error' => "Bugundan keyingi to'qnashuv sanasi yo'q (o'tgan sanalarni o'zgartirib bo'lmaydi)."], 422);
            }
        }
        $writePractice = $pDates === null || $pDates;
        $writeLecture = $lecIds && ($lDates === null || $lDates);
        $actor = $this->timetableActor($request);
        $mudir = $t['teacher'];
        $first = $cards->first();
        $lec0 = $lecCards->first();
        $reason = trim((string) ($data['reason'] ?? '')) ?: null;
        $who = ['teacher_id' => (int) $mudir->id, 'teacher_name' => (string) ($mudir->short_name ?: $mudir->full_name),
            'mudir_name' => (string) $mudir->full_name, 'hemis_id' => $mudir->hemis_id ? (int) $mudir->hemis_id : null,
            'room_code' => $t['room'][0], 'room_name' => $t['room'][1]];
        $by = ['reason' => $reason, 'by_id' => $actor?->getKey(), 'by_name' => $actor?->full_name ?? $actor?->name, 'at' => now()->toDateTimeString()];
        DB::transaction(function () use ($u, $writePractice, $writeLecture, $pDates, $lDates, $who, $by, $first, $lec0, $lecIds, $flowUnits, $board) {
            $placement = \App\Models\TimetableCyclePlacement::lockForUpdate()->find($u['placement']->id);
            if ($writeLecture) {
                // Shu ma'ruzaning boshqa joylashuvlardagi eski belgisi — olib tashlanadi (bitta ma'ruza — bitta belgi).
                foreach (array_unique(array_column(array_intersect_key($this->cycleMudirPhLecMap((int) $board->id), array_flip($lecIds)), 'placement_id')) as $pid) {
                    if ((int) $pid !== (int) $placement->id && ($other = \App\Models\TimetableCyclePlacement::find($pid))) {
                        $os = (array) ($other->lecture_slots ?? []);
                        unset($os['mudir_ph_lec']);
                        $other->update(['lecture_slots' => $os]);
                    }
                }
            }
            $slots = (array) ($placement->lecture_slots ?? []);
            if ($writePractice) {
                $slots['mudir_ph'] = (array) ($slots['mudir_ph'] ?? []);
                $slots['mudir_ph'][(string) $u['unit']] = ['dates' => $pDates] + $who + [
                    'orig_teacher_name' => $first->teacher_name, 'orig_room_name' => $first->auditorium_name] + $by;
            }
            if ($writeLecture) {
                $slots['mudir_ph_lec'] = ['dates' => $lDates] + $who + [
                    'orig_teacher_name' => $lec0?->teacher_name, 'orig_room_code' => $lec0?->auditorium_code, 'orig_room_name' => $lec0?->auditorium_name,
                    'lecture_ids' => $lecIds, 'units' => $flowUnits,
                    'subject' => (string) $u['subject'], 'specialty' => (string) $u['specialty'], 'course' => (int) $u['course']] + $by;
            }
            $placement->update(['lecture_slots' => $slots]);
        });
        request()->attributes->remove('timetable.cycle_units.' . $board->id);

        // Bildirishnoma (fan kafedrasi mudiri): sanalar yoki butun blok, sabab — to'qnashuv matni, asl o'qituvchi va xona.
        $fmt = fn (array $ds) => implode(', ', array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('d.m'), $ds));
        $parts = [];
        if ($writePractice) {
            $parts[] = 'amaliy — ' . ($pDates === null ? 'butun blok' : $fmt($pDates));
        }
        if ($writeLecture) {
            $parts[] = "ma'ruza (oqim: " . $u['parent'] . ') — ' . ($lDates === null ? 'butun blok' : $fmt($lDates));
        }
        $orig = array_filter([$writePractice ? ($first->teacher_name ?: 'biriktirilmagan') . ' (amaliy)' : null,
            $writeLecture ? (($lec0?->teacher_name) ?: 'biriktirilmagan') . " (ma'ruza)" : null]);
        $origRoom = array_filter([$writePractice ? ($first->auditorium_name ?: $first->auditorium_code ?: 'biriktirilmagan') . ' (amaliy)' : null,
            $writeLecture ? (($lec0?->auditorium_name ?: $lec0?->auditorium_code) ?: 'biriktirilmagan') . " (ma'ruza)" : null]);
        $notified = $this->mudirPlaceholderNotify($first, (string) $u['group'], implode('; ', $parts), $lines, implode(', ', $orig), implode(', ', $origRoom), $reason);

        return response()->json(['ok' => true, 'dates' => $writePractice ? $pDates : [], 'lecture_dates' => $writeLecture ? $lDates : [],
            'lecture' => (bool) $writeLecture, 'notified' => $notified]);
    }

    /** POST boards/{board}/cycle-mudir-placeholder/revert {key}: belgi olib tashlanadi — oldingi holat (bandlik tekshirilmaydi). */
    public function cycleMudirPlaceholderRevert(Request $request, \App\Models\TimetableBoard $board)
    {
        $data = $request->validate(['key' => 'required|string|max:500']);
        $r = $this->cycleMudirUnit($request, $board, $data['key']);
        if (!is_array($r)) {
            return $r;
        }
        [$units, , $i] = $r;
        $u = $units[$i];
        $placement = \App\Models\TimetableCyclePlacement::find($u['placement']->id);
        $slots = (array) ($placement->lecture_slots ?? []);
        $hasPractice = isset(((array) ($slots['mudir_ph'] ?? []))[(string) $u['unit']]);
        // Oqim ma'ruzasi belgisi — shu guruhcha oqimi ma'ruzasini qamragan joylashuv(lar)da (istalgan guruhcha blokidan qaytariladi).
        $lecIds = $this->cycleFlowLectureCards($board, $u)->pluck('id')->map(fn ($x) => (int) $x)->all();
        $lecPlacements = array_unique(array_column(array_intersect_key($this->cycleMudirPhLecMap((int) $board->id), array_flip($lecIds)), 'placement_id'));
        if (!$hasPractice && !$lecPlacements) {
            return response()->json(['error' => 'Bu blok vaqtincha kafedra mudirida emas.'], 422);
        }
        DB::transaction(function () use ($placement, $hasPractice, $lecPlacements, $u) {
            if ($hasPractice) {
                $slots = (array) ($placement->fresh()->lecture_slots ?? []);
                unset($slots['mudir_ph'][(string) $u['unit']]);
                if (!$slots['mudir_ph']) {
                    unset($slots['mudir_ph']);
                }
                $placement->update(['lecture_slots' => $slots]);
            }
            foreach ($lecPlacements as $pid) {
                if ($p = \App\Models\TimetableCyclePlacement::find($pid)) {
                    $ps = (array) ($p->lecture_slots ?? []);
                    unset($ps['mudir_ph_lec']);
                    $p->update(['lecture_slots' => $ps]);
                }
            }
        });
        request()->attributes->remove('timetable.cycle_units.' . $board->id);

        return response()->json(['ok' => true, 'lecture' => (bool) $lecPlacements]);
    }
}

<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * "O'qituvchi kun/hafta jadvali" (GET boards/{board}/teacher-agenda): o'qituvchining shu kundagi (yoki haftadagi) barcha darslari
 * butun doska bo'yicha (filtrdan qat'i nazar) — haftalik kartalar (hafta o'zgarishlari bilan), sikl amaliyotlari (guruhcha smenasi,
 * kunlik reja) va sikl ma'ruzalari; bo'sh paralar; tanlangan darsni ("move") bo'sh paraga ko'chirish variantlari.
 *
 * Qamrov: admin (va o'quv bo'limi) — hammasi; kafedra mudiri / fan mas'uli / o'qituvchi — o'z qamrovi (Takliflar huquqi bilan bir
 * xil: suggestionRights). Qamrovdan tashqari dars — faqat vaqti ("band: boshqa fakultet").
 *
 * Ko'chirish: haftalik — Takliflar bajaruvchisi (admin — week-suggestions/apply, darhol; boshqalar — week-suggestions/propose, sabab
 * va tasdiq zanjiri), shu hafta yoki "shu va keyingi haftalar"; sikl amaliyoti — shu kunning boshlanish soati (cycle-practice-starts
 * scope=day) yoki butun blokning kelgusi kunlari (scope=rest); kun o'zgarmaydi. O'qituvchi / fan mas'uli — cycle-time-proposal.
 * Bugungi va o'tgan darslar ko'chirilmaydi.
 */
trait TimetableTeacherAgenda
{
    private const AGENDA_DOW = [1 => 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'];

    private const AGENDA_ROMAN = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'];

    public function teacherAgenda(Request $request, TimetableBoard $board)
    {
        $data = $request->validate([
            // Xona bandligi oynasi: teacher_id o'rniga room_code — xonaning shu kun/hafta darslari va bo'sh paralari (faqat ko'rish).
            'teacher_id' => 'required_without:room_code|nullable|integer|min:1',
            'room_code' => 'nullable|string|max:50',
            'date' => 'required|date_format:Y-m-d',
            'range' => 'nullable|in:day,week',
            'layer' => 'nullable|in:internal,external',
            'move' => 'nullable|string|max:500',
            // "Shu va keyingi haftalar" tekshiruvi — tanlangan bo'sh para ("Y-m-d|para"): har hafta alohida tekshiriladi (og'ir).
            'rest_for' => 'nullable|string|max:40',
        ]);
        $roomCode = empty($data['teacher_id']) ? (string) ($data['room_code'] ?? '') : null;
        $tid = (int) ($data['teacher_id'] ?? 0);
        $teacher = $roomCode === null ? Teacher::find($tid) : null;
        $roomObj = $roomCode !== null ? \App\Models\Auditorium::where('code', $roomCode)->first() : null;
        abort_unless($teacher || $roomObj, 404, $roomCode !== null ? 'Xona topilmadi.' : "O'qituvchi topilmadi.");
        $range = $data['range'] ?? 'day';
        $layer = $data['layer'] ?? 'internal';
        $day = Carbon::parse($data['date'])->startOfDay();
        $monday = $day->copy()->startOfWeek(Carbon::MONDAY);
        $days = $range === 'week'
            ? array_map(fn ($i) => $monday->copy()->addDays($i)->toDateString(), range(0, max(1, min(7, (int) ($board->days ?: 6))) - 1))
            : [$day->toDateString()];
        // Xona reytingi xaritalari so'rov boshida yangilanadi (controller nusxasi uzoq yashashi mumkin).
        $this->roomRankReset();
        $rights = $this->suggestionRights($this->timetableActor($request));
        $slots = $this->hemisSlotTimes($board);
        $pairs = $this->agendaPairs($slots);
        $entries = $this->agendaEntries($board, $tid, $days, $layer, $rights, $slots, $roomCode);
        $free = [];
        foreach ($days as $d) {
            $free[$d] = array_values(array_map(fn ($p) => $p['no'],
                array_filter($pairs, fn ($p) => !$this->agendaBusyAt($entries, $d, $p['from'], $p['to']))));
        }
        $move = !empty($data['move']) && $roomCode === null
            ? $this->agendaMove($request, $board, (string) $data['move'], $entries, $days, $pairs, $free, $layer, $rights, $data['rest_for'] ?? null, $tid) : null;
        // Sarlavha: o'qituvchi — to'liq F.I.Sh. (bo'sh bo'lsa qisqa nomi); xona — to'liq nomi, binosi va sig'imi.
        $short = (string) (TimetableCard::where('board_id', $board->id)->where('teacher_id', $tid)->whereNotNull('teacher_name')->value('teacher_name')
            ?: ($teacher?->short_name ?: $teacher?->full_name));
        $name = $roomCode !== null
            ? trim((string) ($roomObj->name ?: $roomObj->code)) . ($roomObj->building_name ? ' · ' . $roomObj->building_name : '')
                . ($roomObj->volume ? ' (' . (int) $roomObj->volume . " o'rin)" : '')
            : (trim((string) $teacher->full_name) ?: $short);
        $phone = $teacher && $this->teacherPhoneVisible($request, $teacher) ? self::formatUzPhone($teacher->phone) : null;

        return response()->json([
            'teacher' => $roomCode === null ? ['id' => $tid, 'name' => $short, 'full_name' => trim((string) $teacher->full_name) ?: $short,
                'short_name' => (string) ($teacher->short_name ?: $short), 'phone' => $phone['text'] ?? null, 'phone_tel' => $phone['tel'] ?? null] : null,
            'room' => $roomCode !== null ? ['code' => $roomObj->code, 'name' => $roomObj->name, 'volume' => (int) $roomObj->volume,
                'building' => $roomObj->building_name] : null,
            'date' => $day->toDateString(), 'range' => $range, 'layer' => $layer, 'today' => self::proposalToday(),
            'title' => $name . ' — ' . ($range === 'week'
                ? Carbon::parse($days[0])->format('d.m') . '–' . Carbon::parse(end($days))->format('d.m.Y')
                : $day->format('d.m.Y') . ' (' . self::AGENDA_DOW[$day->dayOfWeekIso] . ')'),
            'dates' => array_map(fn ($d) => ['date' => $d, 'dow' => Carbon::parse($d)->dayOfWeekIso,
                'label' => Carbon::parse($d)->format('d.m') . ' (' . self::AGENDA_DOW[Carbon::parse($d)->dayOfWeekIso] . ')'], $days),
            'pairs' => $pairs,
            'entries' => $entries,
            'free' => $free,
            'move' => $move,
        ]);
    }

    /**
     * O'qituvchi telefoni kimga ko'rinadi: admin, registrator ofisi, fan mas'uli — hammasi; kafedra mudiri — o'z kafedrasi
     * o'qituvchilari; boshqa rollar (o'qituvchi) — faqat o'ziniki.
     */
    private function teacherPhoneVisible(Request $request, Teacher $teacher): bool
    {
        $actor = $this->timetableActor($request);
        if (!$actor) {
            return true;   // ichki chaqiruv (konsol/test)
        }
        $role = $this->timetableActiveRole($request);
        if (in_array($role, ['admin', 'superadmin', 'registrator_ofisi', 'fan_masuli'], true)) {
            return true;
        }
        if ($role === 'kafedra_mudiri') {
            $own = $this->departmentHeadKafedraSafe($request);
            $depts = array_filter(array_merge([(string) $teacher->department],
                \Illuminate\Support\Facades\Schema::hasTable('teacher_employments')
                    ? DB::table('teacher_employments')->where('teacher_id', $teacher->id)->pluck('department_name')->all() : []));
            foreach ($depts as $d) {
                if ($own && $this->sameKafedra($d, $own)) {
                    return true;
                }
            }

            return (int) $actor->getKey() === (int) $teacher->id;
        }

        return (int) $actor->getKey() === (int) $teacher->id;
    }

    /** "+998 90 123 45 67" (ko'rinish) va "+998901234567" (tel:); raqam bo'lmasa — null. */
    public static function formatUzPhone(?string $raw): ?array
    {
        $d = preg_replace('/\D+/', '', (string) $raw);
        if ($d === '') {
            return null;
        }
        if (strlen($d) === 9) {
            $d = '998' . $d;
        }
        if (strlen($d) === 12 && str_starts_with($d, '998')) {
            return ['text' => '+998 ' . substr($d, 3, 2) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 2) . ' ' . substr($d, 10, 2), 'tel' => '+' . $d];
        }

        return ['text' => trim((string) $raw), 'tel' => '+' . ltrim($d, '+')];
    }

    /** Paralar (08:30 … 17:50): ikki akademik soat — bitta para; [no, from, to (soat, [from, to)), start, end, label]. */
    private function agendaPairs(array $slots): array
    {
        $out = [];
        for ($k = 1; isset($slots[2 * $k - 1], $slots[2 * $k]); $k++) {
            $out[] = ['no' => $k, 'from' => 2 * $k - 1, 'to' => 2 * $k + 1, 'start' => $slots[2 * $k - 1][0], 'end' => $slots[2 * $k][1],
                'label' => (self::AGENDA_ROMAN[$k - 1] ?? $k) . ' para'];
        }

        return $out;
    }

    private function agendaBusyAt(array $entries, string $date, int $from, int $to): bool
    {
        foreach ($entries as $e) {
            if ($e['date'] === $date && $e['from'] < $to && $from < $e['to']) {
                return true;
            }
        }

        return false;
    }

    /**
     * O'qituvchining shu sanalardagi darslari (tartiblangan): date, dow, from/to (soat), start/end, kind (weekly|cycle), type (M|A),
     * subject, groups, room, room_code, key, card_ids, week (haftalik), unit (sikl amaliyoti), layer, conflict, hidden, label.
     * Bitta oqim ma'ruzasi (bir nechta guruhcha yoki yo'nalish kartasi; fan, xona, vaqt bir xil) — bitta yozuv.
     */
    private function agendaEntries(TimetableBoard $board, int $tid, array $days, string $layer, array $rights, array $slots, ?string $room = null): array
    {
        $dates = array_flip($days);
        $rh = max(1, $this->cycleRowHours($board));
        $raw = [];
        // Xona rejimi: kartaning o'z xonasi yoki biror haftada shu xona berilgan kartalar; keyin effektiv xona bo'yicha.
        $roomIds = $room !== null && Schema::hasColumn('timetable_card_overrides', 'auditorium_code')
            ? DB::table('timetable_card_overrides')->where('auditorium_code', $room)->pluck('card_id')->all() : [];
        $rows = $this->cardRows($board, fn ($q) => $room !== null
            ? $q->where(fn ($w) => $w->where('auditorium_code', $room)->orWhereIn('id', $roomIds))
            : $q->where('teacher_id', $tid));
        if ($rows) {
            $ov = $this->overrideRows($board, fn ($q) => $q->whereIn('o.card_id', array_keys($rows)));
            if ($layer === 'external') {
                // Tashqi qatlam: karta tashqi joyi (ext_day / ext_pair), hafta o'zgarishlarisiz.
                $ext = Schema::hasColumn('timetable_cards', 'ext_day')
                    ? DB::table('timetable_cards')->whereIn('id', array_keys($rows))->get(['id', 'ext_day', 'ext_pair'])->keyBy('id') : collect();
                foreach ($rows as $id => $r) {
                    $r->day = $ext[$id]->ext_day ?? null;
                    $r->pair = $ext[$id]->ext_pair ?? null;
                }
                $ov = [];
            }
            foreach ($this->weeklyOccupancy($board, ['cards' => $rows, 'ov' => $ov], $dates) as $e) {
                if ($room !== null && (string) $e['room'] !== $room) {
                    continue;
                }
                $c = $rows[$e['card']];
                // Haftalik qator (yarim-para) → akademik soat.
                $from = ($e['from'] - 1) * $rh + 1;
                $to = ($e['to'] - 1) * $rh + 1;
                $raw[] = ['date' => $e['date'], 'from' => $from, 'to' => $to, 'kind' => 'weekly', 'lecture' => $c->training_type === 'lecture',
                    'subject' => (string) $c->subject_name, 'groups' => $this->cardGroupsOf($c), 'room' => $e['room_name'] ?: null, 'room_code' => $e['room'],
                    'key' => 'w|' . $e['card'] . '|' . $e['week'], 'card_ids' => [(int) $e['card']], 'week' => (int) $e['week'], 'unit' => null,
                    'layer' => $layer, 'cards' => [$c]];
            }
        }
        // Sikl: o'qituvchi darslari bo'lgan joylangan birliklar (amaliyot va oqim ma'ruzasi).
        [$units, $calendar] = $this->placedCycleUnits($board);
        $mineUnits = $room !== null ? array_values($units) : array_values(array_filter($units, fn ($u) => collect($u['cards'])->contains(fn ($c) => (int) $c->teacher_id === $tid)
            || collect($this->cycleUnitLectureCards($board, $u))->contains(fn ($c) => (int) $c->teacher_id === $tid)));
        if ($mineUnits) {
            $lecCards = [];
            foreach ($this->cycleOccupancy($board, $mineUnits, $calendar) as $e) {
                if (($room !== null ? (string) $e['room'] !== $room : (int) $e['teacher_id'] !== $tid) || !isset($dates[$e['date']])) {
                    continue;
                }
                $u = $mineUnits[$e['unit']];
                $lecture = str_starts_with((string) $e['load_key'], 'l');
                $card = collect($u['cards'])->first(fn ($c) => (int) $c->id === (int) $e['card'])
                    ?? ($lecCards[(int) $e['card']] ??= TimetableCard::find((int) $e['card']));
                $raw[] = ['date' => $e['date'], 'from' => (int) $e['from'], 'to' => (int) $e['to'], 'kind' => 'cycle', 'lecture' => $lecture,
                    'subject' => (string) $u['subject'], 'groups' => array_values(array_unique(array_column($this->focusUnitGroups($u), 'group'))),
                    'room' => $e['room_name'] ?: null, 'room_code' => $e['room'],
                    // Ma'ruza kaliti vaqti bilan: bitta karta guruhchalarda har xil soatda bo'lsa — alohida yozuvlar.
                    'key' => $lecture ? 'l|' . $e['lesson'] . '|' . $e['date'] . '|' . $e['from'] : 'c|' . $u['key'] . '|' . $e['date'],
                    'card_ids' => [(int) $e['card']], 'week' => null, 'unit' => $lecture ? null : [
                        'key' => $u['key'], 'unit' => (string) $u['unit'], 'specialty_name' => (string) $u['specialty'], 'course' => (int) $u['course'],
                        'subject_name' => (string) $u['subject'], 'group_name' => (string) $u['group']],
                    'layer' => 'internal', 'cards' => $card ? [$card] : []];
            }
        }
        // Bitta oqim ma'ruzasi — bitta yozuv (guruhlar birlashadi): bitta ma'ruza kartasi (kalit) yoki turli yo'nalish kartalari — fan,
        // xona va vaqt bir xil (cycleConflicts dagidek; xonasiz ma'ruza oqimligi tasdiqlanmaydi — alohida dars). Qamrov har qism (karta)
        // bo'yicha: tafsilotlar (fan, guruhlar, kartalar, kalit) — faqat qamrovdagi qismlardan; qolgani faqat bandlikka (vaqt).
        // flow_size — oqimdagi kartalar soni (qamrovdan qat'i nazar): ko'p kartali haftalik oqim bu oynadan ko'chirilmaydi.
        $merged = [];
        foreach ($raw as $x) {
            $vis = $x['cards'] && collect($x['cards'])->contains(fn ($c) => $this->suggestionInScope($rights, $c));
            $k = $x['lecture'] && $x['room_code'] ? implode('|', ['L', $x['date'], $x['from'], $x['to'], (string) $x['room_code'],
                $this->normSubject($this->cycleSubjectVariantBase($x['subject']) ?? $x['subject'])]) : $x['key'];
            $m = &$merged[$k];
            if ($m === null) {
                $m = ['visible' => false, 'groups' => [], 'card_ids' => [], 'all_ids' => []] + $x;
                $m['groups'] = [];
                $m['card_ids'] = [];
            }
            $m['all_ids'] = array_values(array_unique(array_merge($m['all_ids'], $x['card_ids'])));
            if ($vis) {
                if (!$m['visible']) {
                    // Birinchi qamrovdagi qism — yozuv fani, kaliti va boshqa tafsilotlari undan.
                    $m = array_replace($m, array_intersect_key($x, array_flip(['subject', 'key', 'week', 'unit', 'room', 'room_code', 'lecture'])));
                }
                $m['visible'] = true;
                $m['groups'] = array_values(array_unique(array_merge($m['groups'], $x['groups'])));
                $m['card_ids'] = array_values(array_unique(array_merge($m['card_ids'], $x['card_ids'])));
            }
            unset($m);
        }
        $list = array_values($merged);
        usort($list, fn ($a, $b) => [$a['date'], $a['from'], $a['key']] <=> [$b['date'], $b['from'], $b['key']]);
        $today = self::proposalToday();
        $out = [];
        foreach ($list as $i => $x) {
            $conflict = false;
            foreach ($list as $j => $y) {
                if ($i !== $j && $y['date'] === $x['date'] && $y['from'] < $x['to'] && $x['from'] < $y['to']) {
                    $conflict = true;
                    break;
                }
            }
            $visible = $x['visible'];
            $dow = Carbon::parse($x['date'])->dayOfWeekIso;
            $base = ['date' => $x['date'], 'dow' => $dow, 'from' => $x['from'], 'to' => $x['to'],
                'start' => $slots[$x['from']][0] ?? null, 'end' => $slots[$x['to'] - 1][1] ?? null,
                'kind' => $x['kind'], 'layer' => $x['layer'], 'conflict' => $conflict, 'past' => $x['date'] <= $today];
            $out[] = $visible
                ? $base + ['hidden' => false, 'type' => $x['lecture'] ? 'M' : 'A', 'subject' => $x['subject'], 'groups' => $x['groups'],
                    'room' => $x['room'], 'room_code' => $x['room_code'], 'key' => $x['key'], 'card_ids' => $x['card_ids'], 'week' => $x['week'],
                    'unit' => $x['unit'], 'flow_size' => count($x['all_ids']),
                'label' => $x['subject'] . ' (' . ($x['lecture'] ? 'M' : 'A') . ') — ' . implode(', ', $x['groups'])]
                // Qamrovdan tashqari — faqat vaqti.
                : $base + ['hidden' => true, 'type' => null, 'subject' => null, 'groups' => [], 'room' => null, 'room_code' => null,
                    'key' => null, 'card_ids' => [], 'week' => null, 'unit' => null, 'label' => 'band: boshqa fakultet'];
        }

        return $out;
    }

    /** Sikl birligining oqim ma'ruzasi kartalari (o'qituvchi ma'ruzachi bo'lsa ham agendaga kirsin). */
    private function cycleUnitLectureCards(TimetableBoard $board, array $u): array
    {
        static $memo = [];
        $k = $board->id . '|' . mb_strtolower((string) $u['specialty']) . '|' . (int) $u['course'] . '|' . mb_strtolower((string) $u['subject']);

        return $memo[$k] ??= TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture')
            ->where('specialty_name', $u['specialty'])->where('course', $u['course'])->where('subject_name', $u['subject'])
            ->get(['id', 'teacher_id'])->all();
    }

    /**
     * Ko'chiriladigan dars ($key — agenda yozuvi kaliti) va har bo'sh para uchun natija: options[sana][para] = {ok, why, room};
     * sikl — block[sana][para] (butun blokning kelgusi kunlari). via — apply (darhol) | propose (so'rov); why — ko'chirib bo'lmasa.
     */
    private function agendaMove(Request $request, TimetableBoard $board, string $key, array $entries, array $days, array $pairs, array $free,
        string $layer, array $rights, ?string $restFor = null, int $tid = 0): ?array
    {
        // Kalit — yozuvniki; birlashgan haftalik ma'ruza oqimida istalgan karta kaliti ("w|karta|hafta") ham shu yozuvni topadi — faqat
        // shu kartaning shu sanadagi haftasi kalitdagi hafta bo'lsa (boshqa haftaga o'tilganda boshqa hafta darsi tanlanmasin).
        [$kind, $kid, $kweek] = array_pad(explode('|', $key), 3, '');
        $entry = collect($entries)->first(fn ($e) => $e['key'] === $key)
            ?? ($kind === 'w' ? collect($entries)->first(function ($e) use ($board, $kid, $kweek) {
                $card = $e['kind'] === 'weekly' && in_array((int) $kid, $e['card_ids'], true) ? TimetableCard::find((int) $kid) : null;

                return $card && $this->calendarWeekAt($board, $card, $e['date']) === (int) $kweek;
            }) : null);
        // Shu haftada joylanmagan haftalik karta ("Joylanmagan kartalar" panelidan): bo'sh paralarga joylash — xuddi ko'chirishdek.
        if (!$entry && $kind === 'w' && ($pc = TimetableCard::find((int) $kid)) && (int) $pc->board_id === (int) $board->id
            && (!$tid || (int) $pc->teacher_id === $tid) && $pc->training_type !== 'lecture' && (int) $kweek >= 1) {
            $ovw = TimetableCardOverride::where('card_id', $pc->id)->where('week', (int) $kweek)->first();
            $placed = $ovw ? (!$ovw->cancelled && $ovw->day && $ovw->pair) : ($pc->day && $pc->pair);
            if (!$placed && !($ovw && $ovw->cancelled)) {
                $first = $days[0] ?? self::proposalToday();
                $entry = ['kind' => 'weekly', 'label' => $pc->subject_name . ' (A) — ' . implode(', ', $this->cardGroupsOf($pc)) . ' (joylanmagan)',
                    'date' => $first, 'dow' => Carbon::parse($first)->dayOfWeekIso, 'from' => 1, 'to' => 1 + $pc->lenHalf(), 'week' => (int) $kweek,
                    'card_ids' => [(int) $pc->id], 'unit' => null, 'past' => false, 'flow_size' => 1, 'type' => 'A', 'unplaced' => true];
            }
        }
        if (!$entry) {
            return ['key' => $key, 'kind' => null, 'via' => null, 'why' => "Ko'chiriladigan dars bu kunlarda yo'q.", 'options' => (object) [], 'block' => (object) []];
        }
        $out = ['key' => $key, 'kind' => $entry['kind'], 'label' => $entry['label'], 'date' => $entry['date'], 'from' => $entry['from'],
            'to' => $entry['to'], 'week' => $entry['week'], 'card_id' => $entry['card_ids'][0] ?? null, 'unit' => $entry['unit'],
            'via' => null, 'why' => null, 'options' => [], 'block' => [], 'block_date' => null, 'rest_weeks' => []];
        $role = $this->timetableActiveRole($request);
        $why = match (true) {
            $layer === 'external' => "Tashqi qatlam — faqat ko'rish.",
            $entry['past'] => "Bugungi va o'tgan darslar ko'chirilmaydi.",
            $entry['kind'] === 'cycle' && $entry['type'] === 'M' => "Sikl ma'ruzasi bu oynadan ko'chirilmaydi (ma'ruza soati — sikl jadvalida).",
            // Bir nechta kartali haftalik ma'ruza oqimi — bitta karta ko'chsa oqim bo'linib qoladi.
            $entry['kind'] === 'weekly' && ($entry['flow_size'] ?? count($entry['card_ids'])) > 1 => "Bir nechta kartadan iborat ma'ruza oqimi bu oynadan ko'chirilmaydi — oqim bo'linib qolmasin (jadvaldan yoki Takliflar orqali).",
            default => null,
        };
        if ($why) {
            return ['why' => $why] + $out;
        }
        $today = self::proposalToday();
        if ($entry['kind'] === 'weekly') {
            $card = TimetableCard::with('board')->findOrFail($out['card_id']);
            $rh = max(1, $this->cycleRowHours($board));
            $row = fn (int $hour) => intdiv($hour - 1, $rh) + 1;
            // Huquq: Takliflar bilan bir xil (admin — darhol, boshqalar — so'rov).
            $check = $this->suggestionApplyCheck($rights, $board, ['card_id' => $card->id, 'actions' => [['op' => 'move', 'card_id' => $card->id,
                'week' => $entry['week'], 'day' => $entry['dow'], 'pair' => $row($entry['from'])]]], null, null, false);
            if ($check['mode'] === null) {
                return ['why' => $check['why']] + $out;
            }
            $out['via'] = $check['mode'] === 'direct' ? 'apply' : 'propose';
            $out['rest_weeks'] = $this->agendaRestWeeks($card, (int) $entry['week']);
            foreach ($days as $d) {
                if ($d <= $today || $this->calendarWeekAt($board, $card, $d) !== (int) $entry['week']) {
                    continue;
                }
                foreach ($pairs as $p) {
                    if (in_array($p['no'], $free[$d], true)) {
                        // Maqsad: shu haftaning kuni va para qatori (Takliflar "move" amali uchun).
                        $at = ['day' => Carbon::parse($d)->dayOfWeekIso, 'pair' => $row($p['from'])];
                        $out['options'][$d][$p['no']] = $this->agendaWeeklyOption($board, $card, (int) $entry['week'], $at['day'], $at['pair']) + $at;
                    }
                }
            }
            // "Shu va keyingi haftalar": tanlangan para (va taklif qilingan xona) keyingi har haftada ham tekshiriladi.
            if ($restFor !== null && count($out['rest_weeks']) > 1) {
                [$rd, $rp] = array_pad(explode('|', $restFor), 2, '');
                $opt = $out['options'][$rd][(int) $rp] ?? null;
                $out['rest'] = ['date' => $rd, 'pair' => (int) $rp] + ($opt && $opt['ok']
                    ? $this->agendaWeeklyRestCheck($board, $card, $out['rest_weeks'], (int) $entry['week'], $opt)
                    : ['ok' => false, 'why' => $opt['why'] ?? "Bu vaqtga ko'chirib bo'lmaydi."]);
            }
        } else {
            $u = $entry['unit'];
            $teacherFlow = in_array($this->timetableActiveRole($request), TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true);
            $cards = collect($this->cycleProposalUnits($board, $u + ['units' => [$u['unit']]], null))->flatMap(fn ($x) => $x['u']['cards']);
            $allowed = $teacherFlow
                ? $cards->contains(fn ($c) => $rights['tid'] > 0 && ((int) $c->teacher_id === $rights['tid'] || $this->responsibleMatch($rights['keys'], $c)))
                : $this->cycleLayerEditable($role, 'internal') && ($rights['level'] === 'admin' || ($rights['kafedra']
                    && $cards->every(fn ($c) => $this->sameKafedra($c->kafedra_name, $rights['kafedra']))));
            if (!$allowed) {
                return ['why' => "Bu darsni ko'chirish huquqingiz yo'q."] + $out;
            }
            $out['via'] = $teacherFlow ? 'propose' : 'apply';
            // Butun blok: kelgusi (ertangi va keyingi) amaliy kunlaridan birinchisi.
            $x = $this->cycleProposalUnits($board, $u + ['units' => [$u['unit']]], null)[0] ?? null;
            $future = $x ? array_values(array_filter(array_keys($x['prac']), fn ($d) => $d > $today)) : [];
            sort($future);
            $out['block_date'] = $future[0] ?? null;
            if (in_array($entry['date'], $days, true)) {
                foreach ($pairs as $p) {
                    if (!in_array($p['no'], $free[$entry['date']], true)) {
                        continue;
                    }
                    $base = $u + ['units' => [$u['unit']], 'start_hour' => $p['from']];
                    $at = ['start_hour' => $p['from']];
                    $out['options'][$entry['date']][$p['no']] = $this->agendaCycleOption($board, $base + ['date' => $entry['date'], 'scope' => 'day']) + $at;
                    $out['block'][$entry['date']][$p['no']] = ($out['block_date']
                        ? $this->agendaCycleOption($board, $base + ['date' => $out['block_date'], 'scope' => 'rest'])
                        : ['ok' => false, 'why' => "Blokning kelgusi kunlari yo'q.", 'room' => null]) + $at;
                }
            }
        }
        $out['options'] = (object) $out['options'];
        $out['block'] = (object) $out['block'];

        return $out;
    }

    /** "Shu va keyingi haftalar": shu haftadan boshlab dars xuddi shu joyida (bekor qilinmagan) turgan haftalar (ko'pi bilan 20). */
    private function agendaRestWeeks(TimetableCard $card, int $week): array
    {
        $grid = $this->cardGrid($card->board, $card);
        $ovs = TimetableCardOverride::where('card_id', $card->id)->get()->keyBy('week');
        $place = function (int $w) use ($card, $ovs) {
            $o = $ovs[$w] ?? null;
            if ($o && $o->cancelled) {
                return null;
            }

            return $o && $o->day && $o->pair ? [(int) $o->day, (int) $o->pair] : ($card->day && $card->pair ? [(int) $card->day, (int) $card->pair] : null);
        };
        $here = $place($week);
        $out = [];
        for ($w = $week; $here && $w <= (int) $grid['weeks'] && count($out) < 20; $w++) {
            if ($place($w) === $here) {
                $out[] = $w;
            }
        }

        return $out;
    }

    /**
     * Haftalik darsni shu haftada (kun, para) ga ko'chirish: guruh, o'qituvchi, xona (haftalik va sikl), kutilayotgan so'rovlar,
     * amaliy blok qoidasi va xona sig'imi. Faqat xona muammosi bo'lsa — reyting bo'yicha shu vaqtda bo'sh va sig'adigan xona.
     */
    private function agendaWeeklyOption(TimetableBoard $board, TimetableCard $card, int $week, int $day, int $pair): array
    {
        $grid = $this->cardGrid($board, $card);
        if ($pair + $card->lenHalf() - 1 > (int) $grid['pairs']) {
            return ['ok' => false, 'why' => 'Tanlangan vaqt jadval chegarasidan tashqarida.', 'room' => null];
        }
        [$errors, $roomCode, $students] = $this->agendaWeekErrors($board, $card, $week, $day, $pair);
        if (!$errors) {
            return ['ok' => true, 'why' => null, 'room' => null];
        }
        $roomOnly = !array_filter($errors, fn ($m) => !str_starts_with($m, 'Xona '));
        $alt = $roomOnly ? $this->agendaFreeRoom($board, $card, $week, $day, $pair, $students, $roomCode) : null;

        return $alt
            ? ['ok' => true, 'why' => implode(' · ', $errors), 'room' => $alt]
            : ['ok' => false, 'why' => implode(' · ', $errors), 'room' => null];
    }

    /**
     * Haftalik darsni shu haftada (kun, para) ga ko'chirish sabablari — barchasi (bittasi topilsa ham qolganlari: xona muammosida boshqa
     * xona faqat xonadan boshqa sabab bo'lmasa taklif qilinadi): guruh, o'qituvchi, xona (haftalik va sikl), kutilayotgan so'rovlar,
     * amaliy blok qoidasi, xona sig'imi. $room — boshqa xona bilan. Qaytaradi: [sabablar, xona kodi, talabalar].
     */
    private function agendaWeekErrors(TimetableBoard $board, TimetableCard $card, int $week, int $day, int $pair, ?Auditorium $room = null): array
    {
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1)->toDateString();
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        $roomCode = $room ? (string) $room->code : (string) ($ov?->auditorium_code ?: $card->auditorium_code);
        $data = ['week' => $week, 'day' => $day, 'pair' => $pair] + ($room ? ['auditorium_code' => $roomCode] : []);
        $errors = array_merge($this->findWeekConflicts($card, $week, $day, $pair, 0, null, $room ? $roomCode : null),
            $this->pendingTimeConflicts($card, $data, []), $this->practiceSplitConflicts($card, $week, $day, $pair),
            $this->weeklyProposalBusy($card, $week, $day, $pair, $date, $room));
        $room ??= $roomCode !== '' ? Auditorium::where('code', $roomCode)->first() : null;
        $students = $this->cardStudents($card);
        if ($room && (int) $room->volume > 0 && $students > (int) $room->volume) {
            $errors[] = self::capacityLine((string) ($room->name ?: $room->code), (int) $room->volume, $students);
        }

        return [array_values(array_unique(array_map(fn ($m) => $this->agendaReasonText($m), $errors))), $roomCode, $students];
    }

    /** "Shu va keyingi haftalar": shu kun/para (va taklif qilingan xona) har keyingi haftada; birinchi to'siq — sababi bilan. */
    private function agendaWeeklyRestCheck(TimetableBoard $board, TimetableCard $card, array $weeks, int $selected, array $opt): array
    {
        $room = !empty($opt['room']['code']) ? Auditorium::where('code', $opt['room']['code'])->first() : null;
        foreach ($weeks as $w) {
            if ((int) $w === $selected) {
                continue;
            }
            [$errors] = $this->agendaWeekErrors($board, $card, (int) $w, (int) $opt['day'], (int) $opt['pair'], $room);
            if ($errors) {
                $date = $this->weekStartDate($board, $card, (int) $w)->addDays((int) $opt['day'] - 1)->format('d.m');

                return ['ok' => false, 'why' => $w . '-hafta (' . $date . '): ' . implode(' · ', $errors)];
            }
        }

        return ['ok' => true, 'why' => null];
    }

    /** Sabab matni: "Auditoriya band: X — …" → "Xona band: X — …"; "Xona 215 band: …" → "Xona band: 215 — …" ("Xona sig'maydi: …" — o'zgarmaydi). */
    private function agendaReasonText(string $m): string
    {
        if (preg_match('/^Xona (\S+) band: (.*)$/u', $m, $x)) {
            return 'Xona band: ' . $x[1] . ' — ' . $x[2];
        }

        return preg_replace('/^Auditoriya band: /u', 'Xona band: ', $m);
    }

    /** Shu vaqtda bo'sh va sig'adigan xona (reyting bo'yicha birinchisi): {code, name, volume, why}. */
    private function agendaFreeRoom(TimetableBoard $board, TimetableCard $card, int $week, int $day, int $pair, int $students, string $current): ?array
    {
        $base = $this->roomRankBase();
        $fit = $base['active']->filter(fn ($r) => (string) $r->code !== $current && (int) $r->volume > 0 && (int) $r->volume >= $students
            && $this->auditoriumAllowedForCard($r, $card, $base['teacher']));
        $tries = 0;
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1)->toDateString();
        foreach ($this->rankRooms($card, $fit, $current, $this->roomHabitForCard($board, $card)) as [$r, $why]) {
            if (++$tries > 15) {
                break;
            }
            // Haftalik darslar bilan (findWeekConflicts), kutilayotgan so'rovlardagi xona broni va sikl darslari bilan
            // (weeklyProposalBusy — shu xona bilan sinab).
            $busy = array_merge(array_filter($this->findWeekConflicts($card, $week, $day, $pair, 0, null, (string) $r->code),
                fn ($m) => str_starts_with($m, 'Auditoriya band')),
                $this->pendingTimeConflicts($card, ['week' => $week, 'day' => $day, 'pair' => $pair, 'auditorium_code' => (string) $r->code], [], ['room']));
            if (!$busy) {
                $busy = array_filter($this->weeklyProposalBusy($card, $week, $day, $pair, $date, $r), fn ($m) => str_starts_with($m, 'Xona '));
            }
            if (!$busy) {
                return ['code' => (string) $r->code, 'name' => (string) $r->name, 'volume' => (int) $r->volume, 'why' => $why];
            }
        }

        return null;
    }

    /** Sikl amaliyoti: shu kun (yoki blokning kelgusi kunlari) uchun yangi boshlanish soati — so'rovdagi tekshiruv bilan bir xil. */
    private function agendaCycleOption(TimetableBoard $board, array $p): array
    {
        try {
            $this->validateCyclePracticeProposal($board, $p);
        } catch (ValidationException $e) {
            $msgs = [];
            foreach (explode(' · ', implode(' · ', $e->validator->errors()->all())) as $m) {
                $msgs[] = $this->agendaReasonText($m);
            }

            return ['ok' => false, 'why' => implode(' · ', array_values(array_unique($msgs))), 'room' => null];
        }

        return ['ok' => true, 'why' => null, 'room' => null];
    }
}

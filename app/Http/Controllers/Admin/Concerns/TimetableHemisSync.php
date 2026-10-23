<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use App\Services\HemisSync\SchedulePlanner;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registrator ofisi: LMS ichki dars jadvali + KTR ↔ HEMIS dars jadvali solishtirish.
 * Faqat o'qiydi va taklif beradi (HEMIS'ga yozish — alohida bosqich, registrator tasdig'i bilan).
 */
trait TimetableHemisSync
{
    /** HEMIS'dagi para vaqtlari (lesson_pair): to'liq va yarim paralar. 17:10–17:50 yarim parasi yo'q. */
    public const HEMIS_PAIRS = [
        '08:30-09:50' => 11, '10:00-11:20' => 12, '12:00-13:20' => 13, '13:30-14:50' => 14, '15:00-16:20' => 15, '16:30-17:50' => 16,
        '08:30-09:10' => 25, '09:10-09:50' => 26, '10:00-10:40' => 17, '10:40-11:20' => 22, '12:00-12:40' => 20, '12:40-13:20' => 27,
        '13:30-14:10' => 18, '14:10-14:50' => 24, '15:00-15:40' => 21, '15:40-16:20' => 23, '16:30-17:10' => 19,
    ];

    public function hemisSyncCompare(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403, 'Faqat registrator ofisi uchun.');
        }
        $data = $request->validate([
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'groups'            => 'nullable|array',
            'groups.*'          => 'string|max:255',
            'today'             => 'nullable|date',
        ]);
        $today = !empty($data['today']) && in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true)
            ? Carbon::parse($data['today'])->toDateString()
            : now()->toDateString();

        return response()->json($this->hemisSyncReport($board, $data, $today));
    }

    /** Yarim-slot vaqtlari [slot => [start, end]] doskaning qo'ng'iroq jadvalidan. */
    private function hemisSlotTimes(TimetableBoard $board): array
    {
        $bell = $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day);
        $pairs = array_values(array_filter((array) $bell, fn ($it) => ($it['type'] ?? 'pair') === 'pair' && !empty($it['start']) && !empty($it['end'])));
        $out = [];
        $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $fmt = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $half = count($pairs) >= 10; // har element allaqachon yarim para
        $i = 1;
        foreach ($pairs as $p) {
            $s = $toMin($p['start']);
            $e = $toMin($p['end']);
            if ($half || $e - $s <= 45) {
                $out[$i++] = [$fmt($s), $fmt($e)];
            } else {
                $out[$i++] = [$fmt($s), $fmt($s + 40)];
                $out[$i++] = [$fmt($s + 40), $fmt($e)];
            }
        }

        return array_slice($out, 0, 12, true);
    }

    /** Fan kaliti: kichik harf, tutuq belgisiz, kirill a/b/c (bo'lak harfi) lotinga. */
    private function hemisSubjectKey(string $name): string
    {
        $n = trim($name);
        if (preg_match('/^(.*?)\s*\(\s*([a-zа-яё])\s*\)\s*$/iu', $n, $m)) {
            $l = mb_strtolower($m[2]);
            $l = ['а' => 'a', 'б' => 'b', 'в' => 'c', 'с' => 'c', 'ц' => 'c'][$l] ?? $l;

            return $this->normSubject($m[1]) . ' (' . $l . ')';
        }

        return $this->normSubject($n);
    }

    private function hemisSubjectBase(string $name): string
    {
        return $this->normSubject((string) preg_replace('/\s*\(\s*[a-zа-яё]\s*\)\s*$/iu', '', trim($name)));
    }

    private function hemisPartOf(string $typeName): ?string
    {
        $n = mb_strtolower($typeName);
        foreach (['mustaqil', 'amaliyot', 'kurs ishi', 'самостоят', 'практика', 'курсов'] as $skip) {
            if (str_contains($n, $skip)) {
                return null;
            }
        }

        return preg_match("/ma['’ʻ`]?ruza|лекц/u", $n) ? 'L' : 'A';
    }

    private function hemisSyncReport(TimetableBoard $board, array $scope, string $today): array
    {
        [$facSet, $specSet, $courseSet] = $this->scopeSets($scope);
        $q = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        $cards = $q->get();

        // Sikl fanlari bu bosqichda yo'q (ularning kunlari sikl kalendarida).
        $cycleSubjects = [];
        foreach ($this->subjectSettingsFor($board) as $st) {
            if (($st['mode'] ?? '') === 'cycle') {
                $cycleSubjects[$this->specKey($st['specialty_name']) . '|' . (int) $st['course'] . '|' . $this->normSubject((string) $st['subject_name'])] = true;
            }
        }
        $isCycle = function (TimetableCard $c) use ($cycleSubjects): bool {
            $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
            $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

            return isset($cycleSubjects[$k . $this->normSubject((string) $c->subject_name)])
                || ($base !== null && isset($cycleSubjects[$k . $this->normSubject($base)]));
        };
        $cycleCards = $cards->filter($isCycle);
        $cards = $cards->reject($isCycle)->values();

        $slotTimes = $this->hemisSlotTimes($board);
        $startToSlot = [];
        $endToSlot = [];
        foreach ($slotTimes as $s => [$st, $en]) {
            $startToSlot[$st] = $s;
            $endToSlot[$en] = $s;
        }
        $toSlots = function (string $start, string $end) use ($startToSlot, $endToSlot): array {
            $a = $startToSlot[substr($start, 0, 5)] ?? null;
            $b = $endToSlot[substr($end, 0, 5)] ?? null;

            return ($a && $b && $b >= $a) ? range($a, $b) : [];
        };

        // Haftalar: kartaning panjarasi (boshlanish sanasi, tanaffuslar) bo'yicha.
        $gset = TimetableGridSetting::where('board_id', $board->id)->get();
        $gridFor = function (TimetableCard $c) use ($gset, $board) {
            $g = $gset->first(fn ($g) => (string) $g->faculty_name === (string) $c->faculty_name && $this->specKey($g->specialty_name) === $this->specKey($c->specialty_name) && (int) $g->course === (int) $c->course)
                ?? $gset->first(fn ($g) => !$g->faculty_name && $this->specKey($g->specialty_name) === $this->specKey($c->specialty_name) && (int) $g->course === (int) $c->course);

            return ['weeks' => (int) ($g->weeks ?? $board->weeks ?: 15), 'days' => (int) ($g->days ?? $board->days ?: 6)];
        };
        $weekCache = [];
        $weekMonday = function (TimetableCard $c, int $w) use ($board, &$weekCache): string {
            $k = $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course . '|' . $w;

            return $weekCache[$k] ??= $this->weekStartDate($board, $c, $w)->toDateString();
        };

        $overrides = Schema::hasTable('timetable_card_overrides')
            ? DB::table('timetable_card_overrides')->whereIn('card_id', $cards->pluck('id'))->get()->groupBy('card_id')
            : collect();
        $hasOvRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
        $teacherHemis = Teacher::whereIn('id', $cards->pluck('teacher_id')->filter()->unique())->pluck('hemis_id', 'id')->all();

        // Kartaning haftalik darslari (ichki jadval): [date, week, slot, len, part, employee..., room...].
        $cardLessons = function (TimetableCard $c) use ($overrides, $hasOvRoom, $gridFor, $weekMonday, $teacherHemis): array {
            $ov = ($overrides[$c->id] ?? collect())->keyBy('week');
            $out = [];
            $g = $gridFor($c);
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $o = $ov[$w] ?? null;
                if ($o && $o->cancelled) {
                    continue;
                }
                $day = $o ? $o->day : $c->day;
                $pair = $o ? $o->pair : $c->pair;
                if (!$day || !$pair) {
                    continue;
                }
                $room = ($hasOvRoom && $o && $o->auditorium_code) ? [$o->auditorium_code, $o->auditorium_name] : [$c->auditorium_code, $c->auditorium_name];
                $out[] = [
                    'date' => Carbon::parse($weekMonday($c, $w))->addDays((int) $day - 1)->toDateString(),
                    'week' => $w, 'slot' => (int) $pair, 'len' => $c->lenHalf(),
                    'part' => $c->training_type === 'lecture' ? 'L' : 'A',
                    'employee_id' => $c->teacher_id ? ($teacherHemis[$c->teacher_id] ?? null) : null,
                    'employee_name' => $c->teacher_id ? $c->teacher_name : null,
                    'auditorium_code' => $room[0] ?: null, 'auditorium_name' => $room[1] ?: null,
                ];
            }

            return $out;
        };

        // Guruhlar: kartalardagi guruhcha nomlari. Nom fakultetlararo takrorlanadi ("1K-01a" ikki davolash
        // fakultetida) — kalit fakultet + nom. HEMIS'dagi nom til qo'shimchasi bilan ("… (rus)") ham, usiz ham
        // bo'lishi mumkin: avval to'liq nomi, so'ng qo'shimchasiz nomi bo'yicha; bir xil nomlilardan — o'z fakulteti.
        $cleanGroup = fn ($n) => trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', (string) $n));
        $facKey = fn ($f) => mb_strtolower(trim((string) $f));
        $cardGroups = fn (TimetableCard $c) => array_values(array_filter(array_map(fn ($n) => trim((string) $n), $c->occupiedGroups())));
        $groupKey = fn (TimetableCard $c, string $raw) => $facKey($c->faculty_name) . '|' . $this->groupNameKey($raw);
        $groupNames = [];
        foreach ($cards as $c) {
            foreach ($cardGroups($c) as $raw) {
                $groupNames[$groupKey($c, $raw)] ??= ['name' => $cleanGroup($raw), 'raw' => $raw, 'card' => $c];
            }
        }
        if (!empty($scope['groups'])) {
            $want = [];
            foreach ($scope['groups'] as $g) {
                $want[$this->groupNameKey($g)] = true;
                $want[$this->groupNameKey($cleanGroup($g))] = true;
            }
            $groupNames = array_filter($groupNames, fn ($g) => isset($want[$this->groupNameKey($g['raw'])]) || isset($want[$this->groupNameKey($g['name'])]));
        }
        $groupRows = DB::table('groups')
            ->whereIn('name', array_values(array_unique(array_merge(array_column($groupNames, 'raw'), array_column($groupNames, 'name')))))
            ->get()->groupBy(fn ($g) => $this->groupNameKey($g->name));
        $resolveGroup = function (array $g) use ($groupRows, $facKey) {
            $cands = $groupRows[$this->groupNameKey($g['raw'])] ?? $groupRows[$this->groupNameKey($g['name'])] ?? collect();
            $own = $cands->first(fn ($r) => $facKey($r->department_name) === $facKey($g['card']->faculty_name));

            return $own ?? ($cands->pluck('group_hemis_id')->unique()->count() === 1 ? $cands->first() : null);
        };

        $kuzgi = $board->semester_parity !== 'bahorgi';
        $fallbackRoom = Auditorium::where('name', 'like', '%kafedra mudiri%')->orderBy('id')->first();
        $mudirCache = [];
        $mudirFor = function ($deptId) use (&$mudirCache) {
            if (!$deptId) {
                return null;
            }
            if (!array_key_exists($deptId, $mudirCache)) {
                $t = Teacher::headsOfDepartment($deptId)->where('is_active', true)->first()
                    ?? Teacher::where('role', 'kafedra_mudiri')->where('department_hemis_id', $deptId)->where('is_active', true)->first();
                $mudirCache[$deptId] = $t && $t->hemis_id ? [(int) $t->hemis_id, $t->full_name] : null;
            }

            return $mudirCache[$deptId];
        };
        $hasCsActive = Schema::hasColumn('curriculum_subjects', 'is_active');
        $planner = new SchedulePlanner($slotTimes, $today, array_fill_keys(array_keys(self::HEMIS_PAIRS), true));

        $groupsOut = [];
        $missingGroups = [];
        foreach ($groupNames as $gk => $gInfo) {
            ['name' => $gname, 'card' => $sample] = $gInfo;
            $grp = $resolveGroup($gInfo);
            if (!$grp) {
                $missingGroups[] = $gname;
                continue;
            }
            $course = (int) $sample->course;
            $semCode = (string) ($kuzgi ? 10 + 2 * $course - 1 : 10 + 2 * $course);
            $csQ = DB::table('curriculum_subjects')->where('curricula_hemis_id', $grp->curriculum_hemis_id)->where('semester_code', $semCode);
            if ($hasCsActive) {
                $csQ->where('is_active', true);
            }
            $csRows = $csQ->get();
            $ktrPlans = Schema::hasTable('ktr_plans')
                ? DB::table('ktr_plans')->whereIn('curriculum_subject_id', $csRows->pluck('id'))->get()->keyBy('curriculum_subject_id')
                : collect();
            $hemisAll = DB::table('schedules')->where('group_id', $grp->group_hemis_id)->where('semester_code', $semCode)
                ->whereNull('deleted_at')->whereNotNull('lesson_date')
                ->get(['schedule_hemis_id', 'subject_id', 'subject_name', 'training_type_code', 'training_type_name', 'lesson_date',
                    'lesson_pair_start_time', 'lesson_pair_end_time', 'employee_id', 'employee_name', 'auditorium_code', 'auditorium_name', 'updated_at']);

            $gCards = $cards->filter(fn ($c) => in_array($gk, array_map(fn ($n) => $groupKey($c, $n), $cardGroups($c)), true))->values();
            $usedCards = [];
            $units = [];
            $unitMeta = [];
            $handledSubjects = [];
            $skipped = [];
            foreach ($csRows as $cs) {
                $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
                $codes = [];
                $codeNames = [];
                foreach ((array) $details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $part = $this->hemisPartOf((string) ($d['trainingType']['name'] ?? ''));
                    if ($code !== '' && $part !== null && (int) ($d['academic_load'] ?? 0) > 0) {
                        $codes[$code] = $part;
                        $codeNames[$code] = (string) $d['trainingType']['name'];
                    }
                }
                if (!$codes) {
                    continue;
                }
                $key = $this->hemisSubjectKey((string) $cs->subject_name);
                $base = $this->hemisSubjectBase((string) $cs->subject_name);
                $matched = $gCards->filter(function ($c) use ($key, $base) {
                    $ck = $this->hemisSubjectKey((string) $c->subject_name);
                    if ($ck === $key) {
                        return true;
                    }
                    // Bo'lakli fanning umumiy ma'ruzasi asosiy nom bilan.
                    return $c->training_type === 'lecture' && $this->variantLetter((string) $c->subject_name) === null && $ck === $base;
                });
                $hemisRows = $hemisAll->where('subject_id', $cs->subject_id);
                if ($matched->isEmpty()) {
                    if ($hemisRows->isNotEmpty() || $cycleCards->contains(fn ($c) => $this->hemisSubjectBase((string) $c->subject_name) === $base)) {
                        $skipped[] = ['subject_name' => $cs->subject_name,
                            'reason' => $cycleCards->contains(fn ($c) => $this->hemisSubjectBase((string) $c->subject_name) === $base)
                                ? 'sikl fani — keyingi bosqichda' : 'LMS doskasida kartochka yo\'q — HEMIS\'ga tegilmaydi'];
                    }
                    continue;
                }
                foreach ($matched as $c) {
                    $usedCards[$c->id] = true;
                }
                $plan = [];
                foreach ($matched as $c) {
                    array_push($plan, ...$cardLessons($c));
                }
                if (!array_filter($plan, fn ($p) => $p['date'] > $today)) {
                    $skipped[] = ['subject_name' => $cs->subject_name, 'reason' => 'LMS ichki jadvalida ertadan keyin dars yo\'q — HEMIS\'ga tegilmaydi'];
                    continue;
                }
                $handledSubjects[(int) $cs->subject_id] = true;
                $kp = $ktrPlans[$cs->id] ?? null;
                $ktr = null;
                if ($kp) {
                    $pd = is_string($kp->plan_data) ? json_decode($kp->plan_data, true) : $kp->plan_data;
                    $hours = (array) ($pd['hours'] ?? $pd ?? []);
                    $ktr = [];
                    for ($w = 1; $w <= (int) $kp->week_count; $w++) {
                        $ktr[] = array_map('intval', (array) ($hours[$w] ?? $hours[(string) $w] ?? []));
                    }
                }
                $first = $matched->first();
                $currentWeek = 0;
                for ($w = 1; $w <= $gridFor($first)['weeks']; $w++) {
                    if ($weekMonday($first, $w) <= $today) {
                        $currentWeek = $w;
                    }
                }
                $mudir = $mudirFor($cs->department_id);
                $units[] = [
                    'key' => $gk . '|' . $cs->id,
                    'subject_id' => (int) $cs->subject_id,
                    'subject_name' => $cs->subject_name,
                    'base' => $base,
                    'codes' => $codes, 'code_names' => $codeNames,
                    'ktr' => $ktr,
                    'current_week' => $currentWeek,
                    'hemis' => $hemisRows->map(fn ($r) => [
                        'id' => (int) $r->schedule_hemis_id, 'date' => substr((string) $r->lesson_date, 0, 10),
                        'start' => substr((string) $r->lesson_pair_start_time, 0, 5), 'end' => substr((string) $r->lesson_pair_end_time, 0, 5),
                        'code' => (string) $r->training_type_code, 'employee_id' => $r->employee_id, 'employee_name' => $r->employee_name,
                        'auditorium_code' => $r->auditorium_code, 'auditorium_name' => $r->auditorium_name,
                    ])->values()->all(),
                    'plan' => $plan,
                    'fallback_employee' => $mudir,
                    'fallback_room' => $fallbackRoom ? [$fallbackRoom->code, $fallbackRoom->name] : null,
                ];
                $unitMeta[$gk . '|' . $cs->id] = ['kafedra' => $cs->department_name, 'cs_id' => $cs->id, 'mudir' => $mudir[1] ?? null];
            }

            // Guruhning boshqa bandligi: rejaga kirmagan HEMIS darslari va LMS'da fani topilmagan kartalar.
            $busy = [];
            foreach ($hemisAll as $r) {
                $d = substr((string) $r->lesson_date, 0, 10);
                if ($d <= $today || isset($handledSubjects[(int) $r->subject_id])) {
                    continue;
                }
                foreach ($toSlots((string) $r->lesson_pair_start_time, (string) $r->lesson_pair_end_time) as $s) {
                    $busy[$d][$s] = true;
                }
            }
            $unmatchedCards = [];
            foreach ($gCards as $c) {
                if (isset($usedCards[$c->id])) {
                    continue;
                }
                $unmatchedCards[$c->subject_name] = true;
                foreach ($cardLessons($c) as $l) {
                    for ($i = 0; $i < $l['len']; $i++) {
                        $busy[$l['date']][$l['slot'] + $i] = true;
                    }
                }
            }

            // Ertadan semestr oxirigacha dars kunlari.
            $workDates = [];
            $g = $gridFor($sample);
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $mon = Carbon::parse($weekMonday($sample, $w));
                for ($d = 0; $d < $g['days']; $d++) {
                    $ds = $mon->copy()->addDays($d)->toDateString();
                    if ($ds > $today) {
                        $workDates[$ds] = $w;
                    }
                }
            }

            $results = $units ? $planner->planGroup($units, $busy, $workDates) : [];
            foreach ($results as &$r) {
                $r += $unitMeta[$r['key']] ?? [];
                unset($r['records']);
            }
            unset($r);

            $groupsOut[] = [
                'group' => $gname,
                'group_hemis_id' => (int) $grp->group_hemis_id,
                'curriculum_hemis_id' => (int) $grp->curriculum_hemis_id,
                'semester_code' => $semCode,
                'imported_at' => $hemisAll->max('updated_at'),
                'units' => $results,
                'skipped' => $skipped,
                'cards_without_subject' => array_keys($unmatchedCards),
            ];
        }

        return [
            'today' => $today,
            'board_id' => $board->id,
            'fallback_room' => $fallbackRoom ? ['code' => $fallbackRoom->code, 'name' => $fallbackRoom->name] : null,
            'hemis_pairs' => self::HEMIS_PAIRS,
            'groups' => $groupsOut,
            'groups_not_in_hemis' => $missingGroups,
        ];
    }
    /** Solishtirish natijasi Excelda: "Balans" (fan + guruh) va "Amallar" (HEMIS'da qilinadigan o'zgarishlar). */
    public function hemisSyncExport(Request $request, TimetableBoard $board)
    {
        $resp = $this->hemisSyncCompare($request, $board);
        $rep = $resp->getData(true);

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $bal = $book->getActiveSheet();
        $bal->setTitle('Balans');
        $bal->fromArray(['Guruh', 'Fan', 'Kafedra', 'Dars turi', 'KTR jami (soat)', 'KTR joriy haftagacha', 'HEMIS bugungacha',
            'Farq oldin (soat)', 'Farq keyin (soat)', 'Qism', 'KTR soni', 'HEMIS soni (bugungacha)', 'Farq soni oldin', 'Farq soni keyin',
            'Tuzatishlar', 'Izohlar'], null, 'A1');
        $act = $book->createSheet();
        $act->setTitle('Amallar');
        $act->fromArray(['Guruh', 'Fan', 'Amal', 'Sana', 'Boshlanish', 'Tugash', 'HEMIS para', 'Dars turi', 'O\'qituvchi', 'Xona',
            'HEMIS id', 'O\'zgarishlar', 'Belgilar', 'To\'siq'], null, 'A1');
        $opName = ['create' => 'Qo\'shish', 'update' => 'O\'zgartirish', 'delete' => 'O\'chirish'];
        $r1 = 2;
        $r2 = 2;
        foreach ($rep['groups'] as $g) {
            foreach ($g['units'] as $u) {
                $fixes = implode("\n", array_map(fn ($f) => $f['text'], $u['fixes']));
                $notes = implode("\n", $u['notes']);
                $first = true;
                foreach ($u['code_names'] as $code => $name) {
                    $part = $u['codes'][$code];
                    $b = $u['before'];
                    $a = $u['after'];
                    $bal->fromArray([$g['group'], $u['subject_name'], $u['kafedra'] ?? '', $name,
                        $b['ktr_hours'][$code] ?? 0, $b['ktr_now_hours'][$code] ?? 0, $b['past_hours'][$code] ?? 0,
                        $b['hours_diff'][$code] ?? 0, $a['hours_diff'][$code] ?? 0,
                        $part === 'L' ? 'Ma\'ruza' : 'Amaliy qism', $b['ktr_count'][$part] ?? 0, $b['past_count'][$part] ?? 0,
                        $b['count_diff'][$part] ?? 0, $a['count_diff'][$part] ?? 0,
                        $first ? $fixes : '', $first ? $notes : ''], null, 'A' . $r1++);
                    $first = false;
                }
                foreach ($u['actions'] as $x) {
                    $act->fromArray([$g['group'], $u['subject_name'], $opName[$x['op']] ?? $x['op'], $x['date'], $x['start'], $x['end'],
                        self::HEMIS_PAIRS[$x['start'] . '-' . $x['end']] ?? '', $u['code_names'][$x['code']] ?? $x['code'],
                        $x['employee_name'] ?? '', $x['auditorium_name'] ?? '', $x['hemis_id'] ?? '',
                        implode('; ', $x['changes'] ?? []), implode(', ', $x['flags'] ?? []), implode('; ', $x['blocked'] ?? [])], null, 'A' . $r2++);
                }
            }
        }
        foreach ([$bal, $act] as $sh) {
            $sh->getStyle('A1:P1')->getFont()->setBold(true);
            $sh->freezePane('A2');
            foreach (range('A', 'P') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);

        return response()->streamDownload(fn () => $writer->save('php://output'), 'hemis_solishtirish_' . $rep['today'] . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}

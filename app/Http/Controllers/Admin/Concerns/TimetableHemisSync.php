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
            // Faqat shu haftagacha (shu hafta oxirigacha) yoziladi; bo'sh/0 — semestr oxirigacha.
            'until_week'        => 'nullable|integer|min:0|max:60',
            // Hisob davri (haftalar): shu oraliqdagi KTR / HEMIS / LMS yig'indilari. Sukut: 1 — joriy hafta.
            'period_from'       => 'nullable|integer|min:1|max:60',
            'period_to'         => 'nullable|integer|min:1|max:60',
            // Excel: faqat farqi borlar va tanlangan amal turlari.
            'only_diff'         => 'nullable|boolean',
            'ops'               => 'nullable|array',
            // ops_filter=1 — amal turlari tanlangan (bo'sh ro'yxat ham: hech biri); yo'q bo'lsa — filtrsiz.
            'ops_filter'        => 'nullable|boolean',
            'ops.*'             => 'in:create,update,delete,blocked',
        ]);
        $today = !empty($data['today']) && in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true)
            ? Carbon::parse($data['today'])->toDateString()
            : now()->toDateString();

        return response()->json($this->hemisSyncReport($board, $data, $today));
    }

    /**
     * Solishtirishdan oldin: HEMIS jadvali LMS'ga oxirgi marta qachon import qilingani va
     * qo'lda boshlangan importning holati. Solishtirish har doim shu import (LMS bazasi)
     * bilan ishlaydi — HEMIS'ning o'ziga bu tugma murojaat qilmaydi.
     */
    public function hemisSyncImportStatus(Request $request, TimetableBoard $board)
    {
        $this->hemisSyncDenyUnlessRegistrar($request);
        // Oxirgi to'liq va xatosiz import. U hali qayd etilmagan bo'lsa (yangilanishdan keyingi birinchi
        // kechgacha) — yozuvlarning eng so'nggi yangilanishi, "taxminiy" belgisi bilan.
        $full = \Illuminate\Support\Facades\Cache::get(\App\Services\ScheduleImportService::LAST_FULL_IMPORT_KEY);
        $last = $full ?? DB::table('schedules')->whereNull('deleted_at')
            ->where('lesson_date', '>=', now()->subDays(14)->startOfDay())
            ->where('lesson_date', '<=', now()->addDays(120)->endOfDay())
            ->max('updated_at');
        $state = \Illuminate\Support\Facades\Cache::get(\App\Jobs\ImportSchedulesWithProgressJob::PROGRESS_KEY);
        $running = $state && in_array($state['status'] ?? null, ['queued', 'running'], true)
            && !empty($state['updated_at']) && Carbon::parse($state['updated_at'])->gt(now()->subMinutes(10));

        return response()->json([
            'last_import_at' => $last,
            'last_import_approx' => $full === null && $last !== null,
            'job' => $state,
            'running' => $running,
        ]);
    }

    /** HEMIS'dan joriy semestr jadvalini qayta import qilishni boshlash ("Dars jadval mosligi" bilan bir xil job). */
    public function hemisSyncImportStart(Request $request, TimetableBoard $board)
    {
        $this->hemisSyncDenyUnlessRegistrar($request);

        return app(\App\Http\Controllers\Admin\ReportController::class)->startScheduleImport();
    }

    private function hemisSyncDenyUnlessRegistrar(Request $request): void
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)) {
            abort(403, 'Faqat registrator ofisi uchun.');
        }
    }

    /**
     * Davr (haftalar oralig'i) bo'yicha yig'indilar: KTR (shu haftalar qatorlari), HEMIS (shu sanalardagi
     * barcha yozuvlar) va LMS ichki jadvali. Soat — dars turi bo'yicha (LMS — qism bo'yicha), soni — qism
     * bo'yicha alohida kunlar.
     */
    private function hemisPeriodStats(array $u, int $fromWeek, int $toWeek, string $fromDate, string $toDate): array
    {
        $part = fn ($code) => $u['codes'][(string) $code] ?? null;
        $ktrH = [];
        $ktrC = ['L' => 0, 'A' => 0];
        foreach ((array) ($u['ktr'] ?? []) as $i => $row) {
            if ($i + 1 < $fromWeek || $i + 1 > $toWeek) {
                continue;
            }
            $sum = ['L' => 0, 'A' => 0];
            foreach ((array) $row as $code => $h) {
                if ($p = $part($code)) {
                    $ktrH[(string) $code] = ($ktrH[(string) $code] ?? 0) + (int) $h;
                    $sum[$p] += (int) $h;
                }
            }
            foreach ($sum as $p => $h) {
                if ($h > 0) $ktrC[$p]++;
            }
        }
        $hemH = [];
        $hemDays = ['L' => [], 'A' => []];
        foreach ((array) $u['hemis'] as $r) {
            $p = $part($r['code']);
            if (!$p || $r['date'] < $fromDate || $r['date'] > $toDate) {
                continue;
            }
            [$h1, $m1] = array_map('intval', explode(':', substr($r['start'], 0, 5)));
            [$h2, $m2] = array_map('intval', explode(':', substr($r['end'], 0, 5)));
            $hemH[(string) $r['code']] = ($hemH[(string) $r['code']] ?? 0) + max(1, (int) round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 40));
            $hemDays[$p][$r['date']] = true;
        }
        $lmsH = ['L' => 0, 'A' => 0];
        $lmsDays = ['L' => [], 'A' => []];
        foreach ((array) $u['plan'] as $l) {
            if ($l['date'] < $fromDate || $l['date'] > $toDate || !in_array($l['part'], array_values($u['codes']), true)) {
                continue;
            }
            $lmsH[$l['part']] += max(1, (int) $l['len']);
            $lmsDays[$l['part']][$l['date']] = true;
        }

        return [
            'from_week' => $fromWeek, 'to_week' => $toWeek, 'from' => $fromDate, 'to' => $toDate,
            'ktr_hours' => $ktrH, 'ktr_count' => $ktrC,
            'hemis_hours' => $hemH, 'hemis_count' => array_map('count', $hemDays),
            'lms_hours' => $lmsH, 'lms_count' => array_map('count', $lmsDays),
        ];
    }

    /** Fan "mos": amal yo'q (tanlangan haftadan keyingilari ham), semestr balansi va davr yig'indisi (HEMIS = KTR) to'g'ri. */
    private function hemisUnitMatches(array $u): bool
    {
        if (!empty($u['actions']) || !empty($u['actions_later']) || !empty($u['notes'])) {
            return false;
        }
        foreach (['hours_diff', 'count_diff'] as $k) {
            foreach ((array) ($u['before'][$k] ?? []) as $v) {
                if ((int) $v !== 0) return false;
            }
        }
        $p = $u['period'] ?? null;
        if ($p) {
            foreach ($u['code_names'] as $code => $_) {
                if ((int) ($p['ktr_hours'][$code] ?? 0) !== (int) ($p['hemis_hours'][$code] ?? 0)) return false;
            }
            foreach (['L', 'A'] as $part) {
                if (in_array($part, $u['codes'], true) && (int) ($p['ktr_count'][$part] ?? 0) !== (int) ($p['hemis_count'][$part] ?? 0)) return false;
            }
        }

        return true;
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
        // "Kafedra mudiri xonasi" (faqat faollari; aynan shu nomlisi ustuvor): avval fanning o'z kafedrasiniki
        // (auditoriums.department_hemis_id), so'ng kafedrasiz umumiy xona, bo'lmasa — istalgan shunday xona.
        // Biriktirilmagan darslar uchun vaqtinchalik chora: HEMIS'da xona ustma-ust tushishiga ruxsat yoqilgan,
        // shuning uchun bu xona hech qachon to'siq emas.
        $mudirRooms = Auditorium::where('name', 'like', '%kafedra mudiri%')->where('active', true)
            ->orderByRaw('LOWER(TRIM(name)) = ? DESC', ['kafedra mudiri xonasi'])->orderBy('id')->get();
        $fallbackRoom = $mudirRooms->first();
        $fallbackRoomFor = function ($deptId) use ($mudirRooms): ?array {
            $room = ($deptId ? $mudirRooms->first(fn ($a) => (string) $a->department_hemis_id === (string) $deptId) : null)
                ?? $mudirRooms->first(fn ($a) => !$a->department_hemis_id)
                ?? $mudirRooms->first();

            return $room ? [$room->code, $room->name] : null;
        };
        // until_week yuborilmasa — joriy hafta oxirigacha; 0 — semestr oxirigacha.
        $untilWeek = array_key_exists('until_week', $scope) && $scope['until_week'] !== null ? (int) $scope['until_week'] : null;
        $weeksOut = [];
        $currentWeekOut = 0;
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
        $finals = [];        // barcha guruhlarning yakuniy (rejadagi) yozuvlari — guruhlararo tekshiruv uchun
        $handledIds = [];    // rejada qayta yoziladigan HEMIS yozuvlari (qolganlari tashqi bandlik)
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
                // Faqat shu birlik turlari (kodlari) uning rejasida; boshqa turdagi HEMIS darslari guruh bandligida qoladi.
                $handledSubjects[(int) $cs->subject_id] = ($handledSubjects[(int) $cs->subject_id] ?? []) + $codes;
                foreach ($hemisRows as $hr) {
                    if (isset($codes[(string) $hr->training_type_code])) {
                        $handledIds[(int) $hr->schedule_hemis_id] = true;
                    }
                }
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
                    'fallback_room' => $fallbackRoomFor($cs->department_id),
                ];
                $otherTypes = $hemisRows->filter(fn ($r) => substr((string) $r->lesson_date, 0, 10) > $today && !isset($codes[(string) $r->training_type_code]))->count();
                $unitMeta[$gk . '|' . $cs->id] = ['kafedra' => $cs->department_name, 'cs_id' => $cs->id, 'mudir' => $mudir[1] ?? null,
                    'other_type_rows' => $otherTypes];
            }

            // Guruhning boshqa bandligi: rejaga kirmagan HEMIS darslari va LMS'da fani topilmagan kartalar.
            $busy = [];
            foreach ($hemisAll as $r) {
                $d = substr((string) $r->lesson_date, 0, 10);
                if ($d <= $today || isset($handledSubjects[(int) $r->subject_id][(string) $r->training_type_code])) {
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

            // Haftalar ro'yxati (oyna uchun) va "shu haftagacha" chegarasi.
            $until = null;
            $gCurrent = 0;
            for ($w = 1; $w <= $g['weeks']; $w++) {
                $mon = $weekMonday($sample, $w);
                $weeksOut[$w] ??= ['week' => $w, 'from' => $mon, 'to' => Carbon::parse($mon)->addDays(6)->toDateString()];
                if ($mon <= $today) {
                    $gCurrent = $w;
                }
            }
            $currentWeekOut = max($currentWeekOut, $gCurrent);
            $uw = $untilWeek ?? $gCurrent;
            if ($uw > 0) {
                $until = Carbon::parse($weekMonday($sample, min($uw, $g['weeks'])))->addDays(6)->toDateString();
            }

            $results = $units ? $planner->planGroup($units, $busy, $workDates) : [];
            // Hisob davri: haftalar oralig'i (sukut 1 — joriy hafta).
            $pFrom = max(1, (int) ($scope['period_from'] ?? 1));
            $pTo = max($pFrom, min($g['weeks'], (int) ($scope['period_to'] ?? max(1, $gCurrent))));
            $pFromDate = $weekMonday($sample, $pFrom);
            $pToDate = Carbon::parse($weekMonday($sample, $pTo))->addDays(6)->toDateString();
            $unitByKey = [];
            foreach ($units as $uu) {
                $unitByKey[$uu['key']] = $uu;
            }
            foreach ($results as &$r) {
                $r += $unitMeta[$r['key']] ?? [];
                $r['period'] = isset($unitByKey[$r['key']])
                    ? $this->hemisPeriodStats($unitByKey[$r['key']], $pFrom, $pTo, $pFromDate, $pToDate) : null;
                if (!empty($r['other_type_rows'])) {
                    $r['notes'][] = "HEMIS'da rejaga kirmagan turdagi {$r['other_type_rows']} ta kelajak dars bor — o'zgartirilmaydi, vaqti band hisoblanadi.";
                }
                foreach ($r['records'] as $rec) {
                    $finals[] = ['key' => $r['key'], 'label' => $gname . ' · ' . $r['subject_name']] + $rec;
                }
                unset($r['records']);
                $r['actions_later'] = 0;
                if ($until !== null) {
                    $r['actions_later'] = count(array_filter($r['actions'], fn ($a) => $a['date'] > $until));
                    $r['actions'] = array_values(array_filter($r['actions'], fn ($a) => $a['date'] <= $until));
                }
            }
            unset($r);

            $groupsOut[] = [
                'group' => $gname,
                'group_hemis_id' => (int) $grp->group_hemis_id,
                'curriculum_hemis_id' => (int) $grp->curriculum_hemis_id,
                'semester_code' => $semCode,
                'education_year' => (int) substr((string) $board->academic_year, 0, 4),
                'until' => $until,
                // Guruhning o'z hisob davri (kalendari boshqacha guruhlarda sukut — o'z joriy haftasi).
                'period_from' => $pFrom,
                'period_to' => $pTo,
                'imported_at' => $hemisAll->max('updated_at'),
                'units' => $results,
                'skipped' => $skipped,
                'cards_without_subject' => array_keys($unmatchedCards),
            ];
        }

        $this->hemisCrossCheck($groupsOut, $finals, $handledIds, $today, $mudirFor);

        return [
            'today' => $today,
            'board_id' => $board->id,
            'fallback_room' => $fallbackRoom ? ['code' => $fallbackRoom->code, 'name' => $fallbackRoom->name] : null,
            'hemis_pairs' => self::HEMIS_PAIRS,
            'until_week' => $untilWeek ?? $currentWeekOut,
            'period_from' => max(1, (int) ($scope['period_from'] ?? 1)),
            'period_to' => (int) ($scope['period_to'] ?? max(1, $currentWeekOut)),
            'current_week' => $currentWeekOut,
            'weeks' => array_values($weeksOut),
            'groups' => $groupsOut,
            'groups_not_in_hemis' => $missingGroups,
        ];
    }
    /**
     * Guruhlar alohida rejalashtiriladi — qo'shiladigan/o'zgaradigan dars o'qituvchi yoki xona bo'yicha boshqa
     * guruhning yakuniy darsi yoki HEMIS'dagi boshqa (rejaga kirmagan) dars bilan ustma-ust tushsa, amal to'siqli.
     * O'sha o'qituvchi o'sha xonada bo'lsa — bu bitta dars (oqim ma'ruzasi, birlashgan amaliy), to'qnashuv emas.
     * O'qituvchilar ustma-ust tushmaydi; yagona istisno — o'qituvchi biriktirilmagani uchun kafedra mudiriga
     * majburan qo'yilgan darslar (o'rinbosar, yoki HEMIS'dagi dars o'qituvchisi — shu fan kafedrasining mudiri).
     * "Kafedra mudiri xonasi" (vaqtincha ustma-ust tushishiga ruxsat bor) va umumiy (raqamsiz) xonalar tekshirilmaydi.
     */
    private function hemisCrossCheck(array &$groupsOut, array $finals, array $handledIds, string $today, callable $mudirFor): void
    {
        $idx = [];   // "e|xodim|sana" / "r|xona|sana" => [[boshlanish, tugash, birlik, matn, xodim, xona], ...]
        $emps = [];
        $rooms = [];
        $freeRoom = fn (array $r) => in_array('mudir_xonasi', $r['flags'] ?? [], true) || $this->isSharedRoom($r['auditorium_name'] ?? null)
            || preg_match('/kafedra\s+mudiri/iu', (string) ($r['auditorium_name'] ?? '')) === 1;
        $index = function (string $type, $id, string $date, string $start, string $end, string $unitKey, string $label, $emp, $room) use (&$idx) {
            $idx[$type . '|' . $id . '|' . $date][] = [$start, $end, $unitKey, $label, (string) $emp, (string) $room];
        };
        foreach ($finals as $f) {
            if ($f['employee_id'] && !in_array('mudir', $f['flags'] ?? [], true)) {
                $index('e', $f['employee_id'], $f['date'], $f['start'], $f['end'], $f['key'], $f['label'], $f['employee_id'], $f['auditorium_code']);
                $emps[(string) $f['employee_id']] = true;
            }
            if ($f['auditorium_code'] && !$freeRoom($f)) {
                $index('r', $f['auditorium_code'], $f['date'], $f['start'], $f['end'], $f['key'], $f['label'], $f['employee_id'], $f['auditorium_code']);
                $rooms[(string) $f['auditorium_code']] = true;
            }
        }
        if ($emps || $rooms) {
            $ext = DB::table('schedules')->whereNull('deleted_at')->where('lesson_date', '>', $today . ' 23:59:59')
                ->where(fn ($q) => $q->whereIn('employee_id', array_keys($emps))->orWhereIn('auditorium_code', array_keys($rooms)))
                ->get(['schedule_hemis_id', 'group_name', 'subject_name', 'lesson_date', 'lesson_pair_start_time', 'lesson_pair_end_time',
                    'employee_id', 'auditorium_code', 'auditorium_name', 'department_id']);
            foreach ($ext as $r) {
                if (isset($handledIds[(int) $r->schedule_hemis_id])) {
                    continue;
                }
                $date = substr((string) $r->lesson_date, 0, 10);
                $start = substr((string) $r->lesson_pair_start_time, 0, 5);
                $end = substr((string) $r->lesson_pair_end_time, 0, 5);
                $label = 'HEMIS: ' . $r->group_name . ' · ' . $r->subject_name;
                // Kafedra mudiriga majburan qo'yilgan HEMIS darsi (o'qituvchisi — shu fan kafedrasining mudiri) bandlik emas.
                $forcedMudir = $r->employee_id && (string) (($mudirFor($r->department_id) ?? [null])[0]) === (string) $r->employee_id;
                if ($r->employee_id && isset($emps[(string) $r->employee_id]) && !$forcedMudir) {
                    $index('e', $r->employee_id, $date, $start, $end, '', $label, $r->employee_id, $r->auditorium_code);
                }
                if ($r->auditorium_code && isset($rooms[(string) $r->auditorium_code]) && !$this->isSharedRoom($r->auditorium_name)) {
                    $index('r', $r->auditorium_code, $date, $start, $end, '', $label, $r->employee_id, $r->auditorium_code);
                }
            }
        }
        foreach ($groupsOut as &$g) {
            foreach ($g['units'] as &$u) {
                foreach ($u['actions'] as &$a) {
                    if (!in_array($a['op'], ['create', 'update'], true)) {
                        continue;
                    }
                    $emp = (string) ($a['employee_id'] ?? '');
                    $room = (string) ($a['auditorium_code'] ?? '');
                    foreach ([['e', $emp, 'mudir', "o'qituvchi band"], ['r', $room, 'mudir_xonasi', 'xona band']] as [$type, $id, $flag, $msg]) {
                        if ($id === '' || in_array($flag, $a['flags'] ?? [], true) || ($type === 'r' && $freeRoom($a))) {
                            continue;
                        }
                        foreach ($idx[$type . '|' . $id . '|' . $a['date']] ?? [] as [$s, $e, $k, $label, $oEmp, $oRoom]) {
                            if ($k === $u['key'] && $s === $a['start']) {
                                continue;   // shu yozuvning o'zi
                            }
                            if ($emp !== '' && $room !== '' && $oEmp === $emp && $oRoom === $room) {
                                continue;   // bitta dars bir necha guruhga (umumiy karta yoki HEMIS'dagi oqim)
                            }
                            if ($s < $a['end'] && $a['start'] < $e) {
                                $a['blocked'][] = "{$msg}: {$label} {$s}-{$e}";
                                break;
                            }
                        }
                    }
                }
                unset($a);
            }
            unset($u);
        }
        unset($g);
    }

    /** Solishtirish natijasi Excelda: "Balans" (fan + guruh) va "Amallar" (HEMIS'da qilinadigan o'zgarishlar). */
    public function hemisSyncExport(Request $request, TimetableBoard $board)
    {
        $resp = $this->hemisSyncCompare($request, $board);
        $rep = $resp->getData(true);
        $onlyDiff = $request->boolean('only_diff');
        $ops = $request->boolean('ops_filter') ? (array) $request->input('ops', []) : null;
        $opOk = function (array $a) use ($ops): bool {
            if ($ops === null) return true;
            if (!empty($a['blocked'])) return in_array('blocked', $ops, true);
            return in_array($a['op'], $ops, true);
        };

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $bal = $book->getActiveSheet();
        $bal->setTitle('Balans');
        $bal->fromArray(['Guruh', 'Fan', 'Kafedra', 'Dars turi', 'KTR jami (soat)', 'KTR joriy haftagacha', 'HEMIS bugungacha',
            'Semestr soat farqi hozir (+ kam, − ortiq)', 'Semestr soat farqi takliflardan keyin', 'Qism (Amaliy qism = amaliy, seminar, lab, klinik)',
            'KTR dars kunlari (semestr)', 'HEMIS dars kunlari (bugungacha)', 'Semestr kunlar farqi hozir (+ kam, − ortiq)', 'Semestr kunlar farqi takliflardan keyin',
            'Tuzatishlar', 'Izohlar', 'Davr', 'Davr KTR soati', 'Davr HEMIS soati', 'Davr soat farqi (HEMIS−KTR)', 'Davr KTR kunlari', 'Davr HEMIS kunlari', 'Davr LMS kunlari'], null, 'A1');
        $act = $book->createSheet();
        $act->setTitle('Amallar');
        $act->fromArray(['Guruh', 'Fan', 'Amal', 'Sana', 'Boshlanish', 'Tugash', 'HEMIS para', 'Dars turi', 'O\'qituvchi', 'Xona',
            'HEMIS id', 'O\'zgarishlar', 'Belgilar', 'To\'siq'], null, 'A1');
        $opName = ['create' => 'Qo\'shish', 'update' => 'O\'zgartirish', 'delete' => 'O\'chirish'];
        $r1 = 2;
        $r2 = 2;
        foreach ($rep['groups'] as $g) {
            foreach ($g['units'] as $u) {
                if ($onlyDiff && $this->hemisUnitMatches($u)) {
                    continue;
                }
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
                        $first ? $fixes : '', $first ? $notes : '',
                        isset($u['period']) ? $u['period']['from_week'] . '–' . $u['period']['to_week'] . '-hafta' : '',
                        $u['period']['ktr_hours'][$code] ?? 0, $u['period']['hemis_hours'][$code] ?? 0,
                        ($u['period']['hemis_hours'][$code] ?? 0) - ($u['period']['ktr_hours'][$code] ?? 0),
                        $u['period']['ktr_count'][$part] ?? 0, $u['period']['hemis_count'][$part] ?? 0, $u['period']['lms_count'][$part] ?? 0,
                    ], null, 'A' . $r1++);
                    $first = false;
                }
                foreach ($u['actions'] as $x) {
                    if (!$opOk($x)) {
                        continue;
                    }
                    $act->fromArray([$g['group'], $u['subject_name'], $opName[$x['op']] ?? $x['op'], $x['date'], $x['start'], $x['end'],
                        self::HEMIS_PAIRS[$x['start'] . '-' . $x['end']] ?? '', $u['code_names'][$x['code']] ?? $x['code'],
                        $x['employee_name'] ?? '', $x['auditorium_name'] ?? '', $x['hemis_id'] ?? '',
                        implode('; ', $x['changes'] ?? []), implode(', ', $x['flags'] ?? []), implode('; ', $x['blocked'] ?? [])], null, 'A' . $r2++);
                }
            }
        }
        foreach ([$bal, $act] as $sh) {
            $sh->getStyle('A1:W1')->getFont()->setBold(true);
            $sh->freezePane('A2');
            foreach (range('A', 'W') as $col) {
                $sh->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book);

        return response()->streamDownload(fn () => $writer->save('php://output'), 'hemis_solishtirish_' . $rep['today'] . '.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}

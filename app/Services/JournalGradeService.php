<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Jurnaldagi JN (joriy nazorat) va MT (mustaqil ta'lim) o'rtacha baholarini
 * jurnal sahifasidagi formula bilan AYNAN bir xil hisoblaydi.
 *
 * Manba: JournalController — `show()` blade'idagi JN%/MT% hisobi va YN snapshot
 * yozuvchisi (yn_student_grades). Bu xizmat o'sha mantiqning yagona,
 * qayta ishlatiladigan nusxasi.
 *
 * Formula:
 *  - JB/MT dars kunlari `schedules` jadvalidan olinadi (yagona manba).
 *  - Har bir dars kuni uchun: shu kundagi baholar yig'indisi / o'sha kunga
 *    rejalashtirilgan para soni → kunlik o'rtacha (yaxlitlangan).
 *  - JN = (bugungacha bo'lgan kunlik o'rtachalar yig'indisi) / (jami dars kuni).
 *    Baho qo'yilmagan ("NB"/kelmagan) kun 0 sifatida hisobga olinadi —
 *    shuning uchun maxraj jadvaldagi BARCHA dars kunlari soni.
 *  - MT da bugungacha cheklov yo'q — barcha MT dars kunlari hisobga olinadi.
 *  - retake_grade — koeffitsient (sababsiz otrabotka = 0.8, sababli = 1.0)
 *    saqlash vaqtida allaqachon qo'llangan yakuniy qiymat; bu yerda qayta
 *    ko'paytirilmaydi.
 */
class JournalGradeService
{
    /** JN (joriy nazorat) ga kirmaydigan training_type kodlari. */
    private const EXCLUDED_JB_CODES = [11, 99, 100, 101, 102, 103];

    /**
     * Bir nechta (guruh, fan, semestr) uchun JN/MT ni bitta martaga hisoblaydi.
     *
     * @param array<int,array{0:int|string,1:int|string,2:int|string}> $triples
     *        Har biri [group_hemis_id, subject_id, semester_code].
     * @param array<int|string,int|string> $studentGroup
     *        [student_hemis_id => group_hemis_id] xaritasi.
     * @return array<string,array<string,array{jn:?int,mt:?int}>>
     *         ["groupHid|subjectId|semesterCode" => [hemis_id => ['jn'=>?int,'mt'=>?int]]]
     */
    /**
     * Joriy o'quv yili boshlanish sanasi (global zaxira qiymat).
     *
     * curriculum_weeks dan: joriy o'quv yili (semesters.current=1 dagi eng
     * katta education_year) ning BARCHA semestrlari (kuzgi + bahorgi)
     * haftalaridan eng ertasi. Aniqrog'i — academicYearStartByGroup() per-reja
     * sanani beradi; bu metod faqat zaxira (reja topilmasa).
     */
    public static function currentAcademicYearStart(): ?string
    {
        try {
            $cy = \App\Models\Semester::where('current', true)->max('education_year');
            if (!$cy) {
                return null;
            }
            $start = DB::table('curriculum_weeks as cw')
                ->join('semesters as s', 's.semester_hemis_id', '=', 'cw.semester_hemis_id')
                ->where('s.education_year', $cy)
                ->min('cw.start_date');
            return $start ?: (((int) $cy) . '-08-01 00:00:00');
        } catch (\Throwable $e) {
            Log::warning('JournalGradeService currentAcademicYearStart failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Har bir guruh uchun JORIY o'quv yili boshlanish sanasi — o'quv reja
     * (curriculum) bo'yicha ALOHIDA.
     *
     * Bir o'quv yili 2 semestrdan iborat (kuzgi + bahorgi). O'quv yili
     * boshlanishi = shu yildagi BIRINCHI (eng erta) semestr boshlanishi —
     * talaba hozir 2-semestrda bo'lsa ham chegara 1-semestr boshidan olinadi.
     * Yo'l: guruh → curriculum → joriy education_year dagi semestrlar →
     * curriculum_weeks ichidan eng erta hafta.
     *
     * @param array $groupHids   guruh hemis id lari
     * @return array<string,string>  [group_hemis_id => "Y-m-d H:i:s"]
     */
    public static function academicYearStartByGroup(array $groupHids): array
    {
        $map = [];
        if (empty($groupHids)) {
            return $map;
        }
        try {
            $cy = \App\Models\Semester::where('current', true)->max('education_year');
            if (!$cy) {
                return $map;
            }

            $groupCurr = DB::table('groups')
                ->whereIn('group_hemis_id', $groupHids)
                ->whereNotNull('curriculum_hemis_id')
                ->pluck('curriculum_hemis_id', 'group_hemis_id');
            if ($groupCurr->isEmpty()) {
                return $map;
            }
            $currIds = array_values(array_unique($groupCurr->all()));

            // Joriy o'quv yili (education_year = $cy) semestrlari — har curriculum uchun
            $sems = DB::table('semesters')
                ->whereIn('curriculum_hemis_id', $currIds)
                ->where('education_year', $cy)
                ->select('curriculum_hemis_id', 'semester_hemis_id')
                ->get();
            $semHidsByCurr = [];
            $allSemHids = [];
            foreach ($sems as $s) {
                $semHidsByCurr[$s->curriculum_hemis_id][] = $s->semester_hemis_id;
                $allSemHids[] = $s->semester_hemis_id;
            }
            if (empty($allSemHids)) {
                return $map;
            }

            // Har semestr uchun eng erta hafta
            $weekMin = DB::table('curriculum_weeks')
                ->whereIn('semester_hemis_id', $allSemHids)
                ->groupBy('semester_hemis_id')
                ->selectRaw('semester_hemis_id, MIN(start_date) as ms')
                ->pluck('ms', 'semester_hemis_id');

            // Har curriculum uchun — semestrlari ichidan eng erta (= 1-semestr boshi)
            $currStart = [];
            foreach ($semHidsByCurr as $curr => $hids) {
                $dates = [];
                foreach ($hids as $h) {
                    if (isset($weekMin[$h])) {
                        $dates[] = $weekMin[$h];
                    }
                }
                if (!empty($dates)) {
                    $currStart[$curr] = min($dates);
                }
            }

            foreach ($groupCurr as $gHid => $curr) {
                if (isset($currStart[$curr])) {
                    $map[(string) $gHid] = $currStart[$curr];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('JournalGradeService academicYearStartByGroup failed: ' . $e->getMessage());
        }
        return $map;
    }

    public static function computeJnMtBulk(array $triples, array $studentGroup): array
    {
        $result = [];
        if (empty($triples) || empty($studentGroup)) {
            return $result;
        }

        $groupHids   = array_values(array_unique(array_map(fn ($t) => (string) $t[0], $triples)));
        $subjectIds  = array_values(array_unique(array_map(fn ($t) => (string) $t[1], $triples)));
        $semCodes    = array_values(array_unique(array_map(fn ($t) => (string) $t[2], $triples)));
        $studentHids = array_map('strval', array_keys($studentGroup));

        // O'quv yili ajratish — kunlik baholar (lesson_date bor) har bir
        // guruhning o'z o'quv-yili boshlanish sanasi (1-semestr) bo'yicha;
        // manual MT (lesson_date NULL) global o'quv yili boshi (created_at).
        $yearStart = self::currentAcademicYearStart();
        $yearStartByGroup = self::academicYearStartByGroup($groupHids);

        // Talabalarni guruh bo'yicha indekslaymiz
        $studentsByGroup = [];
        foreach ($studentGroup as $hemis => $gHid) {
            $studentsByGroup[(string) $gHid][] = (string) $hemis;
        }

        // --- 1) Schedules: JB va MT dars sana/para ustunlari ---
        // datePair[key] = [ "Y-m-d_pair" => "Y-m-d" ]
        $jbDatePair = [];
        $mtDatePair = [];
        try {
            $rows = DB::table('schedules')
                ->whereNull('deleted_at')
                ->whereIn('group_id', $groupHids)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semCodes)
                ->whereNotNull('lesson_date')
                ->select('group_id', 'subject_id', 'semester_code',
                    'training_type_code', 'lesson_date', 'lesson_pair_code')
                ->get();
            foreach ($rows as $r) {
                $key = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $d = Carbon::parse($r->lesson_date)->format('Y-m-d');
                $dp = $d . '_' . $r->lesson_pair_code;
                $tc = (int) $r->training_type_code;
                if ($tc === 99) {
                    $mtDatePair[$key][$dp] = $d;
                } elseif (!in_array($tc, self::EXCLUDED_JB_CODES, true)) {
                    $jbDatePair[$key][$dp] = $d;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('JournalGradeService schedules query failed: ' . $e->getMessage());
            return $result;
        }

        $today = Carbon::now('Asia/Tashkent')->format('Y-m-d');
        $jbMeta = self::buildMeta($jbDatePair, $today, true);   // JN: bugungacha cutoff
        $mtMeta = self::buildMeta($mtDatePair, $today, false);  // MT: cutoff yo'q

        // --- 2) Kunlik baholar (JB + per-day MT) ---
        // grades[key][hemis]["Y-m-d"][pair] = effectiveGrade
        $jbGrades = [];
        $mtGrades = [];
        $hasJb = [];
        $hasMt = [];
        try {
            $rows = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHids)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semCodes)
                ->whereNotIn('training_type_code', [100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->select('student_hemis_id', 'subject_id', 'semester_code',
                    'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();
            foreach ($rows as $r) {
                $hemis = (string) $r->student_hemis_id;
                $gHid = $studentGroup[$hemis] ?? ($studentGroup[(int) $hemis] ?? null);
                if ($gHid === null) {
                    continue;
                }
                // O'quv yili ajratish — dars sanasi joriy o'quv yili (1-semestr)
                // boshlanishidan oldin bo'lsa: eski o'qishidagi yozuv, o'tkazamiz.
                $rStart = $yearStartByGroup[(string) $gHid] ?? $yearStart;
                if ($rStart && substr((string) $r->lesson_date, 0, 10) < substr((string) $rStart, 0, 10)) {
                    continue;
                }
                $key = $gHid . '|' . $r->subject_id . '|' . $r->semester_code;
                $eff = self::effectiveGrade($r);
                if ($eff === null) {
                    continue;
                }
                $d = Carbon::parse($r->lesson_date)->format('Y-m-d');
                $dp = $d . '_' . $r->lesson_pair_code;
                if (isset($jbDatePair[$key][$dp])) {
                    $jbGrades[$key][$hemis][$d][$r->lesson_pair_code] = $eff;
                    $hasJb[$key . '|' . $hemis] = true;
                }
                if (isset($mtDatePair[$key][$dp])) {
                    $mtGrades[$key][$hemis][$d][$r->lesson_pair_code] = $eff;
                    $hasMt[$key . '|' . $hemis] = true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('JournalGradeService grades query failed: ' . $e->getMessage());
        }

        // --- 3) Manual MT (training_type_code=99, lesson_date IS NULL) ---
        $manualMt = [];
        try {
            $rows = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHids)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semCodes)
                ->where('training_type_code', 99)
                ->when($yearStart, fn ($q) => $q->where('created_at', '>=', $yearStart))
                ->whereNull('lesson_date')
                ->whereNotNull('grade')
                ->select('student_hemis_id', 'subject_id', 'semester_code', 'grade')
                ->get();
            foreach ($rows as $r) {
                $manualMt[$r->student_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code] = (float) $r->grade;
            }
        } catch (\Throwable $e) {
            Log::warning('JournalGradeService manual MT query failed: ' . $e->getMessage());
        }

        // --- 4) Har triple, har talaba uchun JN/MT ---
        foreach ($triples as $t) {
            $g = (string) $t[0];
            $s = (string) $t[1];
            $sem = (string) $t[2];
            $key = $g . '|' . $s . '|' . $sem;

            foreach ($studentsByGroup[$g] ?? [] as $hemis) {
                // JN — JB bahosi umuman bo'lmasa null (ma'lumot yo'q)
                $jn = empty($hasJb[$key . '|' . $hemis])
                    ? null
                    : self::dailyAverage($jbGrades[$key][$hemis] ?? [], $jbMeta[$key] ?? null);

                // MT — avval per-day, keyin manual MT override (jurnal bilan bir xil)
                $mt = empty($hasMt[$key . '|' . $hemis])
                    ? null
                    : self::dailyAverage($mtGrades[$key][$hemis] ?? [], $mtMeta[$key] ?? null);
                $mmKey = $hemis . '|' . $s . '|' . $sem;
                if (isset($manualMt[$mmKey])) {
                    $mt = (int) round($manualMt[$mmKey], 0, PHP_ROUND_HALF_UP);
                }

                $result[$key][$hemis] = ['jn' => $jn, 'mt' => $mt];
            }
        }

        return $result;
    }

    /**
     * datePair xaritasidan har triple uchun para-soni va o'rtachaga kiradigan
     * kunlar ro'yxatini quradi.
     *
     * @return array<string,array{pairsPerDay:array<string,int>,datesForAverage:array<string,bool>,totalDays:int}>
     */
    private static function buildMeta(array $datePair, string $today, bool $applyCutoff): array
    {
        $meta = [];
        foreach ($datePair as $key => $dps) {
            $pairsPerDay = [];
            foreach ($dps as $d) {
                $pairsPerDay[$d] = ($pairsPerDay[$d] ?? 0) + 1;
            }
            $datesForAverage = [];
            foreach (array_keys($pairsPerDay) as $d) {
                if (!$applyCutoff || $d <= $today) {
                    $datesForAverage[$d] = true;
                }
            }
            $meta[$key] = [
                'pairsPerDay' => $pairsPerDay,
                'datesForAverage' => $datesForAverage,
                'totalDays' => count($datesForAverage),
            ];
        }
        return $meta;
    }

    /**
     * Kunlik o'rtachalar yig'indisi / jami dars kuni. Baho yo'q kun = 0.
     *
     * @param array<string,array<string,float>> $studentDayGrades  ["Y-m-d" => [pair => grade]]
     */
    private static function dailyAverage(array $studentDayGrades, ?array $meta): ?int
    {
        if ($meta === null || ($meta['totalDays'] ?? 0) <= 0) {
            return null;
        }
        $dailySum = 0.0;
        foreach (array_keys($meta['datesForAverage']) as $d) {
            $dayGrades = $studentDayGrades[$d] ?? [];
            $pairsInDay = $meta['pairsPerDay'][$d] ?? 1;
            $dailySum += round(array_sum($dayGrades) / $pairsInDay, 0, PHP_ROUND_HALF_UP);
        }
        return (int) round($dailySum / $meta['totalDays'], 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Bitta baho qatori uchun amaldagi qiymat — JournalController::getEffectiveGrade
     * (snapshot yozuvchisi nusxasi) bilan AYNAN bir xil.
     */
    private static function effectiveGrade(object $row): ?float
    {
        // ENG YUQORI QOIDA: asl baho < 60 va retake mavjud → retake ustun.
        // Asl baho ≥ 60 bo'lsa retake umuman qabul qilinmaydi.
        if ($row->grade !== null && (float) $row->grade < 60 && $row->retake_grade !== null) {
            return (float) $row->retake_grade;
        }
        if ($row->status === 'pending') {
            return null;
        }
        // Bahosiz "absent" (NB) — retake bo'lsa o'sha, aks holda null (kun bo'sh qoladi).
        if ($row->reason === 'absent' && $row->grade === null) {
            return $row->retake_grade !== null ? (float) $row->retake_grade : null;
        }
        if ($row->status === 'closed' && $row->reason === 'teacher_victim'
            && $row->grade == 0 && $row->retake_grade === null) {
            return null;
        }
        if ($row->status === 'recorded') {
            return $row->grade !== null ? (float) $row->grade : null;
        }
        if ($row->status === 'closed') {
            return $row->grade !== null ? (float) $row->grade : null;
        }
        if ($row->retake_grade !== null) {
            return (float) $row->retake_grade;
        }
        return null;
    }

    /**
     * ON (100), OSKI (101), Test (102) baholarini jurnal sahifasidagi
     * AYNAN bir xil mantiq bilan hisoblaydi — `MAX(grade)` EMAS.
     *
     * Manba: JournalController::show() dagi OSKI/Test ustun hisobi
     * (is_qoshimcha=0, education_year/minScheduleDate oynasi, attempt=1,
     * effectiveGrade). Vedomost-tekshirish, YN qaydnoma generatori va
     * YnQaydnomaDataService shu yagona mantiqdan foydalanadi — natijada
     * qaydnoma har doim jurnaldagi qiymatga teng bo'ladi.
     *
     * QO'SHIMCHA QOIDA: agar talabada haqiqiy natija (reason !=
     * 'sinov_yn_test') bo'lsa, soxta 'sinov_yn_test' qatori hisobga
     * OLINMAYDI. Sinov fani uchun (faqat sinov qatori bor) esa o'sha
     * qiymat ishlatiladi.
     *
     * @param array<int,int|string> $studentHemisIds
     * @return array{on:array<string,int>,oski:array<string,int>,test:array<string,int>}
     */
    public static function computeOnOskiTest(
        string $groupHemisId,
        string $subjectId,
        string $semesterCode,
        array $studentHemisIds
    ): array {
        $out = ['on' => [], 'oski' => [], 'test' => []];
        if (empty($studentHemisIds)) {
            return $out;
        }
        $studentHids = array_map('strval', $studentHemisIds);

        // Joriy o'quv yili — eng so'nggi jadval yozuvidan (jurnal bilan bir xil).
        $educationYearCode = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->value('education_year_code');

        // Shu semestr jadvalining eng erta (imtihonsiz) dars sanasi —
        // undan oldingi eski yozuvlarni chetlatish uchun.
        $minScheduleDate = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->when($educationYearCode !== null, fn ($q) => $q->where('education_year_code', $educationYearCode))
            ->min('lesson_date');

        $hasQoshimcha = Schema::hasColumn('student_grades', 'is_qoshimcha');
        $hasAttempt   = Schema::hasColumn('student_grades', 'attempt');

        $rows = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHids)
            ->where('subject_id', $subjectId)
            // OSKI/Test boshqa semestrda saqlangan bo'lishi mumkin (jurnal bilan bir xil yumshatish)
            ->where(function ($q) use ($semesterCode) {
                $q->where('semester_code', $semesterCode)
                    ->orWhereIn('training_type_code', [101, 102]);
            })
            ->whereIn('training_type_code', [100, 101, 102, 103])
            ->when($hasQoshimcha, fn ($q) => $q->where('is_qoshimcha', 0))
            ->when($educationYearCode !== null, function ($q) use ($educationYearCode, $minScheduleDate) {
                $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $educationYearCode)
                        ->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->when($minScheduleDate !== null, fn ($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                        });
                });
            })
            ->when($educationYearCode === null && $minScheduleDate !== null, fn ($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select(
                'student_hemis_id',
                'training_type_code',
                'grade',
                'retake_grade',
                'status',
                'reason',
                'quiz_result_id',
                $hasAttempt ? DB::raw('attempt') : DB::raw('1 as attempt')
            )
            ->get();

        // Eski 103 (quiz) kodini quiz_type bo'yicha 101/102 ga aniqlash.
        $quizIds = $rows->where('training_type_code', 103)
            ->pluck('quiz_result_id')->filter()->unique()->values()->all();
        $quizTypeById = [];
        if (!empty($quizIds)) {
            $quizTypeById = DB::table('hemis_quiz_results')
                ->whereIn('id', $quizIds)
                ->pluck('quiz_type', 'id')->toArray();
        }
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

        // [hemis][typeCode][attempt] = [ ['grade'=>float,'sinov'=>bool], ... ]
        $grouped = [];
        foreach ($rows as $r) {
            $eff = self::effectiveGrade($r);
            if ($eff === null) {
                continue;
            }
            $tc = (int) $r->training_type_code;
            if ($tc === 103 && $r->quiz_result_id) {
                $qt = $quizTypeById[$r->quiz_result_id] ?? null;
                if (in_array($qt, $oskiTypes, true)) {
                    $tc = 101;
                } elseif (in_array($qt, $testTypes, true)) {
                    $tc = 102;
                } else {
                    continue;
                }
            }
            if (!in_array($tc, [100, 101, 102], true)) {
                continue;
            }
            $attempt = (int) ($r->attempt ?? 1);
            $grouped[(string) $r->student_hemis_id][$tc][$attempt][] = [
                'grade' => $eff,
                'sinov' => ($r->reason === 'sinov_yn_test'),
            ];
        }

        foreach ($grouped as $hemis => $types) {
            foreach ([100 => 'on', 101 => 'oski', 102 => 'test'] as $tc => $key) {
                if (empty($types[$tc])) {
                    continue;
                }
                // ON: mavjud eng kichik urinish; OSKI/Test: asosiy ustun = 1-urinish.
                $target = $tc === 100 ? (int) min(array_keys($types[$tc])) : 1;
                if (empty($types[$tc][$target])) {
                    continue;
                }
                $list = $types[$tc][$target];
                // Haqiqiy natija bo'lsa, soxta sinov qatorini tashlaymiz.
                $hasReal = false;
                foreach ($list as $item) {
                    if (!$item['sinov']) {
                        $hasReal = true;
                        break;
                    }
                }
                if ($hasReal) {
                    $list = array_values(array_filter($list, fn ($i) => !$i['sinov']));
                }
                if (empty($list)) {
                    continue;
                }
                $vals = array_map(fn ($i) => $i['grade'], $list);
                $out[$key][(string) $hemis] = (int) round(array_sum($vals) / count($vals), 0, PHP_ROUND_HALF_UP);
            }
        }

        return $out;
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        // Joriy o'quv yili — tiklangan/transfer talabaning eski o'qishidan
        // qolgan (boshqa education_year) baholarini chiqarib tashlash uchun.
        $currentYearCode = null;
        try {
            $currentYearCode = \App\Models\Semester::where('current', true)->max('education_year');
        } catch (\Throwable $e) {}

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
                ->when($currentYearCode, fn ($q) => $q->where(function ($qq) use ($currentYearCode) {
                    $qq->whereNull('education_year_code')
                       ->orWhere('education_year_code', $currentYearCode);
                }))
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
                ->when($currentYearCode, fn ($q) => $q->where(function ($qq) use ($currentYearCode) {
                    $qq->whereNull('education_year_code')
                       ->orWhere('education_year_code', $currentYearCode);
                }))
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
}

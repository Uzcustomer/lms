<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * JN/MT bahosini jurnal ko'rinishi bilan bir xil mantiqda hisoblaydi.
 *
 * Jurnal "ixcham" tab, YN-oldi Word va YN ga yuborish snapshoti — barchasi
 * shu xizmatdan foydalansa, qiymatlar ziddiyatsiz bo'ladi.
 */
class JnMtCalculator
{
    /**
     * Guruh+fan+semestr bo'yicha har bir talabaning JN va MT bahosini hisoblaydi.
     *
     * @return array<string,array{jn:int,mt:int}> hemis_id => ['jn' => x, 'mt' => y]
     */
    public function computeForGroup(string $groupHemisId, int $subjectId, string $semesterCode): array
    {
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // 1. Schedule — JB (joriy baholash) sanalari: excluded'dan tashqari hammasi
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->get();

        // MT (mustaqil ta'lim) sanalari: training_type_code = 99
        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->get();

        $jbColumns = [];
        foreach ($jbScheduleRows as $s) {
            $date = Carbon::parse($s->lesson_date)->format('Y-m-d');
            $jbColumns[$date . '_' . $s->lesson_pair_code] = ['date' => $date, 'pair' => $s->lesson_pair_code];
        }
        $mtColumns = [];
        foreach ($mtScheduleRows as $s) {
            $date = Carbon::parse($s->lesson_date)->format('Y-m-d');
            $mtColumns[$date . '_' . $s->lesson_pair_code] = ['date' => $date, 'pair' => $s->lesson_pair_code];
        }

        $jbDatePairSet = array_fill_keys(array_keys($jbColumns), true);
        $mtDatePairSet = array_fill_keys(array_keys($mtColumns), true);

        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
        }
        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
        }

        $jbLessonDates = array_values(array_unique(array_map(fn($c) => $c['date'], $jbColumns)));
        sort($jbLessonDates);
        $mtLessonDates = array_values(array_unique(array_map(fn($c) => $c['date'], $mtColumns)));
        sort($mtLessonDates);

        // JN uchun cutoff: faqat bugungi sanaga qadar bo'lgan darslar — jurnal
        // "ixcham" tabidagi $jbLessonDatesForAverage bilan bir xil.
        // MT uchun cutoff yo'q — jurnal MT ixchamida $totalMtDays = count($mtLessonDates).
        $cutoff = Carbon::now('Asia/Tashkent')->endOfDay();
        $jbLessonDates = array_values(array_filter($jbLessonDates, function (string $date) use ($cutoff): bool {
            return Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($cutoff);
        }));

        // 2. Talabalar
        $studentHemisIds = DB::table('students')
            ->where('group_id', $groupHemisId)
            ->pluck('hemis_id')
            ->all();

        if (empty($studentHemisIds)) {
            return [];
        }

        // 3. Baholar
        $gradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
            ->get();

        // 4. Manual MT (training_type_code=99)
        $manualMtGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->whereNotNull('grade')
            ->select('student_hemis_id', 'grade')
            ->get()
            ->keyBy('student_hemis_id');

        // 5. Baholar xaritasini qurish (jurnal getEffectiveGrade mantig'i)
        $jbGrades = [];
        $mtGrades = [];
        foreach ($gradesRaw as $g) {
            $effective = $this->effectiveGrade($g);
            if ($effective === null) continue;
            $date = Carbon::parse($g->lesson_date)->format('Y-m-d');
            $key = $date . '_' . $g->lesson_pair_code;
            if (isset($jbDatePairSet[$key])) {
                $jbGrades[$g->student_hemis_id][$date][$g->lesson_pair_code] = $effective;
            }
            if (isset($mtDatePairSet[$key])) {
                $mtGrades[$g->student_hemis_id][$date][$g->lesson_pair_code] = $effective;
            }
        }

        // 6. Har bir talaba uchun JN va MT
        $result = [];
        foreach ($studentHemisIds as $hemisId) {
            $jn = $this->dailyAverage($jbGrades[$hemisId] ?? [], $jbLessonDates, $jbPairsPerDay);
            $mt = $this->dailyAverage($mtGrades[$hemisId] ?? [], $mtLessonDates, $mtPairsPerDay);

            if (isset($manualMtGrades[$hemisId])) {
                $mt = (int) round((float) $manualMtGrades[$hemisId]->grade, 0, PHP_ROUND_HALF_UP);
            }

            $result[$hemisId] = ['jn' => $jn, 'mt' => $mt];
        }

        return $result;
    }

    /**
     * Bitta student_grades qatori uchun samarali baho (jurnal mantig'iga mos).
     * Bahosi yo'q yoki "pending" bo'lsa — null qaytaradi.
     */
    private function effectiveGrade($row): ?float
    {
        // ENG YUQORI QOIDA: asl baho < 60 va retake mavjud → retake ustun.
        // Asl baho >= 60 bo'lsa, retake umuman qabul qilinmaydi.
        if ($row->grade !== null && (float) $row->grade < 60 && $row->retake_grade !== null) {
            return (float) $row->retake_grade;
        }
        if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
            return (float) $row->grade;
        }
        if ($row->status === 'pending') return null;
        if ($row->reason === 'absent' && $row->grade === null) {
            return $row->retake_grade !== null ? (float) $row->retake_grade : null;
        }
        if ($row->status === 'closed' && $row->reason === 'teacher_victim' && (float) $row->grade == 0 && $row->retake_grade === null) {
            return null;
        }
        if ($row->status === 'recorded') return $row->grade !== null ? (float) $row->grade : null;
        if ($row->status === 'closed') return $row->grade !== null ? (float) $row->grade : null;
        if ($row->retake_grade !== null) return (float) $row->retake_grade;
        return null;
    }

    /**
     * Jurnal "ixcham" mantig'i (show.blade.php:1170-1188 bilan bir xil):
     * - Har bir kun uchun dailyAverage = round(sum(grades)/pairsInDay, HALF_UP)
     *   (NB bo'lsa sum=0, ya'ni dayAverage=0)
     * - JN = round(sum(dailyAverages) / totalScheduledDays, HALF_UP)
     *   ya'ni rejalashtirilgan kunlar soniga bo'linadi (NB ham hisobda qoladi)
     *
     * @param array<string,array<string,float>> $studentDayGrades date => pair => grade
     * @param string[] $lessonDates  cutoffgacha bo'lgan rejalashtirilgan kunlar
     * @param array<string,int> $pairsPerDay
     */
    private function dailyAverage(array $studentDayGrades, array $lessonDates, array $pairsPerDay): int
    {
        $totalDays = count($lessonDates);
        if ($totalDays === 0) return 0;
        $dailySum = 0;
        foreach ($lessonDates as $date) {
            $dayGrades = $studentDayGrades[$date] ?? [];
            $pairs = $pairsPerDay[$date] ?? 1;
            $dailySum += round(array_sum($dayGrades) / $pairs, 0, PHP_ROUND_HALF_UP);
        }
        return (int) round($dailySum / $totalDays, 0, PHP_ROUND_HALF_UP);
    }
}

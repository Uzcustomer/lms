<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class StudentGradeService
{
    function g_averageGradesPerStudentPerPeriod($grades)
    {
        $averageGradesPerStudentPerPeriod = [];
        $dates = [];
        foreach ($grades as $grade) {
            $lessonDate = $grade->lesson_date_tashkent;
            // $lessonDate = date('Y-m-d', strtotime($grade->lesson_date));
            $dates[] = Carbon::parse($lessonDate);
            $averageGradesPerStudentPerPeriod[$grade->student_hemis_id][$lessonDate] =
                $grade->grade;
        }
        $dates = collect($dates);
        $dates = $dates->unique()->sort();
        return [
            $averageGradesPerStudentPerPeriod,
            $dates
        ];
    }
    function g_averageGradesForSubject($averageGradesPerStudentPerPeriod)
    {
        $averageGradesForSubject = [];
        foreach ($averageGradesPerStudentPerPeriod as $key => $values) {
            $sum = array_sum($values); // ichki arrayning qiymatlari yig'indisi
            $count = count($values); // ichki arraydagi elementlar soni
            $averageGradesForSubject[$key] = [
                'days' => $count,
                'average' => round($sum / $count,2)
            ];
        }
        return $averageGradesForSubject;
    }

    function g_independent_averageGradesForSubject($averageGradesPerStudentPerPeriod, $count)
    {
        if (empty($count)) {
            $count = 1;
        }
        $averageGradesForSubject = [];
        foreach ($averageGradesPerStudentPerPeriod as $key => $values) {
            $sum = array_sum($values); // ichki arrayning qiymatlari yig'indisi
            $averageGradesForSubject[$key] = [
                'days' => $count,
                'average' => round($sum / $count, 2)
            ];
        }
        return $averageGradesForSubject;
    }


    function computeAverageGrade($gradesList, $semester_code)
    {
        $currentDate = Carbon::now();

        $firstGrade = collect($gradesList)->first();
        if (!$firstGrade)
            return null;

        $gradesByDate = collect($gradesList)->groupBy(function ($grade) {
            return $grade->lesson_date_tashkent;
        });

        $totalAverage = 0;
        $daysWithGrades = 0;

        foreach ($gradesByDate as $date => $dailyGrades) {
            if (Carbon::parse($date)->lte($currentDate)) {
                $dailyAverage = $this->computeDailyAverage($dailyGrades);
                if ($dailyAverage !== null && $dailyAverage !== 'Nb') {
                    $totalAverage += $dailyAverage;
                }
                $daysWithGrades++;
            }
        }

        if ($daysWithGrades === 0)
            return null;

        // Use actual days with grades count instead of Schedule-based count
        // This ensures grades are displayed even if Schedule table is incomplete
        return [
            'average' => round($totalAverage / $daysWithGrades, 2),
            'days' => $daysWithGrades
        ];

    }

    function computeDailyAverage($dailyGrades)
    {
        if (empty($dailyGrades))
            return null;

        $dailyTotal = 0;
        $dailyCount = 0;
        $absentCount = 0;

        foreach ($dailyGrades as $grade) {
            if ($grade->status === 'retake' && ($grade->reason === 'absent' || $grade->reason === 'teacher_victim')) {
                $dailyTotal += $grade->retake_grade ?? 0;
            } elseif ($grade->status === 'retake' && $grade->reason === 'low_grade') {
                $dailyTotal += $grade->retake_grade ?? 0;
            } elseif ($grade->status == 'pending' && $grade->reason === 'absent') {
                $dailyTotal += $grade->grade ?? 0;
                $absentCount++;
            } else {
                $dailyTotal += $grade->grade ?? 0;
            }
            $dailyCount++;
        }

        if ($dailyCount === 0)
            return null;
        if ($absentCount === $dailyCount)
            return 'Nb';

        return round($dailyTotal / $dailyCount);
    }
}

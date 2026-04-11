<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentRating;
use Illuminate\Http\Request;

class StudentRatingController extends Controller
{
    public function index(Request $request)
    {
        // Filtrlar uchun unique qiymatlarni olish
        $departments = StudentRating::whereNotNull('department_name')
            ->select('department_code', 'department_name')
            ->distinct()
            ->orderBy('department_name')
            ->get();

        $specialties = collect();
        $selectedDepartment = $request->input('department');
        $selectedSpecialty = $request->input('specialty');
        $selectedLevel = $request->input('level');

        if ($selectedDepartment) {
            $specialties = StudentRating::where('department_code', $selectedDepartment)
                ->whereNotNull('specialty_name')
                ->select('specialty_code', 'specialty_name')
                ->distinct()
                ->orderBy('specialty_name')
                ->get();
        }

        // Top 10 va qolganlar
        $query = StudentRating::query();

        if ($selectedDepartment) {
            $query->where('department_code', $selectedDepartment);
        }
        if ($selectedSpecialty) {
            $query->where('specialty_code', $selectedSpecialty);
        }
        if ($selectedLevel) {
            $query->where('level_code', $selectedLevel);
        }

        $query->orderBy('rank')->orderByDesc('jn_average');

        $top10 = (clone $query)->limit(10)->get();
        $others = (clone $query)->offset(10)->paginate(20)->withQueryString();
        $totalStudents = (clone $query)->count();

        $lastUpdated = StudentRating::max('calculated_at');

        return view('admin.student-ratings.index', compact(
            'departments', 'specialties', 'top10', 'others', 'totalStudents',
            'selectedDepartment', 'selectedSpecialty', 'selectedLevel', 'lastUpdated'
        ));
    }

    public function subjectDetails(string $studentHemisId)
    {
        $rating = StudentRating::where('student_hemis_id', $studentHemisId)->first();
        if (!$rating) {
            return response()->json(['error' => 'Topilmadi'], 404);
        }

        $student = Student::where('hemis_id', $studentHemisId)->first();
        if (!$student) {
            return response()->json(['error' => 'Talaba topilmadi'], 404);
        }

        $excludeTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $grades = StudentGrade::where('student_hemis_id', $studentHemisId)
            ->where('semester_code', $student->semester_code)
            ->when($student->education_year_code, fn($q) => $q->where(function ($q2) use ($student) {
                $q2->where('education_year_code', $student->education_year_code)
                    ->orWhereNull('education_year_code');
            }))
            ->whereNotIn('training_type_code', $excludeTypes)
            ->whereNotNull('lesson_date')
            ->get();

        $bySubject = $grades->groupBy('subject_id');
        $subjects = [];

        foreach ($bySubject as $subjectId => $subjectGrades) {
            $subjectName = $subjectGrades->first()->subject_name ?? $subjectId;

            $byDate = $subjectGrades->groupBy(fn($g) => substr($g->lesson_date, 0, 10));
            $totalDaily = 0;
            $daysCount = 0;

            foreach ($byDate as $dailyGrades) {
                $dayTotal = 0;
                $dayCount = 0;
                $absentCount = 0;

                foreach ($dailyGrades as $g) {
                    if ($g->status === 'retake') {
                        $dayTotal += $g->retake_grade ?? 0;
                    } elseif ($g->status === 'pending' && $g->reason === 'absent') {
                        $absentCount++;
                    } else {
                        $dayTotal += $g->grade ?? 0;
                    }
                    $dayCount++;
                }

                if ($dayCount === 0) continue;
                $totalDaily += ($absentCount === $dayCount) ? 0 : round($dayTotal / $dayCount);
                $daysCount++;
            }

            $avg = $daysCount > 0 ? round($totalDaily / $daysCount, 1) : 0;

            $subjects[] = [
                'name' => $subjectName,
                'days' => $daysCount,
                'average' => $avg,
            ];
        }

        usort($subjects, fn($a, $b) => $b['average'] <=> $a['average']);

        return response()->json([
            'full_name' => $rating->full_name,
            'group_name' => $rating->group_name,
            'jn_average' => $rating->jn_average,
            'subjects' => $subjects,
        ]);
    }
}

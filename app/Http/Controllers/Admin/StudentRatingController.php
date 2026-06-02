<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\StudentRatingExport;
use App\Models\CurriculumSubject;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Facades\Excel;

class StudentRatingController extends Controller
{
    public function index(Request $request)
    {
        $departments = StudentRating::whereNotNull('department_name')
            ->select('department_code', 'department_name')
            ->distinct()
            ->orderBy('department_name')
            ->get();

        $specialties = collect();
        $selectedDepartment = $request->input('department');
        $selectedSpecialty = $request->input('specialty');
        $selectedLevel = $request->input('level');
        $selectedSubject = $request->input('subject_id');
        $selectedGroup = $request->input('group_name');
        $search = $request->input('search');

        if ($selectedDepartment) {
            $specialties = StudentRating::where('department_code', $selectedDepartment)
                ->whereNotNull('specialty_name')
                ->select('specialty_code', 'specialty_name')
                ->distinct()
                ->orderBy('specialty_name')
                ->get();
        }

        // Guruh dropdown — joriy filtrlar ostida mavjud guruhlar (10 daqiqa cache)
        $groupsCacheKey = 'student_rating.groups.' . md5(($selectedDepartment ?? '') . '|' . ($selectedSpecialty ?? '') . '|' . ($selectedLevel ?? ''));
        $groups = Cache::remember($groupsCacheKey, 600, function () use ($selectedDepartment, $selectedSpecialty, $selectedLevel) {
            $q = StudentRating::whereNotNull('group_name');
            if ($selectedDepartment) $q->where('department_code', $selectedDepartment);
            if ($selectedSpecialty) $q->where('specialty_code', $selectedSpecialty);
            if ($selectedLevel) $q->where('level_code', $selectedLevel);
            return $q->distinct()->orderBy('group_name')->pluck('group_name');
        });

        // Fan dropdown — curriculum_subjects dan (kichik jadval, tez). Student_grades
        // DISTINCT bilan millionlab qator skani ~30 sek davom etadi. Curriculum
        // jadvalida har fan bir necha marta (curriculum × semestr) takrorlanadi
        // shuning uchun subject_id bo'yicha unique qilamiz. 30 daqiqa cache.
        $subjects = Cache::remember('student_rating.subjects.v2', 1800, function () {
            return CurriculumSubject::whereNotNull('subject_name')
                ->select('subject_id', 'subject_name')
                ->orderBy('subject_name')
                ->get()
                ->unique('subject_id')
                ->values();
        });

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
        if ($selectedGroup) {
            $query->where('group_name', $selectedGroup);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', '%' . $search . '%')
                  ->orWhere('group_name', 'like', '%' . $search . '%');
            });
        }
        if ($selectedSubject) {
            $studentIdsWithSubject = StudentGrade::where('subject_id', $selectedSubject)
                ->distinct()->pluck('student_hemis_id');
            $query->whereIn('student_hemis_id', $studentIdsWithSubject);
        }

        $query->orderByDesc('jn_average');
        $totalStudents = (clone $query)->count();

        $top10 = (clone $query)->limit(10)->get();

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $othersQuery = (clone $query)->offset(10 + ($page - 1) * $perPage)->limit($perPage)->get();
        $others = new \Illuminate\Pagination\LengthAwarePaginator(
            $othersQuery, max(0, $totalStudents - 10), $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $lastUpdated = StudentRating::max('calculated_at');

        return view('admin.student-ratings.index', compact(
            'departments', 'specialties', 'groups', 'subjects',
            'top10', 'others', 'totalStudents',
            'selectedDepartment', 'selectedSpecialty', 'selectedLevel',
            'selectedSubject', 'selectedGroup', 'search', 'lastUpdated'
        ));
    }

    public function exportExcel(Request $request)
    {
        return Excel::download(
            new StudentRatingExport(
                $request->input('department'),
                $request->input('specialty'),
                $request->input('level'),
                $request->input('search'),
                $request->input('subject_id'),
                $request->input('group_name')
            ),
            'talabalar_reytingi_' . date('Y-m-d') . '.xlsx'
        );
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

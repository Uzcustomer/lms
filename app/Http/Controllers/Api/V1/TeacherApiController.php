<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\StudentGradeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherApiController extends Controller
{
    protected StudentGradeService $studentGradeService;

    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }

    /**
     * Dashboard — basic teacher info and stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $totalStudentGrades = StudentGrade::where('employee_id', $teacher->hemis_id)->count();
        $pendingGrades = StudentGrade::where('employee_id', $teacher->hemis_id)
            ->where('status', 'pending')
            ->count();
        $groupCount = $teacher->groups()->count();

        $studentsCount = StudentGrade::where('employee_id', $teacher->hemis_id)
            ->distinct('student_hemis_id')
            ->count('student_hemis_id');

        return response()->json([
            'data' => [
                'teacher_name' => $teacher->full_name,
                'department' => $teacher->department,
                'staff_position' => $teacher->staff_position,
                'image' => $teacher->image,
                'total_grades' => $totalStudentGrades,
                'pending_grades' => $pendingGrades,
                'groups_count' => $groupCount,
                'students_count' => $studentsCount,
            ],
        ]);
    }

    /**
     * Teacher info/profile
     */
    public function profile(Request $request): JsonResponse
    {
        $teacher = $request->user();

        return response()->json([
            'data' => [
                'id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'short_name' => $teacher->short_name,
                'first_name' => $teacher->first_name,
                'second_name' => $teacher->second_name,
                'third_name' => $teacher->third_name,
                'employee_id_number' => $teacher->employee_id_number,
                'birth_date' => $teacher->birth_date,
                'image' => $teacher->image,
                'specialty' => $teacher->specialty,
                'gender' => $teacher->gender,
                'department' => $teacher->department,
                'staff_position' => $teacher->staff_position,
                'employment_form' => $teacher->employment_form,
                'phone' => $teacher->phone,
                'login' => $teacher->login ?? $teacher->employee_id_number,
                'roles' => $teacher->getRoleNames(),
            ],
        ]);
    }

    /**
     * Students list for this teacher (with search)
     */
    public function students(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $query = StudentGrade::with(['student'])
            ->where('employee_id', $teacher->hemis_id);

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('student', function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('student_id_number', 'like', "%{$searchTerm}%");
            });
        }

        $studentGrades = $query->paginate(40);

        $groupedStudents = $studentGrades->getCollection()
            ->groupBy('subject_name')
            ->map(function ($grades, $subjectName) {
                $uniqueStudents = $grades->unique('student_id')->values();
                return [
                    'subject_name' => $subjectName,
                    'students' => $uniqueStudents->map(fn($g) => [
                        'student_id' => $g->student_id,
                        'student_hemis_id' => $g->student_hemis_id,
                        'full_name' => $g->student?->full_name,
                        'student_id_number' => $g->student?->student_id_number,
                        'group_name' => $g->student?->group_name,
                        'subject_id' => $g->subject_id,
                    ])->values(),
                ];
            })->values();

        return response()->json([
            'data' => $groupedStudents,
            'meta' => [
                'current_page' => $studentGrades->currentPage(),
                'last_page' => $studentGrades->lastPage(),
                'per_page' => $studentGrades->perPage(),
                'total' => $studentGrades->total(),
            ],
        ]);
    }

    /**
     * Get groups for this teacher
     */
    public function groups(Request $request): JsonResponse
    {
        $teacher = $request->user();

        if ($teacher->hasRole('dekan')) {
            $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids)->get();
        } else {
            $groups = $teacher->groups;
            if ($groups->count() < 1) {
                $groupIds = StudentGrade::where('employee_id', $teacher->hemis_id)
                    ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->groupBy('students.group_id')
                    ->pluck('students.group_id');

                $groups = Group::whereIn('group_hemis_id', $groupIds)->get();
            }
        }

        return response()->json([
            'data' => $groups->map(fn($g) => [
                'id' => $g->id,
                'group_hemis_id' => $g->group_hemis_id,
                'name' => $g->name,
                'curriculum_hemis_id' => $g->curriculum_hemis_id,
                'department_name' => $g->department_name ?? null,
                'students_count' => Student::where('group_id', $g->group_hemis_id)->count(),
            ])->values(),
        ]);
    }

    /**
     * Get semesters for a group
     */
    public function semesters(Request $request): JsonResponse
    {
        $request->validate(['group_id' => 'required|integer']);

        $group = Group::find($request->group_id);
        if (!$group) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get(['id', 'name', 'code', 'current']);

        return response()->json(['data' => $semesters]);
    }

    /**
     * Get subjects for a group + semester
     */
    public function subjects(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required|integer',
            'semester_id' => 'required|integer',
        ]);

        $teacher = $request->user();
        $group = Group::find($request->group_id);
        if (!$group) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $semester = Semester::findOrFail($request->semester_id);

        if ($teacher->hasRole('dekan') || $teacher->groups->contains($group)) {
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get(['id', 'subject_name', 'subject_id', 'credit']);
        } else {
            $subjectIds = StudentGrade::join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                ->where('student_grades.employee_id', $teacher->hemis_id)
                ->where('student_grades.semester_code', $semester->code)
                ->groupBy('student_grades.subject_id')
                ->pluck('student_grades.subject_id');

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->whereIn('subject_id', $subjectIds)
                ->get(['id', 'subject_name', 'subject_id', 'credit']);
        }

        return response()->json(['data' => $subjects]);
    }

    /**
     * Student grade details for a specific student+subject
     */
    public function studentGradeDetails(Request $request, $studentId, $subjectId): JsonResponse
    {
        $teacher = $request->user();

        $grades = StudentGrade::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('employee_id', $teacher->hemis_id)
            ->orderBy('lesson_date', 'desc')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'status' => $g->status,
                'reason' => $g->reason,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'lesson_pair_name' => $g->lesson_pair_name,
                'lesson_pair_start_time' => $g->lesson_pair_start_time,
                'lesson_pair_end_time' => $g->lesson_pair_end_time,
            ]);

        $student = Student::find($studentId);

        return response()->json([
            'data' => [
                'student' => $student ? [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_id_number' => $student->student_id_number,
                    'group_name' => $student->group_name,
                ] : null,
                'grades' => $grades,
            ],
        ]);
    }

    /**
     * Students in a group with their grades for a subject
     */
    public function groupStudentGrades(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required|integer',
            'semester_id' => 'required|integer',
            'subject_id' => 'required|integer',
        ]);

        $teacher = $request->user();
        $group = Group::find($request->group_id);
        if (!$group) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $semester = Semester::findOrFail($request->semester_id);
        $subject = CurriculumSubject::findOrFail($request->subject_id);

        $students = Student::where('group_id', $group->group_hemis_id)->get();
        $studentIds = $students->pluck('hemis_id');

        $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
            ->where('subject_id', $subject->subject_id)
            ->whereNotIn('training_type_code', config('app.training_type_code', [11, 99, 100, 101, 102]))
            ->get();

        $gradesPerStudent = [];
        foreach ($grades as $grade) {
            $gradesPerStudent[$grade->student_hemis_id][] = $grade;
        }

        $result = $students->map(function ($student) use ($gradesPerStudent, $semester) {
            $studentGrades = $gradesPerStudent[$student->hemis_id] ?? [];
            $averageGrade = $this->studentGradeService->computeAverageGrade($studentGrades, $semester->code);

            return [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'hemis_id' => $student->hemis_id,
                'average_grade' => $averageGrade,
                'total_grades' => count($studentGrades),
            ];
        })->values();

        return response()->json([
            'data' => [
                'group' => $group->name,
                'semester' => $semester->name,
                'subject' => $subject->subject_name,
                'students' => $result,
            ],
        ]);
    }
}

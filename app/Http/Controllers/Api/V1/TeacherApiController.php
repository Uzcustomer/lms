<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumSubjectTeacher;
use App\Models\Group;
use App\Models\LessonOpening;
use App\Models\MarkingSystemScore;
use App\Models\Semester;
use App\Models\Setting;
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
            $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids)
                ->where('active', true)
                ->get();
        } else {
            $assignments = $this->getTeacherSubjectAssignments($teacher->hemis_id);
            $groupIds = $assignments['group_ids'];

            if (!empty($groupIds)) {
                $groups = Group::whereIn('group_hemis_id', $groupIds)
                    ->where('active', true)
                    ->orderBy('name')
                    ->get();
            } else {
                $groups = collect();
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

        if ($teacher->hasRole('dekan')) {
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get(['id', 'subject_name', 'subject_id', 'credit']);
        } else {
            $assignments = $this->getTeacherSubjectAssignments($teacher->hemis_id);
            $teacherSubjectIds = $assignments['subject_ids'];

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->whereIn('subject_id', $teacherSubjectIds)
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

    /**
     * Full journal data — 3 tabs: Ma'ruza (attendance), Amaliy (grades), MT (grades)
     * Adapted from JournalController::show() for mobile API
     */
    public function journal(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'required|integer',
            'subject_id' => 'required',
            'semester_code' => 'required',
        ]);

        $group = Group::find($request->group_id);
        if (!$group) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;

        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->first();
        if (!$subject) {
            return response()->json(['message' => 'Fan topilmadi.'], 404);
        }

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        // Education year from schedules
        $educationYearCode = $curriculum?->education_year_code;
        $scheduleEducationYear = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->value('education_year_code');
        if ($scheduleEducationYear) {
            $educationYearCode = $scheduleEducationYear;
        }

        // Students
        $studentHemisIds = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->pluck('hemis_id');

        $students = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->select('id', 'hemis_id', 'full_name', 'student_id_number')
            ->orderBy('full_name')
            ->get();

        // Excluded training types for Amaliy (JB)
        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test"];
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // ==================== SCHEDULE ROWS ====================

        // JB (Amaliy) schedule
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // MT schedule
        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Lecture schedule
        $lectureScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select(DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Lecture attendance controls
        $lectureControlRows = DB::table('attendance_controls')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select(DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Minimum schedule date
        $minScheduleDate = collect()
            ->merge($jbScheduleRows->pluck('lesson_date'))
            ->merge($mtScheduleRows->pluck('lesson_date'))
            ->merge($lectureScheduleRows->pluck('lesson_date'))
            ->min();

        // ==================== GRADES RAW DATA ====================

        // JB grades
        $jbGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('id', 'hemis_id', 'student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason', 'is_final', 'created_at')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // MT grades (with lesson_date)
        $mtGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('id', 'student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason', 'is_final')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // ==================== EFFECTIVE GRADE HELPER ====================

        $getEffectiveGrade = function ($row) {
            if ($row->status === 'pending') {
                return null;
            }
            if ($row->reason === 'absent' && $row->grade === null) {
                if ($row->retake_grade !== null) {
                    return ['grade' => $row->retake_grade, 'is_retake' => true];
                }
                return null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            if ($row->status === 'recorded') {
                return ['grade' => $row->grade, 'is_retake' => false];
            }
            if ($row->status === 'closed') {
                return ['grade' => $row->grade, 'is_retake' => false];
            }
            if ($row->retake_grade !== null) {
                return ['grade' => $row->retake_grade, 'is_retake' => true];
            }
            return null;
        };

        // ==================== BUILD COLUMNS ====================

        $jbColumns = $jbScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
            ->sort(fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']))
            ->values()->toArray();

        $mtColumns = $mtScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
            ->sort(fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']))
            ->values()->toArray();

        $lectureColumns = $lectureScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->merge($lectureControlRows->map(fn($r) => ['date' => $r->lesson_date, 'pair' => $r->lesson_pair_code]))
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
            ->sort(fn($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']))
            ->values()->toArray();

        // ==================== BUILD GRADES MAPS ====================

        $jbGrades = [];
        foreach ($jbGradesRaw as $g) {
            $eff = $getEffectiveGrade($g);
            if ($eff !== null) {
                $jbGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'grade' => $eff['grade'],
                    'is_retake' => $eff['is_retake'],
                    'id' => $g->id,
                    'status' => $g->status,
                    'reason' => $g->reason,
                    'is_final' => $g->is_final,
                ];
            }
        }

        $jbAbsences = [];
        foreach ($jbGradesRaw as $g) {
            if ($g->reason === 'absent') {
                $jbAbsences[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'id' => $g->id,
                    'retake_grade' => $g->retake_grade,
                ];
            }
        }

        $mtGrades = [];
        foreach ($mtGradesRaw as $g) {
            $eff = $getEffectiveGrade($g);
            if ($eff !== null) {
                $mtGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'grade' => $eff['grade'],
                    'is_retake' => $eff['is_retake'],
                    'id' => $g->id,
                    'status' => $g->status,
                    'reason' => $g->reason,
                    'is_final' => $g->is_final,
                ];
            }
        }

        // ==================== LECTURE ATTENDANCE ====================

        $lectureAttendanceRaw = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code', 'absent_on', 'absent_off')
            ->get();

        $lectureAttendance = [];
        foreach ($lectureAttendanceRaw as $row) {
            $isAbsent = ((int) $row->absent_on) > 0 || ((int) $row->absent_off) > 0;
            $status = $isAbsent ? 'NB' : '+';
            $existing = $lectureAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] ?? null;
            if ($existing === 'NB') {
                continue;
            }
            $lectureAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] = $status;
        }

        // Students without attendance records for control sessions → + (present)
        foreach ($studentHemisIds as $studentHemisId) {
            foreach ($lectureControlRows as $row) {
                if (!isset($lectureAttendance[$studentHemisId][$row->lesson_date][$row->lesson_pair_code])) {
                    $lectureAttendance[$studentHemisId][$row->lesson_date][$row->lesson_pair_code] = '+';
                }
            }
        }

        // ==================== OTHER GRADES (ON, OSKI, Test) ====================

        $otherGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('training_type_code', [100, 101, 102])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where(function ($q5) use ($minScheduleDate) {
                                $q5->where('lesson_date', '>=', $minScheduleDate)->orWhereNull('lesson_date');
                            }));
                    });
            }))
            ->select('student_hemis_id', 'training_type_code', 'grade', 'retake_grade', 'status', 'reason')
            ->get();

        $otherGrades = [];
        foreach ($otherGradesRaw as $g) {
            $eff = $getEffectiveGrade($g);
            if ($eff !== null) {
                $otherGrades[$g->student_hemis_id][$g->training_type_code][] = $eff['grade'];
            }
        }
        foreach ($otherGrades as $studentId => $types) {
            $result = ['on' => null, 'oski' => null, 'test' => null];
            if (isset($types[100]) && count($types[100]) > 0) {
                $result['on'] = round(array_sum($types[100]) / count($types[100]), 2);
            }
            if (isset($types[101]) && count($types[101]) > 0) {
                $result['oski'] = round(array_sum($types[101]) / count($types[101]), 2);
            }
            if (isset($types[102]) && count($types[102]) > 0) {
                $result['test'] = round(array_sum($types[102]) / count($types[102]), 2);
            }
            $otherGrades[$studentId] = $result;
        }

        // ==================== ATTENDANCE STATS ====================

        $attendanceData = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_code', [99, 100, 101, 102])
            ->select('student_hemis_id', DB::raw('SUM(absent_off) as total_absent_off'))
            ->groupBy('student_hemis_id')
            ->pluck('total_absent_off', 'student_hemis_id')
            ->toArray();

        // ==================== MANUAL MT GRADES ====================

        $manualMtGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->get()
            ->keyBy('student_hemis_id');
        $manualMtGrades = $manualMtGradesRaw->map(fn($g) => $g->grade)->toArray();

        // ==================== MT SUBMISSIONS ====================

        $mtSubmissions = [];
        try {
            $allCsHemisIds = DB::table('curriculum_subjects')
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->pluck('curriculum_subject_hemis_id')
                ->toArray();

            if (!empty($allCsHemisIds)) {
                $independentIds = DB::table('independents')
                    ->where('group_hemis_id', $group->group_hemis_id)
                    ->where('semester_code', $semesterCode)
                    ->where(function ($q) use ($allCsHemisIds, $subject) {
                        $q->whereIn('subject_hemis_id', $allCsHemisIds)
                          ->orWhere('subject_name', $subject->subject_name);
                    })
                    ->pluck('id')
                    ->toArray();

                if (!empty($independentIds)) {
                    $submissionsRaw = DB::table('independent_submissions')
                        ->whereIn('independent_id', $independentIds)
                        ->get();
                    foreach ($submissionsRaw as $sub) {
                        if (!isset($mtSubmissions[$sub->student_hemis_id])
                            || ($sub->submitted_at ?? '') > ($mtSubmissions[$sub->student_hemis_id]->submitted_at ?? '')) {
                            $mtSubmissions[$sub->student_hemis_id] = $sub;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently handle
        }

        // ==================== MT GRADE HISTORY ====================

        $mtGradeHistory = [];
        try {
            $mtGradeHistoryRaw = DB::table('mt_grade_history')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->orderBy('attempt_number')
                ->get();

            foreach ($mtGradeHistoryRaw as $h) {
                $mtGradeHistory[$h->student_hemis_id][] = [
                    'attempt' => $h->attempt_number,
                    'grade' => round($h->grade),
                    'has_file' => !empty($h->file_path),
                ];
            }
        } catch (\Exception $e) {
            // Silently handle
        }

        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);

        // ==================== LESSON OPENINGS ====================

        $activeOpenedDates = [];
        $lessonOpeningsMap = [];
        try {
            LessonOpening::expireOverdue();
            $activeOpenedDates = LessonOpening::getActiveOpenings($group->group_hemis_id, $subjectId, $semesterCode);

            $lessonOpenings = LessonOpening::getAllOpenings($group->group_hemis_id, $subjectId, $semesterCode);
            foreach ($lessonOpenings as $lo) {
                $loDateStr = $lo->lesson_date->format('Y-m-d');
                $lessonOpeningsMap[$loDateStr] = [
                    'id' => $lo->id,
                    'status' => $lo->isActive() ? 'active' : $lo->status,
                    'deadline' => $lo->deadline->format('Y-m-d H:i'),
                ];
            }
        } catch (\Exception $e) {
            // Silently handle
        }

        // ==================== COMPUTE JB & MT AVERAGES ====================

        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
        }

        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
        }

        // Marking system
        $markingScore = $curriculum && $curriculum->marking_system_code
            ? MarkingSystemScore::where('marking_system_code', $curriculum->marking_system_code)->first()
            : null;
        $minimumLimit = $markingScore ? $markingScore->minimum_limit : 60;

        // ==================== BUILD RESPONSE ====================

        $studentsData = $students->map(function ($student) use (
            $jbGrades, $jbAbsences, $jbColumns, $jbPairsPerDay,
            $mtGrades, $mtColumns, $mtPairsPerDay,
            $lectureAttendance, $lectureColumns,
            $otherGrades, $attendanceData,
            $manualMtGrades, $manualMtGradesRaw, $mtSubmissions,
            $mtGradeHistory, $mtMaxResubmissions,
            $minimumLimit
        ) {
            $hemis = $student->hemis_id;

            // JB (Amaliy) per-date grades
            $jbData = [];
            foreach ($jbColumns as $col) {
                $gradeInfo = $jbGrades[$hemis][$col['date']][$col['pair']] ?? null;
                $absenceInfo = $jbAbsences[$hemis][$col['date']][$col['pair']] ?? null;
                $jbData[] = [
                    'date' => $col['date'],
                    'pair' => $col['pair'],
                    'grade' => $gradeInfo['grade'] ?? null,
                    'is_retake' => $gradeInfo['is_retake'] ?? false,
                    'status' => $gradeInfo['status'] ?? null,
                    'reason' => $gradeInfo['reason'] ?? null,
                    'is_absent' => $absenceInfo !== null,
                    'is_final' => $gradeInfo['is_final'] ?? null,
                    'has_grade' => $gradeInfo !== null,
                ];
            }

            // JB average
            $jbSum = 0;
            $jbDays = 0;
            $gradesByDate = [];
            foreach ($jbData as $item) {
                if ($item['grade'] !== null) {
                    $gradesByDate[$item['date']][] = $item['grade'];
                }
            }
            foreach ($gradesByDate as $date => $grades) {
                $pairsCount = $jbPairsPerDay[$date] ?? 1;
                $jbSum += array_sum($grades) / $pairsCount;
                $jbDays++;
            }
            $totalJbScheduledDays = count(array_unique(array_column($jbColumns, 'date')));
            $jbAvg = $totalJbScheduledDays > 0 ? round($jbSum / $totalJbScheduledDays, 2) : null;

            // MT per-date grades
            $mtData = [];
            foreach ($mtColumns as $col) {
                $gradeInfo = $mtGrades[$hemis][$col['date']][$col['pair']] ?? null;
                $mtData[] = [
                    'date' => $col['date'],
                    'pair' => $col['pair'],
                    'grade' => $gradeInfo['grade'] ?? null,
                    'is_retake' => $gradeInfo['is_retake'] ?? false,
                    'status' => $gradeInfo['status'] ?? null,
                    'has_grade' => $gradeInfo !== null,
                ];
            }

            // Lecture attendance
            $lectData = [];
            foreach ($lectureColumns as $col) {
                $att = $lectureAttendance[$hemis][$col['date']][$col['pair']] ?? null;
                $lectData[] = [
                    'date' => $col['date'],
                    'pair' => $col['pair'],
                    'status' => $att, // '+', 'NB', or null
                ];
            }

            // Manual MT grade + regrade logic
            $mtManualGrade = $manualMtGrades[$hemis] ?? null;
            $hasSubmission = isset($mtSubmissions[$hemis]);
            $mtLocked = $mtManualGrade !== null && $mtManualGrade >= $minimumLimit;

            // Can regrade: student resubmitted after low grade
            $canRegrade = false;
            $waitingResubmit = false;
            if ($mtManualGrade !== null && $mtManualGrade < $minimumLimit && $hasSubmission) {
                $gradeRow = $manualMtGradesRaw[$hemis] ?? null;
                $sub = $mtSubmissions[$hemis];
                if ($gradeRow) {
                    $gradeTime = $gradeRow->updated_at ?? $gradeRow->created_at;
                    if ($gradeTime && ($sub->submitted_at ?? null)) {
                        $hasResubmitted = Carbon::parse($sub->submitted_at)->gt(Carbon::parse($gradeTime));
                        $attemptCount = count($mtGradeHistory[$hemis] ?? []) + 1;
                        $canRegrade = $hasResubmitted && $attemptCount <= $mtMaxResubmissions;
                        $waitingResubmit = !$hasResubmitted && $attemptCount <= $mtMaxResubmissions;
                    }
                }
            }

            // Other grades
            $other = $otherGrades[$hemis] ?? ['on' => null, 'oski' => null, 'test' => null];

            return [
                'id' => $student->id,
                'hemis_id' => $hemis,
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'amaliy' => $jbData,
                'amaliy_avg' => $jbAvg,
                'mt_schedule' => $mtData,
                'mt_manual_grade' => $mtManualGrade !== null ? round($mtManualGrade) : null,
                'mt_has_submission' => $hasSubmission,
                'mt_locked' => $mtLocked,
                'mt_can_regrade' => $canRegrade,
                'mt_waiting_resubmit' => $waitingResubmit,
                'mt_history' => $mtGradeHistory[$hemis] ?? [],
                'lecture' => $lectData,
                'on' => $other['on'],
                'oski' => $other['oski'],
                'test' => $other['test'],
                'absent_count' => (int) ($attendanceData[$hemis] ?? 0),
            ];
        })->values();

        return response()->json([
            'data' => [
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'group_hemis_id' => $group->group_hemis_id,
                ],
                'subject' => [
                    'subject_id' => $subject->subject_id,
                    'subject_name' => $subject->subject_name,
                    'credit' => $subject->credit,
                ],
                'semester' => [
                    'code' => $semesterCode,
                    'name' => $semester?->name,
                ],
                'columns' => [
                    'amaliy' => $jbColumns,
                    'mt' => $mtColumns,
                    'lecture' => $lectureColumns,
                ],
                'active_opened_dates' => $activeOpenedDates,
                'lesson_openings' => $lessonOpeningsMap,
                'minimum_limit' => $minimumLimit,
                'students' => $studentsData,
            ],
        ]);
    }

    /**
     * Save grade for an opened lesson (Amaliy baho qo'yish)
     * Adapted from JournalController::saveOpenedLessonGrade()
     */
    public function saveOpenedLessonGrade(Request $request): JsonResponse
    {
        $request->validate([
            'student_hemis_id' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'lesson_date' => 'required|date',
            'lesson_pair_code' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
            'group_hemis_id' => 'required',
        ]);

        $teacher = $request->user();

        // YN lock check
        $ynLocked = DB::table('student_grades')
            ->where('student_hemis_id', $request->student_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                'yn_locked' => true,
            ], 403);
        }

        // Check active lesson opening
        $groupHemisId = $request->group_hemis_id;
        $lessonDate = Carbon::parse($request->lesson_date)->format('Y-m-d');
        $opening = LessonOpening::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('lesson_date', $lessonDate)
            ->where('status', 'active')
            ->first();

        if (!$opening) {
            return response()->json(['success' => false, 'message' => 'Dars ochilmagan.'], 403);
        }
        if (!$opening->isActive()) {
            return response()->json(['success' => false, 'message' => 'Dars ochilish muddati tugagan.'], 403);
        }

        // Check teacher assignment
        $isAssigned = CurriculumSubjectTeacher::where('employee_id', $teacher->hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('group_id', $groupHemisId)
            ->exists();

        if (!$isAssigned) {
            $isAssigned = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $request->subject_id)
                ->where('semester_code', $request->semester_code)
                ->where('employee_id', $teacher->hemis_id)
                ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
                ->exists();
        }

        if (!$isAssigned) {
            return response()->json(['success' => false, 'message' => 'Siz bu guruhga biriktirilmagansiz.'], 403);
        }

        // Get schedule record
        $schedule = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereDate('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $request->lesson_pair_code)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
            ->first();

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => 'Jadvalda dars topilmadi.'], 404);
        }

        // Check existing grade
        $existing = DB::table('student_grades')
            ->where('student_hemis_id', $request->student_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereDate('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $request->lesson_pair_code)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Bu katak uchun baho allaqachon mavjud.'], 409);
        }

        // Find student
        $student = DB::table('students')->where('hemis_id', $request->student_hemis_id)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Talaba topilmadi.'], 404);
        }

        $gradeValue = (float) $request->grade;
        $now = now();

        // Insert new grade
        DB::table('student_grades')->insert([
            'hemis_id' => 88888888,
            'student_id' => $student->id,
            'student_hemis_id' => $request->student_hemis_id,
            'subject_id' => $request->subject_id,
            'subject_name' => $schedule->subject_name,
            'subject_code' => $schedule->subject_code,
            'semester_code' => $request->semester_code,
            'semester_name' => $schedule->semester_name,
            'subject_schedule_id' => $schedule->schedule_hemis_id,
            'training_type_code' => $schedule->training_type_code,
            'training_type_name' => $schedule->training_type_name,
            'employee_id' => $schedule->employee_id,
            'employee_name' => $schedule->employee_name,
            'lesson_pair_code' => $schedule->lesson_pair_code,
            'lesson_pair_name' => $schedule->lesson_pair_name,
            'lesson_pair_start_time' => $schedule->lesson_pair_start_time,
            'lesson_pair_end_time' => $schedule->lesson_pair_end_time,
            'lesson_date' => $schedule->lesson_date,
            'grade' => $gradeValue,
            'status' => 'recorded',
            'reason' => null,
            'education_year_code' => $schedule->education_year_code ?? null,
            'education_year_name' => $schedule->education_year_name ?? null,
            'created_at_api' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Baho saqlandi.',
            'grade' => $gradeValue,
        ]);
    }

    /**
     * Save MT (Mustaqil ta'lim) grade
     * Adapted from JournalController::saveMtGrade()
     */
    public function saveMtGrade(Request $request): JsonResponse
    {
        $request->validate([
            'student_hemis_id' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $teacher = $request->user();
        $studentHemisId = $request->student_hemis_id;
        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $grade = $request->grade;
        $isRegrade = (bool) $request->input('regrade', false);

        // YN lock check
        $ynLocked = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                'yn_locked' => true,
            ], 403);
        }

        // Get student
        $student = DB::table('students')->where('hemis_id', $studentHemisId)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Talaba topilmadi.'], 404);
        }

        // Get subject
        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();
        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Fan topilmadi.'], 404);
        }

        // Check file submission
        $allCsHemisIds = DB::table('curriculum_subjects')
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->pluck('curriculum_subject_hemis_id')
            ->toArray();

        $independents = DB::table('independents')
            ->where('group_hemis_id', $student->group_id)
            ->where('semester_code', $semesterCode)
            ->where(function ($q) use ($allCsHemisIds, $subject) {
                $q->whereIn('subject_hemis_id', !empty($allCsHemisIds) ? $allCsHemisIds : [0])
                  ->orWhere('subject_name', $subject->subject_name);
            })
            ->get();

        $independentIds = $independents->pluck('id')->toArray();
        $matchedIndependentId = $independents->first()?->id;

        $studentSubmission = null;
        if (!empty($independentIds)) {
            $studentSubmission = DB::table('independent_submissions')
                ->whereIn('independent_id', $independentIds)
                ->where('student_hemis_id', $studentHemisId)
                ->orderByDesc('submitted_at')
                ->first();
            if ($studentSubmission) {
                $matchedIndependentId = $studentSubmission->independent_id;
            }
        }

        if (!$studentSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'Talaba fayl yuklamagan. Baholardan oldin fayl yuklashi kerak.',
                'no_file' => true,
            ], 422);
        }

        // Check existing grade
        $existingGrade = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->first();

        $minimumLimit = MarkingSystemScore::getByStudentHemisId($studentHemisId)->minimum_limit;

        // Permanently locked if grade >= minimum_limit
        if ($existingGrade && $existingGrade->grade >= $minimumLimit) {
            return response()->json([
                'success' => false,
                'locked' => true,
                'can_regrade' => false,
                'message' => 'Baho qulflangan (>= ' . $minimumLimit . '). O\'zgartirib bo\'lmaydi.',
                'grade' => $existingGrade->grade,
            ], 403);
        }

        // Existing grade but not regrade request
        if ($existingGrade && !$isRegrade) {
            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();
            $currentAttempt = $attemptCount + 1;
            $maxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);

            $hasResubmitted = false;
            if ($studentSubmission && $existingGrade->grade < $minimumLimit) {
                $gradeTime = $existingGrade->updated_at ?? $existingGrade->created_at;
                if ($gradeTime && $studentSubmission->submitted_at) {
                    $hasResubmitted = Carbon::parse($studentSubmission->submitted_at)
                        ->gt(Carbon::parse($gradeTime));
                }
            }

            return response()->json([
                'success' => false,
                'locked' => true,
                'can_regrade' => $hasResubmitted && $existingGrade->grade < $minimumLimit && $currentAttempt <= $maxResubmissions,
                'message' => 'Baho allaqachon qo\'yilgan.',
                'grade' => $existingGrade->grade,
                'attempt' => $currentAttempt,
                'max_attempts' => $maxResubmissions,
            ], 403);
        }

        $now = now();

        // Education year
        $curriculum = DB::table('curricula')
            ->where('curricula_hemis_id', $student->curriculum_id ?? null)
            ->first();
        $educationYearCode = $curriculum?->education_year_code;
        $educationYearName = $curriculum?->education_year_name;

        $scheduleEduYear = DB::table('schedules')
            ->where('group_id', $student->group_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->first(['education_year_code', 'education_year_name']);
        if ($scheduleEduYear) {
            $educationYearCode = $scheduleEduYear->education_year_code;
            $educationYearName = $scheduleEduYear->education_year_name ?? $educationYearName;
        }

        if ($existingGrade && $isRegrade) {
            // Verify resubmission
            $gradeTime = $existingGrade->updated_at ?? $existingGrade->created_at;
            $hasResubmitted = false;
            if ($gradeTime && $studentSubmission->submitted_at) {
                $hasResubmitted = Carbon::parse($studentSubmission->submitted_at)
                    ->gt(Carbon::parse($gradeTime));
            }
            if (!$hasResubmitted) {
                return response()->json([
                    'success' => false,
                    'locked' => true,
                    'can_regrade' => false,
                    'message' => 'Talaba hali qayta fayl yuklamagan. Qayta baholash mumkin emas.',
                    'grade' => $existingGrade->grade,
                ], 403);
            }

            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();

            $maxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);
            $currentAttempt = $attemptCount + 1;

            if ($currentAttempt > $maxResubmissions) {
                return response()->json([
                    'success' => false,
                    'locked' => true,
                    'can_regrade' => false,
                    'message' => "Qayta baholash limiti tugagan ({$maxResubmissions} urinish).",
                    'grade' => $existingGrade->grade,
                ], 403);
            }

            // Archive old grade
            DB::table('mt_grade_history')->insert([
                'student_hemis_id' => $studentHemisId,
                'subject_id' => $subjectId,
                'semester_code' => $semesterCode,
                'attempt_number' => $currentAttempt,
                'grade' => $existingGrade->grade,
                'file_path' => $studentSubmission->file_path ?? null,
                'file_original_name' => $studentSubmission->file_original_name ?? null,
                'graded_by' => $teacher->full_name ?? 'Teacher',
                'graded_at' => $existingGrade->updated_at ?? $existingGrade->created_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $newAttempt = $currentAttempt + 1;

            // Update grade
            DB::table('student_grades')
                ->where('id', $existingGrade->id)
                ->update([
                    'grade' => $grade,
                    'updated_at' => $now,
                ]);
        } elseif (!$existingGrade) {
            $historyCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();
            $newAttempt = $historyCount + 1;

            DB::table('student_grades')->insert([
                'hemis_id' => 0,
                'student_id' => $student->id,
                'student_hemis_id' => $studentHemisId,
                'semester_code' => $semesterCode,
                'semester_name' => $subject->semester_name ?? '',
                'education_year_code' => $educationYearCode,
                'education_year_name' => $educationYearName,
                'subject_schedule_id' => 0,
                'subject_id' => $subjectId,
                'subject_name' => $subject->subject_name ?? '',
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => 99,
                'training_type_name' => "Mustaqil ta'lim",
                'employee_id' => $teacher->hemis_id ?? 0,
                'employee_name' => $teacher->full_name ?? 'Teacher',
                'lesson_pair_code' => '1',
                'lesson_pair_name' => 'Manual',
                'lesson_pair_start_time' => '00:00',
                'lesson_pair_end_time' => '00:00',
                'grade' => $grade,
                'lesson_date' => null,
                'independent_id' => $matchedIndependentId,
                'created_at_api' => $now,
                'status' => 'recorded',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $maxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);

        return response()->json([
            'success' => true,
            'message' => 'Baho saqlandi.',
            'locked' => true,
            'can_regrade' => false,
            'waiting_resubmit' => $grade < $minimumLimit && ($newAttempt ?? 1) <= $maxResubmissions,
            'grade' => $grade,
            'attempt' => $newAttempt ?? 1,
            'max_attempts' => $maxResubmissions,
        ]);
    }

    /**
     * Active subjects list — same as web JournalController::index()
     * Returns all active subject+group combinations for the teacher in current semester
     */
    public function activeSubjects(Request $request): JsonResponse
    {
        $teacher = $request->user();

        $assignments = $this->getTeacherSubjectAssignments($teacher->hemis_id);
        $teacherSubjectIds = $assignments['subject_ids'];
        $teacherGroupIds = $assignments['group_ids'];

        if (empty($teacherSubjectIds) && empty($teacherGroupIds)) {
            return response()->json(['data' => []]);
        }

        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->where('s.current', true)
            ->whereIn('cs.subject_id', $teacherSubjectIds)
            ->whereIn('g.group_hemis_id', $teacherGroupIds)
            ->select([
                'cs.subject_id',
                'cs.subject_name',
                'cs.semester_code',
                'cs.semester_name',
                'cs.credit',
                'cs.department_name as kafedra_name',
                'c.education_type_name',
                'g.id as group_id',
                'g.name as group_name',
                'g.group_hemis_id',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
            ])
            ->distinct()
            ->orderBy('g.name')
            ->orderBy('cs.subject_name')
            ->get();

        return response()->json([
            'data' => $query->map(fn($row) => [
                'subject_id' => $row->subject_id,
                'subject_name' => $row->subject_name,
                'semester_code' => $row->semester_code,
                'semester_name' => $row->semester_name,
                'credit' => $row->credit,
                'kafedra_name' => $row->kafedra_name,
                'education_type_name' => $row->education_type_name,
                'group_id' => $row->group_id,
                'group_name' => $row->group_name,
                'group_hemis_id' => $row->group_hemis_id,
                'faculty_name' => $row->faculty_name,
                'specialty_name' => $row->specialty_name,
                'level_name' => $row->level_name,
            ])->values(),
        ]);
    }

    /**
     * O'qituvchining biriktirilgan fan va guruhlarini aniqlash.
     * Web LMS JournalController bilan bir xil logika.
     */
    private function getTeacherSubjectAssignments(int $employeeHemisId): array
    {
        // 1-manba: curriculum_subject_teachers
        $records = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)->get();

        $subjectIds = $records->pluck('subject_id')->unique()->filter()->values()->toArray();
        $groupIds = $records->pluck('group_id')->unique()->filter()->values()->toArray();

        // 2-manba: dars jadvalidan aniqlash
        $scheduleAssignments = $this->getTeacherScheduleAssignments($employeeHemisId);

        // Ikki manbani birlashtirish
        $subjectIds = array_values(array_unique(array_merge($subjectIds, $scheduleAssignments['subject_ids'])));
        $groupIds = array_values(array_unique(array_merge($groupIds, $scheduleAssignments['group_ids'])));

        return [
            'subject_ids' => $subjectIds,
            'group_ids' => $groupIds,
        ];
    }

    /**
     * Dars jadvalidan o'qituvchining fan-guruh biriktirishlarini aniqlash.
     * Har bir fan+guruh uchun eng ko'p dars o'tgan o'qituvchi "egasi" hisoblanadi.
     */
    private function getTeacherScheduleAssignments(int $employeeHemisId): array
    {
        $teacherCombos = DB::table('schedules')
            ->where('employee_id', $employeeHemisId)
            ->where('education_year_current', true)
            ->whereNull('deleted_at')
            ->select('subject_id', 'group_id')
            ->groupBy('subject_id', 'group_id')
            ->get();

        if ($teacherCombos->isEmpty()) {
            return ['subject_ids' => [], 'group_ids' => []];
        }

        $comboSubjectIds = $teacherCombos->pluck('subject_id')->unique()->toArray();
        $comboGroupIds = $teacherCombos->pluck('group_id')->unique()->toArray();

        $allStats = DB::table('schedules')
            ->where('education_year_current', true)
            ->whereNull('deleted_at')
            ->whereIn('subject_id', $comboSubjectIds)
            ->whereIn('group_id', $comboGroupIds)
            ->select('subject_id', 'group_id', 'employee_id')
            ->selectRaw('COUNT(*) as lesson_count')
            ->selectRaw('MAX(lesson_date) as last_lesson')
            ->groupBy('subject_id', 'group_id', 'employee_id')
            ->get();

        $statsByCombo = $allStats->groupBy(fn($item) => $item->subject_id . '-' . $item->group_id);

        $subjectIds = [];
        $groupIds = [];
        $comboKeys = $teacherCombos->map(fn($c) => $c->subject_id . '-' . $c->group_id)->toArray();

        foreach ($statsByCombo as $key => $teachers) {
            if (!in_array($key, $comboKeys)) {
                continue;
            }

            $primary = $teachers->sort(function ($a, $b) {
                if ($a->lesson_count !== $b->lesson_count) {
                    return $b->lesson_count - $a->lesson_count;
                }
                return strcmp($b->last_lesson ?? '', $a->last_lesson ?? '');
            })->first();

            if ($primary && $primary->employee_id == $employeeHemisId) {
                $subjectIds[] = $primary->subject_id;
                $groupIds[] = $primary->group_id;
            }
        }

        return [
            'subject_ids' => array_values(array_unique(array_filter($subjectIds))),
            'group_ids' => array_values(array_unique(array_filter($groupIds))),
        ];
    }
}

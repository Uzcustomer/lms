<?php

namespace App\Http\Controllers\Teacher;

use App\Exports\StudentGradeBox;
use App\Exports\StudentGradesExportAdmin;
use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Department;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use App\Services\StudentGradeService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;

class TeacherMainController extends Controller
{

    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }
    public function index()
    {
        return view('teacher.dashboard');
    }

    public function info()
    {
        $teacher = auth()->user();
        return view('teacher.info', compact('teacher'));
    }

    public function students(Request $request)
    {
        $teacher = auth()->guard('teacher')->user();
        $userRoles = $teacher->getRoleNames()->toArray();
        $activeRole = session('active_role', $userRoles[0] ?? '');
        if (!in_array($activeRole, $userRoles) && count($userRoles) > 0) {
            $activeRole = $userRoles[0];
        }

        $adminRoles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'dekan', 'oquv_prorektori', 'oquv_bolimi', 'inspeksiya'];
        if (in_array($activeRole, $adminRoles)) {
            return $this->studentsAdmin($request);
        }

        $query = StudentGrade::with(['student', 'teacher'])
            ->where('employee_id', $teacher->hemis_id);

        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->whereHas('student', function ($q) use ($searchTerm) {
                $q->where('full_name', 'like', "%{$searchTerm}%")
                    ->orWhere('student_id_number', 'like', "%{$searchTerm}%");
            });
        }

        $studentGrades = $query->paginate(40)->appends(request()->query());

        $groupedStudents = $studentGrades->groupBy('subject_name')->map(function ($grades) {
            return $grades->unique('student_id')->values();
        });

        return view('teacher.students', compact('groupedStudents', 'studentGrades'));
    }

    private function studentsAdmin(Request $request)
    {
        $query = Student::query();

        if ($request->filled('student_id_number')) {
            $query->where('student_id_number', 'like', '%' . $request->student_id_number . '%');
        }

        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . $request->full_name . '%');
        }

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('semester_code', $request->semester_code);
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('specialty')) {
            $query->where('specialty_id', $request->specialty);
        }

        if ($request->filled('group')) {
            $query->where('group_id', $request->group);
        }
        if ($request->filled('curriculum')) {
            $query->where('curriculum_id', $request->curriculum);
        }

        $perPage = $request->get('per_page', 50);
        $students = $query->paginate($perPage)->appends($request->query());

        $departments = Student::select('department_id', 'department_name', 'department_code')
            ->distinct()
            ->orderBy('department_name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->department_id,
                    'name' => $item->department_name . ' (' . $item->department_code . ')'
                ];
            });

        $specialties = Student::select('specialty_id', 'specialty_name', 'specialty_code')
            ->distinct()
            ->orderBy('specialty_name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->specialty_id,
                    'name' => $item->specialty_name . ' (' . $item->specialty_code . ')'
                ];
            });

        $groups = Student::select('group_id', 'group_name')
            ->distinct()
            ->orderBy('group_name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->group_id,
                    'name' => $item->group_name
                ];
            });

        $semesters = Student::select('semester_code', 'semester_name')
            ->distinct()
            ->orderBy('semester_code')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->semester_code,
                    'name' => $item->semester_name
                ];
            });

        $curriculums = Curriculum::select('curricula_hemis_id', 'name')
            ->orderBy('name')
            ->get();

        return view('teacher.students-admin', compact('students', 'departments', 'specialties', 'groups', 'curriculums', 'semesters'));
    }

    public function studentDetails(Request $request, $studentId, $subjectId)
    {
        $grades = StudentGrade::where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('employee_id', auth()->guard('teacher')->user()->hemis_id)
            ->orderBy('lesson_date', 'desc')
            ->get();

        $student = $grades->first()->student;
        $subjectName = $grades->first()->subject_name;

        return view('teacher.student-details', compact('grades', 'student', 'subjectName'));
    }


    public function updateGrade(Request $request, $gradeId)
    {
        $request->validate([
            'grade' => 'required|integer|min:0|max:100',
            'file' => 'nullable|file|max:2048', // 2MB max
        ]);

        $grade = StudentGrade::findOrFail($gradeId);

        if ($grade->status !== 'pending' || ($grade->student->level_code < 14 && $grade->reason !== 'teacher_victim')) {
            return back()->with('error', 'Bu bahoni o\'zgartirish mumkin emas.');
        }

        // Muddat tekshirish: deadline o'tgan bo'lsa, baho qo'yishga ruxsat bermash
        if ($grade->deadline && now()->greaterThan($grade->deadline)) {
            $deadlineFormatted = \Carbon\Carbon::parse($grade->deadline)->format('d.m.Y H:i');
            return back()->with('error', "Otrabotka muddati o'tgan ({$deadlineFormatted}). Baho qo'yish mumkin emas.");
        }

        $grade->update([
            'retake_grade' => $request->grade,
            'status' => 'retake',
            'retake_by' => 'teacher',
            //            'graded_by_user_id' => Auth::id(),
            'retake_graded_at' => Carbon::now(),
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('public/teachers/retake_files');
            $grade->update(['retake_file_path' => Storage::url($path)]);
        }

        return back()->with('success', 'Baho muvaffaqiyatli saqlandi.');
    }

    public function studentGradesWeek(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $groups = collect();

        if ($teacher->hasRole('dekan')) {
            $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids)->get();
        } else {
            $groups = $teacher->groups;
            if (count($groups) < 1) {
                $group_ids = StudentGrade::where('employee_id', $teacher->hemis_id)
                    ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->groupBy('students.group_id')->pluck('students.group_id');

                $groups = Group::whereIn('group_hemis_id', $group_ids)->get();
            }
        }

        $semesters = collect();
        $subjects = collect();
        $students = collect();
        $weeks = collect();
        $dates = collect();
        $subject = null;
        $teacherName = $teacher->full_name;
        $departmentId = $teacher->hasRole('dekan')
            ? $teacher->deanFaculties()->first()?->id
            : Department::where('department_hemis_id', $teacher->department_hemis_id)->first()?->id;
        $viewType = $request->input('viewType', 'week');

        $averageGradesForSubject = [];
        $averageGradesPerStudentPerPeriod = [];

        if ($request->filled('group')) {
            $group = Group::find($request->group);

            if (!$group) {
                abort(404, 'Guruh topilmadi.');
            }
            $hasAccess = $groups->contains($group);
            if (!$hasAccess) {
                abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
            }

            $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)->get();
        }

        if ($request->filled('group') && $request->filled('semester')) {
            $group = Group::find($request->group);
            $semester = Semester::findOrFail($request->semester);

            $hasAccess = $groups->contains($group);

            if (!$hasAccess) {
                abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
            }

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get();
        }

        if ($request->filled(['group', 'semester', 'subject'])) {
            $group = Group::find($request->group);

            if (!$group) {
                abort(404, 'Guruh topilmadi.');
            }

            if (!$groups->contains($group)) {
                abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
            }

            $semester = Semester::findOrFail($request->semester);
            if ($teacher->hasRole('dekan') or $teacher->groups->contains($group)) {
                $subject = CurriculumSubject::findOrFail($request->subject);
            } else {
                $subject_ids = StudentGrade::join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->where('student_grades.employee_id', $teacher->hemis_id)
                    ->where('student_grades.semester_code', $semester->code)
                    // ->where('students.group_id', $group->group_id)
                    ->groupBy('student_grades.subject_id')
                    ->pluck('student_grades.subject_id');

                $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('semester_code', $semester->code)->whereIn('subject_id', $subject_ids)->pluck('id');
                $subject = CurriculumSubject::where('id', $request->subject)->whereIn('id', $subjects)->first();

            }
            if (!$subject) {
                abort(403, 'Fan topilmadi.');
            }

            $students = Student::where('group_id', $group->group_hemis_id)->get();
            $studentIds = $students->pluck('hemis_id');

            if ($viewType == 'week') {
                $weeks = CurriculumWeek::where('semester_hemis_id', $semester->semester_hemis_id)
                    ->orderBy('start_date')
                    ->get();

                $startDate = $weeks->first()->start_date;
                $endDate = $weeks->last()->end_date;

                $dateToWeekIndex = [];
                foreach ($weeks as $index => $week) {
                    $period = CarbonPeriod::create($week->start_date, $week->end_date);
                    foreach ($period as $date) {
                        $dateToWeekIndex[$date->format('Y-m-d')] = $index;
                    }
                }
            } else {
                // whereHas('studentGrades')->
                $lessonDates = Schedule::where('subject_id', $subject->subject_id)
                    ->where('group_id', $group->group_hemis_id)
                    ->where('semester_code', $semester->code)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->distinct('lesson_date')
                    ->pluck('lesson_date')
                    ->map(function ($date) {
                        return Carbon::parse($date);
                    })->unique()->sort();
                $dates = $lessonDates;
                $startDate = $dates->first();
                $endDate = $dates->last();
            }
            $traning_type = $request->traning_type ?? "joriy";
            if ($traning_type == 'joriy') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->whereBetween('lesson_date', [$startDate, $endDate])
                    ->get();

                $gradesPerStudent = [];
                $gradesPerStudentPerPeriod = [];

                foreach ($grades as $grade) {
                    $studentId = $grade->student_hemis_id;
                    $lessonDate =  $grade->lesson_date_tashkent;;

                    if (!isset($gradesPerStudent[$studentId])) {
                        $gradesPerStudent[$studentId] = [];
                    }
                    $gradesPerStudent[$studentId][] = $grade;

                    if ($viewType == 'week') {
                        $periodKey = $dateToWeekIndex[$lessonDate] ?? null;
                    } else {
                        $periodKey = $lessonDate;
                    }

                    if ($periodKey !== null) {
                        if (!isset($gradesPerStudentPerPeriod[$studentId][$periodKey])) {
                            $gradesPerStudentPerPeriod[$studentId][$periodKey] = [];
                        }
                        $gradesPerStudentPerPeriod[$studentId][$periodKey][] = $grade;
                    }
                }

                $averageGradesForSubject = [];
                $averageGradesPerStudentPerPeriod = [];
                foreach ($students as $student) {
                    $studentGrades = $gradesPerStudent[$student->hemis_id] ?? [];
                    $averageGradesForSubject[$student->hemis_id] = $this->studentGradeService->computeAverageGrade($studentGrades,$semester->code);

                    if (!isset($averageGradesPerStudentPerPeriod[$student->hemis_id])) {
                        $averageGradesPerStudentPerPeriod[$student->hemis_id] = [];
                    }

                    if ($viewType == 'week') {
                        foreach ($weeks as $index => $week) {
                            $weekGrades = $gradesPerStudentPerPeriod[$student->hemis_id][$index] ?? [];
                            $averageGradesPerStudentPerPeriod[$student->hemis_id][$index] =
                                empty($weekGrades) ? null : $this->studentGradeService->computeDailyAverage($weekGrades);
                        }
                    } else {
                        foreach ($dates as $date) {
                            $dateKey = $date->format('Y-m-d');
                            $dailyGrades = $gradesPerStudentPerPeriod[$student->hemis_id][$dateKey] ?? [];
                            $averageGradesPerStudentPerPeriod[$student->hemis_id][$dateKey] =
                                empty($dailyGrades) ? null : $this->studentGradeService->computeDailyAverage($dailyGrades);
                        }
                    }
                }
            } elseif ($traning_type == 'mustaqil') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 99)
                    ->where('semester_code', $semester->code)
                    ->select(DB::raw('sum(grade) as grade'), 'student_hemis_id', 'lesson_date')
                    ->groupBy('student_hemis_id', 'lesson_date')
                    ->get();

                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = $data[1];
                $averageGradesForSubject = $this->studentGradeService->g_averageGradesForSubject($averageGradesPerStudentPerPeriod);


            } elseif ($traning_type == 'oraliq') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 100)
                    ->where('semester_code', $semester->code)
                    ->get();

                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = $data[1];
                $averageGradesForSubject = $this->studentGradeService->g_averageGradesForSubject($averageGradesPerStudentPerPeriod);

            } elseif ($traning_type == 'oski') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 101)
                    ->where('semester_code', $semester->code)
                    ->get();
                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = [];
                $averageGradesForSubject = $this->studentGradeService->g_averageGradesForSubject($averageGradesPerStudentPerPeriod);
                $averageGradesPerStudentPerPeriod = [];

            } elseif ($traning_type == 'examtest') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 102)
                    ->where('semester_code', $semester->code)
                    ->get();
                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = [];
                $averageGradesForSubject = $this->studentGradeService->g_averageGradesForSubject($averageGradesPerStudentPerPeriod);
                $averageGradesPerStudentPerPeriod = [];

            }


        }

        // dump($averageGradesPerStudentPerPeriod);
        // dump($dates);
        // dd($averageGradesForSubject);
        return view('teacher.students.week-grades', compact(
            'groups',
            'semesters',
            'subjects',
            'students',
            'weeks',
            'dates',
            'subject',
            'viewType',
            'teacherName',
            'departmentId',
            'averageGradesForSubject',
            'averageGradesPerStudentPerPeriod'
        ));
    }


    public function getSemestersNew(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $group = Group::find($request->group_id);

        // $group = $teacher->groups()->find($request->group_id);

        if (!$group) {
            return response()->json(['error' => 'Guruh topilmadi'], 404);
        }
        if ($teacher->hasRole('dekan')) {
            $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids);
        } else {
            $groups = $teacher->groups;
            if (count($groups) < 1) {
                $group_ids = StudentGrade::where('employee_id', $teacher->hemis_id)
                    ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->groupBy('students.group_id')->pluck('students.group_id');

                $groups = Group::whereIn('group_hemis_id', $group_ids);
            }
        }
        $count = $groups->where('id', $request->group_id)->count();
        if ($count < 1) {
            return response()->json(['error' => 'Siz bu guruhga bog\'lanmagansiz'], 403);
        }

        $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get(['id', 'name', 'current']);

        return response()->json($semesters);
    }

    public function getSemesters(Request $request)
    {
        $semesters = Schedule::where('group_id', $request->group_id)
            ->select('semester_code as id', 'semester_name as name')
            ->distinct()
            ->get();

        return response()->json($semesters);
    }
    public function getSubjects(Request $request)
    {
        $subjects = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
        ])
            ->select('subject_id as id', 'subject_name as name')
            ->groupBy('subject_id', 'subject_name')
            ->get();

        return response()->json($subjects);
    }
    public function getSubjectsNew(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $group = Group::find($request->group_id);
        if (!$group) {
            return response()->json(['error' => 'Guruh topilmadi'], 404);
        }

        if ($teacher->hasRole('dekan')) {
            $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids);
        } else {
            $groups = $teacher->groups;
            if (count($groups) < 1) {
                $group_ids = StudentGrade::where('employee_id', $teacher->hemis_id)
                    ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->groupBy('students.group_id')->pluck('students.group_id');

                $groups = Group::whereIn('group_hemis_id', $group_ids);
            }
        }
        $count = $groups->where('id', $request->group_id)->count();
        if ($count < 1) {
            return response()->json(['error' => 'Siz bu guruhga bog\'lanmagansiz'], 403);
        }

        $semester = Semester::findOrFail($request->semester_id);

        if ($teacher->hasRole('dekan') or $teacher->groups->contains($group)) {
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->pluck('subject_name', 'id');
        } else {
            $subject_ids = StudentGrade::join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                ->where('student_grades.employee_id', $teacher->hemis_id)
                ->where('student_grades.semester_code', $semester->code)
                // ->where('students.group_id', $group->group_id)
                ->groupBy('student_grades.subject_id')
                ->pluck('student_grades.subject_id');

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)->whereIn('subject_id', $subject_ids)->pluck('subject_name', 'id');

        }


        return response()->json($subjects);
    }

    public function exportStudentGrades(Request $request)
    {
        $filters = $request->only(['department', 'group', 'semester', 'subject']);
        return Excel::download(new StudentGradesExportAdmin($filters), 'Talaba baholari.xlsx');
    }

    public function exportStudentGradesBox(Request $request)
    {
        $filters = $request->only(['department', 'group', 'semester', 'subject']);
        $export = new StudentGradeBox($filters);
        return $export->export();
    }

}

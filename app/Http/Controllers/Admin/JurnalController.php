<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\StudentGradeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JurnalController extends Controller
{
    protected $studentGradeService;

    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }

    /**
     * Jurnal sahifasi - asosiy ko'rinish
     */
    public function index(Request $request)
    {
        // Filterlar uchun ma'lumotlar
        $departments = Department::where('structure_type_code', 11)->orderBy('name')->get();
        $levelCodes = collect([
            '11' => '1-kurs',
            '12' => '2-kurs',
            '13' => '3-kurs',
            '14' => '4-kurs',
            '15' => '5-kurs',
            '16' => '6-kurs'
        ]);

        $groups = collect();
        $semesters = collect();
        $subjects = collect();
        $students = collect();
        $dates = collect();
        $gradesData = [];

        $selectedDepartment = null;
        $selectedGroup = null;
        $selectedSemester = null;
        $selectedSubject = null;
        $teacherName = null;

        // Cascading filters
        if ($request->filled('department_id')) {
            $selectedDepartment = Department::find($request->department_id);
            if ($selectedDepartment) {
                $groups = Group::where('department_hemis_id', $selectedDepartment->department_hemis_id)->get();
            }
        }

        if ($request->filled('level_code')) {
            $groupIds = Student::where('level_code', $request->level_code)
                ->where('student_status_code', '11')
                ->pluck('group_id')
                ->unique()
                ->toArray();

            if ($groups->isNotEmpty()) {
                $groups = $groups->whereIn('group_hemis_id', $groupIds);
            } else {
                $groups = Group::whereIn('group_hemis_id', $groupIds)->get();
            }
        }

        if ($request->filled('group_id')) {
            $selectedGroup = Group::find($request->group_id);
            if ($selectedGroup) {
                $semesters = Semester::where('curriculum_hemis_id', $selectedGroup->curriculum_hemis_id)->get();
            }
        }

        if ($request->filled('semester_id') && $request->filled('group_id')) {
            $selectedSemester = Semester::find($request->semester_id);
            if ($selectedGroup && $selectedSemester) {
                $subjects = CurriculumSubject::where('curricula_hemis_id', $selectedGroup->curriculum_hemis_id)
                    ->where('semester_code', $selectedSemester->code)
                    ->get();
            }
        }

        // Asosiy ma'lumotlarni olish
        if ($request->filled(['group_id', 'semester_id', 'subject_id'])) {
            $selectedGroup = Group::findOrFail($request->group_id);
            $selectedSemester = Semester::findOrFail($request->semester_id);
            $selectedSubject = CurriculumSubject::findOrFail($request->subject_id);

            // O'qituvchi nomi
            $teacher = StudentGrade::where('subject_id', $selectedSubject->subject_id)
                ->whereNotNull('employee_name')
                ->first();
            $teacherName = $teacher ? $teacher->employee_name : 'Noma\'lum';

            // Talabalar
            $students = Student::where('group_id', $selectedGroup->group_hemis_id)
                ->where('student_status_code', '11')
                ->orderBy('full_name')
                ->get();

            if ($request->filled('level_code')) {
                $students = $students->where('level_code', $request->level_code);
            }

            $studentIds = $students->pluck('hemis_id');

            // Dars kunlari
            $dates = Schedule::where('subject_id', $selectedSubject->subject_id)
                ->where('group_id', $selectedGroup->group_hemis_id)
                ->where('semester_code', $selectedSemester->code)
                ->whereNotIn('training_type_code', config('app.training_type_code', []))
                ->distinct('lesson_date')
                ->orderBy('lesson_date')
                ->pluck('lesson_date')
                ->map(fn($date) => Carbon::parse($date));

            if ($dates->isNotEmpty()) {
                $startDate = $dates->first();
                $endDate = $dates->last();

                // Joriy baholar
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $selectedSubject->subject_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code', []))
                    ->whereBetween('lesson_date', [$startDate, $endDate])
                    ->get();

                // MT baholar
                $mtGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $selectedSubject->subject_id)
                    ->where('training_type_code', 99)
                    ->where('semester_code', $selectedSemester->code)
                    ->get()
                    ->groupBy('student_hemis_id');

                // Oraliq nazorat
                $oraliqGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $selectedSubject->subject_id)
                    ->where('training_type_code', 100)
                    ->where('semester_code', $selectedSemester->code)
                    ->get()
                    ->groupBy('student_hemis_id');

                // OSKI
                $oskiGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $selectedSubject->subject_id)
                    ->where('training_type_code', 101)
                    ->where('semester_code', $selectedSemester->code)
                    ->get()
                    ->groupBy('student_hemis_id');

                // Test
                $testGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $selectedSubject->subject_id)
                    ->where('training_type_code', 102)
                    ->where('semester_code', $selectedSemester->code)
                    ->get()
                    ->groupBy('student_hemis_id');

                // Baholarni talaba va sana bo'yicha guruhlash
                $gradesPerStudentPerDate = [];
                foreach ($grades as $grade) {
                    $studentId = $grade->student_hemis_id;
                    $dateKey = Carbon::parse($grade->lesson_date)->format('Y-m-d');

                    if (!isset($gradesPerStudentPerDate[$studentId][$dateKey])) {
                        $gradesPerStudentPerDate[$studentId][$dateKey] = [];
                    }
                    $gradesPerStudentPerDate[$studentId][$dateKey][] = $grade;
                }

                // Har bir talaba uchun ma'lumotlarni tayyorlash
                foreach ($students as $student) {
                    $studentId = $student->hemis_id;
                    $studentGrades = $gradesPerStudentPerDate[$studentId] ?? [];

                    // Kunlik baholar
                    $dailyGrades = [];
                    $totalSum = 0;
                    $totalDays = 0;

                    foreach ($dates as $date) {
                        $dateKey = $date->format('Y-m-d');
                        $dayGrades = $studentGrades[$dateKey] ?? [];

                        if (!empty($dayGrades)) {
                            $dayAvg = collect($dayGrades)->avg(function ($g) {
                                if ($g->status === 'retake' && $g->retake_grade) {
                                    return $g->retake_grade;
                                }
                                return $g->grade;
                            });
                            $dailyGrades[$dateKey] = round($dayAvg);
                            $totalSum += $dayAvg;
                            $totalDays++;

                            // Davomat tekshirish
                            $isAbsent = collect($dayGrades)->every(fn($g) => $g->reason === 'absent');
                            if ($isAbsent) {
                                $dailyGrades[$dateKey . '_absent'] = true;
                            }
                        } else {
                            $dailyGrades[$dateKey] = null;
                        }
                    }

                    // JN o'rtacha
                    $jnAvg = $totalDays > 0 ? round($totalSum / $totalDays) : 0;

                    // MT o'rtacha
                    $studentMt = $mtGrades[$studentId] ?? collect();
                    $mtAvg = $studentMt->isNotEmpty() ? round($studentMt->avg('grade')) : 0;

                    // Oraliq
                    $studentOraliq = $oraliqGrades[$studentId] ?? collect();
                    $oraliqAvg = $studentOraliq->isNotEmpty() ? round($studentOraliq->avg('grade')) : 0;

                    // OSKI
                    $studentOski = $oskiGrades[$studentId] ?? collect();
                    $oskiAvg = $studentOski->isNotEmpty() ? round($studentOski->avg('grade')) : 0;

                    // Test
                    $studentTest = $testGrades[$studentId] ?? collect();
                    $testAvg = $studentTest->isNotEmpty() ? round($studentTest->avg('grade')) : 0;

                    $gradesData[$studentId] = [
                        'daily' => $dailyGrades,
                        'jn' => $jnAvg,
                        'mt' => $mtAvg,
                        'oraliq' => $oraliqAvg,
                        'oski' => $oskiAvg,
                        'test' => $testAvg,
                    ];
                }
            }
        }

        return view('admin.jurnal.index', compact(
            'departments',
            'levelCodes',
            'groups',
            'semesters',
            'subjects',
            'students',
            'dates',
            'gradesData',
            'selectedDepartment',
            'selectedGroup',
            'selectedSemester',
            'selectedSubject',
            'teacherName'
        ));
    }

    /**
     * Cascade filter: Guruhlarni olish
     */
    public function getGroups(Request $request)
    {
        $query = Group::query();

        if ($request->filled('department_id')) {
            $department = Department::find($request->department_id);
            if ($department) {
                $query->where('department_hemis_id', $department->department_hemis_id);
            }
        }

        if ($request->filled('level_code')) {
            $groupIds = Student::where('level_code', $request->level_code)
                ->where('student_status_code', '11')
                ->pluck('group_id')
                ->unique()
                ->toArray();
            $query->whereIn('group_hemis_id', $groupIds);
        }

        $groups = $query->orderBy('name')->get(['id', 'name']);

        return response()->json($groups);
    }

    /**
     * Cascade filter: Semestrlarni olish
     */
    public function getSemesters(Request $request)
    {
        $group = Group::find($request->group_id);

        if (!$group) {
            return response()->json([]);
        }

        $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get(['id', 'name', 'current']);

        return response()->json($semesters);
    }

    /**
     * Cascade filter: Fanlarni olish
     */
    public function getSubjects(Request $request)
    {
        $group = Group::find($request->group_id);
        $semester = Semester::find($request->semester_id);

        if (!$group || !$semester) {
            return response()->json([]);
        }

        $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->get(['id', 'subject_name as name']);

        return response()->json($subjects);
    }
}

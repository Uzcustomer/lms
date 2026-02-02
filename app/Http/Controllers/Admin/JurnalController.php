<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
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
     * Jurnal sahifasi - fanlar ro'yxati
     */
    public function index(Request $request)
    {
        // Filterlar uchun ma'lumotlar
        $departments = Department::where('structure_type_code', 11)->orderBy('name')->get();
        $educationYears = Curriculum::select('education_year_code', 'education_year_name')
            ->distinct()
            ->orderBy('education_year_code', 'desc')
            ->get();
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->distinct()
            ->orderBy('education_type_name')
            ->get();
        $levelCodes = collect([
            '11' => '1-kurs',
            '12' => '2-kurs',
            '13' => '3-kurs',
            '14' => '4-kurs',
            '15' => '5-kurs',
            '16' => '6-kurs'
        ]);

        // Asosiy query - Guruhlar orqali fanlarni olish
        $query = Group::query()
            ->select([
                'groups.id as group_id',
                'groups.name as group_name',
                'groups.group_hemis_id',
                'groups.curriculum_hemis_id',
                'groups.department_name as fakultet',
                'groups.specialty_name as yonalish',
                'curricula.education_type_name as talim_turi',
                'curricula.education_year_name as oquv_yili',
                'curricula.education_year_code',
            ])
            ->join('curricula', 'groups.curriculum_hemis_id', '=', 'curricula.curricula_hemis_id')
            ->whereNotNull('groups.curriculum_hemis_id');

        // Filterlarni qo'llash
        if ($request->filled('department_id')) {
            $department = Department::find($request->department_id);
            if ($department) {
                $query->where('groups.department_hemis_id', $department->department_hemis_id);
            }
        }

        if ($request->filled('education_year')) {
            $query->where('curricula.education_year_code', $request->education_year);
        }

        if ($request->filled('education_type')) {
            $query->where('curricula.education_type_code', $request->education_type);
        }

        if ($request->filled('level_code')) {
            $groupIds = Student::where('level_code', $request->level_code)
                ->where('student_status_code', '11')
                ->pluck('group_id')
                ->unique()
                ->toArray();
            $query->whereIn('groups.group_hemis_id', $groupIds);
        }

        $groups = $query->orderBy('curricula.education_year_code', 'desc')
            ->orderBy('groups.department_name')
            ->orderBy('groups.name')
            ->get();

        // Har bir guruh uchun semestr va fanlarni olish
        $jurnalList = collect();

        foreach ($groups as $group) {
            // Guruh uchun kursni aniqlash (talabalardan)
            $levelCode = Student::where('group_id', $group->group_hemis_id)
                ->where('student_status_code', '11')
                ->value('level_code');

            if ($request->filled('level_code') && $levelCode != $request->level_code) {
                continue;
            }

            $kurs = $levelCodes[$levelCode] ?? 'Noma\'lum';

            // Guruh curriculum'iga tegishli semestrlar
            $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)->get();

            foreach ($semesters as $semester) {
                // Semestrga tegishli fanlar
                $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->where('semester_code', $semester->code)
                    ->get();

                foreach ($subjects as $subject) {
                    $jurnalList->push([
                        'group_id' => $group->group_id,
                        'group_hemis_id' => $group->group_hemis_id,
                        'group_name' => $group->group_name,
                        'talim_turi' => $group->talim_turi,
                        'oquv_yili' => $group->oquv_yili,
                        'fakultet' => $group->fakultet,
                        'yonalish' => $group->yonalish,
                        'kurs' => $kurs,
                        'level_code' => $levelCode,
                        'semester_id' => $semester->id,
                        'semester_name' => $semester->name,
                        'subject_id' => $subject->id,
                        'subject_name' => $subject->subject_name,
                    ]);
                }
            }
        }

        // Pagination uchun
        $perPage = 25;
        $currentPage = $request->get('page', 1);
        $total = $jurnalList->count();
        $jurnalItems = $jurnalList->slice(($currentPage - 1) * $perPage, $perPage);

        $pagination = new \Illuminate\Pagination\LengthAwarePaginator(
            $jurnalItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.jurnal.index', compact(
            'departments',
            'educationYears',
            'educationTypes',
            'levelCodes',
            'pagination'
        ));
    }

    /**
     * Jurnal ko'rish - batafsil
     */
    public function show(Request $request)
    {
        $group = Group::findOrFail($request->group_id);
        $semester = Semester::findOrFail($request->semester_id);
        $subject = CurriculumSubject::findOrFail($request->subject_id);

        $levelCodes = collect([
            '11' => '1-kurs',
            '12' => '2-kurs',
            '13' => '3-kurs',
            '14' => '4-kurs',
            '15' => '5-kurs',
            '16' => '6-kurs'
        ]);

        // O'qituvchi nomi
        $teacher = StudentGrade::where('subject_id', $subject->subject_id)
            ->whereNotNull('employee_name')
            ->first();
        $teacherName = $teacher ? $teacher->employee_name : 'Noma\'lum';

        // Talabalar
        $students = Student::where('group_id', $group->group_hemis_id)
            ->where('student_status_code', '11')
            ->orderBy('full_name')
            ->get();

        $studentIds = $students->pluck('hemis_id');

        // Dars kunlari
        $dates = Schedule::where('subject_id', $subject->subject_id)
            ->where('group_id', $group->group_hemis_id)
            ->where('semester_code', $semester->code)
            ->whereNotIn('training_type_code', config('app.training_type_code', []))
            ->distinct('lesson_date')
            ->orderBy('lesson_date')
            ->pluck('lesson_date')
            ->map(fn($date) => Carbon::parse($date));

        $gradesData = [];

        if ($dates->isNotEmpty()) {
            $startDate = $dates->first();
            $endDate = $dates->last();

            // Joriy baholar
            $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->whereNotIn('training_type_code', config('app.training_type_code', []))
                ->whereBetween('lesson_date', [$startDate, $endDate])
                ->get();

            // MT baholar
            $mtGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->where('training_type_code', 99)
                ->where('semester_code', $semester->code)
                ->get()
                ->groupBy('student_hemis_id');

            // Oraliq nazorat
            $oraliqGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->where('training_type_code', 100)
                ->where('semester_code', $semester->code)
                ->get()
                ->groupBy('student_hemis_id');

            // OSKI
            $oskiGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->where('training_type_code', 101)
                ->where('semester_code', $semester->code)
                ->get()
                ->groupBy('student_hemis_id');

            // Test
            $testGrades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->where('training_type_code', 102)
                ->where('semester_code', $semester->code)
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

        return view('admin.jurnal.show', compact(
            'group',
            'semester',
            'subject',
            'students',
            'dates',
            'gradesData',
            'teacherName',
            'levelCodes'
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

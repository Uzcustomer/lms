<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentGradeBox;
use App\Exports\StudentGradesExport;
use App\Exports\StudentGradesExportAdmin;
use App\Http\Controllers\Controller;
use App\Imports\StudentGradeUpdateViaExcel;
use App\Services\ActivityLogService;
use App\Models\Attendance;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\ExamTest;
use App\Models\Group;
use App\Models\Independent;
use App\Models\MarkingSystemScore;
use App\Models\Oski;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\StudentGrade;
use App\Models\Setting;
use App\Models\StudentPerformance;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
    use App\Models\Student;
    use App\Services\StudentGradeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

class StudentController extends Controller
{
    protected string $token;
    public $studentGradeService;
    public function __construct()
    {
        $this->token = config('services.hemis.token');
        $this->studentGradeService = new StudentGradeService;
    }

    private function getAbsentOffSum($groupId, $subjectId, $hemisId): int
    {
        return (int) Attendance::where('group_id', $groupId)
            ->where('subject_id', $subjectId)
            ->where('student_hemis_id', $hemisId)
            ->sum('absent_off');
    }

    public function index(Request $request)
    {
        $query = Student::query();

        if ($request->filled('student_id_number')) {
            $query->where('student_id_number', 'like', '%' . $request->student_id_number . '%');
        }

        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . $request->full_name . '%');
        }

        if ($request->filled('student_id_number')) {
            $query->where('student_id_number', $request->student_id_number);
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


        return view('admin.students.index', compact('students', 'departments', 'specialties', 'groups', 'curriculums', 'semesters'));
    }

    public function resetLocalPassword(Request $request, Student $student)
    {
        try {
            $request->validate([
                'password_type' => 'required|in:auto,manual',
                'custom_password' => 'required_if:password_type,manual|nullable|string|min:4',
            ]);

            $temporaryPassword = $request->password_type === 'manual'
                ? $request->custom_password
                : $student->student_id_number;

            $tempDays = (int) Setting::get('temp_password_days', 3);

            $student->local_password = $temporaryPassword;
            $student->local_password_expires_at = now()->addDays($tempDays);
            $student->must_change_password = true;
            $student->save();

            return back()->with('success', "{$student->full_name} uchun vaqtinchalik parol o'rnatildi: {$temporaryPassword} ({$tempDays} kun amal qiladi)");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Parolni tiklashda xatolik: ' . $e->getMessage());
            return back()->with('error', "Parolni tiklashda xatolik yuz berdi. Iltimos, migratsiyalar ishga tushirilganligini tekshiring.");
        }
    }


    public function getCurricula(Request $request)
    {
        $year = $request->input('year');

        if ($year) {
            $curricula = Curriculum::where('education_year_name', $year)->get(['curricula_hemis_id as id', 'name']);
            return response()->json($curricula);
        }

        return response()->json([]);
    }

    public function getSubjects(Request $request)
    {
        $curriculumId = $request->input('curriculum_id');

        if ($curriculumId) {
            $subjects = CurriculumSubject::where('curricula_hemis_id', $curriculumId)->get(['subject_id as id', 'subject_name as name']);
            return response()->json($subjects);
        }

        return response()->json([]);
    }

    public function grades(Request $request, $hemis_id)
    {
        $grades = StudentGrade::where('student_hemis_id', $hemis_id)
            ->orderBy('lesson_date', 'desc')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.students.grades', compact('grades', 'hemis_id'));
    }


    public function attendance(Request $request, string $hemisId): View
    {
        $educationYear = $request->input('_education_year', date('Y'));

        $query = Attendance::where('student_hemis_id', $hemisId);

        if ($educationYear) {
            $query->where('education_year_code', $educationYear);
        }

        $attendances = $query->orderBy('lesson_date', 'desc')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.students.attendance', compact('attendances', 'hemisId', 'educationYear'));
    }

    public function getStudents()
    {
        try {
            $students = Student::select(['id', 'hemis_id', 'full_name', 'student_id_number', 'birth_date', 'avg_gpa', 'department_name', 'specialty_name', 'group_name'])->take(10);

            \Log::info('Students query:', ['count' => $students->count(), 'sql' => $students->toSql()]);

            $result = DataTables::of($students)
                ->addIndexColumn()
                ->addColumn('action', function ($row) {
                    $actionBtn = '<a href="javascript:void(0)" class="edit btn btn-success btn-sm">Edit</a> <a href="javascript:void(0)" class="delete btn btn-danger btn-sm">Delete</a>';
                    return $actionBtn;
                })
                ->rawColumns(['action'])
                ->toJson();

            \Log::info('DataTables result:', ['result' => $result]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error in getStudents: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function low_grades($hemis_id, Request $request)
    {
        $student = Student::where('hemis_id', $hemis_id)->first();
        $threshold = ($student && $student->level_code == 16) ? 3 : 60;

        $grades = StudentGrade::where('student_hemis_id', $hemis_id)
            ->where('grade', '<', $threshold)
            ->whereNotNull('grade')
            ->orderBy('lesson_date', 'desc')
            ->paginate(20)
            ->appends($request->query());

        return view('admin.students.low_grades', compact('grades', 'hemis_id'));
    }

    public function student_low(Request $request, $hemisId)
    {
        $perPage = $request->get('per_page', 50);

        $grades = StudentGrade::with('student')
            ->where('student_hemis_id', $hemisId)
            ->whereIn('status', ['pending', 'retake', 'closed'])
            ->orderBy('lesson_date', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        return view('admin.students.student-performances', compact('grades', 'hemisId'));
    }

    public function updateGrade(Request $request, $gradeId)
    {
        $request->validate([
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $grade = StudentGrade::findOrFail($gradeId);
        $grade->update([
            //            'grade' => $request->grade,
            'retake_grade' => $request->grade,
            'status' => 'retake',
            'graded_by_user_id' => Auth::user()->id,
            'retake_graded_at' => Carbon::now(),
        ]);

        return redirect()->back()->with('success', 'Baho muvaffaqiyatli yangilandi.');
    }

    public function updateStatus(Request $request, $gradeId)
    {
        $request->validate([
            'status' => 'required|in:pending,retake,closed',
            'deadline' => 'nullable|date',
        ]);


        $grade = StudentGrade::findOrFail($gradeId);
        if ($request->status == "retake" && $grade->retake_grade == null) {
            return redirect()->back()->with('error', 'Qayta topshirish bahosini kiriting.');
        }


        if ($request->filled('deadline')) {
            $deadline = Carbon::parse($request->deadline)->endOfDay();
            $now = Carbon::now();

            if ($deadline->isPast() && $request->status !== 'closed') {
                return redirect()->back()->with('error', 'Deadline statusi close bo\'lmasa oldingi vaqtni tanlab bo\'lmaydi.');
            }

            $grade->deadline = $deadline;
        }

        $grade->status = $request->status;
        $grade->save();

        return redirect()->back()->with('success', 'Status muvaffaqiyatli yangilandi.');
    }

    public function studentGradesWeek(Request $request)
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $level_codes = collect(['11' => '1-kurs', '12' => '2-kurs', '13' => '3-kurs', '14' => '4-kurs', '15' => '5-kurs', '16' => '6-kurs']);
        $groups = collect();
        $semesters = collect();
        $subjects = collect();
        $students = collect();
        $weeks = collect();
        $dates = collect();
        $subject = null;
        $teacherName = "Yo'q";

        $viewType = $request->input('viewType', 'week');

        if ($request->filled('department')) {
            $department = Department::findOrFail($request->department);
            $groups = $department->groups;
        }
        if (isset($request->level_code)) {
            $level_code = $request->level_code;
            $group_ids = Student::where('level_code', $level_code)
                ->where('student_status_code', '11') // faqat "O'qimoqda" statusidagi talabalar
                ->pluck('group_id')
                ->unique()
                ->toArray();
            if ($groups->isNotEmpty() && count($groups)) {
                $groups = $groups->whereIn('group_hemis_id', $group_ids);
            }else{
                $groups = Group::whereIn('group_hemis_id', $group_ids)->get();
            }
        }

        if ($request->filled('group')) {
            $group = Group::findOrFail($request->group);
            if ($semesters->isEmpty() && count($semesters)) {
                $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)->get();
            }else{
                $semesters = $semesters->where('curriculum_hemis_id', $group->curriculum_hemis_id);
            }
        }

        if ($request->filled('semester') && $request->filled('group')) {
            $group = Group::findOrFail($request->group);
            $semester = Semester::findOrFail($request->semester);
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get();
        }

        if ($request->filled(['group', 'semester', 'subject'])) {
            $group = Group::findOrFail($request->group);
            $semester = Semester::findOrFail($request->semester);
            $subject = CurriculumSubject::findOrFail($request->subject);

            $teacher = StudentGrade::where('subject_id', $subject->subject_id)
                ->whereNotNull('employee_name')
                ->first();

            $teacherName = $teacher ? $teacher->employee_name : 'Noma\'lum o\'qituvchi';

            $students = Student::where('group_id', $group->group_hemis_id)->get();
            if ($request->filled('level_code')) {
                $students = $students->where('level_code', $request->level_code);
            }
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
                // dd($grades->where('student_hemis_id', 3989)->sortBy('lesson_date'));
                foreach ($grades as $grade) {
                    $studentId = $grade->student_hemis_id;
                    $lessonDate = $grade->lesson_date_tashkent;

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
                    $averageGradesForSubject[$student->hemis_id] = $this->studentGradeService->computeAverageGrade($studentGrades, $semester->code);

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
                $independents = Independent::where('group_hemis_id', $group->group_hemis_id)
                    ->where('semester_hemis_id', $semester->semester_hemis_id)
                    ->where('subject_hemis_id', $subject->curriculum_subject_hemis_id)
                    ->pluck('start_date')
                    ->map(function ($date) {
                        return Carbon::parse($date);
                    })->unique()->sort();
                // dd($independents);
                $count = Independent::where('group_hemis_id', $group->group_hemis_id)
                    ->where('semester_hemis_id', $semester->semester_hemis_id)
                    ->where('subject_hemis_id', $subject->curriculum_subject_hemis_id)
                    ->where('start_date', "<=", date("Y-m-d"))->count();
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 99)
                    ->where('semester_code', $semester->code)
                    ->select(DB::raw('avg(grade) as grade'), 'student_hemis_id', 'lesson_date')
                    ->groupBy('student_hemis_id', 'lesson_date')
                    ->get();

                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = $independents;
                $averageGradesForSubject = $this->studentGradeService->g_independent_averageGradesForSubject($averageGradesPerStudentPerPeriod, $count);

            } elseif ($traning_type == 'oraliq') {
                $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                    ->where('subject_id', $subject->subject_id)
                    ->where('training_type_code', 100)
                    ->where('semester_code', $semester->code)
                    ->get();
                $data = $this->studentGradeService->g_averageGradesPerStudentPerPeriod($grades);
                $averageGradesPerStudentPerPeriod = $data[0];
                $dates = [];
                $averageGradesForSubject = $this->studentGradeService->g_averageGradesForSubject($averageGradesPerStudentPerPeriod);
                $averageGradesPerStudentPerPeriod = [];
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
            // dump($dates);
            // dump($averageGradesPerStudentPerPeriod);
            // dd($averageGradesForSubject);
            return view('admin.students.week-grades', compact(
                'departments',
                'level_codes',
                'groups',
                'semesters',
                'subjects',
                'students',
                'weeks',
                'dates',
                'subject',
                'viewType',
                'teacherName',
                'averageGradesForSubject',
                'averageGradesPerStudentPerPeriod'
            ));
        }

        return view('admin.students.week-grades', compact(
            'departments',
            'level_codes',
            'groups',
            'semesters',
            'subjects',
            'students',
            'weeks',
            'dates',
            'subject',
            'viewType',
            'teacherName'
        ));
    }



    private function computeAverageGrade2($gradesList)
    {
        $gradesByDate = collect($gradesList)->groupBy(function ($grade) {
            return Carbon::parse($grade->lesson_date)->format('Y-m-d');
        });

        $totalDailyAverage = 0;
        $dayCount = 0;

        $nb = false;
        foreach ($gradesByDate as $date => $dailyGrades) {
            $dailyTotal = 0;
            $dailyCount = 0;
            $absentCount = 0;
            foreach ($dailyGrades as $grade) {
                //                if ($grade->status === 'retake' && $grade->reason === 'absent') {
//                    $dailyTotal += $grade->retake_grade ?? 0;
//                }

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

            $dailyAverage = $dailyCount > 0 ? $dailyTotal / $dailyCount : 11;
            $totalDailyAverage += $dailyAverage;
            $dayCount++;
            if ($absentCount == $dailyCount) {
                $nb = true;
            }
        }
        if ($dayCount == 1 and $nb) {
            return 'Nb';
        }
        return $dayCount > 0 ? round($totalDailyAverage / $dayCount, 2) : null;
    }


    public function getGroupsByDepartment(Request $request)
    {
        $department = Department::findOrFail($request->department_id);
        $groups = $department->groups->pluck('name', 'id');
        return response()->json($groups);
    }

    public function getGroupsByDepartment_hemis(Request $request)
    {
        $department = Department::where('department_hemis_id', $request->department_id)->first();
        $groups = $department->groups->pluck('name', 'group_hemis_id');
        return response()->json($groups);
    }
    //

    public function getSemestersNew(Request $request)
    {
        $group = Group::findOrFail($request->group_id);
        $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get(['id', 'name', 'current']);
        return response()->json($semesters);
    }
    public function getLevelCodes(Request $request)
    {
        $group_hemis_ids = Student::where('level_code', $request->level_code)
            ->where('student_status_code', '11') // faqat "O'qimoqda" statusidagi talabalar
            ->pluck('group_id')
            ->unique()
            ->toArray();
        $groups = Group::when($request->department_id, function ($query) use($request){
            $department = Department::findOrFail($request->department_id);
            $query->where('department_hemis_id', $department->department_hemis_id);
        })->whereIn('group_hemis_id', $group_hemis_ids)->pluck('name','id')->unique()->toArray();
        return response()->json([
            'groups'=>$groups,
        ]);
    }
    public function getSemestersNew_hemis(Request $request)
    {
        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get(['semester_hemis_id as id', 'name', 'current']);
        return response()->json($semesters);
    }

    public function getSubjectsNew(Request $request)
    {
        $group = Group::findOrFail($request->group_id);
        $semester = Semester::findOrFail($request->semester_id);

        $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->pluck('subject_name', 'id');

        return response()->json($subjects);
    }

    public function getSubjectsNew_hemis(Request $request)
    {
        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semester = Semester::where('semester_hemis_id', $request->semester_id)->first();

        $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->pluck('subject_name', 'curriculum_subject_hemis_id');

        return response()->json($subjects);
    }
    public function exportStudentGrades(Request $request)
    {
        ActivityLogService::log('export', 'student_grade', 'Talaba baholari eksport qilindi');
        $filters = $request->only(['department', 'level_code', 'group', 'semester', 'subject']);
        return Excel::download(new StudentGradesExportAdmin($filters), 'student_grades.xlsx');
    }

    //    public function exportStudentGrades(Request $request)
//    {
//        $filters = $request->only(['department', 'group', 'semester', 'subject']);
//        $export = new StudentGradeBox($filters);
//        return $export->export();
//    }

    public function exportStudentGradesBox(Request $request)
    {
        ActivityLogService::log('export', 'student_grade', 'Talaba baholari (box) eksport qilindi');
        $filters = $request->only(['department', 'level_code', 'group', 'semester', 'subject']);
        $export = new StudentGradeBox($filters);
        return $export->export();
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            Excel::import(new StudentGradeUpdateViaExcel(), $request->file('file'));
            ActivityLogService::log('import', 'student_grade', 'Talaba baholari Excel orqali import qilindi');
            return back()->with('success', 'Ma\'lumotlar muvaffaqiyatli yuklandi va qayta ishlandi.');
        } catch (\Exception $e) {
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }

    public function exportStudentGrades1(Request $request)
    {
        $departments = Department::all();
        $groups = collect();
        $semesters = collect();
        $subjects = collect();
        $students = collect();
        $weeks = collect();
        $dates = collect();
        $subject = null;
        $viewType = $request->input('viewType', 'week');

        if ($request->filled('department')) {
            $department = Department::findOrFail($request->department);
            $groups = $department->groups;
        }

        if ($request->filled('group')) {
            $group = Group::findOrFail($request->group);
            $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('current', true)
                ->get();
        }

        if ($request->filled('group') && $request->filled('semester')) {
            $group = Group::findOrFail($request->group);
            $semester = Semester::findOrFail($request->semester);
            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get();
        }

        if ($request->filled(['group', 'semester', 'subject'])) {
            $group = Group::findOrFail($request->group);
            $semester = Semester::findOrFail($request->semester);
            $subject = CurriculumSubject::findOrFail($request->subject);

            $students = Student::with([
                'studentGrades' => function ($query) use ($subject) {
                    $query->where('subject_id', $subject->subject_id);
                }
            ])->where('group_id', $group->group_hemis_id)->get();

            if ($viewType == 'week') {
                $weeks = CurriculumWeek::where('semester_hemis_id', $semester->semester_hemis_id)
                    ->orderBy('start_date')
                    ->get();
            } else {
                $lessonDates = StudentGrade::where('subject_id', $subject->subject_id)
                    ->whereIn('student_hemis_id', $students->pluck('hemis_id'))
                    ->distinct()
                    ->pluck('lesson_date')
                    ->map(function ($date) {
                        return Carbon::parse($date);
                    })->unique()->sort();

                $dates = $lessonDates;
            }
        }


        return Excel::download(
            new StudentGradesExport($students, $weeks, $dates, $viewType, $subject),
            'student_grades.xlsx'
        );
    }

    function getshakl(Request $request)
    {
        $shakllar =
            [
                [
                    'id' => 1,
                    "name" => "12-shakl"
                ],
                [
                    'id' => 2,
                    "name" => "12-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 3,
                    "name" => "12a-shakl"
                ],
                [
                    'id' => 4,
                    "name" => "12a-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 5,
                    "name" => "12b-shakl"
                ],
                [
                    'id' => 6,
                    "name" => "12b-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 7,
                    "name" => "12-shakl (yozgi)"
                ]
            ];
        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->where('subject_id', $request->subject_id)
            ->first();
        $exam = ExamTest::where(
            'group_id',
            $group->id,
        )->where(
                'subject_hemis_id',
                $subject->curriculum_subject_hemis_id,
            )->where(
                'semester_hemis_id',
                $semester->semester_hemis_id,
            )->orderBy('shakl', 'DESC')->first();
        if (empty($exam)) {
            return [
                [
                    'id' => 1,
                    "name" => "12-shakl"
                ],
            ];
        }
        if ($exam->shakl % 2 == 1) {
            return
                [
                    [
                        'id' => 2,
                        "name" => "12-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 4,
                        "name" => "12a-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 6,
                        "name" => "12b-shakl(qo‘shimcha)"
                    ],
                    $shakllar[($exam->shakl ?? 0) + 1]
                ];
        } else {
            return
                [
                    [
                        'id' => 2,
                        "name" => "12-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 4,
                        "name" => "12a-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 6,
                        "name" => "12b-shakl(qo‘shimcha)"
                    ],
                    $shakllar[($exam->shakl ?? 0)]
                ];
        }

    }

    function getshakl_oski(Request $request)
    {
        $shakllar =
            [
                [
                    'id' => 1,
                    "name" => "12-shakl"
                ],
                [
                    'id' => 2,
                    "name" => "12-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 3,
                    "name" => "12a-shakl"
                ],
                [
                    'id' => 4,
                    "name" => "12a-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 5,
                    "name" => "12b-shakl"
                ],
                [
                    'id' => 6,
                    "name" => "12b-shakl(qo‘shimcha)"
                ],
                [
                    'id' => 7,
                    "name" => "12-shakl (yozgi)"
                ]
            ];
        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->where('subject_id', $request->subject_id)
            ->first();
        $oski = Oski::where(
            'group_id',
            $group->id,
        )->where(
                'subject_hemis_id',
                $subject->curriculum_subject_hemis_id,
            )->where(
                'semester_hemis_id',
                $semester->semester_hemis_id,
            )->orderBy('shakl', 'DESC')->first();
        if (empty($oski)) {
            return [
                [
                    'id' => 1,
                    "name" => "12-shakl"
                ],
            ];
        }
        if ($oski->shakl % 2 == 1) {
            return
                [
                    [
                        'id' => 2,
                        "name" => "12-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 4,
                        "name" => "12a-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 6,
                        "name" => "12b-shakl(qo‘shimcha)"
                    ],
                    $shakllar[($oski->shakl ?? 0) + 1]
                ];
        } else {
            return
                [
                    [
                        'id' => 2,
                        "name" => "12-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 4,
                        "name" => "12a-shakl(qo‘shimcha)"
                    ],
                    [
                        'id' => 6,
                        "name" => "12b-shakl(qo‘shimcha)"
                    ],
                    $shakllar[($oski->shakl ?? 0)]
                ];
        }

    }

    function getStudentsShakl(Request $request)
    {

        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->where('subject_id', $request->subject_id)
            ->first();


        if ($request->shakl == 1) {
            return [
            ];
        }
        if ($request->shakl == 2 or $request->shakl == 4 or $request->shakl == 6) {
            $exam = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', $request->shakl - 1)
                ->orderBy('shakl', 'DESC')->first();
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('semester_code', $semester->code)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();

            $students = Student::selectRaw('
                students.full_name as full_name,
                students.student_id_number as student_id,
                        ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                         students.hemis_id as hemis_id

                    ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->whereIn('students.hemis_id', json_decode($exam->sababli_studets ?? "[]"))
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'test' => $student->test,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;
        }
        if ($request->shakl == 3) {
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->where('semester_code', $semester->code)
                ->distinct('lesson_date')
                ->count();
            $exam1 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 1)->first();
            $exam2 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 2)
                ->first();
            $students = Student::selectRaw('
            students.full_name as full_name,
            students.student_id_number as student_id,
                    ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                      ROUND (
                       ( SELECT max(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.semester_code = ' . $semester->code . '
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.test_id in (' . ($exam1->id ?? "-1") . ', ' . ($exam2->id ?? "-1") . ')
                        GROUP BY student_grades.student_hemis_id)
                    ) as test,
                     students.hemis_id as hemis_id

                ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->test < 60 and $student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'test' => $student->test,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;

        }

        if ($request->shakl == 5) {
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->where('semester_code', $semester->code)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();
            $exam1 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 1)->first();
            $exam2 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 2)
                ->first();
            $exam3 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 3)
                ->first();
            $exam4 = ExamTest::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 4)
                ->first();
            $students = Student::selectRaw('
            students.full_name as full_name,
            students.student_id_number as student_id,
            ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                      ROUND (
                       ( SELECT max(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.test_id in (' . ($exam1->id ?? "-1") . ', ' . ($exam2->id ?? "-1") . ', ' . ($exam3->id ?? "-1") . ', ' . ($exam4->id ?? "-1") . ')
                        GROUP BY student_grades.student_hemis_id)
                    ) as test,
                     students.hemis_id as hemis_id

                ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->test < 60 and $student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'test' => $student->test,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;

        }
    }
    function getStudentsShaklOski(Request $request)
    {

        $group = Group::where('group_hemis_id', $request->group_id)->first();
        $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semester->code)
            ->where('subject_id', $request->subject_id)
            ->first();


        if ($request->shakl == 1) {
            return [
            ];
        }
        if ($request->shakl == 2 or $request->shakl == 4 or $request->shakl == 6) {
            $oski = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', $request->shakl - 1)
                ->orderBy('shakl', 'DESC')->first();
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('semester_code', $semester->code)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();

            $students = Student::selectRaw('
                students.full_name as full_name,
                students.student_id_number as student_id,
                        ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                         students.hemis_id as hemis_id

                    ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->whereIn('students.hemis_id', json_decode($oski->sababli_studets ?? "[]"))
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'oski' => $student->oski,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;
        }
        if ($request->shakl == 3) {
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->where('semester_code', $semester->code)
                ->distinct('lesson_date')
                ->count();
            $oski1 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 1)->first();
            $oski2 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 2)
                ->first();
            $students = Student::selectRaw('
            students.full_name as full_name,
            students.student_id_number as student_id,
                    ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                      ROUND (
                       ( SELECT max(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.semester_code = ' . $semester->code . '
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.oski_id in (' . ($oski1->id ?? "-1") . ', ' . ($oski2->id ?? "-1") . ')
                        GROUP BY student_grades.student_hemis_id)
                    ) as oski,
                     students.hemis_id as hemis_id

                ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->oski < 60 and $student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'oski' => $student->oski,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;

        }

        if ($request->shakl == 5) {
            $currentDate = now();
            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->where('semester_code', $semester->code)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();
            $oski1 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 1)->first();
            $oski2 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 2)
                ->first();
            $oski3 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 3)
                ->first();
            $oski4 = Oski::where(
                'group_id',
                $group->id,
            )->where(
                    'subject_hemis_id',
                    $subject->curriculum_subject_hemis_id,
                )->where(
                    'semester_hemis_id',
                    $semester->semester_hemis_id,
                )->where('shakl', 4)
                ->first();
            $students = Student::selectRaw('
            students.full_name as full_name,
            students.student_id_number as student_id,
            ROUND (
                            (SELECT sum(inner_table.average_grade)/ ' . $count . '
                            FROM (
                                SELECT lesson_date,AVG(COALESCE(
                                CASE
                                    WHEN student_grades.retake_grade IS NOT NULL AND student_grades.retake_grade > 0
                                    THEN student_grades.retake_grade
                                    ELSE student_grades.grade
                                END, 0)) AS average_grade
                                FROM student_grades
                                WHERE student_grades.student_hemis_id = students.hemis_id
                                AND student_grades.subject_id = ' . $subject->subject_id . '
                                AND student_grades.semester_code = ' . $semester->code . '
                                AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                                GROUP BY student_grades.lesson_date
                            ) AS inner_table) + 0.01
                        ) as jn,
                        ROUND(AVG(CASE
                            WHEN student_grades.training_type_code = 99
                            THEN student_grades.grade
                            ELSE NULL
                        END)) as mt,
                      ROUND (
                       ( SELECT max(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.oski_id in (' . ($oski1->id ?? "-1") . ', ' . ($oski2->id ?? "-1") . ', ' . ($oski3->id ?? "-1") . ', ' . ($oski4->id ?? "-1") . ')
                        GROUP BY student_grades.student_hemis_id)
                    ) as oski,
                     students.hemis_id as hemis_id

                ')
                ->leftJoin('student_grades', function ($join) use ($subject, $semester) {
                    $join->on('student_grades.student_hemis_id', '=', 'students.hemis_id')
                        ->where('student_grades.subject_id', '=', $subject->subject_id)->where('student_grades.semester_code', $semester->code);
                })
                ->where('student_grades.semester_code', $semester->code)
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->get();

            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $students_shakl = [];
            foreach ($students as $student) {
                $qoldirgan = $this->getAbsentOffSum($group->group_hemis_id, $subject->subject_id, $student->hemis_id);
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                if ($student->oski < 60 and $student->jn >= $deadline->joriy and $student->mt >= $deadline->mustaqil_talim and $student->qoldiq <= 25) {
                    $students_shakl[] = [
                        'full_name' => $student->full_name,
                        'hemis_id' => $student->hemis_id,
                        'jn' => $student->jn,
                        'mt' => $student->mt,
                        'oski' => $student->oski,
                        'qoldiq' => $student->qoldiq,
                    ];
                }

            }
            return $students_shakl;

        }
    }

}

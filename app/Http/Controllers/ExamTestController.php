<?php

namespace App\Http\Controllers;
use App\Imports\ExamTestImport;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\ExamHistory;
use App\Models\ExamTestStudent;
use App\Models\Group;
use App\Models\ExamTest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExamTestController extends Controller
{
    public function index(Request $request)
    {
        $query = new ExamTest();
        if ($request->department) {
            $query = $query->where('department_id', $request->department);
        }
        if ($request->full_name) {
            $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        }
        if ($request->group) {
            $query = $query->where('group_id', $request->group);
        }
        if ($request->semester) {
            $query = $query->where('semester_id', $request->semester);
        }
        if ($request->subject) {
            $query = $query->where('subject_id', $request->subject);
        }
        if ($request->status == null) {
            $request->status = 2;
        }
        if ($request->status != 2) {
            $query = $query->where('status', $request->status);
        }
        if (!empty($request->start_date)) {
            $query = $query->where('start_date', date("Y-m-d", strtotime($request->start_date)));
        }

        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $examtests = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.exam_test.index', compact('examtests', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        // dd($teacher);

        $query = ExamTest::where('department_hemis_id', $teacher->department_hemis_id);
        // if ($request->department) {
        //     $query = $query->where('department_id', $request->department);
        // }
        if ($request->full_name) {
            $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        }
        if ($request->group) {
            $query = $query->where('group_id', $request->group);
        }
        if ($request->semester) {
            $query = $query->where('semester_id', $request->semester);
        }
        if ($request->subject) {
            $query = $query->where('subject_id', $request->subject);
        }
        if ($request->status == null) {
            $request->status = 2;
        }
        if ($request->status != 2) {
            $query = $query->where('status', $request->status);
        }
        if ($request->start_date) {
            $query = $query->where('start_date', date("Y-m-d", strtotime($request->start_date)));
        }
        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $examtests = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        if ($teacher->hasRole('dekan')) {
            $groups = Group::where('department_hemis_id', $teacher->department_hemis_id)->get();
            // $teachingGroups = $teacher->groups;
            // $groups = $departmentGroups->merge($teachingGroups)->unique('id');
        } else {
            $groups = $teacher->groups;
            if (count($groups) < 1) {
                $group_ids = StudentGrade::where('employee_id', $teacher->hemis_id)
                    ->join('students', 'student_grades.student_hemis_id', '=', 'students.hemis_id')
                    ->groupBy('students.group_id')->pluck('students.group_id');

                $groups = Group::whereIn('group_hemis_id', $group_ids)->get();
            }
        }
        return view('teacher.exam_test.index', compact('examtests', 'groups'));
    }
    public function create()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);
        return view('admin.exam_test.create', compact('departments', 'lastFilters'));
    }
    public function create_teacher()
    {
        $user = Auth::user();
        if (!$user->hasRole(['dekan'])) {
            return back();
        }
        $teacher = Auth::guard('teacher')->user();

        $groups = Group::where('department_hemis_id', $teacher->department_hemis_id)
            ->select('group_hemis_id as id', 'name')
            ->get();
        $lastFilters = session('last_lesson_filters', []);
        $departments = Department::where('department_hemis_id', $teacher->department_hemis_id)->get();
        return view('teacher.exam_test.create', compact('departments', 'groups', 'lastFilters'));
    }
    public function store(Request $request)
    {
        // dd($request->all());

        try {
            DB::beginTransaction();
            session([
                'exam_test' => [
                    'department_id' => $request->department_id,
                    'group_id' => $request->group_id,
                    'semester_code' => $request->semester_code,
                    'subject_id' => $request->subject_id,
                    'lesson_date' => $request->lesson_date,
                    'shakl' => $request->shakl
                ]
            ]);


            $deportment = Department::where('department_hemis_id', $request->department_id ?? 0)->first();
            if (empty($deportment)) {
                DB::rollBack();
                return back()->with('error', 'Departament tanlang');
            }
            $group = Group::where('group_hemis_id', $request->group_id ?? "")->first();
            if (empty($group)) {
                DB::rollBack();
                return back()->with('error', 'Guruh tanlang');
            }
            $semester = Semester::where('code', $request->semester_code ?? "")->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
            if (empty($semester)) {
                DB::rollBack();
                return back()->with('error', 'Semester tanlang');
            }
            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->where('subject_id', $request->subject_id)
                ->first();
            if (empty($subject)) {
                DB::rollBack();
                return back()->with('error', 'Fan tanlang');
            }
            $start_date = $request->lesson_date;
            if (empty($start_date)) {
                DB::rollBack();
                return back()->with('error', 'Sanani kiriting');
            }
            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $shakl = $request->shakl;
            $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($deadline->deadline_days ?? 1) . ' days')); // 5 kun qo'shish
            $filePath = null;
            $fileOriginalName = null;
            if ($request->hasFile('lesson_file')) {
                $file = $request->file('lesson_file');
                $filePath = $file->store('lesson-files', 'public');
                $fileOriginalName = $file->getClientOriginalName();
            }
            $count = ExamTest::where('department_hemis_id', $deportment->department_hemis_id)
                ->where('group_id', $group->id)
                ->where('semester_code', $semester->code)
                ->where('shakl', $shakl)
                ->where('subject_id', $subject->id)
                ->count();
            if ($count > 0) {
                DB::rollBack();
                return back()->with('error', 'Bu guruhga test yaratib bo\'lingan');
            }
            $student_ids = null;
            if ($shakl % 2 == 1) {
                $shakl_ids =
                    [$shakl - 1, $shakl - 2];
            } else {
                $shakl_ids =
                    [$shakl - 1];
            }
            if ($shakl > 1) {
                $count = ExamTest::where('department_hemis_id', $deportment->department_hemis_id)
                    ->where('group_id', $group->id)
                    ->whereIn('shakl', $shakl_ids)
                    ->where('semester_code', $semester->code)
                    ->where('subject_id', $subject->id)
                    ->count();
                if ($count < 1) {
                    DB::rollBack();
                    return back()->with('success', 'Undan oldingi shaklni yarating');
                }
                if (empty($request->students)) {
                    DB::rollBack();
                    return back()->with('success', 'Student tanlang');
                }
                $student_ids = json_encode($request->students);
            }

            ExamTest::create([
                'user_id' => $request->user()->id,
                'department_hemis_id' => $deportment->department_hemis_id,
                'department_id' => $deportment->id,
                'deportment_name' => $deportment->name,
                'group_hemis_id' => $group->group_hemis_id,
                'group_id' => $group->id,
                'group_name' => $group->name,
                'semester_hemis_id' => $semester->semester_hemis_id,
                'semester_id' => $semester->id,
                'semester_name' => $semester->name,
                'semester_code' => $subject->semester_code,
                'subject_hemis_id' => $subject->curriculum_subject_hemis_id,
                'subject_id' => $subject->id,
                'subject_name' => $subject->subject_name,
                'start_date' => $start_date,
                'deadline' => $end_date,
                'file_path' => $filePath,
                'shakl' => $shakl,
                'file_original_name' => $fileOriginalName,
                'student_ids' => $student_ids
            ]);

            DB::commit();
            return back()->with('success', 'Test muvaffaqiyatli yaratildi');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->with('error', 'Ma\'lumot topilmadi');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage() . " " . $e->getLine());
        }
    }
    function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $exam_test = ExamTest::findOrFail($id);
            if (auth()->user()->hasRole('admin')) {
                StudentGrade::where('test_id', $exam_test->id)->delete();
            } else {
                $count = StudentGrade::where('test_id', $exam_test->id)->count();
                if ($count > 0) {
                    return back()->with('error', "Baholanib bo'lgan o'chirishga ruxsat yo'q");
                }
            }
            StudentGrade::where('test_id', $exam_test->id)->delete();

            if ($exam_test->file_path) {
                Storage::disk('public')->delete($exam_test->file_path);
            }

            $exam_test->delete();

            DB::commit();
            return back()->with('success', 'Test muvaffaqiyatli o\'chirildi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
    function grade($id)
    {
        $examtest = ExamTest::find($id);
        if (empty($examtest)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($examtest->shakl == 1) {
            if ($examtest->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->leftJoin('exam_test_students', function ($join) use ($examtest) {
                        $join->on('students.hemis_id', '=', 'exam_test_students.student_hemis_id')
                            ->where('exam_test_students.exam_test_id', '=', $examtest->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(exam_test_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(exam_test_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(exam_test_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        } else {
            if ($examtest->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->whereIn('students.hemis_id', json_decode($examtest->student_ids))
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->leftJoin('exam_test_students', function ($join) use ($examtest) {
                        $join->on('students.hemis_id', '=', 'exam_test_students.student_hemis_id')
                            ->where('exam_test_students.exam_test_id', '=', $examtest->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(exam_test_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(exam_test_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(exam_test_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->whereIn('students.hemis_id', json_decode($examtest->student_ids))
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        }
        return view('admin.exam_test.grade', compact('examtest', 'students'));

    }
    function grade_form($id)
    {
        $examtest = ExamTest::find($id);
        if (empty($examtest)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($examtest->shakl == 1) {
            if ($examtest->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->leftJoin('exam_test_students', function ($join) use ($examtest) {
                        $join->on('students.hemis_id', '=', 'exam_test_students.student_hemis_id')
                            ->where('exam_test_students.exam_test_id', '=', $examtest->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(exam_test_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(exam_test_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(exam_test_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->orderBy('students.hemis_id')
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->get();
            }
        } else {
            if ($examtest->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->whereIn('students.hemis_id', json_decode($examtest->student_ids))
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($examtest) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.test_id', '=', $examtest->id);
                })
                    ->leftJoin('exam_tests', 'exam_tests.id', '=', 'student_grades.test_id')
                    ->leftJoin('exam_test_students', function ($join) use ($examtest) {
                        $join->on('students.hemis_id', '=', 'exam_test_students.student_hemis_id')
                            ->where('exam_test_students.exam_test_id', '=', $examtest->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(exam_test_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(exam_test_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(exam_test_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $examtest->group_hemis_id)
                    ->whereIn('students.hemis_id', json_decode($examtest->student_ids))
                    ->get();
            }
        }
        return view('admin.exam_test.grade_form', compact('examtest', 'students'));

    }
    function grade_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $exam_test = ExamTest::find(intval($request->examtest));
            $exam_test->status = 1;
            $exam_test->grade_teacher = $request->user()->short_name ?? $request->user()->name;
            $exam_test->save();
            foreach ($request->baho as $key => $baho) {
                if (isset($baho)) {
                    $student = Student::find($key);
                    StudentGrade::updateOrCreate([
                        'student_id' => $student->id,
                        'student_hemis_id' => $student->hemis_id,
                        'test_id' => $exam_test->id
                    ], [
                        'hemis_id' => 888888888,
                        'semester_code' => $exam_test->semester->code,
                        'semester_name' => $exam_test->semester->name,
                        'subject_schedule_id' => 0,
                        'subject_id' => $exam_test->subject->subject_id,
                        'subject_name' => $exam_test->subject->subject_name,
                        'subject_code' => $exam_test->subject->subject_code,
                        'training_type_code' => 102,
                        'training_type_name' => "Yakuniy test",
                        'employee_id' => "0",
                        'employee_name' => auth()->user()->name,
                        'lesson_pair_name' => "",
                        'lesson_pair_code' => "",
                        'lesson_pair_start_time' => "",
                        'lesson_pair_end_time' => "",
                        'lesson_date' => $exam_test->start_date,
                        'created_at_api' => $exam_test->created_at,
                        'reason' => 'teacher_victim',
                        'status' => 'recorded',
                        'grade' => $baho,
                        'deadline' => now(),
                    ]);
                } else {
                    $student = Student::find($key);
                    StudentGrade::where(
                        'student_id',
                        $student->id
                    )->where(
                            'student_hemis_id',
                            $student->hemis_id
                        )
                        ->where('test_id', $exam_test->id)->delete();
                }
            }
            DB::commit();
            if (auth()->user()->hasRole('admin')) {
                return redirect()->route('admin.examtest.grade', $exam_test->id)->with('success', 'Test baholandi');
            }
            return back()->with('success', 'Test baholandi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
            return back()->with('error', 'Test Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
        }

    }
    function sababli_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $exam_test = ExamTest::find(intval($request->examtest));
            if (!empty($request->sababli)) {
                $exam_test->sababli_studets = json_encode($request->sababli ?? []);
            }
            foreach ($request->sababli ?? [] as $sababli) {
                $file = $request->sababli_file[$sababli] ?? null;
                $check = $request->check[$sababli] ?? 0;
                if ($check) {
                    continue;
                }
                if ($file) {
                    $filePath = $file->store('test-file', 'public');
                    $fileOriginalName = $file->getClientOriginalName();
                } else {
                    DB::rollBack();
                    return back()->with('error', 'Sababli belgilanganlarga fayl yuklang');
                }
                ExamTestStudent::create([
                    'student_hemis_id' => $sababli,
                    'exam_test_id' => $request->examtest,
                    'file_path' => $filePath,
                    'file_original_name' => $fileOriginalName
                ]);
            }
            $exam_test->save();
            DB::commit();
            return back()->with('success', 'Test baholandi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Test Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
            return back()->with('error', 'Test Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
        }
    }

    function delete_file($id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin'])) {
            return back()->with('error', 'Ruxsat yo\'q');
        }
        $exam_file = ExamTestStudent::where('id', operator: $id)->first();
        if (empty($exam_file)) {
            return back()->with('error', 'Mavjuda emas');
        }
        $exam = ExamTest::where('id', $exam_file->exam_test_id)->first();
        if (empty($exam)) {
            return back()->with('error', 'Test o\'chirilgan emas');
        }
        if (Storage::disk('public')->exists($exam_file->file_path)) {
            Storage::disk('public')->delete($exam_file->file_path);
        }
        $sababli_studets = json_decode($exam->sababli_studets);
        $sababli_studets = array_filter($sababli_studets, function ($value) use ($exam_file) {
            return $value != $exam_file->student_hemis_id;
        });
        $exam->sababli_studets = json_encode($sababli_studets);
        $exam->save();
        $exam_file->delete();

        return back()->with('success', 'O\'chirildi');

    }
    function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
        ]);
        $file = $request->file('file');
        $filePath = $file->store('export-files', 'public');
        $fileOriginalName = $file->getClientOriginalName();
        try {
            $import = new ExamTestImport();
            Excel::import($import, $file);
            $error = $import->error;
            ExamHistory::create([
                'file_original_name' => $fileOriginalName,
                'file_path' => $filePath,
                'errors' => json_encode($error),
                'user_id' => auth()->id(),
            ]);
            return back()->with([
                'success' => 'Ma\'lumotlar muvaffaqiyatli yuklandi va qayta ishlandi.',
                'test_error' => $error,
            ]);
        } catch (\Exception $e) {
            ExamHistory::create([
                'file_original_name' => $fileOriginalName,
                'file_path' => $filePath,
                'errors' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }

    }
}
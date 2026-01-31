<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Imports\OskiImport;
use App\Models\Oski;
use App\Models\OskiHistory;
use App\Models\OskiStudent;
use Illuminate\Http\Request;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\OraliqNazorat;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;
class OskiController extends Controller
{
    public function index(Request $request)
    {
        $query = new Oski();
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
        if ($request->start_date) {
            $query = $query->where('start_date', date("Y-m-d", strtotime($request->start_date)));
        }
        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $oskis = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.oski.index', compact('oskis', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        // dd($teacher);

        $query = Oski::where('department_hemis_id', $teacher->department_hemis_id);
        // if ($request->department) {
        //     $query = $query->where('department_id', $request->department);
        // }
        // if ($request->full_name) {
        //     $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        // }
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
        $oskis = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
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
        return view('teacher.oski.index', compact('oskis', 'groups'));
    }
    public function create()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.oski.create', compact('departments', 'lastFilters'));
    }
    public function store(Request $request)
    {
        // dd($request->all());

        try {
            DB::beginTransaction();
            session([
                'oski_add_filter' => [
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
            $count = Oski::where('department_hemis_id', $deportment->department_hemis_id)
                ->where('group_id', $group->id)
                ->where('semester_code', $semester->code)
                ->where('shakl', $shakl)
                ->where('subject_id', $subject->id)
                ->count();
            if ($count > 0) {
                DB::rollBack();
                return back()->with('error', 'Bu guruhga oski yaratib bo\'lingan');
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
                $count = Oski::where('department_hemis_id', $deportment->department_hemis_id)
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

            Oski::create([
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
            return back()->with('success', 'Oski muvaffaqiyatli yaratildi');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->with('error', 'Ma\'lumot topilmadi');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage() . " " . $e->getLine());
        }
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
        return view('teacher.oski.create', compact('departments', 'groups', 'lastFilters'));
    }
    function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $oski = Oski::findOrFail($id);
            if (auth()->user()->hasRole('admin')) {
                StudentGrade::where('oski_id', $oski->id)->delete();
            } else {
                $count = StudentGrade::where('oski_id', $oski->id)->count();
                if ($count > 0) {
                    return back()->with('error', "Baholanib bo'lgan o'chirishga ruxsat yo'q");
                }
            }
            StudentGrade::where('oski_id', $oski->id)->delete();

            if ($oski->file_path) {
                Storage::disk('public')->delete($oski->file_path);
            }

            $oski->delete();

            DB::commit();
            return back()->with('success', 'Oski muvaffaqiyatli o\'chirildi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Oski o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
    function grade_teacher($id, Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $oski = Oski::where('id', $id)->where('department_hemis_id', $teacher->department_hemis_id)->first();
        if (empty($oski)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($oski->shakl == 1) {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        } else {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        }
        return view('teacher.oski.grade', compact('oski', 'students'));

    }
    function grade($id)
    {
        $oski = Oski::find($id);
        if (empty($oski)) {
            return back()->with('error', 'Bunday Oski topilmadi');
        }
        if ($oski->shakl == 1) {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        } else {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->orderBy('students.hemis_id')
                    ->get();
            }
        }
        return view('admin.oski.grade', compact('oski', 'students'));

    }
    function grade_form($id)
    {
        $oski = Oski::find($id);
        if (empty($oski)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($oski->shakl == 1) {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->orderBy('students.hemis_id')
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oskis', 'oskis.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->orderBy('students.hemis_id')
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->get();
            }
        } else {
            if ($oski->status == 0) {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oski', 'oski.id', '=', 'student_grades.oski_id')
                    ->select(
                        'students.id',
                        'students.hemis_id',
                        'students.full_name',
                        DB::raw('COALESCE(student_grades.grade, "") as grade') // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->get();
            } else {
                $students = Student::leftJoin('student_grades', function ($join) use ($oski) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oski_id', '=', $oski->id);
                })
                    ->leftJoin('oski', 'oski.id', '=', 'student_grades.oski_id')
                    ->leftJoin('oski_students', function ($join) use ($oski) {
                        $join->on('students.hemis_id', '=', 'oski_students.student_hemis_id')
                            ->where('oski_students.oski_id', '=', $oski->id);
                    })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                        DB::raw('COALESCE(oski_students.file_path, "") as file_path'), // Fayl yo'li
                        DB::raw('COALESCE(oski_students.id, "") as exam_file_id'), // Fayl id
                        DB::raw('COALESCE(oski_students.file_original_name, "") as file_original_name') // Original fayl nomi
                    )
                    ->where('students.group_id', $oski->group_hemis_id)
                    ->whereIn('students.hemis_id', json_decode($oski->student_ids))
                    ->get();
            }
        }
        return view('admin.oski.grade_form', compact('oski', 'students'));

    }
    function grade_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $oski = Oski::find(intval($request->oski));
            $oski->status = 1;
            $oski->grade_teacher = $request->user()->short_name ?? $request->user()->name;
            $oski->save();
            foreach ($request->baho as $key => $baho) {
                if (isset($baho)) {
                    $student = Student::find($key);
                    StudentGrade::updateOrCreate([
                        'student_id' => $student->id,
                        'student_hemis_id' => $student->hemis_id,
                        'oski_id' => $oski->id
                    ], [
                        'hemis_id' => 888888888,
                        'semester_code' => $oski->semester->code,
                        'semester_name' => $oski->semester->name,
                        'subject_schedule_id' => 0,
                        'subject_id' => $oski->subject->subject_id,
                        'subject_name' => $oski->subject->subject_name,
                        'subject_code' => $oski->subject->subject_code,
                        'training_type_code' => 101,
                        'training_type_name' => "Oski",
                        'employee_id' => "0",
                        'employee_name' => $request->user()->short_name ?? $request->user()->name,
                        'lesson_pair_name' => "",
                        'lesson_pair_code' => "",
                        'lesson_pair_start_time' => "",
                        'lesson_pair_end_time' => "",
                        'lesson_date' => $oski->start_date,
                        'created_at_api' => $oski->created_at,
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
                        ->where('oski_id', $oski->id)->delete();
                }
            }
            DB::commit();
            if (auth()->user()->hasRole('admin')) {
                return redirect()->route('admin.oski.grade', $oski->id)->with('success', 'Oski baholandi');
            }
            return back()->with('success', 'OSKI baholandi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OSKI Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
            return back()->with('error', 'OSKI Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
        }

    }
    function sababli_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $oski = Oski::find(intval($request->oski));
            if (!empty($request->sababli)) {
                $oski->sababli_studets = json_encode($request->sababli ?? []);
            }
            foreach ($request->sababli ?? [] as $sababli) {
                $file = $request->sababli_file[$sababli] ?? null;
                $check = $request->check[$sababli] ?? 0;
                if ($check) {
                    continue;
                }
                if ($file) {
                    $filePath = $file->store('oski-file', 'public');
                    $fileOriginalName = $file->getClientOriginalName();
                } else {
                    DB::rollBack();
                    return back()->with('error', 'Sababli belgilanganlarga fayl yuklang');
                }
                OskiStudent::create([
                    'student_hemis_id' => $sababli,
                    'oski_id' => $request->oski,
                    'file_path' => $filePath,
                    'file_original_name' => $fileOriginalName
                ]);
            }
            $oski->save();
            DB::commit();
            return back()->with('success', 'Oski baholandi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Oski Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
            return back()->with('error', 'Oski Baholashda xatolik: ' . $e->getMessage() . " " . $e->getLine());
        }
    }

    function delete_file($id)
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin'])) {
            return back()->with('error', 'Ruxsat yo\'q');
        }
        $oski_file = OskiStudent::where('id', $id)->first();
        if (empty($oski_file)) {
            return back()->with('error', 'Mavjuda emas');
        }
        $oski = Oski::where('id', $oski_file->oski_id)->first();
        if (empty($oski)) {
            return back()->with('error', 'Test o\'chirilgan emas');
        }
        if (Storage::disk('public')->exists($oski_file->file_path)) {
            Storage::disk('public')->delete($oski_file->file_path);
        }
        $sababli_studets = json_decode($oski->sababli_studets);
        $sababli_studets = array_filter($sababli_studets, function ($value) use ($oski_file) {
            return $value != $oski_file->student_hemis_id;
        });
        $oski->sababli_studets = json_encode($sababli_studets);
        $oski->save();
        $oski_file->delete();

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
            $import = new OskiImport();
            Excel::import($import, $file);
            $error = $import->error;
            OskiHistory::create([
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
            OskiHistory::create([
                'file_original_name' => $fileOriginalName,
                'file_path' => $filePath,
                'errors' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }

    }
}
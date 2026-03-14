<?php


namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Log;
class OraliqNazoratController extends Controller
{
    public function index(Request $request)
    {
        $query = new OraliqNazorat();
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
        $oraliqnazorats = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.oraliqnazorat.index', compact('oraliqnazorats', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        // dd($teacher);
        $user = Auth::user();
        if ($user->hasRole(['dekan'])) {
            $query = OraliqNazorat::whereIn('department_hemis_id', $teacher->dean_faculty_ids);
        } else {
            $query = OraliqNazorat::where('teacher_hemis_id', $teacher->hemis_id);
        }
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
        $oraliqnazorats = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
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
        return view('teacher.oraliqnazorat.index', compact('oraliqnazorats', 'groups'));
    }
    public function create()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.oraliqnazorat.create', compact('departments', 'lastFilters'));
    }
    public function create_teacher()
    {
        $user = Auth::user();
        if (!$user->hasRole(['dekan'])) {
            return back();
        }
        $teacher = Auth::guard('teacher')->user();

        $groups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids)
            ->select('group_hemis_id as id', 'name')
            ->get();
        $lastFilters = session('last_lesson_filters', []);
        $departments = Department::whereIn('department_hemis_id', $teacher->dean_faculty_ids)->get();
        return view('teacher.oraliqnazorat.create', compact('departments', 'groups', 'lastFilters'));

    }
    public function store(Request $request)
    {


        try {
            DB::beginTransaction();
            $deportment = Department::where('department_hemis_id', $request->department_id)->first();
            $group = Group::where('group_hemis_id', $request->group_id)->first();
            $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->where('subject_id', $request->subject_id)
                ->first();
            $teacher = Teacher::where('hemis_id', $request->teacher_id)->first();
            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            $start_date = $request->lesson_date;
            $end_date = date('Y-m-d', strtotime($start_date . ' +' . ($deadline->deadline_days ?? 1) . ' days')); // 5 kun qo'shish
            $filePath = null;
            $fileOriginalName = null;
            if ($request->hasFile('lesson_file')) {
                $file = $request->file('lesson_file');
                $filePath = $file->store('lesson-files', 'public');
                $fileOriginalName = $file->getClientOriginalName();
            }
            session([
                'oraliq_nazorat_filter' => [
                    'department_id' => $request->department_id,
                    'group_id' => $request->group_id,
                    'semester_code' => $request->semester_code,
                    'subject_id' => $request->subject_id,
                    'lesson_date' => $request->lesson_date,
                ]
            ]);
            $count = OraliqNazorat::where('department_hemis_id', $deportment->department_hemis_id)
                ->where('group_id', $group->id)
                ->where('semester_code', $semester->code)
                ->where('subject_id', $subject->id)
                ->count();
            if ($count > 0) {
                DB::commit();
                return back()->with('error', "Bu guruhga oraliq nazorati yaratib bo'lingan");
            }

            OraliqNazorat::create([
                'user_id' => $request->user()->id,
                'teacher_hemis_id' => $teacher->hemis_id,
                'teacher_id' => $teacher->id,
                'teacher_short_name' => $teacher->short_name,
                'teacher_name' => $teacher->full_name,
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
                'file_original_name' => $fileOriginalName,
            ]);
            DB::commit();
            return back()->with('success', 'Oraliq nazorat muvaffaqiyatli yaratildi');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->with('error', 'Ma\'lumot topilmadi');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
    function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $oraliqnazorat = OraliqNazorat::findOrFail($id);

            if (auth()->user()->hasRole('admin')) {
                StudentGrade::where('oraliq_nazorat_id', $oraliqnazorat->id)->delete();
            } else {
                $count = StudentGrade::where('oraliq_nazorat_id', $oraliqnazorat->id)->count();
                if ($count > 0) {
                    return back()->with('error', "Baholanib bo'lgan o'chirishga ruxsat yo'q");
                }
            }
            if ($oraliqnazorat->file_path) {
                Storage::disk('public')->delete($oraliqnazorat->file_path);
            }
            $oraliqnazorat->delete();
            DB::commit();
            return back()->with('success', 'Oraliq nazorat muvaffaqiyatli o\'chirildi');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Oraliq nazorat o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
    function grade($id)
    {
        $oraliqnazorat = OraliqNazorat::find($id);
        if (empty($oraliqnazorat)) {
            return back()->with('error', 'Bunday Oraliq nazorat topilmadi');
        }
        if ($oraliqnazorat->status == 0) {
            $students =
                $students = Student::leftJoin('student_grades', function ($join) use ($oraliqnazorat) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oraliq_nazorat_id', '=', $oraliqnazorat->id);
                })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                    ->get();
        } else {
            $students = Student::leftJoin('student_grades', function ($join) use ($oraliqnazorat) {
                $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                    ->where('student_grades.oraliq_nazorat_id', '=', $oraliqnazorat->id);
            })
                ->select(
                    'students.id',
                    'students.full_name',
                    'students.hemis_id',
                    DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                )
                ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                ->get();
        }
        return view('admin.oraliqnazorat.grade', compact('oraliqnazorat', 'students'));

    }
    function grade_form($id)
    {
        $oraliqnazorat = OraliqNazorat::find($id);
        if (empty($oraliqnazorat)) {
            return back()->with('error', 'Bunday Oraliq nazorat topilmadi');
        }
        if ($oraliqnazorat->status == 0) {
            $students =
                $students = Student::leftJoin('student_grades', function ($join) use ($oraliqnazorat) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.oraliq_nazorat_id', '=', $oraliqnazorat->id);
                })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                    ->get();
        } else {
            $students = Student::leftJoin('student_grades', function ($join) use ($oraliqnazorat) {
                $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                    ->where('student_grades.oraliq_nazorat_id', '=', $oraliqnazorat->id);
            })
                ->select(
                    'students.id',
                    'students.full_name',
                    'students.hemis_id',
                    DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                )
                ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                ->get();
        }
        return view('admin.oraliqnazorat.grade_form', compact('oraliqnazorat', 'students'));

    }
    function grade_teacher($id, Request $request)
    {
        $oraliqnazorat = OraliqNazorat::where('id', $id)->where('teacher_id', $request->user()->id)->first();
        if (empty($oraliqnazorat)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($oraliqnazorat->status == 0) {
            $students =
                Student::select('id', 'full_name')->where('students.group_id', $oraliqnazorat->group_hemis_id)->get();
        } else {
            $students = Student::leftJoin('student_grades', 'students.hemis_id', '=', 'student_grades.student_hemis_id')
                ->leftJoin('oraliq_nazorats', 'oraliq_nazorats.id', '=', 'student_grades.oraliq_nazorat_id')
                ->select('students.id', 'students.full_name', 'student_grades.grade')
                ->where('oraliq_nazorats.id', $oraliqnazorat->id)
                ->where('students.group_id', $oraliqnazorat->group_hemis_id)
                ->get();
        }
        return view('teacher.oraliqnazorat.grade', compact('oraliqnazorat', 'students'));

    }
    function grade_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $oraliqnazorat = OraliqNazorat::find(intval($request->oraliqnazorat));
            $oraliqnazorat->status = 1;
            $oraliqnazorat->grade_teacher = $request->user()->short_name ?? $request->user()->name;
            $oraliqnazorat->save();
            foreach ($request->baho as $key => $baho) {
                $student = Student::find($key);
                StudentGrade::updateOrCreate([
                    'student_id' => $student->id,
                    'student_hemis_id' => $student->hemis_id,
                    'oraliq_nazorat_id' => $oraliqnazorat->id
                ], [
                    'hemis_id' => 999999999,
                    'semester_code' => $oraliqnazorat->semester->code,
                    'semester_name' => $oraliqnazorat->semester->name,
                    'subject_schedule_id' => 0,
                    'subject_id' => $oraliqnazorat->subject->subject_id,
                    'subject_name' => $oraliqnazorat->subject->subject_name,
                    'subject_code' => $oraliqnazorat->subject->subject_code,
                    'training_type_code' => 100,
                    'training_type_name' => "Oraliq nazorat",
                    'employee_id' => $oraliqnazorat->teacher->hemis_id,
                    'employee_name' => $oraliqnazorat->teacher->short_name,
                    'lesson_pair_name' => "",
                    'lesson_pair_code' => "",
                    'lesson_pair_start_time' => "",
                    'lesson_pair_end_time' => "",
                    'lesson_date' => $oraliqnazorat->start_date,
                    'created_at_api' => $oraliqnazorat->created_at,
                    'reason' => 'teacher_victim',
                    'status' => 'recorded',
                    'grade' => $baho,
                    'deadline' => now(),
                ]);
            }
            DB::commit();
            if (auth()->user()->hasRole('admin')) {
                return redirect()->route('admin.oraliqnazorat.grade', $oraliqnazorat->id)->with('success', 'Oraliq nazorat baholandi');
            }
            return back()->with('success', 'Oraliq nazorat baholandi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Oraliq nazorat Baholashda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Oraliq nazorat Baholashda xatolik: ' . $e->getMessage());
        }

    }
}
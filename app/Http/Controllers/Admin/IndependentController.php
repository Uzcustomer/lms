<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\Independent;
use App\Models\IndependentGradeHistory;
use App\Models\IndependentSubmission;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\MarkingSystemScore;
class IndependentController extends Controller
{
    public function index(Request $request)
    {
        $query = new Independent();
        if ($request->department) {
            $query = $query->where('department_hemis_id', $request->department);
        }
        if ($request->full_name) {
            $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        }
        if ($request->group) {
            $query = $query->where('group_hemis_id', $request->group);
        }
        if ($request->semester_code) {
            $query = $query->where('semester_hemis_id', $request->semester_code);
        }
        if ($request->subject) {
            $query = $query->where('subject_hemis_id', $request->subject);
            // $query = $query->whereHas('subject', function ($query) use ($request) {
            //     $query->where('subject_id', $request->subject);
            // });
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
        $independents = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.independent.index', compact('independents', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $user = Auth::user();
        if ($user->hasRole(['dekan'])) {
            $query = Independent::whereIn('department_hemis_id', $teacher->dean_faculty_ids);
        } else {
            $query = Independent::where('teacher_hemis_id', $teacher->hemis_id);
        }

        if ($request->full_name) {
            $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        }
        if ($request->group) {
            $query = $query->where('group_hemis_id', $request->group);
        }
        if (!empty($request->semester_code)) {
            $query = $query->where('semester_hemis_id', $request->semester_code);
        }
        if (!empty($request->subject)) {
            $query = $query->where('subject_hemis_id', $request->subject);
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
        $independents = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
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
        return view('teacher.independent.index', compact('independents', 'groups'));
    }
    public function create()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.independent.create', compact('departments', 'lastFilters'));
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
        return view('teacher.independent.create', compact('departments', 'groups', 'lastFilters'));

    }
    public function store(Request $request)
    {

        try {
            session([
                'last_lesson_filters' => [
                    'department_id' => $request->department_id,
                    'group_id' => $request->group_id,
                    'semester_code' => $request->semester_code,
                    'subject_id' => $request->subject_id,
                    'lesson_date' => $request->lesson_date,
                ]
            ]);
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
            Independent::create([
                'user_id' => Auth::user()->id,
                'teacher_hemis_id' => $teacher->hemis_id,
                'teacher_short_name' => $teacher->short_name,
                'teacher_name' => $teacher->full_name,
                'department_hemis_id' => $deportment->department_hemis_id,
                'deportment_name' => $deportment->name,
                'group_hemis_id' => $group->group_hemis_id,
                'group_name' => $group->name,
                'semester_hemis_id' => $semester->semester_hemis_id,
                'semester_name' => $semester->name,
                'semester_code' => $subject->semester_code,
                'subject_hemis_id' => $subject->curriculum_subject_hemis_id,
                'subject_name' => $subject->subject_name,
                'start_date' => $start_date,
                'deadline' => $end_date,
                'file_path' => $filePath,
                'file_original_name' => $fileOriginalName,
            ]);
            DB::commit();
            return back()->with('success', 'Mustaqil ta\'lim muvaffaqiyatli yaratildi');

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
            $independent = Independent::findOrFail($id);
            if (auth()->user()->hasRole('admin')) {
                StudentGrade::where('independent_id', $independent->id)->delete();
            } else {
                $count = StudentGrade::where('independent_id', $independent->id)->count();
                if ($count > 0) {
                    return back()->with('error', "Baholanib bo'lgan o'chirishga ruxsat yo'q");
                }
            }
            if ($independent->file_path) {
                Storage::disk('public')->delete($independent->file_path);
            }
            $independent->delete();
            DB::commit();
            return back()->with('success', 'Mustaqil ta\'lim muvaffaqiyatli o\'chirildi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mustaqil ta\'lim o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
    function grade($id)
    {
        $independent = Independent::find($id);
        if (empty($independent)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($independent->status == 0) {
            $students =
                $students = Student::leftJoin('student_grades', function ($join) use ($independent) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.independent_id', '=', $independent->id);
                })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $independent->group_hemis_id)
                    ->get();
        } else {
            $students = Student::leftJoin('student_grades', function ($join) use ($independent) {
                $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                    ->where('student_grades.independent_id', '=', $independent->id);
            })
                ->select(
                    'students.id',
                    'students.full_name',
                    'students.hemis_id',
                    DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                )
                ->where('students.group_id', $independent->group_hemis_id)
                ->get();
        }
        $submissions = IndependentSubmission::where('independent_id', $independent->id)
            ->get()
            ->keyBy('student_id');

        $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
            ->orderBy('student_id')
            ->orderBy('submission_number')
            ->get()
            ->groupBy('student_id');

        // Get minimum limit from group's curriculum marking system
        $group = Group::find($independent->group_hemis_id);
        $markingScore = $group && $group->curriculum_hemis_id
            ? MarkingSystemScore::where('marking_system_code',
                \App\Models\Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->value('marking_system_code')
              )->first()
            : null;
        $minimumLimit = $markingScore ? $markingScore->minimum_limit : 60;

        return view('admin.independent.grade', compact('independent', 'students', 'submissions', 'gradeHistory', 'minimumLimit'));

    }
    function grade_form($id)
    {
        $independent = Independent::find($id);
        if (empty($independent)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        if ($independent->status == 0) {
            $students =
                $students = Student::leftJoin('student_grades', function ($join) use ($independent) {
                    $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                        ->where('student_grades.independent_id', '=', $independent->id);
                })
                    ->select(
                        'students.id',
                        'students.full_name',
                        'students.hemis_id',
                        DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                    )
                    ->where('students.group_id', $independent->group_hemis_id)
                    ->get();
        } else {
            $students = Student::leftJoin('student_grades', function ($join) use ($independent) {
                $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                    ->where('student_grades.independent_id', '=', $independent->id);
            })
                ->select(
                    'students.id',
                    'students.full_name',
                    'students.hemis_id',
                    DB::raw('COALESCE(student_grades.grade, "") as grade'), // Agar grade bo'lmasa, 0 qaytadi
                )
                ->where('students.group_id', $independent->group_hemis_id)
                ->get();
        }
        $submissions = IndependentSubmission::where('independent_id', $independent->id)
            ->get()
            ->keyBy('student_id');

        $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
            ->orderBy('student_id')
            ->orderBy('submission_number')
            ->get()
            ->groupBy('student_id');

        // Get minimum limit from group's curriculum marking system
        $group = Group::find($independent->group_hemis_id);
        $markingScore = $group && $group->curriculum_hemis_id
            ? MarkingSystemScore::where('marking_system_code',
                \App\Models\Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->value('marking_system_code')
              )->first()
            : null;
        $minimumLimit = $markingScore ? $markingScore->minimum_limit : 60;

        return view('admin.independent.grade_form', compact('independent', 'students', 'submissions', 'gradeHistory', 'minimumLimit'));

    }
    function grade_teacher($id, Request $request)
    {
        $user = Auth::user();
        if ($user->hasRole(['dekan'])) {
            $independent = Independent::where('id', $id)->whereIn('department_hemis_id', $request->user()->dean_faculty_ids)->first();
        } else {
            $independent = Independent::where('id', $id)->where('teacher_hemis_id', $request->user()->hemis_id)->first();
        }
        if (empty($independent)) {
            return back()->with('error', 'Bunday mustaqil ish topilmadi');
        }
        $students = Student::leftJoin('student_grades', function ($join) use ($independent) {
                $join->on('students.hemis_id', '=', 'student_grades.student_hemis_id')
                    ->where('student_grades.independent_id', '=', $independent->id);
            })
            ->select(
                'students.id',
                'students.full_name',
                'students.hemis_id',
                DB::raw('COALESCE(student_grades.grade, "") as grade'),
            )
            ->where('students.group_id', $independent->group_hemis_id)
            ->get();

        // Load student submissions for this independent
        $submissions = IndependentSubmission::where('independent_id', $independent->id)
            ->get()
            ->keyBy('student_id');

        $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
            ->orderBy('student_id')
            ->orderBy('submission_number')
            ->get()
            ->groupBy('student_id');

        // Get minimum limit from group's curriculum marking system
        $group = Group::find($independent->group_hemis_id);
        $markingScore = $group && $group->curriculum_hemis_id
            ? MarkingSystemScore::where('marking_system_code',
                \App\Models\Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->value('marking_system_code')
              )->first()
            : null;
        $minimumLimit = $markingScore ? $markingScore->minimum_limit : 60;

        return view('teacher.independent.grade', compact('independent', 'students', 'submissions', 'gradeHistory', 'minimumLimit'));

    }
    function grade_save(Request $request)
    {
        try {
            DB::beginTransaction();
            $independent = Independent::find(intval($request->independent));
            $independent->status = 1;
            $independent->grade_teacher = $request->user()->short_name ?? $request->user()->name;
            $independent->save();
            foreach ($request->baho as $key => $baho) {
                // Skip if no grade was entered (empty field)
                if ($baho === null || $baho === '' || !is_numeric($baho)) {
                    continue;
                }

                $student = Student::find($key);

                // Skip if student already has grade >= minimum_limit (locked)
                $existingGrade = StudentGrade::where('student_id', $student->id)
                    ->where('independent_id', $independent->id)
                    ->first();
                $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
                if ($existingGrade && $existingGrade->grade >= $studentMinLimit) {
                    continue;
                }

                StudentGrade::updateOrCreate([
                    'student_id' => $student->id,
                    'student_hemis_id' => $student->hemis_id,
                    'independent_id' => $independent->id
                ], [
                    'hemis_id' => 888888888,
                    'semester_code' => $independent->semester->code,
                    'semester_name' => $independent->semester->name,
                    'subject_schedule_id' => 0,
                    'subject_id' => $independent->subject->subject_id,
                    'subject_name' => $independent->subject->subject_name,
                    'subject_code' => $independent->subject->subject_code,
                    'training_type_code' => 99,
                    'training_type_name' => "Mustaqil ta'lim",
                    'employee_id' => $independent->teacher->hemis_id,
                    'employee_name' => $independent->teacher->short_name,
                    'lesson_pair_name' => "",
                    'lesson_pair_code' => "",
                    'lesson_pair_start_time' => "",
                    'lesson_pair_end_time' => "",
                    'lesson_date' => $independent->start_date,
                    'created_at_api' => $independent->created_at,
                    'reason' => 'teacher_victim',
                    'status' => 'recorded',
                    'grade' => $baho,
                    'deadline' => now(),
                ]);
            }
            DB::commit();
            if (auth()->user()->hasRole('admin')) {
                return redirect()->route('admin.independent.grade', $independent->id)->with('success', 'Mustaqil ta\'lim baholandi');
            }
            return back()->with('success', 'Mustaqil ta\'lim baholandi');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mustaqil ta\'lim Baholashda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Mustaqil ta\'lim Baholashda xatolik: ' . $e->getMessage());
        }

    }
}
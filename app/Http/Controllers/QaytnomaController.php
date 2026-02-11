<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Group;
use App\Models\Qaytnoma;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use App\Enums\ProjectRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Auth;
class QaytnomaController extends Controller
{
    public function index(Request $request)
    {
        $query = new Qaytnoma();
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

        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $qaytnomas = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.qaytnoma.index', compact('qaytnomas', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $user = Auth::user();
        if (!$user->hasRole(['dekan'])) {
            return back();
        }
        $query = new Qaytnoma();
        $query = $query->where('department_hemis_id', $teacher->department_hemis_id);
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

        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $qaytnomas = $query->orderBy('id', 'desc')->paginate($perPage);
        $groups = Group::where('department_hemis_id', $teacher->department_hemis_id)->get();
        // $teachingGroups = $teacher->groups;
        // $groups = $departmentGroups->merge($teachingGroups)->unique('id');
        return view('teacher.qaytnoma.index', compact('qaytnomas', 'groups'));
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin'])) {
            return back();
        }
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.qaytnoma.create', compact('departments', 'lastFilters'));
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
        return view('teacher.qaytnoma.create', compact('departments', 'groups', 'lastFilters'));
    }
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            if (!$user->hasRole(['admin', 'dekan'])) {
                return back();
            }
            if (empty($request->shakl)) {
                return back()->with('error', 'Shaklni to\'ldiring');
            }
            if (empty($request->number)) {
                return back()->with('error', 'Raqamini to\'ldiring');
            }
            $deportment = Department::where('department_hemis_id', $request->department_id)->first();
            $group = Group::where('group_hemis_id', $request->group_id)->first();
            $semester = Semester::where('code', $request->semester_code)->where('curriculum_hemis_id', $group->curriculum_hemis_id)->first();
            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->where('subject_id', $request->subject_id)
                ->first();
            $teacher = Teacher::where('hemis_id', $request->teacher_id)->first();
            $currentDate = now();

            $count = Schedule::where('subject_id', $subject->subject_id)
                ->where('group_id', $group->group_hemis_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->where('lesson_date', '<=', $currentDate)
                ->distinct('lesson_date')
                ->count();

            $students = Student::selectRaw('
            students.full_name as student_name,
            students.student_id_number as student_id,

                   ROUND (
                        (SELECT sum(inner_table.average_grade)/ ' . $count . '
                        FROM (
                            SELECT lesson_date,AVG(COALESCE(
                                CASE 
                                    WHEN status = "retake" AND (reason = "absent" OR reason = "teacher_victim") 
                                    THEN retake_grade
                                    WHEN status = "retake" AND reason = "low_grade" 
                                    THEN retake_grade
                                    WHEN status = "pending" AND reason = "absent" 
                                    THEN grade
                                    ELSE grade
                                END, 0)) AS average_grade
                            FROM student_grades
                            WHERE student_grades.student_hemis_id = students.hemis_id
                            AND student_grades.subject_id = ' . $subject->subject_id . '
                            AND student_grades.training_type_code NOT IN (' . implode(',', config('app.training_type_code')) . ')
                            GROUP BY student_grades.lesson_date
                        ) AS inner_table)
                    ) as jn,
                    ROUND (
                       ( SELECT avg(
                            student_grades.grade
                        ) as average_grade
                        FROM student_grades
                        WHERE student_grades.student_hemis_id = students.hemis_id
                        AND student_grades.subject_id = ' . $subject->subject_id . '
                        AND student_grades.training_type_code =99
                        GROUP BY student_grades.student_hemis_id)
                    ) as mt,
                     students.hemis_id as hemis_id

                ')
                ->where('students.group_id', $group->group_hemis_id)
                ->groupBy('students.id')
                ->orderBy('students.hemis_id')
                ->get();
            $jadval_students = [];
            $deadline = Deadline::where('level_code', $semester->level_code)->first();
            foreach ($students as $student) {
                $qoldirgan = (int) Attendance::where('group_id', $group->group_hemis_id)
                    ->where('subject_id', $subject->subject_id)
                    ->where('student_hemis_id', $student->hemis_id)
                    ->sum('absent_off');
                $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                $holat = "Ruxsat";
                if ($student->jn < $deadline->joriy) {
                    $holat = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars("X") . '</w:t></w:r>';


                    $student->jn = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars($student->jn) . '</w:t></w:r>';
                }
                if ($student->mt < $deadline->mustaqil_talim) {
                    $student->mt = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars($student->mt) . '</w:t></w:r>';
                    $holat = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars("X") . '</w:t></w:r>';

                }
                if ($student->qoldiq > 25) {
                    $student->qoldiq = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars($student->qoldiq) . '</w:t></w:r>';
                    $holat = '<w:r><w:rPr><w:color w:val="FF0000"/></w:rPr><w:t>' . htmlspecialchars("X") . '</w:t></w:r>';
                }
                $jadval_students[] = [
                    'student_name' => $student->student_name,
                    'student_id' => $student->student_id,
                    'jn' => $student->jn,
                    'mt' => $student->mt,
                    'davomat' => $student->qoldiq != 0 ? round((float) ($student->qoldiq ?? 0), 2) . "%" : "0%",
                    "holati" => $holat
                ];
            }
            $url_file_qaytnoma = "/qaytnoma/" . time() . '.docx';
            $templatePath = public_path('qaytnoma.docx');
            $templateProcessor = new TemplateProcessor($templatePath);
            $templateProcessor->cloneRowAndSetValues('student_name', $jadval_students);

            $apiUrl = "https://api.qrserver.com/v1/create-qr-code";
            $text = env('APP_URL') . "/storage" . $url_file_qaytnoma;
            $fullUrl = "$apiUrl/?data=$text&size=500x500";
            $client = new \GuzzleHttp\Client();
            $qrcode_path = 'app/public/qrcodes/' . time() . '.png';
            $response = $client->request('GET', $fullUrl, [
                'verify' => false,
                'sink' => storage_path($qrcode_path),
            ]);
            if ($response->getStatusCode() != 200) {
                return 'Error: ' . $response->getStatusCode();
            }
            $file_path = storage_path($qrcode_path);
            $templateProcessor->setImageValue('qrcode', array('path' => $file_path, 'width' => 80, 'height' => 80));
            $studentIds = Student::where('group_id', $group->group_hemis_id)
                ->groupBy('hemis_id')
                ->pluck('hemis_id');
            $maruza_teacher = DB::table('student_grades as s')
                ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                ->where('s.subject_id', $subject->subject_id)
                ->where('s.training_type_code', 11)
                ->whereIn('s.student_hemis_id', $studentIds)
                ->groupBy('s.employee_id')
                ->first();
            $other_teachers = DB::table('student_grades as s')
                ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                ->where('s.subject_id', $subject->subject_id)
                ->where('training_type_code', '!=', 11)
                ->whereIn('s.student_hemis_id', $studentIds)
                ->groupBy('s.employee_id')
                ->get();
            $other_teacher_text = "";
            foreach ($other_teachers as $teacher) {
                $other_teacher_text .= $teacher->full_names . ", ";
            }

            if ($other_teacher_text == "") {
                $other_teacher_text = "!!!";
            }
            $dekan = Teacher::where('department_hemis_id', $deportment->department_hemis_id)
                ->whereHas('roles', fn ($q) => $q->where('name', ProjectRole::DEAN->value))
                ->first()->full_name ?? "";
            $data = [
                'fakultet_name' => $deportment->name,
                "kurs" => $semester->level_name,
                "semester" => $semester->name,
                "group" => $group->name,
                'subject' => $subject->subject_name,
                "maruza_teacher" => $maruza_teacher->full_names ?? "!!!",
                "other_teacher" => $other_teacher_text,
                "hours" => $subject->total_acload,
                "shakl" => $request->shakl,
                "number" => $request->number,
                'dekan' => $dekan

            ];
            foreach ($data as $key => $value) {
                $templateProcessor->setValue($key, $value);
            }
            $path = "app/public" . $url_file_qaytnoma;
            $newFilePath = storage_path($path);
            $templateProcessor->saveAs($newFilePath);
            $qaytnoma = Qaytnoma::create([
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
                'file_path' => "/storage" . $url_file_qaytnoma,
                "shakl" => $request->shakl,
                "number" => $request->number,
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

            $independent = Qaytnoma::findOrFail($id);

            StudentGrade::where('independent_id', $independent->id)->delete();

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
}
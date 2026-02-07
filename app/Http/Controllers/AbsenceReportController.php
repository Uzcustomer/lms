<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Group;
use App\Models\AbsenceReport;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AbsenceReportController extends Controller
{
    public function index(Request $request)
    {
        $query = new AbsenceReport();
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

        $perPage = $request->get('per_page', 50);
        $absenceReports = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.absence_report.index', compact('absenceReports', 'departments'));
    }

    public function index_teacher(Request $request)
    {
        $teacher = Auth::guard('teacher')->user();
        $user = Auth::user();
        if (!$user->hasRole(['dekan'])) {
            return back();
        }
        $query = new AbsenceReport();
        $query = $query->where('department_hemis_id', $teacher->department_hemis_id);
        if ($request->group) {
            $query = $query->where('group_id', $request->group);
        }
        if ($request->semester) {
            $query = $query->where('semester_id', $request->semester);
        }
        if ($request->subject) {
            $query = $query->where('subject_id', $request->subject);
        }

        $perPage = $request->get('per_page', 50);
        $absenceReports = $query->orderBy('id', 'desc')->paginate($perPage);
        $groups = Group::where('department_hemis_id', $teacher->department_hemis_id)->get();
        return view('teacher.absence_report.index', compact('absenceReports', 'groups'));
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user->hasRole(['admin'])) {
            return back();
        }
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.absence_report.create', compact('departments', 'lastFilters'));
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
        return view('teacher.absence_report.create', compact('departments', 'groups', 'lastFilters'));
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

            $absenceReport = AbsenceReport::create([
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
                "shakl" => $request->shakl,
                "number" => $request->number,
            ]);

            DB::commit();

            return back()->with('success', '74 soat dars qoldirish muvaffaqiyatli yaratildi');

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

            $absenceReport = AbsenceReport::findOrFail($id);

            if ($absenceReport->file_path) {
                Storage::disk('public')->delete($absenceReport->file_path);
            }

            $absenceReport->delete();

            DB::commit();
            return back()->with('success', '74 soat dars qoldirish muvaffaqiyatli o\'chirildi');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('74 soat dars qoldirish o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
}

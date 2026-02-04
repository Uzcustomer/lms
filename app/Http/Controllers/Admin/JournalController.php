<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Deadline;
use App\Models\Specialty;
use App\Models\StudentGrade;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        // Get filter options for dropdowns
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $educationYears = Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->get();

        $faculties = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get();

        // Build query for journal entries
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->select([
                'cs.id',
                'cs.subject_id',
                'cs.subject_name',
                'cs.semester_code',
                'cs.semester_name',
                'c.education_type_name',
                'c.education_year_name',
                'g.id as group_id',
                'g.name as group_name',
                'd.name as department_name',
                'sp.name as specialty_name',
                's.level_name',
            ])
            ->distinct();

        // Apply filters
        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }

        if ($request->filled('education_year')) {
            $query->where('c.education_year_code', $request->education_year);
        }

        if ($request->filled('faculty')) {
            $query->where('d.id', $request->faculty);
        }

        if ($request->filled('specialty')) {
            $query->where('sp.specialty_hemis_id', $request->specialty);
        }

        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }

        if ($request->filled('subject')) {
            $query->where('cs.subject_id', $request->subject);
        }

        if ($request->filled('group')) {
            $query->where('g.id', $request->group);
        }

        // Sorting
        $sortColumn = $request->get('sort', 'group_name');
        $sortDirection = $request->get('direction', 'asc');

        // Map sort columns to actual database columns
        $sortMap = [
            'education_type' => 'c.education_type_name',
            'education_year' => 'c.education_year_name',
            'faculty' => 'd.name',
            'specialty' => 'sp.name',
            'level' => 's.level_name',
            'semester' => 'cs.semester_name',
            'subject' => 'cs.subject_name',
            'group_name' => 'g.name',
        ];

        $orderByColumn = $sortMap[$sortColumn] ?? 'g.name';
        $query->orderBy($orderByColumn, $sortDirection);

        $perPage = $request->get('per_page', 50);
        $journals = $query->paginate($perPage)->appends($request->query());

        return view('admin.journal.index', compact(
            'journals',
            'educationTypes',
            'educationYears',
            'faculties',
            'sortColumn',
            'sortDirection'
        ));
    }

    public function show(Request $request, $groupId, $subjectId, $semesterCode)
    {
        $group = Group::where('id', $groupId)->firstOrFail();
        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->firstOrFail();

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        // Deadline days for retake eligibility check
        $deadline = $semester ? Deadline::where('level_code', $semester->level_code)->first() : null;
        $deadlineDays = $deadline ? $deadline->deadline_days : 0;

        // Students with aggregated averages (retake_grade considered)
        $students = DB::table('students as st')
            ->where('st.group_id', $group->group_hemis_id)
            ->where('st.is_graduate', false)
            ->leftJoin('student_grades as sg', function ($join) use ($subjectId, $semesterCode) {
                $join->on('sg.student_hemis_id', '=', 'st.hemis_id')
                    ->where('sg.subject_id', '=', $subjectId)
                    ->where('sg.semester_code', '=', $semesterCode);
            })
            ->select([
                'st.id',
                'st.hemis_id',
                'st.full_name',
                'st.student_id_number',
                DB::raw("AVG(CASE WHEN sg.training_type_code NOT IN (99, 100, 101, 102) THEN (CASE WHEN sg.retake_grade IS NOT NULL AND sg.retake_grade > 0 THEN sg.retake_grade ELSE sg.grade END) END) as jb_average"),
                DB::raw("AVG(CASE WHEN sg.training_type_code = 99 THEN (CASE WHEN sg.retake_grade IS NOT NULL AND sg.retake_grade > 0 THEN sg.retake_grade ELSE sg.grade END) END) as mt_average"),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 100 THEN sg.grade END) as on_average'),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 101 THEN sg.grade END) as oski_average'),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 102 THEN sg.grade END) as test_average'),
            ])
            ->groupBy('st.id', 'st.hemis_id', 'st.full_name', 'st.student_id_number')
            ->orderBy('st.full_name')
            ->get();

        // All individual grade records for per-cell display
        $allGrades = DB::table('student_grades as sg')
            ->join('students as st', 'sg.student_hemis_id', '=', 'st.hemis_id')
            ->where('st.group_id', $group->group_hemis_id)
            ->where('sg.subject_id', $subjectId)
            ->where('sg.semester_code', $semesterCode)
            ->select([
                'sg.id',
                'sg.student_hemis_id',
                'sg.grade',
                'sg.retake_grade',
                'sg.status',
                'sg.reason',
                'sg.training_type_code',
                DB::raw("DATE_FORMAT(sg.lesson_date, '%Y-%m-%d') as lesson_date"),
            ])
            ->orderBy('sg.lesson_date')
            ->get();

        // Distinct sorted dates for Amaliyot (joriy) tab
        $amaliyotDates = $allGrades
            ->filter(fn($g) => !in_array((int) $g->training_type_code, [99, 100, 101, 102]))
            ->pluck('lesson_date')
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Distinct sorted dates for Mustaqil ta'lim tab
        $mtDates = $allGrades
            ->filter(fn($g) => (int) $g->training_type_code === 99)
            ->pluck('lesson_date')
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Build lookup maps: map[student_hemis_id][date] = gradeRecord
        $amaliyotMap = [];
        $mtMap = [];
        foreach ($allGrades as $g) {
            $hemis = (string) $g->student_hemis_id;
            if ((int) $g->training_type_code === 99) {
                if (!isset($mtMap[$hemis][$g->lesson_date])) {
                    $mtMap[$hemis][$g->lesson_date] = $g;
                }
            } elseif (!in_array((int) $g->training_type_code, [99, 100, 101, 102])) {
                if (!isset($amaliyotMap[$hemis][$g->lesson_date])) {
                    $amaliyotMap[$hemis][$g->lesson_date] = $g;
                }
            }
        }

        return view('admin.journal.show', compact(
            'group',
            'subject',
            'curriculum',
            'semester',
            'students',
            'amaliyotDates',
            'mtDates',
            'amaliyotMap',
            'mtMap',
            'deadlineDays'
        ));
    }

    public function retakeGradeUpdate(Request $request, $gradeId)
    {
        $request->validate([
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $grade = StudentGrade::findOrFail($gradeId);

        if ($grade->retake_grade !== null) {
            return response()->json(['error' => 'Bu baho allaqachon qayta topshirilgan.'], 422);
        }

        if ($grade->grade !== null && (float) $grade->grade >= 60) {
            return response()->json(['error' => 'Bu baho qayta topshirish uchun eligible emas.'], 422);
        }

        // Deadline check for non-admin users
        if (!Auth::user()->hasRole('admin')) {
            $student = $grade->student;
            if ($student) {
                $semester = Semester::where('curriculum_hemis_id', $student->curriculum_id)
                    ->where('code', $grade->semester_code)
                    ->first();
                if ($semester) {
                    $deadlineRecord = Deadline::where('level_code', $semester->level_code)->first();
                    if ($deadlineRecord && $deadlineRecord->deadline_days) {
                        $lessonDate = Carbon::parse($grade->lesson_date);
                        if ($lessonDate->copy()->addDays($deadlineRecord->deadline_days)->isPast()) {
                            return response()->json(['error' => 'Muddati o\'tgan. Qayta topshirish mumkin emas.'], 422);
                        }
                    }
                }
            }
        }

        $grade->update([
            'retake_grade' => $request->grade,
            'status' => 'retake',
            'graded_by_user_id' => Auth::user()->id,
            'retake_graded_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'retake_grade' => (float) $grade->retake_grade]);
    }

    // AJAX endpoints for cascading dropdowns
    public function getSpecialties(Request $request)
    {
        $query = Specialty::query();

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }

        return $query->select('specialty_hemis_id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'specialty_hemis_id');
    }

    public function getLevelCodes(Request $request)
    {
        $query = Semester::query();

        if ($request->filled('education_year')) {
            $query->where('education_year', $request->education_year);
        }

        return $query->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get()
            ->pluck('level_name', 'level_code');
    }

    public function getSemesters(Request $request)
    {
        $query = Semester::query();

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        return $query->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->get()
            ->pluck('name', 'code');
    }

    public function getSubjects(Request $request)
    {
        $query = CurriculumSubject::query();

        if ($request->filled('semester_code')) {
            $query->where('semester_code', $request->semester_code);
        }

        return $query->select('subject_id', 'subject_name')
            ->groupBy('subject_id', 'subject_name')
            ->orderBy('subject_name')
            ->get()
            ->pluck('subject_name', 'subject_id');
    }

    public function getGroups(Request $request)
    {
        $query = Group::query();

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('specialty_id')) {
            $query->where('specialty_hemis_id', $request->specialty_id);
        }

        return $query->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');
    }

    // Ikki tomonlama bog'liq filtrlar
    public function getFacultiesBySpecialty(Request $request)
    {
        if (!$request->filled('specialty_id')) {
            return Department::where('structure_type_code', 11)
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $specialty = Specialty::where('specialty_hemis_id', $request->specialty_id)->first();
        if (!$specialty) {
            return [];
        }

        return Department::where('structure_type_code', 11)
            ->where('department_hemis_id', $specialty->department_hemis_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getLevelCodesBySemester(Request $request)
    {
        if (!$request->filled('semester_code')) {
            return Semester::select('level_code', 'level_name')
                ->groupBy('level_code', 'level_name')
                ->orderBy('level_code')
                ->pluck('level_name', 'level_code');
        }

        return Semester::where('code', $request->semester_code)
            ->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->pluck('level_name', 'level_code');
    }

    public function getEducationYearsByLevel(Request $request)
    {
        if (!$request->filled('level_code')) {
            return Curriculum::select('education_year_code', 'education_year_name')
                ->whereNotNull('education_year_code')
                ->groupBy('education_year_code', 'education_year_name')
                ->orderBy('education_year_code', 'desc')
                ->pluck('education_year_name', 'education_year_code');
        }

        $semesterCurriculumIds = Semester::where('level_code', $request->level_code)
            ->pluck('curriculum_hemis_id');

        return Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->whereIn('curricula_hemis_id', $semesterCurriculumIds)
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->pluck('education_year_name', 'education_year_code');
    }
}

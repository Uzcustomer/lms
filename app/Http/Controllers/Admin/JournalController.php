<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

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

        $orderColumn = $sortMap[$sortColumn] ?? 'g.name';
        $query->orderBy($orderColumn, $sortDirection);

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

        $excludedTrainingTypes = [
            'Ma’ruza',
            "Mustaqil ta'lim",
            'Oraliq nazorat',
            'Oski',
            'Yakuniy test',
        ];
        $excludedTrainingTypesSql = collect($excludedTrainingTypes)
            ->map(fn ($type) => "'" . str_replace("'", "''", $type) . "'")
            ->implode(', ');

        $lessonDates = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->where('st.group_id', $group->group_hemis_id)
            ->where('sg.subject_id', $subjectId)
            ->where('sg.semester_code', $semesterCode)
            ->whereNotIn('sg.training_type_name', $excludedTrainingTypes)
            ->selectRaw('DATE(sg.lesson_date) as lesson_date')
            ->distinct()
            ->orderBy('lesson_date')
            ->pluck('lesson_date')
            ->map(fn ($date) => Carbon::parse($date));

        $gradesByStudentDate = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->where('st.group_id', $group->group_hemis_id)
            ->where('sg.subject_id', $subjectId)
            ->where('sg.semester_code', $semesterCode)
            ->whereNotIn('sg.training_type_name', $excludedTrainingTypes)
            ->selectRaw('sg.student_hemis_id, DATE(sg.lesson_date) as lesson_date, AVG(sg.grade) as grade')
            ->groupBy('sg.student_hemis_id', 'lesson_date')
            ->get()
            ->groupBy('student_hemis_id')
            ->map(function ($items) {
                return $items->mapWithKeys(function ($item) {
                    return [Carbon::parse($item->lesson_date)->format('Y-m-d') => $item->grade];
                });
            });

        // Get students with their grades for this subject
        $students = DB::table('students as st')
            ->where('st.group_id', $group->group_hemis_id)
            ->where('st.is_active', true)
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
                DB::raw("AVG(CASE WHEN sg.training_type_name NOT IN ($excludedTrainingTypesSql) THEN sg.grade END) as jb_average"),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 99 THEN sg.grade END) as mt_average'),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 100 THEN sg.grade END) as on_average'),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 101 THEN sg.grade END) as oski_average'),
                DB::raw('AVG(CASE WHEN sg.training_type_code = 102 THEN sg.grade END) as test_average'),
            ])
            ->groupBy('st.id', 'st.hemis_id', 'st.full_name', 'st.student_id_number')
            ->orderBy('st.full_name')
            ->get();

        return view('admin.journal.show', compact(
            'group',
            'subject',
            'curriculum',
            'semester',
            'students',
            'lessonDates',
            'gradesByStudentDate'
        ));
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

    // Reverse lookup: Get faculties that have a specific specialty
    public function getFaculties(Request $request)
    {
        $query = Department::where('structure_type_code', 11);

        if ($request->filled('specialty_id')) {
            $specialty = Specialty::where('specialty_hemis_id', $request->specialty_id)->first();
            if ($specialty) {
                $query->where('department_hemis_id', $specialty->department_hemis_id);
            }
        }

        return $query->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');
    }

    // Reverse lookup: Get education years for a specific level code
    public function getEducationYears(Request $request)
    {
        $query = Semester::query();

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $query->where('code', $request->semester_code);
        }

        return $query->select('education_year')
            ->groupBy('education_year')
            ->orderBy('education_year', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                $name = ($item->education_year - 1) . '-' . $item->education_year;
                return [$item->education_year => $name];
            });
    }

    // Get all filter options based on current selections
    public function getFilterOptions(Request $request)
    {
        $response = [];

        // Faculties
        $facultyQuery = Department::where('structure_type_code', 11);
        if ($request->filled('specialty_id')) {
            $specialty = Specialty::where('specialty_hemis_id', $request->specialty_id)->first();
            if ($specialty) {
                $facultyQuery->where('department_hemis_id', $specialty->department_hemis_id);
            }
        }
        $response['faculties'] = $facultyQuery->orderBy('name')->pluck('name', 'id');

        // Specialties
        $specialtyQuery = Specialty::query();
        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $specialtyQuery->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }
        $response['specialties'] = $specialtyQuery->orderBy('name')->pluck('name', 'specialty_hemis_id');

        // Groups
        $groupQuery = Group::query();
        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $groupQuery->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty_id')) {
            $groupQuery->where('specialty_hemis_id', $request->specialty_id);
        }
        $response['groups'] = $groupQuery->orderBy('name')->pluck('name', 'id');

        // Level codes (courses)
        $levelQuery = Semester::query();
        if ($request->filled('education_year')) {
            $levelQuery->where('education_year', $request->education_year);
        }
        if ($request->filled('semester_code')) {
            $levelQuery->where('code', $request->semester_code);
        }
        $response['levels'] = $levelQuery->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->pluck('level_name', 'level_code');

        // Semesters
        $semesterQuery = Semester::query();
        if ($request->filled('level_code')) {
            $semesterQuery->where('level_code', $request->level_code);
        }
        if ($request->filled('education_year')) {
            $semesterQuery->where('education_year', $request->education_year);
        }
        $response['semesters'] = $semesterQuery->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->pluck('name', 'code');

        // Subjects
        $subjectQuery = CurriculumSubject::query();
        if ($request->filled('semester_code')) {
            $subjectQuery->where('semester_code', $request->semester_code);
        }
        $response['subjects'] = $subjectQuery->select('subject_id', 'subject_name')
            ->groupBy('subject_id', 'subject_name')
            ->orderBy('subject_name')
            ->pluck('subject_name', 'subject_id');

        return response()->json($response);
    }
}

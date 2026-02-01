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

class JournalController extends Controller
{
    public function index(Request $request)
    {
        // Get filter options for dropdowns
        $faculties = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get();

        // Build query from student_grades - get unique group/subject/semester combinations
        $query = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'g.curriculum_hemis_id')
                    ->on('s.code', '=', 'sg.semester_code');
            })
            ->select([
                'g.id as group_id',
                'g.group_hemis_id',
                'g.name as group_name',
                'sg.subject_id',
                'sg.subject_name',
                'sg.semester_code',
                'sg.semester_name',
                'd.id as department_id',
                'd.department_hemis_id',
                'd.name as department_name',
                'sp.specialty_hemis_id',
                'sp.name as specialty_name',
                'c.education_type_code',
                'c.education_type_name',
                'c.education_year_code',
                'c.education_year_name',
                's.level_code',
                's.level_name',
                DB::raw('COUNT(DISTINCT sg.id) as grades_count'),
                DB::raw('COUNT(DISTINCT st.id) as students_count'),
            ])
            ->groupBy(
                'g.id', 'g.group_hemis_id', 'g.name',
                'sg.subject_id', 'sg.subject_name',
                'sg.semester_code', 'sg.semester_name',
                'd.id', 'd.department_hemis_id', 'd.name',
                'sp.specialty_hemis_id', 'sp.name',
                'c.education_type_code', 'c.education_type_name',
                'c.education_year_code', 'c.education_year_name',
                's.level_code', 's.level_name'
            );

        // Apply filters
        if ($request->filled('faculty')) {
            $query->where('d.id', $request->faculty);
        }

        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $query->where('sg.semester_code', $request->semester_code);
        }

        if ($request->filled('subject')) {
            $query->where('sg.subject_id', $request->subject);
        }

        if ($request->filled('group')) {
            $query->where('g.id', $request->group);
        }

        $perPage = $request->get('per_page', 50);
        $journals = $query->orderBy('g.name')->orderBy('sg.subject_name')->paginate($perPage)->appends($request->query());

        // Get level codes for filter
        $levelCodes = Semester::select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get();

        return view('admin.journal.index', compact(
            'journals',
            'faculties',
            'levelCodes'
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

        // Get students with their grades for this subject
        $students = DB::table('students as st')
            ->where('st.group_id', $group->group_hemis_id)
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
                DB::raw('AVG(CASE WHEN sg.training_type_code NOT IN (99, 100, 101, 102) THEN sg.grade END) as jb_average'),
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
            'students'
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
}

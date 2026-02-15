<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\YnSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class YnSubmissionReportController extends Controller
{
    public function index(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            $selectedEducationType = $educationTypes
                ->first(fn($type) => str_contains(mb_strtolower($type->education_type_name ?? ''), 'bakalavr'))
                ?->education_type_code;
        }

        return view('admin.yn-submission-report.index', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'dekanFacultyIds'
        ));
    }

    public function data(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $query = DB::table('yn_submissions as ys')
            ->join('groups as g', 'g.group_hemis_id', '=', 'ys.group_hemis_id')
            ->join('users as u', 'u.id', '=', 'ys.submitted_by')
            ->leftJoin('curriculum_subjects as cs', function ($join) {
                $join->on('cs.subject_id', '=', 'ys.subject_id')
                    ->on('cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                    ->on('cs.semester_code', '=', 'ys.semester_code');
            })
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->select([
                'ys.id',
                'ys.subject_id',
                'ys.semester_code',
                'ys.group_hemis_id',
                'ys.submitted_at',
                'g.name as group_name',
                'g.department_name',
                'g.department_hemis_id',
                'g.specialty_name',
                'g.level_name',
                'g.level_code',
                'u.name as submitted_by_name',
                DB::raw('COALESCE(cs.subject_name, ys.subject_id) as subject_name'),
            ]);

        // Filtrlar
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $query->where('g.department_hemis_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('education_type')) {
            $query->where('g.education_type_code', $request->education_type);
        }
        if ($request->filled('specialty')) {
            $query->where('g.specialty_id', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $query->where('g.level_code', $request->level_code);
        }
        if ($request->filled('semester')) {
            $query->where('ys.semester_code', $request->semester);
        }
        if ($request->filled('group')) {
            $query->where('ys.group_hemis_id', $request->group);
        }

        // Joriy semestr filtri
        if ($request->get('current_semester', '0') == '1') {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->unique()
                ->toArray();

            if (!empty($currentSemesterCodes)) {
                $query->whereIn('ys.semester_code', $currentSemesterCodes);
            }
        }

        // Sorting
        $sortColumn = $request->get('sort', 'ys.submitted_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSorts = [
            'submitted_at' => 'ys.submitted_at',
            'group_name' => 'g.name',
            'department_name' => 'g.department_name',
            'specialty_name' => 'g.specialty_name',
            'level_name' => 'g.level_name',
            'subject_name' => 'subject_name',
            'submitted_by_name' => 'u.name',
        ];

        $sortCol = $allowedSorts[$sortColumn] ?? 'ys.submitted_at';
        $sortDir = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'desc';

        $total = $query->count();

        // Pagination
        $perPage = 50;
        $page = max(1, (int) $request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $results = $query->orderBy($sortCol, $sortDir)
            ->offset($offset)
            ->limit($perPage)
            ->get();

        // Semestr nomini olish
        $semesterCodes = $results->pluck('semester_code')->unique()->toArray();
        $semesterNames = [];
        if (!empty($semesterCodes)) {
            $semesterNames = DB::table('semesters')
                ->whereIn('code', $semesterCodes)
                ->pluck('name', 'code')
                ->toArray();
        }

        $data = $results->map(function ($item, $i) use ($offset, $semesterNames) {
            return [
                'row_num' => $offset + $i + 1,
                'id' => $item->id,
                'group_name' => $item->group_name,
                'department_name' => $item->department_name,
                'specialty_name' => $item->specialty_name,
                'level_name' => $item->level_name,
                'semester_name' => $semesterNames[$item->semester_code] ?? $item->semester_code,
                'subject_name' => $item->subject_name,
                'submitted_by_name' => $item->submitted_by_name,
                'submitted_at' => $item->submitted_at ? \Carbon\Carbon::parse($item->submitted_at)->format('d.m.Y H:i') : '-',
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'total' => $total,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
            'per_page' => $perPage,
        ]);
    }
}

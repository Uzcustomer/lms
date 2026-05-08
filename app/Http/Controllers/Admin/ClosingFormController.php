<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClosingFormController extends Controller
{
    private const ALLOWED_FORMS = ['oski', 'test', 'oski_test', 'normativ', 'sinov', 'none'];

    private function checkAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        $activeRole = session('active_role', '');
        $allowedRoles = ['superadmin', 'admin', 'kichik_admin', 'oquv_bolimi', 'oquv_bolimi_boshligi'];
        if (!in_array($activeRole, $allowedRoles, true)) {
            abort(403, "Yopilish shakli sahifasiga faqat o'quv bo'limi va adminlar kira oladi.");
        }
    }

    public function index(Request $request)
    {
        $this->checkAccess();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            $selectedEducationType = $educationTypes
                ->first(fn($t) => str_contains(mb_strtolower($t->education_type_name ?? ''), 'bakalavr'))
                ?->education_type_code;
        }

        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->select([
                'cs.id',
                'cs.subject_name',
                'cs.semester_name',
                'cs.closing_form',
                'cs.is_active',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
                's.level_code',
            ])
            ->where('cs.is_active', true);

        if ($selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
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
        if ($request->filled('subject_name')) {
            $query->where('cs.subject_name', 'like', '%' . $request->subject_name . '%');
        }
        if ($request->filled('closing_form_filter')) {
            $cf = $request->closing_form_filter;
            if ($cf === 'unset') {
                $query->whereNull('cs.closing_form');
            } elseif (in_array($cf, self::ALLOWED_FORMS, true)) {
                $query->where('cs.closing_form', $cf);
            }
        }

        if ($request->get('current_semester', '1') == '1') {
            $query->whereIn('s.semester_hemis_id', function ($sub) {
                $sub->select('semester_hemis_id')
                    ->from('curriculum_weeks')
                    ->groupBy('semester_hemis_id')
                    ->havingRaw('MIN(start_date) <= ? AND MAX(end_date) >= ?', [now()->toDateString(), now()->toDateString()]);
            });
        }

        $query->orderBy('f.name')
            ->orderBy('sp.name')
            ->orderBy('s.level_code')
            ->orderBy('cs.semester_code')
            ->orderBy('cs.subject_name');

        $perPage = (int) $request->get('per_page', 50);
        $subjects = $query->paginate($perPage)->appends($request->query());

        return view('admin.closing-form.index', compact(
            'subjects',
            'educationTypes',
            'selectedEducationType',
            'faculties'
        ));
    }

    public function bulkUpdate(Request $request)
    {
        $this->checkAccess();

        $validated = $request->validate([
            'closing_forms' => 'required|array',
            'closing_forms.*' => 'nullable|in:oski,test,oski_test,normativ,sinov,none',
        ]);

        $forms = $validated['closing_forms'];
        $grouped = [];
        foreach ($forms as $id => $value) {
            $id = (int) $id;
            if ($id <= 0) continue;
            $grouped[$value ?? '__null__'][] = $id;
        }

        DB::transaction(function () use ($grouped) {
            foreach ($grouped as $value => $ids) {
                $payload = ['closing_form' => $value === '__null__' ? null : $value];
                CurriculumSubject::whereIn('id', $ids)->update($payload);
            }
        });

        return redirect()
            ->route('admin.closing-form.index', $request->query())
            ->with('success', "Yopilish shakli muvaffaqiyatli saqlandi.");
    }

    public function getSpecialties(Request $request)
    {
        $this->checkAccess();
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'c.specialty_hemis_id')
            ->where('cs.is_active', true)
            ->whereNotNull('sp.specialty_hemis_id')
            ->whereNotNull('sp.name');

        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }
        if ($request->filled('faculty_id')) {
            $query->where('f.id', $request->faculty_id);
        }
        if ($request->get('current_semester', '1') == '1') {
            $query->whereIn('s.semester_hemis_id', function ($sub) {
                $sub->select('semester_hemis_id')
                    ->from('curriculum_weeks')
                    ->groupBy('semester_hemis_id')
                    ->havingRaw('MIN(start_date) <= ? AND MAX(end_date) >= ?', [now()->toDateString(), now()->toDateString()]);
            });
        }

        $specialties = $query
            ->select('sp.specialty_hemis_id', 'sp.name')
            ->groupBy('sp.specialty_hemis_id', 'sp.name')
            ->orderBy('sp.name')
            ->get();

        $result = [];
        foreach ($specialties as $sp) {
            $result[$sp->specialty_hemis_id] = $sp->name;
        }
        return response()->json($result);
    }

    public function getLevelCodes(Request $request)
    {
        $this->checkAccess();
        $levels = DB::table('semesters')
            ->select('level_code', 'level_name')
            ->whereNotNull('level_code')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get();

        $result = [];
        foreach ($levels as $level) {
            $result[$level->level_code] = $level->level_name;
        }
        return response()->json($result);
    }

    public function getSemesters(Request $request)
    {
        $this->checkAccess();
        $query = DB::table('semesters')
            ->whereNotNull('code')
            ->whereNotNull('name');

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        $semesters = $query
            ->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->get();

        $result = [];
        foreach ($semesters as $semester) {
            $result[$semester->code] = $semester->name;
        }
        return response()->json($result);
    }
}

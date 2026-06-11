<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CurriculumComparisonExport;
use App\Http\Controllers\Controller;
use App\Imports\ManualCurriculumImport;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\ManualCurriculum;
use App\Models\Semester;
use App\Models\Specialty;
use App\Services\CurriculumComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * O'quv reja to'g'riligi (akkreditatsiya monitoringi):
 * namunaviy va ishchi o'quv rejalarni Excel orqali yuklash
 * hamda ularni fanlar kesimida solishtirish.
 */
class CurriculumCheckController extends Controller
{
    public function index()
    {
        $curricula = ManualCurriculum::withCount('subjects')
            ->withSum('subjects as total_hours', 'total_hours')
            ->withSum('subjects as total_credit', 'credit')
            ->orderByDesc('id')
            ->get();

        $educationTypes = Curriculum::query()
            ->whereNotNull('education_type_code')
            ->select('education_type_code', 'education_type_name')
            ->distinct()
            ->orderBy('education_type_code')
            ->get();

        return view('admin.oquv-reja.index', compact('curricula', 'educationTypes'));
    }

    /**
     * HEMIS ma'lumotlari asosida cascade tanlov ro'yxatlari:
     * ta'lim turi -> fakultet -> yo'nalish -> kurs -> semestr -> o'quv reja
     */
    public function options(Request $request)
    {
        $curricula = Curriculum::query();
        if ($request->filled('education_type_code')) {
            $curricula->where('education_type_code', $request->education_type_code);
        }
        if ($request->filled('department_id')) {
            $curricula->where('department_hemis_id', $request->department_id);
        }
        if ($request->filled('specialty_id')) {
            $curricula->where('specialty_hemis_id', $request->specialty_id);
        }

        return response()->json(match ($request->input('list')) {
            'faculties' => Department::whereIn(
                    'department_hemis_id',
                    $curricula->clone()->select('department_hemis_id')->distinct()->pluck('department_hemis_id')
                )
                ->orderBy('name')
                ->get(['department_hemis_id as id', 'name']),

            'specialties' => Specialty::whereIn(
                    'specialty_hemis_id',
                    $curricula->clone()->select('specialty_hemis_id')->distinct()->pluck('specialty_hemis_id')
                )
                ->orderBy('code')
                ->get(['specialty_hemis_id as id', 'code', 'name']),

            'levels' => Semester::whereIn(
                    'curriculum_hemis_id',
                    $curricula->clone()->select('curricula_hemis_id')->pluck('curricula_hemis_id')
                )
                ->select('level_code', 'level_name')
                ->distinct()
                ->orderBy('level_code')
                ->get(),

            'semesters' => Semester::whereIn(
                    'curriculum_hemis_id',
                    $curricula->clone()->select('curricula_hemis_id')->pluck('curricula_hemis_id')
                )
                ->when($request->filled('level_code'), fn($q) => $q->where('level_code', $request->level_code))
                ->select('code', 'name', 'current')
                ->distinct()
                ->orderBy('code')
                ->get(),

            // Joriy semestr(lar): toggle yoqilganda kurs/semestrni avtomatik tanlash uchun
            'current' => Semester::whereIn(
                    'curriculum_hemis_id',
                    $curricula->clone()->select('curricula_hemis_id')->pluck('curricula_hemis_id')
                )
                ->where('current', true)
                ->when($request->filled('level_code'), fn($q) => $q->where('level_code', $request->level_code))
                ->select('level_code', 'level_name', 'code', 'name')
                ->distinct()
                ->orderBy('level_code')
                ->get(),

            'curricula' => $curricula->clone()
                ->when($request->filled('semester_code'), fn($q) => $q->whereIn(
                    'curricula_hemis_id',
                    Semester::where('code', $request->semester_code)
                        ->when($request->filled('level_code'), fn($s) => $s->where('level_code', $request->level_code))
                        ->pluck('curriculum_hemis_id')
                ))
                ->orderBy('name')
                ->get(['curricula_hemis_id as id', 'name', 'education_year_name']),

            default => [],
        });
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:namunaviy,ishchi',
            'curricula_hemis_id' => 'required|exists:curricula,curricula_hemis_id',
            'level_code' => 'nullable|string|max:20',
            'semester_code' => 'nullable|string|max:20',
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $hemisCurriculum = Curriculum::where('curricula_hemis_id', $request->curricula_hemis_id)->first();
        $specialty = Specialty::where('specialty_hemis_id', $hemisCurriculum->specialty_hemis_id)->first();

        $typeLabel = $request->type === 'namunaviy' ? 'namunaviy' : 'ishchi';
        $name = $hemisCurriculum->name . ' — ' . $typeLabel;
        if ($request->filled('semester_code')) {
            $semesterName = Semester::where('curriculum_hemis_id', $hemisCurriculum->curricula_hemis_id)
                ->where('code', $request->semester_code)
                ->value('name');
            $name .= ' (' . ($semesterName ?: $request->semester_code . '-semestr') . ')';
        }

        $file = $request->file('file');

        DB::beginTransaction();
        try {
            $curriculum = ManualCurriculum::create([
                'type' => $request->type,
                'name' => $name,
                'specialty_code' => $specialty?->code,
                'specialty_name' => $specialty?->name,
                'plan_year' => $hemisCurriculum->education_year_name,
                'curricula_hemis_id' => $hemisCurriculum->curricula_hemis_id,
                'level_code' => $request->level_code,
                'semester_code' => $request->semester_code,
                'education_type_name' => $hemisCurriculum->education_type_name,
                'education_period' => $hemisCurriculum->education_period,
                'file_original_name' => $file->getClientOriginalName(),
                'file_path' => $file->store('manual-curricula', 'public'),
                'created_by' => Auth::id(),
            ]);

            $import = new ManualCurriculumImport($curriculum);
            Excel::import($import, $file);

            if (!empty($import->errors)) {
                DB::rollBack();
                return back()->with('error', implode(' ', $import->errors));
            }

            DB::commit();
            return redirect()->route('admin.oquv-reja.show', $curriculum)
                ->with('success', "O'quv reja yuklandi: {$import->imported} ta fan qatori o'qib olindi.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("O'quv reja import xatolik: " . $e->getMessage());
            return back()->with('error', "Faylni o'qishda xatolik: " . $e->getMessage());
        }
    }

    public function show(ManualCurriculum $curriculum)
    {
        $curriculum->load(['subjects' => fn($q) => $q->orderBy('id')]);

        return view('admin.oquv-reja.show', compact('curriculum'));
    }

    public function destroy(ManualCurriculum $curriculum)
    {
        $curriculum->delete();
        return redirect()->route('admin.oquv-reja.index')->with('success', "O'quv reja o'chirildi.");
    }

    public function compare(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $working] = $this->resolvePair($request);
        $comparison = $service->compare($reference, $working);

        return view('admin.oquv-reja.compare', compact('reference', 'working', 'comparison'));
    }

    public function compareExport(Request $request, CurriculumComparisonService $service)
    {
        [$reference, $working] = $this->resolvePair($request);
        $comparison = $service->compare($reference, $working);

        $title = "{$reference->name} <-> {$working->name} solishtirma";
        $fileName = 'oquv-reja-solishtirma-' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new CurriculumComparisonExport($title, $comparison), $fileName);
    }

    private function resolvePair(Request $request): array
    {
        $request->validate([
            'reference_id' => 'required|exists:manual_curricula,id',
            'working_id' => 'required|exists:manual_curricula,id',
        ]);

        return [
            ManualCurriculum::findOrFail($request->reference_id),
            ManualCurriculum::findOrFail($request->working_id),
        ];
    }
}

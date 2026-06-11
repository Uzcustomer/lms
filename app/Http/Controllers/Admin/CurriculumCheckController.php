<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CurriculumComparisonExport;
use App\Http\Controllers\Controller;
use App\Imports\ManualCurriculumImport;
use App\Models\ManualCurriculum;
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

        return view('admin.oquv-reja.index', compact('curricula'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:namunaviy,ishchi',
            'name' => 'required|string|max:255',
            'specialty_code' => 'nullable|string|max:50',
            'specialty_name' => 'nullable|string|max:255',
            'plan_year' => 'nullable|string|max:20',
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $file = $request->file('file');

        DB::beginTransaction();
        try {
            $curriculum = ManualCurriculum::create([
                'type' => $request->type,
                'name' => $request->name,
                'specialty_code' => $request->specialty_code,
                'specialty_name' => $request->specialty_name,
                'plan_year' => $request->plan_year,
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

<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CurriculumComparisonExport;
use App\Exports\ManualCurriculumExport;
use App\Http\Controllers\Controller;
use App\Imports\ManualCurriculumImport;
use App\Models\Curriculum;
use App\Models\ManualCurriculum;
use App\Models\Semester;
use App\Models\Student;
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

        $educationTypes = Student::query()
            ->whereNotNull('education_type_code')
            ->select('education_type_code', 'education_type_name')
            ->distinct()
            ->orderBy('education_type_code')
            ->get();

        return view('admin.oquv-reja.index', compact('curricula', 'educationTypes'));
    }

    /**
     * Cascade tanlov ro'yxatlari TALABALAR jadvali asosida quriladi
     * (HEMIS'dagi semestr "current" flagi ishonchsiz bo'lgani uchun).
     * Active talaba = student_status_code 11. Har bir tanlov o'sha yo'nalishga
     * haqiqatan biriktirilgan talabalar bo'yicha aniqlanadi:
     * ta'lim turi -> fakultet -> yo'nalish -> kurs -> semestr -> o'quv reja
     */
    public function options(Request $request)
    {
        return response()->json(match ($request->input('list')) {
            'faculties' => $this->students($request)
                ->whereNotNull('department_id')
                ->select('department_id as id', 'department_name as name')
                ->distinct()
                ->orderBy('department_name')
                ->get(),

            // Yo'nalishlar alohida yozuv (specialty_id) bo'yicha ko'rsatiladi:
            // bir xil kodli dublikatlar ham alohida turadi, qaysi biriga
            // talaba biriktirilgani talaba soni orqali bilinadi
            'specialties' => $this->students($request)
                ->whereNotNull('specialty_id')
                ->selectRaw('specialty_id as id, specialty_code as code, specialty_name as name, count(*) as student_count')
                ->groupBy('specialty_id', 'specialty_code', 'specialty_name')
                ->orderBy('specialty_code')
                ->get(),

            'levels' => $this->students($request)
                ->whereNotNull('level_code')
                ->selectRaw('level_code, level_name, count(*) as student_count')
                ->groupBy('level_code', 'level_name')
                ->orderBy('level_code')
                ->get(),

            'semesters' => $this->students($request)
                ->whereNotNull('semester_code')
                ->selectRaw('semester_code as code, semester_name as name, count(*) as student_count')
                ->groupBy('semester_code', 'semester_name')
                ->orderBy('semester_code')
                ->get(),

            // Active talaba o'z joriy semestrida bo'ladi — kursdagi yagona
            // (yoki eng ko'p talabali) semestr avtomatik tanlash uchun
            'current' => $this->students($request)
                ->whereNotNull('level_code')
                ->selectRaw('level_code, level_name, semester_code as code, semester_name as name, count(*) as student_count')
                ->groupBy('level_code', 'level_name', 'semester_code', 'semester_name')
                ->orderByDesc('student_count')
                ->get(),

            // O'quv rejalar — shu kurs/semestrdagi talabalar biriktirilgan
            // rejalar. curricula jadvalida bor-yo'qligi alohida belgilanadi
            'curricula' => $this->curriculaForStudents($request),

            // Diagnostika: tanlangan fakultet bo'yicha xom ma'lumot
            'diagnose' => $this->diagnose($request),

            default => [],
        });
    }

    /**
     * Cascade va diagnostika uchun umumiy talaba so'rovi.
     * current_only yoqilganda faqat o'qiyotgan talabalar (status 11).
     */
    private function students(Request $request)
    {
        $query = Student::query();

        if ($request->boolean('current_only')) {
            $query->where('student_status_code', 11);
        }
        if ($request->filled('education_type_code')) {
            $query->where('education_type_code', $request->education_type_code);
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }
        if ($request->filled('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        } elseif ($request->filled('specialty_code')) {
            $query->where('specialty_code', $request->specialty_code);
        }
        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('semester_code', $request->semester_code);
        }

        return $query;
    }

    private function curriculaForStudents(Request $request)
    {
        $rows = $this->students($request)
            ->whereNotNull('curriculum_id')
            ->selectRaw('curriculum_id, count(*) as student_count')
            ->groupBy('curriculum_id')
            ->orderByDesc('student_count')
            ->get();

        $names = Curriculum::whereIn('curricula_hemis_id', $rows->pluck('curriculum_id'))
            ->pluck('name', 'curricula_hemis_id');

        return $rows->map(fn($row) => [
            'id' => $row->curriculum_id,
            'name' => $names[$row->curriculum_id] ?? null,
            'exists' => isset($names[$row->curriculum_id]),
            'student_count' => $row->student_count,
        ])->values();
    }

    /**
     * Tanlangan fakultet (va ixtiyoriy yo'nalish) bo'yicha to'liq xom kesim:
     * har bir yo'nalish-kurs-semestr-reja kombinatsiyasida nechta talaba bor,
     * reja curricula jadvalida bormi, semestri "current" deb belgilanganmi.
     * Bu nima uchun biror kurs/reja cascade'da ko'rinmayotganini ko'rsatadi.
     */
    private function diagnose(Request $request)
    {
        $rows = $this->students($request->merge(['current_only' => false]))
            ->where('student_status_code', '!=', 60)
            ->selectRaw('specialty_id, specialty_code, specialty_name, level_code, level_name,
                semester_code, semester_name, curriculum_id, student_status_code, count(*) as student_count')
            ->groupBy('specialty_id', 'specialty_code', 'specialty_name', 'level_code', 'level_name',
                'semester_code', 'semester_name', 'curriculum_id', 'student_status_code')
            ->orderBy('specialty_code')
            ->orderBy('level_code')
            ->orderBy('semester_code')
            ->get();

        $curriculumNames = Curriculum::whereIn('curricula_hemis_id', $rows->pluck('curriculum_id')->filter())
            ->pluck('name', 'curricula_hemis_id');

        // Reja semestri HEMIS'da "current" deb belgilanganmi
        $currentSemesters = Semester::whereIn('curriculum_hemis_id', $rows->pluck('curriculum_id')->filter())
            ->where('current', true)
            ->get(['curriculum_hemis_id', 'code'])
            ->groupBy('curriculum_hemis_id')
            ->map(fn($g) => $g->pluck('code')->all());

        return $rows->map(fn($row) => [
            'specialty' => trim(($row->specialty_code ?? '') . ' — ' . ($row->specialty_name ?? '')),
            'specialty_id' => $row->specialty_id,
            'level_name' => $row->level_name,
            'semester' => $row->semester_name ?: $row->semester_code,
            'student_count' => $row->student_count,
            'status_code' => $row->student_status_code,
            'curriculum_id' => $row->curriculum_id,
            'curriculum_name' => $row->curriculum_id
                ? ($curriculumNames[$row->curriculum_id] ?? "❌ curricula jadvalida YO'Q")
                : "❌ talabaga reja biriktirilmagan",
            'curriculum_exists' => $row->curriculum_id && isset($curriculumNames[$row->curriculum_id]),
            'semester_is_current' => $row->curriculum_id
                && in_array($row->semester_code, $currentSemesters[$row->curriculum_id] ?? []),
        ])->values();
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
        // Yo'nalish kodi/nomini shu rejaga biriktirilgan talabadan olamiz
        // (HEMIS specialty yozuvlari ba'zan dublikat/nomuvofiq bo'lgani uchun)
        $specialty = Student::where('curriculum_id', $hemisCurriculum->curricula_hemis_id)
            ->whereNotNull('specialty_code')
            ->select('specialty_code as code', 'specialty_name as name')
            ->first();

        $typeLabel = $request->type === 'namunaviy' ? 'namunaviy' : 'ishchi';
        $name = $hemisCurriculum->name . ' — ' . $typeLabel;
        if ($request->type === 'ishchi' && $request->filled('semester_code')) {
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

    public function export(Request $request, ManualCurriculum $curriculum)
    {
        $format = in_array($request->input('format'), ['jadval', 'setka']) ? $request->input('format') : 'jadval';
        $curriculum->load(['subjects' => fn($q) => $q->orderBy('id')]);
        $slug = \Illuminate\Support\Str::slug($curriculum->name);
        $fileName = "{$slug}-{$format}-" . now()->format('Y-m-d') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new ManualCurriculumExport($curriculum, $format), $fileName);
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

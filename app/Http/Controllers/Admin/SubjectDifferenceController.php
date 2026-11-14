<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubjectDifferenceCheck;
use App\Services\SubjectDifference\GradeScale;
use App\Services\SubjectDifference\PlanBuilder;
use App\Services\SubjectDifference\SubjectDifferenceExcel;
use App\Services\SubjectDifference\SubjectDifferenceService;
use App\Services\SubjectDifference\TranscriptReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Fanlar farqi — boshqa OTMdan ko'chib kelgan talabaning transkripti bizning
 * o'quv rejamiz (akkreditatsiyadagi fanlar) bilan solishtiriladi, fanlar farqi
 * va GPA hisoblanadi.
 *
 * Registrator ofisi yoki o'quv bo'limi transkript PDF'ini yuklaydi; PDF matnli
 * bo'lsa qoida bilan ham, AI bilan ham o'qiladi, skaner bo'lsa — AI (Gemini, vedomost
 * tekshirish sahifasidagi API kalitlari). Moslashtirish ham ikki usulda: qoida va AI —
 * natijalar yonma-yon ko'rsatiladi, xodim har qatorni qo'lda tuzata oladi.
 */
class SubjectDifferenceController extends Controller
{
    public function __construct(private SubjectDifferenceService $service)
    {
    }

    public function index(Request $request): View
    {
        $checks = SubjectDifferenceCheck::query()
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($s) => $s
                ->where('student_name', 'like', '%' . $request->search . '%')
                ->orWhere('source_institution', 'like', '%' . $request->search . '%')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.subject-differences.index', [
            'checks' => $checks,
            'aiConfigured' => TranscriptReader::aiConfigured(),
            'defaults' => GradeScale::DEFAULTS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'transcript' => 'required|file|mimes:pdf|max:30720',
            'extra' => 'nullable|array|max:5',
            'extra.*' => 'file|mimes:pdf|max:30720',
            'student_name' => 'nullable|string|max:255',
            'source_institution' => 'nullable|string|max:255',
            'scale_mode' => 'required|in:ball,grade',
            'scale_five' => 'nullable|numeric|min:0|max:100',
            'scale_four' => 'nullable|numeric|min:0|max:100',
            'scale_three' => 'nullable|numeric|min:0|max:100',
        ], [], [
            'transcript' => 'Transkript',
            'extra.*' => "Qo'shimcha fayl",
        ]);

        @set_time_limit(300);
        $files = array_merge([$request->file('transcript')], $request->file('extra', []));
        $check = $this->service->create(
            $files,
            ['mode' => $data['scale_mode'], 'five' => $data['scale_five'] ?? null, 'four' => $data['scale_four'] ?? null, 'three' => $data['scale_three'] ?? null],
            $data['student_name'] ?? null,
            $data['source_institution'] ?? null,
            auth()->user()
        );

        return redirect()->route('admin.subject-differences.show', $check)
            ->with($check->status === 'error' ? 'error' : 'success', $check->status === 'error'
                ? 'Transkript o\'qilmadi: ' . $check->error
                : 'Transkript o\'qildi. Endi o\'quv rejani va qaysi semestrgachaligini tanlang.');
    }

    public function show(SubjectDifferenceCheck $check): View
    {
        $levels = [];
        foreach ($check->levels ?? [] as $level) {
            $levels[] = ['level' => $level, 'result' => $this->service->evaluate($check, $level)];
        }

        return view('admin.subject-differences.show', [
            'check' => $check,
            'levels' => $levels,
            'scale' => GradeScale::fromArray($check->scale),
            'aiConfigured' => TranscriptReader::aiConfigured(),
            'suggestUpto' => $this->suggestUpto($levels),
            'sourceCompare' => $this->service->compareSources($check),
        ]);
    }

    public function curricula(Request $request, PlanBuilder $plans): JsonResponse
    {
        return response()->json($plans->curricula($request->input('q')));
    }

    public function addLevel(Request $request, SubjectDifferenceCheck $check): RedirectResponse
    {
        $data = $request->validate([
            'curriculum_id' => 'required|integer',
            'upto' => 'required|integer|min:1|max:14',
            'use_ai' => 'nullable|boolean',
        ]);
        abort_if($check->status === 'error' || !$check->transcriptRows(), 422, 'Avval transkript o\'qilishi kerak.');

        @set_time_limit(300);
        try {
            $this->service->addLevel($check, (int) $data['curriculum_id'], (int) $data['upto'], $request->boolean('use_ai', true));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.subject-differences.show', $check)->with('success', 'Solishtirildi.');
    }

    public function rematch(Request $request, SubjectDifferenceCheck $check, string $level): RedirectResponse
    {
        @set_time_limit(300);
        try {
            $this->service->rematch($check, $level, $request->boolean('use_ai', true));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Moslashtirish qayta bajarildi.');
    }

    public function saveMapping(Request $request, SubjectDifferenceCheck $check, string $level): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'map' => 'nullable|array',
            'excluded' => 'nullable|array',
        ]);
        try {
            $this->service->saveMapping($check, $level, $data['map'] ?? [], $data['excluded'] ?? []);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Tuzatishlar saqlandi, GPA qayta hisoblandi.');
    }

    public function deleteLevel(SubjectDifferenceCheck $check, string $level): RedirectResponse
    {
        $this->service->deleteLevel($check, $level);

        return back()->with('success', 'Bosqich o\'chirildi.');
    }

    public function setSource(Request $request, SubjectDifferenceCheck $check): RedirectResponse
    {
        try {
            $this->service->setSource($check, (string) $request->input('source'));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asosiy manba o\'zgartirildi. Mavjud bosqichlarni «Qayta moslashtirish» bilan yangilang.');
    }

    public function editRow(Request $request, SubjectDifferenceCheck $check): RedirectResponse
    {
        $data = $request->validate([
            'source' => 'required|in:ai,text',
            'index' => 'required|integer|min:0',
            'name' => 'nullable|string|max:500',
            'semester' => 'nullable|integer|min:1|max:14',
            'ball' => 'nullable|numeric|min:0|max:100',
            'grade' => 'nullable|integer|min:0|max:5',
        ]);
        try {
            $this->service->editTranscriptRow($check, $data['source'], (int) $data['index'], $request->only(['name', 'semester', 'ball', 'grade']));
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transkript qatori tuzatildi.');
    }

    public function reread(SubjectDifferenceCheck $check): RedirectResponse
    {
        @set_time_limit(300);
        $this->service->read($check);

        return back()->with($check->status === 'error' ? 'error' : 'success', $check->status === 'error'
            ? 'Transkript o\'qilmadi: ' . $check->error
            : 'Transkript qayta o\'qildi. Bosqichlarni «Qayta moslashtirish» bilan yangilang.');
    }

    public function update(Request $request, SubjectDifferenceCheck $check): RedirectResponse
    {
        $data = $request->validate([
            'student_name' => 'nullable|string|max:255',
            'source_institution' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
            'scale_mode' => 'nullable|in:ball,grade',
            'scale_five' => 'nullable|numeric|min:0|max:100',
            'scale_four' => 'nullable|numeric|min:0|max:100',
            'scale_three' => 'nullable|numeric|min:0|max:100',
        ]);
        $check->fill(array_intersect_key($data, array_flip(['student_name', 'source_institution', 'notes'])));
        if (!empty($data['scale_mode'])) {
            $check->scale = GradeScale::fromArray(['mode' => $data['scale_mode'], 'five' => $data['scale_five'] ?? null, 'four' => $data['scale_four'] ?? null, 'three' => $data['scale_three'] ?? null])->toArray();
        }
        $check->save();

        return back()->with('success', 'Saqlandi.');
    }

    public function excel(SubjectDifferenceCheck $check, string $level, SubjectDifferenceExcel $excel)
    {
        $lvl = collect($check->levels ?? [])->firstWhere('id', $level);
        abort_unless($lvl, 404);
        $result = $this->service->evaluate($check, $lvl);
        $name = preg_replace('/[^\p{L}\p{N}\-]+/u', '_', ($check->student_name ?: 'talaba') . '_' . $lvl['upto'] . '-semestrgacha');

        return $excel->download($check, $lvl, $result, "fanlar_farqi_{$name}.xlsx");
    }

    public function file(SubjectDifferenceCheck $check, int $index)
    {
        $f = ($check->files ?? [])[$index] ?? null;
        abort_unless($f && Storage::disk(SubjectDifferenceService::DISK)->exists($f['path']), 404);

        return response()->file(Storage::disk(SubjectDifferenceService::DISK)->path($f['path']), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($f['name']) . '"',
        ]);
    }

    public function destroy(SubjectDifferenceCheck $check): RedirectResponse
    {
        Storage::disk(SubjectDifferenceService::DISK)->deleteDirectory('subject-differences/' . $check->id);
        $check->delete();

        return redirect()->route('admin.subject-differences.index')->with('success', 'Tekshiruv o\'chirildi.');
    }

    /** Oxirgi bosqich o'tmagan bo'lsa — keyingi tekshiriladigan semestr. */
    private function suggestUpto(array $levels): ?int
    {
        $last = end($levels);
        if (!$last) {
            return null;
        }

        return $last['result']['decision']['next_upto'] ?? null;
    }
}

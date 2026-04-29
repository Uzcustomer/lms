<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplicationWindow;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeWindowService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetakeWindowController extends Controller
{
    public function __construct(
        private RetakeWindowService $windowService,
    ) {}

    public function index()
    {
        $this->authorizeAccess();

        $windows = RetakeApplicationWindow::query()
            ->withCount('applicationGroups as applications_count')
            ->orderByDesc('start_date')
            ->paginate(30);

        // Form uchun ma'lumotlar
        $specialties = Specialty::orderBy('name')->get(['id', 'specialty_hemis_id', 'name']);
        $levels = $this->levels();
        $semesters = Semester::orderByDesc('education_year')->orderBy('code')
            ->get(['id', 'code', 'name', 'level_code', 'education_year']);

        return view('teacher.academic-dept.retake-windows.index', [
            'windows' => $windows,
            'specialties' => $specialties,
            'levels' => $levels,
            'semesters' => $semesters,
            'canOverride' => $this->canOverride(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'specialty_id' => 'required|integer',
            'specialty_name' => 'required|string|max:255',
            'level_code' => 'required|string|max:10',
            'level_name' => 'nullable|string|max:100',
            'semester_code' => 'required|string|max:50',
            'semester_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        try {
            /** @var Teacher $user */
            $user = Auth::guard('teacher')->user();
            $this->windowService->createWindow($data, $user);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('teacher.retake-windows.index')
            ->with('success', __('Qabul oynasi muvaffaqiyatli yaratildi'));
    }

    /**
     * Super-admin sanalarni override qilish.
     */
    public function overrideDates(Request $request, int $windowId): RedirectResponse
    {
        if (!$this->canOverride()) {
            abort(403, 'Faqat super-admin sanalarni o\'zgartira oladi');
        }

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $window = RetakeApplicationWindow::findOrFail($windowId);

        try {
            $this->windowService->overrideDates($window, $data['start_date'], $data['end_date']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __('Sanalar override qilindi'));
    }

    public function destroy(int $windowId): RedirectResponse
    {
        if (!$this->canOverride()) {
            abort(403, 'Faqat super-admin oynani o\'chira oladi');
        }

        $window = RetakeApplicationWindow::findOrFail($windowId);

        // Arizalar yuborilgan bo'lsa, o'chirib bo'lmaydi
        if ($window->applicationGroups()->exists()) {
            return redirect()->back()->withErrors([
                'window' => 'Bu oynaga arizalar yuborilgan, o\'chirib bo\'lmaydi',
            ]);
        }

        $window->delete();

        return redirect()->route('teacher.retake-windows.index')
            ->with('success', __('Oyna o\'chirildi'));
    }

    // ──────────────────────────────────────────────────────────────────

    private function authorizeAccess(): void
    {
        $user = Auth::guard('teacher')->user();
        if (!RetakeAccess::canManageAcademicDept($user)) {
            abort(403, 'Sizda qayta o\'qish oynalarini boshqarish ruxsati yo\'q');
        }
    }

    private function canOverride(): bool
    {
        return RetakeAccess::canOverride(Auth::guard('teacher')->user());
    }

    /**
     * Loyihada ishlatiladigan kurs (level_code) qiymatlari.
     * 11-16 — bakalavriat 1-6 kurslar; HEMIS standartiga muvofiq.
     */
    private function levels(): array
    {
        return [
            ['code' => '11', 'name' => '1-kurs'],
            ['code' => '12', 'name' => '2-kurs'],
            ['code' => '13', 'name' => '3-kurs'],
            ['code' => '14', 'name' => '4-kurs'],
            ['code' => '15', 'name' => '5-kurs'],
            ['code' => '16', 'name' => '6-kurs'],
        ];
    }
}

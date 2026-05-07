<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RetakeApplicationWindow;
use App\Models\RetakeWindowSession;
use App\Models\Specialty;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeFacultyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RetakeWindowSessionController extends Controller
{
    /**
     * Sessiyalar ro'yxati.
     */
    public function index()
    {
        $this->authorizeAccess();

        $sessions = RetakeWindowSession::query()
            ->withCount('windows')
            ->orderByDesc('is_closed')
            ->orderByDesc('created_at')
            ->orderBy('is_closed') // ochiqlar yuqorida
            ->get()
            ->sortBy(fn ($s) => [$s->is_closed ? 1 : 0, -$s->id])
            ->values();

        // Har sessiya uchun "o'chirib bo'ladimi?" — bitta join + distinct
        // orqali — eager-load + per-session loop o'rniga.
        $sessionsWithApps = [];
        if ($sessions->isNotEmpty()) {
            $sessionsWithApps = \Illuminate\Support\Facades\DB::table('retake_application_groups')
                ->join('retake_application_windows', 'retake_application_windows.id', '=', 'retake_application_groups.window_id')
                ->whereIn('retake_application_windows.session_id', $sessions->pluck('id'))
                ->whereNull('retake_application_windows.deleted_at')
                ->distinct()
                ->pluck('retake_application_windows.session_id')
                ->all();
        }

        return view('teacher.academic-dept.retake-sessions.index', [
            'sessions' => $sessions,
            'sessionsWithApps' => $sessionsWithApps,
        ]);
    }

    /**
     * Yangi sessiya yaratish.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        /** @var Teacher $user */
        $user = RetakeAccess::currentStaff();

        RetakeWindowSession::create([
            'name' => $data['name'],
            'created_by_user_id' => $user->id,
            'created_by_name' => $user->full_name,
        ]);

        return redirect()->route('admin.retake-sessions.index')
            ->with('success', __('Sessiya yaratildi'));
    }

    /**
     * Sessiyani yopish — ichidagi pending arizalar avtomatik rad etilmaydi
     * (alohida cron komandasi tomonidan bajariladi).
     */
    public function close(int $sessionId): RedirectResponse
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::findOrFail($sessionId);

        if ($session->is_closed) {
            return redirect()->back()->withErrors(['session' => 'Sessiya allaqachon yopilgan']);
        }

        $session->update([
            'is_closed' => true,
            'closed_at' => now(),
        ]);

        return redirect()->route('admin.retake-sessions.index')
            ->with('success', __('Sessiya yopildi'));
    }

    /**
     * Sessiyani arxivga ko'chirish (soft delete) — faqat ariza yuborilmagan
     * bo'lsa. Sessiya bilan birga uning oynalari ham arxivga ko'chadi.
     */
    public function destroy(int $sessionId): RedirectResponse
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::with('windows')->findOrFail($sessionId);

        if ($this->sessionHasApplications($session)) {
            return redirect()->back()->withErrors([
                'session' => 'Bu sessiyada arizalar mavjud, o\'chirib bo\'lmaydi',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($session) {
            $session->windows()->delete();  // soft delete kaskadi yo'q — qo'lda
            $session->delete();
        });

        return redirect()->route('admin.retake-sessions.index')
            ->with('success', __('Sessiya arxivga ko\'chirildi'));
    }

    /**
     * Tanlangan sessiyalarni ommaviy arxivga ko'chirish.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'session_ids' => 'required|array|min:1',
            'session_ids.*' => 'integer',
        ]);

        $sessions = RetakeWindowSession::with('windows')
            ->whereIn('id', $data['session_ids'])
            ->get();

        $deleted = 0;
        $skipped = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($sessions, &$deleted, &$skipped) {
            foreach ($sessions as $session) {
                if ($this->sessionHasApplications($session)) {
                    $skipped++;
                    continue;
                }
                $session->windows()->delete();
                $session->delete();
                $deleted++;
            }
        });

        $msg = "{$deleted} ta sessiya arxivga ko'chirildi";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta sessiyada arizalar mavjudligi sababli o'tkazib yuborildi";
        }

        return redirect()->route('admin.retake-sessions.index')->with('success', $msg);
    }

    /**
     * Tarix (arxiv) sahifasi — o'chirilgan sessiyalar.
     */
    public function trashed()
    {
        $this->authorizeAccess();

        $sessions = RetakeWindowSession::onlyTrashed()
            ->withCount(['windows' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('deleted_at')
            ->get();

        // Eslatma: window_count yuqorida `withTrashed` bilan chunki sessiya
        // arxivlanganda windowlar ham soft-delete bo'ladi.

        return view('teacher.academic-dept.retake-sessions.trashed', [
            'sessions' => $sessions,
            'canForceDelete' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
        ]);
    }

    /**
     * Arxivdan tiklash.
     */
    public function restore(int $sessionId): RedirectResponse
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::onlyTrashed()->findOrFail($sessionId);
        \Illuminate\Support\Facades\DB::transaction(function () use ($session) {
            // Tegishli oynalarni ham qaytaramiz
            \App\Models\RetakeApplicationWindow::onlyTrashed()
                ->where('session_id', $session->id)
                ->restore();
            $session->restore();
        });

        return redirect()->route('admin.retake-sessions.trashed')
            ->with('success', __('Sessiya tiklandi'));
    }

    /**
     * Arxivdan butunlay o'chirish (faqat super-admin).
     */
    public function forceDestroy(int $sessionId): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403);
        }

        $session = RetakeWindowSession::onlyTrashed()->findOrFail($sessionId);

        \Illuminate\Support\Facades\DB::transaction(function () use ($session) {
            \App\Models\RetakeApplicationWindow::onlyTrashed()
                ->where('session_id', $session->id)
                ->forceDelete();
            $session->forceDelete();
        });

        return redirect()->route('admin.retake-sessions.trashed')
            ->with('success', __('Sessiya butunlay o\'chirildi'));
    }

    /**
     * Tanlangan sessiyalarni butunlay o'chirish (faqat super-admin).
     * Tarixda qolmaydi.
     */
    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403);
        }

        $data = $request->validate([
            'session_ids' => 'required|array|min:1',
            'session_ids.*' => 'integer',
        ]);

        $deleted = 0;
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, &$deleted) {
            $sessions = RetakeWindowSession::withTrashed()
                ->whereIn('id', $data['session_ids'])
                ->get();
            foreach ($sessions as $session) {
                \App\Models\RetakeApplicationWindow::withTrashed()
                    ->where('session_id', $session->id)
                    ->each(fn ($w) => $w->forceDelete());
                $session->forceDelete();
                $deleted++;
            }
        });

        return redirect()->back()->with('success', "{$deleted} ta sessiya butunlay o'chirildi");
    }

    private function sessionHasApplications(RetakeWindowSession $session): bool
    {
        $windowIds = $session->windows->pluck('id');
        if ($windowIds->isEmpty()) {
            return false;
        }
        return \App\Models\RetakeApplicationGroup::query()
            ->whereIn('window_id', $windowIds)
            ->exists();
    }

    /**
     * Sessiya ichidagi oynalar — bu yerda yangi oyna ham yaratiladi.
     */
    public function show(int $sessionId, Request $request)
    {
        $this->authorizeAccess();

        $session = RetakeWindowSession::findOrFail($sessionId);

        $statusFilter = $request->input('status', 'all');
        $departmentId = $request->input('department');
        $specialtyId = $request->input('specialty');
        $levelCode = $request->input('level_code');
        $semesterCode = $request->input('semester_code');
        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }

        $windowsQuery = RetakeApplicationWindow::query()
            ->where('session_id', $session->id)
            ->withCount('applicationGroups as applications_count')
            ->orderBy('created_at'); // ochilish vaqti tartibida (eng oldin ochilgan birinchi)

        if ($statusFilter !== 'all') {
            $today = now()->toDateString();
            match ($statusFilter) {
                'active' => $windowsQuery->whereDate('start_date', '>', $today),
                'study' => $windowsQuery->whereDate('start_date', '<=', $today)
                    ->whereDate('end_date', '>=', $today),
                'closed' => $windowsQuery->whereDate('end_date', '<', $today),
                default => null,
            };
        }

        if ($departmentId) {
            $specialtyIdsForDept = Specialty::where('department_hemis_id', $departmentId)
                ->pluck('specialty_hemis_id');
            $windowsQuery->where(function ($q) use ($departmentId, $specialtyIdsForDept) {
                $q->where('department_hemis_id', $departmentId)
                  ->orWhere(function ($q2) use ($departmentId, $specialtyIdsForDept) {
                      $q2->where(function ($q3) {
                          $q3->whereNull('department_hemis_id')->orWhere('department_hemis_id', '');
                      })->whereIn('specialty_id', $specialtyIdsForDept);
                  });
            });
        }

        if ($specialtyId) {
            $windowsQuery->where('specialty_id', $specialtyId);
        }

        if ($levelCode) {
            $windowsQuery->where('level_code', $levelCode);
        }

        if ($semesterCode) {
            $windowsQuery->where('semester_code', $semesterCode);
        }

        $windows = $windowsQuery->paginate($perPage)->withQueryString();

        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();

        // Bevosita department_hemis_id'dan fakultet nomi (window'da saqlangan).
        // Faqat structure_type_code = 11 (fakultet) bo'yicha qidiriladi shunda
        // bir xil hemis_id'li kafedra bilan to'qnashish bo'lmasin.
        $deptIdToName = Department::where('structure_type_code', 11)
            ->pluck('name', 'department_hemis_id');

        // Eski view va batch fallback uchun legacy lookup (specialty_hemis_id -> faculty_name)
        $specialtyToFaculty = Specialty::query()
            ->join('departments', 'departments.department_hemis_id', '=', 'specialties.department_hemis_id')
            ->where('departments.structure_type_code', 11)
            ->select('specialties.specialty_hemis_id as sp_hemis_id', 'departments.name as faculty_name')
            ->pluck('faculty_name', 'sp_hemis_id');

        // specialty_hemis_id -> [department_hemis_id, ...] (bir nechta bo'lishi mumkin)
        $specialtyDeptOptions = Specialty::query()
            ->select('specialty_hemis_id', 'department_hemis_id')
            ->whereIn('specialty_hemis_id', $windows->pluck('specialty_id')->filter()->unique())
            ->get()
            ->groupBy('specialty_hemis_id')
            ->map(fn ($rows) => $rows->pluck('department_hemis_id')->filter()->unique()->values()->all());

        // Real talabalar bo'yicha (specialty, level) — fakultet xaritasi
        $resolvedDeptByWindow = RetakeFacultyResolver::resolveFacultiesForWindows($windows);

        $resolveFaculty = function ($w) use ($deptIdToName, $specialtyDeptOptions, $resolvedDeptByWindow) {
            // 1) Eng ishonchli — Student jadvalidan kelgan haqiqiy fakultet
            $resolved = $resolvedDeptByWindow[$w->id] ?? null;
            if ($resolved && $deptIdToName->has($resolved)) {
                return $deptIdToName[$resolved];
            }
            // 2) Window'da saqlangan department_hemis_id
            if (!empty($w->department_hemis_id) && $deptIdToName->has($w->department_hemis_id)) {
                return $deptIdToName[$w->department_hemis_id];
            }
            // 3) Eski yozuvlar — specialty_hemis_id orqali (faqat 1 ta variant bo'lsa)
            $opts = $specialtyDeptOptions->get($w->specialty_id, []);
            if (count($opts) === 1) {
                return $deptIdToName[$opts[0]] ?? null;
            }
            return null;
        };

        // Batch'dagi qo'shni fakultetlar
        $batchIds = $windows->pluck('creation_batch_id')->filter()->unique()->values();
        $batchFaculties = collect();
        if ($batchIds->isNotEmpty()) {
            $batchSiblings = \App\Models\RetakeApplicationWindow::query()
                ->whereIn('creation_batch_id', $batchIds)
                ->get(['id', 'creation_batch_id', 'specialty_id', 'department_hemis_id']);
            $batchFaculties = $batchSiblings->groupBy('creation_batch_id')
                ->map(function ($siblings) use ($resolveFaculty) {
                    return $siblings->map(fn ($s) => $resolveFaculty($s))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();
                });
        }

        $departments = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);
        $specialties = Specialty::orderBy('name')
            ->get(['id', 'specialty_hemis_id', 'name', 'department_hemis_id']);

        // Real (fakultet, yo'nalish) juftliklari — talabalar mavjudligi asosida.
        // Shu bilan formada faqat aslida talabasi bor kombinatsiyalar ko'rsatiladi
        // va oynaning fakulteti foydalanuvchi tanlovi bo'yicha saqlanadi.
        $facultySpecialtyPairs = RetakeFacultyResolver::studentFacultySpecialtyPairs();

        // Har bir qatorga to'g'ri fakultet nomi map qilamiz
        $rowFaculties = collect();
        foreach ($windows as $w) {
            $rowFaculties[$w->id] = $resolveFaculty($w);
        }

        return view('teacher.academic-dept.retake-sessions.show', [
            'session' => $session,
            'windows' => $windows,
            'rowFaculties' => $rowFaculties,
            'specialtyToFaculty' => $specialtyToFaculty,
            'batchFaculties' => $batchFaculties,
            'departments' => $departments,
            'specialties' => $specialties,
            'facultySpecialtyPairs' => $facultySpecialtyPairs,
            'levels' => $this->levels(),
            'semesters' => $this->semesters(),
            'statusFilter' => $statusFilter,
            'departmentIdFilter' => $departmentId,
            'levelCodeFilter' => $levelCode,
            'canOverride' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
            'educationTypes' => $educationTypes,
        ]);
    }

    private function authorizeAccess(): void
    {
        if (!RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda qayta o\'qish sessiyalarini boshqarish ruxsati yo\'q');
        }
    }

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

    private function semesters(): array
    {
        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $items[] = ['code' => (string) $i, 'name' => $i . '-semestr'];
        }
        return $items;
    }
}

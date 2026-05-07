<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\Department;
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

    public function index(Request $request)
    {
        $this->authorizeAccess();

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
            ->with('session')
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
            $windowsQuery->whereIn('specialty_id', $specialtyIdsForDept);
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

        // Bevosita department_hemis_id'dan fakultet nomi
        $deptIdToName = Department::pluck('name', 'department_hemis_id');
        $specialtyToFaculty = Specialty::query()
            ->join('departments', 'departments.department_hemis_id', '=', 'specialties.department_hemis_id')
            ->select('specialties.specialty_hemis_id as sp_hemis_id', 'departments.name as faculty_name')
            ->pluck('faculty_name', 'sp_hemis_id');

        $resolveFaculty = function ($w) use ($deptIdToName, $specialtyToFaculty) {
            if (!empty($w->department_hemis_id) && $deptIdToName->has($w->department_hemis_id)) {
                return $deptIdToName[$w->department_hemis_id];
            }
            return $specialtyToFaculty[$w->specialty_id] ?? null;
        };

        $rowFaculties = collect();
        foreach ($windows as $w) {
            $rowFaculties[$w->id] = $resolveFaculty($w);
        }

        // Bulk batch'dagi qo'shni fakultetlar
        $batchIds = $windows->pluck('creation_batch_id')->filter()->unique()->values();
        $batchFaculties = collect();
        if ($batchIds->isNotEmpty()) {
            $batchSiblings = RetakeApplicationWindow::query()
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

        // Form uchun ma'lumotlar — faqat fakultetlar (structure_type_code = 11)
        $departments = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);
        $specialties = Specialty::orderBy('name')
            ->get(['id', 'specialty_hemis_id', 'name', 'department_hemis_id']);
        $levels = $this->levels();
        // Faqat 12 ta unikal semestr (1-semestr ... 12-semestr)
        $semesters = $this->semesters();

        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();

        // Hozir ochiq (faol) oynalar — sahifaning yuqorisida ko'rsatish uchun
        $today = now()->toDateString();
        $activeWindows = RetakeApplicationWindow::query()
            ->with('session')
            ->withCount('applicationGroups as applications_count')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('session', fn ($q) => $q->where('is_closed', false))
            ->orderByDesc('start_date')
            ->get();

        return view('teacher.academic-dept.retake-windows.index', [
            'windows' => $windows,
            'activeWindows' => $activeWindows,
            'specialtyToFaculty' => $specialtyToFaculty,
            'rowFaculties' => $rowFaculties,
            'batchFaculties' => $batchFaculties,
            'departments' => $departments,
            'specialties' => $specialties,
            'levels' => $levels,
            'semesters' => $semesters,
            'statusFilter' => $statusFilter,
            'departmentIdFilter' => $departmentId,
            'levelCodeFilter' => $levelCode,
            'canOverride' => $this->canOverride(),
            'educationTypes' => $educationTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'session_id' => 'required|integer|exists:retake_window_sessions,id',
            'specialty_ids' => 'required|array|min:1',
            'specialty_ids.*' => 'integer',
            'level_codes' => 'required|array|min:1',
            'level_codes.*' => 'string|max:10',
            'semester_codes' => 'nullable|array',
            'semester_codes.*' => 'string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $session = \App\Models\RetakeWindowSession::findOrFail($data['session_id']);
        if ($session->is_closed) {
            return redirect()->back()->withErrors([
                'session_id' => 'Yopilgan sessiyaga oyna qo\'shib bo\'lmaydi',
            ])->withInput();
        }

        // Tanlangan yo'nalishlar va kurslarni nomlari bilan birga olish
        $specialties = Specialty::whereIn('specialty_hemis_id', $data['specialty_ids'])
            ->get(['specialty_hemis_id', 'name', 'department_hemis_id'])
            ->keyBy('specialty_hemis_id');

        $allLevels = config('app.retake_levels')
            ?? collect(Semester::query()
                ->whereNotNull('level_code')
                ->select('level_code', 'level_name')
                ->distinct()
                ->get())
                ->map(fn ($s) => ['code' => $s->level_code, 'name' => $s->level_name])
                ->all();

        $levelMap = collect($allLevels)->keyBy(fn ($lv) => is_array($lv) ? $lv['code'] : $lv->code)
            ->map(fn ($lv) => is_array($lv) ? $lv['name'] : $lv->name);

        // Xalqaro fakultet bormi — semester majburligini tekshirish
        $depHemisIds = $specialties->pluck('department_hemis_id')->unique()->toArray();
        $departments = Department::whereIn('department_hemis_id', $depHemisIds)
            ->pluck('name', 'department_hemis_id');

        $hasXalqaro = $departments->contains(fn ($n) => preg_match('/xalqaro/i', (string) $n));
        if ($hasXalqaro && empty($data['semester_codes'])) {
            return redirect()->back()->withErrors([
                'semester_codes' => "Xalqaro talim fakulteti uchun kamida bitta semestr tanlang",
            ])->withInput();
        }

        $semesterMap = collect();
        if (!empty($data['semester_codes'])) {
            $semesterMap = Semester::whereIn('code', $data['semester_codes'])
                ->pluck('name', 'code');
        }
        // Agar xalqaro yo'q bo'lsa, bitta bo'sh semester combo bilan iteratsiya qilamiz
        $semesterCombos = $hasXalqaro
            ? collect($data['semester_codes'])->map(fn ($c) => ['code' => $c, 'name' => $semesterMap->get($c) ?? $c])->all()
            : [['code' => '', 'name' => '']];

        /** @var Teacher $user */
        $user = RetakeAccess::currentStaff();
        $created = 0;
        $skipped = 0;
        $firstError = null;

        // Bulk operatsiya uchun bitta umumiy batch ID — natija jadvalida shu
        // batch ostidagi barcha fakultetlarni ko'rsatish uchun ishlatiladi.
        $batchId = (string) \Illuminate\Support\Str::uuid();

        DB::transaction(function () use ($data, $specialties, $levelMap, $departments, $semesterCombos, $user, &$created, &$skipped, &$firstError, $hasXalqaro, $batchId) {
            foreach ($specialties as $sp) {
                $depName = (string) ($departments[$sp->department_hemis_id] ?? '');
                $isSpecXalqaro = preg_match('/xalqaro/i', $depName) === 1;
                // Xalqaro bo'lmagan yo'nalishlar uchun semester='' qo'llanadi
                $combos = ($hasXalqaro && $isSpecXalqaro) ? $semesterCombos : [['code' => '', 'name' => '']];

                foreach ($data['level_codes'] as $lvCode) {
                    foreach ($combos as $sem) {
                        try {
                            $this->windowService->createWindow([
                                'session_id' => $data['session_id'],
                                'specialty_id' => (int) $sp->specialty_hemis_id,
                                'specialty_name' => $sp->name,
                                'department_hemis_id' => (string) $sp->department_hemis_id,
                                'level_code' => $lvCode,
                                'level_name' => $levelMap->get($lvCode) ?? $lvCode,
                                'semester_code' => $sem['code'],
                                'semester_name' => $sem['name'],
                                'start_date' => $data['start_date'],
                                'end_date' => $data['end_date'],
                                'creation_batch_id' => $batchId,
                            ], $user);
                            $created++;
                        } catch (ValidationException $e) {
                            $skipped++;
                            $firstError = $firstError ?? collect($e->errors())->flatten()->first();
                        }
                    }
                }
            }
        });

        $msg = "{$created} ta qabul oynasi yaratildi";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta o'tkazib yuborildi (allaqachon mavjud)";
        }

        return redirect()->route('admin.retake-sessions.show', $data['session_id'])
            ->with('success', $msg);
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

        return redirect()->route('admin.retake-windows.index')
            ->with('success', __('Oyna o\'chirildi'));
    }

    /**
     * Bitta oynaning to'liq tarixi: barcha arizalar, statistikasi,
     * kim qaysi guruhga biriktirildi, kim rad etildi.
     */
    public function show(int $windowId)
    {
        $this->authorizeAccess();

        $window = RetakeApplicationWindow::with('session')->findOrFail($windowId);

        // Bu oynadagi barcha ariza-guruhlar va ulardagi arizalar
        $applicationGroups = \App\Models\RetakeApplicationGroup::query()
            ->where('window_id', $window->id)
            ->with([
                'student',
                'applications' => fn ($q) => $q->orderBy('subject_name'),
                'applications.deanUser',
                'applications.registrarUser',
                'applications.academicDeptUser',
                'applications.retakeGroup.teacher',
            ])
            ->orderByDesc('created_at')
            ->get();

        $allApplications = $applicationGroups->flatMap->applications;

        $stats = [
            'students' => $applicationGroups->count(),
            'applications' => $allApplications->count(),
            'approved' => $allApplications->where('final_status', 'approved')->count(),
            'rejected' => $allApplications->where('final_status', 'rejected')->count(),
            'pending' => $allApplications->where('final_status', 'pending')->count(),
        ];

        // Arizalarni RetakeGroup (o'qitish guruhi) bo'yicha guruhlash
        $byRetakeGroup = $allApplications
            ->where('final_status', 'approved')
            ->whereNotNull('retake_group_id')
            ->groupBy('retake_group_id')
            ->map(function ($apps) {
                $first = $apps->first();
                return [
                    'group' => $first->retakeGroup,
                    'applications' => $apps->values(),
                ];
            })
            ->values();

        // Tasdiqlangan, ammo guruh biriktirilmagan
        $approvedNoGroup = $allApplications
            ->where('final_status', 'approved')
            ->whereNull('retake_group_id')
            ->values();

        $rejected = $allApplications
            ->where('final_status', 'rejected')
            ->values();

        $pending = $allApplications
            ->where('final_status', 'pending')
            ->values();

        return view('teacher.academic-dept.retake-windows.show', [
            'window' => $window,
            'stats' => $stats,
            'applicationGroups' => $applicationGroups,
            'byRetakeGroup' => $byRetakeGroup,
            'approvedNoGroup' => $approvedNoGroup,
            'rejected' => $rejected,
            'pending' => $pending,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────

    private function authorizeAccess(): void
    {
        $user = RetakeAccess::currentStaff();
        if (!RetakeAccess::canManageAcademicDept($user)) {
            abort(403, 'Sizda qayta o\'qish oynalarini boshqarish ruxsati yo\'q');
        }
    }

    private function canOverride(): bool
    {
        return RetakeAccess::canOverride(RetakeAccess::currentStaff());
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

    /**
     * 12 ta unikal semestr (curriculum/yil bo'yicha takrorlanmasdan).
     * code: '1', '2', ..., '12' va name: '1-semestr' ... '12-semestr'.
     */
    private function semesters(): array
    {
        $items = [];
        for ($i = 1; $i <= 12; $i++) {
            $items[] = ['code' => (string) $i, 'name' => $i . '-semestr'];
        }
        return $items;
    }
}

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
        $this->authorizeView();

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

        $specialtyDeptOptions = Specialty::query()
            ->select('specialty_hemis_id', 'department_hemis_id')
            ->whereIn('specialty_hemis_id', $windows->pluck('specialty_id')->filter()->unique())
            ->get()
            ->groupBy('specialty_hemis_id')
            ->map(fn ($rows) => $rows->pluck('department_hemis_id')->filter()->unique()->values()->all());

        $resolveFaculty = function ($w) use ($deptIdToName, $specialtyDeptOptions) {
            if (!empty($w->department_hemis_id) && $deptIdToName->has($w->department_hemis_id)) {
                return $deptIdToName[$w->department_hemis_id];
            }
            $opts = $specialtyDeptOptions->get($w->specialty_id, []);
            if (count($opts) === 1) {
                return $deptIdToName[$opts[0]] ?? null;
            }
            return null;
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

        return view('teacher.academic-dept.retake-windows.index', [
            'windows' => $windows,
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
            'canManage' => $this->canManage(),
            'educationTypes' => $educationTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'session_id' => 'required|integer|exists:retake_window_sessions,id',
            // Yangi format: "fid|specialty_pk|level_code" triplet'lari.
            // Har triplet bitta (fakultet, yo'nalish, kurs) kombinatsiyasi.
            'assignments' => 'nullable|array|min:1',
            'assignments.*' => ['string', 'regex:/^\d+\|\d+\|[A-Za-z0-9_-]+$/'],
            // Eski format (orqaga moslik): yo'nalishlar va kurslar yassi ro'yxat.
            'specialty_pks' => 'nullable|array',
            'specialty_pks.*' => 'integer',
            'level_codes' => 'nullable|array',
            'level_codes.*' => 'string|max:10',
            'semester_codes' => 'nullable|array',
            'semester_codes.*' => 'string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if (empty($data['assignments']) && empty($data['specialty_pks'])) {
            return redirect()->back()->withErrors([
                'assignments' => 'Kamida bitta fakultet, yo\'nalish va kurs tanlang',
            ])->withInput();
        }

        $session = \App\Models\RetakeWindowSession::findOrFail($data['session_id']);
        if ($session->is_closed) {
            return redirect()->back()->withErrors([
                'session_id' => 'Yopilgan sessiyaga oyna qo\'shib bo\'lmaydi',
            ])->withInput();
        }

        // (fid, specialty_pk, level_code) triplet'larni normalizatsiya.
        // Eski format kelgan bo'lsa, har spec.dept × har level → triplet.
        $triplets = [];
        if (!empty($data['assignments'])) {
            foreach ($data['assignments'] as $raw) {
                [$fid, $spPk, $lvCode] = explode('|', $raw, 3);
                $triplets[] = [
                    'fid' => (string) $fid,
                    'specialty_pk' => (int) $spPk,
                    'level_code' => (string) $lvCode,
                ];
            }
        } else {
            if (empty($data['level_codes'])) {
                return redirect()->back()->withErrors([
                    'level_codes' => 'Kursni tanlang',
                ])->withInput();
            }
            $legacySpecs = Specialty::whereIn('id', $data['specialty_pks'])
                ->get(['id', 'department_hemis_id']);
            foreach ($legacySpecs as $sp) {
                foreach ($data['level_codes'] as $lvCode) {
                    $triplets[] = [
                        'fid' => (string) $sp->department_hemis_id,
                        'specialty_pk' => (int) $sp->id,
                        'level_code' => (string) $lvCode,
                    ];
                }
            }
        }

        if (empty($triplets)) {
            return redirect()->back()->withErrors([
                'assignments' => 'Kombinatsiyalar bo\'sh',
            ])->withInput();
        }

        // Yo'nalishlar va fakultet nomlarini bir martagina yuklab olamiz.
        $specPks = collect($triplets)->pluck('specialty_pk')->unique()->values()->all();
        $specialties = Specialty::whereIn('id', $specPks)
            ->get(['id', 'specialty_hemis_id', 'name', 'department_hemis_id'])
            ->keyBy('id');

        $depHemisIds = collect($triplets)->pluck('fid')->unique()->values()->all();
        $departments = Department::whereIn('department_hemis_id', $depHemisIds)
            ->pluck('name', 'department_hemis_id');

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

        // Xalqaro fakultet ishtirok etganmi — semester majburligi
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

        /** @var Teacher $user */
        $user = RetakeAccess::currentStaff();
        $created = 0;
        $skipped = 0;
        $firstError = null;
        $createdWindowIds = [];

        // Bulk operatsiya uchun bitta umumiy batch ID
        $batchId = (string) \Illuminate\Support\Str::uuid();

        DB::transaction(function () use ($data, $triplets, $specialties, $levelMap, $departments, $semesterMap, $user, &$created, &$skipped, &$firstError, &$createdWindowIds, $batchId) {
            foreach ($triplets as $t) {
                $sp = $specialties->get($t['specialty_pk']);
                if (!$sp) {
                    continue;
                }
                $depName = (string) ($departments[$t['fid']] ?? '');
                $isXalqaro = preg_match('/xalqaro/i', $depName) === 1;

                // Xalqaro fakultet uchun har semestr alohida oyna; aks holda — bitta bo'sh semester
                $combos = ($isXalqaro && !empty($data['semester_codes']))
                    ? collect($data['semester_codes'])
                        ->map(fn ($c) => ['code' => $c, 'name' => $semesterMap->get($c) ?? $c])
                        ->all()
                    : [['code' => '', 'name' => '']];

                foreach ($combos as $sem) {
                    try {
                        $newWindow = $this->windowService->createWindow([
                            'session_id' => $data['session_id'],
                            'specialty_id' => (int) $sp->specialty_hemis_id,
                            'specialty_name' => $sp->name,
                            'department_hemis_id' => (string) $t['fid'],
                            'level_code' => $t['level_code'],
                            'level_name' => $levelMap->get($t['level_code']) ?? $t['level_code'],
                            'semester_code' => $sem['code'],
                            'semester_name' => $sem['name'],
                            'start_date' => $data['start_date'],
                            'end_date' => $data['end_date'],
                            'creation_batch_id' => $batchId,
                        ], $user);
                        $created++;
                        $createdWindowIds[] = $newWindow->id;
                    } catch (ValidationException $e) {
                        $skipped++;
                        $firstError = $firstError ?? collect($e->errors())->flatten()->first();
                    }
                }
            }
        });

        // Avtomatik Telegram xabar: yangi yaratilgan har oyna uchun mos talabalarga
        $notifiedCount = 0;
        if (!empty($createdWindowIds)) {
            $notifier = app(\App\Services\Retake\RetakeNotificationService::class);
            foreach (RetakeApplicationWindow::whereIn('id', $createdWindowIds)->get() as $win) {
                try {
                    $notifiedCount += $notifier->notifyWindowOpened($win);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[Retake] notifyWindowOpened: ' . $e->getMessage());
                }
            }
        }

        $msg = "{$created} ta qabul oynasi yaratildi";
        if ($notifiedCount > 0) {
            $msg .= " · {$notifiedCount} talabaga Telegram xabar yuborildi";
        }
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

        // Avtomatik Telegram xabar: sanalar yangilanganda mos talabalarga
        try {
            $sent = app(\App\Services\Retake\RetakeNotificationService::class)
                ->notifyWindowDatesUpdated($window->fresh());
        } catch (\Throwable $e) {
            $sent = 0;
            \Illuminate\Support\Facades\Log::warning('[Retake] notifyWindowDatesUpdated: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __("Sanalar yangilandi. {$sent} talabaga Telegram xabar yuborildi."));
    }

    public function destroy(Request $request, int $windowId): RedirectResponse
    {
        if (!$this->canOverride()) {
            abort(403, 'Faqat super-admin oynani o\'chira oladi');
        }

        $window = RetakeApplicationWindow::findOrFail($windowId);

        $force = $request->boolean('force');

        // Arizalar yuborilgan bo'lsa, force=1 bo'lmasa o'chirib bo'lmaydi
        if ($window->applicationGroups()->exists() && !$force) {
            return redirect()->back()->withErrors([
                'window' => 'Bu oynaga arizalar yuborilgan, o\'chirib bo\'lmaydi (force=1 bilan majburiy o\'chirish mumkin)',
            ]);
        }

        if ($force && $window->applicationGroups()->exists()) {
            DB::transaction(function () use ($window) {
                // Cascading delete: oyna → application_groups → applications → har bir bog'liq qator
                foreach ($window->applicationGroups()->with('applications')->get() as $group) {
                    foreach ($group->applications as $app) {
                        $app->delete();
                    }
                    $group->delete();
                }
                $window->delete();
            });
        } else {
            $window->delete();
        }

        return redirect()->route('admin.retake-windows.index')
            ->with('success', __('Oyna o\'chirildi') . ($force ? ' (' . __("majburiy") . ')' : ''));
    }

    /**
     * Bitta oynaning to'liq tarixi: barcha arizalar, statistikasi,
     * kim qaysi guruhga biriktirildi, kim rad etildi.
     */
    public function show(int $windowId)
    {
        $this->authorizeView();

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
            'canManage' => $this->canManage(),
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

    /**
     * Faqat ko'rish ruxsati — O'quv bo'limi yoki Registrator ofisi.
     */
    private function authorizeView(): void
    {
        $user = RetakeAccess::currentStaff();
        if (!RetakeAccess::canViewWindows($user)) {
            abort(403, 'Sizda qayta o\'qish oynalarini ko\'rish ruxsati yo\'q');
        }
    }

    /**
     * Joriy foydalanuvchi oynalarni boshqara oladimi (yozish amallari)?
     * View'da Override/O'chirish/Yangi oyna tugmalarini ko'rsatish uchun.
     */
    private function canManage(): bool
    {
        return RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff());
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

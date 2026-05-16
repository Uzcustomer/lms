<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Exports\RetakeApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeApplicationService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Dekan va Registrator ofisi paneli — qayta o'qish arizalarini ko'rib chiqish.
 *
 * Ikki rol bitta controllerni ishlatadi (UI va mantiq deyarli bir xil),
 * lekin ko'rinish va amal $role param bilan ajraladi.
 */
class RetakeApprovalController extends Controller
{
    public function __construct(
        private RetakeApplicationService $applicationService,
    ) {}

    /**
     * Arizalar ro'yxati. Ariza-guruhlari bo'yicha guruhlangan.
     */
    public function index(Request $request)
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $filter = $request->input('filter', 'all');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // Talaba ma'lumotlari bo'yicha cascading filtrlar
        $studentFilters = [
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department'),
            'specialty' => $request->input('specialty'),
            'level_code' => $request->input('level_code'),
            'semester_code' => $request->input('semester_code'),
            'group' => $request->input('group'),
        ];
        $subjectFilter = $request->input('subject');
        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }

        // Ariza-guruhlari joiniga ariza-bog'liq filtr
        $statusColumn = $role === 'dean' ? 'dean_status' : 'registrar_status';
        $query = RetakeApplicationGroup::query()
            ->with([
                'student',
                'window',
                'applications' => function ($q) {
                    $q->orderBy('semester_id')->orderBy('subject_name');
                },
                'applications.deanUser',
                'applications.registrarUser',
            ])
            // Birinchi navbatda kutilayotgan arizalar — undan keyin eng yangilari
            ->orderByRaw(
                "CASE WHEN EXISTS (SELECT 1 FROM retake_applications ra "
                . "WHERE ra.group_id = retake_application_groups.id "
                . "AND ra.{$statusColumn} = 'pending' "
                . "AND ra.final_status = 'pending') THEN 0 ELSE 1 END ASC"
            )
            ->orderByDesc('created_at');

        // Dekan — faqat o'z fakulteti talabalari
        if ($role === 'dean' && $user instanceof Teacher) {
            $facultyIds = array_map('intval', $user->deanFacultyIds);
            if (empty($facultyIds)) {
                $query->whereRaw('1=0');
            } else {
                $query->whereIn('student_hemis_id', function ($sub) use ($facultyIds) {
                    $sub->select('hemis_id')
                        ->from('students')
                        ->whereIn('department_id', $facultyIds);
                });
            }
        }

        // Holat filtri
        $this->applyFilter($query, $filter, $role);

        // F.I.SH yoki HEMIS ID bo'yicha qidirish
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('student', function ($sq) use ($search) {
                    $sq->where('full_name', 'like', "%{$search}%");
                });

                // Faqat raqamli qidiruv → HEMIS ID bo'yicha
                if (ctype_digit($search)) {
                    $q->orWhere('student_hemis_id', $search);
                }
            });
        }

        // Sana oralig'i (yuborilgan sana bo'yicha)
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Talaba ma'lumotlari bo'yicha qo'shimcha cascading filtrlar
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();
        if ($hasStudentFilter) {
            $query->whereIn('student_hemis_id', function ($sub) use ($studentFilters) {
                $sub->select('hemis_id')->from('students');
                if (!empty($studentFilters['education_type'])) {
                    $sub->where('education_type_code', $studentFilters['education_type']);
                }
                if (!empty($studentFilters['department'])) {
                    $sub->where('department_id', $studentFilters['department']);
                }
                if (!empty($studentFilters['specialty'])) {
                    $sub->where('specialty_id', $studentFilters['specialty']);
                }
                if (!empty($studentFilters['level_code'])) {
                    $sub->where('level_code', $studentFilters['level_code']);
                }
                if (!empty($studentFilters['semester_code'])) {
                    $sub->where('semester_code', $studentFilters['semester_code']);
                }
                if (!empty($studentFilters['group'])) {
                    $sub->where('group_id', $studentFilters['group']);
                }
            });
        }

        // Fan bo'yicha filtr — guruhda hech bo'lmaganda bitta arizа shu fanga tegishli
        if ($subjectFilter) {
            $query->whereHas('applications', fn ($q) => $q->where('subject_id', $subjectFilter));
        }

        $groups = $query->paginate($perPage)->withQueryString();

        // Statistika (faqat rolga tegishli arizalar bo'yicha)
        $stats = $this->calculateStats($role, $user);

        // Registrator uchun: to'lov cheki tekshirilishi kutilayotganlar soni
        // va tasdiqlangan to'lovlar soni
        $paymentToVerifyCount = 0;
        $paymentApprovedCount = 0;
        if ($role === 'registrar') {
            $paymentToVerifyCount = RetakeApplicationGroup::query()
                ->whereNotNull('payment_uploaded_at')
                ->where('payment_verification_status', 'pending')
                ->count();
            $paymentApprovedCount = RetakeApplicationGroup::query()
                ->whereNotNull('payment_uploaded_at')
                ->where('payment_verification_status', 'approved')
                ->count();
        }

        // Filtr bo'yicha ma'lumotlar (keshlangan)
        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();
        $subjects = \App\Services\Retake\RetakeFilterCache::subjects();

        return view('teacher.retake.index', [
            'groups' => $groups,
            'role' => $role,
            'filter' => $filter,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'stats' => $stats,
            'paymentToVerifyCount' => $paymentToVerifyCount,
            'paymentApprovedCount' => $paymentApprovedCount,
            'minReasonLength' => \App\Models\RetakeSetting::rejectReasonMinLength(),
            'educationTypes' => $educationTypes,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Arizalarni Excelga eksport qilish (joriy filtrlar bo'yicha).
     * Dekan — faqat o'z fakulteti; Registrator — barchasi.
     */
    public function export(Request $request)
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $filters = [
            'filter' => $request->input('filter'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'subject' => $request->input('subject'),
            'semester_code' => $request->input('semester_code'),
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department'),
            'specialty' => $request->input('specialty'),
            'level_code' => $request->input('level_code'),
            'group' => $request->input('group'),
            'search' => $request->input('search'),
        ];

        // 'filter' qiymatini Export uchun aniqlashtiramiz.
        // To'lov holatlari alohida payment_status param sifatida uzatiladi.
        $filterValue = $filters['filter'] ?? null;
        if ($filterValue === 'payment_to_verify') {
            $filters['payment_status'] = 'pending';
            $filters['filter'] = null;
        } elseif ($filterValue === 'payment_approved') {
            $filters['payment_status'] = 'approved';
            $filters['filter'] = null;
        } elseif ($filterValue === 'pending_mine' || $filterValue === 'all') {
            $filters['filter'] = null;
        }

        // Dekan — faqat o'z fakultetidagi talabalarning arizalarini eksport qilsin
        if ($role === 'dean' && $user instanceof Teacher) {
            $filters['dean_faculty_ids'] = array_map('intval', $user->deanFacultyIds);
        }

        $fileName = 'retake_arizalar_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new RetakeApplicationsExport($filters), $fileName);
    }

    /**
     * Bitta ariza-guruhini ko'rsatish (modal yoki alohida sahifa).
     */
    public function show(int $groupId)
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $group = RetakeApplicationGroup::with([
            'student',
            'window',
            'applications.deanUser',
            'applications.registrarUser',
            'applications.academicDeptUser',
            'applications.logs.user',
            'applications.retakeGroup.teacher',
        ])->findOrFail($groupId);

        $this->authorizeGroupView($user, $role, $group);

        return view('teacher.retake.show', [
            'group' => $group,
            'role' => $role,
            'minReasonLength' => \App\Models\RetakeSetting::rejectReasonMinLength(),
        ]);
    }

    /**
     * Ariza bo'yicha qaror (tasdiqlash yoki rad etish).
     * Registrator tasdiqlasa — joriy/mustaqil ta'lim baholari va OSKE/TEST flaglari
     * majburiy.
     */
    public function decide(Request $request, int $applicationId): RedirectResponse
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $rules = [
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|min:' . \App\Models\RetakeSetting::rejectReasonMinLength() . '|max:1000',
        ];

        // Eski Joriy/Mustaqil va OSKE/TEST/Sinov flaglar registrator tomonidan
        // endi belgilanmaydi — O'quv bo'limi guruh yaratayotganda baholash turi
        // tanlanadi.

        $data = $request->validate($rules);

        $app = RetakeApplication::findOrFail($applicationId);

        $this->authorizeApplicationDecision($user, $role, $app);

        try {
            if ($role === 'dean') {
                $this->applicationService->deanDecide(
                    $app,
                    $user,
                    $data['decision'],
                    $data['reason'] ?? null,
                );
            } else {
                $this->applicationService->registrarDecide(
                    $app,
                    $user,
                    $data['decision'],
                    $data['reason'] ?? null,
                );
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Qaror muvaffaqiyatli yozildi');
    }

    /**
     * Tanlangan arizalarni bir vaqtda tasdiqlash yoki rad etish.
     * - Dekan: tasdiqlash va rad etish ikkalasi ham bulk amalga oshiriladi
     * - Registrator: faqat bulk rad etish (tasdiqlash uchun har ariza uchun
     *   alohida baho/flaglar kiritilishi kerak — bulk emas)
     */
    public function bulkDecide(Request $request): RedirectResponse
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $minReason = \App\Models\RetakeSetting::rejectReasonMinLength();

        $data = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'integer',
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|min:' . $minReason . '|max:1000',
        ]);

        // Registrator bulk tasdiqlash mumkin emas — har biriga baho kerak
        if ($role === 'registrar' && $data['decision'] === 'approved') {
            return redirect()->back()->withErrors([
                'bulk' => 'Registrator tasdiqlash har bir ariza uchun alohida bajariladi (oldingi baholar majburiy).',
            ]);
        }

        if ($data['decision'] === 'rejected' && empty($data['reason'])) {
            return redirect()->back()->withErrors([
                'reason' => 'Rad etish uchun sababni yozing (eng kamida ' . $minReason . ' belgi)',
            ]);
        }

        $applications = RetakeApplication::whereIn('id', $data['application_ids'])->get();

        $done = 0;
        $skipped = 0;

        foreach ($applications as $app) {
            // Ruxsat tekshiruvi
            try {
                $this->authorizeApplicationDecision($user, $role, $app);
            } catch (\Throwable $e) {
                $skipped++;
                continue;
            }

            // Allaqachon shu rol qaror chiqargan bo'lsa — o'tkazib yuboramiz
            $myStatus = $role === 'dean' ? $app->dean_status : $app->registrar_status;
            if ($myStatus !== RetakeApplication::STATUS_PENDING) {
                $skipped++;
                continue;
            }

            try {
                if ($role === 'dean') {
                    $this->applicationService->deanDecide(
                        $app,
                        $user,
                        $data['decision'],
                        $data['reason'] ?? null,
                    );
                } else {
                    // Registrator bu yerda faqat rad etadi
                    $this->applicationService->registrarDecide(
                        $app,
                        $user,
                        $data['decision'],
                        $data['reason'] ?? null,
                        [],
                    );
                }
                $done++;
            } catch (\Throwable $e) {
                $skipped++;
            }
        }

        $action = $data['decision'] === 'approved' ? 'tasdiqlandi' : 'rad etildi';
        $msg = "{$done} ta ariza {$action}";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta o'tkazib yuborildi";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Registrator ofisi to'lov chekini tasdiqlaydi yoki rad etadi.
     */
    public function verifyPayment(Request $request, int $groupId): RedirectResponse
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        if ($role !== 'registrar') {
            abort(403, 'Faqat registrator ofisi to\'lov chekini tekshira oladi');
        }

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|min:' . \App\Models\RetakeSetting::rejectReasonMinLength() . '|max:1000',
        ]);

        $group = RetakeApplicationGroup::findOrFail($groupId);

        try {
            $this->applicationService->verifyPayment(
                $group,
                $user,
                $data['decision'],
                $data['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        $msg = $data['decision'] === 'approved'
            ? 'To\'lov cheki tasdiqlandi va ariza o\'quv bo\'limiga jo\'natildi'
            : 'To\'lov cheki rad etildi, talaba xabardor qilindi';

        return redirect()->back()->with('success', $msg);
    }

    /**
     * To'lov chekini ko'rib chiqish (registrator tekshiradi).
     */
    public function paymentReceipt(int $groupId)
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $group = RetakeApplicationGroup::findOrFail($groupId);
        $this->authorizeGroupView($user, $role, $group);

        if (!$group->payment_receipt_path) {
            abort(404, 'To\'lov cheki yo\'q');
        }

        $absPath = \Illuminate\Support\Facades\Storage::disk('public')->path($group->payment_receipt_path);
        if (!file_exists($absPath)) {
            abort(404, 'To\'lov cheki fayli topilmadi');
        }

        $mime = mime_content_type($absPath) ?: 'application/octet-stream';
        return response()->file($absPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="tolov_cheki_' . $group->id . '"',
        ]);
    }

    /**
     * Tanlangan ariza-guruhlarini ommaviy o'chirish.
     * Faqat registrator ofisi (va super-admin) — dekan o'chira olmaydi.
     * `retake_applications.group_id` va `retake_application_logs.application_id`
     * cascade qilingani uchun, ariza va loglar avtomatik o'chadi.
     * Kvitansiya/DOCX/PDF fayllarini storage'dan qo'lda o'chiramiz.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        if ($role !== 'registrar') {
            abort(403, 'Bu amalga ruxsat yo\'q');
        }

        $data = $request->validate([
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'integer',
        ]);

        $groups = RetakeApplicationGroup::whereIn('id', $data['group_ids'])->get();

        if ($groups->isEmpty()) {
            return redirect()->back()->with('error', 'O\'chirish uchun ariza topilmadi');
        }

        $deleted = 0;

        DB::transaction(function () use ($groups, &$deleted) {
            foreach ($groups as $group) {
                foreach (['receipt_path', 'docx_path', 'pdf_certificate_path'] as $col) {
                    $path = $group->{$col};
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }

                $group->delete();
                $deleted++;
            }
        });

        return redirect()->back()->with('success', $deleted . ' ta ariza o\'chirildi');
    }

    /**
     * Super-admin: tanlangan retake arizalarini va ularga bog'liq barcha ma'lumotlarni
     * (RetakeApplication, fayllar) butunlay o'chirish — tarixda qolmaydi.
     */
    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403, 'Faqat super-admin uchun');
        }

        $data = $request->validate([
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'integer',
        ]);

        $groups = RetakeApplicationGroup::whereIn('id', $data['group_ids'])->get();
        $deleted = 0;

        DB::transaction(function () use ($groups, &$deleted) {
            foreach ($groups as $group) {
                foreach (['receipt_path', 'docx_path', 'pdf_certificate_path', 'payment_receipt_path'] as $col) {
                    $path = $group->{$col} ?? null;
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }

                \App\Models\RetakeApplication::where('group_id', $group->id)->delete();
                $group->forceDelete();
                $deleted++;
            }
        });

        return redirect()->back()->with('success', "{$deleted} ta ariza butunlay o'chirildi");
    }

    /**
     * Kvitansiyani ko'rib chiqish (faqat ruxsati bo'lganlar).
     * Nginx /storage/* ni static qilib serve qilmagani uchun Laravel orqali
     * uzatamiz, shu bilan birga ruxsat tekshiruvi ham bajariladi.
     */
    public function receipt(int $groupId)
    {
        $user = RetakeAccess::currentStaff();
        $role = $this->detectRole($user);

        $group = RetakeApplicationGroup::findOrFail($groupId);
        $this->authorizeGroupView($user, $role, $group);

        if (!$group->receipt_path) {
            abort(404, 'Kvitansiya fayli yo\'q');
        }

        $absPath = \Illuminate\Support\Facades\Storage::disk('public')->path($group->receipt_path);
        if (!file_exists($absPath)) {
            abort(404, 'Kvitansiya fayli topilmadi');
        }

        $mime = mime_content_type($absPath) ?: 'application/octet-stream';
        return response()->file($absPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="kvitansiya_' . $group->id . '"',
        ]);
    }

    // ──────────────────────────────────────────────────────────────────

    /**
     * Joriy aktor (Teacher yoki User) uchun mos rolni aniqlash.
     * 'registrar' — universitet bo'yicha hammani ko'radi, 'dean' — faqat o'z fakulteti.
     */
    private function detectRole(?\Illuminate\Database\Eloquent\Model $user): string
    {
        if (!$user) {
            abort(403, 'Avtorizatsiya talab qilinadi');
        }

        // Sessionda aktiv rol (sidebar shuni ishlatadi). Foydalanuvchining
        // bir nechta roli bo'lishi mumkin — biz aktiv rol bo'yicha xulq belgilaymiz,
        // lekin agar aktiv rol kichik_admin/oquv_prorektori va h.k. bo'lsa,
        // ular ham hammasini ko'radi (registrar sifatida).
        $activeRole = (string) session('active_role', '');
        $registrarLikeActive = [
            ProjectRole::REGISTRAR_OFFICE->value,
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
            ProjectRole::JUNIOR_ADMIN->value,
            ProjectRole::INSPECTOR->value,
            ProjectRole::VICE_RECTOR->value,
            ProjectRole::ACADEMIC_DEPARTMENT->value,
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ];

        if ($activeRole === ProjectRole::DEAN->value) {
            return 'dean';
        }
        if (in_array($activeRole, $registrarLikeActive, true)) {
            return 'registrar';
        }

        // Aktiv rol noma'lum yoki yo'q — hasRole bilan tekshiramiz
        if ($user->hasAnyRole($registrarLikeActive)) {
            return 'registrar';
        }
        if ($user->hasRole(ProjectRole::DEAN->value)) {
            return 'dean';
        }

        abort(403, 'Sizda qayta o\'qish arizalarini ko\'rib chiqish ruxsati yo\'q');
    }

    private function applyFilter($query, string $filter, string $role): void
    {
        $myStatusColumn = $role === 'dean' ? 'dean_status' : 'registrar_status';

        // Registrator uchun: to'lov cheki tekshirish kutilayotganlar
        if ($filter === 'payment_to_verify' && $role === 'registrar') {
            $query->whereNotNull('payment_uploaded_at')
                  ->where('payment_verification_status', 'pending');
            return;
        }

        // Registrator uchun: to'lov cheki tasdiqlangan guruhlar
        if ($filter === 'payment_approved' && $role === 'registrar') {
            $query->whereNotNull('payment_uploaded_at')
                  ->where('payment_verification_status', 'approved');
            return;
        }

        if ($filter === 'pending_mine') {
            $query->whereHas('applications', function ($q) use ($myStatusColumn) {
                $q->where($myStatusColumn, 'pending')
                  ->where('final_status', 'pending');
            });
            return;
        }

        if ($filter === 'approved') {
            $query->whereHas('applications', fn ($q) => $q->where($myStatusColumn, 'approved'));
            return;
        }

        if ($filter === 'rejected') {
            $query->whereHas('applications', fn ($q) => $q->where($myStatusColumn, 'rejected'));
            return;
        }

        // 'all' yoki noma'lum filtr — barcha arizalarni ko'rsatamiz
    }

    private function calculateStats(string $role, \Illuminate\Database\Eloquent\Model $user): array
    {
        $base = RetakeApplication::query();

        if ($role === 'dean' && $user instanceof Teacher) {
            $facultyIds = $user->deanFacultyIds;
            if (empty($facultyIds)) {
                return ['pending' => 0, 'approved' => 0, 'rejected' => 0];
            }
            $base->whereIn('student_hemis_id', function ($sub) use ($facultyIds) {
                $sub->select('hemis_id')->from('students')->whereIn('department_id', $facultyIds);
            });
        }

        $statusColumn = $role === 'dean' ? 'dean_status' : 'registrar_status';

        return [
            'pending' => (clone $base)->where($statusColumn, 'pending')->count(),
            'approved' => (clone $base)->where($statusColumn, 'approved')->count(),
            'rejected' => (clone $base)->where($statusColumn, 'rejected')->count(),
        ];
    }

    private function authorizeGroupView(\Illuminate\Database\Eloquent\Model $user, string $role, RetakeApplicationGroup $group): void
    {
        if ($role === 'registrar') {
            return; // Registrator hammasini ko'radi
        }

        // Dekan — o'z fakulteti
        if (!$user instanceof Teacher) {
            abort(403, 'Dekan ruxsati teacher hisobida bo\'lishi kerak');
        }
        $student = Student::where('hemis_id', $group->student_hemis_id)->first();
        if (!$student || !RetakeAccess::deanHandlesStudent($user, $student)) {
            abort(403, 'Bu ariza sizning fakultetingizga tegishli emas');
        }
    }

    private function authorizeApplicationDecision(\Illuminate\Database\Eloquent\Model $user, string $role, RetakeApplication $app): void
    {
        if ($role === 'registrar') {
            return;
        }
        if (!$user instanceof Teacher) {
            abort(403, 'Dekan ruxsati teacher hisobida bo\'lishi kerak');
        }
        if (!RetakeAccess::deanCanReviewApplication($user, $app)) {
            abort(403, 'Bu ariza sizning fakultetingizga tegishli emas');
        }
    }
}

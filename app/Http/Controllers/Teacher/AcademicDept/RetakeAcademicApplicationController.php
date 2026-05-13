<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RetakeApplication;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeFilterCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * O'quv bo'limi uchun "QO': Arizalar" sahifasi.
 *
 * Dekan + registrator tasdiqidan o'tgan arizalarni ko'rsatadi va o'quv bo'limi
 * ularni guruhga ajratishdan oldin oldindan tasdiqlash imkonini beradi
 * (yakka yoki bulk ravishda). Tasdiqlangan arizalargina QO': Guruhlar
 * sahifasida guruhlash uchun ko'rinadi.
 */
class RetakeAcademicApplicationController extends Controller
{
    public function __construct(
        private RetakeApplicationService $applicationService,
    ) {}

    public function index(Request $request)
    {
        $this->authorizeAccess();

        $departmentId = $request->input('department');
        $specialtyId = $request->input('specialty');
        $levelCode = $request->input('level_code');
        $semesterCode = $request->input('semester_code');
        $groupId = $request->input('group');
        $search = trim((string) $request->input('search', ''));
        $stage = $request->input('stage', 'all'); // all|pending|preapproved|rejected

        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }

        // Talabaning hemis_id'larini filtrga ko'ra tanlash
        $studentQuery = Student::query();

        if ($departmentId) {
            $studentQuery->where('department_id', $departmentId);
        }
        if ($specialtyId) {
            $studentQuery->where('specialty_id', $specialtyId);
        }
        if ($levelCode) {
            $studentQuery->where('level_code', $levelCode);
        }
        if ($semesterCode) {
            $studentQuery->where('semester_code', $semesterCode);
        }
        if ($groupId) {
            $studentQuery->where('group_id', $groupId);
        }
        if ($search !== '') {
            $studentQuery->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('hemis_id', $search);
            });
        }

        $hasFilters = $departmentId || $specialtyId || $levelCode || $semesterCode || $groupId || $search !== '';
        $studentHemisIds = $hasFilters ? $studentQuery->pluck('hemis_id') : null;

        $appsQuery = RetakeApplication::query()
            ->with(['group.student', 'deanUser', 'registrarUser', 'academicDeptUser']);

        if ($studentHemisIds !== null) {
            $appsQuery->whereIn('student_hemis_id', $studentHemisIds);
        }

        // Bosqich filtri
        match ($stage) {
            // Dekan + Registrator tasdiqlagan, lekin o'quv bo'limi hali ko'rib chiqmagan
            'pending' => $appsQuery
                ->where('dean_status', 'approved')
                ->where('registrar_status', 'approved')
                ->where('academic_dept_status', 'pending')
                ->where('final_status', 'pending'),
            // O'quv bo'limi tasdiqlagan, lekin guruhga biriktirilmagan
            'preapproved' => $appsQuery
                ->where('academic_dept_status', 'approved')
                ->where('final_status', 'pending')
                ->whereNull('retake_group_id'),
            // O'quv bo'limi tomonidan rad etilgan
            'rejected' => $appsQuery
                ->where('academic_dept_status', 'rejected'),
            // Hammasi (statistika uchun) — hech qanday qo'shimcha filtrsiz
            default => null,
        };

        $appsQuery->orderByDesc('created_at');
        $applications = $appsQuery->paginate($perPage)->withQueryString();

        // Filtr panel uchun ma'lumot
        $educationTypes = RetakeFilterCache::educationTypes();

        // Sanoq qatorlari (tab badge) — to'lov filtri olib tashlangan, har xolatga
        // mos arizalar soni ko'rsatiladi
        $counters = [
            'pending' => RetakeApplication::query()
                ->where('dean_status', 'approved')
                ->where('registrar_status', 'approved')
                ->where('academic_dept_status', 'pending')
                ->where('final_status', 'pending')
                ->count(),
            'preapproved' => RetakeApplication::query()
                ->where('academic_dept_status', 'approved')
                ->where('final_status', 'pending')
                ->whereNull('retake_group_id')
                ->count(),
            'rejected' => RetakeApplication::query()
                ->where('academic_dept_status', 'rejected')
                ->count(),
        ];

        return view('teacher.academic-dept.retake-applications.index', [
            'applications' => $applications,
            'educationTypes' => $educationTypes,
            'stage' => $stage,
            'counters' => $counters,
            'perPage' => $perPage,
        ]);
    }

    private function countersBaseQuery()
    {
        // Endi to'lov filtri yo'q — barcha arizalar bo'yicha sanoq
        return RetakeApplication::query();
    }

    /**
     * Yakka arizani oldindan tasdiqlash.
     */
    public function approve(int $applicationId): RedirectResponse
    {
        $this->authorizeAccess();

        $app = RetakeApplication::findOrFail($applicationId);
        $actor = $this->actor();

        try {
            $this->applicationService->academicPreApprove($app, $actor);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __("Ariza tasdiqlandi"));
    }

    /**
     * Yakka arizani rad etish.
     */
    public function reject(Request $request, int $applicationId): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $app = RetakeApplication::findOrFail($applicationId);
        $actor = $this->actor();

        try {
            $this->applicationService->academicReject($app, $actor, $data['reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __("Ariza rad etildi"));
    }

    /**
     * Bulk: tanlangan arizalarni oldindan tasdiqlash.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'integer',
        ]);

        $actor = $this->actor();
        $apps = RetakeApplication::whereIn('id', $data['application_ids'])->get();

        $approved = 0;
        $skipped = 0;
        foreach ($apps as $app) {
            try {
                $this->applicationService->academicPreApprove($app, $actor);
                $approved++;
            } catch (ValidationException $e) {
                $skipped++;
            }
        }

        $msg = "{$approved} ta ariza tasdiqlandi";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta o'tkazib yuborildi";
        }

        return redirect()->back()->with('success', $msg);
    }

    /**
     * Bulk: tanlangan arizalarni rad etish (umumiy sabab bilan).
     */
    public function bulkReject(Request $request): RedirectResponse
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'integer',
            'reason' => 'required|string|max:500',
        ]);

        $actor = $this->actor();
        $apps = RetakeApplication::whereIn('id', $data['application_ids'])->get();

        $rejected = 0;
        $skipped = 0;
        foreach ($apps as $app) {
            try {
                $this->applicationService->academicReject($app, $actor, $data['reason']);
                $rejected++;
            } catch (ValidationException $e) {
                $skipped++;
            }
        }

        $msg = "{$rejected} ta ariza rad etildi";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta o'tkazib yuborildi";
        }

        return redirect()->back()->with('success', $msg);
    }

    private function authorizeAccess(): void
    {
        if (!RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff())) {
            abort(403, "Sizda qayta o'qish arizalarini boshqarish ruxsati yo'q");
        }
    }

    private function actor(): Teacher
    {
        return RetakeAccess::currentStaff();
    }
}

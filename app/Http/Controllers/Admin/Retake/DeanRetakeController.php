<?php

namespace App\Http\Controllers\Admin\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\RejectRetakeApplicationRequest;
use App\Models\Group;
use App\Models\RetakeApplication;
use App\Models\Teacher;
use App\Services\Retake\RetakeApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dekan tomoni — parallel tasdiqlash.
 *
 * Faqat o'z fakulteti talabalari arizalari ko'rinadi (dean_faculties pivot).
 * Spec qoidalari:
 *  - Mening tasdiqimni kutyapti — dean_status='pending' default
 *  - Registrator holati ham ko'rinadi (lekin o'z qarori bog'liq emas)
 *  - Tasdiqlash/Rad etish (sabab 10-500 belgi)
 */
class DeanRetakeController extends Controller
{
    public function __construct(
        private readonly RetakeApprovalService $approvalService,
    ) {
    }

    public function index(Request $request): View
    {
        $teacher = $this->resolveDean();
        $facultyIds = array_map('intval', $teacher->dean_faculty_ids ?? []);

        $deanStatus = $request->query('dean_status', 'pending');

        $query = RetakeApplication::query()
            ->whereHas('student', fn ($q) => empty($facultyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('department_id', $facultyIds))
            ->with(['student:id,hemis_id,full_name,group_name,group_id,department_id,department_name,specialty_name,specialty_id,level_name', 'period'])
            ->orderByDesc('submitted_at');

        if ($deanStatus !== 'all') {
            $query->where('dean_status', $deanStatus);
        }

        if ($registrarStatus = $request->query('registrar_status')) {
            $query->where('registrar_status', $registrarStatus);
        }

        if ($groupId = $request->query('group_id')) {
            $query->whereHas('student', fn ($q) => $q->where('group_id', $groupId));
        }

        if ($semesterId = $request->query('semester_id')) {
            $query->where('semester_id', $semesterId);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }

        $applications = $query->paginate(25)->withQueryString();

        $groups = $this->resolveFacultyGroups($facultyIds);

        $stats = $this->buildStats($facultyIds);

        return view('admin.retake.dean.index', [
            'applications' => $applications,
            'groups' => $groups,
            'stats' => $stats,
            'filters' => $request->only(['dean_status', 'registrar_status', 'group_id', 'semester_id', 'search']),
        ]);
    }

    public function show(int $id): View
    {
        $teacher = $this->resolveDean();
        $facultyIds = array_map('intval', $teacher->dean_faculty_ids ?? []);

        $application = RetakeApplication::query()
            ->whereHas('student', fn ($q) => $q->whereIn('department_id', $facultyIds))
            ->with(['student', 'period', 'retakeGroup.teacher', 'logs'])
            ->findOrFail($id);

        return view('admin.retake.dean.show', [
            'application' => $application,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $teacher = $this->resolveDean();
        $application = $this->loadApplicationForFaculty($id, $teacher);

        try {
            $this->approvalService->approveAsDean($teacher, $application);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.retake.dean.show', $id)
            ->with('success', 'Ariza tasdiqlandi.');
    }

    public function reject(RejectRetakeApplicationRequest $request, int $id): RedirectResponse
    {
        $teacher = $this->resolveDean();
        $application = $this->loadApplicationForFaculty($id, $teacher);

        try {
            $this->approvalService->rejectAsDean(
                $teacher,
                $application,
                $request->input('rejection_reason'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.retake.dean.show', $id)
            ->with('success', 'Ariza rad etildi.');
    }

    /**
     * Kvitansiya / DOCX faylini yuklab olish (private storage).
     */
    public function downloadFile(int $id, string $type): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $teacher = $this->resolveDean();
        $facultyIds = array_map('intval', $teacher->dean_faculty_ids ?? []);

        $application = RetakeApplication::query()
            ->whereHas('student', fn ($q) => $q->whereIn('department_id', $facultyIds))
            ->findOrFail($id);

        $path = match ($type) {
            'receipt' => $application->receipt_path,
            'document' => $application->generated_doc_path,
            'tasdiqnoma' => $application->tasdiqnoma_pdf_path,
            default => null,
        };

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Fayl topilmadi.');
        }

        $filename = match ($type) {
            'receipt' => $application->receipt_original_name ?? "receipt-{$application->id}",
            'document' => "ariza-{$application->application_group_id}.docx",
            'tasdiqnoma' => "tasdiqnoma-{$application->id}.pdf",
        };

        return Storage::disk('local')->download($path, $filename);
    }

    private function resolveDean(): Teacher
    {
        $user = Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
        if (! ($user instanceof Teacher) || ! $user->hasRole('dekan')) {
            abort(403, 'Faqat dekan rolidagi xodim foydalana oladi.');
        }
        return $user;
    }

    private function loadApplicationForFaculty(int $id, Teacher $teacher): RetakeApplication
    {
        $facultyIds = array_map('intval', $teacher->dean_faculty_ids ?? []);
        return RetakeApplication::query()
            ->whereHas('student', fn ($q) => $q->whereIn('department_id', $facultyIds))
            ->with('student')
            ->findOrFail($id);
    }

    /**
     * Dekan fakultetidagi guruhlar ro'yxati (filter dropdown uchun).
     *
     * @param  array<int, int>  $facultyIds
     */
    private function resolveFacultyGroups(array $facultyIds): \Illuminate\Support\Collection
    {
        if (empty($facultyIds)) {
            return collect();
        }

        return Group::query()
            ->whereIn('department_hemis_id', $facultyIds)
            ->orderBy('name')
            ->get(['id', 'group_hemis_id', 'name']);
    }

    /**
     * Dashboard statistika: pending/approved/rejected/all sonlari.
     *
     * @param  array<int, int>  $facultyIds
     */
    private function buildStats(array $facultyIds): array
    {
        if (empty($facultyIds)) {
            return ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
        }

        $base = RetakeApplication::query()
            ->whereHas('student', fn ($q) => $q->whereIn('department_id', $facultyIds));

        return [
            'pending' => (clone $base)->where('dean_status', RetakeReviewStatus::PENDING->value)->count(),
            'approved' => (clone $base)->where('dean_status', RetakeReviewStatus::APPROVED->value)->count(),
            'rejected' => (clone $base)->where('dean_status', RetakeReviewStatus::REJECTED->value)->count(),
            'all' => (clone $base)->count(),
        ];
    }
}

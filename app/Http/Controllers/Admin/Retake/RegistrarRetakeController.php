<?php

namespace App\Http\Controllers\Admin\Retake;

use App\Enums\RetakeReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\RejectRetakeApplicationRequest;
use App\Models\Department;
use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registrator (ofisi) tomoni — barcha fakultetlar bo'yicha parallel tasdiqlash.
 *
 * Spec qoidalari:
 *  - Mening tasdiqimni kutyapti — registrar_status='pending' default
 *  - Dekan holati ham ko'rinadi (parallel ishlaydi)
 *  - Tasdiqlash/Rad etish (sabab 10-500 belgi)
 *  - Statistika (Excel/PDF eksport — keyingi enhancement)
 */
class RegistrarRetakeController extends Controller
{
    public function __construct(
        private readonly RetakeApprovalService $approvalService,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureRegistrar();

        $registrarStatus = $request->query('registrar_status', 'pending');

        $query = RetakeApplication::query()
            ->with(['student:id,hemis_id,full_name,group_name,group_id,department_id,department_name,specialty_name,specialty_id,level_name', 'period'])
            ->orderByDesc('submitted_at');

        if ($registrarStatus !== 'all') {
            $query->where('registrar_status', $registrarStatus);
        }

        if ($deanStatus = $request->query('dean_status')) {
            $query->where('dean_status', $deanStatus);
        }

        if ($departmentId = $request->query('department_id')) {
            $query->whereHas('student', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($semesterId = $request->query('semester_id')) {
            $query->where('semester_id', $semesterId);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }

        $applications = $query->paginate(25)->withQueryString();

        $departments = Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'department_hemis_id', 'name']);

        $stats = [
            'pending' => RetakeApplication::where('registrar_status', RetakeReviewStatus::PENDING->value)->count(),
            'approved' => RetakeApplication::where('registrar_status', RetakeReviewStatus::APPROVED->value)->count(),
            'rejected' => RetakeApplication::where('registrar_status', RetakeReviewStatus::REJECTED->value)->count(),
            'all' => RetakeApplication::count(),
        ];

        return view('admin.retake.registrar.index', [
            'applications' => $applications,
            'departments' => $departments,
            'stats' => $stats,
            'filters' => $request->only(['registrar_status', 'dean_status', 'department_id', 'semester_id', 'search']),
        ]);
    }

    public function show(int $id): View
    {
        $this->ensureRegistrar();

        $application = RetakeApplication::query()
            ->with(['student', 'period', 'retakeGroup.teacher', 'logs'])
            ->findOrFail($id);

        return view('admin.retake.registrar.show', [
            'application' => $application,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $this->ensureRegistrar();
        $user = Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
        $application = RetakeApplication::findOrFail($id);

        try {
            $this->approvalService->approveAsRegistrar($user, $application);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()
            ->route('admin.retake.registrar.show', $id)
            ->with('success', 'Ariza tasdiqlandi.');
    }

    public function reject(RejectRetakeApplicationRequest $request, int $id): RedirectResponse
    {
        $this->ensureRegistrar();
        $user = Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
        $application = RetakeApplication::findOrFail($id);

        try {
            $this->approvalService->rejectAsRegistrar(
                $user,
                $application,
                $request->input('rejection_reason'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.retake.registrar.show', $id)
            ->with('success', 'Ariza rad etildi.');
    }

    public function downloadFile(int $id, string $type): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $this->ensureRegistrar();
        $application = RetakeApplication::findOrFail($id);

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

    private function ensureRegistrar(): void
    {
        $user = Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
        if ($user === null
            || ! method_exists($user, 'hasRole')
            || ! $user->hasRole(['registrator_ofisi', 'admin', 'superadmin'])) {
            abort(403, 'Faqat registrator ofisi xodimi foydalana oladi.');
        }
    }
}

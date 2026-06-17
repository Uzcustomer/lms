<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeSetting;
use App\Models\Student;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeDebtService;
use App\Services\Retake\RetakeWindowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RetakeApplicationApiController extends Controller
{
    public function __construct(
        private RetakeApplicationService $applicationService,
        private RetakeDebtService $debtService,
        private RetakeWindowService $windowService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $window = $this->windowService->activeWindowForStudent($student);

        $activeApplications = RetakeApplication::query()
            ->forStudent((int) $student->hemis_id)
            ->whereIn('final_status', [
                RetakeApplication::STATUS_PENDING,
                RetakeApplication::STATUS_APPROVED,
            ])
            ->with(['retakeGroup.teacher'])
            ->get()
            ->keyBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id);

        $debts = $this->debtService->debts($student);

        $history = RetakeApplicationGroup::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->with(['applications.retakeGroup.teacher', 'window.session'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $currentSemester = $this->applicationService->currentSemesterNumber($student);
        $remainingSlots = $this->applicationService->currentSemesterRemainingSlots($student, $window?->id);

        return response()->json([
            'success' => true,
            'data' => [
                'window' => $this->formatWindow($window),
                'debts' => $debts
                    ->map(fn ($debt) => $this->formatDebt($debt, $activeApplications, $currentSemester))
                    ->values(),
                'history' => $history
                    ->map(fn (RetakeApplicationGroup $group) => $this->formatGroup($group))
                    ->values(),
                'groups_awaiting_payment' => $history
                    ->filter(fn (RetakeApplicationGroup $group) => $group->requires_payment)
                    ->map(fn (RetakeApplicationGroup $group) => $this->formatGroup($group))
                    ->values(),
                'groups_payment_verifying' => $history
                    ->filter(fn (RetakeApplicationGroup $group) => $group->payment_awaiting_verification)
                    ->map(fn (RetakeApplicationGroup $group) => $this->formatGroup($group))
                    ->values(),
                'settings' => [
                    'remaining_slots' => $remainingSlots,
                    'current_semester' => $currentSemester,
                    'credit_price' => (float) RetakeSetting::creditPrice(),
                    'receipt_max_mb' => (int) RetakeSetting::receiptMaxMb(),
                    'payment_max_mb' => RetakeApplicationService::PAYMENT_RECEIPT_MAX_MB,
                    'max_subjects_per_application' => RetakeApplicationService::MAX_SUBJECTS_PER_APPLICATION,
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $maxMb = RetakeSetting::receiptMaxMb();

        $data = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.subject_id' => ['required', 'string'],
            'subjects.*.semester_id' => ['required', 'string'],
            'receipt' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:' . ($maxMb * 1024)],
            'comment' => ['nullable', 'string', 'max:' . RetakeApplicationService::MAX_COMMENT_LENGTH],
        ]);

        $group = $this->applicationService->submit(
            $student,
            $data['subjects'],
            $request->file('receipt'),
            $data['comment'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Ariza muvaffaqiyatli yuborildi. Tasdiqlanishini kuting.',
            'data' => $this->formatGroup($group->load(['applications.retakeGroup.teacher', 'window.session'])),
        ], 201);
    }

    public function uploadPayment(Request $request, int $groupId): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $request->validate([
            'payment' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:' . (RetakeApplicationService::PAYMENT_RECEIPT_MAX_MB * 1024),
            ],
        ]);

        $group = RetakeApplicationGroup::query()
            ->where('id', $groupId)
            ->where('student_hemis_id', $student->hemis_id)
            ->firstOrFail();

        $updated = $this->applicationService->uploadPayment(
            $student,
            $group,
            $request->file('payment'),
        );

        return response()->json([
            'success' => true,
            'message' => "Arizangiz o'quv bo'limiga yuborildi",
            'data' => $this->formatGroup($updated->load(['applications.retakeGroup.teacher', 'window.session'])),
        ]);
    }

    private function formatWindow($window): ?array
    {
        if (!$window) {
            return null;
        }

        return [
            'id' => $window->id,
            'specialty_name' => $window->specialty_name,
            'level_name' => $window->level_name,
            'semester_code' => $window->semester_code,
            'semester_name' => $window->semester_name,
            'start_date' => $window->start_date?->format('Y-m-d'),
            'end_date' => $window->end_date?->format('Y-m-d'),
            'status' => $window->status,
            'is_open' => $window->isOpen(),
            'remaining_days' => $window->remaining_days,
        ];
    }

    private function formatDebt(object $debt, Collection $activeApplications, ?int $currentSemester): array
    {
        $key = $debt->subject_id . '|' . $debt->semester_id;
        /** @var RetakeApplication|null $application */
        $application = $activeApplications->get($key);
        $semesterNumber = RetakeApplicationService::semesterNumber($debt->semester_name ?: $debt->semester_id);

        return [
            'subject_id' => $debt->subject_id,
            'subject_name' => $debt->subject_name,
            'semester_id' => $debt->semester_id,
            'semester_name' => $debt->semester_name,
            'credit' => (float) $debt->credit,
            'grade' => $debt->grade !== null && $debt->grade !== '' ? (float) $debt->grade : null,
            'debt_reason' => $debt->debt_reason,
            'is_current_semester' => $currentSemester !== null
                && $semesterNumber !== null
                && $semesterNumber === (int) $currentSemester,
            'is_active' => $application !== null,
            'active_status' => $application?->studentDisplayStatus(),
            'final_status' => $application?->final_status,
            'application' => $application ? $this->formatApplication($application) : null,
        ];
    }

    private function formatGroup(RetakeApplicationGroup $group): array
    {
        $applications = $group->applications
            ->map(fn (RetakeApplication $application) => $this->formatApplication($application))
            ->values();

        return [
            'id' => $group->id,
            'created_at' => $group->created_at?->format('Y-m-d H:i'),
            'receipt_amount' => (float) $group->receipt_amount,
            'credit_price_at_time' => (float) $group->credit_price_at_time,
            'total_credits' => (float) $group->applications->sum('credit'),
            'comment' => $group->comment,
            'overall_status' => $group->overall_status,
            'requires_payment' => $group->requires_payment,
            'payment_awaiting_verification' => $group->payment_awaiting_verification,
            'payment_verification_status' => $group->payment_verification_status,
            'payment_rejection_reason' => $group->payment_rejection_reason,
            'payment_uploaded_at' => $group->payment_uploaded_at?->format('Y-m-d H:i'),
            'docx_url' => $this->publicUrl($group->docx_path),
            'certificate_uz_url' => $this->publicUrl($group->pdf_certificate_path),
            'session_name' => $group->window?->session?->name,
            'window' => $this->formatWindow($group->window),
            'applications' => $applications,
        ];
    }

    private function formatApplication(RetakeApplication $application): array
    {
        return [
            'id' => $application->id,
            'subject_id' => $application->subject_id,
            'subject_name' => $application->subject_name,
            'semester_id' => $application->semester_id,
            'semester_name' => $application->semester_name,
            'credit' => (float) $application->credit,
            'dean_status' => $application->dean_status,
            'registrar_status' => $application->registrar_status,
            'academic_dept_status' => $application->academic_dept_status,
            'final_status' => $application->final_status,
            'display_status' => $application->studentDisplayStatus(),
            'rejection_reason' => $application->rejectionReason(),
            'has_oske' => (bool) $application->has_oske,
            'has_test' => (bool) $application->has_test,
            'has_sinov' => (bool) $application->has_sinov,
            'previous_joriy_grade' => $application->previous_joriy_grade !== null ? (float) $application->previous_joriy_grade : null,
            'previous_mustaqil_grade' => $application->previous_mustaqil_grade !== null ? (float) $application->previous_mustaqil_grade : null,
            'retake_group' => $this->formatRetakeGroup($application->retakeGroup),
        ];
    }

    private function formatRetakeGroup($group): ?array
    {
        if (!$group) {
            return null;
        }

        return [
            'id' => $group->id,
            'name' => $group->name,
            'teacher_name' => $group->teacher_name ?? $group->teacher?->full_name,
            'teacher_phones' => $group->teacher_phones ?? [],
            'start_date' => $group->start_date?->format('Y-m-d'),
            'end_date' => $group->end_date?->format('Y-m-d'),
            'status' => $group->status,
            'status_label' => $group->statusLabel(),
            'assessment_type' => $group->assessment_type,
            'oske_date' => $group->oske_date?->format('Y-m-d'),
            'test_date' => $group->test_date?->format('Y-m-d'),
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        return url(Storage::url($path));
    }
}

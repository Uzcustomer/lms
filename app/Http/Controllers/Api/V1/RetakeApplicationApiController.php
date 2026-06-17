<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeGrade;
use App\Models\RetakeMustaqilSubmission;
use App\Models\RetakeSetting;
use App\Models\Student;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeDebtService;
use App\Services\Retake\RetakeJournalService;
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
        private RetakeJournalService $journalService,
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
                'journal' => $this->journalApplications($student)
                    ->map(fn (RetakeApplication $application) => $this->formatJournalApplication($application))
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

    public function journal(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->journalApplications($student)
                ->map(fn (RetakeApplication $application) => $this->formatJournalApplication($application))
                ->values(),
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

    public function uploadMustaqil(Request $request, int $applicationId): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png,zip,rar',
                'max:' . (RetakeMustaqilSubmission::MAX_FILE_MB * 1024),
            ],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $application = RetakeApplication::query()
            ->where('id', $applicationId)
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->firstOrFail();

        if (!$application->retakeGroup) {
            return response()->json([
                'message' => 'Qayta o\'qish guruhi topilmadi.',
            ], 404);
        }

        $this->journalService->submitMustaqil(
            $application->retakeGroup,
            $student,
            $request->file('file'),
            $request->input('comment'),
            $application->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Mustaqil ta\'lim fayli yuklandi.',
            'data' => $this->formatJournalApplication($application->refresh()->load(['retakeGroup.teacher'])),
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
            'joriy_score' => $application->joriy_score !== null ? (float) $application->joriy_score : null,
            'oske_score' => $application->oske_score !== null ? (float) $application->oske_score : null,
            'test_score' => $application->test_score !== null ? (float) $application->test_score : null,
            'final_grade_value' => $application->final_grade_value !== null ? (float) $application->final_grade_value : null,
            'retake_group' => $this->formatRetakeGroup($application->retakeGroup),
        ];
    }

    private function journalApplications(Student $student): Collection
    {
        return RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn (RetakeApplication $application) => $application->retakeGroup !== null)
            ->values();
    }

    private function formatJournalApplication(RetakeApplication $application): array
    {
        $group = $application->retakeGroup;
        $isEditable = $group ? $this->journalService->isEditable($group) : false;

        $mustaqil = $group
            ? RetakeMustaqilSubmission::query()
                ->where('retake_group_id', $group->id)
                ->where('application_id', $application->id)
                ->first()
            : null;

        $dailyGrades = $group
            ? RetakeGrade::query()
                ->where('retake_group_id', $group->id)
                ->where('application_id', $application->id)
                ->orderBy('lesson_date')
                ->get()
            : collect();

        return [
            'id' => $application->id,
            'subject_id' => $application->subject_id,
            'subject_name' => $application->subject_name,
            'semester_id' => $application->semester_id,
            'semester_name' => $application->semester_name,
            'credit' => (float) $application->credit,
            'retake_group' => $this->formatRetakeGroup($group),
            'assessment_type_label' => $this->assessmentTypeLabel($group?->assessment_type),
            'is_editable' => $isEditable,
            'joriy_score' => $application->joriy_score !== null ? (float) $application->joriy_score : null,
            'joriy_graded_by_name' => $application->joriy_graded_by_name,
            'joriy_graded_at' => $application->joriy_graded_at?->format('Y-m-d H:i'),
            'oske_score' => $application->oske_score !== null ? (float) $application->oske_score : null,
            'test_score' => $application->test_score !== null ? (float) $application->test_score : null,
            'final_grade_value' => $application->final_grade_value !== null ? (float) $application->final_grade_value : null,
            'final_grade_set_at' => $application->final_grade_set_at?->format('Y-m-d H:i'),
            'lesson_dates' => $group ? $this->journalService->lessonDates($group) : [],
            'daily_grades' => $dailyGrades
                ->map(fn (RetakeGrade $grade) => [
                    'date' => $grade->lesson_date?->format('Y-m-d'),
                    'grade' => $grade->grade !== null ? (float) $grade->grade : null,
                    'comment' => $grade->comment,
                    'graded_by_name' => $grade->graded_by_name,
                    'graded_at' => $grade->graded_at?->format('Y-m-d H:i'),
                ])
                ->values(),
            'mustaqil' => $this->formatMustaqilSubmission($mustaqil, $isEditable),
        ];
    }

    private function formatMustaqilSubmission(?RetakeMustaqilSubmission $submission, bool $isEditable): array
    {
        $attemptCount = (int) ($submission?->attempt_count ?? 0);
        $passed = $submission?->isPassed() ?? false;
        $exhausted = $submission?->attemptsExhausted() ?? false;
        $canUpload = $isEditable && !$passed && !$exhausted;

        return [
            'exists' => $submission !== null,
            'file_name' => $submission?->original_filename,
            'file_url' => $this->publicUrl($submission?->file_path),
            'student_comment' => $submission?->student_comment,
            'submitted_at' => $submission?->submitted_at?->format('Y-m-d H:i'),
            'grade' => $submission?->grade !== null ? (float) $submission->grade : null,
            'teacher_comment' => $submission?->teacher_comment,
            'graded_by_name' => $submission?->graded_by_name,
            'graded_at' => $submission?->graded_at?->format('Y-m-d H:i'),
            'attempt_count' => $attemptCount,
            'attempts_left' => max(0, RetakeMustaqilSubmission::MAX_ATTEMPTS - $attemptCount),
            'max_attempts' => RetakeMustaqilSubmission::MAX_ATTEMPTS,
            'pass_grade' => RetakeMustaqilSubmission::PASS_GRADE,
            'is_passed' => $passed,
            'is_exhausted' => $exhausted,
            'can_upload' => $canUpload,
        ];
    }

    private function assessmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'oske' => 'OSKE',
            'test' => 'TEST',
            'oske_test' => 'OSKE + TEST',
            'sinov_fan' => 'Sinov fan',
            default => $type ?: '-',
        };
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

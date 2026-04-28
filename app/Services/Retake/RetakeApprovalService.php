<?php

namespace App\Services\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeLogAction;
use App\Enums\RetakeReviewStatus;
use App\Models\RetakeApplication;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Parallel approval mantiqi:
 *  - Dekan VA Registrator mustaqil ravishda ariza ko'radi (tartib emas).
 *  - Birortasi rejected → ariza darhol rejected (final).
 *  - Ikkalasi approved → academic_dept_status avtomatik 'pending' ga o'tadi.
 *  - O'quv bo'limi yakuniy bosqich (RetakeGroupService orqali — chunki guruh va
 *    sanalar/o'qituvchi MAJBURIY).
 *
 * Eslatma: Audit log uchun har approver alohida yoziladi (rejected bo'lsa ham
 * boshqa approver qaroriy yozib olinadi).
 */
class RetakeApprovalService
{
    public const REJECTION_REASON_MIN = 10;
    public const REJECTION_REASON_MAX = 500;

    public function __construct(
        private readonly RetakeLogService $logService,
    ) {
    }

    // === Dekan ===

    public function approveAsDean(Authenticatable $actor, RetakeApplication $application): RetakeApplication
    {
        $this->ensureDean($actor);
        $this->ensureDeanFacultyMatch($actor, $application);
        $this->ensureNotFinalized($application);

        return DB::transaction(function () use ($actor, $application) {
            $application->update([
                'dean_status' => RetakeReviewStatus::APPROVED->value,
                'dean_reviewed_by' => $actor->getAuthIdentifier(),
                'dean_reviewed_by_guard' => $this->logService->guardForActor($actor),
                'dean_reviewed_at' => CarbonImmutable::now(),
            ]);

            $this->logService->log($application, RetakeLogAction::DEAN_APPROVED, $actor);
            $this->maybeAdvanceToAcademicDept($application);

            return $application->fresh();
        });
    }

    public function rejectAsDean(Authenticatable $actor, RetakeApplication $application, string $reason): RetakeApplication
    {
        $this->ensureDean($actor);
        $this->ensureDeanFacultyMatch($actor, $application);
        $this->ensureNotFinalized($application);
        $this->validateReason($reason);

        return DB::transaction(function () use ($actor, $application, $reason) {
            $application->update([
                'dean_status' => RetakeReviewStatus::REJECTED->value,
                'dean_reviewed_by' => $actor->getAuthIdentifier(),
                'dean_reviewed_by_guard' => $this->logService->guardForActor($actor),
                'dean_reviewed_at' => CarbonImmutable::now(),
                'dean_rejection_reason' => $reason,
            ]);

            $this->logService->log($application, RetakeLogAction::DEAN_REJECTED, $actor, $reason);

            return $application->fresh();
        });
    }

    // === Registrator ===

    public function approveAsRegistrar(Authenticatable $actor, RetakeApplication $application): RetakeApplication
    {
        $this->ensureRegistrar($actor);
        $this->ensureNotFinalized($application);

        return DB::transaction(function () use ($actor, $application) {
            $application->update([
                'registrar_status' => RetakeReviewStatus::APPROVED->value,
                'registrar_reviewed_by' => $actor->getAuthIdentifier(),
                'registrar_reviewed_by_guard' => $this->logService->guardForActor($actor),
                'registrar_reviewed_at' => CarbonImmutable::now(),
            ]);

            $this->logService->log($application, RetakeLogAction::REGISTRAR_APPROVED, $actor);
            $this->maybeAdvanceToAcademicDept($application);

            return $application->fresh();
        });
    }

    public function rejectAsRegistrar(Authenticatable $actor, RetakeApplication $application, string $reason): RetakeApplication
    {
        $this->ensureRegistrar($actor);
        $this->ensureNotFinalized($application);
        $this->validateReason($reason);

        return DB::transaction(function () use ($actor, $application, $reason) {
            $application->update([
                'registrar_status' => RetakeReviewStatus::REJECTED->value,
                'registrar_reviewed_by' => $actor->getAuthIdentifier(),
                'registrar_reviewed_by_guard' => $this->logService->guardForActor($actor),
                'registrar_reviewed_at' => CarbonImmutable::now(),
                'registrar_rejection_reason' => $reason,
            ]);

            $this->logService->log($application, RetakeLogAction::REGISTRAR_REJECTED, $actor, $reason);

            return $application->fresh();
        });
    }

    // === O'quv bo'limi (yakka rad etish) ===

    /**
     * O'quv bo'limi yakka talabani rad etadi (boshqalarga ta'sir qilmaydi).
     * Tasdiqlash esa RetakeGroupService orqali (guruh shakllantirish bilan).
     */
    public function rejectAsAcademicDept(Authenticatable $actor, RetakeApplication $application, string $reason): RetakeApplication
    {
        $this->ensureAcademicDept($actor);
        $this->ensureReachedAcademicDept($application);
        $this->ensureNotFinalized($application);
        $this->validateReason($reason);

        return DB::transaction(function () use ($actor, $application, $reason) {
            $application->update([
                'academic_dept_status' => RetakeAcademicDeptStatus::REJECTED->value,
                'academic_dept_reviewed_by' => $actor->getAuthIdentifier(),
                'academic_dept_reviewed_by_guard' => $this->logService->guardForActor($actor),
                'academic_dept_reviewed_at' => CarbonImmutable::now(),
                'academic_dept_rejection_reason' => $reason,
            ]);

            $this->logService->log($application, RetakeLogAction::ACADEMIC_DEPT_REJECTED, $actor, $reason);

            return $application->fresh();
        });
    }

    /**
     * O'quv bo'limi tasdiqlashi (RetakeGroupService chaqiradi):
     *  - academic_dept_status = approved
     *  - retake_group_id biriktiriladi
     *  - verification_code (UUID v4) generatsiya qilinadi
     */
    public function markApprovedByAcademicDept(
        Authenticatable $actor,
        RetakeApplication $application,
        int $retakeGroupId,
    ): RetakeApplication {
        $this->ensureReachedAcademicDept($application);

        $application->update([
            'academic_dept_status' => RetakeAcademicDeptStatus::APPROVED->value,
            'academic_dept_reviewed_by' => $actor->getAuthIdentifier(),
            'academic_dept_reviewed_by_guard' => $this->logService->guardForActor($actor),
            'academic_dept_reviewed_at' => CarbonImmutable::now(),
            'retake_group_id' => $retakeGroupId,
            'verification_code' => (string) Str::uuid(),
        ]);

        $this->logService->log($application, RetakeLogAction::ACADEMIC_DEPT_APPROVED, $actor);
        $this->logService->log($application, RetakeLogAction::ASSIGNED_TO_GROUP, $actor, "Group ID: {$retakeGroupId}");

        return $application->fresh();
    }

    // === State transitions ===

    /**
     * Dekan VA Registrator ikkalasi approved bo'lganda, va academic_dept_status
     * hali not_started bo'lsa — pending'ga o'tkazadi.
     */
    private function maybeAdvanceToAcademicDept(RetakeApplication $application): void
    {
        $fresh = $application->fresh();
        if ($fresh === null) {
            return;
        }

        $bothApproved = $fresh->dean_status === RetakeReviewStatus::APPROVED
            && $fresh->registrar_status === RetakeReviewStatus::APPROVED;
        $stillNotStarted = $fresh->academic_dept_status === RetakeAcademicDeptStatus::NOT_STARTED;

        if ($bothApproved && $stillNotStarted) {
            $fresh->update([
                'academic_dept_status' => RetakeAcademicDeptStatus::PENDING->value,
            ]);
        }
    }

    // === Validators / Guards ===

    private function ensureDean(Authenticatable $actor): void
    {
        if (! method_exists($actor, 'hasRole') || ! $actor->hasRole('dekan')) {
            throw ValidationException::withMessages(['actor' => 'Faqat dekan rolidagi xodim ariza ko\'rib chiqishi mumkin.']);
        }
    }

    private function ensureRegistrar(Authenticatable $actor): void
    {
        if (! method_exists($actor, 'hasRole') || ! $actor->hasRole('registrator_ofisi')) {
            throw ValidationException::withMessages(['actor' => 'Faqat registrator ofisi xodimi ariza ko\'rib chiqishi mumkin.']);
        }
    }

    private function ensureAcademicDept(Authenticatable $actor): void
    {
        if (! method_exists($actor, 'hasRole') || ! $actor->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi'])) {
            throw ValidationException::withMessages(['actor' => 'Faqat o\'quv bo\'limi xodimi ariza ko\'rib chiqishi mumkin.']);
        }
    }

    /**
     * Dekan faqat o'z fakulteti talabalarining arizalarini ko'ra oladi.
     * dean_faculties pivot orqali aniqlanadi. Mavjud kod patterni:
     *   $teacher->dean_faculty_ids
     */
    private function ensureDeanFacultyMatch(Authenticatable $actor, RetakeApplication $application): void
    {
        if (! ($actor instanceof Teacher)) {
            throw ValidationException::withMessages(['actor' => 'Dekan ariza tasdiqlashi uchun Teacher modelida bo\'lishi shart.']);
        }

        $studentDepartmentId = $application->student?->department_id;
        if ($studentDepartmentId === null) {
            throw ValidationException::withMessages(['application' => 'Talabaning fakulteti aniqlanmadi.']);
        }

        $facultyIds = $actor->dean_faculty_ids ?? [];
        if (! in_array((int) $studentDepartmentId, array_map('intval', $facultyIds), true)) {
            throw ValidationException::withMessages([
                'actor' => 'Bu ariza sizning fakultetingizga tegishli emas.',
            ]);
        }
    }

    /**
     * Ariza yakunlangan (approved yoki rejected) bo'lsa — yana qaror qabul qilib bo'lmaydi.
     */
    private function ensureNotFinalized(RetakeApplication $application): void
    {
        if ($application->final_status !== 'pending') {
            throw ValidationException::withMessages([
                'application' => 'Ariza allaqachon yakuniy holatda — yangi qaror qabul qilib bo\'lmaydi.',
            ]);
        }
    }

    private function ensureReachedAcademicDept(RetakeApplication $application): void
    {
        if ($application->academic_dept_status === RetakeAcademicDeptStatus::NOT_STARTED) {
            throw ValidationException::withMessages([
                'application' => 'Ariza hali o\'quv bo\'limiga yetib kelmagan (dekan va registrator tasdiqi kerak).',
            ]);
        }
    }

    private function validateReason(string $reason): void
    {
        $length = mb_strlen(trim($reason));
        if ($length < self::REJECTION_REASON_MIN) {
            throw ValidationException::withMessages([
                'rejection_reason' => "Sabab eng kamida " . self::REJECTION_REASON_MIN . " belgi bo'lishi kerak.",
            ]);
        }
        if ($length > self::REJECTION_REASON_MAX) {
            throw ValidationException::withMessages([
                'rejection_reason' => "Sabab " . self::REJECTION_REASON_MAX . " belgidan oshmasligi kerak.",
            ]);
        }
    }
}

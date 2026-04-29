<?php

namespace App\Services\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeGroupStatus;
use App\Enums\RetakeReviewStatus;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O'quv bo'limi tomonidan qayta o'qish guruhini shakllantirish.
 *
 * Yakuniy tasdiqlash bosqichi: faqat dekan VA registrator approved bo'lgan
 * arizalar (academic_dept_status = pending) guruhga biriktirilishi mumkin.
 * Saqlanganda har biriga verification_code va retake_group_id bog'lanadi.
 *
 * Tasdiqnoma PDF generatsiyasi keyingi bosqichda (RetakeVerificationService
 * ichida) qo'shiladi — hozircha verification_code yaratish bilan cheklanamiz.
 */
class RetakeGroupService
{
    public function __construct(
        private readonly RetakeApprovalService $approvalService,
    ) {
    }

    /**
     * Yangi guruh shakllantirish va tanlangan arizalarni unga biriktirish.
     *
     * @param  array<int, int>  $applicationIds  yakuniy tasdiqlanadigan arizalar
     */
    public function createAndAssign(
        Authenticatable $actor,
        string $name,
        int $subjectId,
        string $subjectName,
        int $semesterId,
        ?string $semesterName,
        Carbon $startDate,
        Carbon $endDate,
        int $teacherId,
        ?int $maxStudents,
        array $applicationIds,
    ): RetakeGroup {
        $this->validateDates($startDate, $endDate);
        $this->validateApplicationsCount($applicationIds);
        $this->ensureTeacherExists($teacherId);

        $applications = $this->loadAndValidateApplications($applicationIds, $subjectId, $semesterId);

        return DB::transaction(function () use (
            $actor, $name, $subjectId, $subjectName, $semesterId, $semesterName,
            $startDate, $endDate, $teacherId, $maxStudents, $applications,
        ) {
            $group = RetakeGroup::create([
                'name' => $name,
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'semester_id' => $semesterId,
                'semester_name' => $semesterName,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'teacher_id' => $teacherId,
                'max_students' => $maxStudents,
                'status' => RetakeGroupStatus::SCHEDULED->value,
                'created_by' => $actor->getAuthIdentifier(),
                'created_by_guard' => $actor instanceof Teacher ? 'teacher' : 'web',
            ]);

            foreach ($applications as $application) {
                $this->approvalService->markApprovedByAcademicDept($actor, $application, $group->id);
            }

            return $group->fresh();
        });
    }

    /**
     * Mavjud guruhni yangilash. Faqat boshlanmagan guruhlar uchun
     * (start_date hali kelmagan) sanalar/o'qituvchi/maks. talabani
     * o'zgartirib bo'ladi.
     */
    public function update(
        RetakeGroup $group,
        Carbon $startDate,
        Carbon $endDate,
        int $teacherId,
        ?int $maxStudents,
    ): RetakeGroup {
        $this->validateDates($startDate, $endDate);
        $this->ensureTeacherExists($teacherId);

        if ($group->status === RetakeGroupStatus::IN_PROGRESS
            || $group->status === RetakeGroupStatus::COMPLETED) {
            throw ValidationException::withMessages([
                'group' => 'Boshlangan yoki tugagan guruhni o\'zgartirib bo\'lmaydi.',
            ]);
        }

        // Boshlangan kun bo'lsa ham qayta tekshirish
        if (CarbonImmutable::today()->greaterThanOrEqualTo($group->start_date)) {
            throw ValidationException::withMessages([
                'group' => 'Guruh boshlanganidan keyin sanalarni o\'zgartirib bo\'lmaydi.',
            ]);
        }

        $group->update([
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'teacher_id' => $teacherId,
            'max_students' => $maxStudents,
        ]);

        return $group->fresh();
    }

    /**
     * Status o'tkazish — odatda cron orqali avtomatik (start_date kelguncha
     * scheduled, oraliqda in_progress, end_date'dan keyin completed).
     * Manual chaqirish uchun ham mavjud.
     */
    public function refreshStatus(RetakeGroup $group): RetakeGroup
    {
        if ($group->status === RetakeGroupStatus::FORMING) {
            return $group;
        }

        $today = CarbonImmutable::today();
        $newStatus = match (true) {
            $today->lessThan($group->start_date) => RetakeGroupStatus::SCHEDULED,
            $today->greaterThan($group->end_date) => RetakeGroupStatus::COMPLETED,
            default => RetakeGroupStatus::IN_PROGRESS,
        };

        if ($group->status !== $newStatus) {
            $group->update(['status' => $newStatus->value]);
            return $group->fresh();
        }

        return $group;
    }

    private function validateDates(Carbon $startDate, Carbon $endDate): void
    {
        if ($startDate->greaterThan($endDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi shart.',
            ]);
        }
    }

    /**
     * @param  array<int, int>  $applicationIds
     */
    private function validateApplicationsCount(array $applicationIds): void
    {
        if (count($applicationIds) < 1) {
            throw ValidationException::withMessages([
                'application_ids' => 'Eng kamida 1 ta talabani guruhga biriktirish kerak.',
            ]);
        }
    }

    private function ensureTeacherExists(int $teacherId): void
    {
        $exists = Teacher::query()->whereKey($teacherId)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Bunday o\'qituvchi topilmadi.',
            ]);
        }
    }

    /**
     * Tanlangan arizalar:
     *  - mavjud
     *  - bir xil subject_id va semester_id
     *  - academic_dept_status = pending (ya'ni dekan VA registrator ikkalasi
     *    approved, hali o'quv bo'limi qaror qilmagan)
     *
     * @param  array<int, int>  $applicationIds
     * @return Collection<int, RetakeApplication>
     */
    private function loadAndValidateApplications(array $applicationIds, int $subjectId, int $semesterId): Collection
    {
        $applications = RetakeApplication::query()
            ->whereIn('id', $applicationIds)
            ->get();

        if ($applications->count() !== count($applicationIds)) {
            throw ValidationException::withMessages([
                'application_ids' => 'Ba\'zi arizalar topilmadi.',
            ]);
        }

        foreach ($applications as $application) {
            if ((int) $application->subject_id !== $subjectId
                || (int) $application->semester_id !== $semesterId) {
                throw ValidationException::withMessages([
                    'application_ids' => 'Tanlangan arizalar guruh fani va semestriga mos kelmaydi.',
                ]);
            }

            if ($application->academic_dept_status !== RetakeAcademicDeptStatus::PENDING) {
                throw ValidationException::withMessages([
                    'application_ids' => 'Faqat dekan va registrator tasdiqlagan arizalarni guruhga biriktirib bo\'ladi.',
                ]);
            }

            // Qo'shimcha integritet tekshiruvi
            if ($application->dean_status !== RetakeReviewStatus::APPROVED
                || $application->registrar_status !== RetakeReviewStatus::APPROVED) {
                throw ValidationException::withMessages([
                    'application_ids' => 'Ariza holati noto\'g\'ri (dekan/registrator approved emas).',
                ]);
            }
        }

        return $applications;
    }
}

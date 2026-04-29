<?php

namespace App\Services\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeReviewStatus;
use App\Models\AcademicRecord;
use App\Models\RetakeApplication;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Talabaning akademik qarzdorliklarini olish (HEMIS academic_records.retraining_status = TRUE)
 * va har fanning joriy ariza holatini biriktirish.
 */
class StudentDebtService
{
    /**
     * Talaba uchun qarzdor fanlar ro'yxati. Har element tarkibi:
     *  - subject_id, subject_name, semester_id, semester_name, education_year
     *  - credit, total_point, grade
     *  - application_status: 'eligible' | 'pending_dean_registrar' | 'pending_registrar'
     *      | 'pending_dean' | 'pending_academic_dept' | 'approved' | 'rejected'
     *  - active_application: RetakeApplication|null (joriy aktiv ariza)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getDebtSubjects(Student $student): Collection
    {
        // 1. HEMIS academic_records'dan qarzdorliklarni olish
        $debts = AcademicRecord::query()
            ->where('student_id', $student->hemis_id)
            ->where('retraining_status', true)
            ->get([
                'subject_id', 'subject_name',
                'semester_id', 'semester_name',
                'education_year', 'credit', 'total_point', 'grade',
            ]);

        if ($debts->isEmpty()) {
            return collect();
        }

        // 2. Talabaning shu fanlar bo'yicha barcha aktiv arizalarini bir martalik olish
        $subjectKeys = $debts->map(fn ($d) => $d->subject_id . '|' . $d->semester_id);
        $applications = RetakeApplication::query()
            ->where('student_id', $student->id)
            ->whereIn('subject_id', $debts->pluck('subject_id')->unique())
            ->orderByDesc('submitted_at')
            ->get();

        // Map: "subject_id|semester_id" => Most recent application
        $appMap = $applications->groupBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id)
            ->map(fn ($apps) => $apps->first());

        // 3. Har qarzdor fanga holatini biriktirish
        return $debts->map(function ($debt) use ($appMap) {
            $key = $debt->subject_id . '|' . $debt->semester_id;
            $app = $appMap->get($key);

            return [
                'subject_id' => (int) $debt->subject_id,
                'subject_name' => $debt->subject_name,
                'semester_id' => (int) $debt->semester_id,
                'semester_name' => $debt->semester_name,
                'education_year' => $debt->education_year,
                'credit' => (float) $debt->credit,
                'total_point' => $debt->total_point !== null ? (float) $debt->total_point : null,
                'grade' => $debt->grade,
                'application_status' => $this->resolveStatus($app),
                'active_application' => $app,
                'is_eligible_for_new' => $this->isEligibleForNew($app),
            ];
        });
    }

    /**
     * Talabaning aktiv (rejected emas) arizalari bo'yicha holat etiketi.
     */
    private function resolveStatus(?RetakeApplication $app): string
    {
        if ($app === null) {
            return 'eligible';
        }

        $final = $app->final_status;
        if ($final === 'approved') {
            return 'approved';
        }
        if ($final === 'rejected') {
            return 'rejected';
        }

        // pending — qaysi bosqich?
        if ($app->academic_dept_status === RetakeAcademicDeptStatus::PENDING) {
            return 'pending_academic_dept';
        }

        $deanApproved = $app->dean_status === RetakeReviewStatus::APPROVED;
        $registrarApproved = $app->registrar_status === RetakeReviewStatus::APPROVED;

        return match (true) {
            $deanApproved && ! $registrarApproved => 'pending_registrar',
            $registrarApproved && ! $deanApproved => 'pending_dean',
            default => 'pending_dean_registrar',
        };
    }

    /**
     * Yangi ariza yuborish uchun fan tanlov mumkinmi:
     * - Hech qanday ariza yo'q, yoki
     * - Eng so'nggi ariza rejected.
     */
    private function isEligibleForNew(?RetakeApplication $app): bool
    {
        if ($app === null) {
            return true;
        }

        return $app->final_status === 'rejected';
    }
}

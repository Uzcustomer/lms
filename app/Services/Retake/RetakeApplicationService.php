<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeApplicationLog;
use App\Models\RetakeSetting;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Qayta o'qish arizalari uchun asosiy biznes mantiq.
 *
 * Vazifalari:
 *  - Slot tekshirish (max 3 aktiv)
 *  - Ariza yuborish (group + applications)
 *  - Dekan/Registrator parallel tasdiqlash
 *  - O'quv bo'limi yakuniy tasdiqlash/rad etish
 *  - HEMIS sync orqali avto-bekor qilish
 *  - Audit log
 */
class RetakeApplicationService
{
    public const MAX_ACTIVE_SUBJECTS = 3;
    public const MIN_SUBJECTS_PER_APPLICATION = 1;
    public const MAX_SUBJECTS_PER_APPLICATION = 3;
    public const MAX_COMMENT_LENGTH = 500;

    /**
     * To'lov cheki uchun maksimal hajm (MB).
     * Bu qiymatni sozlamalarga ko'chirish o'rniga konstant qoldirildi —
     * mahsulot talabida aniq 5 MB belgilangan.
     */
    public const PAYMENT_RECEIPT_MAX_MB = 5;

    public function __construct(
        private RetakeDebtService $debtService,
        private RetakeWindowService $windowService,
        private RetakeDocumentService $documentService,
        private RetakeNotificationService $notificationService,
    ) {}

    /**
     * Talabaning aktiv (pending + approved) arizalari soni.
     * Faqat slot hisoblash uchun ishlatiladi.
     */
    public function activeSubjectCount(int $studentHemisId): int
    {
        return RetakeApplication::query()
            ->forStudent($studentHemisId)
            ->active()
            ->count();
    }

    /**
     * Qancha slot bo'sh — talaba yana nechta fan tanlay oladi.
     */
    public function remainingSlots(int $studentHemisId): int
    {
        return max(0, self::MAX_ACTIVE_SUBJECTS - $this->activeSubjectCount($studentHemisId));
    }

    /**
     * Berilgan fan uchun talaba aktiv arizasi bor (pending yoki approved)?
     * Bitta fanga bir vaqtda faqat bitta aktiv ariza bo'la oladi.
     */
    public function hasActiveApplicationFor(int $studentHemisId, string $subjectId, string $semesterId): bool
    {
        return RetakeApplication::query()
            ->forStudent($studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->active()
            ->exists();
    }

    /**
     * Talaba ariza yuboradi.
     *
     * @param  array<int,array{subject_id:string,semester_id:string}>  $selectedSubjects
     */
    public function submit(
        Student $student,
        array $selectedSubjects,
        UploadedFile $receipt,
        ?string $comment = null,
    ): RetakeApplicationGroup {
        // 1. Oyna tekshirish
        $window = $this->windowService->activeWindowForStudent($student);
        if (!$window || !$window->isOpen()) {
            throw ValidationException::withMessages([
                'window' => 'Sizning yo\'nalishingiz va kursingiz uchun ariza qabul oynasi ochiq emas',
            ]);
        }

        // 2. Soni tekshirish (1..3)
        $count = count($selectedSubjects);
        if ($count < self::MIN_SUBJECTS_PER_APPLICATION || $count > self::MAX_SUBJECTS_PER_APPLICATION) {
            throw ValidationException::withMessages([
                'subjects' => 'Eng kamida 1 ta, eng ko\'pi 3 ta fan tanlash mumkin',
            ]);
        }

        // 3. Slot tekshirish (aktiv + yangi <= 3)
        $remaining = $this->remainingSlots((int) $student->hemis_id);
        if ($count > $remaining) {
            throw ValidationException::withMessages([
                'subjects' => "Bo'sh slot yetarli emas. Aktiv arizalaringiz bilan birga jami 3 tadan oshmasligi kerak (qolgan: {$remaining})",
            ]);
        }

        // 4. Izoh uzunligi
        if ($comment !== null && mb_strlen($comment) > self::MAX_COMMENT_LENGTH) {
            throw ValidationException::withMessages([
                'comment' => 'Izoh ' . self::MAX_COMMENT_LENGTH . ' belgidan oshmasligi kerak',
            ]);
        }

        // 5. Kvitansiya tekshirish
        $maxMb = RetakeSetting::receiptMaxMb();
        if ($receipt->getSize() > $maxMb * 1024 * 1024) {
            throw ValidationException::withMessages([
                'receipt' => "Kvitansiya hajmi {$maxMb} MB dan oshmasligi kerak",
            ]);
        }
        $allowedExt = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $ext = strtolower($receipt->getClientOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages([
                'receipt' => 'Faqat PDF, DOC, DOCX, JPG, PNG fayllar yuklanishi mumkin',
            ]);
        }

        // 6. Tanlangan fanlarning haqiqatan qarzdorlik ekanligini tekshirish
        $debts = $this->debtService->debts($student);
        $debtsKeyed = $debts->keyBy(fn ($r) => $r->subject_id . '|' . $r->semester_id);

        $resolved = [];
        foreach ($selectedSubjects as $sel) {
            $key = $sel['subject_id'] . '|' . $sel['semester_id'];
            $debt = $debtsKeyed->get($key);
            if (!$debt) {
                throw ValidationException::withMessages([
                    'subjects' => "Tanlangan fan ({$sel['subject_id']}) sizning qarzdor ro'yxatingizda yo'q",
                ]);
            }

            // Bu fan uchun aktiv ariza yo'qligini tekshirish
            if ($this->hasActiveApplicationFor((int) $student->hemis_id, $debt->subject_id, $debt->semester_id)) {
                throw ValidationException::withMessages([
                    'subjects' => "{$debt->subject_name} fani bo'yicha sizda aktiv ariza allaqachon mavjud",
                ]);
            }
            $resolved[] = $debt;
        }

        // 7. Hisob-kitob (kreditlar va summa)
        $creditPrice = RetakeSetting::creditPrice();
        $totalCredits = array_sum(array_map(fn ($r) => (float) $r->credit, $resolved));
        $amount = $totalCredits * $creditPrice;

        // 8. Kvitansiya faylini saqlash
        $receiptPath = $receipt->store(
            "retake/receipts/{$student->hemis_id}",
            'public'
        );

        // 9. DB tranzaksiya — group + applications + log
        return DB::transaction(function () use ($student, $window, $resolved, $receiptPath, $amount, $creditPrice, $comment) {
            $group = RetakeApplicationGroup::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'window_id' => $window->id,
                'receipt_path' => $receiptPath,
                'receipt_amount' => $amount,
                'credit_price_at_time' => $creditPrice,
                'comment' => $comment,
            ]);

            foreach ($resolved as $debt) {
                $app = RetakeApplication::create([
                    'group_id' => $group->id,
                    'student_hemis_id' => $student->hemis_id,
                    'subject_id' => $debt->subject_id,
                    'subject_name' => $debt->subject_name,
                    'semester_id' => $debt->semester_id,
                    'semester_name' => $debt->semester_name,
                    'credit' => (float) $debt->credit,
                ]);

                $this->log($app, RetakeApplicationLog::ACTION_SUBMITTED, $student, null, [
                    'group_id' => $group->id,
                ]);
            }

            $loaded = $group->load('applications', 'student');

            // Talaba arizasini darhol DOCX qilib generatsiya qilamiz —
            // ariza shabloni qarorlarga bog'liq emas, talaba uni hozir yuklab olib
            // chop etishi mumkin.
            try {
                $loaded->docx_path = $this->documentService->generateDocx($loaded);
                $loaded->save();
            } catch (\Throwable $e) {
                report($e);
            }

            $this->notificationService->notifyNewSubmission($loaded);

            return $loaded;
        });
    }

    /**
     * Dekan tasdiqlaydi/rad etadi.
     */
    public function deanDecide(RetakeApplication $app, Teacher $actor, string $decision, ?string $reason = null): RetakeApplication
    {
        $this->assertReviewable($app);
        $this->assertDecision($decision);

        if ($decision === RetakeApplication::STATUS_REJECTED) {
            $this->assertReason($reason);
        }

        return DB::transaction(function () use ($app, $actor, $decision, $reason) {
            $from = $app->dean_status;

            $app->update([
                'dean_status' => $decision,
                'dean_user_id' => $actor->id,
                'dean_user_name' => $actor->full_name,
                'dean_decision_at' => now(),
                'dean_reason' => $reason,
            ]);

            $this->log(
                $app,
                $decision === RetakeApplication::STATUS_APPROVED
                    ? RetakeApplicationLog::ACTION_DEAN_APPROVED
                    : RetakeApplicationLog::ACTION_DEAN_REJECTED,
                $actor,
                $reason,
                ['from' => $from, 'to' => $decision]
            );

            $this->recomputeFinalStatus($app->fresh());

            $fresh = $app->refresh();
            $this->notificationService->notifyDeanDecision($fresh);
            $this->maybeNotifyPaymentRequired($fresh);

            return $fresh;
        });
    }

    /**
     * Registrator tasdiqlaydi/rad etadi.
     *
     * Tasdiqlashda registrator quyidagilarni majburiy to'ldirishi kerak:
     *  - previous_joriy_grade (oldingi semestrdagi joriy ta'lim bahosi)
     *  - previous_mustaqil_grade (oldingi semestrdagi mustaqil ta'lim bahosi)
     *  - has_oske / has_test (qayta o'qishda OSKE / TEST topshiriladimi?)
     *
     * @param  array{
     *     previous_joriy_grade?: float|null,
     *     previous_mustaqil_grade?: float|null,
     *     has_oske?: bool,
     *     has_test?: bool,
     * }  $details
     */
    public function registrarDecide(
        RetakeApplication $app,
        Teacher $actor,
        string $decision,
        ?string $reason = null,
        array $details = [],
    ): RetakeApplication {
        $this->assertReviewable($app);
        $this->assertDecision($decision);

        if ($decision === RetakeApplication::STATUS_REJECTED) {
            $this->assertReason($reason);
        }

        if ($decision === RetakeApplication::STATUS_APPROVED) {
            // Joriy va mustaqil ta'lim baholari majburiy
            $joriy = $details['previous_joriy_grade'] ?? null;
            $mustaqil = $details['previous_mustaqil_grade'] ?? null;
            if ($joriy === null || $joriy === '' || $mustaqil === null || $mustaqil === '') {
                throw ValidationException::withMessages([
                    'previous_grades' => 'Joriy va mustaqil ta\'lim baholarini to\'ldirish majburiy',
                ]);
            }
            if ((float) $joriy < 0 || (float) $joriy > 100 || (float) $mustaqil < 0 || (float) $mustaqil > 100) {
                throw ValidationException::withMessages([
                    'previous_grades' => 'Baholar 0 dan 100 gacha bo\'lishi kerak',
                ]);
            }
        }

        return DB::transaction(function () use ($app, $actor, $decision, $reason, $details) {
            $from = $app->registrar_status;

            $update = [
                'registrar_status' => $decision,
                'registrar_user_id' => $actor->id,
                'registrar_user_name' => $actor->full_name,
                'registrar_decision_at' => now(),
                'registrar_reason' => $reason,
            ];

            if ($decision === RetakeApplication::STATUS_APPROVED) {
                $update['previous_joriy_grade'] = (float) $details['previous_joriy_grade'];
                $update['previous_mustaqil_grade'] = (float) $details['previous_mustaqil_grade'];
                $update['has_oske'] = !empty($details['has_oske']);
                $update['has_test'] = !empty($details['has_test']);
            }

            $app->update($update);

            $this->log(
                $app,
                $decision === RetakeApplication::STATUS_APPROVED
                    ? RetakeApplicationLog::ACTION_REGISTRAR_APPROVED
                    : RetakeApplicationLog::ACTION_REGISTRAR_REJECTED,
                $actor,
                $reason,
                ['from' => $from, 'to' => $decision]
            );

            $this->recomputeFinalStatus($app->fresh());

            $fresh = $app->refresh();
            $this->notificationService->notifyRegistrarDecision($fresh);
            $this->maybeNotifyPaymentRequired($fresh);

            return $fresh;
        });
    }

    /**
     * O'quv bo'limi yakka arizani rad etadi (guruh shakllantirilmagan bo'lsa).
     */
    public function academicReject(RetakeApplication $app, Teacher $actor, string $reason): RetakeApplication
    {
        $this->assertReason($reason);

        if ($app->final_status !== RetakeApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application' => 'Faqat kutilayotgan arizani rad etish mumkin',
            ]);
        }

        return DB::transaction(function () use ($app, $actor, $reason) {
            $app->update([
                'academic_dept_status' => RetakeApplication::STATUS_REJECTED,
                'academic_dept_user_id' => $actor->id,
                'academic_dept_user_name' => $actor->full_name,
                'academic_dept_decision_at' => now(),
                'academic_dept_reason' => $reason,
                'final_status' => RetakeApplication::STATUS_REJECTED,
                'rejected_by' => RetakeApplication::REJECTED_BY_ACADEMIC_DEPT,
            ]);

            $this->log($app, RetakeApplicationLog::ACTION_ACADEMIC_REJECTED, $actor, $reason);

            $fresh = $app->refresh();
            $this->maybeGenerateGroupDocuments($fresh, $actor);
            $this->notificationService->notifyAcademicDecision($fresh);

            return $fresh;
        });
    }

    /**
     * O'quv bo'limi guruhga biriktirib tasdiqlaydi.
     * Bu metod RetakeGroupService dan chaqiriladi — bir nechta arizani birga tasdiqlaydi.
     */
    public function academicApprove(RetakeApplication $app, Teacher $actor, int $retakeGroupId): RetakeApplication
    {
        if ($app->final_status !== RetakeApplication::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'application' => 'Faqat kutilayotgan arizani tasdiqlash mumkin',
            ]);
        }

        if (!$app->isDualApproved()) {
            throw ValidationException::withMessages([
                'application' => 'Avval dekan va registrator tasdiqlashi kerak',
            ]);
        }

        return DB::transaction(function () use ($app, $actor, $retakeGroupId) {
            $app->update([
                'academic_dept_status' => RetakeApplication::STATUS_APPROVED,
                'academic_dept_user_id' => $actor->id,
                'academic_dept_user_name' => $actor->full_name,
                'academic_dept_decision_at' => now(),
                'final_status' => RetakeApplication::STATUS_APPROVED,
                'retake_group_id' => $retakeGroupId,
            ]);

            $this->log($app, RetakeApplicationLog::ACTION_ACADEMIC_APPROVED, $actor, null, [
                'retake_group_id' => $retakeGroupId,
            ]);
            $this->log($app, RetakeApplicationLog::ACTION_GROUP_ASSIGNED, $actor, null, [
                'retake_group_id' => $retakeGroupId,
            ]);

            $fresh = $app->refresh();
            $this->maybeGenerateGroupDocuments($fresh, $actor);
            $this->notificationService->notifyAcademicDecision($fresh);

            // Hujjatlar tayyor bo'lsa — talabaga xabar
            if ($fresh->group?->pdf_certificate_path) {
                $this->notificationService->notifyDocumentsReady($fresh->group);
            }

            return $fresh;
        });
    }

    /**
     * HEMIS sync'da baho paydo bo'ldi — ariza avtomatik bekor qilinadi.
     */
    public function autoCancelByHemis(RetakeApplication $app): RetakeApplication
    {
        if ($app->final_status !== RetakeApplication::STATUS_PENDING) {
            return $app;
        }

        return DB::transaction(function () use ($app) {
            $app->update([
                'final_status' => RetakeApplication::STATUS_REJECTED,
                'rejected_by' => RetakeApplication::REJECTED_BY_SYSTEM_HEMIS,
                'academic_dept_reason' => 'HEMIS sync\'da baho paydo bo\'ldi yoki retraining_status o\'zgardi',
            ]);

            $this->log(
                $app,
                RetakeApplicationLog::ACTION_AUTO_CANCELLED_HEMIS,
                null,
                'HEMIS\'da baho paydo bo\'lgan, ariza avtomatik bekor qilindi'
            );

            $fresh = $app->refresh();
            $this->maybeGenerateGroupDocuments($fresh);
            $this->notificationService->notifyAutoCancelled($fresh);

            return $fresh;
        });
    }

    /**
     * Talaba dekan + registrator tasdig'idan keyin to'lov chekini yuklaydi.
     * Yuklangandan so'ng — guruhdagi dual_approved arizalar o'quv bo'limiga jo'natiladi.
     */
    public function uploadPayment(
        Student $student,
        RetakeApplicationGroup $group,
        UploadedFile $file,
    ): RetakeApplicationGroup {
        // 1. Egalik tekshiruvi
        if ((int) $group->student_hemis_id !== (int) $student->hemis_id) {
            throw ValidationException::withMessages([
                'group' => 'Bu ariza sizga tegishli emas',
            ]);
        }

        // 2. Allaqachon yuklanganmi?
        if ($group->payment_uploaded_at !== null) {
            throw ValidationException::withMessages([
                'payment' => 'To\'lov cheki allaqachon yuklangan',
            ]);
        }

        // 3. Group dual-approved holatdami?
        $loaded = $group->load('applications');
        if (!$loaded->requires_payment) {
            throw ValidationException::withMessages([
                'payment' => 'To\'lov cheki yuklash hozircha talab qilinmaydi',
            ]);
        }

        // 4. Fayl tekshirish
        $maxBytes = self::PAYMENT_RECEIPT_MAX_MB * 1024 * 1024;
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                'payment' => "To'lov cheki hajmi " . self::PAYMENT_RECEIPT_MAX_MB . " MB dan oshmasligi kerak",
            ]);
        }
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowedExt, true)) {
            throw ValidationException::withMessages([
                'payment' => 'Faqat PDF, JPG, PNG fayllar yuklanishi mumkin',
            ]);
        }

        // 5. Saqlash + holatni o'zgartirish
        $path = $file->store("retake/payment_receipts/{$student->hemis_id}", 'public');

        return DB::transaction(function () use ($loaded, $path, $student) {
            $loaded->update([
                'payment_receipt_path' => $path,
                'payment_uploaded_at' => now(),
                // Qayta yuklash holati uchun (avval rejected bo'lgan bo'lsa) — pending'ga qaytaradi
                'payment_verification_status' => RetakeApplicationGroup::PAYMENT_VERIFICATION_PENDING,
                'payment_verified_by_user_id' => null,
                'payment_verified_by_name' => null,
                'payment_verified_at' => null,
                'payment_rejection_reason' => null,
            ]);

            $loaded->refresh()->load('applications');

            // Talabaga: ariza registratorga yuborildi (haqiqiyligi tekshiriladi)
            $this->notificationService->notifyPaymentSubmitted($loaded);
            // Registrator ofisiga: yangi to'lov cheki tekshirilsin
            $this->notificationService->notifyPaymentToVerify($loaded);

            return $loaded;
        });
    }

    /**
     * Registrator ofisi to'lov chekining haqiqiyligini tasdiqlaydi yoki rad etadi.
     * Faqat tasdiqlangandan keyin ariza o'quv bo'limiga jo'natiladi.
     */
    public function verifyPayment(
        RetakeApplicationGroup $group,
        Teacher $actor,
        string $decision,
        ?string $reason = null,
    ): RetakeApplicationGroup {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Noto\'g\'ri qaror']);
        }

        if ($group->payment_uploaded_at === null) {
            throw ValidationException::withMessages([
                'payment' => 'Talaba hali to\'lov chekini yuklamagan',
            ]);
        }

        if ($group->payment_verification_status !== RetakeApplicationGroup::PAYMENT_VERIFICATION_PENDING) {
            throw ValidationException::withMessages([
                'payment' => 'To\'lov cheki allaqachon ko\'rib chiqilgan',
            ]);
        }

        if ($decision === 'rejected') {
            $this->assertReason($reason);
        }

        return DB::transaction(function () use ($group, $actor, $decision, $reason) {
            $group->update([
                'payment_verification_status' => $decision,
                'payment_verified_by_user_id' => $actor->id,
                'payment_verified_by_name' => $actor->full_name,
                'payment_verified_at' => now(),
                'payment_rejection_reason' => $decision === 'rejected' ? $reason : null,
            ]);

            $loaded = $group->refresh()->load('applications');

            if ($decision === 'approved') {
                // Endi o'quv bo'limiga jo'natamiz
                foreach ($loaded->applications as $app) {
                    if ($app->isDualApproved() && $app->academic_dept_status === RetakeApplication::STATUS_PENDING) {
                        $this->notificationService->notifyAcademicReady($app);
                    }
                }
                $this->notificationService->notifyPaymentVerified($loaded);
            } else {
                $this->notificationService->notifyPaymentRejected($loaded, $reason);
            }

            return $loaded;
        });
    }

    /**
     * Agar guruh dual-approved holatga kelgan bo'lsa va to'lov hali yuklanmagan
     * bo'lsa — talabaga to'lov chekini yuklash kerakligi haqida xabar yuboriladi.
     * Har bir guruh uchun bir martagina yuborilishi kerak — `notifyPaymentRequired`
     * ichida buni boshqaramiz (yangi log action emas).
     */
    private function maybeNotifyPaymentRequired(RetakeApplication $app): void
    {
        if (!$app->isDualApproved()) {
            return;
        }
        if ($app->academic_dept_status !== RetakeApplication::STATUS_PENDING) {
            return;
        }

        $group = $app->group()->with('applications')->first();
        if (!$group) {
            return;
        }
        if ($group->payment_uploaded_at !== null) {
            return;
        }

        $this->notificationService->notifyPaymentRequired($group);
    }

    /**
     * dean+registrator qarorlari asosida final_status'ni qayta hisoblash.
     * Yakuniy holat o'zgarsa — guruh hujjatlari tayyor bo'lganini tekshiramiz.
     */
    private function recomputeFinalStatus(RetakeApplication $app): void
    {
        // Birortasi rad etgan? → darhol rejected
        if ($app->dean_status === RetakeApplication::STATUS_REJECTED) {
            $app->update([
                'final_status' => RetakeApplication::STATUS_REJECTED,
                'rejected_by' => RetakeApplication::REJECTED_BY_DEAN,
            ]);
            $this->maybeGenerateGroupDocuments($app);
            return;
        }
        if ($app->registrar_status === RetakeApplication::STATUS_REJECTED) {
            $app->update([
                'final_status' => RetakeApplication::STATUS_REJECTED,
                'rejected_by' => RetakeApplication::REJECTED_BY_REGISTRAR,
            ]);
            $this->maybeGenerateGroupDocuments($app);
            return;
        }

        // Ikkalasi tasdiqlagan? → academic_dept'ga o'tadi (status pending qoladi)
        if ($app->isDualApproved() && $app->academic_dept_status === RetakeApplication::STATUS_PENDING) {
            return;
        }
    }

    /**
     * Ariza yakuniy holatga kelganda — guruhda barcha arizalar yakunlanganmi?
     * Agar ha bo'lsa, hujjatlar (DOCX + PDF + QR) generatsiya qilinadi.
     */
    public function maybeGenerateGroupDocuments(RetakeApplication $app, ?Teacher $generator = null): void
    {
        $group = $app->group()->with('applications')->first();
        if (!$group) {
            return;
        }
        $this->documentService->generateForGroup($group, $generator);
    }

    private function assertReviewable(RetakeApplication $app): void
    {
        if ($app->final_status === RetakeApplication::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'application' => 'Bu ariza allaqachon rad etilgan, lekin sizning qarroringiz audit log uchun yoziladi',
            ]);
        }
    }

    private function assertDecision(string $decision): void
    {
        if (!in_array($decision, [RetakeApplication::STATUS_APPROVED, RetakeApplication::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'decision' => 'Noto\'g\'ri qaror',
            ]);
        }
    }

    private function assertReason(?string $reason): void
    {
        $min = RetakeSetting::rejectReasonMinLength();
        if ($reason === null || mb_strlen(trim($reason)) < $min) {
            throw ValidationException::withMessages([
                'reason' => "Sabab eng kamida {$min} belgidan iborat bo'lishi kerak",
            ]);
        }
    }

    /**
     * Audit log yozish. $actor — Teacher, Student yoki null (system).
     */
    private function log(
        RetakeApplication $app,
        string $action,
        Teacher|Student|null $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        $userId = $actor?->id;
        $userType = match (true) {
            $actor instanceof Teacher => 'teacher',
            $actor instanceof Student => 'student',
            default => 'system',
        };
        $userName = $actor?->full_name;

        RetakeApplicationLog::create([
            'application_id' => $app->id,
            'group_id' => $app->group_id,
            'user_id' => $userId,
            'user_type' => $userType,
            'user_name' => $userName,
            'action' => $action,
            'from_status' => $metadata['from'] ?? null,
            'to_status' => $metadata['to'] ?? null,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}

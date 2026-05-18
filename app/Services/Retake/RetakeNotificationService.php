<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

/**
 * Qayta o'qish arizalari bo'yicha bildirishnomalar.
 *
 * Ikki kanal:
 *  - Tizim ichi (StudentNotification / TeacherNotification)
 *  - Telegram (TelegramService::sendToUser)
 */
class RetakeNotificationService
{
    public function __construct(
        private TelegramService $telegram,
    ) {}

    // ─── Talabaga bildirishnomalar ─────────────────────────────────

    public function notifyDeanDecision(RetakeApplication $app): void
    {
        $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        if (!$student) return;

        $isApproved = $app->dean_status === RetakeApplication::STATUS_APPROVED;
        $title = $isApproved
            ? "Dekan arizangizni tasdiqladi"
            : "Dekan arizangizni rad etdi";

        $body = $isApproved
            ? "Fan: {$app->subject_name}. Endi registrator tasdiqi kutilmoqda."
            : "Fan: {$app->subject_name}. Sabab: " . ($app->dean_reason ?? '—');

        $this->sendToStudent($student, 'retake_dean_decision', $title, $body, $app->id);
    }

    public function notifyRegistrarDecision(RetakeApplication $app): void
    {
        $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        if (!$student) return;

        $isApproved = $app->registrar_status === RetakeApplication::STATUS_APPROVED;
        $title = $isApproved
            ? "Registrator arizangizni tasdiqladi"
            : "Registrator arizangizni rad etdi";

        $body = $isApproved
            ? "Fan: {$app->subject_name}. Endi dekan tasdiqi kutilmoqda."
            : "Fan: {$app->subject_name}. Sabab: " . ($app->registrar_reason ?? '—');

        $this->sendToStudent($student, 'retake_registrar_decision', $title, $body, $app->id);
    }

    public function notifyAcademicDecision(RetakeApplication $app): void
    {
        $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        if (!$student) return;

        $isApproved = $app->academic_dept_status === RetakeApplication::STATUS_APPROVED;
        if ($isApproved) {
            $group = $app->retakeGroup;
            $body = "Fan: {$app->subject_name}. ";
            if ($group) {
                $body .= "Guruh: {$group->name}, o'qituvchi: " . ($group->teacher_name ?? '—')
                    . ", sanalar: " . $group->start_date?->format('Y-m-d')
                    . " → " . $group->end_date?->format('Y-m-d') . ".";
            }
            $title = "Qayta o'qish arizangiz tasdiqlandi ✓";
        } else {
            $title = "Qayta o'qish arizangiz O'quv bo'limi tomonidan rad etildi";
            $body = "Fan: {$app->subject_name}. Sabab: " . ($app->academic_dept_reason ?? '—');
        }

        $this->sendToStudent($student, 'retake_academic_decision', $title, $body, $app->id);
    }

    /**
     * Talaba dekan + registrator tasdig'idan keyin to'lov chekini yuklashi kerak.
     */
    public function notifyPaymentRequired(RetakeApplicationGroup $group): void
    {
        $student = $group->student;
        if (!$student) return;

        $title = "To'lov chekingizni yuklang";
        $body = "Dekan va registrator arizangizni tasdiqlashdi. Jarayonni davom ettirish uchun to'lov qog'ozini yuklang (max 5 MB).";

        $this->sendToStudent(
            $student,
            'retake_payment_required',
            $title,
            $body,
            $group->id,
            route('student.retake.index'),
        );
    }

    /**
     * To'lov yuklandi — talabaga: registrator tekshirishi kutilmoqda.
     */
    public function notifyPaymentSubmitted(RetakeApplicationGroup $group): void
    {
        $student = $group->student;
        if (!$student) return;

        $title = "To'lov chekingiz qabul qilindi";
        $body = "Registrator ofisi to'lov chekining haqiqiyligini tekshiradi. Tasdiqlanganidan so'ng ariza o'quv bo'limiga jo'natiladi.";

        $this->sendToStudent(
            $student,
            'retake_payment_submitted',
            $title,
            $body,
            $group->id,
            route('student.retake.index'),
        );
    }

    /**
     * Registrator ofisiga: yangi to'lov cheki tekshirish uchun keldi.
     */
    public function notifyPaymentToVerify(RetakeApplicationGroup $group): void
    {
        $student = $group->student;
        if (!$student) return;

        $title = "To'lov cheki tekshirilishi kutilmoqda";
        $body = "{$student->full_name} ({$student->level_name}) qayta o'qish uchun to'lov chekini yukladi. Haqiqiyligini tekshiring.";

        $registrars = Teacher::role(\App\Enums\ProjectRole::REGISTRAR_OFFICE->value)
            ->where('status', true)
            ->get();

        foreach ($registrars as $r) {
            $this->sendToTeacher($r, 'retake_payment_to_verify', $title, $body, $group->id, route('admin.retake.index'));
        }
    }

    /**
     * Registrator to'lov chekini tasdiqladi — ariza o'quv bo'limiga jo'natildi.
     */
    public function notifyPaymentVerified(RetakeApplicationGroup $group): void
    {
        $student = $group->student;
        if (!$student) return;

        $title = "Arizangiz o'quv bo'limiga yuborildi";
        $body = "To'lov chekingiz registrator tomonidan tasdiqlandi. Endi ariza o'quv bo'limi tomonidan ko'rib chiqiladi.";

        $this->sendToStudent(
            $student,
            'retake_payment_verified',
            $title,
            $body,
            $group->id,
            route('student.retake.index'),
        );
    }

    /**
     * Registrator to'lov chekini rad etdi — talabaga sabab bilan xabar.
     */
    public function notifyPaymentRejected(RetakeApplicationGroup $group, ?string $reason = null): void
    {
        $student = $group->student;
        if (!$student) return;

        $title = "To'lov chekingiz rad etildi";
        $body = "Registrator to'lov chekingizni rad etdi. Sabab: " . ($reason ?? '—') . ". Iltimos, to'lov chekini qayta yuklang.";

        $this->sendToStudent(
            $student,
            'retake_payment_rejected',
            $title,
            $body,
            $group->id,
            route('student.retake.index'),
        );
    }

    public function notifyAutoCancelled(RetakeApplication $app): void
    {
        $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        if (!$student) return;

        $this->sendToStudent(
            $student,
            'retake_auto_cancelled',
            "Qayta o'qish arizangiz avtomatik bekor qilindi",
            "Fan: {$app->subject_name}. Sabab: HEMIS'da baho paydo bo'lgan yoki qarzdorlik holati o'zgargan.",
            $app->id,
        );
    }

    /**
     * Talabaga yakuniy hujjatlar (DOCX + PDF) tayyor degan xabar.
     */
    public function notifyDocumentsReady(RetakeApplicationGroup $group): void
    {
        if (!$group->student) return;
        if (!$group->pdf_certificate_path) return;

        $title = "Qayta o'qish tasdiqnomangiz tayyor";
        $body = "DOCX (ariza) va PDF (tasdiqnoma) sahifangizdan yuklab olishingiz mumkin.";

        $this->sendToStudent(
            $group->student,
            'retake_documents_ready',
            $title,
            $body,
            $group->id,
            route('student.retake.index'),
        );
    }

    // ─── Tasdiqlovchilarga bildirishnomalar ────────────────────────

    /**
     * Yangi ariza yuborilganda — dekan(lar)ga + registrator(lar)ga.
     */
    public function notifyNewSubmission(RetakeApplicationGroup $group): void
    {
        $student = $group->student;
        if (!$student) return;

        $count = $group->applications()->count();
        $title = "Yangi qayta o'qish arizasi";
        $body = "{$student->full_name} ({$student->level_name}) — {$count} ta fan. Tasdiqlash kutilmoqda.";

        // Dekan (talaba fakultetining)
        $deans = Teacher::role(\App\Enums\ProjectRole::DEAN->value)
            ->where('status', true)
            ->get()
            ->filter(function (Teacher $t) use ($student) {
                return in_array((int) $student->department_id, array_map('intval', $t->deanFacultyIds), true);
            });

        foreach ($deans as $dean) {
            $this->sendToTeacher($dean, 'retake_new_submission', $title, $body, $group->id, route('admin.retake.index'));
        }

        // Registrator ofisi (universitet bo'yicha hammaga)
        $registrars = Teacher::role(\App\Enums\ProjectRole::REGISTRAR_OFFICE->value)
            ->where('status', true)
            ->get();

        foreach ($registrars as $r) {
            $this->sendToTeacher($r, 'retake_new_submission', $title, $body, $group->id, route('admin.retake.index'));
        }
    }

    /**
     * O'quv bo'limiga keldi (dean+registrator approved).
     */
    public function notifyAcademicReady(RetakeApplication $app): void
    {
        $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        $studentName = $student?->full_name ?? 'talaba';

        $title = "O'quv bo'limi tasdiqi kutilmoqda";
        $body = "{$studentName} — {$app->subject_name} ({$app->semester_name}). Dekan va registrator tasdiqlagan.";

        $academicStaff = Teacher::role([
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT->value,
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ])->where('status', true)->get();

        foreach ($academicStaff as $t) {
            $this->sendToTeacher($t, 'retake_academic_ready', $title, $body, $app->id, route('admin.retake-groups.index'));
        }
    }

    // ─── Quyi metodlar ─────────────────────────────────────────────

    /**
     * Oyna sanalari o'zgarganda — shu oynaga mos talabalarga xabar yuboradi.
     * Mos talabalar: window.specialty_id YOKI specialty_name + level_code bo'yicha.
     * Faqat telegram_chat_id mavjud talabalarga yuboriladi.
     */
    public function notifyWindowDatesUpdated(\App\Models\RetakeApplicationWindow $window): int
    {
        $sent = 0;
        $students = $this->matchingStudentsForWindow($window);

        $title = "📅 Qayta o'qish sanalari yangilandi";
        $body = "Yo'nalish: {$window->specialty_name}\n"
            . "Kurs: " . ($window->level_name ?? $window->level_code) . "\n\n"
            . "Yangi sanalar: " . $window->start_date->format('Y-m-d')
            . " → " . $window->end_date->format('Y-m-d') . "\n\n"
            . "📌 Qayta o'qish sanasi boshlangunga qadar (shu kuni ham)\n"
            . "arizangizni yubora olasiz.\n\n"
            . "🔗 " . route('student.retake.index');

        foreach ($students as $student) {
            $this->sendToStudent($student, 'retake_window_dates_updated', $title, $body, $window->id, route('student.retake.index'));
            $sent++;
            usleep(40000);
        }

        return $sent;
    }

    /**
     * Yangi oyna ochilganda — shu oynaga mos talabalarga xabar yuboradi.
     */
    public function notifyWindowOpened(\App\Models\RetakeApplicationWindow $window): int
    {
        $sent = 0;
        $students = $this->matchingStudentsForWindow($window);

        $deptName = '';
        if (!empty($window->department_hemis_id)) {
            $dept = \App\Models\Department::where('department_hemis_id', $window->department_hemis_id)->first();
            $deptName = $dept?->name ?? '';
        }

        $title = "🆕 Qayta o'qish qabul oynasi ochildi!";
        $body = "Yo'nalish: {$window->specialty_name}\n"
            . "Kurs: " . ($window->level_name ?? $window->level_code) . "\n"
            . ($deptName ? "Fakultet: {$deptName}\n" : '')
            . "\nSanalar: " . $window->start_date->format('Y-m-d')
            . " → " . $window->end_date->format('Y-m-d') . "\n\n"
            . "📌 Siz " . $window->start_date->format('Y-m-d') . " kuniga qadar\n"
            . "ariza yubora olasiz (shu kuni ham ariza qabul ochiq).\n\n"
            . "🔗 " . route('student.retake.index');

        foreach ($students as $student) {
            $this->sendToStudent($student, 'retake_window_opened', $title, $body, $window->id, route('student.retake.index'));
            $sent++;
            usleep(40000);
        }

        return $sent;
    }

    /**
     * Oynaga mos keluvchi talabalarni topish (specialty_id YOKI name + level_code).
     * Faqat telegram_chat_id mavjudlar qaytariladi (xabar yuborish uchun).
     */
    private function matchingStudentsForWindow(\App\Models\RetakeApplicationWindow $window): \Illuminate\Database\Eloquent\Collection
    {
        $query = Student::query()
            ->whereNotNull('telegram_chat_id')
            ->where('level_code', $window->level_code)
            ->where(function ($q) use ($window) {
                $q->where('specialty_id', (int) $window->specialty_id);
                if (!empty($window->specialty_name)) {
                    $q->orWhereRaw('LOWER(TRIM(specialty_name)) = ?', [mb_strtolower(trim($window->specialty_name))]);
                }
            });

        if (!empty($window->department_hemis_id)) {
            $query->where('department_id', $window->department_hemis_id);
        }

        return $query->get();
    }

    private function sendToStudent(
        Student $student,
        string $type,
        string $title,
        string $message,
        ?int $refId = null,
        ?string $url = null,
    ): void {
        try {
            StudentNotification::create([
                'student_id' => $student->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $url ?? route('student.retake.index'),
                'data' => $refId ? ['retake_ref_id' => $refId] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[RetakeNotification] StudentNotification: " . $e->getMessage());
        }

        if (!empty($student->telegram_chat_id)) {
            $this->telegram->sendToUser(
                (string) $student->telegram_chat_id,
                $this->telegramText($title, $message),
            );
        }
    }

    private function sendToTeacher(
        Teacher $teacher,
        string $type,
        string $title,
        string $message,
        ?int $refId = null,
        ?string $url = null,
    ): void {
        $link = $url ?? route('admin.retake.index');

        try {
            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'data' => $refId ? ['retake_ref_id' => $refId] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[RetakeNotification] TeacherNotification: " . $e->getMessage());
        }

        // Yuqori bell ikonasidagi xabarlar paneli `Notification` modelidan o'qiydi —
        // shuning uchun retake bildirishnomalari u yerda ham ko'rinishi uchun yozamiz.
        try {
            \App\Models\Notification::create([
                'recipient_id' => $teacher->id,
                'recipient_type' => Teacher::class,
                'subject' => $title,
                'body' => $message,
                'type' => $type,
                'url' => $link,
                'data' => $refId ? ['retake_ref_id' => $refId] : null,
                'is_read' => false,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("[RetakeNotification] Notification: " . $e->getMessage());
        }

        if (!empty($teacher->telegram_chat_id)) {
            $this->telegram->sendToUser(
                (string) $teacher->telegram_chat_id,
                $this->telegramText($title, $message),
            );
        }
    }

    private function telegramText(string $title, string $message): string
    {
        return "<b>" . e($title) . "</b>\n\n" . e($message);
    }
}

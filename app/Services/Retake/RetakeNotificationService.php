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
        try {
            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'link' => $url ?? route('admin.retake.index'),
                'data' => $refId ? ['retake_ref_id' => $refId] : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[RetakeNotification] TeacherNotification: " . $e->getMessage());
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

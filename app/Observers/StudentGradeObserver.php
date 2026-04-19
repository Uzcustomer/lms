<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StudentGradeObserver
{
    /**
     * Yangi baho yaratilganda talabaga Telegram xabar yuborish
     */
    public function created(StudentGrade $grade): void
    {
        $this->sendGradeNotification($grade);
        $this->sendAdminNotification('create', $grade);
    }

    /**
     * Baho yangilanganda talabaga Telegram xabar yuborish
     */
    public function updated(StudentGrade $grade): void
    {
        if ($grade->wasChanged('grade') && $grade->grade !== null) {
            $this->sendGradeNotification($grade);
        }

        $watched = ['grade', 'retake_grade', 'retake_graded_at', 'retake_file_path', 'retake_by', 'status', 'reason'];
        $changed = collect($watched)->filter(fn ($f) => $grade->wasChanged($f))->values()->all();
        if (!empty($changed)) {
            $this->sendAdminNotification('update', $grade, $changed);
        }
    }

    /**
     * Baho o'chirilganda admin Telegramga xabar yuborish
     */
    public function deleted(StudentGrade $grade): void
    {
        $this->sendAdminNotification('delete', $grade);
    }

    /**
     * Telegram orqali baho haqida xabar yuborish
     */
    private function sendGradeNotification(StudentGrade $grade): void
    {
        try {
            // Faqat test baholarni xabar qilish (test_id bor bo'lsa)
            if (empty($grade->test_id)) {
                return;
            }

            // Baho bo'sh bo'lsa yubormaslik
            if ($grade->grade === null || $grade->grade === '') {
                return;
            }

            // Status recorded bo'lishi kerak
            if ($grade->status !== 'recorded') {
                return;
            }

            $student = Student::find($grade->student_id);
            if (!$student) {
                return;
            }

            // Telegram verified bo'lishi kerak
            if (empty($student->telegram_chat_id) || empty($student->telegram_verified_at)) {
                return;
            }

            $lessonDate = $grade->lesson_date
                ? \Carbon\Carbon::parse($grade->lesson_date)->format('d.m.Y')
                : now()->format('d.m.Y');

            $appealUrl = url('/student/appeals/create');

            $message = "📋 <b>Yangi baho qo'yildi!</b>\n\n"
                . "📅 Sana: <b>{$lessonDate}</b>\n"
                . "📚 Fan: <b>{$grade->subject_name}</b>\n"
                . "📝 Turi: <b>" . ($grade->training_type_name ?? 'Yakuniy test') . "</b>\n"
                . "🎯 Baho: <b>{$grade->grade} ball</b>\n\n"
                . "❗ Agar bahodan norozi bo'lsangiz, <b>24 soat</b> ichida apellyatsiya topshirishingiz mumkin.\n\n"
                . "📎 <a href=\"{$appealUrl}\">Apellyatsiya topshirish</a>";

            $telegram = new TelegramService();
            $telegram->sendToUser($student->telegram_chat_id, $message);

        } catch (\Throwable $e) {
            Log::error('Telegram baho xabar yuborishda xato: ' . $e->getMessage(), [
                'student_grade_id' => $grade->id,
                'student_id' => $grade->student_id,
            ]);
        }
    }

    /**
     * Admin Telegram chatiga (kechqurungi xabar boradigan group) baho o'zgarishi haqida xabar yuborish
     */
    private function sendAdminNotification(string $action, StudentGrade $grade, array $changedFields = []): void
    {
        try {
            $chatId = config('services.telegram.attendance_group_id');
            if (empty($chatId)) {
                return;
            }

            $actor = $this->resolveActor();
            $studentName = optional(Student::find($grade->student_id))->full_name ?? $grade->student_hemis_id ?? '—';
            $lessonDate = $grade->lesson_date
                ? \Carbon\Carbon::parse($grade->lesson_date)->format('d.m.Y')
                : '—';

            $isRetake = ($action === 'create' && !empty($grade->retake_grade))
                || ($action === 'update' && in_array('retake_grade', $changedFields, true));

            $title = match (true) {
                $action === 'delete' => "🗑 <b>Baho o‘chirildi</b>",
                $isRetake => "♻️ <b>Otrabotka bahosi qo‘yildi/o‘zgartirildi</b>",
                $action === 'create' => "🆕 <b>Yangi baho yaratildi</b>",
                default => "✏️ <b>Baho o‘zgartirildi</b>",
            };

            $lines = [
                $title,
                '',
                "👤 Talaba: <b>" . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . "</b>",
                "📚 Fan: <b>" . htmlspecialchars((string) $grade->subject_name, ENT_QUOTES, 'UTF-8') . "</b>",
                "📝 Turi: <b>" . htmlspecialchars((string) ($grade->training_type_name ?? '—'), ENT_QUOTES, 'UTF-8') . "</b>",
                "📅 Dars: <b>{$lessonDate}</b>",
                "👨‍🏫 O‘qituvchi: <b>" . htmlspecialchars((string) ($grade->employee_name ?? '—'), ENT_QUOTES, 'UTF-8') . "</b>",
            ];

            if ($action === 'update' && !empty($changedFields)) {
                $lines[] = '';
                $lines[] = '<b>O‘zgargan maydonlar:</b>';
                foreach ($changedFields as $field) {
                    $old = $grade->getOriginal($field);
                    $new = $grade->getAttribute($field);
                    $lines[] = "• {$field}: <code>" . $this->fmt($old) . "</code> → <code>" . $this->fmt($new) . "</code>";
                }
            } elseif ($action === 'create') {
                $lines[] = '';
                $lines[] = "🎯 Baho: <b>" . $this->fmt($grade->grade) . "</b>";
                if (!empty($grade->retake_grade)) {
                    $lines[] = "♻️ Otrabotka bahosi: <b>" . $this->fmt($grade->retake_grade) . "</b>";
                }
            } elseif ($action === 'delete') {
                $lines[] = '';
                $lines[] = "🎯 Baho: <b>" . $this->fmt($grade->grade) . "</b>";
                if (!empty($grade->retake_grade)) {
                    $lines[] = "♻️ Otrabotka bahosi: <b>" . $this->fmt($grade->retake_grade) . "</b>";
                }
            }

            $lines[] = '';
            $lines[] = "🛠 Kim: <b>" . htmlspecialchars($actor, ENT_QUOTES, 'UTF-8') . "</b>";
            $lines[] = "🕒 Vaqt: <b>" . now('Asia/Tashkent')->format('d.m.Y H:i') . "</b>";

            (new TelegramService())->sendToUser((string) $chatId, implode("\n", $lines));

        } catch (\Throwable $e) {
            Log::error('Admin Telegram baho xabari yuborishda xato: ' . $e->getMessage(), [
                'student_grade_id' => $grade->id,
                'action' => $action,
            ]);
        }
    }

    private function fmt($value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function resolveActor(): string
    {
        foreach (['web', 'teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                $name = $user->name ?? $user->full_name ?? $user->short_name ?? ('user#' . $user->id);
                return $name . ' (' . $guard . ')';
            }
        }
        return 'system';
    }
}

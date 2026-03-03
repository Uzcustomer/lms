<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;

class StudentGradeObserver
{
    /**
     * Yangi baho yaratilganda talabaga Telegram xabar yuborish
     */
    public function created(StudentGrade $grade): void
    {
        $this->sendGradeNotification($grade);
    }

    /**
     * Baho yangilanganda talabaga Telegram xabar yuborish
     */
    public function updated(StudentGrade $grade): void
    {
        if ($grade->wasChanged('grade') && $grade->grade !== null) {
            $this->sendGradeNotification($grade);
        }
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
}

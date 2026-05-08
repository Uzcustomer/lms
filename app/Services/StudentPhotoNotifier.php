<?php

namespace App\Services;

use App\Models\StudentPhoto;
use App\Models\Teacher;
use Illuminate\Support\Facades\Log;

class StudentPhotoNotifier
{
    public function __construct(private TelegramService $telegram) {}

    public function notifyApproved(StudentPhoto $photo): void
    {
        $teacher = $this->resolveTeacher($photo);
        if (!$teacher || empty($teacher->telegram_chat_id)) {
            return;
        }

        $text = "✅ Talaba rasmi to'liq tasdiqlandi\n\n"
              . "Talaba: {$photo->full_name}\n"
              . "Guruh: " . ($photo->group_name ?: '—') . "\n\n"
              . "Rasm Moodle'da FaceID uchun tayyor.";

        $this->send($teacher, $text, $photo);
    }

    public function notifyRejected(StudentPhoto $photo, ?string $reason = null): void
    {
        $teacher = $this->resolveTeacher($photo);
        if (!$teacher || empty($teacher->telegram_chat_id)) {
            return;
        }

        $text = "❌ Talaba rasmi rad etildi\n\n"
              . "Talaba: {$photo->full_name}\n"
              . "Guruh: " . ($photo->group_name ?: '—') . "\n"
              . "Sabab: " . ($reason ?: 'Standartlarga mos emas') . "\n\n"
              . "Iltimos, talaba rasmi standartlarga (tirsakdan yuqori, oq xalatda, oq fonda) mos ravishda qayta yuklang.";

        $this->send($teacher, $text, $photo);
    }

    private function resolveTeacher(StudentPhoto $photo): ?Teacher
    {
        $teacher = null;
        if ($photo->uploaded_by_teacher_id) {
            $teacher = Teacher::find($photo->uploaded_by_teacher_id);
        }
        if (!$teacher && $photo->uploaded_by) {
            $teacher = Teacher::where('full_name', $photo->uploaded_by)->first();
        }
        return $teacher;
    }

    private function send(Teacher $teacher, string $text, StudentPhoto $photo): void
    {
        try {
            $this->telegram->sendAndGetId((string) $teacher->telegram_chat_id, $text);
        } catch (\Throwable $e) {
            Log::warning('Tyutorga telegram bildirish yuborilmadi', [
                'photo_id' => $photo->id,
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

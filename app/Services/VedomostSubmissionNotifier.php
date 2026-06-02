<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Setting;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\User;
use App\Models\VedomostSubmission;
use Illuminate\Support\Facades\Log;

class VedomostSubmissionNotifier
{
    public function __construct(private TelegramService $telegram)
    {
    }

    /**
     * Status o'zgarganda o'qituvchi, fan mas'uli va kafedra mudiriga xabar.
     * Rad etilganda — qo'shimcha o'quv prorektoriga bildirgi.
     */
    /** Bildirishnomalar yoqilganmi (admin toggle orqali boshqariladi). */
    public static function enabled(): bool
    {
        return Setting::get('vedomost_notify_enabled', '0') === '1';
    }

    public function notifyStatusChange(VedomostSubmission $v): void
    {
        // Test davrida o'chirib qo'yilgan bo'lsa — hech qanday xabar yuborilmaydi
        if (!self::enabled()) {
            return;
        }

        [$title, $body] = $this->statusText($v);

        $recipients = array_values(array_unique(array_filter([
            $v->teacher_hemis_id,
            $v->fan_masuli_hemis_id,
            $v->kafedra_mudiri_hemis_id,
        ])));

        if (!empty($recipients)) {
            $teachers = Teacher::whereIn('hemis_id', $recipients)->get();
            foreach ($teachers as $teacher) {
                $this->sendToTeacher($teacher, $title, $body, $v);
            }
        }

        if ($v->status === VedomostSubmission::STATUS_REJECTED) {
            $this->notifyProrektor($v);
        }
    }

    private function statusText(VedomostSubmission $v): array
    {
        $ctx = "{$v->group_name} — {$v->subject_name}";

        return match ($v->status) {
            VedomostSubmission::STATUS_RECEIVED => [
                'Vedomost tekshirish uchun qabul qilindi',
                "{$ctx} vedomosti registrator ofisi tomonidan qabul qilindi.",
            ],
            VedomostSubmission::STATUS_REVIEWING => [
                'Vedomost tekshirilmoqda',
                "{$ctx} vedomosti tekshirilmoqda.",
            ],
            VedomostSubmission::STATUS_APPROVED => [
                'Vedomost tasdiqlandi',
                "{$ctx} vedomosti tasdiqlandi. ✅",
            ],
            VedomostSubmission::STATUS_REJECTED => [
                'Vedomost rad etildi',
                "{$ctx} vedomosti rad etildi.\nSabab: " . ($v->rejection_reason ?: '—'),
            ],
            default => [
                'Vedomost holati o\'zgardi',
                "{$ctx}: {$v->status_label}",
            ],
        };
    }

    private function sendToTeacher(Teacher $teacher, string $title, string $body, VedomostSubmission $v): void
    {
        try {
            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => 'vedomost_submission',
                'title' => $title,
                'message' => $body,
                'link' => null,
                'data' => ['vedomost_submission_id' => $v->id, 'status' => $v->status],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[VedomostNotifier] TeacherNotification: ' . $e->getMessage());
        }

        if (!empty($teacher->telegram_chat_id)) {
            try {
                $this->telegram->sendToUser((string) $teacher->telegram_chat_id, $this->tgText($title, $body));
            } catch (\Throwable $e) {
                Log::warning('[VedomostNotifier] Telegram: ' . $e->getMessage());
            }
        }
    }

    /**
     * O'quv prorektoriga bildirgi (xato vedomost taqdim qilgan o'qituvchi haqida).
     */
    private function notifyProrektor(VedomostSubmission $v): void
    {
        $title = 'Xato vedomost taqdim etildi';
        $body = "O'qituvchi: {$v->teacher_name}\nFan: {$v->subject_name}\nGuruh: {$v->group_name}\nKafedra: {$v->department_name}\nSabab: " . ($v->rejection_reason ?: '—');

        // Teacher rolidagi prorektorlar
        $teachers = Teacher::whereHas('roles', fn($q) => $q->where('name', 'oquv_prorektori'))->get();
        foreach ($teachers as $teacher) {
            $this->sendToTeacher($teacher, $title, $body, $v);
        }

        // User rolidagi prorektorlar (agar alohida foydalanuvchi bo'lsa)
        try {
            $users = User::whereHas('roles', fn($q) => $q->where('name', 'oquv_prorektori'))->get();
            foreach ($users as $user) {
                try {
                    Notification::create([
                        'recipient_id' => $user->id,
                        'recipient_type' => User::class,
                        'subject' => $title,
                        'body' => $body,
                        'type' => 'alert',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[VedomostNotifier] Prorektor Notification: ' . $e->getMessage());
                }
                if (!empty($user->telegram_chat_id)) {
                    try {
                        $this->telegram->sendToUser((string) $user->telegram_chat_id, $this->tgText($title, $body));
                    } catch (\Throwable $e) {
                        Log::warning('[VedomostNotifier] Prorektor Telegram: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[VedomostNotifier] Prorektor users: ' . $e->getMessage());
        }

        $v->forceFill(['prorektor_notified_at' => now()])->saveQuietly();
    }

    private function tgText(string $title, string $body): string
    {
        return "📋 <b>" . e($title) . "</b>\n\n" . e($body);
    }
}

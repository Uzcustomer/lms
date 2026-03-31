<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class CheckVisaExpiryCommand extends Command
{
    protected $signature = 'visa:check-expiry';
    protected $description = 'Xalqaro talabalar viza va propiska muddatlarini tekshirish va bildirishnoma yuborish';

    public function handle(): int
    {
        $telegram = app(TelegramService::class);

        $visaInfos = StudentVisaInfo::with('student')
            ->whereNotNull('registration_end_date')
            ->orWhereNotNull('visa_end_date')
            ->get();

        foreach ($visaInfos as $info) {
            $student = $info->student;
            if (!$student) continue;

            $this->checkRegistration($info, $student, $telegram);
            $this->checkVisa($info, $student, $telegram);
            $this->checkPassportHandover($info, $student, $telegram);
        }

        $this->info('Viza va propiska tekshiruvi tugadi.');

        return self::SUCCESS;
    }

    private function checkRegistration(StudentVisaInfo $info, Student $student, TelegramService $telegram): void
    {
        $daysLeft = $info->registrationDaysLeft();
        if ($daysLeft === null) return;

        $level = $this->getRegistrationNotificationLevel($daysLeft);
        if (!$level) return;

        $message = $this->buildRegistrationMessage($daysLeft, $level);
        $this->sendNotification($student, $telegram, $message, $level, 'propiska');
    }

    private function checkVisa(StudentVisaInfo $info, Student $student, TelegramService $telegram): void
    {
        $daysLeft = $info->visaDaysLeft();
        if ($daysLeft === null) return;

        $level = $this->getVisaNotificationLevel($daysLeft);
        if (!$level) return;

        $message = $this->buildVisaMessage($daysLeft, $level);
        $this->sendNotification($student, $telegram, $message, $level, 'visa');
    }

    private function checkPassportHandover(StudentVisaInfo $info, Student $student, TelegramService $telegram): void
    {
        if ($info->passport_handed_over) return;

        $regDays = $info->registrationDaysLeft();
        $visaDays = $info->visaDaysLeft();

        // Faqat propiska yoki viza muddati yaqinlashganda pasport ogohlantirishi
        $shouldWarn = ($regDays !== null && $regDays <= 7) || ($visaDays !== null && $visaDays <= 30);
        if (!$shouldWarn) return;

        $message = "⚠️ Diqqat! Pasportingizni registrator ofisi xodimiga topshirishingiz kerak. Iltimos, tezroq topshiring.";

        $this->sendNotification($student, $telegram, $message, 'danger', 'passport');
    }

    private function getRegistrationNotificationLevel(int $daysLeft): ?string
    {
        if ($daysLeft <= 3) return 'danger';
        if ($daysLeft <= 5) return 'warning';
        if ($daysLeft <= 7) return 'info';

        return null;
    }

    private function getVisaNotificationLevel(int $daysLeft): ?string
    {
        if ($daysLeft <= 15) return 'danger';
        if ($daysLeft <= 20) return 'warning';
        if ($daysLeft <= 30) return 'info';

        return null;
    }

    private function buildRegistrationMessage(int $daysLeft, string $level): string
    {
        $emoji = match($level) {
            'danger' => '🔴',
            'warning' => '🟡',
            'info' => '🟢',
        };

        if ($daysLeft <= 0) {
            return "{$emoji} Vaqtinchalik ro'yxatga qo'yish (propiska) muddati tugagan! Iltimos, zudlik bilan registrator ofisiga murojaat qiling.";
        }

        return "{$emoji} Vaqtinchalik ro'yxatga qo'yish (propiska) muddati tugashiga {$daysLeft} kun qoldi. Iltimos, muddatini uzaytiring.";
    }

    private function buildVisaMessage(int $daysLeft, string $level): string
    {
        $emoji = match($level) {
            'danger' => '🔴',
            'warning' => '🟡',
            'info' => '🟢',
        };

        if ($daysLeft <= 0) {
            return "{$emoji} Viza muddati tugagan! Iltimos, zudlik bilan registrator ofisiga murojaat qiling.";
        }

        return "{$emoji} Viza muddati tugashiga {$daysLeft} kun qoldi. Iltimos, vizangizni yangilang.";
    }

    private function sendNotification(Student $student, TelegramService $telegram, string $message, string $level, string $type): void
    {
        // Saytda bildirishnoma
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => match($type) {
                'propiska' => 'Propiska muddati ogohlantirishi',
                'visa' => 'Viza muddati ogohlantirishi',
                'passport' => 'Pasport topshirish ogohlantirishi',
            },
            'message' => $message,
            'data' => ['level' => $level, 'type' => $type],
        ]);

        // Telegram orqali xabar
        if ($student->telegram_chat_id) {
            $telegram->sendToUser($student->telegram_chat_id, $message);
        }
    }
}

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
            ->where(function ($q) {
                $q->whereNotNull('registration_end_date')
                  ->orWhereNotNull('visa_end_date');
            })
            ->get();

        $sent = 0;

        foreach ($visaInfos as $info) {
            $student = $info->student;
            if (!$student) continue;

            $sent += $this->checkRegistration($info, $student, $telegram);
            $sent += $this->checkVisa($info, $student, $telegram);
            $sent += $this->checkPassportHandover($info, $student, $telegram);
        }

        $this->info("Viza va propiska tekshiruvi tugadi. {$sent} ta bildirishnoma yuborildi.");

        return self::SUCCESS;
    }

    private function checkRegistration(StudentVisaInfo $info, Student $student, TelegramService $telegram): int
    {
        $daysLeft = $info->registrationDaysLeft();
        if ($daysLeft === null) return 0;

        // 7 kun va undan kam qolganda bildirishnoma
        if ($daysLeft > 7) return 0;

        // Qizil (3 kun va kam) — har kuni yuboriladi
        // Sariq (5 kun) va Yashil (7 kun) — faqat shu kuni
        if ($daysLeft <= 3) {
            $level = 'danger';
        } elseif ($daysLeft == 4 || $daysLeft == 5) {
            $level = 'warning';
        } elseif ($daysLeft == 6 || $daysLeft == 7) {
            $level = 'info';
        } else {
            return 0;
        }

        $emoji = match($level) { 'danger' => '🔴', 'warning' => '🟡', 'info' => '🟢' };

        if ($daysLeft <= 0) {
            $message = "{$emoji} Vaqtinchalik ro'yxatga qo'yish (propiska) muddati tugagan! Zudlik bilan registrator ofisiga murojaat qiling.";
        } else {
            $message = "{$emoji} Propiska muddati tugashiga {$daysLeft} kun qoldi. Muddatini uzaytiring.";
        }

        $this->sendNotification($student, $telegram, $message, $level, 'propiska');
        return 1;
    }

    private function checkVisa(StudentVisaInfo $info, Student $student, TelegramService $telegram): int
    {
        $daysLeft = $info->visaDaysLeft();
        if ($daysLeft === null) return 0;

        // 30 kun va undan kam qolganda bildirishnoma
        if ($daysLeft > 30) return 0;

        // Qizil (15 kun va kam) — har kuni yuboriladi
        // Sariq (20 kun) va Yashil (30 kun) — shu oraliqda
        if ($daysLeft <= 15) {
            $level = 'danger';
        } elseif ($daysLeft <= 20) {
            $level = 'warning';
        } elseif ($daysLeft <= 30) {
            $level = 'info';
        } else {
            return 0;
        }

        $emoji = match($level) { 'danger' => '🔴', 'warning' => '🟡', 'info' => '🟢' };

        if ($daysLeft <= 0) {
            $message = "{$emoji} Viza muddati tugagan! Zudlik bilan registrator ofisiga murojaat qiling.";
        } else {
            $message = "{$emoji} Viza muddati tugashiga {$daysLeft} kun qoldi. Vizangizni yangilang.";
        }

        $this->sendNotification($student, $telegram, $message, $level, 'visa');
        return 1;
    }

    private function checkPassportHandover(StudentVisaInfo $info, Student $student, TelegramService $telegram): int
    {
        if ($info->passport_handed_over) return 0;

        $regDays = $info->registrationDaysLeft();
        $visaDays = $info->visaDaysLeft();

        $shouldWarn = ($regDays !== null && $regDays <= 7) || ($visaDays !== null && $visaDays <= 30);
        if (!$shouldWarn) return 0;

        $message = "⚠️ Pasportingizni registrator ofisi xodimiga topshirishingiz kerak. Iltimos, tezroq topshiring.";
        $this->sendNotification($student, $telegram, $message, 'danger', 'passport');
        return 1;
    }

    private function sendNotification(Student $student, TelegramService $telegram, string $message, string $level, string $type): void
    {
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

        if ($student->telegram_chat_id) {
            $telegram->sendToUser($student->telegram_chat_id, $message);
        }
    }
}

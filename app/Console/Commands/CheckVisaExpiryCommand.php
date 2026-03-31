<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Models\User;
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
        if ($daysLeft === null || $daysLeft > 7) return 0;

        if ($daysLeft <= 3) {
            $level = 'danger';
        } elseif ($daysLeft <= 5) {
            $level = 'warning';
        } elseif ($daysLeft <= 7) {
            $level = 'info';
        } else {
            return 0;
        }

        $emoji = match($level) { 'danger' => '🔴', 'warning' => '🟡', 'info' => '🟢' };

        if ($daysLeft <= 0) {
            $message = "{$emoji} Propiska muddati tugagan! Zudlik bilan registrator ofisiga murojaat qiling.";
        } else {
            $message = "{$emoji} Propiska muddati tugashiga {$daysLeft} kun qoldi. Muddatini uzaytiring.";
        }

        // Talabaga bildirishnoma
        $this->notifyStudent($student, $telegram, $message, $level, 'propiska');

        // Qizil holatda — firma javobgariga va registrator ofisiga ham
        if ($level === 'danger') {
            $staffMsg = "🔴 {$student->full_name} ({$student->group_name}) — propiska muddati tugashiga {$daysLeft} kun qoldi!";
            if ($daysLeft <= 0) {
                $staffMsg = "🔴 {$student->full_name} ({$student->group_name}) — propiska muddati TUGAGAN!";
            }
            $this->notifyFirmAndRegistrar($info, $telegram, $staffMsg);
        }

        return 1;
    }

    private function checkVisa(StudentVisaInfo $info, Student $student, TelegramService $telegram): int
    {
        $daysLeft = $info->visaDaysLeft();
        if ($daysLeft === null || $daysLeft > 30) return 0;

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

        $this->notifyStudent($student, $telegram, $message, $level, 'visa');

        // Qizil holatda — firma javobgariga va registrator ofisiga ham
        if ($level === 'danger') {
            $staffMsg = "🔴 {$student->full_name} ({$student->group_name}) — viza muddati tugashiga {$daysLeft} kun qoldi!";
            if ($daysLeft <= 0) {
                $staffMsg = "🔴 {$student->full_name} ({$student->group_name}) — viza muddati TUGAGAN!";
            }
            $this->notifyFirmAndRegistrar($info, $telegram, $staffMsg);
        }

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
        $this->notifyStudent($student, $telegram, $message, 'danger', 'passport');
        return 1;
    }

    private function notifyStudent(Student $student, TelegramService $telegram, string $message, string $level, string $type): void
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

    /**
     * Firma javobgariga va registrator ofisi xodimlariga xabar yuborish.
     */
    private function notifyFirmAndRegistrar(StudentVisaInfo $info, TelegramService $telegram, string $message): void
    {
        // Registrator ofisi xodimlariga sayt bildirishnomasi
        $registrarUsers = User::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))->get();
        foreach ($registrarUsers as $regUser) {
            \App\Models\Notification::create([
                'sender_id' => null,
                'sender_type' => null,
                'recipient_id' => $regUser->id,
                'recipient_type' => User::class,
                'subject' => 'Talaba muddati yaqinlashmoqda',
                'body' => $message,
                'type' => 'alert',
                'is_read' => false,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        }

        // Firma javobgariga (assigned_firm bo'yicha)
        if ($info->firm) {
            $firmUsers = User::where('assigned_firm', $info->firm)
                ->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))
                ->get();

            foreach ($firmUsers as $firmUser) {
                \App\Models\Notification::create([
                    'sender_id' => null,
                    'sender_type' => null,
                    'recipient_id' => $firmUser->id,
                    'recipient_type' => User::class,
                    'subject' => 'Talaba muddati yaqinlashmoqda',
                    'body' => $message,
                    'type' => 'alert',
                    'is_read' => false,
                    'is_draft' => false,
                    'sent_at' => now(),
                ]);
            }
        }
    }
}

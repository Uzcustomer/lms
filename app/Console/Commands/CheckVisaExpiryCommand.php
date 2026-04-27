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
    protected $description = 'Xalqaro talabalar viza va registratsiya muddatlarini tekshirish va bildirishnoma yuborish';

    public function handle(): int
    {
        $telegram = app(TelegramService::class);

        $visaInfos = StudentVisaInfo::with('student')
            ->where(function ($q) {
                $q->whereNotNull('registration_end_date')
                  ->orWhereNotNull('visa_end_date');
            })
            ->get();

        // Staff uchun guruhlangan Telegram xabarlari:
        // $groups[$level][$type][] = ['line' => '...', 'firm' => '...']
        $groups = [
            'renewing' => ['registratsiya' => [], 'visa' => []],
            'danger'   => ['registratsiya' => [], 'visa' => []],
            'warning'  => ['registratsiya' => [], 'visa' => []],
            'info'     => ['registratsiya' => [], 'visa' => []],
        ];

        $sent = 0;

        foreach ($visaInfos as $info) {
            $student = $info->student;
            if (!$student) continue;

            $sent += $this->checkRegistration($info, $student, $telegram, $groups);
            $sent += $this->checkVisa($info, $student, $telegram, $groups);
            $sent += $this->checkPassportHandover($info, $student, $telegram);
        }

        // Staff, firma javobgarlari va obunachilarga holat bo'yicha bitta xabar
        $this->dispatchGroupedTelegramMessages($groups, $telegram);

        $this->info("Viza va registratsiya tekshiruvi tugadi. {$sent} ta talaba bo'yicha bildirishnoma yuborildi.");

        return self::SUCCESS;
    }

    private function isInternational(Student $student): bool
    {
        return str_starts_with(strtolower($student->group_name ?? ''), 'xd')
            || str_contains(strtolower($student->citizenship_name ?? ''), 'orijiy');
    }

    private function checkRegistration(StudentVisaInfo $info, Student $student, TelegramService $telegram, array &$groups): int
    {
        $daysLeft = $info->registrationDaysLeft();
        if ($daysLeft === null || $daysLeft > 7) return 0;

        $en = $this->isInternational($student);

        // Agar registratsiya yangilanmoqda holatida bo'lsa — alohida xabar
        if ($info->isRegistrationProcessActive()) {
            $message = $en
                ? "🔄 Your registration is currently being renewed. Please wait."
                : "🔄 Registratsiyangiz yangilanmoqda. Iltimos, kuting.";

            $this->notifyStudent($student, $telegram, $message, 'renewing', 'registratsiya');

            $staffLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — registratsiya yangilanmoqda";
            $this->createSiteNotificationsForStaff($info, "🔄 {$staffLine}");

            $groups['renewing']['registratsiya'][] = [
                'line' => "{$student->full_name} (" . ($student->group_name ?? '-') . ") — yangilanmoqda",
                'firm' => $info->firm,
            ];

            return 1;
        }

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
            $message = $en
                ? "{$emoji} Your registration has expired! Please contact the registrator office immediately."
                : "{$emoji} Registratsiya muddati tugagan! Zudlik bilan registrator ofisiga murojaat qiling.";
        } else {
            $message = $en
                ? "{$emoji} Your registration expires in {$daysLeft} days. Please renew it."
                : "{$emoji} Registratsiya muddati tugashiga {$daysLeft} kun qoldi. Muddatini uzaytiring.";
        }

        // Talabaning o'ziga shaxsiy xabar (shaxsiy xabarlar o'z holatida qoladi)
        $this->notifyStudent($student, $telegram, $message, $level, 'registratsiya');

        // Staff uchun sayt bildirishnomasi (har bir talaba uchun alohida yozuv qoladi)
        $staffLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — ";
        $staffLine .= $daysLeft <= 0 ? "registratsiya muddati TUGAGAN" : "registratsiya muddati tugashiga {$daysLeft} kun qoldi";
        $this->createSiteNotificationsForStaff($info, "{$emoji} {$staffLine}");

        // Telegram uchun guruhga qo'shamiz (yuborish handle() oxirida bo'ladi)
        $groupLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — ";
        $groupLine .= $daysLeft <= 0 ? "muddati TUGAGAN" : "{$daysLeft} kun qoldi";
        $groups[$level]['registratsiya'][] = [
            'line' => $groupLine,
            'firm' => $info->firm,
        ];

        return 1;
    }

    private function checkVisa(StudentVisaInfo $info, Student $student, TelegramService $telegram, array &$groups): int
    {
        $daysLeft = $info->visaDaysLeft();
        if ($daysLeft === null || $daysLeft > 30) return 0;

        $en = $this->isInternational($student);

        // Agar viza yangilanmoqda holatida bo'lsa — alohida xabar
        if ($info->isVisaProcessActive()) {
            $message = $en
                ? "🔄 Your visa is currently being renewed. Please wait."
                : "🔄 Vizangiz yangilanmoqda. Iltimos, kuting.";

            $this->notifyStudent($student, $telegram, $message, 'renewing', 'visa');

            $staffLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — viza yangilanmoqda";
            $this->createSiteNotificationsForStaff($info, "🔄 {$staffLine}");

            $groups['renewing']['visa'][] = [
                'line' => "{$student->full_name} (" . ($student->group_name ?? '-') . ") — yangilanmoqda",
                'firm' => $info->firm,
            ];

            return 1;
        }

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
            $message = $en
                ? "{$emoji} Your visa has expired! Please contact the registrator office immediately."
                : "{$emoji} Viza muddati tugagan! Zudlik bilan registrator ofisiga murojaat qiling.";
        } else {
            $message = $en
                ? "{$emoji} Your visa expires in {$daysLeft} days. Please renew it."
                : "{$emoji} Viza muddati tugashiga {$daysLeft} kun qoldi. Vizangizni yangilang.";
        }

        $this->notifyStudent($student, $telegram, $message, $level, 'visa');

        // Staff uchun sayt bildirishnomasi
        $staffLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — ";
        $staffLine .= $daysLeft <= 0 ? "viza muddati TUGAGAN" : "viza muddati tugashiga {$daysLeft} kun qoldi";
        $this->createSiteNotificationsForStaff($info, "{$emoji} {$staffLine}");

        // Telegram uchun guruhga qo'shamiz
        $groupLine = "{$student->full_name} (" . ($student->group_name ?? '-') . ") — ";
        $groupLine .= $daysLeft <= 0 ? "muddati TUGAGAN" : "{$daysLeft} kun qoldi";
        $groups[$level]['visa'][] = [
            'line' => $groupLine,
            'firm' => $info->firm,
        ];

        return 1;
    }

    private function checkPassportHandover(StudentVisaInfo $info, Student $student, TelegramService $telegram): int
    {
        if ($info->passport_handed_over) return 0;

        $regDays = $info->registrationDaysLeft();
        $visaDays = $info->visaDaysLeft();

        $shouldWarn = ($regDays !== null && $regDays <= 7) || ($visaDays !== null && $visaDays <= 30);
        if (!$shouldWarn) return 0;

        $en = $this->isInternational($student);
        $message = $en
            ? "⚠️ You need to submit your passport to the registrator office. Please do it as soon as possible."
            : "⚠️ Pasportingizni registrator ofisi xodimiga topshirishingiz kerak. Iltimos, tezroq topshiring.";
        $this->notifyStudent($student, $telegram, $message, 'danger', 'passport');
        return 1;
    }

    private function notifyStudent(Student $student, TelegramService $telegram, string $message, string $level, string $type): void
    {
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => match(true) {
                $level === 'renewing' && $type === 'registratsiya' => 'Registratsiya yangilanmoqda',
                $level === 'renewing' && $type === 'visa' => 'Viza yangilanmoqda',
                $type === 'registratsiya' => 'Registratsiya muddati ogohlantirishi',
                $type === 'visa' => 'Viza muddati ogohlantirishi',
                $type === 'passport' => 'Pasport topshirish ogohlantirishi',
            },
            'message' => $message,
            'data' => ['level' => $level, 'type' => $type],
        ]);

        if ($student->telegram_chat_id) {
            $telegram->sendToUser($student->telegram_chat_id, $message);
        }
    }

    /**
     * Registrator ofisi, firma javobgari va obunachilar uchun sayt bildirishnomasi
     * (Telegram bu yerda yuborilmaydi — u handle() oxirida guruhlangan holda jo'natiladi).
     */
    private function createSiteNotificationsForStaff(StudentVisaInfo $info, string $message): void
    {
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

        if (\Schema::hasTable('visa_notification_subscribers')) {
            $subscribers = \DB::table('visa_notification_subscribers')->get();
            foreach ($subscribers as $sub) {
                \App\Models\Notification::create([
                    'sender_id' => null,
                    'sender_type' => null,
                    'recipient_id' => $sub->subscribable_id,
                    'recipient_type' => $sub->subscribable_type,
                    'subject' => 'Viza/Registratsiya ogohlantirish',
                    'body' => $message,
                    'type' => 'alert',
                    'is_read' => false,
                    'is_draft' => false,
                    'sent_at' => now(),
                ]);
            }
        }
    }

    /**
     * Staff (registrator ofisi, firma javobgarlari, obunachilar)ga holat bo'yicha
     * guruhlangan Telegram xabarlarini yuborish. Har bir holat uchun bitta xabar.
     */
    private function dispatchGroupedTelegramMessages(array $groups, TelegramService $telegram): void
    {
        $emojis = [
            'renewing' => '🔄',
            'danger'   => '🔴',
            'warning'  => '🟡',
            'info'     => '🟢',
        ];
        $titles = [
            'renewing' => 'Yangilanmoqda',
            'danger'   => 'Shoshilinch — muddat tugagan yoki juda yaqin',
            'warning'  => 'Ogohlantirish — muddat yaqin',
            'info'     => 'Eslatma — muddat yaqinlashmoqda',
        ];

        foreach ($groups as $level => $typesData) {
            if (empty($typesData['registratsiya']) && empty($typesData['visa'])) {
                continue;
            }

            // Registrator ofisi va obunachilar uchun umumiy xabar (barcha talabalar)
            $overall = $this->buildGroupMessage($typesData, $emojis[$level], $titles[$level]);

            $registrarUsers = User::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))
                ->whereNotNull('telegram_chat_id')
                ->get();
            foreach ($registrarUsers as $regUser) {
                $telegram->sendToUser($regUser->telegram_chat_id, $overall);
            }

            if (\Schema::hasTable('visa_notification_subscribers')) {
                $subscribers = \DB::table('visa_notification_subscribers')
                    ->whereNotNull('telegram_chat_id')
                    ->get();
                foreach ($subscribers as $sub) {
                    $telegram->sendToUser($sub->telegram_chat_id, $overall);
                }
            }

            // Firma javobgarlariga faqat o'z firmasi talabalari haqidagi xabar
            $byFirm = [];
            foreach (['registratsiya', 'visa'] as $type) {
                foreach ($typesData[$type] as $entry) {
                    $firm = $entry['firm'];
                    if ($firm) {
                        $byFirm[$firm][$type][] = $entry;
                    }
                }
            }

            foreach ($byFirm as $firm => $firmTypesData) {
                $firmTypesData = [
                    'registratsiya' => $firmTypesData['registratsiya'] ?? [],
                    'visa'          => $firmTypesData['visa'] ?? [],
                ];
                $firmMsg = $this->buildGroupMessage(
                    $firmTypesData,
                    $emojis[$level],
                    $titles[$level] . " — {$firm}"
                );

                $firmUsers = User::where('assigned_firm', $firm)
                    ->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))
                    ->whereNotNull('telegram_chat_id')
                    ->get();
                foreach ($firmUsers as $firmUser) {
                    $telegram->sendToUser($firmUser->telegram_chat_id, $firmMsg);
                }

                $firmTeachers = \App\Models\Teacher::where('assigned_firm', $firm)
                    ->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))
                    ->whereNotNull('telegram_chat_id')
                    ->get();
                foreach ($firmTeachers as $firmTeacher) {
                    $telegram->sendToUser($firmTeacher->telegram_chat_id, $firmMsg);
                }
            }
        }
    }

    private function buildGroupMessage(array $typesData, string $emoji, string $title): string
    {
        $lines = ["{$emoji} <b>{$title}</b>"];

        if (!empty($typesData['registratsiya'])) {
            $lines[] = '';
            $lines[] = '<b>Registratsiya:</b>';
            foreach ($typesData['registratsiya'] as $entry) {
                $lines[] = '• ' . $entry['line'];
            }
        }

        if (!empty($typesData['visa'])) {
            $lines[] = '';
            $lines[] = '<b>Viza:</b>';
            foreach ($typesData['visa'] as $entry) {
                $lines[] = '• ' . $entry['line'];
            }
        }

        return implode("\n", $lines);
    }
}

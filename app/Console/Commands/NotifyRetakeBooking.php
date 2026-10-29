<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\MoodleRetakeException;
use App\Services\RetakeBookingService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Talabaga Telegram orqali qayta topshirish (otrabotka) qarzlari va har biri
 * uchun vaqt tanlash havolasini yuboradi. Qarzlar Moodle'dan, muddat LMS'dan.
 */
class NotifyRetakeBooking extends Command
{
    protected $signature = 'retake-bookings:notify
        {student : Talaba ID raqami (student_id_number) yoki HEMIS id}
        {--dry-run : Yubormasdan, xabar matnini ko\'rsatish}';

    protected $description = "Talabaga Telegramda qayta topshirish qarzlari va vaqt tanlash havolasini yuborish";

    public function handle(RetakeBookingService $service, TelegramService $telegram): int
    {
        $key = trim((string) $this->argument('student'));
        $student = Student::where('student_id_number', $key)->first()
            ?? (ctype_digit($key) ? Student::where('hemis_id', (int) $key)->first() : null);
        if (!$student) {
            $this->error("Talaba topilmadi: {$key}");

            return self::FAILURE;
        }

        try {
            $debts = $service->withLmsDeadline($service->debts((int) $student->hemis_id), $student);
        } catch (MoodleRetakeException $e) {
            $this->error("Moodle'dan qarzlarni olib bo'lmadi: " . $e->getMessage());

            return self::FAILURE;
        }

        $debts = array_values(array_filter($debts, fn ($d) => !empty($d['can_book']) || ($d['state'] ?? '') === 'booked'));
        if (!$debts) {
            $this->warn("{$student->full_name}: vaqt tanlash mumkin bo'lgan qarz yo'q — xabar yuborilmadi.");

            return self::SUCCESS;
        }

        $message = $this->message($student, $debts);
        $this->line($message);

        if ($this->option('dry-run')) {
            $this->info('--dry-run: yuborilmadi.');

            return self::SUCCESS;
        }
        if (empty($student->telegram_chat_id)) {
            $this->error("{$student->full_name}: Telegram ulanmagan (telegram_chat_id yo'q).");

            return self::FAILURE;
        }
        if (!$telegram->sendToUser((string) $student->telegram_chat_id, $message)) {
            $this->error('Telegramga yuborilmadi (batafsil — logda).');

            return self::FAILURE;
        }

        $this->info("Yuborildi: {$student->full_name}, " . count($debts) . ' ta qarz.');

        return self::SUCCESS;
    }

    private function message(Student $student, array $debts): string
    {
        $lines = ['Hurmatli ' . e($student->full_name) . '!', '', 'Sizda qayta topshirilishi kerak bo\'lgan mavzular bor:'];

        foreach ($debts as $d) {
            $date = Carbon::parse($d['lesson_date'])->format('d.m.Y');
            $what = ($d['reason'] ?? '') === 'nb' ? 'NB' : 'past baho';
            $url = route('student.retake-booking.index', ['subject_id' => $d['subject_id'], 'lesson_date' => $d['lesson_date']]);

            $lines[] = '';
            $lines[] = '📌 <b>' . e($d['subject_name'] ?? '') . '</b> — ' . $date . ', ' . $what;
            if (!empty($d['deadline'])) {
                $lines[] = 'Muddat: ' . Carbon::parse($d['deadline'])->format('d.m.Y') . ' gacha';
            }
            if (($d['state'] ?? '') === 'booked' && !empty($d['slot_start'])) {
                $lines[] = 'Tanlangan vaqt: ' . Carbon::parse($d['slot_start'])->format('d.m.Y H:i')
                    . '–' . Carbon::parse($d['slot_end'])->format('H:i');
                $lines[] = '<a href="' . e($url) . '">Vaqtni o\'zgartirish yoki bekor qilish</a>';
            } else {
                $lines[] = '<a href="' . e($url) . '">Qayta topshirish vaqtini tanlash</a>';
            }
        }

        $lines[] = '';
        $lines[] = 'Vaqt tanlash majburiy emas: tanlamasangiz, muddat tugaguncha istalgan vaqtda topshirishingiz mumkin. '
            . 'Vaqt tanlasangiz, faqat shu vaqtda topshira olasiz; kelmasangiz, bron bekor bo\'ladi.';

        return implode("\n", $lines);
    }
}

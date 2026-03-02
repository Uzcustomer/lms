<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExamReminders extends Command
{
    protected $signature = 'students:send-exam-reminders';

    protected $description = 'Imtihonga 1 kun qolganda talabalarga Telegram orqali ogohlantirish yuborish';

    public function handle(TelegramService $telegram): int
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $this->info("Ertangi sana: {$tomorrow}");
        $this->info("Imtihon eslatmalari tekshirilmoqda...");

        $examSchedules = ExamSchedule::where(function ($query) use ($tomorrow) {
            $query->where(function ($q) use ($tomorrow) {
                $q->whereDate('oski_date', $tomorrow)
                    ->where('oski_na', false);
            })->orWhere(function ($q) use ($tomorrow) {
                $q->whereDate('test_date', $tomorrow)
                    ->where('test_na', false);
            });
        })->get();

        if ($examSchedules->isEmpty()) {
            $this->info('Ertaga uchun imtihon topilmadi.');
            return 0;
        }

        $this->info("Topilgan imtihonlar soni: {$examSchedules->count()}");

        $sentCount = 0;
        $skippedCount = 0;

        $schedulesByGroup = $examSchedules->groupBy('group_hemis_id');

        foreach ($schedulesByGroup as $groupHemisId => $groupSchedules) {
            $students = Student::where('group_id', $groupHemisId)
                ->whereNotNull('telegram_chat_id')
                ->whereNotNull('telegram_verified_at')
                ->get();

            if ($students->isEmpty()) {
                $skippedCount += $groupSchedules->count();
                continue;
            }

            foreach ($students as $student) {
                $relevantSchedules = $groupSchedules->filter(function ($schedule) use ($student) {
                    return $schedule->semester_code === $student->semester_code;
                });

                if ($relevantSchedules->isEmpty()) {
                    continue;
                }

                $message = $this->buildMessage($student, $relevantSchedules, $tomorrow);

                try {
                    $telegram->sendToUser($student->telegram_chat_id, $message);
                    $sentCount++;
                    $this->info("Eslatma yuborildi: {$student->full_name} ({$student->group_name ?? $groupHemisId})");
                } catch (\Throwable $e) {
                    Log::error("Telegram imtihon eslatma yuborishda xato (Student: {$student->full_name}): " . $e->getMessage());
                    $this->error("Xato: {$student->full_name} - " . $e->getMessage());
                }
            }
        }

        $this->info("Yakunlandi. Yuborilgan eslatmalar: {$sentCount}, O'tkazib yuborilgan: {$skippedCount}");

        return 0;
    }

    private function buildMessage(Student $student, $schedules, string $tomorrow): string
    {
        $formattedDate = Carbon::parse($tomorrow)->format('d.m.Y');

        $lines = [];
        $lines[] = "Hurmatli <b>{$student->full_name}</b>!";
        $lines[] = "";
        $lines[] = "Ertaga (<b>{$formattedDate}</b>) quyidagi imtihon(lar)ingiz bor:";
        $lines[] = "";

        foreach ($schedules as $schedule) {
            $examTypes = [];

            if ($schedule->oski_date && !$schedule->oski_na && $schedule->oski_date->format('Y-m-d') === $tomorrow) {
                $examTypes[] = 'OSKI';
            }
            if ($schedule->test_date && !$schedule->test_na && $schedule->test_date->format('Y-m-d') === $tomorrow) {
                $examTypes[] = 'Test';
            }

            if (!empty($examTypes)) {
                $typeStr = implode(', ', $examTypes);
                $lines[] = "  - <b>{$schedule->subject_name}</b> ({$typeStr})";
            }
        }

        $lines[] = "";
        $lines[] = "Imtihonga tayyorgarlik ko'ring! Omad tilaymiz!";

        return implode("\n", $lines);
    }
}

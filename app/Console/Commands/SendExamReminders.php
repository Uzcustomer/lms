<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Semester;
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

        // Joriy o'quv yilini aniqlash
        $currentEducationYear = Semester::where('current', true)->value('education_year');

        $examSchedules = ExamSchedule::where(function ($query) use ($tomorrow) {
            $query->where(function ($q) use ($tomorrow) {
                $q->whereDate('oski_date', $tomorrow)
                    ->where(function ($q2) {
                        $q2->where('oski_na', false)->orWhereNull('oski_na');
                    });
            })->orWhere(function ($q) use ($tomorrow) {
                $q->whereDate('test_date', $tomorrow)
                    ->where(function ($q2) {
                        $q2->where('test_na', false)->orWhereNull('test_na');
                    });
            });
        })
            ->where(function ($query) use ($currentEducationYear) {
                $query->where('education_year', $currentEducationYear)
                    ->orWhereNull('education_year');
            })
            ->get();

        if ($examSchedules->isEmpty()) {
            $this->info('Ertaga uchun imtihon topilmadi.');
            return 0;
        }

        $this->info("Topilgan imtihonlar soni: {$examSchedules->count()}");

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        $schedulesByGroup = $examSchedules->groupBy('group_hemis_id');

        foreach ($schedulesByGroup as $groupHemisId => $groupSchedules) {
            $students = Student::where('group_id', $groupHemisId)
                ->whereNotNull('telegram_chat_id')
                ->whereNotNull('telegram_verified_at')
                ->get();

            if ($students->isEmpty()) {
                $this->warn("Guruh {$groupHemisId}: Telegram tasdiqlangan talaba topilmadi (jami imtihonlar: {$groupSchedules->count()})");
                $skippedCount += $groupSchedules->count();
                continue;
            }

            $this->info("Guruh {$groupHemisId}: {$students->count()} ta talabaga yuborilmoqda...");

            foreach ($students as $student) {
                // semester_code bo'yicha filtrlash (loose comparison — null va type mismatch uchun)
                $relevantSchedules = $groupSchedules->filter(function ($schedule) use ($student) {
                    // Agar talabaning semester_code null bo'lsa, barcha imtihonlarni ko'rsatish
                    if (empty($student->semester_code)) {
                        return true;
                    }
                    return $schedule->semester_code == $student->semester_code;
                });

                if ($relevantSchedules->isEmpty()) {
                    $this->warn("  O'tkazildi: {$student->full_name} — semester mos kelmadi (talaba: {$student->semester_code}, imtihon: {$groupSchedules->pluck('semester_code')->unique()->implode(', ')})");
                    continue;
                }

                $message = $this->buildMessage($student, $relevantSchedules, $tomorrow);

                $success = $telegram->sendToUser($student->telegram_chat_id, $message);

                if ($success) {
                    $sentCount++;
                    $this->info("  Yuborildi: {$student->full_name} ({$student->group_name ?? $groupHemisId})");
                } else {
                    $failedCount++;
                    $this->error("  Xato: {$student->full_name} — Telegram yuborishda muammo");
                    Log::error("Telegram imtihon eslatma yuborishda xato", [
                        'student' => $student->full_name,
                        'chat_id' => $student->telegram_chat_id,
                        'group' => $groupHemisId,
                    ]);
                }
            }
        }

        $this->info("Yakunlandi. Yuborilgan: {$sentCount}, Xato: {$failedCount}, O'tkazilgan: {$skippedCount}");

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
                $lines[] = "📌 <b>{$schedule->subject_name}</b> ({$typeStr})";
            }
        }

        $lines[] = "";
        $lines[] = "Imtihonga tayyorgarlik ko'ring! Omad tilaymiz! 🍀";

        return implode("\n", $lines);
    }
}

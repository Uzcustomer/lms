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

        // 1-qadam: Ertaga imtihoni bor guruhlarni topish (tez query)
        $groupIds = ExamSchedule::where(function ($query) use ($tomorrow) {
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
        })->pluck('group_hemis_id')->unique()->values();

        if ($groupIds->isEmpty()) {
            $this->info('Ertaga uchun imtihon topilmadi.');
            return 0;
        }

        $this->info("Ertaga imtihoni bor guruhlar: {$groupIds->count()} ta");

        // 2-qadam: Shu guruhlardan Telegram tasdiqlangan talabalarni topish
        $students = Student::whereIn('group_id', $groupIds)
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->get();

        if ($students->isEmpty()) {
            $this->warn('Telegram tasdiqlangan talaba topilmadi (guruhlar: ' . $groupIds->take(10)->implode(', ') . ')');
            return 0;
        }

        $this->info("Telegram tasdiqlangan talabalar: {$students->count()} ta");

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        // 3-qadam: Har bir talaba uchun imtihonlarni topish
        // StudentController::examSchedule() bilan BIR XIL mantiq
        foreach ($students as $student) {
            $exams = ExamSchedule::where('group_hemis_id', $student->group_id)
                ->where('semester_code', $student->semester_code)
                ->where(function ($query) use ($student) {
                    $query->where('education_year', $student->education_year_code)
                        ->orWhereNull('education_year');
                })
                ->where(function ($query) use ($tomorrow) {
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
                ->get();

            if ($exams->isEmpty()) {
                $skippedCount++;
                $this->warn("  O'tkazildi: {$student->full_name} — semester yoki education_year mos kelmadi (semester: {$student->semester_code}, year: {$student->education_year_code})");
                continue;
            }

            $message = $this->buildMessage($student, $exams, $tomorrow);

            $success = $telegram->sendToUser($student->telegram_chat_id, $message);

            if ($success) {
                $sentCount++;
                $groupLabel = $student->group_name ?: $student->group_id;
                $this->info("  Yuborildi: {$student->full_name} ({$groupLabel}) — {$exams->count()} ta imtihon");
            } else {
                $failedCount++;
                $this->error("  Xato: {$student->full_name} — Telegram yuborishda muammo");
                Log::error("Telegram imtihon eslatma yuborishda xato", [
                    'student' => $student->full_name,
                    'chat_id' => $student->telegram_chat_id,
                    'group' => $student->group_id,
                ]);
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

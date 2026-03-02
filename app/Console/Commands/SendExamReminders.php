<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Notification;
use App\Models\Student;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExamReminders extends Command
{
    protected $signature = 'students:send-exam-reminders';

    protected $description = 'Imtihonga 1 kun qolganda talabalarga Telegram va xabarnoma orqali ogohlantirish yuborish';

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

        // 2-qadam: Shu guruhlardan BARCHA talabalarni topish (xabarnoma uchun)
        $allStudents = Student::whereIn('group_id', $groupIds)->get();

        if ($allStudents->isEmpty()) {
            $this->warn('Talaba topilmadi (guruhlar: ' . $groupIds->take(10)->implode(', ') . ')');
            return 0;
        }

        $this->info("Jami talabalar: {$allStudents->count()} ta");

        $telegramSentCount = 0;
        $telegramFailedCount = 0;
        $notificationCount = 0;
        $skippedCount = 0;

        // 3-qadam: Har bir talaba uchun imtihonlarni topish
        // StudentController::examSchedule() bilan BIR XIL mantiq
        foreach ($allStudents as $student) {
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
                continue;
            }

            $formattedDate = Carbon::parse($tomorrow)->format('d.m.Y');
            $examList = $this->buildExamList($exams, $tomorrow);

            // Xabarnoma yaratish (barcha talabalar uchun)
            try {
                Notification::create([
                    'recipient_id' => $student->id,
                    'recipient_type' => Student::class,
                    'subject' => "Ertaga ({$formattedDate}) imtihon bor!",
                    'body' => $examList,
                    'type' => Notification::TYPE_ALERT,
                    'url' => route('student.exam-schedule'),
                    'is_draft' => false,
                    'sent_at' => now(),
                ]);
                $notificationCount++;
            } catch (\Throwable $e) {
                Log::error("Student notification yaratishda xato: " . $e->getMessage());
            }

            // Telegram yuborish (faqat tasdiqlangan talabalar uchun)
            if ($student->telegram_chat_id && $student->telegram_verified_at) {
                $message = $this->buildTelegramMessage($student, $exams, $tomorrow);
                $success = $telegram->sendToUser($student->telegram_chat_id, $message);

                if ($success) {
                    $telegramSentCount++;
                    $groupLabel = $student->group_name ?: $student->group_id;
                    $this->info("  Yuborildi: {$student->full_name} ({$groupLabel}) — {$exams->count()} ta imtihon");
                } else {
                    $telegramFailedCount++;
                    Log::error("Telegram imtihon eslatma yuborishda xato", [
                        'student' => $student->full_name,
                        'chat_id' => $student->telegram_chat_id,
                    ]);
                }
            }
        }

        $this->info("Yakunlandi. Telegram: {$telegramSentCount} yuborildi, {$telegramFailedCount} xato. Xabarnoma: {$notificationCount}. O'tkazilgan: {$skippedCount}");

        return 0;
    }

    private function buildExamList($schedules, string $tomorrow): string
    {
        $lines = [];
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
                $lines[] = "{$schedule->subject_name} ({$typeStr})";
            }
        }

        return implode("\n", $lines);
    }

    private function buildTelegramMessage(Student $student, $schedules, string $tomorrow): string
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

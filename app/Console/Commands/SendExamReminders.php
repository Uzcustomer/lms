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

    protected $description = 'Imtihonga 3 kun qolgandan boshlab har 12 soatda talabalarga Telegram eslatma yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = Carbon::today();

        $this->info("Bugungi sana: {$today->format('Y-m-d')}");
        $this->info("Imtihon eslatmalari tekshirilmoqda (bugundan 3 kungacha)...");

        // Bugundan 3 kungacha bo'lgan sanalar (bugun + 1, 2, 3 kun)
        $dateRange = [];
        for ($i = 0; $i <= 3; $i++) {
            $dateRange[] = $today->copy()->addDays($i)->format('Y-m-d');
        }

        // 1-qadam: Yaqin 3 kunda imtihoni bor guruhlarni topish (tez query)
        $groupIds = ExamSchedule::where(function ($query) use ($dateRange) {
            $query->where(function ($q) use ($dateRange) {
                $q->whereIn(\DB::raw('DATE(oski_date)'), $dateRange)
                    ->where(function ($q2) {
                        $q2->where('oski_na', false)->orWhereNull('oski_na');
                    });
            })->orWhere(function ($q) use ($dateRange) {
                $q->whereIn(\DB::raw('DATE(test_date)'), $dateRange)
                    ->where(function ($q2) {
                        $q2->where('test_na', false)->orWhereNull('test_na');
                    });
            });
        })->pluck('group_hemis_id')->unique()->values();

        if ($groupIds->isEmpty()) {
            $this->info('Yaqin 3 kun ichida imtihon topilmadi.');
            return 0;
        }

        $this->info("Yaqin 3 kunda imtihoni bor guruhlar: {$groupIds->count()} ta");

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
                ->where(function ($query) use ($dateRange) {
                    $query->where(function ($q) use ($dateRange) {
                        $q->whereIn(\DB::raw('DATE(oski_date)'), $dateRange)
                            ->where(function ($q2) {
                                $q2->where('oski_na', false)->orWhereNull('oski_na');
                            });
                    })->orWhere(function ($q) use ($dateRange) {
                        $q->whereIn(\DB::raw('DATE(test_date)'), $dateRange)
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

            $message = $this->buildMessage($student, $exams, $today);

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

    private function buildMessage(Student $student, $schedules, Carbon $today): string
    {
        $lines = [];
        $lines[] = "Hurmatli <b>{$student->full_name}</b>!";
        $lines[] = "";

        // Imtihonlarni sanaga qarab guruhlash
        $examsByDate = [];
        foreach ($schedules as $schedule) {
            if ($schedule->oski_date && !$schedule->oski_na) {
                $dateKey = $schedule->oski_date->format('Y-m-d');
                $daysLeft = $today->diffInDays($schedule->oski_date);
                if ($daysLeft <= 3) {
                    $examsByDate[$dateKey]['exams'][] = ['name' => $schedule->subject_name, 'type' => 'OSKI'];
                    $examsByDate[$dateKey]['days'] = $daysLeft;
                    $examsByDate[$dateKey]['date'] = $schedule->oski_date->format('d.m.Y');
                }
            }
            if ($schedule->test_date && !$schedule->test_na) {
                $dateKey = $schedule->test_date->format('Y-m-d');
                $daysLeft = $today->diffInDays($schedule->test_date);
                if ($daysLeft <= 3) {
                    $examsByDate[$dateKey]['exams'][] = ['name' => $schedule->subject_name, 'type' => 'Test'];
                    $examsByDate[$dateKey]['days'] = $daysLeft;
                    $examsByDate[$dateKey]['date'] = $schedule->test_date->format('d.m.Y');
                }
            }
        }

        ksort($examsByDate);

        foreach ($examsByDate as $dateInfo) {
            $daysLeft = $dateInfo['days'];

            if ($daysLeft == 0) {
                $lines[] = "🔴 <b>Bugun</b> ({$dateInfo['date']}) imtihon(lar)ingiz bor:";
            } elseif ($daysLeft == 1) {
                $lines[] = "🟠 <b>Ertaga</b> ({$dateInfo['date']}) imtihon(lar)ingiz bor:";
            } else {
                $lines[] = "🟡 <b>{$daysLeft} kun qoldi</b> ({$dateInfo['date']}):";
            }

            foreach ($dateInfo['exams'] as $exam) {
                $lines[] = "  📌 <b>{$exam['name']}</b> ({$exam['type']})";
            }
            $lines[] = "";
        }

        $hasToday = collect($examsByDate)->contains(fn($d) => $d['days'] == 0);
        if ($hasToday) {
            $lines[] = "Imtihonda omad tilaymiz! 🍀";
        } else {
            $lines[] = "Imtihonga tayyorgarlik ko'ring! 📚";
        }

        return implode("\n", $lines);
    }
}

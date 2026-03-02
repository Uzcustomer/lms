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

    protected $description = 'Imtihonga 3 kun qolgandan boshlab har 12 soatda talabalarga Telegram eslatma yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = Carbon::today();

        $this->info("Bugungi sana: {$today->format('Y-m-d')}");
        $this->info("Imtihon eslatmalari tekshirilmoqda (bugundan 3 kungacha)...");

        // Joriy o'quv yilini aniqlash
        $currentEducationYear = Semester::where('current', true)->value('education_year');

        // Bugundan 3 kungacha bo'lgan imtihonlarni topish (bugun + 1, 2, 3 kun)
        $dateRange = [];
        for ($i = 0; $i <= 3; $i++) {
            $dateRange[] = $today->copy()->addDays($i)->format('Y-m-d');
        }

        $examSchedules = ExamSchedule::where(function ($query) use ($dateRange) {
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
            ->where(function ($query) use ($currentEducationYear) {
                $query->where('education_year', $currentEducationYear)
                    ->orWhereNull('education_year');
            })
            ->get();

        if ($examSchedules->isEmpty()) {
            $this->info('Yaqin 3 kun ichida imtihon topilmadi.');
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

                $message = $this->buildMessage($student, $relevantSchedules, $today);

                $success = $telegram->sendToUser($student->telegram_chat_id, $message);

                if ($success) {
                    $sentCount++;
                    $groupLabel = $student->group_name ?: $groupHemisId;
                    $this->info("  Yuborildi: {$student->full_name} ({$groupLabel})");
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

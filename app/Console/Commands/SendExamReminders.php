<?php

namespace App\Console\Commands;

use App\Models\ExamSchedule;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

        // 1-qadam: Yaqin 3 kunda imtihoni bor guruhlarni topish
        $examSchedules = ExamSchedule::where(function ($query) use ($dateRange) {
            $query->where(function ($q) use ($dateRange) {
                $q->whereIn(DB::raw('DATE(oski_date)'), $dateRange)
                    ->where(function ($q2) {
                        $q2->where('oski_na', false)->orWhereNull('oski_na');
                    });
            })->orWhere(function ($q) use ($dateRange) {
                $q->whereIn(DB::raw('DATE(test_date)'), $dateRange)
                    ->where(function ($q2) {
                        $q2->where('test_na', false)->orWhereNull('test_na');
                    });
            });
        })->get();

        if ($examSchedules->isEmpty()) {
            $this->info('Yaqin 3 kun ichida imtihon topilmadi.');
            return 0;
        }

        $groupIds = $examSchedules->pluck('group_hemis_id')->unique()->values();
        $this->info("Yaqin 3 kunda imtihoni bor guruhlar: {$groupIds->count()} ta");

        // Imtihonlarni group_id + semester_code bo'yicha indekslash (N+1 muammoni hal qilish)
        $examsByGroupAndSemester = $examSchedules->groupBy(function ($exam) {
            return $exam->group_hemis_id . '|' . $exam->semester_code;
        });

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
        $notificationRecords = [];

        // 3-qadam: Har bir talaba uchun — qo'shimcha query YO'Q, xotiradagi ma'lumotlardan
        foreach ($students as $student) {
            $key = $student->group_id . '|' . $student->semester_code;
            $candidateExams = $examsByGroupAndSemester->get($key, collect());

            // education_year filtri
            $exams = $candidateExams->filter(function ($exam) use ($student) {
                return $exam->education_year === $student->education_year_code
                    || $exam->education_year === null;
            });

            if ($exams->isEmpty()) {
                $skippedCount++;
                $this->warn("  O'tkazildi: {$student->full_name} — semester yoki education_year mos kelmadi (semester: {$student->semester_code}, year: {$student->education_year_code})");
                continue;
            }

            $message = $this->buildMessage($student, $exams, $today);
            $plainMessage = $this->buildPlainMessage($exams, $today);

            // Telegram ga yuborish
            $success = $telegram->sendToUser($student->telegram_chat_id, $message);

            // Notification inbox ga yozish (Telegram muvaffaqiyatidan qat'iy nazar)
            $notificationRecords[] = [
                'student_id' => $student->id,
                'type' => 'exam_reminder',
                'title' => $this->buildNotificationTitle($exams, $today),
                'message' => $plainMessage,
                'link' => '/student/exam-schedule',
                'data' => json_encode([
                    'exam_count' => $exams->count(),
                    'dates' => $exams->pluck('oski_date', 'test_date')->toArray(),
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

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

            // Har 50 ta notificationni batch insert qilish
            if (count($notificationRecords) >= 50) {
                StudentNotification::insert($notificationRecords);
                $notificationRecords = [];
            }
        }

        // Qolgan notificationlarni yozish
        if (!empty($notificationRecords)) {
            StudentNotification::insert($notificationRecords);
        }

        $this->info("Yakunlandi. Yuborilgan: {$sentCount}, Xato: {$failedCount}, O'tkazilgan: {$skippedCount}");

        return 0;
    }

    private function buildNotificationTitle($exams, Carbon $today): string
    {
        $examsByDate = $this->groupExamsByDate($exams, $today);
        $minDays = collect($examsByDate)->min('days');

        if ($minDays == 0) {
            return "Bugun imtihoningiz bor!";
        } elseif ($minDays == 1) {
            return "Ertaga imtihoningiz bor!";
        } else {
            return "Imtihonga {$minDays} kun qoldi!";
        }
    }

    private function buildPlainMessage($exams, Carbon $today): string
    {
        $lines = [];
        $examsByDate = $this->groupExamsByDate($exams, $today);

        foreach ($examsByDate as $dateInfo) {
            $daysLeft = $dateInfo['days'];

            if ($daysLeft == 0) {
                $lines[] = "Bugun ({$dateInfo['date']}):";
            } elseif ($daysLeft == 1) {
                $lines[] = "Ertaga ({$dateInfo['date']}):";
            } else {
                $lines[] = "{$daysLeft} kun qoldi ({$dateInfo['date']}):";
            }

            foreach ($dateInfo['exams'] as $exam) {
                $timePart = !empty($exam['time']) ? " — soat {$exam['time']}" : '';
                $lines[] = "  - {$exam['name']} ({$exam['type']}){$timePart}";
            }
        }

        return implode("\n", $lines);
    }

    private function groupExamsByDate($schedules, Carbon $today): array
    {
        $examsByDate = [];
        foreach ($schedules as $schedule) {
            if ($schedule->oski_date && !$schedule->oski_na) {
                $dateKey = $schedule->oski_date->format('Y-m-d');
                $daysLeft = $today->diffInDays($schedule->oski_date);
                if ($daysLeft <= 3) {
                    $examsByDate[$dateKey]['exams'][] = ['name' => $schedule->subject_name, 'type' => 'OSKI', 'time' => null];
                    $examsByDate[$dateKey]['days'] = $daysLeft;
                    $examsByDate[$dateKey]['date'] = $schedule->oski_date->format('d.m.Y');
                }
            }
            if ($schedule->test_date && !$schedule->test_na) {
                $dateKey = $schedule->test_date->format('Y-m-d');
                $daysLeft = $today->diffInDays($schedule->test_date);
                if ($daysLeft <= 3) {
                    $testTime = $schedule->test_time ? substr($schedule->test_time, 0, 5) : null;
                    $examsByDate[$dateKey]['exams'][] = ['name' => $schedule->subject_name, 'type' => 'Test', 'time' => $testTime];
                    $examsByDate[$dateKey]['days'] = $daysLeft;
                    $examsByDate[$dateKey]['date'] = $schedule->test_date->format('d.m.Y');
                }
            }
        }
        ksort($examsByDate);
        return $examsByDate;
    }

    private function buildMessage(Student $student, $schedules, Carbon $today): string
    {
        $lines = [];
        $lines[] = "Hurmatli <b>{$student->full_name}</b>!";
        $lines[] = "";

        $examsByDate = $this->groupExamsByDate($schedules, $today);

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
                $timePart = !empty($exam['time']) ? " — ⏰ {$exam['time']}" : '';
                $lines[] = "  📌 <b>{$exam['name']}</b> ({$exam['type']}){$timePart}";
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

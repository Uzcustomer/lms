<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\StudentGrade;
use App\Models\Teacher;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTeacherReminders extends Command
{
    protected $signature = 'teachers:send-reminders';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilarga Telegram orqali eslatma yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = Carbon::today()->format('Y-m-d');

        // Baho qo'yilmaydigan training type kodlari (config/app.php dan)
        // 11=Ma'ruza, 99=Mustaqil ta'lim, 100=Oraliq nazorat, 101=Oski, 102=Yakuniy test
        // Bu turlarga faqat davomat tekshiriladi, baho tekshirilmaydi
        $excludedTrainingTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Bugungi sana: {$today}");
        $this->info("O'qituvchilarga eslatma yuborilmoqda...");

        // Bugungi darslarni DARS JADVALIDAN (schedules) olish
        $todaySchedules = Schedule::whereDate('lesson_date', $today)->get();

        if ($todaySchedules->isEmpty()) {
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        // O'qituvchilar bo'yicha guruhlash
        $schedulesByTeacher = $todaySchedules->groupBy('employee_id');

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($schedulesByTeacher as $employeeId => $schedules) {
            $teacher = Teacher::where('hemis_id', $employeeId)->first();

            if (!$teacher || !$teacher->telegram_chat_id) {
                $skippedCount++;
                continue;
            }

            $missingAttendance = [];
            $missingGrades = [];

            foreach ($schedules as $schedule) {
                $trainingTypeCode = (int) $schedule->training_type_code;

                // DAVOMAT tekshirish: barcha dars turlari uchun
                $hasAttendance = Attendance::where('employee_id', $schedule->employee_id)
                    ->where('subject_schedule_id', $schedule->schedule_hemis_id)
                    ->whereDate('lesson_date', $today)
                    ->where('lesson_pair_code', $schedule->lesson_pair_code)
                    ->exists();

                if (!$hasAttendance) {
                    $missingAttendance[] = $schedule;
                }

                // BAHO tekshirish: faqat amaliyot turlari uchun
                // Ma'ruza (11), Mustaqil ta'lim (99), Oraliq nazorat (100), Oski (101), Yakuniy test (102)
                // — bu turlarga baho qo'yilmaydi, shuning uchun tekshirilmaydi
                if (!in_array($trainingTypeCode, $excludedTrainingTypes)) {
                    $hasGrades = StudentGrade::where('employee_id', $schedule->employee_id)
                        ->where('subject_schedule_id', $schedule->schedule_hemis_id)
                        ->whereDate('lesson_date', $today)
                        ->where('lesson_pair_code', $schedule->lesson_pair_code)
                        ->whereNotNull('grade')
                        ->exists();

                    if (!$hasGrades) {
                        $missingGrades[] = $schedule;
                    }
                }
            }

            if (empty($missingAttendance) && empty($missingGrades)) {
                continue;
            }

            $message = $this->buildMessage($teacher, $missingAttendance, $missingGrades, $today);

            try {
                $telegram->sendToUser($teacher->telegram_chat_id, $message);
                $sentCount++;
                $this->info("Eslatma yuborildi: {$teacher->full_name}");
            } catch (\Throwable $e) {
                Log::error("Telegram eslatma yuborishda xato (Teacher: {$teacher->full_name}): " . $e->getMessage());
                $this->error("Xato: {$teacher->full_name} - " . $e->getMessage());
            }
        }

        $this->info("Yakunlandi. Yuborildi: {$sentCount}, O'tkazib yuborildi: {$skippedCount}");

        return 0;
    }

    private function buildMessage(Teacher $teacher, array $missingAttendance, array $missingGrades, string $today): string
    {
        $lines = [];
        $lines[] = "Hurmatli {$teacher->full_name}!";
        $lines[] = "";
        $lines[] = "Bugungi sana: {$today}";
        $lines[] = "";

        if (!empty($missingAttendance)) {
            $lines[] = "Davomat olinmagan darslar:";
            foreach ($missingAttendance as $schedule) {
                $time = $schedule->lesson_pair_start_time
                    ? " ({$schedule->lesson_pair_start_time}-{$schedule->lesson_pair_end_time})"
                    : "";
                $lines[] = "  - {$schedule->subject_name} | {$schedule->group_name} | {$schedule->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        if (!empty($missingGrades)) {
            $lines[] = "Baho qo'yilmagan darslar:";
            foreach ($missingGrades as $schedule) {
                $time = $schedule->lesson_pair_start_time
                    ? " ({$schedule->lesson_pair_start_time}-{$schedule->lesson_pair_end_time})"
                    : "";
                $lines[] = "  - {$schedule->subject_name} | {$schedule->group_name} | {$schedule->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        $hasBoth = !empty($missingAttendance) && !empty($missingGrades);
        $onlyAttendance = !empty($missingAttendance) && empty($missingGrades);

        if ($hasBoth) {
            $lines[] = "Iltimos, tizimga kirib davomat va baholarni kiriting.";
        } elseif ($onlyAttendance) {
            $lines[] = "Iltimos, tizimga kirib davomatni kiriting.";
        } else {
            $lines[] = "Iltimos, tizimga kirib baholarni kiriting.";
        }

        return implode("\n", $lines);
    }
}

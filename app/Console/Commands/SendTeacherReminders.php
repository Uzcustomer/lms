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

        $this->info("Bugungi sana: {$today}");
        $this->info("O'qituvchilarga eslatma yuborilmoqda...");

        // Bugungi darslarni DARS JADVALIDAN (schedules) olish
        // Har bir yozuvda aynan qaysi o'qituvchi qaysi turning darsiga biriktirilgani ko'rsatilgan
        $todaySchedules = Schedule::whereDate('lesson_date', $today)->get();

        if ($todaySchedules->isEmpty()) {
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        // O'qituvchilar bo'yicha guruhlash (har bir o'qituvchi faqat o'ziga biriktirilgan darslarni ko'radi)
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
                // Davomat tekshirish:
                // Aynan shu o'qituvchi (employee_id) + shu jadval yozuvi (schedule_hemis_id) uchun
                // attendance yozuvi bormi?
                $hasAttendance = Attendance::where('employee_id', $schedule->employee_id)
                    ->where('subject_schedule_id', $schedule->schedule_hemis_id)
                    ->whereDate('lesson_date', $today)
                    ->where('lesson_pair_code', $schedule->lesson_pair_code)
                    ->exists();

                if (!$hasAttendance) {
                    $missingAttendance[] = $schedule;
                }

                // Baho tekshirish:
                // Aynan shu o'qituvchi + shu jadval yozuvi uchun baho qo'yilganmi?
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

        $lines[] = "Iltimos, tizimga kirib davomat va baholarni kiriting.";

        return implode("\n", $lines);
    }
}

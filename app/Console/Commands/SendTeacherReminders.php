<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceControl;
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

        // Bugungi darslarni attendance_controls dan olish
        $todayLessons = AttendanceControl::whereDate('lesson_date', $today)->get();

        if ($todayLessons->isEmpty()) {
            $this->info('Bugun uchun dars topilmadi.');
            return 0;
        }

        // O'qituvchilar bo'yicha guruhlash
        $lessonsByTeacher = $todayLessons->groupBy('employee_id');

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($lessonsByTeacher as $employeeId => $lessons) {
            // O'qituvchini topish
            $teacher = Teacher::where('hemis_id', $employeeId)->first();

            if (!$teacher || !$teacher->telegram_chat_id) {
                $skippedCount++;
                continue;
            }

            $missingAttendance = [];
            $missingGrades = [];

            foreach ($lessons as $lesson) {
                // Davomat tekshirish: bu dars uchun attendance yozuvi bormi?
                $hasAttendance = Attendance::where('employee_id', $lesson->employee_id)
                    ->where('subject_schedule_id', $lesson->subject_schedule_id)
                    ->whereDate('lesson_date', $today)
                    ->where('lesson_pair_code', $lesson->lesson_pair_code)
                    ->exists();

                if (!$hasAttendance) {
                    $missingAttendance[] = $lesson;
                }

                // Baho tekshirish: bu dars uchun baho qo'yilganmi?
                $hasGrades = StudentGrade::where('employee_id', $lesson->employee_id)
                    ->where('subject_schedule_id', $lesson->subject_schedule_id)
                    ->whereDate('lesson_date', $today)
                    ->where('lesson_pair_code', $lesson->lesson_pair_code)
                    ->whereNotNull('grade')
                    ->exists();

                if (!$hasGrades) {
                    $missingGrades[] = $lesson;
                }
            }

            // Agar hech qanday muammo bo'lmasa, keyingisiga o'tish
            if (empty($missingAttendance) && empty($missingGrades)) {
                continue;
            }

            // Xabar tuzish
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
            foreach ($missingAttendance as $lesson) {
                $time = $lesson->lesson_pair_start_time ? " ({$lesson->lesson_pair_start_time}-{$lesson->lesson_pair_end_time})" : "";
                $lines[] = "  - {$lesson->subject_name} | {$lesson->group_name} | {$lesson->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        if (!empty($missingGrades)) {
            $lines[] = "Baho qo'yilmagan darslar:";
            foreach ($missingGrades as $lesson) {
                $time = $lesson->lesson_pair_start_time ? " ({$lesson->lesson_pair_start_time}-{$lesson->lesson_pair_end_time})" : "";
                $lines[] = "  - {$lesson->subject_name} | {$lesson->group_name} | {$lesson->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        $lines[] = "Iltimos, tizimga kirib davomat va baholarni kiriting.";

        return implode("\n", $lines);
    }
}

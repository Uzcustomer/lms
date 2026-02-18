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

class SendAttendanceGroupSummary extends Command
{
    protected $signature = 'teachers:send-group-summary';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilar haqida Telegram guruhga umumlashtirilgan hisobot yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = Carbon::today()->format('Y-m-d');

        $excludedTrainingTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Bugungi sana: {$today}");
        $this->info("Telegram guruhga umumlashtirilgan hisobot yuborilmoqda...");

        $todaySchedules = Schedule::whereDate('lesson_date', $today)->get();

        if ($todaySchedules->isEmpty()) {
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        $schedulesByTeacher = $todaySchedules->groupBy('employee_id');

        $teachersMissingAttendance = [];
        $teachersMissingGrades = [];

        foreach ($schedulesByTeacher as $employeeId => $schedules) {
            $teacher = Teacher::where('hemis_id', $employeeId)->first();
            $teacherName = $teacher ? $teacher->full_name : ($schedules->first()->employee_name ?? "ID: {$employeeId}");

            $missingAttendanceCount = 0;
            $missingGradeCount = 0;

            foreach ($schedules as $schedule) {
                $trainingTypeCode = (int) $schedule->training_type_code;

                // Davomat tekshirish
                $hasAttendance = Attendance::where('employee_id', $schedule->employee_id)
                    ->where('subject_schedule_id', $schedule->schedule_hemis_id)
                    ->whereDate('lesson_date', $today)
                    ->where('lesson_pair_code', $schedule->lesson_pair_code)
                    ->exists();

                if (!$hasAttendance) {
                    $missingAttendanceCount++;
                }

                // Baho tekshirish (faqat amaliyot turlari uchun)
                if (!in_array($trainingTypeCode, $excludedTrainingTypes)) {
                    $hasGrades = StudentGrade::where('employee_id', $schedule->employee_id)
                        ->where('subject_schedule_id', $schedule->schedule_hemis_id)
                        ->whereDate('lesson_date', $today)
                        ->where('lesson_pair_code', $schedule->lesson_pair_code)
                        ->whereNotNull('grade')
                        ->exists();

                    if (!$hasGrades) {
                        $missingGradeCount++;
                    }
                }
            }

            if ($missingAttendanceCount > 0) {
                $department = $schedules->first()->department_name ?? 'Noma\'lum';
                $teachersMissingAttendance[] = [
                    'name' => $teacherName,
                    'department' => $department,
                    'count' => $missingAttendanceCount,
                ];
            }

            if ($missingGradeCount > 0) {
                $department = $schedules->first()->department_name ?? 'Noma\'lum';
                $teachersMissingGrades[] = [
                    'name' => $teacherName,
                    'department' => $department,
                    'count' => $missingGradeCount,
                ];
            }
        }

        if (empty($teachersMissingAttendance) && empty($teachersMissingGrades)) {
            $this->info('Barcha o\'qituvchilar davomat va baholarni kiritgan.');
            return 0;
        }

        $message = $this->buildGroupMessage($teachersMissingAttendance, $teachersMissingGrades, $today, $todaySchedules->count());

        $groupChatId = config('services.telegram.attendance_group_id');

        if (!$groupChatId) {
            $this->error('TELEGRAM_ATTENDANCE_GROUP_ID sozlanmagan. .env fayliga qo\'shing.');
            Log::warning('TELEGRAM_ATTENDANCE_GROUP_ID is not configured.');
            return 1;
        }

        try {
            $telegram->sendToUser($groupChatId, $message);
            $this->info('Umumlashtirilgan hisobot Telegram guruhga yuborildi.');
        } catch (\Throwable $e) {
            Log::error('Telegram guruhga hisobot yuborishda xato: ' . $e->getMessage());
            $this->error('Xato: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function buildGroupMessage(array $missingAttendance, array $missingGrades, string $today, int $totalLessons): string
    {
        $lines = [];
        $lines[] = "📊 KUNLIK HISOBOT — {$today}";
        $lines[] = str_repeat('─', 30);
        $lines[] = "";

        $totalTeachers = count(
            collect(array_merge($missingAttendance, $missingGrades))
                ->pluck('name')
                ->unique()
        );
        $totalMissingAttendance = array_sum(array_column($missingAttendance, 'count'));
        $totalMissingGrades = array_sum(array_column($missingGrades, 'count'));

        $lines[] = "📋 Jami darslar: {$totalLessons}";
        $lines[] = "👨‍🏫 Muammoli o'qituvchilar: {$totalTeachers}";
        $lines[] = "";

        if (!empty($missingAttendance)) {
            $lines[] = "❌ DAVOMAT OLINMAGAN ({$totalMissingAttendance} ta dars):";
            $lines[] = "";

            // Kafedra bo'yicha guruhlash
            $byDepartment = collect($missingAttendance)->groupBy('department');

            foreach ($byDepartment as $dept => $teachers) {
                $lines[] = "  📁 {$dept}:";
                foreach ($teachers as $teacher) {
                    $lines[] = "    • {$teacher['name']} — {$teacher['count']} ta dars";
                }
            }
            $lines[] = "";
        }

        if (!empty($missingGrades)) {
            $lines[] = "❌ BAHO QO'YILMAGAN ({$totalMissingGrades} ta dars):";
            $lines[] = "";

            $byDepartment = collect($missingGrades)->groupBy('department');

            foreach ($byDepartment as $dept => $teachers) {
                $lines[] = "  📁 {$dept}:";
                foreach ($teachers as $teacher) {
                    $lines[] = "    • {$teacher['name']} — {$teacher['count']} ta dars";
                }
            }
            $lines[] = "";
        }

        $lines[] = str_repeat('─', 30);
        $lines[] = "🕐 Hisobot vaqti: " . Carbon::now()->format('H:i');

        return implode("\n", $lines);
    }
}

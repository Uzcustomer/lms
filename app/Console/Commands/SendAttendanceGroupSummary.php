<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Teacher;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAttendanceGroupSummary extends Command
{
    protected $signature = 'teachers:send-group-summary';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilar haqida Telegram guruhga jadval ko\'rinishida hisobot yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = Carbon::today()->format('Y-m-d');

        $excludedTrainingTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Bugungi sana: {$today}");
        $this->info("Telegram guruhga jadval hisobot yuborilmoqda...");

        $todaySchedules = Schedule::whereDate('lesson_date', $today)
            ->orderBy('department_name')
            ->orderBy('employee_name')
            ->orderBy('lesson_pair_code')
            ->get();

        if ($todaySchedules->isEmpty()) {
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        // Batch load related data to avoid N+1 queries
        $groupIds = $todaySchedules->pluck('group_id')->unique()->toArray();

        $groups = Group::whereIn('group_hemis_id', $groupIds)
            ->get()
            ->keyBy('group_hemis_id');

        $studentCounts = Student::whereIn('group_id', $groupIds)
            ->selectRaw('group_id, count(*) as cnt')
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // Batch check attendance records
        $attendanceKeys = [];
        $attendanceRecords = Attendance::whereDate('lesson_date', $today)
            ->select('employee_id', 'subject_schedule_id', 'lesson_pair_code')
            ->distinct()
            ->get();

        foreach ($attendanceRecords as $record) {
            $key = $record->employee_id . '|' . $record->subject_schedule_id . '|' . $record->lesson_pair_code;
            $attendanceKeys[$key] = true;
        }

        // Batch check grade records
        $gradeKeys = [];
        $gradeRecords = StudentGrade::whereDate('lesson_date', $today)
            ->whereNotNull('grade')
            ->select('employee_id', 'subject_schedule_id', 'lesson_pair_code')
            ->distinct()
            ->get();

        foreach ($gradeRecords as $record) {
            $key = $record->employee_id . '|' . $record->subject_schedule_id . '|' . $record->lesson_pair_code;
            $gradeKeys[$key] = true;
        }

        // Build table rows
        $tableRows = [];
        $totalMissingAttendance = 0;
        $totalMissingGrades = 0;
        $teachersWithIssues = [];
        $rowNum = 0;

        foreach ($todaySchedules as $schedule) {
            $trainingTypeCode = (int) $schedule->training_type_code;

            // Check attendance using batch-loaded data
            $scheduleKey = $schedule->employee_id . '|' . $schedule->schedule_hemis_id . '|' . $schedule->lesson_pair_code;
            $hasAttendance = isset($attendanceKeys[$scheduleKey]);

            // Check grade
            $gradeApplicable = !in_array($trainingTypeCode, $excludedTrainingTypes);
            $hasGrade = true;
            if ($gradeApplicable) {
                $hasGrade = isset($gradeKeys[$scheduleKey]);
            }

            // Track stats
            if (!$hasAttendance) {
                $totalMissingAttendance++;
                $teachersWithIssues[$schedule->employee_id] = true;
            }
            if ($gradeApplicable && !$hasGrade) {
                $totalMissingGrades++;
                $teachersWithIssues[$schedule->employee_id] = true;
            }

            // Get group info
            $group = $groups[$schedule->group_id] ?? null;
            $specialty = $group ? ($group->specialty_name ?? '-') : '-';
            $studCount = $studentCounts[$schedule->group_id] ?? 0;

            // Calculate course year from semester code
            $semCode = max((int) ($schedule->semester_code ?? 1), 1);
            $kurs = (int) ceil($semCode / 2);

            // Format time
            $time = '-';
            if ($schedule->lesson_pair_start_time && $schedule->lesson_pair_end_time) {
                $time = substr($schedule->lesson_pair_start_time, 0, 5) . '-' . substr($schedule->lesson_pair_end_time, 0, 5);
            }

            $rowNum++;

            $tableRows[] = [
                $rowNum,
                TableImageGenerator::truncate($schedule->employee_name ?? '-', 22),
                TableImageGenerator::truncate($schedule->faculty_name ?? '-', 18),
                TableImageGenerator::truncate($specialty, 18),
                $kurs,
                $schedule->semester_code ?? '-',
                TableImageGenerator::truncate($schedule->department_name ?? '-', 18),
                TableImageGenerator::truncate($schedule->subject_name ?? '-', 22),
                $schedule->group_name ?? '-',
                TableImageGenerator::truncate($schedule->training_type_name ?? '-', 14),
                $time,
                $studCount,
                $hasAttendance,       // true/false - TableImageGenerator will render as Ha/Yo'q
                $gradeApplicable ? $hasGrade : null,  // true/false/null(-)
                $today,
            ];
        }

        $groupChatId = config('services.telegram.attendance_group_id');

        if (!$groupChatId) {
            $this->error('TELEGRAM_ATTENDANCE_GROUP_ID sozlanmagan. .env fayliga qo\'shing.');
            Log::warning('TELEGRAM_ATTENDANCE_GROUP_ID is not configured.');
            return 1;
        }

        // Build summary text message
        $summaryText = $this->buildSummaryText($today, $todaySchedules->count(), $teachersWithIssues, $totalMissingAttendance, $totalMissingGrades);

        // Generate table image(s)
        $headers = [
            '#', 'XODIM FISH', 'FAKULTET', "YO'NALISH", 'KURS', 'SEM',
            'KAFEDRA', 'FAN', 'GURUH', "MASHG'ULOT TURI",
            'VAQT', 'T.SONI', 'DAVOMAT', 'BAHO', 'SANA',
        ];

        $generator = new TableImageGenerator();
        $images = $generator->generate($headers, $tableRows, "KUNLIK HISOBOT - {$today}");

        $tempFiles = [];

        try {
            // Send text summary first
            $telegram->sendToUser($groupChatId, $summaryText);

            // Send table image(s)
            foreach ($images as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = '';
                if (count($images) > 1) {
                    $caption = ($index + 1) . '/' . count($images) . '-sahifa';
                }
                $telegram->sendPhoto($groupChatId, $imagePath, $caption);
            }

            $this->info('Jadval hisobot Telegram guruhga yuborildi. Rasmlar: ' . count($images));
        } catch (\Throwable $e) {
            Log::error('Telegram guruhga hisobot yuborishda xato: ' . $e->getMessage());
            $this->error('Xato: ' . $e->getMessage());
            return 1;
        } finally {
            // Clean up temp files
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        return 0;
    }

    private function buildSummaryText(string $today, int $totalLessons, array $teachersWithIssues, int $missingAttendance, int $missingGrades): string
    {
        $lines = [];
        $lines[] = "📊 KUNLIK HISOBOT — {$today}";
        $lines[] = str_repeat('─', 30);
        $lines[] = "";
        $lines[] = "📋 Jami darslar: {$totalLessons}";
        $lines[] = "👨‍🏫 Muammoli o'qituvchilar: " . count($teachersWithIssues);
        $lines[] = "";

        if ($missingAttendance > 0) {
            $lines[] = "❌ Davomat olinmagan: {$missingAttendance} ta dars";
        } else {
            $lines[] = "✅ Barcha darslar uchun davomat olingan";
        }

        if ($missingGrades > 0) {
            $lines[] = "❌ Baho qo'yilmagan: {$missingGrades} ta dars";
        } else {
            $lines[] = "✅ Barcha darslar uchun baho qo'yilgan";
        }

        $lines[] = "";
        $lines[] = str_repeat('─', 30);
        $lines[] = "🕐 Hisobot vaqti: " . Carbon::now()->format('H:i');

        if ($missingAttendance === 0 && $missingGrades === 0) {
            $lines[] = "";
            $lines[] = "🎉 Barcha o'qituvchilar davomat va baholarni kiritgan!";
        }

        return implode("\n", $lines);
    }
}

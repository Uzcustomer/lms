<?php

namespace App\Console\Commands;

use App\Models\Curriculum;
use App\Services\ScheduleImportService;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAttendanceGroupSummary extends Command
{
    protected $signature = 'teachers:send-group-summary {--chat-id= : Test uchun shaxsiy Telegram chat_id}';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilar haqida Telegram guruhga jadval ko\'rinishida hisobot yuborish';

    public function handle(TelegramService $telegram, ScheduleImportService $importService): int
    {
        $today = Carbon::today();
        $todayStr = $today->format('Y-m-d');
        $now = Carbon::now();

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Bugungi sana: {$todayStr}");

        // 1-QADAM: Avval HEMIS dan jadval ma'lumotlarini yangilash
        $this->info("HEMIS dan jadval yangilanmoqda...");
        try {
            $importService->importBetween($today->copy()->startOfDay(), $today->copy()->endOfDay());
            $this->info("Jadval muvaffaqiyatli yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('HEMIS sinxronlashda xato (hisobot davom etadi): ' . $e->getMessage());
            $this->warn("HEMIS yangilashda xato: " . $e->getMessage());
        }

        // 2-QADAM: Jadvaldan ma'lumot olish (web hisobot bilan bir xil logika)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) = ?', [$todayStr])
            ->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            })
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
                'sch.employee_name',
                'sch.faculty_name',
                'g.specialty_name',
                'sem.level_name',
                'sch.semester_code',
                'sch.semester_name',
                'sch.department_name',
                'sch.subject_id',
                'sch.subject_name',
                'sch.group_id',
                'sch.group_name',
                'sch.training_type_code',
                'sch.training_type_name',
                'sch.lesson_pair_code',
                'sch.lesson_pair_start_time',
                'sch.lesson_pair_end_time',
                DB::raw('DATE(sch.lesson_date) as lesson_date_str')
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        // 3-QADAM: Davomat va baho tekshirish (web hisobot bilan bir xil logika)
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();

        // Davomat: attendance_controls jadvalidan (web hisobot bilan bir xil)
        $attendanceSet = DB::table('attendance_controls')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) = ?', [$todayStr])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        // Baho: student_grades jadvalidan (web hisobot bilan bir xil)
        $gradeSet = DB::table('student_grades')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereRaw('DATE(lesson_date) = ?', [$todayStr])
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        // Talaba sonini guruh bo'yicha hisoblash (faqat faol talabalar)
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // 4-QADAM: Ma'lumotlarni guruhlash (web hisobot bilan bir xil kalit)
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '-';

            // Davomat va baho tekshirish uchun atribut kalitlari
            $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $gradeKey = $sch->employee_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            if (!isset($grouped[$key])) {
                $semCode = max((int) ($sch->semester_code ?? 1), 1);

                $grouped[$key] = [
                    'employee_name' => $sch->employee_name,
                    'faculty_name' => $sch->faculty_name,
                    'specialty_name' => $sch->specialty_name ?? '-',
                    'level_name' => $sch->level_name ?? '-',
                    'semester_name' => $sch->semester_name ?? $sch->semester_code,
                    'department_name' => $sch->department_name,
                    'subject_name' => $sch->subject_name,
                    'group_name' => $sch->group_name,
                    'training_type' => $sch->training_type_name,
                    'lesson_pair_time' => $pairTime,
                    'student_count' => $studentCounts[$sch->group_id] ?? 0,
                    'has_attendance' => isset($attendanceSet[$attKey]),
                    'has_grades' => isset($gradeSet[$gradeKey]),
                    'lesson_date' => $sch->lesson_date_str,
                    'kurs' => (int) ceil($semCode / 2),
                    'employee_id' => $sch->employee_id,
                ];
            }
        }

        // 5-QADAM: Faqat kamida biri yo'q (any_missing) filtrini qo'llash
        $filtered = array_filter($grouped, function ($r) {
            return !$r['has_attendance'] || !$r['has_grades'];
        });

        $totalSchedules = count($grouped);
        $results = array_values($filtered);

        // Saralash: kafedra, xodim, vaqt bo'yicha
        usort($results, function ($a, $b) {
            return strcasecmp($a['department_name'] . $a['employee_name'], $b['department_name'] . $b['employee_name']);
        });

        // Chat ID: --chat-id parametri yoki config dan
        $groupChatId = $this->option('chat-id') ?: config('services.telegram.attendance_group_id');

        if ($this->option('chat-id')) {
            $this->info("TEST rejim: xabar {$groupChatId} ga yuboriladi");
        }

        if (empty($results)) {
            $this->info("Barcha o'qituvchilar davomat va baholarni kiritgan. Jami darslar: {$totalSchedules}");

            if ($groupChatId) {
                $summaryText = $this->buildSummaryText($todayStr, $now, $totalSchedules, [], 0, 0);
                $telegram->sendToUser($groupChatId, $summaryText);
            }

            return 0;
        }

        // Statistika
        $totalMissingAttendance = 0;
        $totalMissingGrades = 0;
        $teachersWithIssues = [];

        foreach ($results as $r) {
            if (!$r['has_attendance']) {
                $totalMissingAttendance++;
                $teachersWithIssues[$r['employee_id']] = true;
            }
            if (!$r['has_grades']) {
                $totalMissingGrades++;
                $teachersWithIssues[$r['employee_id']] = true;
            }
        }

        if (!$groupChatId) {
            $this->error('TELEGRAM_ATTENDANCE_GROUP_ID sozlanmagan yoki --chat-id bering.');
            return 1;
        }

        // Jadval qatorlarini tayyorlash
        $tableRows = [];
        foreach ($results as $i => $r) {
            $tableRows[] = [
                $i + 1,
                TableImageGenerator::truncate($r['employee_name'] ?? '-', 22),
                TableImageGenerator::truncate($r['faculty_name'] ?? '-', 18),
                TableImageGenerator::truncate($r['specialty_name'] ?? '-', 18),
                $r['kurs'],
                $r['semester_name'] ?? '-',
                TableImageGenerator::truncate($r['department_name'] ?? '-', 18),
                TableImageGenerator::truncate($r['subject_name'] ?? '-', 22),
                $r['group_name'] ?? '-',
                TableImageGenerator::truncate($r['training_type'] ?? '-', 14),
                $r['lesson_pair_time'],
                $r['student_count'],
                $r['has_attendance'],
                $r['has_grades'],
                $now->format('H:i') . ' ' . $r['lesson_date'],
            ];
        }

        // Xulosa xabari
        $summaryText = $this->buildSummaryText($todayStr, $now, $totalSchedules, $teachersWithIssues, $totalMissingAttendance, $totalMissingGrades);

        // Jadval rasmini generatsiya qilish
        $headers = [
            '#', 'XODIM FISH', 'FAKULTET', "YO'NALISH", 'KURS', 'SEM',
            'KAFEDRA', 'FAN', 'GURUH', "MASHG'ULOT TURI",
            'VAQT', 'T.SONI', 'DAVOMAT', 'BAHO', 'SANA',
        ];

        $generator = new TableImageGenerator();
        $images = $generator->generate($headers, $tableRows, "KUNLIK HISOBOT - {$now->format('H:i')} {$todayStr} (Kamida biri yo'q: " . count($results) . ")");

        $tempFiles = [];

        try {
            $telegram->sendToUser($groupChatId, $summaryText);

            foreach ($images as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = '';
                if (count($images) > 1) {
                    $caption = ($index + 1) . '/' . count($images) . '-sahifa';
                }
                $telegram->sendPhoto($groupChatId, $imagePath, $caption);
            }

            $this->info("Hisobot yuborildi. Jami: {$totalSchedules}, Muammoli: " . count($results) . ", Rasmlar: " . count($images));
        } catch (\Throwable $e) {
            Log::error('Telegram guruhga hisobot yuborishda xato: ' . $e->getMessage());
            $this->error('Xato: ' . $e->getMessage());
            return 1;
        } finally {
            foreach ($tempFiles as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }

        return 0;
    }

    private function buildSummaryText(string $today, Carbon $now, int $totalLessons, array $teachersWithIssues, int $missingAttendance, int $missingGrades): string
    {
        $lines = [];
        $lines[] = "📊 KUNLIK HISOBOT — {$now->format('H:i')} {$today}";
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
        $lines[] = "🕐 Hisobot vaqti: " . $now->format('H:i');

        if ($missingAttendance === 0 && $missingGrades === 0) {
            $lines[] = "";
            $lines[] = "🎉 Barcha o'qituvchilar davomat va baholarni kiritgan!";
        }

        return implode("\n", $lines);
    }
}

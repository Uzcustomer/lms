<?php

namespace App\Console\Commands;

use App\Services\ScheduleImportService;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendAttendanceFinalDailyReport extends Command
{
    protected $signature = 'teachers:send-final-daily-report {--chat-id= : Test uchun shaxsiy Telegram chat_id} {--date= : Hisobot sanasi (Y-m-d), standart: kecha}';

    protected $description = 'Kechagi kunning yakuniy davomat va baho hisobotini ertalab Telegram guruhga yuborish (faqat o\'qituvchilar kesimi)';

    public function handle(TelegramService $telegram, ScheduleImportService $importService): int
    {
        $reportDate = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $reportDateStr = $reportDate->format('Y-m-d');
        $formattedDate = $reportDate->format('d.m.Y');
        $now = Carbon::now();

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Hisobot sanasi: {$reportDateStr} (yakuniy)");

        // 1-QADAM: Avval HEMIS dan jadval ma'lumotlarini yangilash
        $this->info("HEMIS dan jadval yangilanmoqda ({$reportDateStr})...");
        try {
            $importService->importBetween($reportDate->copy()->startOfDay(), $reportDate->copy()->endOfDay());
            $this->info("Jadval muvaffaqiyatli yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('HEMIS sinxronlashda xato (hisobot davom etadi): ' . $e->getMessage());
            $this->warn("HEMIS yangilashda xato: " . $e->getMessage());
        }

        // 1.5-QADAM: Davomat nazorati (attendance_controls) yangilash
        $this->info("HEMIS dan davomat nazorati yangilanmoqda ({$reportDateStr})...");
        try {
            \Illuminate\Support\Facades\Artisan::call('import:attendance-controls', [
                '--date' => $reportDateStr,
                '--silent' => true,
            ]);
            $this->info("Davomat nazorati yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('Davomat nazorati yangilashda xato (hisobot davom etadi): ' . $e->getMessage());
            $this->warn("Davomat nazorati yangilashda xato: " . $e->getMessage());
        }

        // 1.6-QADAM: Baholarni HEMIS dan yangilash (student_grades)
        $this->info("HEMIS dan baholar yangilanmoqda ({$reportDateStr})...");
        try {
            \Illuminate\Support\Facades\Artisan::call('student:import-data', [
                '--mode' => 'final',
            ]);
            $this->info("Baholar yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('Baholar yangilashda xato (hisobot davom etadi): ' . $e->getMessage());
            $this->warn("Baholar yangilashda xato: " . $e->getMessage());
        }

        // 2-QADAM: Jadvaldan kechagi kun ma'lumotlarini olish
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) = ?', [$reportDateStr])
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
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
                'sem.level_code',
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
            $this->info("Kechagi kun ({$reportDateStr}) uchun dars jadvali topilmadi.");
            return 0;
        }

        // Davomat va baho tekshirish
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();

        // Davomat (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        $attendanceByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->pluck('subject_schedule_id')
            ->flip();

        // Davomat (2-usul): atribut kalitlari orqali tekshirish (zaxira)
        $attendanceByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) = ?', [$reportDateStr])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        // Baho (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        $gradeByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->pluck('subject_schedule_id')
            ->unique()
            ->flip();

        // Baho (2-usul): student → group orqali tekshirish (zaxira)
        $gradeByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.employee_id', $employeeIds)
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) = ?', [$reportDateStr])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(sg.employee_id, '|', st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.training_type_code, '|', sg.lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // Ma'lumotlarni guruhlash
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '-';

            $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $gradeKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            // Davomat: schedule_hemis_id orqali yoki atribut kaliti orqali tekshirish
            $hasAtt = isset($attendanceByScheduleId[$sch->schedule_hemis_id])
                   || isset($attendanceByKey[$attKey]);

            if (!isset($grouped[$key])) {
                $semCode = max((int) ($sch->semester_code ?? 1), 1);
                $skipGradeCheck = in_array($sch->training_type_code, $gradeExcludedTypes);

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
                    'has_attendance' => $hasAtt,
                    'has_grades' => $skipGradeCheck ? null : (isset($gradeByScheduleId[$sch->schedule_hemis_id]) || isset($gradeByKey[$gradeKey])),
                    'lesson_date' => $sch->lesson_date_str,
                    'kurs' => $sch->level_code ? ((int) $sch->level_code % 10) : (int) ceil($semCode / 2),
                    'employee_id' => $sch->employee_id,
                ];
            }
        }

        // Faqat kamida biri yo'q bo'lganlarni filtrlash
        $filtered = array_filter($grouped, function ($r) {
            return !$r['has_attendance'] || $r['has_grades'] === false;
        });

        $totalSchedules = count($grouped);
        $results = array_values($filtered);

        // Saralash: kafedra, xodim bo'yicha
        usort($results, function ($a, $b) {
            return strcasecmp($a['department_name'] . $a['employee_name'], $b['department_name'] . $b['employee_name']);
        });

        $groupChatId = $this->option('chat-id') ?: config('services.telegram.attendance_group_id');

        if (!$groupChatId) {
            $this->error('TELEGRAM_ATTENDANCE_GROUP_ID sozlanmagan yoki --chat-id bering.');
            return 1;
        }

        if (empty($results)) {
            $this->info("Kechagi kun barcha o'qituvchilar davomat va baholarni kiritgan. Jami darslar: {$totalSchedules}");

            $telegram->sendToUser($groupChatId, "✅ KECHAGI KUN YAKUNIY HISOBOT — {$formattedDate}\n\nBarcha o'qituvchilar davomat va baholarni kiritgan!\nJami darslar: {$totalSchedules}");

            return 0;
        }

        // O'qituvchilar kesimi jadval qatorlarini tayyorlash
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
                $r['lesson_date'],
            ];
        }

        $headers = [
            '#', 'XODIM FISH', 'FAKULTET', "YO'NALISH", 'KURS', 'SEM',
            'KAFEDRA', 'FAN', 'GURUH', "MASHG'ULOT TURI",
            'VAQT', 'T.SONI', 'DAVOMAT', 'BAHO', 'SANA',
        ];

        $generator = new TableImageGenerator();
        $detailImages = $generator->generate($headers, $tableRows, "YAKUNIY HISOBOT: O'QITUVCHILAR KESIMI - {$formattedDate} (Kamida biri yo'q: " . count($results) . ")");

        $tempFiles = [];

        try {
            // Faqat o'qituvchilar kesimi jadval rasmlarini yuborish
            foreach ($detailImages as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = "Kechagi kun yakuniy hisobot — {$formattedDate}";
                if (count($detailImages) > 1) {
                    $caption .= ' ' . ($index + 1) . '/' . count($detailImages) . '-sahifa';
                }
                $telegram->sendPhoto($groupChatId, $imagePath, $caption);
            }

            $this->info("Yakuniy hisobot yuborildi. Jami: {$totalSchedules}, Muammoli: " . count($results) . ", Rasmlar: " . count($detailImages));
        } catch (\Throwable $e) {
            Log::error('Telegram guruhga yakuniy hisobot yuborishda xato: ' . $e->getMessage());
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
}

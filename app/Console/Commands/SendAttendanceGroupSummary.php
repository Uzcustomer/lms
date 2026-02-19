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
    protected $signature = 'teachers:send-group-summary {--chat-id= : Test uchun shaxsiy Telegram chat_id} {--detail : O\'qituvchilar kesimi batafsil jadvalini ham yuborish}';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilar haqida Telegram guruhga jadval ko\'rinishida hisobot yuborish';

    public function handle(TelegramService $telegram, ScheduleImportService $importService): int
    {
        $today = Carbon::today();
        $todayStr = $today->format('Y-m-d');
        $now = Carbon::now();

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        // Bu turlarga faqat davomat tekshiriladi, baho tekshirilmaydi (ma'ruza va h.k.)
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

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

        // 1.5-QADAM: Bugungi davomat nazorati (attendance_controls) yangilash
        $this->info("HEMIS dan bugungi davomat nazorati yangilanmoqda...");
        try {
            \Illuminate\Support\Facades\Artisan::call('import:attendance-controls', [
                '--date' => $todayStr,
                '--silent' => true,
            ]);
            $this->info("Davomat nazorati yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('Davomat nazorati yangilashda xato (hisobot davom etadi): ' . $e->getMessage());
            $this->warn("Davomat nazorati yangilashda xato: " . $e->getMessage());
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
            ->whereNull('deleted_at')
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
                // Ma'ruza va boshqa maxsus turlarga baho talab qilinmaydi
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
                    'has_attendance' => isset($attendanceSet[$attKey]),
                    'has_grades' => $skipGradeCheck || isset($gradeSet[$gradeKey]),
                    'lesson_date' => $sch->lesson_date_str,
                    'kurs' => (int) ceil($semCode / 2),
                    'employee_id' => $sch->employee_id,
                    'academic_hours' => $this->calculateAcademicHours($sch->lesson_pair_start_time, $sch->lesson_pair_end_time),
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
                $summaryText = $this->buildSummaryText($todayStr, $now, $totalSchedules, [], 0, 0, 0, 0);
                $telegram->sendToUser($groupChatId, $summaryText);
            }

            return 0;
        }

        // Statistika
        $totalMissingAttendance = 0;
        $totalMissingGrades = 0;
        $teachersWithIssues = [];
        $teachersMissingAtt = [];
        $teachersMissingGrade = [];

        // Fakultet kesimi statistikasi
        $facultyStats = [];
        // Kafedra kesimi statistikasi
        $departmentStats = [];

        foreach ($results as $r) {
            $facultyName = $r['faculty_name'] ?? 'Noma\'lum';
            $deptName = $r['department_name'] ?? 'Noma\'lum';
            $subjectName = $r['subject_name'] ?? '-';
            $hours = $r['academic_hours'];

            if (!isset($facultyStats[$facultyName])) {
                $facultyStats[$facultyName] = ['total' => 0, 'no_attendance' => 0, 'no_grades' => 0, 'teachers' => []];
            }

            // Kafedra ichida fan bo'yicha guruhlash (faqat kafedra nomi bo'yicha)
            $deptKey = $deptName;
            if (!isset($departmentStats[$deptKey])) {
                $departmentStats[$deptKey] = [
                    'department_name' => $deptName,
                    'subjects' => [],
                ];
            }
            if (!isset($departmentStats[$deptKey]['subjects'][$subjectName])) {
                $departmentStats[$deptKey]['subjects'][$subjectName] = ['no_attendance' => 0, 'no_grades' => 0, 'total' => 0];
            }

            $facultyStats[$facultyName]['total'] += $hours;
            $departmentStats[$deptKey]['subjects'][$subjectName]['total'] += $hours;

            if (!$r['has_attendance']) {
                $totalMissingAttendance += $hours;
                $teachersWithIssues[$r['employee_id']] = true;
                $teachersMissingAtt[$r['employee_id']] = true;
                $facultyStats[$facultyName]['no_attendance'] += $hours;
                $facultyStats[$facultyName]['teachers'][$r['employee_id']] = true;
                $facultyStats[$facultyName]['teachers_att'][$r['employee_id']] = true;
                $departmentStats[$deptKey]['subjects'][$subjectName]['no_attendance'] += $hours;
            }
            if (!$r['has_grades']) {
                $totalMissingGrades += $hours;
                $teachersWithIssues[$r['employee_id']] = true;
                $teachersMissingGrade[$r['employee_id']] = true;
                $facultyStats[$facultyName]['no_grades'] += $hours;
                $facultyStats[$facultyName]['teachers'][$r['employee_id']] = true;
                $facultyStats[$facultyName]['teachers_grade'][$r['employee_id']] = true;
                $departmentStats[$deptKey]['subjects'][$subjectName]['no_grades'] += $hours;
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

        // Xulosa xabari (fakultet kesimi bilan)
        $summaryText = $this->buildSummaryText($todayStr, $now, $totalSchedules, $teachersWithIssues, $totalMissingAttendance, $totalMissingGrades, count($teachersMissingAtt), count($teachersMissingGrade), $facultyStats);

        // Kafedra kesimi jadval rasmini tayyorlash
        $deptTableRows = [];
        $deptNum = 0;
        // Kafedrani jami soat bo'yicha kamayish tartibida saralash
        $sortedDepts = $departmentStats;
        uasort($sortedDepts, function ($a, $b) {
            $totalA = array_sum(array_column($a['subjects'], 'total'));
            $totalB = array_sum(array_column($b['subjects'], 'total'));
            return $totalB <=> $totalA;
        });

        foreach ($sortedDepts as $dept) {
            $deptNum++;
            // Kafedra jami yig'indisini hisoblash
            $deptTotalAtt = array_sum(array_column($dept['subjects'], 'no_attendance'));
            $deptTotalGrade = array_sum(array_column($dept['subjects'], 'no_grades'));
            $deptTotal = array_sum(array_column($dept['subjects'], 'total'));

            // Kafedra sarlavha qatori (jami bilan)
            $deptTableRows[] = [
                $deptNum,
                TableImageGenerator::truncate($dept['department_name'], 30),
                $deptTotalAtt,
                $deptTotalGrade,
                $deptTotal,
            ];

            // Fanlarni jami soat bo'yicha kamayish tartibida saralash
            $subjects = $dept['subjects'];
            uasort($subjects, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            foreach ($subjects as $subjectName => $stats) {
                // Fan qatori (tab bilan)
                $deptTableRows[] = [
                    '',
                    '   ' . TableImageGenerator::truncate($subjectName, 27),
                    $stats['no_attendance'],
                    $stats['no_grades'],
                    $stats['total'],
                ];
            }
        }

        $generator = new TableImageGenerator();

        // Kafedra kesimi rasmi (ixcham rejimda - bitta rasmga sig'dirish uchun)
        $deptHeaders = ['#', 'KAFEDRA / FAN', 'DAV. YO\'Q', 'BAHO YO\'Q', 'JAMI SOAT'];
        $formattedDate = Carbon::parse($todayStr)->format('d.m.Y');
        $compactGenerator = (new TableImageGenerator())->compact();
        $deptImages = $compactGenerator->generate($deptHeaders, $deptTableRows, "KAFEDRA KESIMI - {$formattedDate} yil {$now->format('H:i')} soat (Kafedralar: {$deptNum})");

        // Batafsil jadval rasmi (faqat --detail flag bilan)
        $detailImages = [];
        if ($this->option('detail')) {
            $headers = [
                '#', 'XODIM FISH', 'FAKULTET', "YO'NALISH", 'KURS', 'SEM',
                'KAFEDRA', 'FAN', 'GURUH', "MASHG'ULOT TURI",
                'VAQT', 'T.SONI', 'DAVOMAT', 'BAHO', 'SANA',
            ];

            $detailImages = $generator->generate($headers, $tableRows, "O'QITUVCHILAR KESIMI - {$formattedDate} yil {$now->format('H:i')} soat (Kamida biri yo'q: " . count($results) . ")");
        }

        $tempFiles = [];

        try {
            // 1. Xulosa xabari
            $telegram->sendToUser($groupChatId, $summaryText);

            // 2. Kafedra kesimi rasmlari
            foreach ($deptImages as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = 'Kafedra kesimi';
                if (count($deptImages) > 1) {
                    $caption .= ' ' . ($index + 1) . '/' . count($deptImages) . '-sahifa';
                }
                $telegram->sendPhoto($groupChatId, $imagePath, $caption);
            }

            // 3. Batafsil jadval rasmlari
            foreach ($detailImages as $index => $imagePath) {
                $tempFiles[] = $imagePath;
                $caption = 'Batafsil hisobot';
                if (count($detailImages) > 1) {
                    $caption .= ' ' . ($index + 1) . '/' . count($detailImages) . '-sahifa';
                }
                $telegram->sendPhoto($groupChatId, $imagePath, $caption);
            }

            $totalImages = count($deptImages) + count($detailImages);
            $this->info("Hisobot yuborildi. Jami: {$totalSchedules}, Muammoli: " . count($results) . ", Rasmlar: {$totalImages}");
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

    private function buildSummaryText(string $today, Carbon $now, int $totalLessons, array $teachersWithIssues, int $missingAttendance, int $missingGrades, int $uniqueAttTeachers = 0, int $uniqueGradeTeachers = 0, array $facultyStats = []): string
    {
        $formattedDate = Carbon::parse($today)->format('d.m.Y');

        $lines = [];
        $lines[] = "📊 DAVOMAT OLMAGANLAR VA BAHO QO'YMAGANLAR KUNLIK HISOBOTI — {$formattedDate} yil {$now->format('H:i')} soat";
        $lines[] = str_repeat('─', 30);

        if ($missingAttendance > 0) {
            $lines[] = "📝 Davomat olinmagan: {$uniqueAttTeachers} o'qituvchi";
        } else {
            $lines[] = "✅ Barcha darslar uchun davomat olingan";
        }

        if ($missingGrades > 0) {
            $lines[] = "💯 Baho qo'yilmagan: {$uniqueGradeTeachers} o'qituvchi";
        } else {
            $lines[] = "✅ Barcha darslar uchun baho qo'yilgan";
        }

        // Fakultet kesimi
        if (!empty($facultyStats)) {
            $lines[] = "";
            $lines[] = str_repeat('─', 30);
            $lines[] = "🏛 FAKULTET KESIMI:";
            $lines[] = "";

            // Jami soat bo'yicha kamayish tartibida saralash
            uasort($facultyStats, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            $num = 0;
            foreach ($facultyStats as $fname => $fdata) {
                $num++;
                $attTeachers = count($fdata['teachers_att'] ?? []);
                $gradeTeachers = count($fdata['teachers_grade'] ?? []);
                $lines[] = "<b>{$num}. {$fname}: Jami: {$fdata['total']} soat</b>";
                $lines[] = "   📝 Davomat: {$fdata['no_attendance']} soat (👩‍🏫 {$attTeachers}) | 📕 Baho: {$fdata['no_grades']} soat (👩‍🏫 {$gradeTeachers})";
            }
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

    private function calculateAcademicHours(?string $startTime, ?string $endTime): int
    {
        if (!$startTime || !$endTime) {
            return 2; // standart juftlik = 2 soat
        }

        try {
            $start = Carbon::createFromFormat('H:i:s', $startTime);
            $end = Carbon::createFromFormat('H:i:s', $endTime);
            $minutes = $start->diffInMinutes($end);

            // 60+ minut = 2 akademik soat (juftlik), aks holda 1 soat
            return $minutes >= 60 ? 2 : 1;
        } catch (\Throwable $e) {
            return 2;
        }
    }
}

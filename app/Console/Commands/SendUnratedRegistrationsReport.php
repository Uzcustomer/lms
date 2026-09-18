<?php

namespace App\Console\Commands;

use App\Models\StaffRegistrationDivision;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendUnratedRegistrationsReport extends Command
{
    /** HEMIS talaba holati: 11 — "O'qimoqda". */
    private const ACTIVE_STATUS = 11;

    protected $signature = 'registrar:send-unrated-report
        {--chat-id= : Test uchun shaxsiy Telegram chat_id}
        {--from= : Davr boshi (Y-m-d) — berilmasa joriy semestr boshi}';

    protected $description = 'Semestr boshidan kechagacha baho qo\'yilmagan darslar haqida back ofis menejerlari kesimida registrator guruhiga hisobot yuborish (har kuni 08:30)';

    public function handle(TelegramService $telegram): int
    {
        $now = Carbon::now();
        $yesterday = Carbon::yesterday();
        $yesterdayStr = $yesterday->format('Y-m-d');

        // Baho tekshirishdan chiqariladigan mashg'ulot turlari
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $gradeExcludedSubjectPatterns = config('app.excluded_rating_subject_patterns', []);

        // 1-QADAM: Davr boshi — --from berilsa o'sha sana, aks holda joriy semestr boshi
        $semesterStart = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : $this->getSemesterStartDate();
        if (!$semesterStart) {
            $this->error('Joriy semestr topilmadi.');
            return 1;
        }
        $semesterStartStr = $semesterStart->format('Y-m-d');
        $this->info("Davr: {$semesterStartStr} — {$yesterdayStr}");

        // 2-QADAM: Back ofis menejerlarini olish
        $backOfficeManagers = StaffRegistrationDivision::active()
            ->where('division_type', 'back_office')
            ->with('teacher', 'department', 'specialty')
            ->get();

        if ($backOfficeManagers->isEmpty()) {
            $this->info('Back ofis menejerlari topilmadi.');
            return 0;
        }

        // Menejerlarni teacher_id bo'yicha guruhlash (bir menejer bir necha biriktirishga ega bo'lishi mumkin)
        $managerAssignments = $backOfficeManagers->groupBy('teacher_id');

        $this->info("Back ofis menejerlari: {$managerAssignments->count()} ta");

        // 3-QADAM: Semestr davridagi barcha darslarni olish (semestr boshi — kecha)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->whereNotIn('sch.training_type_code', $gradeExcludedTypes)
            ->where('sch.education_year_current', true)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) >= ?', [$semesterStartStr])
            ->whereRaw('DATE(sch.lesson_date) <= ?', [$yesterdayStr])
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            ->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            })
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
                'sch.employee_name',
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
                'g.department_hemis_id as group_department_hemis_id',
                'g.specialty_hemis_id as group_specialty_hemis_id',
                'sem.level_code',
                'sch.semester_code',
                DB::raw('DATE(sch.lesson_date) as lesson_date_str'),
                DB::raw('EXISTS (SELECT 1 FROM lesson_openings lo WHERE lo.group_hemis_id = sch.group_id AND lo.subject_id = sch.subject_id AND lo.semester_code = sch.semester_code AND DATE(lo.lesson_date) = DATE(sch.lesson_date)) as has_opening')
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('Dars jadvali topilmadi.');
            return 0;
        }

        $this->info("Jami darslar: {$schedules->count()}");

        // 4-QADAM: Baho qo'yilganligini tekshirish (har bir talaba uchun)
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();

        // Fanga biriktirilgan talabalar soni (student_subjects jadvalidan).
        // Faqat o'qiyotganlar (status 11 — "O'qimoqda") sanaladi: chetlashtirilgan,
        // akademik ta'tildagi va boshqa o'qimayotgan talabalarga baho qo'yilmasligi
        // normal, ular hisobotga tushmasligi kerak.
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $semesterCodes = $schedules->pluck('semester_code')->unique()->values()->toArray();

        $subjectStudentCounts = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereIn('ss.subject_id', $subjectIds)
            ->whereIn('ss.semester_id', $semesterCodes)
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->select(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id) as gs_key"), DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id)"))
            ->pluck('cnt', 'gs_key');

        // Zaxira: agar student_subjects da ma'lumot bo'lmasa, guruh bo'yicha hisoblash
        $groupStudentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', self::ACTIVE_STATUS)
            ->groupBy('group_id')
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->pluck('cnt', 'group_id');

        // Baho (1-usul): subject_schedule_id orqali — baho qo'yilgan o'qiyotgan talabalar soni.
        // O'qimayotganlar ro'yxatini NOT IN bilan berish o'rniga talabalar jadvaliga
        // bog'lanadi: ro'yxat minglab bo'lishi mumkin va so'rovni sekinlashtiradi.
        $gradeCountByScheduleId = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.subject_schedule_id', $scheduleHemisIds)
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(function ($q) {
                $q->where('sg.grade', '>', 0)
                  ->orWhere('sg.retake_grade', '>', 0)
                  ->orWhere('sg.status', 'recorded')
                  ->orWhere('sg.reason', 'absent');
            })
            ->groupBy('sg.subject_schedule_id')
            ->select('sg.subject_schedule_id', DB::raw('COUNT(DISTINCT sg.student_hemis_id) as graded_count'))
            ->pluck('graded_count', 'subject_schedule_id');

        // Baho (2-usul): guruh + fan + sana orqali — baho qo'yilgan faol talabalar soni
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $gradeCountByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereIn('sg.subject_id', $subjectIds)
            ->whereNotNull('sg.lesson_date')
            ->whereRaw('DATE(sg.lesson_date) >= ?', [$semesterStartStr])
            ->whereRaw('DATE(sg.lesson_date) <= ?', [$yesterdayStr])
            ->where('st.student_status_code', self::ACTIVE_STATUS)
            ->where(function ($q) {
                $q->where('sg.grade', '>', 0)
                  ->orWhere('sg.retake_grade', '>', 0)
                  ->orWhere('sg.status', 'recorded')
                  ->orWhere('sg.reason', 'absent');
            })
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date))"))
            ->select(
                DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date)) as gk"),
                DB::raw('COUNT(DISTINCT sg.student_hemis_id) as graded_count')
            )
            ->pluck('graded_count', 'gk');

        // 5-QADAM: Baho qo'yilmagan darslarni filtrlash va menejerga biriktirish
        $unratedByManager = []; // teacher_id => [darslar ro'yxati]
        $unassigned = []; // Menejer topilmagan darslar

        foreach ($schedules as $sch) {
            // Fan nomi bo'yicha chiqarilganlarni tekshirish
            $subjectNameLower = mb_strtolower($sch->subject_name ?? '');
            $skipGrade = !empty($gradeExcludedSubjectPatterns) && collect($gradeExcludedSubjectPatterns)
                ->contains(fn($p) => str_contains($subjectNameLower, mb_strtolower($p)));
            if ($skipGrade) {
                continue;
            }

            // Fanga biriktirilgan faol talabalar soni (student_subjects), topilmasa guruh soni
            $gsKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code;
            $subjectTotal = $subjectStudentCounts[$gsKey] ?? ($groupStudentCounts[$sch->group_id] ?? 0);
            if ($subjectTotal === 0) {
                continue; // Bu fanga biriktirilgan faol talaba yo'q
            }

            // Baho qo'yilgan talabalar soni (ikkala usuldan kattasini olish)
            $gradeKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str;
            $gradedBySchedule = $gradeCountByScheduleId[$sch->schedule_hemis_id] ?? 0;
            $gradedByKey = $gradeCountByKey[$gradeKey] ?? 0;
            $gradedCount = max($gradedBySchedule, $gradedByKey);

            // Barcha biriktirilgan talabaga baho/NB qo'yilgan bo'lsa — o'tkazib yuborish
            if ($gradedCount >= $subjectTotal) {
                continue;
            }

            // Mas'ul back ofis menejerni topish
            $managerId = $this->findBackOfficeManager(
                $managerAssignments,
                $sch->group_department_hemis_id,
                $sch->group_specialty_hemis_id,
                $sch->level_code
            );

            // Duplikatlarni tekshirish (web controller bilan bir xil 6 ta ID maydon)
            $uniqueKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                       . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            $missingCount = max(0, $subjectTotal - $gradedCount);

            $entry = [
                'employee_name' => $sch->employee_name,
                'department_name' => $sch->department_name,
                'subject_name' => $sch->subject_name,
                'group_name' => $sch->group_name,
                'training_type_name' => $sch->training_type_name,
                'lesson_pair_time' => $pairTime,
                'lesson_date' => $sch->lesson_date_str,
                'has_opening' => $sch->has_opening ?? false,
                'missing_grade_count' => $missingCount,
                'total_students' => $subjectTotal,
            ];

            if ($managerId) {
                $unratedByManager[$managerId][$uniqueKey] = $entry;
            } else {
                $unassigned[$uniqueKey] = $entry;
            }
        }

        // Unique array values (duplikatlar key bo'yicha allaqachon olib tashlangan)
        foreach ($unratedByManager as $tid => $entries) {
            $unratedByManager[$tid] = array_values($entries);
        }
        $unassigned = array_values($unassigned);

        $totalUnrated = array_sum(array_map('count', $unratedByManager)) + count($unassigned);
        $this->info("Baho qo'yilmaganlar: {$totalUnrated}");

        if ($totalUnrated === 0) {
            $this->info('Barcha darslarga baho qo\'yilgan.');

            $chatId = $this->option('chat-id') ?: config('services.telegram.registrar_group_id');
            if ($chatId) {
                $formattedDate = $yesterday->format('d.m.Y');
                $semesterStartFormatted = $semesterStart->format('d.m.Y');
                $telegram->sendToUser($chatId,
                    "✅ BAHO QO'YILMAGANLAR HISOBOTI\n"
                    . "📅 Davr: {$semesterStartFormatted} — {$formattedDate}\n"
                    . "⏰ {$now->format('H:i')} | {$now->format('d.m.Y')}\n\n"
                    . "🎉 Barcha darslarga baho qo'yilgan!"
                );
            }

            return 0;
        }

        // 6-QADAM: Telegram guruhga hisobot yuborish
        $chatId = $this->option('chat-id') ?: config('services.telegram.registrar_group_id');
        if (!$chatId) {
            $this->error('TELEGRAM_REGISTRAR_GROUP_ID sozlanmagan yoki --chat-id bering.');
            return 1;
        }

        $formattedDate = $yesterday->format('d.m.Y');
        $semesterStartFormatted = $semesterStart->format('d.m.Y');

        // Xulosa xabari
        $lines = [];
        $lines[] = "📊 BAHO QO'YILMAGANLAR HISOBOTI";
        $lines[] = "📅 Davr: {$semesterStartFormatted} — {$formattedDate}";
        $lines[] = "⏰ {$now->format('H:i')} | {$now->format('d.m.Y')}";
        $lines[] = str_repeat('─', 30);
        $lines[] = "🔴 Jami baho qo'yilmagan: {$totalUnrated} ta dars";
        $lines[] = "";

        // Menejer kesimi statistikasi
        $lines[] = "👤 BACK OFIS MENEJERLARI KESIMI:";
        $lines[] = "";

        $num = 0;
        foreach ($unratedByManager as $teacherId => $entries) {
            $num++;
            $manager = $backOfficeManagers->firstWhere('teacher_id', $teacherId);
            $managerName = $manager?->teacher?->short_name ?? $manager?->teacher?->full_name ?? "Menejer #{$teacherId}";
            $count = count($entries);
            $lines[] = "{$num}. <b>{$managerName}</b>: {$count} ta";
        }

        if (!empty($unassigned)) {
            $lines[] = "";
            $lines[] = "⚠️ Menejer biriktirilmagan: " . count($unassigned) . " ta";
        }

        $telegram->sendToUser($chatId, implode("\n", $lines));

        // 7-QADAM: Har bir menejer uchun jadval rasmi
        $generator = (new TableImageGenerator())->compact();
        $tempFiles = [];
        $sentCount = 0;
        $failedCount = 0;

        $headers = ['#', "O'QITUVCHI", 'FAN', 'GURUH', "MASHG'ULOT TURI", 'JUFTLIK', 'SANA', 'OCHILGAN', 'BAHO'];

        $allSections = [];
        foreach ($unratedByManager as $teacherId => $entries) {
            $manager = $backOfficeManagers->firstWhere('teacher_id', $teacherId);
            $managerName = $manager?->teacher?->short_name ?? $manager?->teacher?->full_name ?? "Menejer #{$teacherId}";
            $allSections[] = ['name' => $managerName, 'entries' => $entries];
        }
        if (!empty($unassigned)) {
            $allSections[] = ['name' => 'Menejer biriktirilmagan', 'entries' => $unassigned];
        }

        foreach ($allSections as $section) {
            $sectionName = $section['name'];
            $entries = $section['entries'];

            try {
                usort($entries, fn($a, $b) => strcmp($a['lesson_date'], $b['lesson_date']));

                $tableRows = [];
                foreach ($entries as $i => $entry) {
                    $lessonDate = Carbon::parse($entry['lesson_date'])->format('d.m');
                    $tableRows[] = [
                        $i + 1,
                        TableImageGenerator::truncate($entry['employee_name'] ?? '-', 24),
                        TableImageGenerator::truncate($entry['subject_name'] ?? '-', 24),
                        $entry['group_name'] ?? '-',
                        TableImageGenerator::truncate($entry['training_type_name'] ?? '-', 14),
                        $entry['lesson_pair_time'] ?? '',
                        $lessonDate,
                        $entry['has_opening'] ? 'Ha' : 'Yo\'q',
                        'badge:red:Yo\'q (' . ($entry['missing_grade_count'] ?? 0) . ')',
                    ];
                }

                $title = "{$sectionName} — " . count($entries) . " ta baho qo'yilmagan";

                Log::info("Registrator hisobot: {$sectionName} uchun rasm generatsiya qilinmoqda ({$title}, {$totalUnrated} qator)");

                $images = $generator->generate($headers, $tableRows, $title);

                Log::info("Registrator hisobot: {$sectionName} — " . count($images) . " ta rasm yaratildi");

                foreach ($images as $index => $imagePath) {
                    $tempFiles[] = $imagePath;
                    $fileSize = file_exists($imagePath) ? filesize($imagePath) : 0;
                    Log::info("Registrator hisobot: {$sectionName} rasm #{$index} — hajmi: " . round($fileSize / 1024) . " KB");

                    $caption = $sectionName;
                    if (count($images) > 1) {
                        $caption .= ' ' . ($index + 1) . '/' . count($images) . '-sahifa';
                    }

                    $sent = $telegram->sendPhoto($chatId, $imagePath, $caption);
                    if ($sent) {
                        $sentCount++;
                        Log::info("Registrator hisobot: {$sectionName} rasm #{$index} — yuborildi");
                    } else {
                        $failedCount++;
                        Log::error("Registrator hisobot: {$sectionName} rasm #{$index} — YUBORILMADI (sendPhoto false qaytardi)");
                    }
                }
            } catch (\Throwable $e) {
                $failedCount++;
                Log::error("Registrator hisobot: {$sectionName} uchun xato: " . $e->getMessage(), [
                    'exception' => $e::class,
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                $this->error("Xato ({$sectionName}): " . $e->getMessage());
            }
        }

        // Temp fayllarni tozalash
        foreach ($tempFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }

        $this->info("Hisobot yuborildi. Jami: {$totalUnrated}, rasmlar: {$sentCount} yuborildi, {$failedCount} xato");

        return 0;
    }

    /**
     * Joriy semestrning boshlanish sanasini aniqlash.
     * Kuz va bahor semestrlarini ajratib, hozirgi vaqtga mos semestrni tanlaydi.
     */
    /**
     * Joriy semestr boshlanishi — o'quv haftalaridan, sanaga qarab.
     *
     * semesters.current belgisiga tayanilmaydi: u faqat haftalik importda
     * yangilanadi va yangi o'quv yili boshida eskirib qoladi — shunda hisobot
     * o'tgan yilning bahorgi semestridan (yanvardan) boshlab hisoblardi.
     *
     * Bugun davom etayotgan bakalavr semestrlari olinadi (birinchi haftasi
     * bugundan oldin, oxirgisi bugundan keyin) va ularning eng erta
     * boshlanishi qaytariladi. Semestrlar orasidagi ta'tilda davom etayotgani
     * bo'lmasa — eng oxirgi boshlangan semestr olinadi.
     */
    private function getSemesterStartDate(): ?Carbon
    {
        $today = Carbon::today()->toDateString();

        $semesterRanges = DB::table('curriculum_weeks as cw')
            ->join('semesters as s', 's.semester_hemis_id', '=', 'cw.semester_hemis_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 's.curriculum_hemis_id')
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            ->groupBy('cw.semester_hemis_id')
            ->selectRaw('MIN(DATE(cw.start_date)) as sem_start, MAX(DATE(cw.end_date)) as sem_end')
            ->get()
            // Semestr odatda 5 oydan oshmaydi. Undan uzun oraliq — xato yoki
            // birlashib ketgan haftalar; u davrni yanvarga tortib ketmasin.
            ->filter(fn ($r) => Carbon::parse($r->sem_start)->diffInDays(Carbon::parse($r->sem_end)) <= 200);

        $inProgress = $semesterRanges->filter(fn ($r) => $r->sem_start <= $today && $r->sem_end >= $today);
        if ($inProgress->isNotEmpty()) {
            return Carbon::parse($inProgress->min('sem_start'));
        }

        $latestStarted = $semesterRanges->filter(fn ($r) => $r->sem_start <= $today)->max('sem_start');

        return $latestStarted ? Carbon::parse($latestStarted) : null;
    }

    /**
     * Guruhning department, specialty, level bo'yicha back ofis menejerini topish.
     * StaffRegistrationDivision::findForStudent() logikasi bilan bir xil ierarxiya.
     */
    private function findBackOfficeManager($managerAssignments, $departmentHemisId, $specialtyHemisId, $levelCode): ?int
    {
        if (!$departmentHemisId) {
            return null;
        }

        // 1. Eng aniq: department + specialty + level
        foreach ($managerAssignments as $teacherId => $assignments) {
            foreach ($assignments as $a) {
                if ($a->department_hemis_id == $departmentHemisId
                    && $a->specialty_hemis_id == $specialtyHemisId
                    && $a->level_code == $levelCode
                    && $specialtyHemisId && $levelCode) {
                    return $teacherId;
                }
            }
        }

        // 2. Department + specialty (barcha kurslar)
        if ($specialtyHemisId) {
            foreach ($managerAssignments as $teacherId => $assignments) {
                foreach ($assignments as $a) {
                    if ($a->department_hemis_id == $departmentHemisId
                        && $a->specialty_hemis_id == $specialtyHemisId
                        && !$a->level_code) {
                        return $teacherId;
                    }
                }
            }
        }

        // 3. Department + level (barcha yo'nalishlar)
        if ($levelCode) {
            foreach ($managerAssignments as $teacherId => $assignments) {
                foreach ($assignments as $a) {
                    if ($a->department_hemis_id == $departmentHemisId
                        && !$a->specialty_hemis_id
                        && $a->level_code == $levelCode) {
                        return $teacherId;
                    }
                }
            }
        }

        // 4. Faqat department (barcha yo'nalish va kurslar)
        foreach ($managerAssignments as $teacherId => $assignments) {
            foreach ($assignments as $a) {
                if ($a->department_hemis_id == $departmentHemisId
                    && !$a->specialty_hemis_id
                    && !$a->level_code) {
                    return $teacherId;
                }
            }
        }

        return null;
    }
}

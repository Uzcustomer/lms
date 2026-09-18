<?php

namespace App\Console\Commands;

use App\Models\StaffRegistrationDivision;
use App\Services\TableImageGenerator;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SendUnratedRegistrationsReport extends Command
{
    /** HEMIS talaba holati: 11 — "O'qimoqda". */
    private const ACTIVE_STATUS = 11;

    protected $signature = 'registrar:send-unrated-report {--chat-id= : Test uchun shaxsiy Telegram chat_id}';

    protected $description = 'Semestr boshidan kechagacha baho qo\'yilmagan darslar haqida back ofis menejerlari kesimida registrator guruhiga hisobot yuborish (har kuni 17:00)';

    public function handle(TelegramService $telegram): int
    {
        $now = Carbon::now();
        $yesterday = Carbon::yesterday();
        $yesterdayStr = $yesterday->format('Y-m-d');

        // Baho tekshirishdan chiqariladigan mashg'ulot turlari
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $gradeExcludedSubjectPatterns = config('app.excluded_rating_subject_patterns', []);

        // 1-QADAM: Joriy semestr boshlanish sanasini aniqlash
        $semesterStart = $this->getSemesterStartDate();
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

        // Fanga biriktirilgan faol talabalar soni (student_subjects jadvalidan).
        // Faqat o'qiyotgan talabalar (status 11) sanaladi: chetlashtirilgan,
        // akademik ta'tildagi, boshqa OTMga ko'chgan va h.k. talabalarga baho
        // qo'yilmasligi normal — ular hisobotga tushmasligi kerak.
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

        // O'qimayotgan talabalar (baho sanashdan chiqarish uchun)
        $excludedStudentHemisIds = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where(function ($q) {
                $q->whereNull('student_status_code')
                  ->orWhere('student_status_code', '!=', self::ACTIVE_STATUS);
            })
            ->pluck('hemis_id')
            ->toArray();

        // Semestr davomida guruhga o'tkazilgan talabalar va kelgan sanasi.
        // Kelishidan oldingi darslarda ularning bahosi bo'lmaydi va bo'lishi
        // shart ham emas — aks holda o'sha darslar "baho qo'yilmagan" chiqardi.
        $lateJoiners = $this->lateJoinersByGroup($groupHemisIds);
        $lateJoinerSubjects = $this->subjectKeysFor(
            collect($lateJoiners)->flatten(1)->pluck('hemis_id')->unique()->values()->all(),
            $subjectIds,
            $semesterCodes
        );

        // Baho (1-usul): subject_schedule_id orqali — baho qo'yilgan faol talabalar soni
        $gradeCountByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->when(!empty($excludedStudentHemisIds), fn($q) => $q->whereNotIn('student_hemis_id', $excludedStudentHemisIds))
            ->where(function ($q) {
                $q->where('grade', '>', 0)
                  ->orWhere('retake_grade', '>', 0)
                  ->orWhere('status', 'recorded')
                  ->orWhere('reason', 'absent');
            })
            ->groupBy('subject_schedule_id')
            ->select('subject_schedule_id', DB::raw('COUNT(DISTINCT student_hemis_id) as graded_count'))
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
            $countedFromSubjects = isset($subjectStudentCounts[$gsKey]);
            $subjectTotal = (int) ($subjectStudentCounts[$gsKey] ?? ($groupStudentCounts[$sch->group_id] ?? 0));

            // Dars kunidan keyin guruhga kelgan talabalar bu darsda sanalmaydi.
            // student_subjects bo'yicha sanalganda — faqat shu fanga biriktirilganlari
            // umumiy songa kirgan, qolganlarini ayirish kerak emas.
            foreach ($lateJoiners[$sch->group_id] ?? [] as $joiner) {
                if ($joiner['joined'] <= $sch->lesson_date_str) {
                    continue;
                }
                if ($countedFromSubjects
                    && !isset($lateJoinerSubjects[$joiner['hemis_id'] . '|' . $sch->subject_id . '|' . $sch->semester_code])) {
                    continue;
                }
                $subjectTotal--;
            }

            if ($subjectTotal <= 0) {
                continue; // Bu darsda baho kutiladigan faol talaba yo'q
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
     * Guruhga boshqa guruhdan o'tkazilgan faol talabalar va kelgan sanasi.
     *
     * student_group_history dan olinadi: oxirgi yozuvlardan orqaga qarab joriy
     * guruhdagi uzluksiz qism topiladi (to'lov shakli yoki semestr o'zgarganda
     * ham yangi yozuv ochiladi), uning eng birinchisi — guruhga kelgan sana.
     * Undan oldin boshqa guruhda yozuvi bo'lmagan talaba boshidan shu guruhda
     * hisoblanadi va ro'yxatga tushmaydi.
     *
     * @return array<string, array<int, array{hemis_id: string, joined: string}>> group_id => talabalar
     */
    private function lateJoinersByGroup(array $groupHemisIds): array
    {
        if (empty($groupHemisIds) || !Schema::hasTable('student_group_history')) {
            return [];
        }

        $students = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', self::ACTIVE_STATUS)
            ->get(['id', 'hemis_id', 'group_id'])
            ->keyBy('id');

        $result = [];

        foreach ($students->keys()->chunk(1000) as $ids) {
            $history = DB::table('student_group_history')
                ->whereIn('student_id', $ids->all())
                ->orderBy('student_id')
                ->orderBy('started_at')
                ->get(['student_id', 'group_hemis_id', 'started_at'])
                ->groupBy('student_id');

            foreach ($history as $studentId => $rows) {
                $student = $students->get($studentId);
                $currentGroup = (string) $student->group_id;
                $joined = null;
                $cameFromOtherGroup = false;

                foreach ($rows->reverse() as $row) {
                    if ((string) $row->group_hemis_id === $currentGroup) {
                        $joined = $row->started_at;
                        continue;
                    }
                    $cameFromOtherGroup = true;
                    break;
                }

                if ($cameFromOtherGroup && $joined) {
                    $result[$currentGroup][] = [
                        'hemis_id' => (string) $student->hemis_id,
                        'joined' => Carbon::parse($joined)->toDateString(),
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * Berilgan talabalarning fan biriktirmalari: "hemis_id|subject_id|semester" kalitlari.
     */
    private function subjectKeysFor(array $studentHemisIds, array $subjectIds, array $semesterCodes): array
    {
        if (empty($studentHemisIds)) {
            return [];
        }

        return DB::table('student_subjects')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_id', $semesterCodes)
            ->select(DB::raw("CONCAT(student_hemis_id, '|', subject_id, '|', semester_id) as k"))
            ->pluck('k')
            ->flip()
            ->all();
    }

    private function getSemesterStartDate(): ?Carbon
    {
        // 1. Joriy o'quv yilini aniqlash
        $educationYear = DB::table('semesters')
            ->where('current', true)
            ->orderByDesc('education_year')
            ->value('education_year');

        if (!$educationYear) {
            return null;
        }

        // 2. Shu o'quv yildagi joriy semestrlarni olish
        $currentSemesterIds = DB::table('semesters')
            ->where('current', true)
            ->where('education_year', $educationYear)
            ->pluck('semester_hemis_id');

        if ($currentSemesterIds->isEmpty()) {
            return null;
        }

        // 3. Bahor semestrlarini ajratish (yanvar oyidan keyin boshlanganlar)
        $springCutoff = ($educationYear + 1) . '-01-01';

        $springSemesterIds = DB::table('curriculum_weeks')
            ->whereIn('semester_hemis_id', $currentSemesterIds)
            ->groupBy('semester_hemis_id')
            ->havingRaw('MIN(start_date) >= ?', [$springCutoff])
            ->pluck('semester_hemis_id');

        // Bahor semestri bor bo'lsa — uni tanlash, aks holda kuz semestri
        $targetSemesterIds = $springSemesterIds->isNotEmpty()
            ? $springSemesterIds
            : $currentSemesterIds;

        $startDate = DB::table('curriculum_weeks')
            ->whereIn('semester_hemis_id', $targetSemesterIds)
            ->min('start_date');

        return $startDate ? Carbon::parse($startDate) : null;
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

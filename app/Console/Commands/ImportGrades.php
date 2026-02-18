<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Deadline;
use App\Models\ImportStatus;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use App\Services\TelegramService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MarkingSystemScore;

class ImportGrades extends Command
{
    protected string $baseUrl;
    protected string $token;
    protected array $report = [];

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url');
        $this->token = config('services.hemis.token');
    }

    protected $signature = 'student:import-data {--mode=live : Import mode: live, final, or backfill} {--from= : Backfill start date (Y-m-d)}';

    protected $description = 'Import student grades and attendance from Hemis API';

    public function handle()
    {
        $mode = $this->option('mode');

        if ($mode === 'final') {
            return $this->handleFinalImport();
        }

        if ($mode === 'backfill') {
            return $this->handleBackfillImport();
        }

        return $this->handleLiveImport();
    }

    // =========================================================================
    // LIVE IMPORT — har 30 daqiqada, bugungi baholarni yangilaydi
    // =========================================================================
    private function handleLiveImport()
    {
        $this->info('Starting LIVE import...');
        Log::info('[LiveImport] Starting live import at ' . Carbon::now());

        $today = Carbon::today();

        // Agar bugungi baholar allaqachon yakunlangan (is_final=true) bo'lsa, import qilmaslik
        $alreadyFinalized = StudentGrade::where('lesson_date', '>=', $today->copy()->startOfDay())
            ->where('lesson_date', '<=', $today->copy()->endOfDay())
            ->where('is_final', true)
            ->exists();

        if ($alreadyFinalized) {
            $this->info("Live import: bugungi baholar allaqachon yakunlangan (is_final=true), o'tkazib yuborildi.");
            Log::info("[LiveImport] Today's grades already finalized, skipping.");
            return;
        }

        $from = $today->copy()->startOfDay()->timestamp;
        $to = Carbon::now()->timestamp;

        // 1-qadam: Baholarni API dan tortib olish (xotiraga)
        $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to);

        if ($gradeItems === false) {
            $this->error('Grade API failed — eski baholar saqlanib qoldi.');
            Log::error('[LiveImport] Grade API failed, skipping soft delete.');
            $this->report['student-grade-list'] = [
                'total_days' => 1,
                'success_days' => 0,
                'failed_pages' => ['API xato — baholar yangilanmadi'],
            ];
        } else {
            // 2-qadam: Muvaffaqiyatli — soft delete + yangi yozish
            $this->applyGrades($gradeItems, $today, false);
            $this->report['student-grade-list'] = [
                'total_days' => 1,
                'success_days' => 1,
                'failed_pages' => [],
            ];
        }

        // Davomatni alohida import qilish (eski logika — attendance uchun soft delete kerak emas)
        $this->importAttendance($from, $to, $today);

        $this->sendTelegramReport();
        Log::info('[LiveImport] Completed at ' . Carbon::now());
    }

    // =========================================================================
    // FINAL IMPORT — har kuni 00:30 da, kechagi kunni yakunlaydi
    // =========================================================================
    private function handleFinalImport()
    {
        $yesterday = Carbon::yesterday();

        // Kechagi kunda is_final baholar bormi?
        $alreadyFinalized = StudentGrade::where('lesson_date', '>=', $yesterday->copy()->startOfDay())
            ->where('lesson_date', '<=', $yesterday->copy()->endOfDay())
            ->where('is_final', true)
            ->exists();

        if ($alreadyFinalized) {
            $this->info("Final import: {$yesterday->toDateString()} allaqachon yakunlangan, o'tkazib yuborildi.");
            Log::info("[FinalImport] {$yesterday->toDateString()} already finalized, skipping.");
            return;
        }

        $this->info("Starting FINAL import for {$yesterday->toDateString()}...");
        Log::info("[FinalImport] Starting for {$yesterday->toDateString()} at " . Carbon::now());

        $from = $yesterday->copy()->startOfDay()->timestamp;
        $to = $yesterday->copy()->endOfDay()->timestamp;

        // 1-qadam: Kechagi kunni to'liq API dan tortish
        $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to);

        if ($gradeItems === false) {
            $this->error("Final import FAILED for {$yesterday->toDateString()} — API xato.");
            Log::error("[FinalImport] API failed for {$yesterday->toDateString()}");
            $this->report['final-import'] = [
                'total_days' => 1,
                'success_days' => 0,
                'failed_pages' => ["API xato — {$yesterday->toDateString()} yakunlanmadi"],
            ];
            $this->sendTelegramReport();
            return;
        }

        // 2-qadam: Soft delete + is_final=true qilib yozish
        $this->applyGrades($gradeItems, $yesterday, true);

        // Attendance ham final import qilish
        $attendanceItems = $this->fetchAllPages('attendance-list', $from, $to);
        if ($attendanceItems !== false) {
            foreach ($attendanceItems as $item) {
                $this->processAttendance($item, true);
            }
        }

        $this->report['final-import'] = [
            'total_days' => 1,
            'success_days' => 1,
            'failed_pages' => [],
        ];

        $this->sendTelegramReport();
        Log::info("[FinalImport] Completed for {$yesterday->toDateString()} at " . Carbon::now());
    }

    // =========================================================================
    // BACKFILL IMPORT — bir martalik, berilgan sanadan kechagigacha is_final=true
    // php artisan student:import-data --mode=backfill --from=2026-01-26
    // =========================================================================
    private function handleBackfillImport()
    {
        $fromDate = $this->option('from');
        if (!$fromDate) {
            $this->error('--from parametri kerak. Masalan: --from=2026-01-26');
            return;
        }

        $startDate = Carbon::parse($fromDate)->startOfDay();
        $endDate = Carbon::yesterday()->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            $this->error("Boshlanish sanasi ({$startDate->toDateString()}) kechagi kundan ({$endDate->toDateString()}) katta bo'lishi mumkin emas.");
            return;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        $this->info("BACKFILL: {$startDate->toDateString()} → {$endDate->toDateString()} ({$totalDays} kun)");
        Log::info("[Backfill] Starting from {$startDate->toDateString()} to {$endDate->toDateString()}");

        $successDays = 0;
        $failedDays = [];

        foreach ($period as $date) {
            $dayFrom = $date->copy()->startOfDay()->timestamp;
            $dayTo = $date->copy()->endOfDay()->timestamp;

            $this->info("--- {$date->toDateString()} ---");

            // Baholar
            $gradeItems = $this->fetchAllPages('student-grade-list', $dayFrom, $dayTo);

            if ($gradeItems === false) {
                $this->error("XATO: {$date->toDateString()} — baholar import qilinmadi, keyingi kunga o'tiladi.");
                $failedDays[] = $date->toDateString();
                continue;
            }

            $this->applyGrades($gradeItems, $date, true);

            // Attendance
            $attendanceItems = $this->fetchAllPages('attendance-list', $dayFrom, $dayTo);
            if ($attendanceItems !== false) {
                foreach ($attendanceItems as $item) {
                    $this->processAttendance($item, true);
                }
            }

            $successDays++;
            $this->info("Tayyor: {$date->toDateString()} — {$successDays}/{$totalDays}");
        }

        $this->report['backfill'] = [
            'total_days' => $totalDays,
            'success_days' => $successDays,
            'failed_pages' => $failedDays,
        ];

        $this->sendTelegramReport();

        $this->info("BACKFILL tugadi: {$successDays}/{$totalDays} kun muvaffaqiyatli.");
        if (!empty($failedDays)) {
            $this->warn("Xato bo'lgan kunlar: " . implode(', ', $failedDays));
        }
        Log::info("[Backfill] Completed: {$successDays}/{$totalDays} days.");
    }

    // =========================================================================
    // API dan barcha sahifalarni xotiraga yig'ish
    // Muvaffaqiyatli bo'lsa array, xato bo'lsa false qaytaradi
    // =========================================================================
    private function fetchAllPages(string $endpoint, int $from, int $to): array|false
    {
        $allItems = [];
        $currentPage = 1;
        $totalPages = 1;
        $maxRetries = 3;

        do {
            $queryParams = [
                'limit' => 200,
                'page' => $currentPage,
                'lesson_date_from' => $from,
                'lesson_date_to' => $to,
            ];

            $retryCount = 0;
            $pageSuccess = false;

            while ($retryCount < $maxRetries && !$pageSuccess) {
                try {
                    $response = Http::timeout(60)->withoutVerifying()->withToken($this->token)
                        ->get("{$this->baseUrl}/v1/data/{$endpoint}", $queryParams);

                    if ($response->successful()) {
                        $data = $response->json()['data']['items'] ?? [];
                        $allItems = array_merge($allItems, $data);
                        $totalPages = $response->json()['data']['pagination']['pageCount'] ?? $totalPages;
                        $this->info("Fetched {$endpoint} page {$currentPage}/{$totalPages}");
                        $pageSuccess = true;
                        sleep(2);
                    } else {
                        $retryCount++;
                        Log::error("[Fetch] {$endpoint} page {$currentPage} failed. Status: {$response->status()}");
                        if ($retryCount < $maxRetries) {
                            sleep(5);
                        }
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    Log::error("[Fetch] {$endpoint} page {$currentPage} exception: " . $e->getMessage());
                    if ($retryCount < $maxRetries) {
                        sleep(5);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->error("Failed {$endpoint} page {$currentPage} after {$maxRetries} retries — ABORTING.");
                Log::error("[Fetch] Aborting {$endpoint} — page {$currentPage} failed after all retries.");
                return false;
            }

            $currentPage++;
        } while ($currentPage <= $totalPages);

        $this->info("Total {$endpoint} items fetched: " . count($allItems));
        return $allItems;
    }

    // =========================================================================
    // Baholarni bazaga yozish: soft delete + insert
    // =========================================================================
    private function applyGrades(array $gradeItems, Carbon $date, bool $isFinal): void
    {
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();

        $gradeCount = 0;
        $skippedCount = 0;
        $softDeletedCount = 0;

        // Faqat kutilgan sanaga mos yozuvlarni filtrlash
        $filteredItems = array_filter($gradeItems, function ($item) use ($date, &$skippedCount) {
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
            if (!$lessonDate->isSameDay($date)) {
                $skippedCount++;
                return false;
            }
            return true;
        });

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} records with mismatched lesson_date (expected {$date->toDateString()})");
            Log::warning("[ApplyGrades] Skipped {$skippedCount} records with lesson_date != {$date->toDateString()}");
        }

        DB::transaction(function () use ($filteredItems, $dateStart, $dateEnd, $isFinal, &$gradeCount, &$softDeletedCount) {
            $query = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd);

            if ($isFinal) {
                // Final/Backfill: BARCHA eski yozuvlarni o'chirish (is_final=true va false)
                // Bu backfill'ni qayta ishlatishda duplicate yaratmaslikni ta'minlaydi
                $softDeletedCount = $query->delete();
            } else {
                // Live import: faqat is_final=false yozuvlarni o'chirish
                // is_final=true (yakunlangan) baholar saqlanib qoladi
                $softDeletedCount = $query->where('is_final', false)->delete();
            }

            $this->info("Soft deleted {$softDeletedCount} old grades for {$dateStart->toDateString()}");
            Log::info("[ApplyGrades] Soft deleted {$softDeletedCount} grades for {$dateStart->toDateString()}");

            // Yangi baholarni yozish
            foreach ($filteredItems as $item) {
                $this->processGrade($item, $isFinal);
                $gradeCount++;
            }
        });

        $this->info("Written {$gradeCount} grades (is_final=" . ($isFinal ? 'true' : 'false') . ")");
        Log::info("[ApplyGrades] Written {$gradeCount} grades for {$dateStart->toDateString()}, is_final=" . ($isFinal ? 'true' : 'false'));
    }

    // =========================================================================
    // Attendance import (eski logika, faqat live import uchun)
    // =========================================================================
    private function importAttendance(int $from, int $to, Carbon $date): void
    {
        $attendanceItems = $this->fetchAllPages('attendance-list', $from, $to);

        if ($attendanceItems === false) {
            $this->error('Attendance API failed.');
            $this->report['attendance-list'] = [
                'total_days' => 1,
                'success_days' => 0,
                'failed_pages' => ['API xato'],
            ];
            return;
        }

        foreach ($attendanceItems as $item) {
            $this->processAttendance($item);
        }

        $this->report['attendance-list'] = [
            'total_days' => 1,
            'success_days' => 1,
            'failed_pages' => [],
        ];
    }

    // =========================================================================
    // Bitta baho yozish
    // =========================================================================
    private function processGrade(array $item, bool $isFinal = false): void
    {
        $student = Student::where('hemis_id', $item['_student'])->first();

        if (!$student) {
            return;
        }

        $gradeValue = $item['grade'];
        $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);

        $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);
        $studentMinLimit = $markingScore ? $markingScore->minimum_limit : 0;

        $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
            ($student->level_code != 16 && $gradeValue < $studentMinLimit);

        $status = $isLowGrade ? 'pending' : 'recorded';
        $reason = $isLowGrade ? 'low_grade' : null;
        $deadline = $isLowGrade ? $this->getDeadline($student->level_code, $lessonDate) : null;

        StudentGrade::create([
            'hemis_id' => $item['id'],
            'student_id' => $student->id,
            'student_hemis_id' => $item['_student'],
            'semester_code' => $item['semester']['code'],
            'semester_name' => $item['semester']['name'],
            'education_year_code' => $item['educationYear']['code'] ?? null,
            'education_year_name' => $item['educationYear']['name'] ?? null,
            'subject_schedule_id' => $item['_subject_schedule'],
            'subject_id' => $item['subject']['id'],
            'subject_name' => $item['subject']['name'],
            'subject_code' => $item['subject']['code'],
            'training_type_code' => $item['trainingType']['code'],
            'training_type_name' => $item['trainingType']['name'],
            'employee_id' => $item['employee']['id'],
            'employee_name' => $item['employee']['name'],
            'lesson_pair_code' => $item['lessonPair']['code'],
            'lesson_pair_name' => $item['lessonPair']['name'],
            'lesson_pair_start_time' => $item['lessonPair']['start_time'],
            'lesson_pair_end_time' => $item['lessonPair']['end_time'],
            'grade' => $gradeValue,
            'lesson_date' => $lessonDate,
            'created_at_api' => Carbon::createFromTimestamp($item['created_at']),
            'reason' => $reason,
            'deadline' => $deadline,
            'status' => $status,
            'is_final' => $isFinal,
        ]);
    }

    // =========================================================================
    // Davomatni qayta ishlash (eski logika saqlanadi)
    // =========================================================================
    private function processAttendance($item, bool $isFinal = false)
    {
        $student = Student::where('hemis_id', $item['student']['id'])->first();

        if ($student && ($item['absent_off'] > 0 || $item['absent_on'] > 0)) {
            Attendance::updateOrCreate(
                [
                    'hemis_id' => $item['id'],
                ],
                [
                    'subject_schedule_id' => $item['_subject_schedule'],
                    'student_id' => $student->id,
                    'student_hemis_id' => $item['student']['id'],
                    'student_name' => $item['student']['name'],
                    'employee_id' => $item['employee']['id'],
                    'employee_name' => $item['employee']['name'],
                    'subject_id' => $item['subject']['id'],
                    'subject_name' => $item['subject']['name'],
                    'subject_code' => $item['subject']['code'],
                    'education_year_code' => $item['educationYear']['code'],
                    'education_year_name' => $item['educationYear']['name'],
                    'education_year_current' => $item['educationYear']['current'],
                    'semester_code' => $item['semester']['code'],
                    'semester_name' => $item['semester']['name'],
                    'group_id' => $item['group']['id'],
                    'group_name' => $item['group']['name'],
                    'education_lang_code' => $item['group']['educationLang']['code'],
                    'education_lang_name' => $item['group']['educationLang']['name'],
                    'training_type_code' => $item['trainingType']['code'],
                    'training_type_name' => $item['trainingType']['name'],
                    'lesson_pair_code' => $item['lessonPair']['code'],
                    'lesson_pair_name' => $item['lessonPair']['name'],
                    'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                    'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                    'absent_on' => $item['absent_on'],
                    'absent_off' => $item['absent_off'],
                    'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
                    'status' => 'absent',
                ]
            );

            $this->processGradeForAbsence($item, $student, $isFinal);
        }
    }

    private function processGradeForAbsence($item, $student, bool $isFinal = false)
    {
        $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);

        // withTrashed() — soft-deleted yozuvlarni ham tekshirish (duplicate yaratmaslik uchun)
        $existingGrade = StudentGrade::withTrashed()->where([
            'student_id' => $student->id,
            'subject_name' => $item['subject']['name'],
            'lesson_date' => $lessonDate,
            'lesson_pair_code' => $item['lessonPair']['code'],
            'lesson_pair_start_time' => $item['lessonPair']['start_time'],
        ])->first();

        if (!$existingGrade) {
            StudentGrade::create([
                'hemis_id' => 111,
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'semester_code' => $item['semester']['code'],
                'semester_name' => $item['semester']['name'],
                'education_year_code' => $item['educationYear']['code'] ?? null,
                'education_year_name' => $item['educationYear']['name'] ?? null,
                'subject_schedule_id' => $item['_subject_schedule'],
                'subject_id' => $item['subject']['id'],
                'subject_name' => $item['subject']['name'],
                'subject_code' => $item['subject']['code'],
                'training_type_code' => $item['trainingType']['code'],
                'training_type_name' => $item['trainingType']['name'],
                'employee_id' => $item['employee']['id'],
                'employee_name' => $item['employee']['name'],
                'lesson_pair_code' => $item['lessonPair']['code'],
                'lesson_pair_name' => $item['lessonPair']['name'],
                'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                'grade' => null,
                'lesson_date' => $lessonDate,
                'created_at_api' => Carbon::now(),
                'reason' => 'absent',
                'deadline' => $this->getDeadline($student->level_code, $lessonDate),
                'status' => 'pending',
                'is_final' => $isFinal,
            ]);
        }
    }

    // =========================================================================
    // Yordamchi metodlar
    // =========================================================================
    private function getDeadline($levelCode, $lessonDate)
    {
        $deadline = Deadline::where('level_code', $levelCode)->first();
        if ($deadline) {
            return $lessonDate->copy()->addDays($deadline->deadline_days)->endOfDay();
        }
        return $lessonDate->copy()->addWeek()->endOfDay();
    }

    private function sendTelegramReport()
    {
        $mode = $this->option('mode');
        $lines = ["{$mode} import natijasi (" . Carbon::now()->format('d.m.Y H:i') . "):"];

        $hasErrors = false;
        foreach ($this->report as $endpoint => $stats) {
            if ($stats['total_days'] === 0) {
                $lines[] = "{$endpoint}: yangi ma'lumot yo'q";
                continue;
            }

            if (empty($stats['failed_pages'])) {
                $lines[] = "{$endpoint}: {$stats['success_days']}/{$stats['total_days']} kun muvaffaqiyatli";
            } else {
                $hasErrors = true;
                $lines[] = "{$endpoint}: {$stats['success_days']}/{$stats['total_days']} kun muvaffaqiyatli";
                foreach ($stats['failed_pages'] as $failed) {
                    $lines[] = "  xato: {$failed}";
                }
            }
        }

        $emoji = $hasErrors ? 'Import xatolar bor' : 'Import muvaffaqiyatli';
        $message = $emoji . "\n" . implode("\n", $lines);

        $this->info($message);
        app(TelegramService::class)->notify($message);
    }
}

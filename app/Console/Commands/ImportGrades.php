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
    protected ?string $baseUrl;
    protected ?string $token;
    protected array $report = [];

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url') ?? '';
        $this->token = config('services.hemis.token') ?? '';
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

        // Agar bugungi BARCHA baholar yakunlangan bo'lsa, import qilmaslik
        // Faqat bitta is_final=true bor deb butun kunni o'tkazmaslik —
        // BARCHA yozuvlar is_final=true bo'lgandagina o'tkazish
        $hasUnfinalized = StudentGrade::where('lesson_date', '>=', $today->copy()->startOfDay())
            ->where('lesson_date', '<=', $today->copy()->endOfDay())
            ->where('is_final', false)
            ->exists();

        $hasAnyGrades = StudentGrade::where('lesson_date', '>=', $today->copy()->startOfDay())
            ->where('lesson_date', '<=', $today->copy()->endOfDay())
            ->exists();

        if ($hasAnyGrades && !$hasUnfinalized) {
            $this->info("Live import: bugungi BARCHA baholar yakunlangan (is_final=true), o'tkazib yuborildi.");
            Log::info("[LiveImport] Today's ALL grades finalized, skipping.");
            return;
        }

        $from = $today->copy()->startOfDay()->timestamp;
        $to = Carbon::now()->timestamp;

        // 1-qadam: Baholarni API dan tortib olish (xotiraga)
        $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to);

        if ($gradeItems === false) {
            $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
            $this->error("Grade API failed — eski baholar saqlanib qoldi. ({$errorDetail})");
            Log::error("[LiveImport] Grade API failed: {$errorDetail}");
            $this->report['student-grade-list'] = [
                'total_days' => 1,
                'success_days' => 0,
                'failed_pages' => ["API xato ({$errorDetail})"],
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
    // FINAL IMPORT — har kuni 00:30 da, yakunlanmagan kunlarni is_final=true qiladi
    // Faqat kechagini emas, oxirgi 7 kun ichidagi BARCHA is_final=false kunlarni tekshiradi
    // Bu API fail bo'lgan kunlarni avtomatik qayta urinadi
    // =========================================================================
    private function handleFinalImport()
    {
        $this->info('Starting FINAL import...');
        Log::info('[FinalImport] Starting at ' . Carbon::now());

        // Oxirgi 7 kun ichida yakunlanmagan (is_final=false) kunlarni topish
        $lookbackStart = Carbon::today()->subDays(7)->startOfDay();
        $todayStart = Carbon::today()->startOfDay();

        $unfinishedDates = StudentGrade::where('is_final', false)
            ->where('lesson_date', '>=', $lookbackStart)
            ->where('lesson_date', '<', $todayStart)
            ->selectRaw('DATE(lesson_date) as grade_date')
            ->distinct()
            ->orderBy('grade_date')
            ->pluck('grade_date');

        if ($unfinishedDates->isEmpty()) {
            $this->info('Final import: barcha kunlar allaqachon yakunlangan.');
            Log::info('[FinalImport] All recent days already finalized, nothing to do.');
            $this->report['final-import'] = [
                'total_days' => 0,
                'success_days' => 0,
                'failed_pages' => [],
            ];
            $this->sendTelegramReport();
            return;
        }

        $totalDays = $unfinishedDates->count();
        $successDays = 0;
        $failedDays = [];

        $this->info("Yakunlanmagan kunlar: {$totalDays} ta ({$unfinishedDates->first()} — {$unfinishedDates->last()})");
        Log::info("[FinalImport] Found {$totalDays} unfinished days");

        foreach ($unfinishedDates as $dateStr) {
            $date = Carbon::parse($dateStr);

            // Faqat BARCHA yozuvlar is_final=true bo'lgandagina o'tkazish
            // Bir nechta is_final=true (retake) bor, lekin boshqalar is_final=false bo'lsa — import qilish kerak
            $hasUnfinalizedForDate = StudentGrade::where('lesson_date', '>=', $date->copy()->startOfDay())
                ->where('lesson_date', '<=', $date->copy()->endOfDay())
                ->where('is_final', false)
                ->exists();

            if (!$hasUnfinalizedForDate) {
                $this->info("  {$date->toDateString()} — BARCHA yozuvlar yakunlangan, o'tkazib yuborildi.");
                $totalDays--;
                continue;
            }

            $from = $date->copy()->startOfDay()->timestamp;
            $to = $date->copy()->endOfDay()->timestamp;

            $this->info("  {$date->toDateString()} — API dan tortilmoqda...");

            // Baholar
            $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to);

            if ($gradeItems === false) {
                $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
                $this->error("  {$date->toDateString()} — API xato ({$errorDetail}), keyingi kunga o'tiladi.");
                $failedDays[] = "{$date->toDateString()} ({$errorDetail})";
                continue;
            }

            $this->applyGrades($gradeItems, $date, true);

            // Attendance
            $attendanceItems = $this->fetchAllPages('attendance-list', $from, $to);
            if ($attendanceItems !== false) {
                foreach ($attendanceItems as $item) {
                    $this->processAttendance($item, true);
                }
            }

            $successDays++;
            $this->info("  {$date->toDateString()} — yakunlandi ({$successDays}/{$totalDays})");
        }

        $this->report['final-import'] = [
            'total_days' => $totalDays,
            'success_days' => $successDays,
            'failed_pages' => $failedDays,
        ];

        $this->sendTelegramReport();
        Log::info("[FinalImport] Completed at " . Carbon::now() . ": {$successDays}/{$totalDays} days finalized.");
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
                $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
                $this->error("XATO: {$date->toDateString()} — baholar import qilinmadi ({$errorDetail}), keyingi kunga o'tiladi.");
                $failedDays[] = "{$date->toDateString()} ({$errorDetail})";
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
    private string $lastFetchError = '';

    private function fetchAllPages(string $endpoint, int $from, int $to): array|false
    {
        $allItems = [];
        $currentPage = 1;
        $totalPages = 1;
        $maxRetries = 3;
        $this->lastFetchError = '';

        do {
            $queryParams = [
                'limit' => 200,
                'page' => $currentPage,
                'lesson_date_from' => $from,
                'lesson_date_to' => $to,
            ];

            $retryCount = 0;
            $pageSuccess = false;
            $lastError = '';

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
                        $lastError = "HTTP {$response->status()}";
                        $body = substr($response->body(), 0, 200);
                        Log::error("[Fetch] {$endpoint} page {$currentPage} failed. Status: {$response->status()}, Body: {$body}");
                        if ($retryCount < $maxRetries) {
                            sleep(5);
                        }
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    $lastError = $e->getMessage();
                    Log::error("[Fetch] {$endpoint} page {$currentPage} exception: " . $e->getMessage());
                    if ($retryCount < $maxRetries) {
                        sleep(5);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->lastFetchError = "sahifa {$currentPage}: {$lastError}";
                $this->error("Failed {$endpoint} page {$currentPage} after {$maxRetries} retries — ABORTING. Error: {$lastError}");
                Log::error("[Fetch] Aborting {$endpoint} — page {$currentPage} failed after all retries. Last error: {$lastError}");
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
            // 1-QADAM: Retake ma'lumotlarni xotiraga saqlash (o'chirishdan oldin)
            $retakeBackup = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd)
                ->whereNotNull('retake_grade')
                ->get(['id', 'student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code',
                        'retake_grade', 'retake_graded_at', 'retake_by', 'retake_file_path', 'graded_by_user_id'])
                ->keyBy(function ($item) {
                    return $item->student_hemis_id . '_' . $item->subject_id . '_' .
                           $item->lesson_date . '_' . $item->lesson_pair_code;
                });

            if ($retakeBackup->isNotEmpty()) {
                $this->info("Backed up {$retakeBackup->count()} retake grades for {$dateStart->toDateString()}");
            }

            // 1b-QADAM: O'chirishdan OLDIN barcha aktiv yozuv ID larni saqlash
            // Keyin 6-qadamda HEMIS da yo'q lokal baholarni tiklash uchun kerak
            $activeIdsBeforeDelete = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd)
                ->pluck('id')
                ->toArray();

            // 2-QADAM: BARCHA eski yozuvlarni soft-delete (retake ham — HEMIS yangilanishi uchun)
            $query = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd);

            if ($isFinal) {
                $softDeletedCount = $query->delete();
            } else {
                $softDeletedCount = $query->where('is_final', false)->delete();
            }

            $this->info("Soft deleted {$softDeletedCount} old grades for {$dateStart->toDateString()}");
            Log::info("[ApplyGrades] Soft deleted {$softDeletedCount} grades for {$dateStart->toDateString()}");

            // 3-QADAM: HEMIS dan yangi yozuvlarni yaratish (to'liq yangi ma'lumot)
            foreach ($filteredItems as $item) {
                $this->processGrade($item, $isFinal);
                $gradeCount++;
            }

            // 4-QADAM: Saqlangan retake ma'lumotlarni yangi yozuvlarga qayta qo'yish
            $retakeRestored = 0;
            $restoredKeys = [];
            foreach ($retakeBackup as $key => $retake) {
                $updated = StudentGrade::where('student_hemis_id', $retake->student_hemis_id)
                    ->where('subject_id', $retake->subject_id)
                    ->whereDate('lesson_date', $retake->lesson_date)
                    ->where('lesson_pair_code', $retake->lesson_pair_code)
                    ->whereNull('deleted_at')
                    ->whereNull('retake_grade')
                    ->update([
                        'retake_grade' => $retake->retake_grade,
                        'status' => 'retake',
                        'retake_graded_at' => $retake->retake_graded_at,
                        'retake_by' => $retake->retake_by,
                        'retake_file_path' => $retake->retake_file_path,
                        'graded_by_user_id' => $retake->graded_by_user_id,
                        'is_final' => $isFinal,
                    ]);
                if ($updated) {
                    $retakeRestored++;
                    $restoredKeys[] = $key;
                }
            }

            // 5-QADAM: HEMIS da yo'q yozuvlarni (teacher_victim, lokal NB) to'g'ridan-to'g'ri tiklash
            // Faqat 2-qadamda SOFT-DELETE bo'lgan yozuvlarni tiklash
            // is_final=true bo'lib 2-qadamda o'chirilmagan yozuvlarga TEGMASLIK
            $undeleted = 0;
            foreach ($retakeBackup as $key => $retake) {
                if (in_array($key, $restoredKeys)) {
                    continue; // 4-qadamda tiklangan, o'tkazib yuborish
                }
                $affected = StudentGrade::onlyTrashed()
                    ->where('id', $retake->id)
                    ->update([
                        'deleted_at' => null,
                        'is_final' => $isFinal,
                    ]);
                if ($affected) {
                    $undeleted++;
                }
            }

            if ($retakeRestored > 0 || $undeleted > 0) {
                $this->info("Retake grades: {$retakeRestored} restored to new records, {$undeleted} un-deleted for {$dateStart->toDateString()}");
                Log::info("[ApplyGrades] Retake: {$retakeRestored} restored, {$undeleted} un-deleted for {$dateStart->toDateString()}");
            }

            // 6-QADAM: O'qituvchi tomonidan qo'yilgan lokal baholarni tiklash
            // HEMIS da yo'q (yangi yozuv yaratilmagan) lekin 2-qadamda o'chirilgan baholarni qaytarish
            $activeKeysAfterImport = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd)
                ->get(['student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code'])
                ->map(fn ($g) => $g->student_hemis_id . '_' . $g->subject_id . '_' .
                    Carbon::parse($g->lesson_date)->toDateString() . '_' . $g->lesson_pair_code)
                ->flip()
                ->toArray();

            $orphanCandidates = StudentGrade::onlyTrashed()
                ->whereIn('id', $activeIdsBeforeDelete)
                ->get(['id', 'student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code']);

            $orphanIds = [];
            foreach ($orphanCandidates as $orphan) {
                $key = $orphan->student_hemis_id . '_' . $orphan->subject_id . '_' .
                    Carbon::parse($orphan->lesson_date)->toDateString() . '_' . $orphan->lesson_pair_code;
                if (!isset($activeKeysAfterImport[$key])) {
                    $orphanIds[] = $orphan->id;
                }
            }

            $restoredLocal = 0;
            if (!empty($orphanIds)) {
                $restoredLocal = StudentGrade::onlyTrashed()
                    ->whereIn('id', $orphanIds)
                    ->update(['deleted_at' => null, 'is_final' => $isFinal]);
            }

            if ($restoredLocal > 0) {
                $this->info("Local grades: {$restoredLocal} restored (HEMIS da yo'q, o'qituvchi qo'ygan) for {$dateStart->toDateString()}");
                Log::info("[ApplyGrades] Local grades: {$restoredLocal} restored for {$dateStart->toDateString()}");
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
            $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
            $this->error("Attendance API failed. ({$errorDetail})");
            $this->report['attendance-list'] = [
                'total_days' => 1,
                'success_days' => 0,
                'failed_pages' => ["API xato ({$errorDetail})"],
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

        // Faqat aktiv (soft-delete qilinmagan) yozuvlarni tekshirish
        // withTrashed() ISHLATMASLIK kerak — chunki applyGrades() eski yozuvlarni soft-delete qiladi,
        // va yangi absence record yaratilishi kerak, aks holda davomat bahosi yo'qoladi
        $existingGrade = StudentGrade::where([
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

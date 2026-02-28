<?php

namespace App\Console\Commands;

use App\Models\AttendanceControl;
use App\Models\CurriculumWeek;
use App\Models\Semester;
use App\Services\ImportProgressReporter;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportAttendanceControls extends Command
{
    protected $signature = 'import:attendance-controls
        {--mode=live : Import rejimi: live yoki final}
        {--date= : Faqat shu kun uchun import (Y-m-d)}
        {--date-from= : Sana oralig\'i boshlanishi (Y-m-d)}
        {--date-to= : Sana oralig\'i tugashi (Y-m-d)}
        {--silent : Telegram xabar yubormaslik}';

    protected $description = 'Import attendance controls from HEMIS API (live/final rejimlar)';

    public function handle(TelegramService $telegram): int
    {
        ini_set('memory_limit', '512M');

        // OOM crash himoyasi
        register_shutdown_function(function () use ($telegram) {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $msg = "💀 import:attendance-controls CRASH: {$error['message']} ({$error['file']}:{$error['line']})";
                try { Log::critical($msg); } catch (\Throwable $e) { error_log($msg); }

                if (!$this->option('silent')) {
                    try { $telegram->notify($msg); } catch (\Throwable $e) {}
                }
            }
        });

        $mode = $this->option('mode');
        $dateOption = $this->option('date');
        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');
        $silent = $this->option('silent');

        $token = config('services.hemis.token');

        if ($mode === 'final') {
            return $this->handleFinalImport($token, $telegram, $silent);
        }

        // Sana oralig'i berilgan bo'lsa — range import
        if ($dateFrom && $dateTo) {
            return $this->handleRangeImport($token, $dateFrom, $dateTo, $telegram, $silent);
        }

        return $this->handleLiveImport($token, $dateOption, $telegram, $silent);
    }

    // =========================================================================
    // LIVE IMPORT — kun davomida, bugungi davomat nazoratini yangilaydi
    // =========================================================================
    private function handleLiveImport(string $token, ?string $dateOption, TelegramService $telegram, bool $silent): int
    {
        $date = $dateOption ? Carbon::parse($dateOption) : Carbon::today();
        $todayStr = $date->toDateString();

        // Agar bugungi davomat allaqachon yakunlangan (is_final=true) bo'lsa, import qilmaslik
        $alreadyFinalized = AttendanceControl::where('lesson_date', '>=', $date->copy()->startOfDay())
            ->where('lesson_date', '<=', $date->copy()->endOfDay())
            ->where('is_final', true)
            ->exists();

        if ($alreadyFinalized) {
            $this->info("Live import: {$todayStr} davomat nazorati allaqachon yakunlangan (is_final=true), o'tkazib yuborildi.");
            return self::SUCCESS;
        }

        $this->info("Live import: {$todayStr} uchun davomat nazorati yangilanmoqda...");

        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;

        // Live import — bitta kun, kichik data, fetchAllPages xavfsiz
        $items = $this->fetchAllPages($token, $from, $to);

        if ($items === false) {
            $this->error("Live import: API xato — eski ma'lumotlar saqlanib qoldi.");
            Log::error("[AttCtrl LiveImport] API failed for {$todayStr}, skipping soft delete.");
            if (!$silent) {
                $telegram->notify("❌ Davomat nazorati live import xato: {$todayStr}");
            }
            return self::FAILURE;
        }

        // API muvaffaqiyatli — faqat upsert (soft delete yo'q)
        $this->applyAttendanceControls($items, $date, false);

        $this->info("Live import tugadi: {$todayStr}, API dan: " . count($items) . " ta yozuv");
        return self::SUCCESS;
    }

    // =========================================================================
    // RANGE IMPORT — sana oralig'i uchun, sahifama-sahifa process qiladi
    // SyncReportDataJob dan foydalaniladi
    // =========================================================================
    private function handleRangeImport(string $token, string $dateFrom, string $dateTo, TelegramService $telegram, bool $silent)
    {
        $from = Carbon::parse($dateFrom)->startOfDay();
        $to = Carbon::parse($dateTo)->endOfDay();

        $this->info("Range import: {$from->toDateString()} — {$to->toDateString()} uchun davomat yangilanmoqda...");

        $result = $this->streamPagesAndProcess($token, $from->timestamp, $to->timestamp, false);

        if ($result === false) {
            $this->error("Range import: API xato.");
            Log::error("[AttCtrl RangeImport] API failed for {$dateFrom} — {$dateTo}");
            return 1;
        }

        $this->info("Range import tugadi: {$result['totalDays']} kun, {$result['totalRecords']} yozuv");
        return 0;
    }

    // =========================================================================
    // FINAL IMPORT — har kuni tunda, joriy semestr boshidan kechagacha
    // Davomat o'zgarishi semestr davomida mumkin (sababli/sababsiz)
    // =========================================================================
    private function handleFinalImport(string $token, TelegramService $telegram, bool $silent): int
    {
        // Joriy semestr boshlanish sanasini aniqlash
        $currentSemesterIds = Semester::where('current', true)->pluck('semester_hemis_id');

        $semesterStart = CurriculumWeek::whereIn('semester_hemis_id', $currentSemesterIds)
            ->orderBy('start_date', 'asc')
            ->value('start_date');

        if (!$semesterStart) {
            $msg = '❌ Joriy semestr sanasi topilmadi (CurriculumWeek da ma\'lumot yo\'q)';
            $this->error($msg);
            if (!$silent) { $telegram->notify($msg); }
            return self::FAILURE;
        }

        $from = Carbon::parse($semesterStart)->startOfDay();
        $to = Carbon::yesterday()->endOfDay();

        $dateRange = "{$from->toDateString()} — {$to->toDateString()}";

        if (!$silent) {
            $telegram->notify("🟢 Davomat nazorati FINAL import boshlandi ({$dateRange})");
        }
        $this->info("Starting FINAL attendance controls import ({$dateRange})...");

        // Sahifama-sahifa process — xotiraga yig'masdan
        $result = $this->streamPagesAndProcess($token, $from->timestamp, $to->timestamp, true);

        if ($result === false) {
            $this->error('Final import: API xato — import bekor qilindi.');
            if (!$silent) {
                $telegram->notify("❌ Davomat nazorati FINAL import xato: API javob bermadi");
            }
            return self::FAILURE;
        }

        $totalDays = $result['totalDays'];
        $totalRecords = $result['totalRecords'];

        $msg = "✅ Davomat nazorati FINAL: {$totalDays} kun, {$totalRecords} yozuv ({$dateRange})";
        if (!$silent) {
            $telegram->notify($msg);
        }
        $this->info($msg);

        // Nightly wrapper ga yakuniy natija
        if (app()->bound('nightly.progress')) {
            $nightlyCallback = app('nightly.progress');
            $nightlyCallback("{$totalDays} kun, {$totalRecords} yozuv");
        }

        return self::SUCCESS;
    }

    // =========================================================================
    // Sahifama-sahifa fetch + process — xotira tejaladi
    // Har sahifani olganidan keyin darhol bazaga yozadi
    // =========================================================================
    private function streamPagesAndProcess(string $token, ?int $from, ?int $to, bool $isFinal): array|false
    {
        $reporter = app()->bound(ImportProgressReporter::class) ? app(ImportProgressReporter::class) : null;
        $page = 1;
        $pageSize = 200;
        $totalPages = 1;
        $maxRetries = 3;
        $totalFetched = 0;

        $todayStr = Carbon::today()->toDateString();
        $softDeletedDates = []; // Qaysi sanalar uchun soft-delete qilingan
        $dateCounts = [];       // Har sana uchun yozuvlar soni

        do {
            $url = "https://student.ttatf.uz/rest/v1/data/attendance-control-list?limit={$pageSize}&page={$page}";
            if ($from !== null && $to !== null) {
                $url .= "&lesson_date_from={$from}&lesson_date_to={$to}";
            }

            $retryCount = 0;
            $pageSuccess = false;

            while ($retryCount < $maxRetries && !$pageSuccess) {
                try {
                    $response = Http::withoutVerifying()
                        ->withToken($token)
                        ->timeout(60)
                        ->get($url);

                    if ($response->successful()) {
                        $json = $response->json();
                        if (!($json['success'] ?? false)) {
                            $this->error("API returned success=false on page {$page}");
                            return false;
                        }

                        $items = $json['data']['items'] ?? [];
                        $totalPages = $json['data']['pagination']['pageCount'] ?? 1;
                        $totalFetched += count($items);

                        $this->info("Fetched page {$page}/{$totalPages} (" . count($items) . " items)");
                        if ($reporter) {
                            $reporter->updateProgress($page, $totalPages);
                        }
                        // Nightly wrapper ga API progress
                        if (app()->bound('nightly.progress')) {
                            $nightlyCallback = app('nightly.progress');
                            $nightlyCallback("API: {$page}/{$totalPages} sahifa ({$totalFetched} yozuv)");
                        }

                        // Sahifadagi itemlarni sanalar bo'yicha guruhlash va darhol process qilish
                        $this->processPageItems($items, $isFinal, $todayStr, $softDeletedDates, $dateCounts);

                        $pageSuccess = true;
                        sleep(1);
                    } else {
                        $retryCount++;
                        Log::error("[AttCtrl Fetch] Page {$page} failed. Status: {$response->status()}");
                        if ($retryCount < $maxRetries) {
                            sleep(3);
                        }
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    Log::error("[AttCtrl Fetch] Page {$page} exception: " . $e->getMessage());
                    if ($retryCount < $maxRetries) {
                        sleep(3);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->error("Failed page {$page} after {$maxRetries} retries — ABORTING.");
                return false;
            }

            // Har 10 sahifada memory tozalash
            if ($page % 10 === 0) {
                gc_collect_cycles();
            }

            $page++;
        } while ($page <= $totalPages);

        $this->info("Total items fetched: {$totalFetched}");

        return [
            'totalFetched' => $totalFetched,
            'totalDays' => count($dateCounts),
            'totalRecords' => array_sum($dateCounts),
            'dateCounts' => $dateCounts,
        ];
    }

    // =========================================================================
    // Sahifa itemlarini sanalar bo'yicha guruhlash va darhol bazaga yozish
    // =========================================================================
    private function processPageItems(array $items, bool $isFinal, string $todayStr, array &$softDeletedDates, array &$dateCounts): void
    {
        if (empty($items)) return;

        // Sanalar bo'yicha guruhlash
        $groupedByDate = [];
        foreach ($items as $item) {
            $dateStr = isset($item['lesson_date']) ? date('Y-m-d', $item['lesson_date']) : null;
            if ($dateStr === null) continue;

            // Final import: bugunni o'tkazib yuborish (bugunni live import boshqaradi)
            if ($isFinal && $dateStr >= $todayStr) continue;

            $groupedByDate[$dateStr][] = $item;
        }

        foreach ($groupedByDate as $dateStr => $dateItems) {
            $date = Carbon::parse($dateStr);

            // Soft-delete faqat FINAL da va faqat BIR MARTA har sana uchun
            $doSoftDelete = $isFinal && !isset($softDeletedDates[$dateStr]);
            if ($doSoftDelete) {
                $softDeletedDates[$dateStr] = true;
            }

            $this->applyAttendanceControls($dateItems, $date, $isFinal, $doSoftDelete);
            $dateCounts[$dateStr] = ($dateCounts[$dateStr] ?? 0) + count($dateItems);
        }
    }

    // =========================================================================
    // API dan barcha sahifalarni xotiraga yig'ish (faqat live import uchun — kichik data)
    // =========================================================================
    private function fetchAllPages(string $token, ?int $from = null, ?int $to = null): array|false
    {
        $reporter = app()->bound(ImportProgressReporter::class) ? app(ImportProgressReporter::class) : null;
        $allItems = [];
        $page = 1;
        $pageSize = 200;
        $totalPages = 1;
        $maxRetries = 3;

        do {
            $url = "https://student.ttatf.uz/rest/v1/data/attendance-control-list?limit={$pageSize}&page={$page}";
            if ($from !== null && $to !== null) {
                $url .= "&lesson_date_from={$from}&lesson_date_to={$to}";
            }

            $retryCount = 0;
            $pageSuccess = false;

            while ($retryCount < $maxRetries && !$pageSuccess) {
                try {
                    $response = Http::withoutVerifying()
                        ->withToken($token)
                        ->timeout(60)
                        ->get($url);

                    if ($response->successful()) {
                        $json = $response->json();
                        if (!($json['success'] ?? false)) {
                            $this->error("API returned success=false on page {$page}");
                            return false;
                        }

                        $items = $json['data']['items'] ?? [];
                        $totalPages = $json['data']['pagination']['pageCount'] ?? 1;
                        $allItems = array_merge($allItems, $items);
                        $this->info("Fetched page {$page}/{$totalPages} (" . count($items) . " items)");
                        if ($reporter) {
                            $reporter->updateProgress($page, $totalPages);
                        }
                        // Nightly wrapper ga API progress
                        if (app()->bound('nightly.progress')) {
                            $nightlyCallback = app('nightly.progress');
                            $nightlyCallback("API: {$page}/{$totalPages} sahifa (" . count($allItems) . " yozuv)");
                        }
                        $pageSuccess = true;
                        sleep(1);
                    } else {
                        $retryCount++;
                        Log::error("[AttCtrl Fetch] Page {$page} failed. Status: {$response->status()}");
                        if ($retryCount < $maxRetries) {
                            sleep(3);
                        }
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    Log::error("[AttCtrl Fetch] Page {$page} exception: " . $e->getMessage());
                    if ($retryCount < $maxRetries) {
                        sleep(3);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->error("Failed page {$page} after {$maxRetries} retries — ABORTING.");
                return false;
            }

            $page++;
        } while ($page <= $totalPages);

        $this->info("Total items fetched: " . count($allItems));
        return $allItems;
    }

    // =========================================================================
    // Lock wait timeout bo'lganda qayta urinish (3 marta, oraliq bilan)
    // =========================================================================
    private function retryOnLockTimeout(callable $callback, int $maxRetries = 3): mixed
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $callback();
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 1205 || $e->getCode() == 1213 || $e->getCode() == '40001'
                    || str_contains($e->getMessage(), 'Lock wait timeout')
                    || str_contains($e->getMessage(), 'Deadlock found')) {
                    $waitSeconds = $attempt * 2;
                    $this->warn("Lock/Deadlock xato (urinish {$attempt}/{$maxRetries}), {$waitSeconds}s kutilmoqda...");
                    Log::warning("[AttCtrl] Lock/Deadlock attempt {$attempt}/{$maxRetries}, waiting {$waitSeconds}s");
                    sleep($waitSeconds);

                    if ($attempt === $maxRetries) {
                        throw $e;
                    }
                } else {
                    throw $e;
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Davomat nazoratini bazaga yozish
    // Live: faqat upsert (soft-delete QILMAYDI — API har doim to'liq ma'lumot qaytarmaydi)
    // Final: soft delete + upsert (tunda, barcha ma'lumot to'liq bo'lganda)
    // =========================================================================
    private function applyAttendanceControls(array $items, Carbon $date, bool $isFinal, bool $doSoftDelete = true): void
    {
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();
        $dateStr = $dateStart->toDateString();
        $now = now();

        $softDeletedCount = 0;

        // Soft delete faqat FINAL import da VA doSoftDelete=true bo'lganda
        if ($isFinal && $doSoftDelete) {
            $softDeletedCount = $this->retryOnLockTimeout(fn () =>
                AttendanceControl::where('lesson_date', '>=', $dateStart)
                    ->where('lesson_date', '<=', $dateEnd)
                    ->delete()
            );
            $this->info("Soft deleted {$softDeletedCount} old records for {$dateStr}");
        }

        // Upsert uchun ma'lumotlarni tayyorlash
        $upsertRows = [];
        foreach ($items as $item) {
            $lessonDate = isset($item['lesson_date']) ? date('Y-m-d H:i:s', $item['lesson_date']) : null;

            $upsertRows[] = [
                'hemis_id' => $item['id'],
                'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                'subject_id' => $item['subject']['id'] ?? null,
                'subject_code' => $item['subject']['code'] ?? null,
                'subject_name' => $item['subject']['name'] ?? null,
                'employee_id' => $item['employee']['id'] ?? null,
                'employee_name' => $item['employee']['name'] ?? null,
                'education_year_code' => $item['educationYear']['code'] ?? null,
                'education_year_name' => $item['educationYear']['name'] ?? null,
                'semester_code' => $item['semester']['code'] ?? null,
                'semester_name' => $item['semester']['name'] ?? null,
                'group_id' => $item['group']['id'] ?? null,
                'group_name' => $item['group']['name'] ?? null,
                'education_lang_code' => $item['group']['educationLang']['code'] ?? null,
                'education_lang_name' => $item['group']['educationLang']['name'] ?? null,
                'training_type_code' => $item['trainingType']['code'] ?? null,
                'training_type_name' => $item['trainingType']['name'] ?? null,
                'lesson_pair_code' => $item['lessonPair']['code'] ?? null,
                'lesson_pair_name' => $item['lessonPair']['name'] ?? null,
                'lesson_pair_start_time' => $item['lessonPair']['start_time'] ?? null,
                'lesson_pair_end_time' => $item['lessonPair']['end_time'] ?? null,
                'lesson_date' => $lessonDate,
                'load' => $item['load'] ?? 2,
                'is_final' => $isFinal,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert — 200 tadan chunk qilib, har biri alohida tranzaksiya
        $updateColumns = [
            'subject_schedule_id', 'subject_id', 'subject_code', 'subject_name',
            'employee_id', 'employee_name', 'education_year_code', 'education_year_name',
            'semester_code', 'semester_name', 'group_id', 'group_name',
            'education_lang_code', 'education_lang_name', 'training_type_code', 'training_type_name',
            'lesson_pair_code', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time',
            'lesson_date', 'load', 'is_final', 'deleted_at', 'updated_at',
        ];

        foreach (array_chunk($upsertRows, 200) as $chunk) {
            $this->retryOnLockTimeout(fn () =>
                DB::table('attendance_controls')->upsert($chunk, ['hemis_id'], $updateColumns)
            );
        }

        $this->info("Written " . count($upsertRows) . " records for {$dateStr} (is_final=" . ($isFinal ? 'true' : 'false') . ")");
    }
}

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
use App\Services\ImportProgressReporter;
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
    private ?int $telegramProgressMsgId = null;
    private array $dayStatuses = [];
    private ?float $importStartTime = null;
    private float $lastTelegramUpdate = 0;
    private string $currentDayKey = '';
    private int $currentDayNum = 0;
    private int $currentDayTotal = 0;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url') ?? '';
        $this->token = config('services.hemis.token') ?? '';
    }

    protected $signature = 'student:import-data {--mode=live : Import mode: live, final, or backfill} {--from= : Backfill start date (Y-m-d)} {--to= : Backfill end date (Y-m-d), default yesterday} {--date= : Single date import (Y-m-d)} {--silent : Suppress Telegram notifications}';

    protected $description = 'Import student grades and attendance from Hemis API';

    public function handle()
    {
        if ($this->option('date')) {
            return $this->handleSingleDayImport();
        }

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
    // SINGLE DAY IMPORT — ma'lum bir sana uchun baholarni import qilish
    // Hisobot sync (Yangilash) tugmasidan chaqiriladi
    // =========================================================================
    private function handleSingleDayImport()
    {
        $date = Carbon::parse($this->option('date'));
        $dateStr = $date->toDateString();

        $this->info("Single-day import: {$dateStr}");
        Log::info("[SingleDayImport] Starting for {$dateStr}");

        $from = Carbon::parse($dateStr, 'UTC')->startOfDay()->timestamp;
        $to = Carbon::parse($dateStr, 'UTC')->endOfDay()->timestamp;

        $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to, $date);

        if ($gradeItems === false) {
            $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
            $this->error("Single-day import: API xato ({$errorDetail}) — {$dateStr}");
            Log::error("[SingleDayImport] API failed for {$dateStr}: {$errorDetail}");
            return;
        }

        if (!empty($gradeItems)) {
            $this->applyGrades($gradeItems, $date, false);
        }

        $count = count($gradeItems);
        $this->info("Single-day import done: {$dateStr}, {$count} ta baho");
        Log::info("[SingleDayImport] Completed for {$dateStr}: {$count} grades");
    }

    // =========================================================================
    // LIVE IMPORT — har 30 daqiqada, bugungi baholarni yangilaydi
    // =========================================================================
    private function handleLiveImport()
    {
        $reporter = app()->bound(ImportProgressReporter::class) ? app(ImportProgressReporter::class) : null;
        if ($reporter) {
            $reporter->start();
            $reporter->startStep('Baholar import qilinmoqda', 'Baholar import qilindi');
        }

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
            // Cache ni yangilash — hisobot komandlari "yaqinda import bo'lgan" deb bilishi uchun
            // (oldin cache yangilanmasdi → 18:00 hisobot "import yo'q" deb qayta import boshlardi)
            \Illuminate\Support\Facades\Cache::put('live_import_last_success', Carbon::now()->toDateTimeString(), Carbon::today()->endOfDay());
            return;
        }

        $liveStartTime = microtime(true);

        // HEMIS API timestamplarni UTC kun chegaralari bo'yicha filter qiladi.
        $from = Carbon::parse($today->toDateString(), 'UTC')->startOfDay()->timestamp;
        $to = Carbon::parse($today->toDateString(), 'UTC')->endOfDay()->timestamp;

        // 1-qadam: Baholarni API dan tortib olish (xotiraga)
        if ($reporter) {
            $reporter->setStepContext('baholar API...');
        }
        $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to, $today);
        $gradeCount = 0;
        $gradeError = null;

        if ($gradeItems === false) {
            $gradeError = $this->lastFetchError ?: 'noma\'lum xato';
            $this->error("Grade API failed — eski baholar saqlanib qoldi. ({$gradeError})");
            Log::error("[LiveImport] Grade API failed: {$gradeError}");
            if ($reporter) {
                $reporter->failStep($gradeError);
            }
        } else {
            try {
                if ($reporter) {
                    $reporter->setStepContext('bazaga yozilmoqda ' . count($gradeItems) . ' ta yozuv...');
                }
                $gradeCount = count($gradeItems);
                $this->applyGrades($gradeItems, $today, false);
                if ($reporter) {
                    $reporter->completeStep();
                }
            } catch (\Throwable $e) {
                $gradeError = substr($e->getMessage(), 0, 100);
                $this->error("applyGrades EXCEPTION: {$e->getMessage()}");
                Log::error("[LiveImport] applyGrades exception: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString(),
                ]);
                if ($reporter) {
                    $reporter->failStep($gradeError);
                }
            }
        }

        // 2-qadam: Davomatni import qilish
        if ($reporter) {
            $reporter->startStep('Davomat import qilinmoqda', 'Davomat import qilindi');
            $reporter->setStepContext('davomat API...');
        }
        $attendanceError = null;
        try {
            $this->importAttendance($from, $to, $today);
            if ($reporter) {
                $reporter->completeStep();
            }
        } catch (\Throwable $e) {
            $attendanceError = substr($e->getMessage(), 0, 100);
            $this->error("importAttendance EXCEPTION: {$e->getMessage()}");
            Log::error("[LiveImport] importAttendance exception: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);
            if ($reporter) {
                $reporter->failStep($attendanceError);
            }
        }

        // NB sonini bazadan olish (bugungi)
        $nbCount = StudentGrade::where('lesson_date', '>=', $today->copy()->startOfDay())
            ->where('lesson_date', '<=', $today->copy()->endOfDay())
            ->where('reason', 'absent')
            ->whereNull('deleted_at')
            ->count();

        $elapsed = round((microtime(true) - $liveStartTime) / 60, 1);

        // Cache ni AVVAL yozish — sendDailyLiveReport crash qilsa ham data muvaffaqiyati saqlansin
        // TTL: kun oxirigacha (oldin 2 soat edi — 15:30 da yozilgan cache 17:30 da expire bo'lib,
        // 18:00 hisobot import qayta boshlardi va oxiriga yetolmasdan to'xtardi)
        if (!$gradeError && !$attendanceError) {
            \Illuminate\Support\Facades\Cache::put('live_import_last_success', Carbon::now()->toDateTimeString(), Carbon::today()->endOfDay());
        }

        if (!$this->option('silent')) {
            $this->sendDailyLiveReport($gradeCount, $nbCount, $gradeError, $attendanceError, $elapsed);
        }
        Log::info('[LiveImport] Completed at ' . Carbon::now());
    }

    // =========================================================================
    // FINAL IMPORT — har kuni 00:30 da, yakunlanmagan kunlarni is_final=true qiladi
    // Faqat kechagini emas, oxirgi 7 kun ichidagi BARCHA is_final=false kunlarni tekshiradi
    // Bu API fail bo'lgan kunlarni avtomatik qayta urinadi
    // =========================================================================
    private function handleFinalImport()
    {
        // Final import ko'p xotira ishlatadi (7 kunlik API ma'lumotlari) — OOM oldini olish
        $currentLimit = trim(ini_get('memory_limit'));
        if ($currentLimit !== '-1') {
            ini_set('memory_limit', '512M');
        }

        // Reporter faqat mustaqil chaqirilganda boshlanadi,
        // SendAttendanceFinalDailyReport dan chaqirilganda reporter allaqachon boshqarilmoqda
        $reporter = app()->bound(ImportProgressReporter::class) ? app(ImportProgressReporter::class) : null;

        $this->info('Starting FINAL import...');
        Log::info('[FinalImport] Starting at ' . Carbon::now());

        // Crash detection: agar process o'lib qolsa (OOM, fatal error),
        // Telegram ga xabar yuboriladi
        $crashDetected = true; // sendProgressDone() dan keyin false bo'ladi
        $shutdownChatId = config('services.telegram.chat_id');
        $shutdownMsgId = &$this->telegramProgressMsgId;
        $shutdownDayStatuses = &$this->dayStatuses;
        $shutdownStartTime = &$this->importStartTime;
        register_shutdown_function(function () use (&$crashDetected, $shutdownChatId, &$shutdownMsgId, &$shutdownDayStatuses, &$shutdownStartTime) {
            if (!$crashDetected) return;

            $error = error_get_last();
            $errorMsg = $error ? "{$error['type']}: {$error['message']}" : 'process killed';
            Log::critical("[FinalImport] CRASH detected in shutdown handler: {$errorMsg}");

            if (!$shutdownMsgId || !$shutdownChatId) return;

            try {
                $elapsed = $shutdownStartTime ? round((microtime(true) - $shutdownStartTime) / 60, 1) : 0;
                $lines = [];
                foreach ($shutdownDayStatuses as $d => $s) {
                    $label = (strlen($d) === 10 && ($d[4] ?? '') === '-') ? substr($d, 5) : $d;
                    $lines[] = "{$label} {$s}";
                }

                $msg = "💀 FINAL import CRASH\n"
                     . Carbon::now()->format('d.m.Y H:i') . "\n\n"
                     . implode("\n", $lines) . "\n\n"
                     . "❌ Process o'lib qoldi\n"
                     . "⏱ {$elapsed} daq\n\n"
                     . "Sabab: " . substr($errorMsg, 0, 200);

                app(TelegramService::class)->editMessage($shutdownChatId, $shutdownMsgId, $msg);
            } catch (\Throwable $e) {
                // Shutdown handler ichida exception tashlash xavfli
            }
        });

        // Oxirgi 7 kunni tekshirish (bugundan tashqari)
        // LIVE importga bog'liq emas — har bir kunni mustaqil tekshiramiz
        $lookbackStart = Carbon::today()->subDays(7)->startOfDay();
        $todayStart = Carbon::today()->startOfDay();

        // Barcha 7 kunni ro'yxatga olish
        $allDatesToProcess = [];
        for ($d = $lookbackStart->copy(); $d->lt($todayStart); $d->addDay()) {
            $allDatesToProcess[] = $d->toDateString();
        }

        $totalDays = count($allDatesToProcess);
        $successDays = 0;
        $failedDays = [];
        $dayNum = 0;

        $this->info("Final import: {$totalDays} ta kun tekshiriladi ({$allDatesToProcess[0]} — " . end($allDatesToProcess) . ")");
        Log::info("[FinalImport] Processing {$totalDays} days", ['dates' => $allDatesToProcess]);

        $originalTotal = $totalDays;
        $this->sendProgressStart('final', $totalDays, "{$allDatesToProcess[0]} — " . end($allDatesToProcess));

        foreach ($allDatesToProcess as $dateStr) {
            $dayNum++;
            $date = Carbon::parse($dateStr);

            // Kun ichidagi sub-progress uchun kontekst
            $this->currentDayKey = $dateStr;
            $this->currentDayNum = $dayNum;
            $this->currentDayTotal = $originalTotal;

            try {
                $dateStartOfDay = $date->copy()->startOfDay();
                $dateEndOfDay = $date->copy()->endOfDay();

                if ($reporter) {
                    $reporter->setStepContext("{$dayNum}/{$totalDays} kun ({$dateStr})");
                }

                // Bazada shu kun uchun yozuvlar holati
                $hasUnfinalizedForDate = StudentGrade::where('lesson_date', '>=', $dateStartOfDay)
                    ->where('lesson_date', '<=', $dateEndOfDay)
                    ->where('is_final', false)
                    ->whereNull('deleted_at')
                    ->exists();

                $finalizedCount = StudentGrade::where('lesson_date', '>=', $dateStartOfDay)
                    ->where('lesson_date', '<=', $dateEndOfDay)
                    ->where('is_final', true)
                    ->whereNull('deleted_at')
                    ->count();

                // ─── Stsenariy A: is_final=false + is_final=true aralash mavjud ───
                // Dublikatlarni tozalash (journal sync tiklagan is_final=false yozuvlar)
                if ($hasUnfinalizedForDate && $finalizedCount > 0) {
                    $cleaned = DB::update("
                        UPDATE student_grades sg
                        INNER JOIN student_grades g2
                            ON g2.student_id = sg.student_id
                            AND g2.subject_id = sg.subject_id
                            AND DATE(g2.lesson_date) = DATE(sg.lesson_date)
                            AND g2.lesson_pair_code = sg.lesson_pair_code
                            AND g2.training_type_code = sg.training_type_code
                            AND g2.is_final = 1
                            AND g2.deleted_at IS NULL
                            AND g2.id != sg.id
                        SET sg.deleted_at = NOW()
                        WHERE sg.is_final = 0
                            AND sg.deleted_at IS NULL
                            AND DATE(sg.lesson_date) = ?
                    ", [$dateStr]);

                    // Qolgan is_final=false yozuvlarni (lokal/qo'lda kiritilgan) is_final=true qilish
                    $upgraded = StudentGrade::where('lesson_date', '>=', $dateStartOfDay)
                        ->where('lesson_date', '<=', $dateEndOfDay)
                        ->where('is_final', false)
                        ->whereNull('deleted_at')
                        ->update(['is_final' => true]);

                    $this->info("  {$dateStr} — dublikat tozalandi: {$cleaned} o'chirildi, {$upgraded} ta lokal baho yakunlandi.");
                    Log::info("[FinalImport] {$dateStr} — cleaned {$cleaned} duplicate is_final=false, upgraded {$upgraded} unique local records.");

                    // Oxirgi 3 kun DOIM API dan tortiladi (to'liq ishonch uchun)
                    // Eski kunlar (4+ kun) uchun — yetarli yozuv bo'lsa skip
                    // Carbon 3: diffInDays() manfiy qaytaradi, shuning uchun abs()
                    $daysAgo = abs(Carbon::today()->diffInDays($date));
                    if ($finalizedCount >= 500 && $daysAgo >= 3) {
                        $this->importDayAttendance($dateStr, $date, true);
                        $this->updateDayProgress($dateStr, '✅', "tozalandi ({$cleaned})", $dayNum, $originalTotal);
                        $successDays++;
                        continue;
                    }
                    // Kam yozuv yoki yaqin sana — API dan to'ldirish kerak
                    $this->info("  {$dateStr} — qisman yakunlangan ({$finalizedCount} ta yozuv, {$daysAgo} kun oldin), API dan to'ldirish...");
                }

                // ─── Stsenariy B: Faqat is_final=true mavjud ───
                // Oxirgi 3 kun DOIM API dan qayta tortiladi (race condition yoki API xato bo'lgan bo'lishi mumkin)
                // Eski kunlar (4+ kun) — yetarli yozuv bo'lsa skip
                // Carbon 3: diffInDays() manfiy qaytaradi, shuning uchun abs()
                $daysAgo = abs(Carbon::today()->diffInDays($date));
                if (!$hasUnfinalizedForDate && $finalizedCount > 0 && $finalizedCount >= 500 && $daysAgo >= 3) {
                    $this->importDayAttendance($dateStr, $date, true);
                    $this->info("  {$dateStr} — to'liq yakunlangan ({$finalizedCount} ta yozuv, {$daysAgo} kun oldin), o'tkazildi.");
                    $this->updateDayProgress($dateStr, '✅', "to'liq ({$finalizedCount})", $dayNum, $originalTotal);
                    $successDays++;
                    continue;
                }

                // ─── Stsenariy C: API dan tortish ───
                // - Bazada yozuv yo'q (LIVE import ishlamagan)
                // - Faqat is_final=false mavjud (is_final=true hali yo'q)
                // - Qisman is_final=true (journal sync dan, kam yozuv)
                // UTC midnight — HEMIS API UTC kun chegaralari bo'yicha filter qiladi
                $from = Carbon::parse($date->toDateString(), 'UTC')->startOfDay()->timestamp;
                $to = Carbon::parse($date->toDateString(), 'UTC')->endOfDay()->timestamp;

                $reason = $finalizedCount === 0 && !$hasUnfinalizedForDate
                    ? 'bazada yozuv yo\'q'
                    : ($finalizedCount > 0 ? "qisman ({$finalizedCount} ta yozuv)" : 'is_final=false mavjud');
                $this->info("  {$dateStr} — API dan tortilmoqda ({$reason})...");
                $this->updateDayProgress($dateStr, '⏳', "API...", $dayNum, $originalTotal);

                if ($reporter) {
                    $reporter->setStepContext("{$dayNum}/{$totalDays} kun ({$dateStr}), baholar API...");
                }
                $gradeItems = $this->fetchAllPages('student-grade-list', $from, $to, $date);

                if ($gradeItems === false) {
                    $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
                    $this->error("  {$dateStr} — API xato ({$errorDetail}), keyingi kunga o'tiladi.");
                    $this->updateDayProgress($dateStr, '❌', "API xato", $dayNum, $originalTotal);
                    $failedDays[] = "{$dateStr} ({$errorDetail})";
                    continue;
                }

                if (empty($gradeItems)) {
                    // Baho bo'lmasa ham, davomat (NB) bo'lishi mumkin
                    $this->importDayAttendance($dateStr, $date, true);

                    if (!$hasUnfinalizedForDate && $finalizedCount === 0) {
                        // Bazada yozuv yo'q, API ham 0 — bu kunda dars bo'lmagan
                        $this->info("  {$dateStr} — dars bo'lmagan (API 0, bazada yozuv yo'q).");
                        $this->updateDayProgress($dateStr, '✅', "dars yo'q", $dayNum, $originalTotal);
                        $successDays++;
                    } elseif ($hasUnfinalizedForDate) {
                        // is_final=false yozuvlar bor, lekin API 0 qaytardi — retry uchun saqlanadi
                        $this->warn("  {$dateStr} — API 0 ta baho qaytardi, is_final=false saqlanadi (keyingi importda qayta uriniladi).");
                        $this->updateDayProgress($dateStr, '⚠️', "API 0, retry", $dayNum, $originalTotal);
                        Log::warning("[FinalImport] {$dateStr} — API returned 0 grades, keeping is_final=false for retry.");
                        $failedDays[] = "{$dateStr} (API 0, is_final=false saqlanadi)";
                    } else {
                        // Faqat is_final=true bor (journal sync dan), API 0 — yangi ma'lumot yo'q
                        $this->info("  {$dateStr} — API 0, mavjud {$finalizedCount} ta yozuv saqlanadi.");
                        $this->updateDayProgress($dateStr, '✅', "mavjud ({$finalizedCount})", $dayNum, $originalTotal);
                        $successDays++;
                    }
                    continue;
                }

                if ($reporter) {
                    $reporter->setStepContext("{$dayNum}/{$totalDays} kun ({$dateStr}), bazaga yozilmoqda " . count($gradeItems) . " ta yozuv...");
                }
                $gradeCount = count($gradeItems);
                $this->applyGrades($gradeItems, $date, true);
                unset($gradeItems); // Xotirani bo'shatish — keyingi kun uchun joy

                // Attendance
                if ($reporter) {
                    $reporter->setStepContext("{$dayNum}/{$totalDays} kun ({$dateStr}), davomat API...");
                }
                $this->importDayAttendance($dateStr, $date, true);

                $this->updateDayProgress($dateStr, '✅', "{$gradeCount} ta baho", $dayNum, $originalTotal);
                $successDays++;
                $this->info("  {$date->toDateString()} — yakunlandi ({$successDays}/{$totalDays})");
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                $this->error("  {$date->toDateString()} — EXCEPTION: {$errorMsg}");
                Log::error("[FinalImport] {$date->toDateString()} exception: {$errorMsg}", [
                    'trace' => $e->getTraceAsString(),
                ]);
                $failedDays[] = "{$date->toDateString()} (Exception: " . substr($errorMsg, 0, 100) . ")";
                $this->updateDayProgress($dateStr, '❌', "xato", $dayNum, $originalTotal);
            }
        }

        // Global tozalash: is_final=false duplikatlarni kunlik batch qilib soft-delete qilish
        // Faqat oxirgi 14 kun — asosiy sabab (JournalController zombie tiklash) tuzatilgan
        // Agar tarixiy tozalash kerak bo'lsa: php artisan student:import-data --mode=backfill
        $cleanupDays = 14;
        $cleanupFrom = Carbon::today()->subDays($cleanupDays)->toDateString();
        $this->info("Global tozalash boshlandi (oxirgi {$cleanupDays} kun)...");
        try {
            $globalCleaned = 0;
            $staleDates = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->where('is_final', false)
                ->whereRaw('DATE(lesson_date) >= ?', [$cleanupFrom])
                ->whereRaw('DATE(lesson_date) < CURDATE()')
                ->selectRaw('DATE(lesson_date) as grade_date')
                ->distinct()
                ->pluck('grade_date');

            $staleTotal = $staleDates->count();
            $staleProcessed = 0;
            $this->info("  {$staleTotal} ta kun topildi, tozalash boshlanmoqda...");
            $this->updateDayProgress('tozalash', '⏳', "0/{$staleTotal} kun...", $dayNum, $originalTotal);

            $lastTgUpdate = microtime(true);

            foreach ($staleDates as $staleDate) {
                $cleaned = DB::update("
                    UPDATE student_grades sg
                    INNER JOIN student_grades g2
                        ON g2.student_id = sg.student_id
                        AND g2.subject_id = sg.subject_id
                        AND DATE(g2.lesson_date) = DATE(sg.lesson_date)
                        AND g2.lesson_pair_code = sg.lesson_pair_code
                        AND g2.training_type_code = sg.training_type_code
                        AND g2.is_final = 1
                        AND g2.deleted_at IS NULL
                        AND g2.id != sg.id
                    SET sg.deleted_at = NOW()
                    WHERE sg.is_final = 0
                        AND sg.deleted_at IS NULL
                        AND DATE(sg.lesson_date) = ?
                ", [$staleDate]);
                $globalCleaned += $cleaned;
                $staleProcessed++;

                // Telegram va consolega har 5 kunda yoki 10 sekundda yangilash
                $now = microtime(true);
                if ($staleProcessed % 5 === 0 || $staleProcessed === $staleTotal || ($now - $lastTgUpdate) > 10) {
                    $this->info("  tozalash: {$staleProcessed}/{$staleTotal} kun ({$globalCleaned} ta o'chirildi)");
                    $this->updateDayProgress('tozalash', '⏳', "{$staleProcessed}/{$staleTotal} kun, {$globalCleaned} ta", $dayNum, $originalTotal);
                    $lastTgUpdate = $now;
                }
            }

            if ($globalCleaned > 0) {
                $this->info("Global cleanup: {$globalCleaned} ta eski is_final=false duplikat tozalandi ({$staleTotal} kun).");
                Log::info("[FinalImport] Global cleanup: {$globalCleaned} stale is_final=false duplicates removed across {$staleTotal} days.");
            }
        } catch (\Throwable $e) {
            Log::error("[FinalImport] Global cleanup exception: {$e->getMessage()}");
            $failedDays[] = "Global cleanup (Exception: " . substr($e->getMessage(), 0, 100) . ")";
        }

        $cleanedCount = $globalCleaned ?? 0;
        $this->updateDayProgress('tozalash', '✅', "tugadi ({$cleanedCount} ta)", $dayNum, $originalTotal);

        $this->report['final-import'] = [
            'total_days' => $totalDays,
            'success_days' => $successDays,
            'failed_pages' => $failedDays,
        ];

        $this->sendProgressDone('final', $successDays, $originalTotal, $failedDays);
        $crashDetected = false; // Normal tugatish — shutdown handler ishlamasligi kerak
        $this->sendTelegramReport();
        Log::info("[FinalImport] Completed at " . Carbon::now() . ": {$successDays}/{$totalDays} days finalized.");

        // 04:00 retry ni boshqarish — muvaffaqiyatli bo'lsa cache ga yozish
        if (empty($failedDays)) {
            \Illuminate\Support\Facades\Cache::put('final_import_last_success', Carbon::now()->toDateTimeString(), now()->addHours(12));
        }
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
        $toDate = $this->option('to');
        $endDate = $toDate ? Carbon::parse($toDate)->startOfDay() : Carbon::yesterday()->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            $this->error("Boshlanish sanasi ({$startDate->toDateString()}) tugash sanasidan ({$endDate->toDateString()}) katta bo'lishi mumkin emas.");
            return;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = abs($startDate->diffInDays($endDate)) + 1;

        $this->info("BACKFILL: {$startDate->toDateString()} → {$endDate->toDateString()} ({$totalDays} kun)");
        Log::info("[Backfill] Starting from {$startDate->toDateString()} to {$endDate->toDateString()}");

        $this->sendProgressStart('backfill', $totalDays, "{$startDate->toDateString()} — {$endDate->toDateString()}");

        $successDays = 0;
        $failedDays = [];
        $dayNum = 0;

        foreach ($period as $date) {
            $dayNum++;

            // Kun ichidagi sub-progress uchun kontekst
            $this->currentDayKey = $date->toDateString();
            $this->currentDayNum = $dayNum;
            $this->currentDayTotal = $totalDays;

            // UTC midnight — HEMIS API UTC kun chegaralari bo'yicha filter qiladi
            $dayFrom = Carbon::parse($date->toDateString(), 'UTC')->startOfDay()->timestamp;
            $dayTo = Carbon::parse($date->toDateString(), 'UTC')->endOfDay()->timestamp;

            $this->info("--- {$date->toDateString()} ---");

            // Baholar — $date ni berib, boshqa kunlik recordlarni fetch paytida filtrlaymiz
            $gradeItems = $this->fetchAllPages('student-grade-list', $dayFrom, $dayTo, $date);

            if ($gradeItems === false) {
                $errorDetail = $this->lastFetchError ?: 'noma\'lum xato';
                $this->error("XATO: {$date->toDateString()} — baholar import qilinmadi ({$errorDetail}), keyingi kunga o'tiladi.");
                $this->updateDayProgress($date->toDateString(), '❌', "API xato", $dayNum, $totalDays);
                $failedDays[] = "{$date->toDateString()} ({$errorDetail})";
                continue;
            }

            $isFinal = !$date->isToday();
            $this->applyGrades($gradeItems, $date, $isFinal);

            // Attendance
            $attendanceItems = $this->fetchAllPages('attendance-list', $dayFrom, $dayTo);
            if ($attendanceItems !== false) {
                foreach ($attendanceItems as $item) {
                    try {
                        $this->processAttendance($item, $isFinal);
                    } catch (\Throwable $e) {
                        Log::warning("[Backfill] Attendance item failed: " . substr($e->getMessage(), 0, 100));
                    }
                }
            }

            $this->updateDayProgress($date->toDateString(), '✅', "tayyor", $dayNum, $totalDays);
            $successDays++;
            $this->info("Tayyor: {$date->toDateString()} — {$successDays}/{$totalDays}");
        }

        $this->report['backfill'] = [
            'total_days' => $totalDays,
            'success_days' => $successDays,
            'failed_pages' => $failedDays,
        ];

        $this->sendProgressDone('backfill', $successDays, $totalDays, $failedDays);
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

    private function fetchAllPages(string $endpoint, int $from, int $to, ?Carbon $filterDate = null): array|false
    {
        $reporter = app()->bound(ImportProgressReporter::class) ? app(ImportProgressReporter::class) : null;
        $allItems = [];
        $currentPage = 1;
        $totalPages = 1;
        $maxRetries = 3;
        $this->lastFetchError = '';
        $skippedByFilter = 0;

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
                        $pagination = $response->json()['data']['pagination'] ?? [];

                        // Diagnostika: API pagination ma'lumotlarini log qilish
                        if ($currentPage === 1) {
                            $totalCount = $pagination['totalCount'] ?? $pagination['total'] ?? 'N/A';
                            $pageCount = $pagination['pageCount'] ?? 'N/A';
                            $this->info("[API Diag] {$endpoint}: totalCount={$totalCount}, pageCount={$pageCount}, firstPageItems=" . count($data));
                            Log::info("[API Diag] {$endpoint}: totalCount={$totalCount}, pageCount={$pageCount}, firstPageItems=" . count($data), [
                                'from' => $from,
                                'to' => $to,
                                'filterDate' => $filterDate?->toDateString(),
                                'pagination' => $pagination,
                            ]);
                        }

                        // Xavfsizlik filtri: UTC timestamp fix bilan API to'g'ri ishlashi kerak,
                        // lekin ehtiyot sifatida boshqa kunlik recordlarni tashlash saqlanadi
                        if ($filterDate && $endpoint === 'student-grade-list') {
                            $beforeCount = count($data);
                            $data = array_filter($data, function ($item) use ($filterDate) {
                                return Carbon::createFromTimestamp($item['lesson_date'])->isSameDay($filterDate);
                            });
                            $skippedByFilter += $beforeCount - count($data);
                        }

                        // array_merge o'rniga push — xotira tejash (har merge da yangi massiv yaratilmaydi)
                        foreach ($data as $item) {
                            $allItems[] = $item;
                        }
                        $totalPages = $response->json()['data']['pagination']['pageCount'] ?? $totalPages;
                        $this->info("Fetched {$endpoint} page {$currentPage}/{$totalPages}");
                        if ($reporter) {
                            $reporter->updateProgress($currentPage, $totalPages);
                        }
                        $this->updateCurrentDayStatus("API {$currentPage}/{$totalPages}");
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

        if ($skippedByFilter > 0) {
            $this->warn("Filtered out {$skippedByFilter} records with wrong date during fetch");
            Log::warning("[Fetch] Filtered out {$skippedByFilter} records with wrong lesson_date for {$filterDate->toDateString()}");
        }

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

        // Xavfsizlik filtri — fetchAllPages allaqachon filtrlaganligiga qaramasdan
        // ikkinchi marta tekshiramiz (agar fetchAllPages filterDate siz chaqirilgan bo'lsa)
        $filteredItems = array_filter($gradeItems, function ($item) use ($date, &$skippedCount) {
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
            if (!$lessonDate->isSameDay($date)) {
                $skippedCount++;
                return false;
            }
            return true;
        });

        // API javobini bo'shatish — endi faqat filteredItems kerak
        unset($gradeItems);

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} records with mismatched lesson_date (expected {$date->toDateString()})");
            Log::warning("[ApplyGrades] Skipped {$skippedCount} records with lesson_date != {$date->toDateString()}");
        }

        // ===== TRANSACTION TASHQARISIDA: Studentlar va deadline-larni oldindan yuklash =====
        // Bu N+1 query muammosini hal qiladi (8685 ta SELECT o'rniga 1 ta)
        $hemisIds = array_unique(array_column(array_values($filteredItems), '_student'));
        $studentsMap = Student::whereIn('hemis_id', $hemisIds)->get()->keyBy('hemis_id');
        $deadlinesMap = Deadline::all()->keyBy('level_code');

        // ===== INSERT QATORLARINI OLDINDAN TAYYORLASH (transaction tashqarisida) =====
        // Bu xotirani tejaydi: Eloquent model yaratish + LogsActivity o'rniga oddiy array
        $insertRows = [];
        $now = Carbon::now();

        foreach ($filteredItems as $item) {
            $student = $studentsMap->get($item['_student']);
            if (!$student) continue;

            $gradeValue = $item['grade'];
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);

            $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);
            $studentMinLimit = $markingScore ? $markingScore->minimum_limit : 0;

            $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
                ($student->level_code != 16 && $gradeValue < $studentMinLimit);

            $status = $isLowGrade ? 'pending' : 'recorded';
            $reason = $isLowGrade ? 'low_grade' : null;
            $deadline = null;
            if ($isLowGrade) {
                $dl = $deadlinesMap->get($student->level_code);
                $deadline = $dl
                    ? $lessonDate->copy()->addDays($dl->deadline_days)->endOfDay()
                    : $lessonDate->copy()->addWeek()->endOfDay();
            }

            $insertRows[] = [
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
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $gradeCount = count($insertRows);
        $this->updateCurrentDayStatus("{$gradeCount} ta tayyorlandi, bazaga yozilmoqda...");

        // Xotirani bo'shatish — filteredItems va studentsMap endi kerak emas
        unset($filteredItems, $studentsMap, $deadlinesMap, $hemisIds);

        // ====================================================================
        // XAVFSIZLIK: Agar yangi yozuvlar bo'sh bo'lsa, mavjud yozuvlarni o'chirmaslik
        // API 0 ta baho qaytarsa yoki barcha studentlar topilmasa — eski baholar saqlanadi
        // ====================================================================
        if ($gradeCount === 0) {
            $this->warn("applyGrades: 0 ta yozuv tayyorlandi ({$date->toDateString()}), mavjud yozuvlar saqlanadi.");
            Log::warning("[ApplyGrades] 0 insert rows for {$date->toDateString()}, skipping delete to preserve existing data.");
            // is_final=false ni true ga O'TKAZMAYMIZ — keyingi importda API qayta tortiladi
            return;
        }

        // ====================================================================
        // 1-QADAM: Read-only — tranzaksiyadan OLDIN (lock ushlamasligi uchun)
        // ====================================================================
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
        $activeIdsBeforeDelete = StudentGrade::where('lesson_date', '>=', $dateStart)
            ->where('lesson_date', '<=', $dateEnd)
            ->pluck('id')
            ->toArray();

        // ====================================================================
        // 2-3 QADAM: Soft-delete + Bulk insert — TRANZAKSIYA ICHIDA
        // Agar insert xato bersa, soft-delete ROLLBACK qilinadi (ma'lumot yo'qolmaydi)
        // retryOnLockTimeout butun tranzaksiyani qayta urinadi
        // ====================================================================
        $this->retryOnLockTimeout(function () use ($insertRows, $dateStart, $dateEnd, $isFinal, &$softDeletedCount) {
            DB::transaction(function () use ($insertRows, $dateStart, $dateEnd, $isFinal, &$softDeletedCount) {
                $query = StudentGrade::where('lesson_date', '>=', $dateStart)
                    ->where('lesson_date', '<=', $dateEnd);

                if ($isFinal) {
                    $softDeletedCount = $query->delete();
                } else {
                    $softDeletedCount = (clone $query)->where('is_final', false)->delete();
                }

                foreach (array_chunk($insertRows, 200) as $chunk) {
                    DB::table('student_grades')->insert($chunk);
                }
            });
        });

        $this->info("Soft deleted {$softDeletedCount} old grades for {$dateStart->toDateString()}");
        Log::info("[ApplyGrades] Soft deleted {$softDeletedCount} grades for {$dateStart->toDateString()}");

        // ====================================================================
        // 4-QADAM: Retake ma'lumotlarni batch qayta qo'yish (tranzaksiyadan tashqarida)
        // N+1 loop o'rniga — batch update
        // ====================================================================
        $retakeRestored = 0;
        $restoredKeys = [];
        foreach ($retakeBackup as $key => $retake) {
            $updated = $this->retryOnLockTimeout(fn () =>
                StudentGrade::where('student_hemis_id', $retake->student_hemis_id)
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
                    ])
            );
            if ($updated) {
                $retakeRestored++;
                $restoredKeys[] = $key;
            }
        }

        // 5-QADAM: HEMIS da yo'q retake yozuvlarni batch tiklash
        $unrestored = $retakeBackup->keys()->diff($restoredKeys)->toArray();
        $undeleted = 0;
        if (!empty($unrestored)) {
            $unrestoredIds = $retakeBackup->only($unrestored)->pluck('id')->toArray();
            if (!empty($unrestoredIds)) {
                $undeleted = StudentGrade::onlyTrashed()
                    ->whereIn('id', $unrestoredIds)
                    ->update(['deleted_at' => null, 'is_final' => $isFinal]);
            }
        }

        if ($retakeRestored > 0 || $undeleted > 0) {
            $this->info("Retake grades: {$retakeRestored} restored to new records, {$undeleted} un-deleted for {$dateStart->toDateString()}");
            Log::info("[ApplyGrades] Retake: {$retakeRestored} restored, {$undeleted} un-deleted for {$dateStart->toDateString()}");
        }

        // ====================================================================
        // 6-QADAM: Lokal baholarni tiklash (tranzaksiyadan tashqarida)
        // ====================================================================
        $activeKeysAfterImport = StudentGrade::where('lesson_date', '>=', $dateStart)
            ->where('lesson_date', '<=', $dateEnd)
            ->get(['student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code', 'training_type_code'])
            ->map(fn ($g) => $g->student_hemis_id . '_' . $g->subject_id . '_' .
                Carbon::parse($g->lesson_date)->toDateString() . '_' . $g->lesson_pair_code . '_' . $g->training_type_code)
            ->flip()
            ->toArray();

        $orphanCandidates = StudentGrade::onlyTrashed()
            ->whereIn('id', $activeIdsBeforeDelete)
            ->get(['id', 'student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code', 'training_type_code']);

        $orphanIds = [];
        foreach ($orphanCandidates as $orphan) {
            $key = $orphan->student_hemis_id . '_' . $orphan->subject_id . '_' .
                Carbon::parse($orphan->lesson_date)->toDateString() . '_' . $orphan->lesson_pair_code . '_' . $orphan->training_type_code;
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

        // 7-QADAM: Xavfsizlik tozalash
        if ($isFinal) {
            $leftoverCount = StudentGrade::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd)
                ->where('is_final', false)
                ->delete();
            if ($leftoverCount > 0) {
                $this->warn("Safety cleanup: {$leftoverCount} ta is_final=false qoldiq tozalandi ({$dateStart->toDateString()})");
                Log::warning("[ApplyGrades] Safety cleanup: {$leftoverCount} is_final=false leftovers soft-deleted for {$dateStart->toDateString()}");
            }
        }

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

        $failedItems = 0;
        foreach ($attendanceItems as $item) {
            try {
                $this->processAttendance($item);
            } catch (\Throwable $e) {
                $failedItems++;
                Log::warning("[importAttendance] Item failed: " . substr($e->getMessage(), 0, 100));
            }
        }

        $this->report['attendance-list'] = [
            'total_days' => 1,
            'success_days' => $failedItems === 0 ? 1 : 0,
            'failed_pages' => $failedItems > 0 ? ["{$failedItems} ta yozuv xato"] : [],
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
    // Kunlik davomat import (barcha stsenariyalarda — A, B, C)
    // Baholar to'liq yakunlangan bo'lsa ham, yangi NB/davomat kelishi mumkin
    // =========================================================================
    private function importDayAttendance(string $dateStr, Carbon $date, bool $isFinal = true): void
    {
        $this->updateCurrentDayStatus("davomat API...");

        $from = Carbon::parse($dateStr, 'UTC')->startOfDay()->timestamp;
        $to = Carbon::parse($dateStr, 'UTC')->endOfDay()->timestamp;

        $attendanceItems = $this->fetchAllPages('attendance-list', $from, $to);
        if ($attendanceItems === false) {
            Log::warning("[ImportAttendance] {$dateStr} — API xato, davomat import qilinmadi.");
            return;
        }

        $totalItems = count($attendanceItems);
        $absentItems = 0;
        foreach ($attendanceItems as $item) {
            if (($item['absent_off'] ?? 0) > 0 || ($item['absent_on'] ?? 0) > 0) {
                $absentItems++;
            }
        }
        Log::info("[NB-DIAG] {$dateStr} — attendance API: total={$totalItems}, absent={$absentItems}");
        $this->info("  {$dateStr} — davomat API: {$totalItems} ta yozuv, {$absentItems} ta NB.");

        $count = 0;
        foreach ($attendanceItems as $item) {
            try {
                $this->processAttendance($item, $isFinal);
                $count++;
            } catch (\Throwable $e) {
                Log::warning("[ImportAttendance] {$dateStr} — item failed: " . substr($e->getMessage(), 0, 100));
            }
        }

        if ($count > 0) {
            $this->info("  {$dateStr} — {$count} ta davomat import qilindi.");
        }
    }

    // =========================================================================
    // Davomatni qayta ishlash (eski logika saqlanadi)
    // =========================================================================
    private function processAttendance($item, bool $isFinal = false)
    {
        $student = Student::where('hemis_id', $item['student']['id'])->first();

        if ($student && ($item['absent_off'] > 0 || $item['absent_on'] > 0)) {
            $this->retryOnLockTimeout(function () use ($item, $student) {
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
            });

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

        if ($existingGrade) {
            // DIAGNOSTIKA: existing grade topildi — NB yaratish skip qilinadi
            Log::info("[NB-DIAG] SKIP: student={$student->hemis_id}, date={$lessonDate->toDateString()}, pair={$item['lessonPair']['code']}, existing_reason={$existingGrade->reason}, existing_grade={$existingGrade->grade}, existing_status={$existingGrade->status}, hemis_id={$existingGrade->hemis_id}");
        }

        if (!$existingGrade) {
            Log::info("[NB-DIAG] CREATE: student={$student->hemis_id}, date={$lessonDate->toDateString()}, pair={$item['lessonPair']['code']}");
            $this->retryOnLockTimeout(function () use ($item, $student, $lessonDate, $isFinal) {
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
            });
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

    // =========================================================================
    // Lock wait timeout bo'lganda qayta urinish (3 marta, oraliq bilan)
    // MySQL Error 1205: Lock wait timeout exceeded
    // =========================================================================
    private function retryOnLockTimeout(callable $callback, int $maxRetries = 3): mixed
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $callback();
            } catch (\Illuminate\Database\QueryException $e) {
                // MySQL error 1205 = Lock wait timeout exceeded
                if ($e->getCode() == 1205 || $e->getCode() == 1213 || $e->getCode() == '40001'
                    || str_contains($e->getMessage(), 'Lock wait timeout')
                    || str_contains($e->getMessage(), 'Deadlock found')) {
                    $waitSeconds = $attempt * 2; // 2s, 4s, 6s
                    $this->warn("Lock/Deadlock xato (urinish {$attempt}/{$maxRetries}), {$waitSeconds}s kutilmoqda...");
                    Log::warning("[ImportGrades] Lock/Deadlock attempt {$attempt}/{$maxRetries}, waiting {$waitSeconds}s");
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
    // Progress tracking — Telegram live xabar + Console banner
    // =========================================================================
    private function sendProgressStart(string $mode, int $totalDays, string $dateRange): void
    {
        $this->importStartTime = microtime(true);
        $this->dayStatuses = [];

        // Console
        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║  " . strtoupper($mode) . " IMPORT BOSHLANDI (" . Carbon::now()->format('H:i:s') . ")");
        $this->info("║  {$totalDays} kun: {$dateRange}");
        $this->info("╚══════════════════════════════════════════════════════════╝");

        // Telegram — yangi xabar yuborish va ID saqlash
        if ($this->option('silent')) return;
        $chatId = config('services.telegram.chat_id');
        if (!$chatId) return;

        $bar = $this->makeProgressBar(0, $totalDays);
        $msg = "⏳ " . strtoupper($mode) . " import boshlandi\n"
             . Carbon::now()->format('d.m.Y H:i') . "\n\n"
             . "{$bar} 0/{$totalDays}\n\n"
             . $dateRange;

        $this->telegramProgressMsgId = app(TelegramService::class)->sendAndGetId($chatId, $msg);
    }

    private function updateDayProgress(string $key, string $icon, string $details, int $current, int $total): void
    {
        $this->dayStatuses[$key] = "{$icon} {$details}";

        // Nightly wrapper ga progress yuborish (--silent rejimda)
        $this->reportToNightly($current, $total);

        // Faqat Telegram yangilash — console allaqachon info() orqali yozilmoqda
        $chatId = config('services.telegram.chat_id');
        if (!$this->telegramProgressMsgId || !$chatId) return;

        // Telegram rate limit: ⏳ uchun minimum 5 sekund oraliq, ✅/❌ har doim yuboriladi
        $now = microtime(true);
        if ($icon === '⏳' && ($now - $this->lastTelegramUpdate) < 5) return;
        $this->lastTelegramUpdate = $now;

        $elapsed = round(($now - $this->importStartTime) / 60, 1);
        $bar = $this->makeProgressBar($current, $total);
        $mode = strtoupper($this->option('mode'));

        $lines = [];
        foreach ($this->dayStatuses as $d => $s) {
            $label = (strlen($d) === 10 && ($d[4] ?? '') === '-') ? substr($d, 5) : $d;
            $lines[] = "{$label} {$s}";
        }

        $msg = "⏳ {$mode} import jarayonda...\n"
             . Carbon::now()->format('d.m.Y H:i') . "\n\n"
             . "{$bar} {$current}/{$total}\n\n"
             . implode("\n", $lines) . "\n\n"
             . "⏱ {$elapsed} daq";

        app(TelegramService::class)->editMessage($chatId, $this->telegramProgressMsgId, $msg);
    }

    /**
     * Joriy kun uchun sub-step statusini yangilash (fetchAllPages/applyGrades ichidan)
     */
    private function updateCurrentDayStatus(string $details): void
    {
        if ($this->currentDayKey && $this->currentDayTotal > 0) {
            $this->updateDayProgress($this->currentDayKey, '⏳', $details, $this->currentDayNum, $this->currentDayTotal);
        }
    }

    private function sendProgressDone(string $mode, int $successDays, int $totalDays, array $failedDays = []): void
    {
        $elapsed = $this->importStartTime
            ? round((microtime(true) - $this->importStartTime) / 60, 1)
            : 0;
        $hasErrors = !empty($failedDays);

        // Nightly wrapper ga yakuniy progress yuborish
        $this->reportToNightly($successDays, $totalDays, true);

        // Console
        $this->newLine();
        $status = $hasErrors ? "XATOLAR BOR" : "MUVAFFAQIYATLI";
        $this->info("╔══════════════════════════════════════════════════════════╗");
        $this->info("║  " . strtoupper($mode) . " IMPORT TUGADI — {$status}");
        $this->info("║  Natija: {$successDays}/{$totalDays} kun muvaffaqiyatli");
        $this->info("║  Vaqt: {$elapsed} daqiqa (" . Carbon::now()->format('H:i:s') . ")");
        if ($hasErrors) {
            foreach ($failedDays as $f) {
                $this->error("║  xato: {$f}");
            }
        }
        $this->info("╚══════════════════════════════════════════════════════════╝");
        $this->newLine();

        // Telegram — yakuniy xabarni yangilash
        $chatId = config('services.telegram.chat_id');
        if (!$this->telegramProgressMsgId || !$chatId) return;

        $emoji = $hasErrors ? "⚠️" : "✅";
        $bar = $this->makeProgressBar($successDays, $totalDays);

        $lines = [];
        foreach ($this->dayStatuses as $d => $s) {
            $label = (strlen($d) === 10 && ($d[4] ?? '') === '-') ? substr($d, 5) : $d;
            $lines[] = "{$label} {$s}";
        }

        $msg = "{$emoji} " . strtoupper($mode) . " import tugadi\n"
             . Carbon::now()->format('d.m.Y H:i') . "\n\n"
             . "{$bar} {$successDays}/{$totalDays}\n\n"
             . implode("\n", $lines) . "\n\n"
             . "📊 {$successDays}/{$totalDays} muvaffaqiyatli\n"
             . "⏱ {$elapsed} daqiqa";

        if ($hasErrors) {
            $msg .= "\n\n❌ Xatolar:\n" . implode("\n", array_map(fn($f) => "• {$f}", $failedDays));
        }

        app(TelegramService::class)->editMessage($chatId, $this->telegramProgressMsgId, $msg);
    }

    private function makeProgressBar(int $current, int $total, int $width = 20): string
    {
        if ($total <= 0) return '[' . str_repeat('░', $width) . ']';
        $filled = min($width, (int) round($current / $total * $width));
        return '[' . str_repeat('█', $filled) . str_repeat('░', $width - $filled) . ']';
    }

    /**
     * Nightly wrapper ga progress yuborish (nightly:run ichidan chaqirilganda)
     */
    private function reportToNightly(int $current, int $total, bool $isDone = false): void
    {
        if (!app()->bound('nightly.progress')) return;

        $bar = $this->makeProgressBar($current, $total);
        $lines = ["{$bar} {$current}/{$total}"];

        foreach ($this->dayStatuses as $d => $s) {
            $label = (strlen($d) === 10 && ($d[4] ?? '') === '-') ? substr($d, 5) : $d;
            $lines[] = "{$label} {$s}";
        }

        if ($isDone) {
            $lines[] = "📊 {$current}/{$total} muvaffaqiyatli";
        }

        $callback = app('nightly.progress');
        $callback(implode("\n", $lines));
    }

    // =========================================================================
    // Kunlik yig'ma Telegram xabar (live import uchun)
    // Kunda 1 ta xabar — har 30 daqiqalik import natijasi qo'shib boriladi
    // =========================================================================
    private function sendDailyLiveReport(int $gradeCount, int $nbCount, ?string $gradeError, ?string $attendanceError, float $elapsed): void
    {
        $chatId = config('services.telegram.chat_id');
        if (!$chatId) return;

        $telegram = app(TelegramService::class);
        $stateFile = storage_path('app/telegram_live_daily.json');
        $today = Carbon::today()->toDateString();
        $time = Carbon::now()->format('H:i');

        // Bugungi xabar holatini o'qish
        $state = null;
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            if (($state['date'] ?? null) !== $today) {
                $state = null; // boshqa kun — yangi xabar
            }
        }

        // Yangi qator tayyorlash
        $hasError = $gradeError || $attendanceError;
        $icon = $hasError ? '⚠️' : '✅';
        $line = "{$time} {$icon}";
        if ($gradeError) {
            $line .= " baholar: ❌ {$gradeError}";
        } else {
            $line .= " baholar: {$gradeCount}";
        }
        if ($attendanceError) {
            $line .= ", NB: ❌ {$attendanceError}";
        } else {
            $line .= ", NB: {$nbCount}";
        }
        $line .= " ({$elapsed} daq)";

        if ($state && !empty($state['message_id'])) {
            // Mavjud xabarga qo'shish
            $state['lines'][] = $line;
            $msg = $this->buildDailyMessage($today, $state['lines']);
            $telegram->editMessage($chatId, $state['message_id'], $msg);
        } else {
            // Yangi xabar yuborish
            $state = ['date' => $today, 'lines' => [$line]];
            $msg = $this->buildDailyMessage($today, $state['lines']);
            $msgId = $telegram->sendAndGetId($chatId, $msg);
            $state['message_id'] = $msgId;
        }

        // Holatni saqlash
        try {
            file_put_contents($stateFile, json_encode($state));
        } catch (\Throwable $e) {
            Log::warning("[LiveImport] telegram_live_daily.json yozishda xato: {$e->getMessage()}");
            $this->warn("State fayl yozishda xato (hisobot davom etadi): {$e->getMessage()}");
        }

        // Console uchun ham chiqarish
        $this->info($line);
    }

    private function buildDailyMessage(string $date, array $lines): string
    {
        $formatted = Carbon::parse($date)->format('d.m.Y');
        $msg = "📊 Live import — {$formatted}\n\n";
        $msg .= implode("\n", $lines);
        return $msg;
    }

    private function sendTelegramReport()
    {
        if ($this->option('silent')) return;
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

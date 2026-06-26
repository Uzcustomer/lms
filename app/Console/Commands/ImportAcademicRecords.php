<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ImportAcademicRecords extends Command
{
    protected $signature = 'import:academic-records';
    protected $description = 'Import academic records from HEMIS API';

    public function handle(TelegramService $telegram, HemisService $hemisService)
    {
        // Parallel ishga tushishni oldini olish
        if (Cache::get('academic_import_lock')) {
            $this->warn('Import allaqachon ketayapti. Qayta ishga tushirilmadi.');
            $telegram->notify("⚠️ Akademik qaydlar importi allaqachon ketayapti — qayta bosmaslik kerak!");
            return;
        }
        Cache::put('academic_import_lock', true, 3600);

        $this->info('Fetching academic records from HEMIS API...');
        $startTime = microtime(true);
        $skippedCount = 0;
        $lastTelegramPct = -1;

        Cache::put('academic_import_progress', [
            'status'     => 'running',
            'page'       => 0,
            'pages'      => 1,
            'imported'   => 0,
            'percent'    => 0,
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        $telegram->notify("🟢 Akademik qaydlar importi boshlandi");

        try {
            $totalImported = $hemisService->importAcademicRecords(
                function ($page, $totalPages, $imported, $totalCount) use (&$skippedCount, &$lastTelegramPct, $telegram) {
                    $processed = $page * 200;
                    $skippedCount = max(0, $processed - $imported);
                    $percent = round(($page / $totalPages) * 100, 1);

                    $this->output->write("\r  Sahifa: {$page}/{$totalPages} | Yangi: {$imported} | {$percent}%");

                    Cache::put('academic_import_progress', [
                        'status'     => 'running',
                        'page'       => $page,
                        'pages'      => $totalPages,
                        'imported'   => $imported,
                        'percent'    => $percent,
                        'started_at' => now()->toDateTimeString(),
                    ], 3600);

                    // Har 25% da Telegram xabari
                    $milestone = (int)($percent / 25) * 25;
                    if ($milestone > $lastTelegramPct && $milestone > 0 && $milestone < 100) {
                        $telegram->notify("📊 Akademik qaydlar import: {$milestone}% ({$page}/{$totalPages} sahifa, yangi: {$imported} ta)");
                        $lastTelegramPct = $milestone;
                    }
                }
            );
        } finally {
            Cache::forget('academic_import_lock');
        }

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Yangi/o'zgargan: {$totalImported} ta, O'tkazib: {$skippedCount} ta, Vaqt: {$duration} daqiqa");

        Cache::put('academic_import_progress', [
            'status'      => 'done',
            'imported'    => $totalImported,
            'skipped'     => $skippedCount,
            'percent'     => 100,
            'duration'    => $duration,
            'finished_at' => now()->toDateTimeString(),
        ], 3600);

        $telegram->notify("✅ Akademik qaydlar importi tugadi!\nYangi/o'zgargan: {$totalImported} ta\nO'tkazib: {$skippedCount} ta\nVaqt: {$duration} daqiqa");

        $this->info("Talabalarning biriktirilgan fanlari (student-subjects) import qilinmoqda...");
        $this->call('import:student-subjects');
    }
}

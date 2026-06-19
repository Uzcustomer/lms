<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportAcademicRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:academic-records';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import academic records from HEMIS API';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram, HemisService $hemisService)
    {
        $telegram->notify("🟢 Akademik qaydlar importi boshlandi");
        $this->info('Fetching academic records from HEMIS API...');
        $this->info('Bu jarayon biroz vaqt olishi mumkin (359,000+ yozuv)...');

        $startTime = microtime(true);
        $skippedCount = 0;

        \Illuminate\Support\Facades\Cache::put('academic_import_progress', [
            'status'   => 'running',
            'page'     => 0,
            'pages'    => 1,
            'imported' => 0,
            'percent'  => 0,
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        $totalImported = $hemisService->importAcademicRecords(function ($page, $totalPages, $imported, $totalCount) use (&$skippedCount) {
            $processed = $page * 200;
            $skippedCount = max(0, $processed - $imported);
            $percent = round(($page / $totalPages) * 100, 1);
            $this->output->write("\r  Sahifa: {$page}/{$totalPages} | Yangi/o'zgargan: {$imported} | O'tkazib: {$skippedCount} | {$percent}%");
            \Illuminate\Support\Facades\Cache::put('academic_import_progress', [
                'status'   => 'running',
                'page'     => $page,
                'pages'    => $totalPages,
                'imported' => $imported,
                'percent'  => $percent,
                'started_at' => \Illuminate\Support\Facades\Cache::get('academic_import_progress.started_at', now()->toDateTimeString()),
            ], 3600);
        });

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Yangi/o'zgargan: {$totalImported} ta, O'tkazib yuborildi: {$skippedCount} ta, Vaqt: {$duration} daqiqa");
        $telegram->notify("✅ Akademik qaydlar importi tugadi. Yangi/o'zgargan: {$totalImported} ta, Vaqt: {$duration} daqiqa");
        \Illuminate\Support\Facades\Cache::put('academic_import_progress', [
            'status'   => 'done',
            'page'     => null,
            'pages'    => null,
            'imported' => $totalImported,
            'skipped'  => $skippedCount,
            'percent'  => 100,
            'duration' => $duration,
            'finished_at' => now()->toDateTimeString(),
        ], 3600);

        // Talabaga biriktirilgan fanlarni ham yangilash (debt hisobi uchun)
        $this->info('Talabalarning biriktirilgan fanlari (student-subjects) import qilinmoqda...');
        $this->call('import:student-subjects');
    }
}

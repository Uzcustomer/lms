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

        $totalImported = $hemisService->importAcademicRecords(function ($page, $totalPages, $imported, $totalCount) use (&$skippedCount) {
            $processed = $page * 200; // ~200 yozuv har sahifada
            $skippedCount = max(0, $processed - $imported);
            $percent = round(($page / $totalPages) * 100, 1);
            $this->output->write("\r  Sahifa: {$page}/{$totalPages} | Yangi/o'zgargan: {$imported} | O'tkazib: {$skippedCount} | {$percent}%");
        });

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Yangi/o'zgargan: {$totalImported} ta, O'tkazib yuborildi: {$skippedCount} ta, Vaqt: {$duration} daqiqa");
        $telegram->notify("✅ Akademik qaydlar importi tugadi. Yangi/o'zgargan: {$totalImported} ta, Vaqt: {$duration} daqiqa");

        // Talabaga biriktirilgan fanlarni ham yangilash (debt hisobi uchun)
        $this->info('Talabalarning biriktirilgan fanlari (student-subjects) import qilinmoqda...');
        $this->call('import:student-subjects');
    }
}

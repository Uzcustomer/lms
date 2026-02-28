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

        $totalImported = $hemisService->importAcademicRecords(function ($page, $totalPages, $imported, $totalCount) {
            $percent = round(($page / $totalPages) * 100, 1);
            $this->output->write("\r  Sahifa: {$page}/{$totalPages} | Import: {$imported}/{$totalCount} | {$percent}%");
        });

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Jami: {$totalImported} ta yozuv, Vaqt: {$duration} daqiqa");
        $telegram->notify("✅ Akademik qaydlar importi tugadi. Jami: {$totalImported} ta, Vaqt: {$duration} daqiqa");
    }
}

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

        $totalImported = $hemisService->importAcademicRecords();

        $telegram->notify("✅ Akademik qaydlar importi tugadi. Jami: {$totalImported} ta");
        $this->info("Academic records import completed. Total imported: {$totalImported}");
    }
}

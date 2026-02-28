<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportContracts extends Command
{
    protected $signature = 'contracts:import';

    protected $description = 'Import contracts from HEMIS API to local database';

    public function handle(HemisService $hemisService, TelegramService $telegram)
    {
        $telegram->notify("🟢 Kontraktlar importi boshlandi");
        $this->info('Starting contract import from HEMIS...');

        try {
            $count = $hemisService->importContracts(function ($page, $imported, $total) {
                $this->info("  Sahifa {$page} — {$imported}/{$total} ta import qilindi");
            });

            $this->info("Contract import completed. Total: {$count}");
            $telegram->notify("✅ Kontraktlar importi tugadi. Jami: {$count} ta");
        } catch (\Throwable $e) {
            $this->error('Import error: ' . $e->getMessage());
            $telegram->notify("❌ Kontraktlar importida xatolik: " . $e->getMessage());
        }
    }
}

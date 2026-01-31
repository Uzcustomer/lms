<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportStudentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:import';

    /**
     * The console command description.
     *
     * @var string
     */


    protected $description = 'Import students from HEMIS';

    /**
     * Execute the console command.
     */
    public function handle(HemisService $hemisService, TelegramService $telegram)
    {
        $telegram->notify("🟢 Talabalar importi boshlandi");
        $this->info('Starting student import...');

        try {
            $count = $hemisService->importStudents();
            $telegram->notify("✅ Talabalar importi tugadi. Jami: {$count} ta");
        } catch (\Throwable $e) {
            $telegram->notify("❌ Talabalar importida xatolik: " . $e->getMessage());
        }

        $this->info('Student import completed.');
    }
}

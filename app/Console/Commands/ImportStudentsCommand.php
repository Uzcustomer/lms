<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
        $lock = Cache::lock('import:students', 3600);

        if (!$lock->get()) {
            $telegram->notify("⚠️ Talabalar importi allaqachon ishlayapti");
            $this->warn('Import already running, skipping...');
            return 1;
        }

        try {
            $telegram->notify("🟢 Talabalar importi boshlandi");
            $this->info('Starting student import...');

            $count = $hemisService->importStudents();
            $telegram->notify("✅ Talabalar importi tugadi. Jami: {$count} ta");

            $this->info('Student import completed.');
        } catch (\Throwable $e) {
            $telegram->notify("❌ Talabalar importida xatolik: " . $e->getMessage());
        } finally {
            $lock->release();
        }
    }
}

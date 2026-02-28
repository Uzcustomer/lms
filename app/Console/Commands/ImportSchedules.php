<?php

namespace App\Console\Commands;

use App\Services\ScheduleImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportSchedules extends Command
{
    protected $signature = 'import:schedules {--silent : Telegram xabar yubormaslik}';

    protected $description = 'Import schedules from HEMIS API by current education year';

    public function handle(ScheduleImportService $service): int
    {
        $this->info('Jadval importi boshlanmoqda (joriy o\'quv yili bo\'yicha)...');

        try {
            $service->importByEducationYear(fn($msg) => $this->line($msg), (bool)$this->option('silent'));
            $this->info('Jadval importi muvaffaqiyatli yakunlandi.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $this->error('Xatolik: ' . $errorMsg);
            Log::channel('import_schedule')->error("Jadval import exception: {$errorMsg}", [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);
            return self::FAILURE;
        }
    }
}

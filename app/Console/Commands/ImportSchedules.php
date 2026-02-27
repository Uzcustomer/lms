<?php

namespace App\Console\Commands;

use App\Services\ScheduleImportService;
use Illuminate\Console\Command;

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
            $this->error('Xatolik: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

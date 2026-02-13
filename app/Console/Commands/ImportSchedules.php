<?php

namespace App\Console\Commands;

use App\Services\ScheduleImportService;
use Illuminate\Console\Command;

class ImportSchedules extends Command
{
    protected $signature = 'import:schedules {--from-page=1 : Qaysi sahifadan boshlash (davom ettirish uchun)}';

    protected $description = 'Import schedules from HEMIS API by current education year';

    public function handle(ScheduleImportService $service): int
    {
        $fromPage = (int) $this->option('from-page');
        $this->info('Jadval importi boshlanmoqda (joriy o\'quv yili bo\'yicha)...');

        if ($fromPage > 1) {
            $this->info("Sahifa {$fromPage} dan davom ettirilmoqda...");
        }

        try {
            $service->importByEducationYear(fn($msg) => $this->line($msg), $fromPage);
            $this->info('Jadval importi muvaffaqiyatli yakunlandi.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Xatolik: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

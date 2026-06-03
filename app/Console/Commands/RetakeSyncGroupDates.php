<?php

namespace App\Console\Commands;

use App\Models\RetakeApplicationWindow;
use App\Services\Retake\RetakeWindowService;
use Illuminate\Console\Command;

/**
 * Mavjud (eski) qayta o'qish guruhlarini ularning qabul oynalari sanalariga
 * sinxronlash. Avval propagatsiya ishlamagan paytda override qilingan oynalar
 * uchun bir martalik tuzatuv.
 *
 *   php artisan retake:sync-group-dates
 *   php artisan retake:sync-group-dates --window=12
 */
class RetakeSyncGroupDates extends Command
{
    protected $signature = 'retake:sync-group-dates
                            {--window= : Faqat shu oyna ID uchun}';

    protected $description = "Mavjud o'qish guruhlarini qabul oynalari sanalariga sinxronlash (eski holatlar uchun)";

    public function handle(RetakeWindowService $service): int
    {
        $windowQuery = RetakeApplicationWindow::query();
        if ($this->option('window')) {
            $windowQuery->where('id', (int) $this->option('window'));
        }
        $windowIds = $windowQuery->pluck('id')->all();

        if (empty($windowIds)) {
            $this->warn('Mos oyna topilmadi.');
            return self::SUCCESS;
        }

        $this->info(count($windowIds) . ' ta oyna qayta ishlanadi...');

        $totalChanged = 0;
        foreach (array_chunk($windowIds, 100) as $chunk) {
            $totalChanged += $service->extendLinkedGroupEndDates($chunk);
        }

        $this->info("Sinxronlangan guruhlar: {$totalChanged}");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\MonitoringDetector;
use App\Models\MonitoringRun;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Console\Command;

/**
 * Monitoring detektorlarini ishga tushirish (TZ 5-bo'lim). Har kuni tunda
 * HEMIS importlaridan keyin rejalashtiruvchi chaqiradi.
 */
class RunMonitoringDetectors extends Command
{
    protected $signature = 'monitoring:run
                            {code?* : Detektor kodlari (masalan D-2 Q-1); bo\'sh bo\'lsa --all kerak}
                            {--all : Yoqilgan va amalga oshirilgan barcha detektorlar}';

    protected $description = 'Monitoring detektorlarini ishga tushiradi va istisnolarni yangilaydi';

    public function handle(DetectorRunner $runner): int
    {
        $codes = (array) $this->argument('code');

        if ($codes === [] && !$this->option('all')) {
            $this->error('Detektor kodini bering yoki --all bayrog\'ini qo\'ying.');

            return self::INVALID;
        }

        $detectors = $codes !== []
            ? MonitoringDetector::whereIn('code', $codes)->get()
            : MonitoringDetector::runnable()->orderBy('code')->get();

        if ($detectors->isEmpty()) {
            $this->warn('Ishga tushiriladigan detektor topilmadi.');

            return self::SUCCESS;
        }

        $failed = 0;
        foreach ($detectors as $detector) {
            if (!$detector->isImplemented()) {
                $this->warn("{$detector->code}: hali amalga oshirilmagan — o'tkazib yuborildi.");
                continue;
            }

            $run = $runner->run($detector);
            if ($run->status === MonitoringRun::STATUS_OK) {
                $this->info(sprintf(
                    '%s: topildi %d, yangi %d, yangilandi %d, yopildi %d, qayta ochildi %d',
                    $detector->code, $run->found_count, $run->opened_count, $run->updated_count,
                    $run->closed_count, $run->reopened_count
                ));
            } else {
                $failed++;
                $this->error("{$detector->code}: xato — {$run->error_message}");
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

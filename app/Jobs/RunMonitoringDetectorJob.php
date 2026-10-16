<?php

namespace App\Jobs;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Paneldagi «Hozir ishga tushir» tugmasi uchun (navbat orqali, sahifa kutib qolmaydi).
 */
class RunMonitoringDetectorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        public int $detectorId,
        public ?string $actorType = null,
        public ?int $actorId = null,
    ) {
    }

    public function handle(DetectorRunner $runner): void
    {
        $detector = MonitoringDetector::find($this->detectorId);
        if ($detector === null) {
            return;
        }

        $actor = ($this->actorType && $this->actorId && class_exists($this->actorType))
            ? $this->actorType::find($this->actorId)
            : null;

        $runner->run($detector, $actor);
    }
}

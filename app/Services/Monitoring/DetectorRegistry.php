<?php

namespace App\Services\Monitoring;

use App\Services\Monitoring\Contracts\Detector;
use InvalidArgumentException;

/**
 * Detektor kodi → klass (config/monitoring.php → detectors).
 */
class DetectorRegistry
{
    public function has(string $code): bool
    {
        return isset(config('monitoring.detectors', [])[$code]);
    }

    public function make(string $code): Detector
    {
        $class = config('monitoring.detectors', [])[$code] ?? null;
        if ($class === null) {
            throw new InvalidArgumentException("{$code} detektori hali amalga oshirilmagan.");
        }

        $detector = app($class);
        if (!$detector instanceof Detector) {
            throw new InvalidArgumentException("{$class} Detector interfeysini amalga oshirmaydi.");
        }

        return $detector;
    }
}

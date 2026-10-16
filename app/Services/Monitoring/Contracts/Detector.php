<?php

namespace App\Services\Monitoring\Contracts;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\DetectorResult;

/**
 * Har bir detektor — ikki to'plamning farqi yoki ikki sananing farqi (TZ 1-bo'lim).
 * Detektor faqat topilgan holatlarni qaytaradi; istisno ochish, yangilash va
 * avtomatik yopishni DetectorRunner bajaradi.
 */
interface Detector
{
    /**
     * @param  array  $params  monitoring_detectors.params (paneldan sozlanadi)
     */
    public function run(array $params, MonitoringDetector $detector): DetectorResult;
}

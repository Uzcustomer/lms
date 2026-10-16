<?php

use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * docs/detectors.yaml dagi 54 detektorni monitoring_detectors ga yozadi.
 * Keyinchalik katalog yangilansa: php artisan monitoring:sync-catalog
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            app(MonitoringCatalog::class)->sync();
        } catch (\Throwable $e) {
            // Katalog fayli bo'lmasa migratsiya to'xtamaydi — keyin buyruq bilan yuklanadi.
            Log::warning('Monitoring katalogi yuklanmadi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Ma'lumot migratsiyasi — jadvallar oldingi migratsiyada o'chiriladi.
    }
};

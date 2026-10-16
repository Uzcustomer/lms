<?php

use App\Models\MonitoringDetector;
use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Buyurtmachi qarorlari (16.10.2026):
 *  - Q-1: HEMIS'ga yakuniy natijalar semestr oxirida kiritiladi — standart tekshiruv vedomost bo'yicha
 *    (check_against = vedomost). Katalog sinxroni yangi kalitni qo'shadi.
 *  - D-1 «xavf zonasi» (30–74 soat) yoqiladi; hali ishlamagan bo'lsa — ogohlantirish rejimida.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            app(MonitoringCatalog::class)->sync();
        } catch (\Throwable $e) {
            Log::warning('Monitoring katalogi yuklanmadi: ' . $e->getMessage());
        }

        $q1 = MonitoringDetector::where('code', 'Q-1')->first();
        if ($q1 !== null && !isset($q1->params['check_against'])) {
            $q1->update(['params' => array_merge($q1->params ?? [], ['check_against' => 'vedomost'])]);
        }

        $d1 = MonitoringDetector::where('code', 'D-1')->first();
        if ($d1 !== null && $d1->last_run_at === null && !$d1->enabled) {
            $d1->update(['enabled' => true, 'warning_mode' => true]);
        }
    }

    public function down(): void
    {
        // Sozlama qarorlari — paneldan o'zgartiriladi.
    }
};

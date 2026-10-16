<?php

use App\Models\MonitoringDetector;
use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Buyurtmachi qarorlari (16.10.2026) — katalog avvalroq (eski docs/detectors.yaml bilan)
 * yuklangan serverlar uchun. Katalog sinxroni paneldagi qiymatlarni ataylab o'zgartirmaydi,
 * shuning uchun qarorlar shu yerda qo'llanadi:
 *  - yangi parametr kalitlari (E-1 accommodation_*) katalogdan qo'shiladi;
 *  - Q-1: qayta topshirishlar ham tekshiriladi (include_resits = true);
 *  - staging yo'q: hali bir marta ham ishlamagan pilot detektorlar ogohlantirish rejimida boshlanadi.
 * Yangi o'rnatishda (katalog yangi yaml bilan yuklangan) hech narsa o'zgarmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            app(MonitoringCatalog::class)->sync();
        } catch (\Throwable $e) {
            Log::warning('Monitoring katalogi yuklanmadi: ' . $e->getMessage());

            return;
        }

        $q1 = MonitoringDetector::where('code', 'Q-1')->first();
        if ($q1 !== null && !($q1->params['include_resits'] ?? false)) {
            $q1->update(['params' => array_merge($q1->params ?? [], ['include_resits' => true])]);
        }

        MonitoringDetector::where('pilot', true)
            ->whereNull('last_run_at')
            ->where('warning_mode', false)
            ->get()
            ->each(fn (MonitoringDetector $d) => $d->update(['warning_mode' => true]));
    }

    public function down(): void
    {
        // Sozlama qarorlari — orqaga qaytarilmaydi (paneldan o'zgartiriladi).
    }
};

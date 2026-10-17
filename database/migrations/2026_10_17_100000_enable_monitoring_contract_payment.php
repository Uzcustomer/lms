<?php

use App\Models\MonitoringDetector;
use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Buyurtmachi qarori (17.10.2026): kontrakt to'lovi muddatlari Sozlamalarda kurs kesimida kiritiladi,
 * F-6 shu muddatlar bo'yicha tekshiradi. Katalog sinxroni F-6 parametrlarini qo'shadi; hali ishlamagan
 * F-6 ogohlantirish rejimida yoqiladi.
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

        $f6 = MonitoringDetector::where('code', 'F-6')->first();
        if ($f6 !== null && $f6->last_run_at === null) {
            $f6->update(['enabled' => true, 'warning_mode' => true]);
        }
    }

    public function down(): void
    {
        // Sozlama qarori — paneldan o'zgartiriladi.
    }
};

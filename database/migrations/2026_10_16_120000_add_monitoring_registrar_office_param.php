<?php

use App\Services\Monitoring\MonitoringCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * A-5, E-1, E-7: istisno talabaning kursiga «Registrator bo'linmalari»da biriktirilgan xodimga boradi
 * (registrar_office parametri). Katalog avval yuklangan serverlarda yangi kalit shu sinxron bilan qo'shiladi;
 * paneldagi qiymatlar o'zgarmaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            app(MonitoringCatalog::class)->sync();
        } catch (\Throwable $e) {
            // Katalog o'qilmasa kalit qo'shilmaydi: istisnolar avvalgidek standart mas'ulga boradi.
            Log::warning('Monitoring katalogi yuklanmadi: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Sozlama — paneldan o'zgartiriladi.
    }
};

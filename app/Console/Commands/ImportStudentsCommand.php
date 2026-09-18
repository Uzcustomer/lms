<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportStudentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:import';

    /**
     * The console command description.
     *
     * @var string
     */


    protected $description = 'Import students from HEMIS';

    /**
     * Execute the console command.
     */
    public function handle(HemisService $hemisService, TelegramService $telegram)
    {
        $telegram->notify("🟢 Talabalar importi boshlandi");
        $this->info('Starting student import...');

        try {
            // Har sahifadan keyin jarayonni ko'rsatamiz — import bir necha
            // daqiqa davom etadi va aks holda qotib qolgandek ko'rinadi.
            $count = $hemisService->importStudents(function (int $page, int $pageCount, int $imported, int $total) {
                $this->line(sprintf(
                    '  Sahifa %d/%d — %s ta talaba ishlandi%s',
                    $page,
                    $pageCount,
                    number_format($imported, 0, '.', ' '),
                    $total > 0 ? ' (HEMIS da jami ' . number_format($total, 0, '.', ' ') . ')' : ''
                ));
            });
            $this->info("Tugadi: {$count} ta talaba yangilandi.");
            $telegram->notify("✅ Talabalar importi tugadi. Jami: {$count} ta");
        } catch (\Throwable $e) {
            // Import to'xtaganda hech bir talaba "chetlashgan" deb belgilanmaydi —
            // HemisService faqat to'liq ro'yxat olinganda shu qadamga o'tadi.
            $this->error('Xatolik: ' . $e->getMessage());
            $this->line('Import yarim qoldi; o\'chirilgan yoki chetlashtirilgan talaba yo\'q. Qayta ishga tushiring.');
            $telegram->notify("❌ Talabalar importida xatolik: " . $e->getMessage());
        }

        $this->info('Student import completed.');
    }
}

<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class ImportStudentSubjects extends Command
{
    protected $signature = 'import:student-subjects';

    protected $description = "Har bir talabaga HEMIS da biriktirilgan fanlarni import qilish (student-subject-list)";

    public function handle(TelegramService $telegram, HemisService $hemisService)
    {
        $telegram->notify("🟢 Talaba fanlar importi boshlandi");
        $this->info('HEMIS dan talabalarning biriktirilgan fanlari import qilinmoqda...');

        $startTime = microtime(true);

        $totalImported = $hemisService->importStudentSubjects(function ($done, $total, $imported) {
            $percent = round(($done / $total) * 100, 1);
            $this->output->write("\r  Talaba: {$done}/{$total} | Yozuv: {$imported} | {$percent}%");
        });

        $duration = round((microtime(true) - $startTime) / 60, 1);

        $this->newLine();
        $this->info("Import tugadi! Jami: {$totalImported} ta yozuv, Vaqt: {$duration} daqiqa");
        $telegram->notify("✅ Talaba fanlar importi tugadi. Jami: {$totalImported} ta, Vaqt: {$duration} daqiqa");
    }
}

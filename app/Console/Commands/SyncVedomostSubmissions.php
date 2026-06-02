<?php

namespace App\Console\Commands;

use App\Services\VedomostSubmissionService;
use Illuminate\Console\Command;

class SyncVedomostSubmissions extends Command
{
    protected $signature = 'vedomost:sync';

    protected $description = "Joriy semestr bo'yicha vedomost topshirish yozuvlarini generatsiya/yangilash";

    public function handle(VedomostSubmissionService $service): int
    {
        $this->info('Vedomost yozuvlari yangilanmoqda...');
        $count = $service->sync();
        $this->info("Tugadi. {$count} ta yozuv generatsiya/yangilandi.");

        return self::SUCCESS;
    }
}

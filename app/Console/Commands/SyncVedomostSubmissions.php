<?php

namespace App\Console\Commands;

use App\Services\VedomostSubmissionService;
use Illuminate\Console\Command;

class SyncVedomostSubmissions extends Command
{
    protected $signature = 'vedomost:sync {--dry-run : Hech narsa yozmasdan/o\'chirmasdan, faqat nima o\'chishini ko\'rsatadi}';

    protected $description = "Joriy semestr bo'yicha vedomost topshirish yozuvlarini generatsiya/yangilash";

    public function handle(VedomostSubmissionService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('SINOV REJIMI (--dry-run): bazaga hech narsa yozilmaydi/o\'chirilmaydi.');
        }

        $this->info('Vedomost yozuvlari yangilanmoqda...');
        $count = $service->sync($dryRun);
        $this->info("Generatsiya/yangilanadigan yozuvlar: {$count} ta.");

        $candidates = $service->lastPruneCandidates;
        $this->newLine();
        $this->info('O\'chiriladigan (eskirgan) qatorlar: ' . count($candidates) . ' ta'
            . ($dryRun ? '' : ' — o\'chirildi.'));

        if (!empty($candidates)) {
            $this->table(
                ['ID', 'Shakl', 'Guruh', 'Yo\'nalish', 'Fan', 'Semestr'],
                array_map(fn($r) => [
                    $r->id,
                    $r->form_type,
                    $r->group_name,
                    $r->specialty_name,
                    $r->subject_name,
                    $r->semester_code,
                ], $candidates)
            );
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Sinov tugadi — hech narsa o\'zgartirilmadi. Haqiqiy ishga tushirish: php artisan vedomost:sync');
        }

        return self::SUCCESS;
    }
}

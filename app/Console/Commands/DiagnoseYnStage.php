<?php

namespace App\Console\Commands;

use App\Services\YnAttemptStatusService;
use App\Services\YnStageService;
use Illuminate\Console\Command;

/**
 * YnStageService natijasini (bosqich/aktiv shakllar) bitta (guruh, fan, semestr)
 * uchun chiqaradi — qaydnoma Excel chiqargan shakllar bilan solishtirish uchun.
 *
 * Misol:
 *   php artisan yn:diagnose-stage 691 77 15
 *   php artisan yn:diagnose-stage 691 77 15 --weights=30,10,0,0,60
 */
class DiagnoseYnStage extends Command
{
    protected $signature = 'yn:diagnose-stage
        {group : group_hemis_id}
        {subject : subject_id}
        {semester : semester_code}
        {--weights= : jn,mt,on,oski,test (bo\'sh bo\'lsa closing_form\'dan)}';

    protected $description = 'YnStageService bosqich/aktiv shakllarini chiqaradi (qaydnoma bilan solishtirish uchun)';

    public function handle(YnStageService $service): int
    {
        $group = (string) $this->argument('group');
        $subject = (string) $this->argument('subject');
        $semester = (string) $this->argument('semester');

        $w = [null, null, null, null, null];
        if ($this->option('weights')) {
            $parts = array_map('intval', explode(',', $this->option('weights')));
            if (count($parts) === 5) {
                $w = $parts;
            } else {
                $this->error('--weights 5 ta son bo\'lishi kerak: jn,mt,on,oski,test');
                return self::FAILURE;
            }
        }

        $res = $service->computeForGroupSubject($group, $subject, $semester, $w[0], $w[1], $w[2], $w[3], $w[4]);

        if ($res === null) {
            $this->error('Guruh yoki fan topilmadi.');
            return self::FAILURE;
        }

        $labels = YnAttemptStatusService::class;

        $this->info("Guruh={$group}  Fan={$subject}  Semestr={$semester}");
        $this->line('Talabalar: ' . count($res['stages']));

        // Bosqichlar taqsimoti
        $byStage = [];
        foreach ($res['stages'] as $st) {
            $byStage[$st] = ($byStage[$st] ?? 0) + 1;
        }
        arsort($byStage);
        $this->line('');
        $this->info('Bosqichlar taqsimoti:');
        foreach ($byStage as $stage => $cnt) {
            $this->line(sprintf('  %-26s %d', $stage, $cnt));
        }

        $this->line('');
        $this->info('Aktiv shakllar (qaydnomadagi sheetlar bilan mos kelishi kerak):');
        foreach ($res['activeForms'] as $form => $active) {
            $mark = $active ? '✅' : '—';
            $this->line(sprintf('  %s  %s', $mark, $form));
        }

        return self::SUCCESS;
    }
}

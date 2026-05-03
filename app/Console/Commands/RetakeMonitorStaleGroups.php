<?php

namespace App\Console\Commands;

use App\Services\Retake\RetakeGroupService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class RetakeMonitorStaleGroups extends Command
{
    protected $signature = 'retake:monitor-stale-groups';

    protected $description = "Boshlanish sanasi o'tib ketgan, lekin hali scheduled holatda turgan guruhlarni aniqlash (cron buzilgan bo'lishi mumkin)";

    public function handle(RetakeGroupService $groupService, TelegramService $telegram): int
    {
        $stale = $groupService->staleScheduledGroups();

        if ($stale->isEmpty()) {
            $this->info('Stale guruhlar yo\'q. Cron sog\'lom.');
            return self::SUCCESS;
        }

        $this->warn("Stale guruhlar topildi: {$stale->count()}");

        $lines = ["⚠️ Qayta o'qish guruhlari: scheduled, lekin sanasi o'tib ketgan (cron buzilishi mumkin):", ""];
        foreach ($stale as $g) {
            $line = "• #{$g->id} {$g->name} — start_date: {$g->start_date->format('Y-m-d')}";
            $this->line($line);
            $lines[] = $line;
        }
        $lines[] = "";
        $lines[] = "Tekshirib, kerak bo'lsa retake:transition-group-statuses ni qayta ishga tushiring.";

        $telegram->notify(implode("\n", $lines));

        return self::SUCCESS;
    }
}

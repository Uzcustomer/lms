<?php

namespace App\Console\Commands;

use App\Services\Retake\RetakeGroupService;
use Illuminate\Console\Command;

class RetakeTransitionGroupStatuses extends Command
{
    protected $signature = 'retake:transition-group-statuses';

    protected $description = "Qayta o'qish guruhlari holatlarini sanaga qarab avtomatik o'zgartirish (scheduled→in_progress, in_progress→completed)";

    public function handle(RetakeGroupService $groupService): int
    {
        $result = $groupService->autoTransitionStatuses();

        $this->info(sprintf(
            "scheduled→in_progress: %d, in_progress→completed: %d",
            $result['transitioned_to_in_progress'],
            $result['transitioned_to_completed'],
        ));

        return self::SUCCESS;
    }
}

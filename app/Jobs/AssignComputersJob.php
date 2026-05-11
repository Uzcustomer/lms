<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\ComputerAssignmentService;
use App\Services\ExamNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AssignComputersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $examScheduleId,
        public string $ynType,
    ) {}

    public function handle(ComputerAssignmentService $service, ExamNotificationService $notifier): void
    {
        $schedule = ExamSchedule::find($this->examScheduleId);
        if (!$schedule) {
            return;
        }

        $result = $service->assign($schedule, $this->ynType);

        if (empty($result['ok'])) {
            $reason = $result['reason'] ?? 'unknown';
            Log::warning('AssignComputersJob: not assigned', [
                'schedule_id' => $this->examScheduleId,
                'yn' => $this->ynType,
                'reason' => $reason,
            ]);
            $notifier->notifyComputerShortage($schedule, $this->ynType, $reason);
            return;
        }
    }
}

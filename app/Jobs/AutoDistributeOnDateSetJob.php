<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\AutoAssignService;
use App\Services\ExamCapacityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Triggered when an admin sets the YN date for a group/subject without
 * picking a specific time. The job:
 *
 *   1. Looks up the day's working hours (default 09:00 from settings).
 *   2. Calls AutoAssignService::distribute() to spread the group across
 *      time slots starting at work_hours_start. Computer numbers stay
 *      null until JIT assigns them ~5 minutes before each slot.
 *   3. Persists the earliest slot start as the canonical group test_time
 *      (done inside AutoAssignService::distribute()).
 */
class AutoDistributeOnDateSetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(
        public int $examScheduleId,
        public string $ynType,
    ) {}

    public function handle(AutoAssignService $service): void
    {
        $schedule = ExamSchedule::find($this->examScheduleId);
        if (!$schedule) {
            return;
        }

        $ynType = strtolower($this->ynType);
        $dateField = $ynType . '_date';
        $timeField = $ynType . '_time';
        $naField = $ynType . '_na';

        if (empty($schedule->{$dateField}) || $schedule->{$naField}) {
            return;
        }
        // Skip if a time was already set manually since the job was queued
        if (!empty($schedule->{$timeField})) {
            return;
        }

        $dateStr = $schedule->{$dateField} instanceof \Carbon\Carbon
            ? $schedule->{$dateField}->format('Y-m-d')
            : (string) $schedule->{$dateField};
        $capacity = ExamCapacityService::getSettingsForDate($dateStr);
        $startTime = $capacity['work_hours_start'] ?? '09:00';

        $result = $service->distribute($schedule, $ynType, $startTime);

        if (empty($result['ok'])) {
            Log::warning('AutoDistributeOnDateSetJob: distribute failed', [
                'schedule_id' => $this->examScheduleId,
                'yn' => $ynType,
                'reason' => $result['reason'] ?? 'unknown',
            ]);
            return;
        }

        Log::info('AutoDistributeOnDateSetJob: distributed', [
            'schedule_id' => $this->examScheduleId,
            'yn' => $ynType,
            'count' => $result['count'] ?? 0,
            'slots' => count($result['slots'] ?? []),
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\MoodleExamBookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fires at exam_time + open_window. Re-calls the Moodle book endpoint with
 * timeopen pushed to year 2100, which prevents NEW attempt starts after the
 * cutoff while letting in-progress attempts run out their own timelimit.
 *
 * Self-rescheduling: if the schedule's date/time has been pushed later than
 * we expected, the job releases itself back to the queue with the new delay
 * so it still fires at the correct cutoff.
 */
class LockMoodleStartCutoff implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [60, 120, 300, 600, 900];

    public function __construct(
        public int $examScheduleId,
        public string $ynType,
    ) {}

    public function handle(MoodleExamBookingService $service): void
    {
        $schedule = ExamSchedule::find($this->examScheduleId);
        if (!$schedule) {
            return;
        }

        $dateField = $this->ynType . '_date';
        $timeField = $this->ynType . '_time';
        $naField = $this->ynType . '_na';

        if ($schedule->{$naField}) {
            return; // exam was cancelled
        }
        if (empty($schedule->{$dateField}) || empty($schedule->{$timeField})) {
            return; // exam time was cleared
        }

        // Recompute the cutoff in case the schedule was edited after this job
        // was queued. If we're firing too early, release back with new delay.
        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));
        $dateStr = $schedule->{$dateField} instanceof Carbon
            ? $schedule->{$dateField}->format('Y-m-d')
            : Carbon::parse((string) $schedule->{$dateField})->format('Y-m-d');
        $timeStr = substr((string) $schedule->{$timeField}, 0, 5);

        try {
            $cutoffAt = Carbon::parse($dateStr . ' ' . $timeStr, config('app.timezone'))
                ->addMinutes($window);
        } catch (\Throwable $e) {
            Log::warning('LockMoodleStartCutoff: bad date/time', [
                'schedule_id' => $this->examScheduleId,
                'yn' => $this->ynType,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        if (Carbon::now()->lt($cutoffAt)) {
            $this->release(max(1, $cutoffAt->diffInSeconds(Carbon::now())));
            return;
        }

        $service->book($schedule, $this->ynType, lockMode: true);
    }
}

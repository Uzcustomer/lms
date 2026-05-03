<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\MoodleExamBookingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BookMoodleGroupExam implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $examScheduleId,
        public string $ynType,
    ) {}

    public function handle(MoodleExamBookingService $service): void
    {
        $schedule = ExamSchedule::find($this->examScheduleId);
        if (!$schedule) {
            Log::info('BookMoodleGroupExam: schedule missing', [
                'id' => $this->examScheduleId,
                'yn' => $this->ynType,
            ]);
            return;
        }

        $result = $service->book($schedule, $this->ynType);

        // Skipped (no config / na / missing time) — do not retry.
        if (!empty($result['skipped'])) {
            return;
        }

        // Persistent failure (e.g. missing course_idnumber): no point retrying.
        if (!($result['ok'] ?? false) && !empty($result['error'])) {
            Log::warning('BookMoodleGroupExam failed (no retry)', [
                'schedule_id' => $this->examScheduleId,
                'yn' => $this->ynType,
                'error' => $result['error'],
            ]);
            return;
        }

        // Per-language WS call had a transient error → trigger retry.
        if (!($result['ok'] ?? false)) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}

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

    /**
     * WS error codes that will never succeed on retry - the (course, quiz)
     * mapping is wrong, not the network. Retrying just burns the queue and
     * fills failed_jobs; the error is already persisted on the schedule.
     */
    private const PERMANENT_WS_ERRORS = [
        'quiznotfound',
        'coursenotfound',
        'manualenrolnotenabled',
        'invalid_parameter_exception',
    ];

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

        // Skipped (no config / na / missing time / no quiz history) - do not retry.
        if (!empty($result['skipped'])) {
            return;
        }

        if ($result['ok'] ?? false) {
            return;
        }

        // Top-level failure (e.g. cannot parse date/time): permanent, no retry.
        if (!empty($result['error'])) {
            Log::warning('BookMoodleGroupExam failed (no retry)', [
                'schedule_id' => $this->examScheduleId,
                'yn' => $this->ynType,
                'error' => $result['error'],
            ]);
            return;
        }

        // Per-language WS calls failed. Retry only when at least one failure
        // looks transient (network / timeout). quiznotfound / coursenotfound
        // and friends are permanent mapping problems - retrying just fills
        // failed_jobs while the error is already persisted on the schedule.
        $hasTransient = false;
        foreach ($result['calls'] ?? [] as $c) {
            if ($c['ok'] ?? false) {
                continue;
            }
            $resp = $c['response'] ?? null;
            $code = is_array($resp)
                ? (string) ($resp['errorcode'] ?? $resp['exception'] ?? '')
                : '';
            if ($code === '' || !in_array($code, self::PERMANENT_WS_ERRORS, true)) {
                $hasTransient = true;
                break;
            }
        }

        if ($hasTransient) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
            return;
        }

        Log::warning('BookMoodleGroupExam: permanent WS failure, not retrying', [
            'schedule_id' => $this->examScheduleId,
            'yn' => $this->ynType,
        ]);
    }
}

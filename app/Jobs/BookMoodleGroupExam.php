<?php

namespace App\Jobs;

use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use App\Services\ComputerAssignmentService;
use App\Services\MoodleExamBookingService;
use Carbon\Carbon;
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

    /**
     * @param bool $unscheduled When true, push a date-only "unscheduled" hold
     *                          (Moodle blocks the quiz, no time window) instead
     *                          of a full booking. Set by ExamSchedule::booted()
     *                          when the exam has a date but no time yet.
     * @param int  $attempt     Which attempt to push: 1 = attempt-1 columns,
     *                          2 = resit columns, 3 = resit2 columns. Each
     *                          attempt is a separate Moodle quiz.
     */
    public function __construct(
        public int $examScheduleId,
        public string $ynType,
        public bool $unscheduled = false,
        public int $attempt = 1,
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

        $result = $service->book($schedule, $this->ynType, $this->unscheduled, $this->attempt);

        // Skipped (no config / na / missing time / no quiz history) - do not retry.
        if (!empty($result['skipped'])) {
            // Surface the skip reason onto the schedule so the YN jadvali UI's
            // per-row error column tells a proctor why the booking didn't push
            // (especially for individual student schedules where a missing
            // group-level quiz template otherwise fails silently).
            $reason = $result['reason'] ?? null;
            if (is_string($reason) && $reason !== '') {
                $prefix = $service->attemptPrefix($this->ynType, $this->attempt);
                $schedule->forceFill([
                    $prefix . '_moodle_error' => $reason,
                ])->save();
            }
            return;
        }

        if ($result['ok'] ?? false) {
            // Moodle push succeeded. Auto-create the matching computer_assignments
            // rows so FaceID terminals can find the student. Without this step,
            // re-sit (attempt >= 2) and individual schedules push to Moodle but
            // have no CA rows, and terminals reject the student at the desk with
            // "No exam is allocated for you at this time." The attempt-1 group
            // case is normally covered by the admin's manual "Kompyuter
            // raqamlarini taqsimlash" button on YN jadvali, but individual rows
            // are filtered out of that screen and re-sits are easy to forget.
            try {
                $this->autoCreateComputerAssignments($schedule);
            } catch (\Throwable $e) {
                Log::warning('auto.computer.assignment.failed', [
                    'schedule_id' => $this->examScheduleId,
                    'yn' => $this->ynType,
                    'attempt' => $this->attempt,
                    'error' => $e->getMessage(),
                ]);
            }
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

    /**
     * Materialize computer_assignments rows for this (schedule, yn_type,
     * attempt). Idempotent and respectful of work the admin / proctor /
     * FaceID terminal already did:
     *   - if any pinned (manual) row exists, skip (don't overwrite an
     *     explicit proctor assignment)
     *   - if any in-progress / finished row exists, skip (don't disturb a
     *     student who's already at a desk)
     *   - if scheduled rows already exist, skip (a previous push or the
     *     admin's manual button already covered this)
     * Otherwise:
     *   - individual schedule (student_hemis_id is set): one row via
     *     assignSingleStudent()
     *   - group schedule: full distribution via ComputerAssignmentService::assign()
     */
    private function autoCreateComputerAssignments(ExamSchedule $schedule): void
    {
        $existingQuery = ComputerAssignment::where('exam_schedule_id', $schedule->id)
            ->where('yn_type', $this->ynType)
            ->where('attempt', $this->attempt);

        $hasProtected = (clone $existingQuery)
            ->where(function ($q) {
                $q->where('is_pinned', true)
                    ->orWhereIn('status', [
                        ComputerAssignment::STATUS_IN_PROGRESS,
                        ComputerAssignment::STATUS_FINISHED,
                    ]);
            })
            ->exists();
        if ($hasProtected) {
            return;
        }

        // For individuals, only check at the single-student level (the
        // group may legitimately have other students already assigned).
        if (!empty($schedule->student_hemis_id)) {
            $alreadyForThisStudent = (clone $existingQuery)
                ->where('student_hemis_id', (string) $schedule->student_hemis_id)
                ->exists();
            if ($alreadyForThisStudent) {
                return;
            }

            $fields = ComputerAssignmentService::attemptFields($this->ynType, $this->attempt);
            $dateVal = $schedule->{$fields['date']} ?? null;
            $timeVal = $schedule->{$fields['time']} ?? null;
            if (empty($dateVal) || empty($timeVal)) {
                return;
            }
            $dateStr = $dateVal instanceof Carbon
                ? $dateVal->format('Y-m-d')
                : Carbon::parse((string) $dateVal)->format('Y-m-d');
            $timeStr = substr((string) $timeVal, 0, 5);
            $startsAt = Carbon::parse($dateStr . ' ' . $timeStr, config('app.timezone'));

            $res = app(ComputerAssignmentService::class)->assignSingleStudent(
                $schedule,
                $this->ynType,
                $this->attempt,
                (string) $schedule->student_hemis_id,
                $startsAt,
            );
            if (!empty($res['ok'])) {
                Log::info('auto.computer.assignment', [
                    'schedule_id' => $schedule->id,
                    'yn' => $this->ynType,
                    'attempt' => $this->attempt,
                    'mode' => 'individual',
                    'count' => 1,
                    'computer_number' => $res['computer_number'] ?? null,
                ]);
            }
            return;
        }

        // Group case — if any scheduled rows already exist for this triple,
        // don't re-shuffle (would change computer numbers on every push retry).
        $hasScheduled = (clone $existingQuery)
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->exists();
        if ($hasScheduled) {
            return;
        }

        $res = app(ComputerAssignmentService::class)->assign(
            $schedule,
            $this->ynType,
            $this->attempt,
        );
        if (!empty($res['ok']) && empty($res['skipped'])) {
            Log::info('auto.computer.assignment', [
                'schedule_id' => $schedule->id,
                'yn' => $this->ynType,
                'attempt' => $this->attempt,
                'mode' => 'group',
                'count' => $res['count'] ?? 0,
            ]);
        }
    }
}

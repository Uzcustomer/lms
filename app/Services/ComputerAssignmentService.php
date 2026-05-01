<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Assigns physical computers (1..N) to each student of an ExamSchedule slot,
 * respecting conflicts with other (group, subject) bookings on the same date.
 *
 * Group-level scheduling for now: every student in a group gets the same
 * planned_start (= test_time/oski_time) and planned_end (start + quiz_duration).
 * Each student gets a unique computer number from the pool of computers that
 * are not occupied by another schedule's window.
 *
 * Per-student start time staggering can be added later without breaking this.
 */
class ComputerAssignmentService
{
    public function __construct() {}

    /**
     * Assign computers for one (schedule, yn_type). Idempotent — re-running
     * deletes any previous assignments for the same key and recomputes.
     *
     * @param string $ynType "oski" or "test"
     * @return array{ok:bool, count?:int, skipped?:bool, reason?:string}
     */
    public function assign(ExamSchedule $schedule, string $ynType): array
    {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'invalid yn_type'];
        }

        $dateField = $ynType . '_date';
        $timeField = $ynType . '_time';
        $naField = $ynType . '_na';

        if ($schedule->{$naField}) {
            $this->clearAssignmentsFor($schedule->id, $ynType);
            return ['ok' => true, 'skipped' => true, 'reason' => 'yn marked N/A'];
        }
        if (empty($schedule->{$dateField}) || empty($schedule->{$timeField})) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'date or time missing'];
        }

        $startsAt = $this->combineDateTime($schedule->{$dateField}, $schedule->{$timeField});
        if (!$startsAt) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'cannot parse date/time'];
        }

        $duration = max(1, (int) config('services.moodle.quiz_duration_minutes', 25));
        $buffer = max(0, (int) config('services.moodle.computer_buffer_minutes', 5));
        // planned_end represents "computer becomes free for the next student"
        // i.e. attempt-end + buffer.
        $plannedEnd = $startsAt->copy()->addMinutes($duration + $buffer);

        $students = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->get(['student_id_number', 'hemis_id']);

        if ($students->isEmpty()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'no students in group'];
        }

        $occupied = $this->occupiedComputerNumbers(
            $startsAt,
            $plannedEnd,
            $schedule->id,
            $ynType,
        );

        $total = max(1, (int) config('services.moodle.total_computers', 60));
        $available = collect(range(1, $total))->diff($occupied)->values();

        if ($available->count() < $students->count()) {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => "need {$students->count()} computers but only {$available->count()} free at this slot",
            ];
        }

        $picked = $available->shuffle()->take($students->count())->values();

        DB::transaction(function () use ($schedule, $ynType, $students, $picked, $startsAt, $plannedEnd) {
            ComputerAssignment::where('exam_schedule_id', $schedule->id)
                ->where('yn_type', $ynType)
                ->delete();

            $rows = [];
            $now = now();
            foreach ($students as $i => $student) {
                $rows[] = [
                    'exam_schedule_id' => $schedule->id,
                    'student_id_number' => (string) $student->student_id_number,
                    'student_hemis_id' => (string) $student->hemis_id,
                    'yn_type' => $ynType,
                    'computer_number' => (int) $picked[$i],
                    'planned_start' => $startsAt,
                    'planned_end' => $plannedEnd,
                    'status' => ComputerAssignment::STATUS_SCHEDULED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            ComputerAssignment::insert($rows);
        });

        return ['ok' => true, 'count' => $students->count()];
    }

    /**
     * @return int[] Computer numbers occupied during [start, end] by OTHER schedules.
     */
    private function occupiedComputerNumbers(
        Carbon $start,
        Carbon $end,
        int $excludeScheduleId,
        string $excludeYnType,
    ): array {
        return ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->where(function ($q) use ($excludeScheduleId, $excludeYnType) {
                $q->where('exam_schedule_id', '!=', $excludeScheduleId)
                    ->orWhere('yn_type', '!=', $excludeYnType);
            })
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->pluck('computer_number')
            ->unique()
            ->values()
            ->all();
    }

    private function clearAssignmentsFor(int $scheduleId, string $ynType): void
    {
        ComputerAssignment::where('exam_schedule_id', $scheduleId)
            ->where('yn_type', $ynType)
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                // do NOT delete in_progress / finished — those are real history
            ])
            ->delete();
    }

    private function combineDateTime(mixed $date, mixed $time): ?Carbon
    {
        try {
            $dateStr = $date instanceof Carbon
                ? $date->format('Y-m-d')
                : Carbon::parse((string) $date)->format('Y-m-d');
            $timeStr = substr((string) $time, 0, 5);
            return Carbon::parse($dateStr . ' ' . $timeStr, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}

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

    /**
     * Manual bulk assignment: admin explicitly picks (time, computer) per
     * student. Each row in $perStudent is:
     *   ['student_hemis_id' => string, 'computer_number' => int, 'time' => 'HH:MM']
     *
     * Validations are mostly mirrored from pinComputer():
     *   - the computer must exist and be active
     *   - it must not be occupied by ANOTHER (schedule, yn_type) at that
     *     time window
     *   - within this same call, two students can't share a computer
     *     while their windows overlap
     * Existing scheduled rows for (schedule, yn_type) are wiped before
     * the new ones are inserted so re-running the modal is idempotent.
     *
     * @return array{ok:bool, count?:int, errors?:array<int, string>, earliest_time?:string}
     */
    public function manualAssign(ExamSchedule $schedule, string $ynType, array $perStudent): array
    {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return ['ok' => false, 'errors' => ['Noto\'g\'ri yn turi.']];
        }
        $dateField = $ynType . '_date';
        $naField   = $ynType . '_na';

        if ($schedule->{$naField}) {
            return ['ok' => false, 'errors' => ['Bu yn N/A deb belgilangan.']];
        }
        if (empty($schedule->{$dateField})) {
            return ['ok' => false, 'errors' => ['Avval sana belgilanishi kerak.']];
        }
        $dateStr = $schedule->{$dateField} instanceof Carbon
            ? $schedule->{$dateField}->format('Y-m-d')
            : Carbon::parse((string) $schedule->{$dateField})->format('Y-m-d');

        if (empty($perStudent)) {
            return ['ok' => false, 'errors' => ['Hech qaysi talaba uchun biriktiruv yuborilmagan.']];
        }

        $duration = max(1, (int) config('services.moodle.quiz_duration_minutes', 25));
        $buffer   = max(0, (int) config('services.moodle.computer_buffer_minutes', 5));
        $slotLen  = $duration + $buffer;

        // Build & validate every row.
        $errors = [];
        $rows = [];
        $studentSeen = [];

        $groupStudents = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->get(['student_id_number', 'hemis_id', 'full_name'])
            ->keyBy(fn($s) => (string) $s->hemis_id);

        foreach ($perStudent as $idx => $entry) {
            $hemisId = isset($entry['student_hemis_id']) ? (string) $entry['student_hemis_id'] : '';
            $compNum = isset($entry['computer_number']) ? (int) $entry['computer_number'] : 0;
            $timeStr = isset($entry['time']) ? substr((string) $entry['time'], 0, 5) : '';

            if ($hemisId === '' || !preg_match('/^\d{2}:\d{2}$/', $timeStr) || $compNum < 1) {
                $errors[] = "Qator " . ($idx + 1) . ": ma'lumot to'liq emas.";
                continue;
            }
            if (!isset($groupStudents[$hemisId])) {
                $errors[] = "Talaba (hemis_id={$hemisId}) bu guruhga tegishli emas.";
                continue;
            }
            if (isset($studentSeen[$hemisId])) {
                $errors[] = "Talaba {$groupStudents[$hemisId]->full_name} bir necha qatorda qaytarilgan.";
                continue;
            }
            $studentSeen[$hemisId] = true;

            try {
                $start = Carbon::parse($dateStr . ' ' . $timeStr, config('app.timezone'));
            } catch (\Throwable) {
                $errors[] = "Talaba {$groupStudents[$hemisId]->full_name}: vaqt formatlash xatosi.";
                continue;
            }
            $end = $start->copy()->addMinutes($slotLen);

            $rows[] = [
                'hemis_id'        => $hemisId,
                'student'         => $groupStudents[$hemisId],
                'computer_number' => $compNum,
                'planned_start'   => $start,
                'planned_end'     => $end,
            ];
        }

        if (!empty($errors)) {
            return ['ok' => false, 'errors' => $errors];
        }

        // Internal conflict: same computer, overlapping windows within this batch.
        foreach ($rows as $i => $a) {
            foreach ($rows as $j => $b) {
                if ($i >= $j) continue;
                if ($a['computer_number'] !== $b['computer_number']) continue;
                if ($a['planned_end']->lte($b['planned_start'])) continue;
                if ($b['planned_end']->lte($a['planned_start'])) continue;
                $errors[] = "#{$a['computer_number']} kompyuter ikki talabaga bir vaqtda berilgan: "
                    . "{$a['student']->full_name} va {$b['student']->full_name}.";
            }
        }
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => array_values(array_unique($errors))];
        }

        // External conflict: each picked computer must not be busy at its
        // window per any OTHER (schedule, yn_type).
        foreach ($rows as $r) {
            $occupied = $this->occupiedComputerNumbers(
                $r['planned_start'],
                $r['planned_end'],
                $schedule->id,
                $ynType,
            );
            if (in_array($r['computer_number'], $occupied, true)) {
                $errors[] = "{$r['student']->full_name}: #{$r['computer_number']} bu vaqt oralig'ida boshqa guruh tomonidan band.";
            }
            $computer = \App\Models\Computer::where('number', $r['computer_number'])->first();
            if (!$computer || !$computer->active) {
                $errors[] = "Kompyuter #{$r['computer_number']} mavjud emas yoki faol emas.";
            }
        }
        if (!empty($errors)) {
            return ['ok' => false, 'errors' => array_values(array_unique($errors))];
        }

        // All good — wipe and insert atomically. The wipe matches what
        // assign() does so re-running the modal is idempotent and the
        // earlier status='in_progress'/'finished' history is preserved.
        $now = now();
        $earliest = collect($rows)->pluck('planned_start')->sort()->first();

        DB::transaction(function () use ($schedule, $ynType, $rows, $now, $earliest) {
            ComputerAssignment::where('exam_schedule_id', $schedule->id)
                ->where('yn_type', $ynType)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->delete();

            $insert = [];
            foreach ($rows as $r) {
                $insert[] = [
                    'exam_schedule_id'  => $schedule->id,
                    'student_id_number' => (string) $r['student']->student_id_number,
                    'student_hemis_id'  => (string) $r['student']->hemis_id,
                    'yn_type'           => $ynType,
                    'computer_number'   => $r['computer_number'],
                    'planned_start'     => $r['planned_start'],
                    'planned_end'       => $r['planned_end'],
                    'reveal_at'         => null,
                    'reveal_notified'   => false,
                    'approach_notified' => false,
                    'ready_notified'    => false,
                    'is_reserve'        => false,
                    'is_pinned'         => true,
                    'status'            => ComputerAssignment::STATUS_SCHEDULED,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }
            ComputerAssignment::insert($insert);

            $modeField = $ynType . '_assignment_mode';
            $timeField = $ynType . '_time';
            $schedule->{$modeField} = 'manual_explicit';
            if ($earliest) {
                $schedule->{$timeField} = $earliest->format('H:i');
            }
            $schedule->save();
        });

        return [
            'ok'             => true,
            'count'          => count($rows),
            'earliest_time'  => $earliest ? $earliest->format('H:i') : null,
        ];
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

<?php

namespace App\Services;

use App\Models\Computer;
use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-assigns YN exam time slots and (optionally) computers.
 *
 * Default mode is JIT (just-in-time): distribute() pre-allocates only the
 * time slot for each student (computer_number = null) and the actual
 * computer is picked by ExamScheduleTickJob a few minutes before each
 * student's planned_start, based on which computers are really free.
 *
 * Admin can override JIT for any individual student via pinComputer(),
 * which commits a specific computer number now (is_pinned = true). The
 * JIT processor never touches pinned rows.
 */
class AutoAssignService
{
    public function __construct() {}

    /**
     * Plan time slots for a whole group; computer numbers stay null and
     * are filled in JIT by the tick job. Skips lunch and respects work
     * hours / capacity.
     *
     * @param string $ynType "test" or "oski"
     * @param string $startTime "HH:mm" — earliest slot start for this group
     * @return array{ok:bool, count?:int, slots?:array<int,array{time:string,students:int}>, skipped?:bool, reason?:string}
     */
    public function distribute(ExamSchedule $schedule, string $ynType, string $startTime): array
    {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'invalid yn_type'];
        }

        $dateField = $ynType . '_date';
        $naField = $ynType . '_na';

        if ($schedule->{$naField}) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'yn marked N/A'];
        }
        $date = $schedule->{$dateField};
        if (empty($date)) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'date missing'];
        }

        $dateStr = $date instanceof Carbon
            ? $date->format('Y-m-d')
            : Carbon::parse((string) $date)->format('Y-m-d');

        $capacity = ExamCapacityService::getSettingsForDate($dateStr);
        $duration = max(1, (int) $capacity['test_duration_minutes']);
        $buffer = max(0, (int) config('services.moodle.computer_buffer_minutes', 5));
        $slotLength = $duration + $buffer;

        $workStart = Carbon::parse($dateStr . ' ' . $capacity['work_hours_start']);
        $workEnd = Carbon::parse($dateStr . ' ' . $capacity['work_hours_end']);
        $lunchStart = !empty($capacity['lunch_start'])
            ? Carbon::parse($dateStr . ' ' . $capacity['lunch_start'])
            : null;
        $lunchEnd = !empty($capacity['lunch_end'])
            ? Carbon::parse($dateStr . ' ' . $capacity['lunch_end'])
            : null;

        $students = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->get(['student_id_number', 'hemis_id'])
            ->shuffle()
            ->values();

        if ($students->isEmpty()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'no students in group'];
        }

        $slotCapacity = $this->primaryComputerCount();
        if ($slotCapacity < 1) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'no primary computers configured'];
        }

        $slotStart = Carbon::parse($dateStr . ' ' . substr($startTime, 0, 5));
        if ($slotStart->lt($workStart)) {
            $slotStart = $workStart->copy();
        }

        $remaining = $students->all();
        $createdRows = [];
        $slotReport = [];
        $now = now();

        $guard = 0;
        while (!empty($remaining) && $guard++ < 100) {
            $slotEnd = $slotStart->copy()->addMinutes($slotLength);

            if ($slotEnd->gt($workEnd)) {
                return [
                    'ok' => false,
                    'skipped' => true,
                    'reason' => "no slot available in working hours; " . count($remaining) . ' students unplaced',
                ];
            }

            // Skip lunch
            if ($lunchStart && $lunchEnd && $slotStart->lt($lunchEnd) && $slotEnd->gt($lunchStart)) {
                $slotStart = $lunchEnd->copy();
                continue;
            }

            // How many other students from OTHER schedules already share this slot?
            $alreadyBookedHere = ComputerAssignment::query()
                ->where('planned_end', '>', $slotStart)
                ->where('planned_start', '<', $slotEnd)
                ->where(function ($q) use ($schedule, $ynType) {
                    $q->where('exam_schedule_id', '!=', $schedule->id)
                        ->orWhere('yn_type', '!=', $ynType);
                })
                ->whereIn('status', [
                    ComputerAssignment::STATUS_SCHEDULED,
                    ComputerAssignment::STATUS_IN_PROGRESS,
                ])
                ->count();

            $room = max(0, $slotCapacity - $alreadyBookedHere);
            if ($room < 1) {
                $slotStart = $slotEnd->copy();
                continue;
            }

            $take = array_splice($remaining, 0, $room);
            foreach ($take as $student) {
                $createdRows[] = [
                    'exam_schedule_id' => $schedule->id,
                    'student_id_number' => (string) $student->student_id_number,
                    'student_hemis_id' => (string) $student->hemis_id,
                    'yn_type' => $ynType,
                    'computer_number' => null,
                    'planned_start' => $slotStart->copy(),
                    'planned_end' => $slotEnd->copy(),
                    'reveal_at' => null,
                    'reveal_notified' => false,
                    'approach_notified' => false,
                    'ready_notified' => false,
                    'is_reserve' => false,
                    'is_pinned' => false,
                    'status' => ComputerAssignment::STATUS_SCHEDULED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $slotReport[] = [
                'time' => $slotStart->format('H:i'),
                'students' => count($take),
            ];

            $slotStart = $slotEnd->copy();
        }

        if (!empty($remaining)) {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'could not place ' . count($remaining) . ' students',
            ];
        }

        DB::transaction(function () use ($schedule, $ynType, $createdRows) {
            // Wipe scheduled-only rows for this (schedule, yn_type) — preserve real history
            ComputerAssignment::where('exam_schedule_id', $schedule->id)
                ->where('yn_type', $ynType)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->delete();
            ComputerAssignment::insert($createdRows);
        });

        // Persist the earliest slot start as the canonical group "exam time"
        $earliest = collect($slotReport)->pluck('time')->sort()->first();
        $timeField = $ynType . '_time';
        $modeField = $ynType . '_assignment_mode';
        $schedule->{$timeField} = $earliest;
        $schedule->{$modeField} = 'auto_jit';
        $schedule->save();

        return [
            'ok' => true,
            'count' => count($createdRows),
            'slots' => $slotReport,
        ];
    }

    /**
     * Pick a real free computer for a JIT-pending student and commit it.
     * Returns the chosen computer number on success, null otherwise.
     *
     * "Free" means: the computer is active, not in the reserve pool, and
     * not currently occupied (status != in_progress) by another student
     * within this assignment's planned window.
     */
    public function jitAssign(ComputerAssignment $assignment): ?int
    {
        if ($assignment->is_pinned || $assignment->computer_number !== null) {
            return $assignment->computer_number;
        }

        $occupied = $this->occupiedComputerNumbers(
            $assignment->planned_start,
            $assignment->planned_end,
            $assignment->exam_schedule_id,
            $assignment->yn_type,
            excludeAssignmentId: $assignment->id,
        );

        $pool = $this->primaryPoolNumbers();
        $available = array_values(array_diff($pool, $occupied));
        if (empty($available)) {
            return null;
        }
        shuffle($available);
        $picked = (int) $available[0];

        $assignment->update([
            'computer_number' => $picked,
            'reveal_at' => now(),
        ]);

        return $picked;
    }

    /**
     * Admin pins a specific computer to a specific student. Bypasses JIT.
     *
     * @return array{ok:bool, reason?:string}
     */
    public function pinComputer(ComputerAssignment $assignment, int $computerNumber): array
    {
        $computer = Computer::where('number', $computerNumber)->where('active', true)->first();
        if (!$computer) {
            return ['ok' => false, 'reason' => "Kompyuter #{$computerNumber} mavjud emas yoki faol emas."];
        }

        $occupied = $this->occupiedComputerNumbers(
            $assignment->planned_start,
            $assignment->planned_end,
            $assignment->exam_schedule_id,
            $assignment->yn_type,
            excludeAssignmentId: $assignment->id,
        );
        if (in_array($computerNumber, $occupied, true)) {
            return ['ok' => false, 'reason' => "Kompyuter #{$computerNumber} bu vaqt oralig'ida boshqa talabaga band."];
        }

        $history = $assignment->history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'reason' => 'admin_pin',
            'from' => $assignment->computer_number,
            'to' => $computerNumber,
        ];

        $assignment->update([
            'computer_number' => $computerNumber,
            'is_pinned' => true,
            'history' => $history,
        ]);

        return ['ok' => true];
    }

    /**
     * Move an in-progress overflow into a reserve computer for the given
     * scheduled assignment. Returns the new computer number on success.
     */
    public function moveToReserve(ComputerAssignment $assignment, string $reason = 'overflow'): ?int
    {
        $reserve = Computer::reservePoolNumbers();
        if (empty($reserve)) {
            return null;
        }

        $occupied = $this->occupiedComputerNumbers(
            $assignment->planned_start,
            $assignment->planned_end,
            $assignment->exam_schedule_id,
            $assignment->yn_type,
            excludeAssignmentId: $assignment->id,
        );
        $available = array_values(array_diff($reserve, $occupied));
        if (empty($available)) {
            return null;
        }

        shuffle($available);
        $newNumber = (int) $available[0];

        $history = $assignment->history ?? [];
        $history[] = [
            'at' => now()->toIso8601String(),
            'reason' => $reason,
            'from' => $assignment->computer_number,
            'to' => $newNumber,
        ];

        $assignment->update([
            'moved_from_computer' => $assignment->computer_number,
            'computer_number' => $newNumber,
            'is_reserve' => true,
            'moved_reason' => $reason,
            'history' => $history,
        ]);

        return $newNumber;
    }

    /**
     * @return int[]
     */
    private function occupiedComputerNumbers(
        Carbon $start,
        Carbon $end,
        int $excludeScheduleId,
        string $excludeYnType,
        ?int $excludeAssignmentId = null,
    ): array {
        $q = ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->whereNotNull('computer_number')
            ->where(function ($q) use ($excludeScheduleId, $excludeYnType) {
                $q->where('exam_schedule_id', '!=', $excludeScheduleId)
                    ->orWhere('yn_type', '!=', $excludeYnType);
            })
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ]);
        if ($excludeAssignmentId !== null) {
            $q->where('id', '!=', $excludeAssignmentId);
        }
        return $q->pluck('computer_number')->unique()->values()->all();
    }

    /**
     * @return int[]
     */
    private function primaryPoolNumbers(): array
    {
        $reserve = Computer::reservePoolNumbers();
        $totalConfig = (int) config('services.moodle.total_computers', 60);
        $allActive = Computer::where('active', true)->pluck('number')->map(fn($n) => (int) $n)->all();
        if (empty($allActive)) {
            $allActive = range(1, $totalConfig);
        }
        return array_values(array_diff($allActive, $reserve));
    }

    private function primaryComputerCount(): int
    {
        return count($this->primaryPoolNumbers());
    }
}

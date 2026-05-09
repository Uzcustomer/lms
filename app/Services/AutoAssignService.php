<?php

namespace App\Services;

use App\Models\Computer;
use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-assigns randomized time slots and computers for a group's YN exam,
 * keeping a configurable reserve pool of computers free for fallbacks.
 *
 * Strategy: try to fit the whole group into one slot starting at the
 * provided $startTime. If the group is bigger than the available
 * (non-reserve, non-occupied) computers in that slot, students who don't
 * fit roll over to the next slot at $startTime + slot_minutes, and so on
 * (skipping lunch and respecting work hours).
 */
class AutoAssignService
{
    public function __construct() {}

    /**
     * @param string $ynType "test" or "oski"
     * @param string $startTime "HH:mm" — the earliest slot start for this group
     * @return array{ok:bool, count?:int, slots?:array<int,array{time:string,students:int,computers:int[]}>, skipped?:bool, reason?:string}
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

        $reservePool = Computer::reservePoolNumbers();
        $totalComputers = (int) config('services.moodle.total_computers', 60);
        $allActive = Computer::where('active', true)->pluck('number')->map(fn($n) => (int) $n)->all();
        if (empty($allActive)) {
            $allActive = range(1, $totalComputers);
        }
        $primaryPool = array_values(array_diff($allActive, $reservePool));

        $revealMinutes = max(0, (int) config('services.moodle.reveal_minutes_before', 15));

        $slotStart = Carbon::parse($dateStr . ' ' . substr($startTime, 0, 5));
        if ($slotStart->lt($workStart)) {
            $slotStart = $workStart->copy();
        }

        $remaining = $students->all();
        $createdRows = [];
        $slotReport = [];
        $now = now();

        $guard = 0;
        while (!empty($remaining) && $guard++ < 50) {
            $slotEnd = $slotStart->copy()->addMinutes($slotLength);

            if ($slotEnd->gt($workEnd)) {
                return [
                    'ok' => false,
                    'skipped' => true,
                    'reason' => "no slot available in working hours; {$students->count()} students, " . count($remaining) . ' unplaced',
                ];
            }

            // Skip lunch
            if ($lunchStart && $lunchEnd && $slotStart->lt($lunchEnd) && $slotEnd->gt($lunchStart)) {
                $slotStart = $lunchEnd->copy();
                continue;
            }

            $occupied = $this->occupiedComputerNumbers($slotStart, $slotEnd, $schedule->id, $ynType);
            $availablePrimary = array_values(array_diff($primaryPool, $occupied));
            shuffle($availablePrimary);

            if (empty($availablePrimary)) {
                $slotStart = $slotEnd->copy();
                continue;
            }

            $take = array_splice($remaining, 0, count($availablePrimary));
            $picks = array_slice($availablePrimary, 0, count($take));

            foreach ($take as $i => $student) {
                $createdRows[] = [
                    'exam_schedule_id' => $schedule->id,
                    'student_id_number' => (string) $student->student_id_number,
                    'student_hemis_id' => (string) $student->hemis_id,
                    'yn_type' => $ynType,
                    'computer_number' => (int) $picks[$i],
                    'planned_start' => $slotStart->copy(),
                    'planned_end' => $slotEnd->copy(),
                    'reveal_at' => $slotStart->copy()->subMinutes($revealMinutes),
                    'reveal_notified' => false,
                    'approach_notified' => false,
                    'ready_notified' => false,
                    'is_reserve' => false,
                    'status' => ComputerAssignment::STATUS_SCHEDULED,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $slotReport[] = [
                'time' => $slotStart->format('H:i'),
                'students' => count($take),
                'computers' => $picks,
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
        $schedule->{$modeField} = 'auto_random';
        $schedule->save();

        return [
            'ok' => true,
            'count' => count($createdRows),
            'slots' => $slotReport,
        ];
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
}

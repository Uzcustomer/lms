<?php

namespace App\Jobs;

use App\Models\ComputerAssignment;
use App\Services\AutoAssignService;
use App\Services\ExamNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Periodic tick (every minute) that drives time-sensitive transitions for
 * computer assignments:
 *
 *   1. JIT assign:   for JIT-mode rows whose planned_start is within
 *                    {jit_assign_minutes_before} minutes, pick a real
 *                    free computer now and notify the student.
 *   2. Reveal:       (legacy auto_random) send "your computer is #N"
 *                    once now >= reveal_at for pre-allocated rows.
 *   3. Approaching:  warn the next-in-line student when the current
 *                    occupant of the same computer has spent ~80% of
 *                    quiz_duration (≈ question 20 of 25).
 *   4. Ready:        notify the next student once the current one finishes.
 *   5. Overflow:     when planned_start has passed but the previous
 *                    occupant is still in_progress, move the new student
 *                    to a reserve computer.
 *   6. No-show:      mark scheduled assignments abandoned if actual_start
 *                    is missing N minutes after planned_start.
 */
class ExamScheduleTickJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(
        ExamNotificationService $notifier,
        AutoAssignService $autoAssign,
    ): void {
        $now = now();

        $this->processJitAssignment($now, $autoAssign, $notifier);
        $this->processReveal($now, $notifier);
        $this->processFinishedReady($now, $notifier);
        $this->processApproaching($now, $notifier);
        $this->processOverflow($now, $autoAssign, $notifier);
        $this->processNoShow($now);
    }

    private function processJitAssignment(Carbon $now, AutoAssignService $autoAssign, ExamNotificationService $notifier): void
    {
        $jitMinutes = max(1, (int) config('services.moodle.jit_assign_minutes_before', 5));

        $pending = ComputerAssignment::query()
            ->whereNull('computer_number')
            ->where('is_pinned', false)
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->where('planned_start', '<=', $now->copy()->addMinutes($jitMinutes))
            ->where('planned_start', '>=', $now->copy()->subMinutes(30))
            ->orderBy('planned_start')
            ->limit(200)
            ->get();

        foreach ($pending as $a) {
            try {
                $picked = $autoAssign->jitAssign($a);
                if ($picked === null) {
                    // No primary slot free; try reserve as a last resort.
                    $reserveNumber = $autoAssign->moveToReserve($a, 'overflow');
                    if ($reserveNumber === null) {
                        Log::warning('JIT: no free computer for assignment', ['id' => $a->id]);
                        continue;
                    }
                    $a->refresh();
                }
                $a->refresh();
                $notifier->notifyReveal($a);
                $a->update(['reveal_notified' => true]);
            } catch (\Throwable $e) {
                Log::warning('JIT assignment failed', ['id' => $a->id, 'err' => $e->getMessage()]);
            }
        }
    }

    private function processReveal(Carbon $now, ExamNotificationService $notifier): void
    {
        $due = ComputerAssignment::query()
            ->where('reveal_notified', false)
            ->whereNotNull('reveal_at')
            ->where('reveal_at', '<=', $now)
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->limit(200)
            ->get();

        foreach ($due as $a) {
            try {
                $notifier->notifyReveal($a);
                $a->update(['reveal_notified' => true]);
            } catch (\Throwable $e) {
                Log::warning('reveal notify failed', ['id' => $a->id, 'err' => $e->getMessage()]);
            }
        }
    }

    private function processFinishedReady(Carbon $now, ExamNotificationService $notifier): void
    {
        // Find finished/abandoned assignments whose computer has a queued
        // next student that hasn't been notified yet.
        $recentlyFreed = ComputerAssignment::query()
            ->whereIn('status', [
                ComputerAssignment::STATUS_FINISHED,
                ComputerAssignment::STATUS_ABANDONED,
            ])
            ->whereNotNull('actual_end')
            ->where('actual_end', '>=', $now->copy()->subMinutes(15))
            ->get(['id', 'computer_number', 'actual_end', 'planned_end']);

        foreach ($recentlyFreed as $freed) {
            $next = ComputerAssignment::query()
                ->where('computer_number', $freed->computer_number)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->where('ready_notified', false)
                ->where('planned_start', '<=', $now->copy()->addMinutes(30))
                ->orderBy('planned_start')
                ->first();
            if (!$next) {
                continue;
            }
            try {
                $notifier->notifyReady($next);
                $next->update(['ready_notified' => true]);
            } catch (\Throwable $e) {
                Log::warning('ready notify failed', ['id' => $next->id, 'err' => $e->getMessage()]);
            }
        }
    }

    private function processApproaching(Carbon $now, ExamNotificationService $notifier): void
    {
        $ratio = (float) config('services.moodle.quiz_warn_progress_ratio', 0.80);
        $duration = max(1, (int) config('services.moodle.quiz_duration_minutes', 25));
        $thresholdMinutes = max(1, (int) round($duration * $ratio));

        $inProgress = ComputerAssignment::query()
            ->where('status', ComputerAssignment::STATUS_IN_PROGRESS)
            ->whereNotNull('actual_start')
            ->where('actual_start', '<=', $now->copy()->subMinutes($thresholdMinutes))
            ->get(['id', 'computer_number', 'actual_start']);

        foreach ($inProgress as $running) {
            $next = ComputerAssignment::query()
                ->where('computer_number', $running->computer_number)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->where('approach_notified', false)
                ->where('planned_start', '>', $running->actual_start)
                ->where('planned_start', '<=', $now->copy()->addMinutes($duration))
                ->orderBy('planned_start')
                ->first();
            if (!$next) {
                continue;
            }
            try {
                $notifier->notifyApproaching($next);
                $next->update(['approach_notified' => true]);
            } catch (\Throwable $e) {
                Log::warning('approach notify failed', ['id' => $next->id, 'err' => $e->getMessage()]);
            }
        }
    }

    private function processOverflow(Carbon $now, AutoAssignService $autoAssign, ExamNotificationService $notifier): void
    {
        $grace = max(0, (int) config('services.moodle.overflow_grace_minutes', 0));

        $pending = ComputerAssignment::query()
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->where('planned_start', '<=', $now->copy()->subMinutes($grace))
            ->where('is_reserve', false)
            ->limit(100)
            ->get();

        foreach ($pending as $a) {
            $busy = ComputerAssignment::query()
                ->where('computer_number', $a->computer_number)
                ->where('id', '!=', $a->id)
                ->where('status', ComputerAssignment::STATUS_IN_PROGRESS)
                ->exists();
            if (!$busy) {
                continue;
            }

            $original = (int) $a->computer_number;
            try {
                $newNumber = $autoAssign->moveToReserve($a, 'overflow');
                if ($newNumber !== null) {
                    $a->refresh();
                    $notifier->notifyMoved($a, $original, 'overflow');
                } else {
                    Log::warning('overflow: no reserve computer available', ['id' => $a->id]);
                }
            } catch (\Throwable $e) {
                Log::warning('overflow handling failed', ['id' => $a->id, 'err' => $e->getMessage()]);
            }
        }
    }

    private function processNoShow(Carbon $now): void
    {
        $minutes = max(1, (int) config('services.moodle.no_show_minutes', 5));

        DB::table('computer_assignments')
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->whereNull('actual_start')
            ->where('planned_end', '<', $now->copy()->subMinutes($minutes))
            ->update([
                'status' => ComputerAssignment::STATUS_ABANDONED,
                'updated_at' => $now,
            ]);
    }
}

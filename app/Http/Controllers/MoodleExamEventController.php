<?php

namespace App\Http\Controllers;

use App\Models\ComputerAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives quiz attempt events pushed by the Moodle local_hemisexport plugin.
 *
 * Plugin payload (POST JSON):
 *   {
 *     "event":          "started" | "finished",
 *     "attempt_id":     int,
 *     "username":       string (= LMS students.student_id_number),
 *     "user_id":        int,                  (Moodle user id, optional)
 *     "quiz_id":        int,                  (Moodle quiz id, optional)
 *     "quiz_idnumber":  string,
 *     "course_id":      int,
 *     "course_idnumber":string,
 *     "timestamp":      int (Unix ts of the event),
 *     "state":          string                (e.g. inprogress|finished|abandoned)
 *   }
 *
 * Auth: shared secret via X-SYNC-SECRET header (same as /moodle/import).
 */
class MoodleExamEventController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) env('MOODLE_SYNC_SECRET', '');
        $got = (string) $request->header('X-SYNC-SECRET', '');
        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $event = (string) $request->input('event', '');
        $username = (string) $request->input('username', '');
        $attemptId = (int) $request->input('attempt_id', 0);
        $timestamp = (int) $request->input('timestamp', time());
        $state = (string) $request->input('state', '');

        if (!in_array($event, ['started', 'finished'], true) || $username === '' || $attemptId <= 0) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid payload',
            ], 422);
        }

        $occurredAt = Carbon::createFromTimestamp($timestamp, config('app.timezone'));

        // Find the relevant assignment for this user. Prefer the one whose
        // planned window brackets the event time; otherwise fall back to the
        // closest-by-day match. This handles re-bookings and small clock skew.
        $assignment = $this->resolveAssignment($username, $occurredAt);
        if (!$assignment) {
            Log::info('MoodleExamEvent: no assignment matched', [
                'username' => $username,
                'event' => $event,
                'attempt_id' => $attemptId,
                'ts' => $occurredAt->toIso8601String(),
            ]);
            // Acknowledge so Moodle doesn't keep retrying. The mismatch is
            // benign (e.g. quiz outside the test-markazi flow).
            return response()->json(['ok' => true, 'matched' => false]);
        }

        if ($event === 'started') {
            $assignment->actual_start = $occurredAt;
            $assignment->moodle_attempt_id = $attemptId;
            $assignment->status = ComputerAssignment::STATUS_IN_PROGRESS;
        } else {
            $assignment->actual_end = $occurredAt;
            $assignment->moodle_attempt_id = $assignment->moodle_attempt_id ?: $attemptId;
            $assignment->status = $state === 'abandoned'
                ? ComputerAssignment::STATUS_ABANDONED
                : ComputerAssignment::STATUS_FINISHED;
        }
        $assignment->save();

        return response()->json([
            'ok' => true,
            'matched' => true,
            'assignment_id' => $assignment->id,
            'computer_number' => $assignment->computer_number,
            'status' => $assignment->status,
        ]);
    }

    private function resolveAssignment(string $username, Carbon $at): ?ComputerAssignment
    {
        // 1) Exact-window match: planned_start <= at <= planned_end + 1h slack
        $exact = ComputerAssignment::query()
            ->where('student_id_number', $username)
            ->where('planned_start', '<=', $at)
            ->where('planned_end', '>=', $at->copy()->subHour())
            ->orderBy('planned_start', 'desc')
            ->first();
        if ($exact) {
            return $exact;
        }

        // 2) Same-day fallback (clock skew or pre-window event)
        $sameDay = ComputerAssignment::query()
            ->where('student_id_number', $username)
            ->whereDate('planned_start', $at->toDateString())
            ->orderBy('planned_start', 'desc')
            ->first();
        if ($sameDay) {
            return $sameDay;
        }

        // 3) Most recent assignment for this user (last resort)
        return ComputerAssignment::query()
            ->where('student_id_number', $username)
            ->orderBy('planned_start', 'desc')
            ->first();
    }
}

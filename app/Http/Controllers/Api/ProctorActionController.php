<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComputerAssignment;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Proctor-initiated student actions exposed for the Moodle dashboard.
 *
 * Currently surfaces a single move-student operation: a proctor drags a
 * student from one PC cell on the layout grid onto another, and this
 * endpoint persists the new computer_number on the student's active
 * ComputerAssignment row. The seat-binding guard is bypassed by default
 * (EXAM_ENFORCE_COMPUTER_BINDING=false), so this is mostly a record-
 * keeping move — but it keeps the dashboard's "who is where" state
 * consistent with reality after a proctor reseats someone.
 *
 * Auth: shared X-SYNC-SECRET, same as every other Moodle-callable API.
 */
class ProctorActionController extends Controller
{
    public function move(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');
        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username'           => ['required', 'string', 'max:64'],
            'to_computer_number' => ['required', 'integer', 'min:1'],
            'around_time'        => ['required', 'date'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false, 'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $username = trim((string) $request->input('username'));
        $toPc     = (int) $request->input('to_computer_number');
        $aroundT  = Carbon::parse((string) $request->input('around_time'));

        $student = Student::where('student_id_number', $username)->first();
        if (!$student) {
            return response()->json([
                'ok' => false, 'reason' => 'unknown_user',
                'message' => "Talaba topilmadi: {$username}",
            ], 404);
        }

        // Find the student's active or upcoming-soon assignment near the
        // given time. Search window is wide on purpose — the proctor might
        // drag during the slot itself or slightly before/after.
        $assignment = ComputerAssignment::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->whereDate('planned_start', $aroundT->toDateString())
            ->where('planned_start', '<=', $aroundT->copy()->addMinutes(30))
            ->where('planned_end',   '>=', $aroundT->copy()->subMinutes(30))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->orderBy('planned_start')
            ->first();

        if (!$assignment) {
            return response()->json([
                'ok' => false, 'reason' => 'no_active_assignment',
                'message' => "Talabaning shu vaqtga active assignmenti topilmadi.",
            ], 404);
        }

        $fromPc = $assignment->computer_number;
        if ($fromPc === $toPc) {
            return response()->json([
                'ok' => true, 'unchanged' => true,
                'message' => 'Talaba allaqachon shu PC ga biriktirilgan.',
            ]);
        }

        // Refuse if target PC is occupied in the same time window by another
        // student. The proctor will need to free that PC first (or drag
        // the conflicting student elsewhere).
        $conflict = ComputerAssignment::query()
            ->where('computer_number', $toPc)
            ->where('planned_end',   '>', $assignment->planned_start)
            ->where('planned_start', '<', $assignment->planned_end)
            ->where('id', '!=', $assignment->id)
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->first();
        if ($conflict) {
            $conflictStudent = $conflict->student?->student_id_number ?? 'noma\'lum';
            return response()->json([
                'ok' => false, 'reason' => 'target_busy',
                'message' => "PC #{$toPc} band — boshqa talaba ({$conflictStudent}) shu vaqtga biriktirilgan.",
                'conflict' => [
                    'username'   => $conflictStudent,
                    'time_range' => $conflict->planned_start?->format('H:i') . '-' . $conflict->planned_end?->format('H:i'),
                ],
            ], 409);
        }

        DB::transaction(function () use ($assignment, $toPc) {
            $assignment->computer_number = $toPc;
            $assignment->is_pinned = true;
            $assignment->save();
        });

        Log::info('proctor.move_student', [
            'username' => $username,
            'from_pc'  => $fromPc,
            'to_pc'    => $toPc,
            'planned'  => $assignment->planned_start?->toIso8601String(),
        ]);

        return response()->json([
            'ok'       => true,
            'username' => $username,
            'from_pc'  => $fromPc,
            'to_pc'    => $toPc,
            'planned_start' => $assignment->planned_start?->toIso8601String(),
            'message'  => "Talaba PC #{$fromPc} → #{$toPc} ga ko'chirildi.",
        ]);
    }
}

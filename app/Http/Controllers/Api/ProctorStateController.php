<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\ComputerAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only API consumed by the Moodle proctor dashboard plugin.
 *
 * Auth: shared MOODLE_SYNC_SECRET via X-SYNC-SECRET header.
 *
 * Endpoints:
 *   GET /api/proctor/state?date=YYYY-MM-DD
 *     → { date, computers: [...], updated_at }
 */
class ProctorStateController extends Controller
{
    public function state(Request $request): JsonResponse
    {
        $secret = (string) env('MOODLE_SYNC_SECRET', '');
        $got = (string) $request->header('X-SYNC-SECRET', '');
        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $date = $request->input('date', Carbon::today()->toDateString());
        try {
            $dateObj = Carbon::parse($date);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'invalid date'], 422);
        }

        $computers = Computer::orderBy('number')->get();

        // All assignments for the day, eager-loaded with student
        $assignmentsByComp = ComputerAssignment::query()
            ->whereDate('planned_start', $dateObj->toDateString())
            ->with('student:hemis_id,full_name,group_name,student_id_number')
            ->orderBy('planned_start')
            ->get()
            ->groupBy('computer_number');

        $result = [];
        foreach ($computers as $pc) {
            $assigns = $assignmentsByComp->get($pc->number, collect());

            $current = $assigns->first(fn($a) => $a->status === ComputerAssignment::STATUS_IN_PROGRESS)
                ?? $assigns->first(fn($a) => $a->status === ComputerAssignment::STATUS_SCHEDULED);

            $result[] = [
                'number' => $pc->number,
                'ip_address' => $pc->ip_address,
                'mac_address' => $pc->mac_address,
                'label' => $pc->label,
                'grid_column' => $pc->grid_column,
                'grid_row' => $pc->grid_row,
                'active' => $pc->active,
                'queue_total' => $assigns->count(),
                'current' => $current ? [
                    'assignment_id' => $current->id,
                    'student_id_number' => $current->student_id_number,
                    'student_full_name' => $current->student?->full_name,
                    'group_name' => $current->student?->group_name,
                    'planned_start' => $current->planned_start?->toIso8601String(),
                    'planned_end' => $current->planned_end?->toIso8601String(),
                    'actual_start' => $current->actual_start?->toIso8601String(),
                    'actual_end' => $current->actual_end?->toIso8601String(),
                    'status' => $current->status,
                    'moodle_attempt_id' => $current->moodle_attempt_id,
                ] : null,
                'queue' => $assigns->map(fn($a) => [
                    'assignment_id' => $a->id,
                    'student_id_number' => $a->student_id_number,
                    'student_full_name' => $a->student?->full_name,
                    'group_name' => $a->student?->group_name,
                    'planned_start' => $a->planned_start?->toIso8601String(),
                    'planned_end' => $a->planned_end?->toIso8601String(),
                    'actual_start' => $a->actual_start?->toIso8601String(),
                    'actual_end' => $a->actual_end?->toIso8601String(),
                    'status' => $a->status,
                ])->values(),
            ];
        }

        return response()->json([
            'ok' => true,
            'date' => $dateObj->toDateString(),
            'updated_at' => now()->toIso8601String(),
            'computers' => $result,
        ]);
    }
}

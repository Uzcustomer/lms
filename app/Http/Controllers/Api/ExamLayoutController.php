<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\ComputerAssignment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server endpoint consumed by the Moodle proctor dashboard
 * (auth_faceid plugin). Returns the full computer grid for today plus,
 * per computer, who is currently scheduled to sit there and who is
 * coming next. The reveal_at curtain is intentionally ignored: that
 * exists to keep the assignment hidden from the *student*, but the
 * proctor needs the whole picture to manage the room.
 */
class ExamLayoutController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $now = now();
        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));

        $computers = Computer::query()
            ->where('active', true)
            ->orderBy('number')
            ->get(['id', 'number', 'ip_address', 'grid_column', 'grid_row', 'is_reserve_pool', 'label']);

        $assignments = ComputerAssignment::query()
            ->with(['student:hemis_id,full_name,short_name,first_name,second_name,third_name,student_id_number'])
            ->whereDate('planned_start', $now->toDateString())
            ->orderBy('planned_start')
            ->get();

        // Bucket assignments per computer for O(1) lookup. Rows with a
        // NULL computer_number are slot reservations created by AutoAssign
        // that have not yet been JIT-bound to a specific PC — they'd
        // otherwise fall into bucket 0 and disappear from the grid, so
        // surface them separately as $unassigned (see below).
        $byComputer = [];
        $unassignedRows = [];
        foreach ($assignments as $a) {
            if ($a->computer_number === null) {
                $unassignedRows[] = $a;
                continue;
            }
            $byComputer[(int) $a->computer_number][] = $a;
        }

        $payload = [];
        foreach ($computers as $c) {
            $todays = $byComputer[(int) $c->number] ?? [];

            $current = null;
            $next = null;

            foreach ($todays as $a) {
                $start = $a->planned_start;
                $end   = $a->planned_end;
                if (!$start || !$end) {
                    continue;
                }

                $inWindow = $now->between(
                    $start->copy()->subMinutes($window),
                    $end->copy()->addMinutes($window)
                );

                if ($current === null && $inWindow
                    && in_array($a->status, [
                        ComputerAssignment::STATUS_SCHEDULED,
                        ComputerAssignment::STATUS_IN_PROGRESS,
                    ], true)) {
                    $current = $this->serializeAssignment($a);
                    continue;
                }

                if ($next === null
                    && $start->gt($now)
                    && $a->status === ComputerAssignment::STATUS_SCHEDULED) {
                    $next = $this->serializeAssignment($a);
                }
            }

            $payload[] = [
                'number'      => (int) $c->number,
                'ip'          => $c->ip_address,
                'grid_column' => $c->grid_column !== null ? (int) $c->grid_column : null,
                'grid_row'    => $c->grid_row !== null ? (int) $c->grid_row : null,
                'is_reserve'  => (bool) $c->is_reserve_pool,
                'label'       => $c->label,
                'current'     => $current,
                'next'        => $next,
                'today_total' => count($todays),
            ];
        }

        // Group still-unassigned (computer_number = NULL) rows by their
        // start time, but only for the next-90-minutes horizon — older
        // unassigned rows are stale leftovers, far-future ones aren't
        // actionable for the proctor right now.
        $horizonStart = $now->copy()->subMinutes($window);
        $horizonEnd   = $now->copy()->addMinutes(90);

        $unassignedByTime = [];
        foreach ($unassignedRows as $a) {
            $start = $a->planned_start;
            if (!$start) {
                continue;
            }
            if ($start->lt($horizonStart) || $start->gt($horizonEnd)) {
                continue;
            }
            if (!in_array($a->status, [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ], true)) {
                continue;
            }
            $key = $start->format('H:i');
            if (!isset($unassignedByTime[$key])) {
                $unassignedByTime[$key] = [
                    'planned_start_h' => $key,
                    'planned_end_h'   => $a->planned_end?->format('H:i'),
                    'count'           => 0,
                    'students'        => [],
                ];
            }
            $unassignedByTime[$key]['count']++;
            // Keep the per-student list bounded so the JSON stays small
            // when there are many waves — proctor only needs a sample
            // and the total count.
            if (count($unassignedByTime[$key]['students']) < 30) {
                $unassignedByTime[$key]['students'][] = [
                    'username'   => $a->student?->student_id_number,
                    'short_name' => self::shortName($a->student),
                ];
            }
        }
        ksort($unassignedByTime);
        $unassignedPanel = array_values($unassignedByTime);

        return response()->json([
            'ok'         => true,
            'now'        => $now->toIso8601String(),
            'computers'  => $payload,
            'unassigned' => $unassignedPanel,
        ]);
    }

    private function serializeAssignment(ComputerAssignment $a): array
    {
        $student = $a->student;

        return [
            'username'        => $student?->student_id_number,
            'full_name'       => $student?->full_name,
            'short_name'      => self::shortName($student),
            'planned_start'   => $a->planned_start?->toIso8601String(),
            'planned_end'     => $a->planned_end?->toIso8601String(),
            'planned_start_h' => $a->planned_start?->format('H:i'),
            'planned_end_h'   => $a->planned_end?->format('H:i'),
            'status'          => $a->status,
            'is_reserve'      => (bool) $a->is_reserve,
        ];
    }

    /**
     * Compact "FAMILIYA B.I." form for grid cells.
     *
     * Hemis full_name is upper-cased and frequently carries patronymic
     * suffixes ("QIZI", "O'G'LI", etc.) that should not eat an initial.
     * Strategy: surname = first token (or second_name), then one initial
     * per remaining "real" token.
     */
    public static function shortName(?\App\Models\Student $student): ?string
    {
        if (!$student) {
            return null;
        }

        $full = trim((string) ($student->full_name ?? ''));
        if ($full === '') {
            return null;
        }

        $tokens = preg_split('/\s+/u', $full) ?: [];
        if (empty($tokens)) {
            return $full;
        }

        $skip = ['QIZI', 'KIZI', "O'G'LI", "O‘G‘LI", "OG'LI", 'OGLI', "UG'LI", 'UGLI'];
        $skipNorm = array_map(fn($s) => mb_strtoupper($s), $skip);

        $surname = array_shift($tokens);
        $initials = '';
        foreach ($tokens as $t) {
            $tu = mb_strtoupper($t);
            if (in_array($tu, $skipNorm, true)) {
                continue;
            }
            $first = mb_substr($t, 0, 1);
            if ($first !== '') {
                $initials .= mb_strtoupper($first) . '.';
            }
        }

        return trim($surname . ($initials !== '' ? ' ' . $initials : ''));
    }
}

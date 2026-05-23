<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BookMoodleGroupExam;
use App\Models\ExamSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Admin-triggered bulk push of exam schedules to Moodle.
 *
 * Called by the Moodle lmsguard plugin when an admin clicks
 * "Markdan ma'lumot olish" on the plugin settings page. The Moodle
 * side sends a date range (capped at 30 days) and a shared API key;
 * we then re-run the same booking push that ExamSchedule::booted()
 * normally fires automatically, by dispatchSync-ing BookMoodleGroupExam
 * for every (schedule, date-column) pair inside the range.
 *
 * Auth is an inline shared-secret check against config('services.moodle.api_key')
 * (env: MOODLE_API_KEY). A new middleware would be overkill for a single endpoint.
 */
class MoodleTriggerPushController extends Controller
{
    /**
     * (date column, time column) pairs on exam_schedules → (yn_type, attempt)
     * the job expects. Time column may be NULL — treated as 00:00:00.
     */
    private const DATE_COLUMN_MAP = [
        'test_date'        => ['test_time',        'test', 1],
        'test_resit_date'  => ['test_resit_time',  'test', 2],
        'test_resit2_date' => ['test_resit2_time', 'test', 3],
        'oski_date'        => ['oski_time',        'oski', 1],
        'oski_resit_date'  => ['oski_resit_time',  'oski', 2],
        'oski_resit2_date' => ['oski_resit2_time', 'oski', 3],
    ];

    /**
     * Hard cap on dispatchSync calls per request. Each dispatch is a
     * synchronous Moodle WS call (up to ~120s in the worst case), so even
     * a few hundred can stretch the request out — this is the safety belt.
     */
    private const MAX_DISPATCHES_PER_CALL = 500;

    /**
     * Max allowed [from, to] span in days. Admin UI button is meant for
     * "the upcoming week"; anything wider is almost certainly a mistake.
     */
    private const MAX_RANGE_DAYS = 30;

    public function __invoke(Request $request): JsonResponse
    {
        $startedAt = microtime(true);

        // Accept either a plain date (YYYY-MM-DD) — expanded to the full day —
        // or a datetime (YYYY-MM-DD HH:MM[:SS]). Laravel's `date` rule accepts
        // both via strtotime; we try `date_format:Y-m-d` first so we can tell
        // them apart and expand date-only inputs to full-day bounds.
        $data = $request->validate([
            'from'    => ['required', 'string', 'date'],
            'to'      => ['required', 'string', 'date', 'after_or_equal:from'],
            'api_key' => ['required', 'string'],
        ]);

        $expected = (string) config('services.moodle.api_key');
        if ($expected === '' || !hash_equals($expected, (string) $data['api_key'])) {
            return response()->json([
                'ok'    => false,
                'error' => 'Unauthorized',
            ], 401);
        }

        $from = $this->parseBound((string) $data['from'], false);
        $to   = $this->parseBound((string) $data['to'], true);

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return response()->json([
                'ok'    => false,
                'error' => 'Range too large (max '.self::MAX_RANGE_DAYS.' days)',
            ], 422);
        }

        Log::info('moodle.trigger_push', [
            'phase'   => 'start',
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'from_dt' => $from->toIso8601String(),
            'to_dt'   => $to->toIso8601String(),
            'ip'      => $request->ip(),
        ]);

        // Build the WHERE: schedule matches if ANY of the six date columns
        // falls inside [from-day, to-day]. We deliberately compare only the
        // date part here (widening to whole-day bounds) so that rows on the
        // boundary days survive the SQL filter — the per-row datetime check
        // below refines them down to the exact [from, to] window.
        $fromDay = $from->startOfDay();
        $toDay   = $to->endOfDay();
        $query = ExamSchedule::query()->where(function ($q) use ($fromDay, $toDay) {
            foreach (array_keys(self::DATE_COLUMN_MAP) as $col) {
                $q->orWhereBetween($col, [$fromDay, $toDay]);
            }
        });

        $scanned = 0;
        $pushed  = 0;
        $errors  = [];
        $truncated = false;

        // Iterate in chunks to keep memory bounded even if the range is wide.
        $query->orderBy('id')->chunkById(200, function ($chunk) use (
            $from,
            $to,
            &$scanned,
            &$pushed,
            &$errors,
            &$truncated
        ) {
            foreach ($chunk as $schedule) {
                foreach (self::DATE_COLUMN_MAP as $dateCol => [$timeCol, $ynType, $attempt]) {
                    $dateVal = $schedule->{$dateCol};
                    if (!$dateVal) {
                        continue;
                    }

                    // Combine (date, time) into a full datetime. Time column
                    // may be NULL — fall back to 00:00:00. Date column may be
                    // cast to Carbon (DATETIME) or returned as a string.
                    $dateStr = $dateVal instanceof \DateTimeInterface
                        ? $dateVal->format('Y-m-d')
                        : substr((string) $dateVal, 0, 10);

                    $timeVal = $schedule->{$timeCol} ?? null;
                    if ($timeVal instanceof \DateTimeInterface) {
                        $timeStr = $timeVal->format('H:i:s');
                    } elseif ($timeVal !== null && $timeVal !== '') {
                        $timeStr = (string) $timeVal;
                    } else {
                        $timeStr = '00:00:00';
                    }

                    $dt = CarbonImmutable::parse($dateStr.' '.$timeStr);

                    if ($dt->lt($from) || $dt->gt($to)) {
                        continue;
                    }

                    $scanned++;

                    if ($scanned > self::MAX_DISPATCHES_PER_CALL) {
                        $truncated = true;
                        return false; // stop chunkById
                    }

                    try {
                        BookMoodleGroupExam::dispatchSync(
                            $schedule->id,
                            $ynType,
                            false,
                            $attempt
                        );
                        $pushed++;
                    } catch (Throwable $e) {
                        $errors[] = [
                            'schedule_id' => $schedule->id,
                            'yn_type'     => $ynType,
                            'attempt'     => $attempt,
                            'reason'      => mb_substr($e->getMessage(), 0, 200),
                        ];
                    }
                }
            }

            return true;
        });

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        Log::info('moodle.trigger_push', [
            'phase'       => 'finish',
            'from'        => $from->toDateString(),
            'to'          => $to->toDateString(),
            'scanned'     => $scanned,
            'pushed'      => $pushed,
            'errors'      => count($errors),
            'truncated'   => $truncated,
            'duration_ms' => $durationMs,
        ]);

        $response = [
            'ok'          => true,
            'scanned'     => $scanned,
            'pushed'      => $pushed,
            'errors'      => $errors,
            'duration_ms' => $durationMs,
        ];

        if ($truncated) {
            $response['note'] = 'Stopped early at '.self::MAX_DISPATCHES_PER_CALL
                .' dispatches; narrow the date range and call again.';
        }

        return response()->json($response);
    }

    /**
     * Parse a `from`/`to` bound. A bare YYYY-MM-DD expands to start/end of
     * day (controlled by $isUpperBound); a YYYY-MM-DD HH:MM[:SS] is used
     * as-is. Already validated by Laravel's `date` rule before we get here.
     */
    private function parseBound(string $value, bool $isUpperBound): CarbonImmutable
    {
        $value = trim($value);

        // Date-only → expand to full-day bound for backward compatibility
        // with the original date-range callers.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $dt = CarbonImmutable::parse($value);

            return $isUpperBound ? $dt->endOfDay() : $dt->startOfDay();
        }

        return CarbonImmutable::parse($value);
    }
}

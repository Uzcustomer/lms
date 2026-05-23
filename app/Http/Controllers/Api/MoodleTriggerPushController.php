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
     * Date columns on exam_schedules → (yn_type, attempt) the job expects.
     */
    private const DATE_COLUMN_MAP = [
        'test_date'        => ['test', 1],
        'test_resit_date'  => ['test', 2],
        'test_resit2_date' => ['test', 3],
        'oski_date'        => ['oski', 1],
        'oski_resit_date'  => ['oski', 2],
        'oski_resit2_date' => ['oski', 3],
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

        $data = $request->validate([
            'from'    => ['required', 'date'],
            'to'      => ['required', 'date', 'after_or_equal:from'],
            'api_key' => ['required', 'string'],
        ]);

        $expected = (string) config('services.moodle.api_key');
        if ($expected === '' || !hash_equals($expected, (string) $data['api_key'])) {
            return response()->json([
                'ok'    => false,
                'error' => 'Unauthorized',
            ], 401);
        }

        $from = CarbonImmutable::parse($data['from'])->startOfDay();
        $to   = CarbonImmutable::parse($data['to'])->endOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            return response()->json([
                'ok'    => false,
                'error' => 'Range too large (max '.self::MAX_RANGE_DAYS.' days)',
            ], 422);
        }

        Log::info('moodle.trigger_push', [
            'phase' => 'start',
            'from'  => $from->toDateString(),
            'to'    => $to->toDateString(),
            'ip'    => $request->ip(),
        ]);

        // Build the WHERE: schedule matches if ANY of the six date columns
        // falls inside [from, to]. whereBetween on a date column with full
        // day-bounded timestamps works for both DATE and DATETIME columns.
        $query = ExamSchedule::query()->where(function ($q) use ($from, $to) {
            foreach (array_keys(self::DATE_COLUMN_MAP) as $col) {
                $q->orWhereBetween($col, [$from, $to]);
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
                foreach (self::DATE_COLUMN_MAP as $col => [$ynType, $attempt]) {
                    $val = $schedule->{$col};
                    if (!$val) {
                        continue;
                    }

                    // Column may be cast to Carbon (DATETIME) or returned as
                    // a date string. Normalize via CarbonImmutable.
                    $dt = CarbonImmutable::parse(
                        $val instanceof \DateTimeInterface ? $val->format('Y-m-d') : (string) $val
                    )->startOfDay();

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
}

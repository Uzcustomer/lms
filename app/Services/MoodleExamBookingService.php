<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\HemisQuizResult;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleExamBookingService
{
    public function __construct(
        private readonly ?string $wsUrl = null,
        private readonly ?string $wsToken = null,
    ) {}

    /**
     * Book a single (schedule, yn_type) on Moodle and persist the outcome on the schedule.
     *
     * @param string $ynType "oski" or "test"
     * @return array{ok:bool, skipped?:bool, reason?:string, calls?:array}
     */
    public function book(ExamSchedule $schedule, string $ynType): array
    {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return $this->fail($schedule, $ynType, 'invalid yn_type: ' . $ynType);
        }

        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'Moodle WS not configured'];
        }

        $dateField = $ynType . '_date';
        $timeField = $ynType . '_time';
        $naField = $ynType . '_na';

        if ($schedule->{$naField}) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'yn marked N/A'];
        }
        if (empty($schedule->{$dateField}) || empty($schedule->{$timeField})) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'date or time missing'];
        }

        $startsAt = $this->combineDateTime($schedule->{$dateField}, $schedule->{$timeField});
        if (!$startsAt) {
            return $this->fail($schedule, $ynType, 'cannot parse date/time');
        }

        // open_window_minutes = +/- entry grace (early/late cutoff for FaceID
        // login & quiz entry), independent of slot duration. Default 10.
        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));
        // attempt_start_buffer_minutes = extra grace AFTER the late entry
        // cutoff during which the student is still allowed to click "Start
        // attempt". Solves the "FaceID at 15:44, but the page navigation /
        // click takes 30 sec, hits the start_cutoff at 15:45 and the quiz
        // refuses" problem. Doesn't extend the FaceID login window itself
        // (that's still controlled by $window), only the quiz start cutoff
        // on Moodle side.
        $attemptBuffer = max(0, (int) config('services.moodle.attempt_start_buffer_minutes', 2));
        $capacity = ExamCapacityService::getSettingsForDate($startsAt->toDateString());
        $duration = max(1, (int) ($capacity['test_duration_minutes'] ?? 15));
        // closeBuffer = how long after the late-entry cutoff the quiz stays
        // open, so a student starting at the cutoff still has the full
        // duration. We default it to the exam duration; an env override
        // remains available if a site wants something different.
        $closeBuffer = max(1, (int) config('services.moodle.close_buffer_minutes', $duration));

        $timeopen = $startsAt->copy()->subMinutes($window)->getTimestamp();
        // After exam_time + window + attemptBuffer: no NEW attempt starts.
        // The +attemptBuffer minutes are the grace between a FaceID login
        // landing right on the late-entry edge and the student actually
        // clicking "Start attempt".
        $startCutoff = $startsAt->copy()->addMinutes($window + $attemptBuffer)->getTimestamp();
        // timeclose is wider so in-progress attempts run their full Moodle timelimit.
        $timeclose = $startsAt->copy()->addMinutes($window + $attemptBuffer + $closeBuffer)->getTimestamp();
        $timelimit = max(0, (int) config('services.moodle.timelimit_seconds', 0));

        // Resolve the stable middle of the Moodle quiz name - subject + semester
        // + faculty + direction - from the real quiz names Moodle itself
        // recorded for this group in hemis_quiz_results (the Diagnostika
        // import). cm.idnumber is empty on the Moodle install and
        // course.idnumber is unreliable, so the quiz NAME is the only
        // dependable key. We never reconstruct it from tokens - we replay what
        // Moodle already told us.
        $quizMiddle = $this->resolveQuizMiddle($schedule, $ynType);
        if ($quizMiddle === null) {
            // No prior Moodle attempt for this group+subject, so we cannot
            // derive the real quiz name. Leave it for the proctor to add via
            // the "Add manual booking" fallback on the Moodle proctor page.
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'no Moodle quiz history for this group+subject - proctor manual booking needed',
            ];
        }

        $fanId = (string) $schedule->subject_id;

        $studentsByLang = $this->studentsByLanguage($schedule->group_hemis_id);
        if (empty($studentsByLang)) {
            return $this->fail($schedule, $ynType, 'no students in group ' . $schedule->group_hemis_id);
        }

        $calls = [];
        $allOk = true;

        foreach ($studentsByLang as $langCode => $usernames) {
            $quizName = $this->buildQuizName($ynType, $langCode, $quizMiddle);
            $payload = [
                'wstoken' => $token,
                'wsfunction' => 'local_hemisexport_book_group_exam',
                'moodlewsrestformat' => 'json',
                'quiz_name' => $quizName,
                'fan_id' => $fanId,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
                'timelimit' => $timelimit,
                'start_cutoff' => $startCutoff,
                'students' => array_values($usernames),
            ];

            $callResult = $this->call($url, $payload);
            $callResult['lang'] = $langCode;
            $callResult['quiz_name'] = $quizName;
            $callResult['student_count'] = count($usernames);
            $calls[] = $callResult;

            if (!$callResult['ok']) {
                $allOk = false;
            }
        }

        $result = [
            'ok' => $allOk,
            'quiz_middle' => $quizMiddle,
            'fan_id' => $fanId,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'start_cutoff' => $startCutoff,
            'calls' => $calls,
        ];

        $this->persistResult($schedule, $ynType, $result);

        return $result;
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

    /**
     * Resolve the stable "middle" of the Moodle quiz name for this
     * (group, subject): the subject + semester + faculty + direction segment,
     * which is identical across language and attempt number.
     *
     * Source of truth = hemis_quiz_results.attempt_name, i.e. the real quiz
     * names Moodle pushed back during the Diagnostika results import. We pick a
     * recorded name of the same yn_type and strip the "{type} ({lang})_" prefix
     * and the trailing "_{shakl}" so it can be rebuilt for any language/attempt.
     *
     * Returns null when the group has no recorded attempt for this subject in
     * the matching yn_type - the caller then skips the booking.
     */
    private function resolveQuizMiddle(ExamSchedule $schedule, string $ynType): ?string
    {
        $groupStudentIds = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->pluck('student_id_number')
            ->all();
        if (empty($groupStudentIds)) {
            return null;
        }

        $prefix = $ynType === 'oski' ? 'OSKI' : 'YN test';

        $names = HemisQuizResult::query()
            ->where('fan_id', $schedule->subject_id)
            ->whereIn('student_id', $groupStudentIds)
            ->whereNotNull('attempt_name')
            ->where('attempt_name', 'LIKE', $prefix . ' (%')
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->pluck('attempt_name');

        foreach ($names as $name) {
            $middle = $this->extractQuizMiddle((string) $name, $prefix);
            if ($middle !== null) {
                return $middle;
            }
        }

        return null;
    }

    /**
     * Strip the "{type} ({lang})_" prefix and the trailing "_{shakl}" off a
     * full Moodle quiz name, leaving the language-/attempt-independent middle.
     *
     * "YN test (uzb)_Tibbiy kimyo_2-sem_DAV-2_D_1-urinish" => "Tibbiy kimyo_2-sem_DAV-2_D"
     */
    private function extractQuizMiddle(string $quizName, string $prefix): ?string
    {
        $pattern = '/^' . preg_quote($prefix, '/') . '\\s*\\([^)]*\\)_(.+)_[^_]+$/u';
        if (preg_match($pattern, trim($quizName), $m)) {
            $middle = trim($m[1]);
            return $middle !== '' ? $middle : null;
        }
        return null;
    }

    /**
     * Build the target Moodle quiz name for attempt 1 in the given language.
     */
    private function buildQuizName(string $ynType, string $langCode, string $quizMiddle): string
    {
        $prefix = $ynType === 'oski' ? 'OSKI' : 'YN test';
        return $prefix . ' (' . $langCode . ')_' . $quizMiddle . '_1-urinish';
    }

    /**
     * Group student usernames (= student_id_number) by their effective exam language.
     * Effective = student.exam_language_code (override) ?? group.education_lang_code.
     *
     * @return array<string, array<int, string>> [langCode => [username, ...]]
     */
    private function studentsByLanguage(string $groupHemisId): array
    {
        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        $defaultLang = $this->normalizeLang($group?->education_lang_code);

        $students = Student::where('group_id', $groupHemisId)
            ->whereNotNull('student_id_number')
            ->get(['student_id_number', 'exam_language_code']);

        $bucket = [];
        foreach ($students as $st) {
            // Default = group's educationLang; student override wins.
            $lang = $this->normalizeLang($st->exam_language_code ?: $defaultLang);
            $bucket[$lang][] = (string) $st->student_id_number;
        }
        // Deduplicate just in case
        foreach ($bucket as $k => $list) {
            $bucket[$k] = array_values(array_unique($list));
        }
        return $bucket;
    }

    private function normalizeLang(?string $code): string
    {
        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return (string) (config('services.moodle.lang_map.uz') ?? 'uzb');
        }
        $map = (array) config('services.moodle.lang_map', []);
        // Allow both raw HEMIS code (uz/ru/en) and already-mapped code (uzb/rus/eng).
        if (isset($map[$code])) {
            return (string) $map[$code];
        }
        if (in_array($code, $map, true)) {
            return $code;
        }
        // Best-effort short->long
        return match ($code) {
            'uz', 'oz', 'uzb' => 'uzb',
            'ru', 'rus' => 'rus',
            'en', 'eng' => 'eng',
            default => $code,
        };
    }

    /**
     * @return array{ok:bool, http_status?:int, response?:mixed, error?:string}
     */
    private function call(string $url, array $payload): array
    {
        $timeout = max(5, (int) config('services.moodle.ws_timeout', 30));
        try {
            $resp = Http::asForm()->timeout($timeout)->post($url, $payload);
            $body = $resp->json();
            $ok = $resp->successful() && is_array($body) && !empty($body['success']);
            return [
                'ok' => $ok,
                'http_status' => $resp->status(),
                'response' => $body ?? $resp->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Moodle book_group_exam failed', [
                'error' => $e->getMessage(),
                'payload' => array_diff_key($payload, ['wstoken' => 1]),
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function persistResult(ExamSchedule $schedule, string $ynType, array $result): void
    {
        $prefix = $ynType;
        $errorText = null;
        if (!($result['ok'] ?? false)) {
            $errors = [];
            foreach ($result['calls'] ?? [] as $c) {
                if (!($c['ok'] ?? false)) {
                    $resp = $c['response'] ?? null;
                    $errors[] = ($c['lang'] ?? '?') . ': ' . (
                        is_array($resp)
                            ? json_encode($resp, JSON_UNESCAPED_UNICODE)
                            : (string) ($c['error'] ?? $resp ?? 'unknown')
                    );
                }
            }
            // Top-level `error` (e.g. "cannot parse date/time") wins when there
            // are no per-language calls; otherwise prepend it to per-call details.
            $topLevel = (string) ($result['error'] ?? '');
            if ($topLevel !== '' && empty($errors)) {
                $errorText = $topLevel;
            } elseif ($topLevel !== '') {
                $errorText = $topLevel . ' | ' . implode(' | ', $errors);
            } else {
                $errorText = implode(' | ', $errors) ?: 'unknown error';
            }
        }

        $schedule->forceFill([
            $prefix . '_moodle_synced_at' => now(),
            $prefix . '_moodle_response' => $result,
            $prefix . '_moodle_error' => $errorText,
        ])->save();
    }

    private function fail(ExamSchedule $schedule, string $ynType, string $message): array
    {
        $result = ['ok' => false, 'error' => $message, 'calls' => []];
        $this->persistResult($schedule, $ynType, $result);
        return $result;
    }
}

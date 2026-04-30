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
    private const ATTEMPT_LABEL = '1-urinish';

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

        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));
        $timeopen = $startsAt->copy()->subMinutes($window)->getTimestamp();
        $timeclose = $startsAt->copy()->addMinutes($window)->getTimestamp();
        $timelimit = max(0, (int) config('services.moodle.timelimit_seconds', 0));

        $courseIdnumber = $this->resolveCourseIdnumber($schedule, $ynType);
        if (!$courseIdnumber) {
            return $this->fail(
                $schedule,
                $ynType,
                'course_idnumber not found — make sure quizzes were imported at least once for this group/subject'
            );
        }

        $studentsByLang = $this->studentsByLanguage($schedule->group_hemis_id);
        if (empty($studentsByLang)) {
            return $this->fail($schedule, $ynType, 'no students in group ' . $schedule->group_hemis_id);
        }

        $calls = [];
        $allOk = true;

        foreach ($studentsByLang as $langCode => $usernames) {
            $quizIdnumber = $this->buildQuizIdnumber($ynType, $langCode);
            $payload = [
                'wstoken' => $token,
                'wsfunction' => 'local_hemisexport_book_group_exam',
                'moodlewsrestformat' => 'json',
                'course_idnumber' => $courseIdnumber,
                'quiz_idnumber' => $quizIdnumber,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
                'timelimit' => $timelimit,
                'students' => array_values($usernames),
            ];

            $callResult = $this->call($url, $payload);
            $callResult['lang'] = $langCode;
            $callResult['quiz_idnumber'] = $quizIdnumber;
            $callResult['student_count'] = count($usernames);
            $calls[] = $callResult;

            if (!$callResult['ok']) {
                $allOk = false;
            }
        }

        $result = [
            'ok' => $allOk,
            'course_idnumber' => $courseIdnumber,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
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
     * Find the Moodle course_idnumber for this (group, subject) pair.
     * Strategy: pick the most recently synced hemis_quiz_results row whose student
     * belongs to this group and whose fan_id matches the subject. The plugin uses
     * tokens 2 (OSKI/TEST) inside course_idnumber, so we swap that token to match
     * the requested yn_type.
     */
    private function resolveCourseIdnumber(ExamSchedule $schedule, string $ynType): ?string
    {
        $groupStudentIds = Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->pluck('student_id_number')
            ->all();
        if (empty($groupStudentIds)) {
            return null;
        }

        $idnumber = HemisQuizResult::query()
            ->whereNotNull('course_idnumber')
            ->where('fan_id', $schedule->subject_id)
            ->whereIn('student_id', $groupStudentIds)
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->value('course_idnumber');

        if (!$idnumber) {
            return null;
        }

        return $this->swapYnToken((string) $idnumber, $ynType);
    }

    /**
     * Swap the YN-type token (2nd underscore-separated segment) to match $ynType.
     * Example: "436_TEST_8-SEM_DAV-1_D" + "oski" => "436_OSKI_8-SEM_DAV-1_D"
     */
    private function swapYnToken(string $idnumber, string $ynType): string
    {
        $tokens = explode('_', $idnumber);
        if (count($tokens) < 2) {
            return $idnumber;
        }
        $current = strtoupper($tokens[1]);
        $target = strtoupper($ynType);
        if ($current === 'OSKI' || $current === 'TEST') {
            $tokens[1] = $target;
        }
        return implode('_', $tokens);
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
        // Best-effort short→long
        return match ($code) {
            'uz', 'oz', 'uzb' => 'uzb',
            'ru', 'rus' => 'rus',
            'en', 'eng' => 'eng',
            default => $code,
        };
    }

    private function buildQuizIdnumber(string $ynType, string $langCode): string
    {
        return strtoupper($ynType) . ' (' . $langCode . ')_' . self::ATTEMPT_LABEL;
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
        if (!$result['ok']) {
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
            $errorText = implode(' | ', $errors) ?: 'unknown error';
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

<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Glue helpers for the trilingual exam landing page (the language picker the
 * student sees AFTER a successful FaceID login and BEFORE actually entering
 * the Moodle quiz). Centralises three things:
 *
 *   1. The signed short-lived token used to identify the student on the
 *      otherwise-public picker URL. The Moodle auth_faceid plugin mints one
 *      via /api/exam-landing-token immediately after login and hands the
 *      browser the resulting LMS URL.
 *
 *   2. The list of exams the student is booked for "today" (the rows from
 *      `exam_schedules` whose attempt-1 / resit / resit2 date column for
 *      either yn_type matches today's date and which target this student
 *      either via student_hemis_id or via the group).
 *
 *   3. The mapping between the picker's three buttons ("uz" / "ru" / "en")
 *      and the HEMIS exam_language_code stored on `students.exam_language_code`,
 *      so that the choice survives long enough for the booking push to use it.
 */
class ExamLandingService
{
    /** Cache key prefix; one entry per minted token. */
    private const CACHE_PREFIX = 'exam_landing_token:';

    /** Token lifetime — keep short, this is just the post-login bounce window. */
    private const TOKEN_TTL_SECONDS = 900; // 15 minutes

    /**
     * UI button code (uz/ru/en) → HEMIS exam_language_code we write back to
     * `students.exam_language_code`. The numeric codes mirror what
     * config('services.moodle.lang_map') already understands (11=uzb, 13=rus,
     * 14=eng), so the MoodleExamBookingService booking push picks them up
     * unchanged.
     */
    public const HEMIS_LANG_CODES = [
        'uz' => '11',
        'ru' => '13',
        'en' => '14',
    ];

    /**
     * Mint a single-use-ish landing token for $student, cache it for
     * TOKEN_TTL_SECONDS and return the token string. The picker URL is
     * built by the caller with route('exam.landing', $token).
     */
    public function issueToken(Student $student): string
    {
        $token = (string) Str::random(48);
        Cache::put(self::CACHE_PREFIX . $token, (int) $student->id, self::TOKEN_TTL_SECONDS);
        return $token;
    }

    /**
     * Reverse the token back to the Student model. Returns null when the
     * token is unknown / expired / refers to a deleted student.
     */
    public function resolveToken(string $token): ?Student
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        $studentId = Cache::get(self::CACHE_PREFIX . $token);
        if (!$studentId) {
            return null;
        }
        return Student::find((int) $studentId);
    }

    /**
     * The (yn_type, attempt) pairs that have a date column for "today" for
     * this student. Each entry carries enough info for the picker view to
     * render a row, and for the choose endpoint to identify the booking
     * push uniquely:
     *
     *   - schedule_id    : exam_schedules.id
     *   - yn_type        : "oski" | "test"
     *   - attempt        : 1 | 2 | 3
     *   - subject_name   : human title
     *   - exam_time_local: "HH:MM" or null when no time picked yet (unscheduled)
     *   - available_langs: ['uz','ru','en'] — at minimum the languages
     *                       configured in services.moodle.lang_map. We do not
     *                       sniff Moodle for "this quiz actually exists in
     *                       lang X" — the booking push will fail gracefully
     *                       if it doesn't, and the picker would otherwise
     *                       force a guess against a remote table.
     *
     * @return array<int, array{
     *     schedule_id:int, yn_type:string, attempt:int,
     *     subject_name:string, exam_time_local:?string,
     *     available_langs:array<int,string>
     * }>
     */
    public function examsForStudent(Student $student): array
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $hemisId = (string) ($student->hemis_id ?? '');
        $groupHemisId = (string) ($student->group_id ?? '');

        // Pick up BOTH per-student rows (student_hemis_id == this student)
        // AND group-wide rows (student_hemis_id NULL, group_hemis_id matches).
        // A per-student row exists for individual grafik and wins implicitly:
        // the booking push itself filters to the student in that case.
        $schedules = ExamSchedule::query()
            ->where(function ($q) use ($hemisId, $groupHemisId) {
                if ($hemisId !== '') {
                    $q->where('student_hemis_id', $hemisId);
                }
                if ($groupHemisId !== '') {
                    $q->orWhere(function ($q2) use ($groupHemisId) {
                        $q2->where('group_hemis_id', $groupHemisId)
                            ->whereNull('student_hemis_id');
                    });
                }
            })
            ->get();

        $rows = [];
        $availableLangs = $this->availableLangs();

        foreach ($schedules as $schedule) {
            foreach ($this->attemptPlan() as [$ynType, $attempt, $prefix]) {
                $dateField = $prefix . '_date';
                $timeField = $prefix . '_time';

                $date = $schedule->{$dateField} ?? null;
                if (!$date) {
                    continue;
                }
                $dateStr = $date instanceof Carbon
                    ? $date->toDateString()
                    : Carbon::parse((string) $date)->toDateString();
                if ($dateStr !== $today) {
                    continue;
                }
                // Attempt-1 N/A flag: this yn was waived for this group, skip.
                if ($attempt === 1 && (bool) ($schedule->{$ynType . '_na'} ?? false)) {
                    continue;
                }

                $time = $schedule->{$timeField} ?? null;
                $timeStr = $time ? substr((string) $time, 0, 5) : null;

                $rows[] = [
                    'schedule_id'    => (int) $schedule->id,
                    'yn_type'        => $ynType,
                    'attempt'        => $attempt,
                    'subject_name'   => (string) ($schedule->subject_name ?? ''),
                    'exam_time_local'=> $timeStr,
                    'available_langs'=> $availableLangs,
                ];
            }
        }

        // Sort by time so the earliest exam shows first; rows without time
        // (unscheduled holds) fall to the bottom.
        usort($rows, function ($a, $b) {
            $av = $a['exam_time_local'] ?? '99:99';
            $bv = $b['exam_time_local'] ?? '99:99';
            return strcmp($av, $bv);
        });

        return $rows;
    }

    /**
     * The list of "real" UI language buttons the picker offers. Always all
     * three when the lang_map config carries entries for uz/ru/en (the
     * default), trimmed otherwise. Order is deliberate: Uzbek first
     * because that's the campus default.
     *
     * @return array<int,string>
     */
    public function availableLangs(): array
    {
        $map = (array) config('services.moodle.lang_map', []);
        $langs = [];
        foreach (['uz', 'ru', 'en'] as $code) {
            if (isset($map[$code]) && $map[$code] !== '') {
                $langs[] = $code;
            }
        }
        return $langs ?: ['uz', 'ru', 'en'];
    }

    /**
     * Resolve the Moodle quiz_idnumber the chosen (yn_type, lang, attempt)
     * combination should map to, using the same templates ExamQuizTargetController
     * already uses for the post-FaceID redirect. Returns null only when the
     * templates are misconfigured (no template for the yn_type, no fallback).
     */
    public function quizIdnumberFor(string $ynType, string $lang, int $attempt): ?string
    {
        $ynType = strtolower(trim($ynType));
        if ($ynType === '') {
            return null;
        }
        $perType = (array) config('services.moodle.quiz_idnumber_templates', []);
        $template = $perType[$ynType]
            ?? (string) config(
                'services.moodle.quiz_idnumber_template',
                'YN {yn} ({lang})_{attempt}-urinish'
            );
        if ($template === '') {
            return null;
        }
        return strtr($template, [
            '{yn}'      => $ynType,
            '{YN}'      => strtoupper($ynType),
            '{lang}'    => $this->normalizeMoodleLang($lang),
            '{attempt}' => (string) $attempt,
        ]);
    }

    /**
     * Build the absolute URL the picker should send the browser to after a
     * successful language choice. This is a thin Moodle endpoint
     * (auth/faceid/quiz_redirect.php) that resolves the quiz_idnumber to a
     * course-module id and 302s to /mod/quiz/view.php — we go through it so
     * the LMS doesn't have to know Moodle's internal numeric ids.
     */
    public function moodleQuizRedirectUrl(string $quizIdnumber): string
    {
        $root = $this->moodleWwwroot();
        if ($root === '') {
            return '';
        }
        return rtrim($root, '/') . '/auth/faceid/quiz_redirect.php?idnumber=' . rawurlencode($quizIdnumber);
    }

    /**
     * Best-effort Moodle wwwroot, configured explicitly via MOODLE_WWWROOT or
     * derived from MOODLE_WS_URL (which is "{wwwroot}/webservice/rest/server.php").
     */
    public function moodleWwwroot(): string
    {
        $explicit = trim((string) config('services.moodle.wwwroot'));
        if ($explicit !== '') {
            return rtrim($explicit, '/');
        }
        $wsUrl = trim((string) config('services.moodle.ws_url'));
        if ($wsUrl === '') {
            return '';
        }
        $parts = parse_url($wsUrl);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        return $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Iterate the six (yn_type, attempt, column-prefix) tuples that
     * exam_schedules carries date columns for.
     *
     * @return array<int, array{0:string, 1:int, 2:string}>
     */
    private function attemptPlan(): array
    {
        return [
            ['oski', 1, 'oski'],
            ['oski', 2, 'oski_resit'],
            ['oski', 3, 'oski_resit2'],
            ['test', 1, 'test'],
            ['test', 2, 'test_resit'],
            ['test', 3, 'test_resit2'],
        ];
    }

    /**
     * Normalize a UI lang code (uz/ru/en) to the Moodle quiz_idnumber token
     * (uzb/rus/eng) via the services.moodle.lang_map config.
     */
    private function normalizeMoodleLang(string $code): string
    {
        $code = strtolower(trim($code));
        $map = (array) config('services.moodle.lang_map', []);
        if (isset($map[$code])) {
            return (string) $map[$code];
        }
        return match ($code) {
            'uz', 'oz', 'uzb' => 'uzb',
            'ru', 'rus' => 'rus',
            'en', 'eng' => 'eng',
            default => $code,
        };
    }
}

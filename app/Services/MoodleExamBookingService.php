<?php

namespace App\Services;

use App\Models\ExamSchedule;
use App\Models\HemisQuizResult;
use App\Models\Semester;
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
     * Book a single (schedule, yn_type, attempt) on Moodle and persist the
     * outcome on the schedule.
     *
     * @param string $ynType "oski" or "test"
     * @param bool $unscheduled When true, push a date-only "unscheduled" hold:
     *                          Moodle records the booking and blocks the quiz,
     *                          but no time window / start cutoff is sent. Used
     *                          when the schedule has a date but no exam time yet.
     * @param int $attempt 1 = attempt-1 columns (oski_ / test_), 2 = resit
     *                     columns (_resit_), 3 = resit2 columns (_resit2_).
     *                     Each attempt is a separate Moodle quiz, named
     *                     "..._{attempt}-urinish".
     * @return array{ok:bool, skipped?:bool, reason?:string, calls?:array}
     */
    public function book(ExamSchedule $schedule, string $ynType, bool $unscheduled = false, int $attempt = 1): array
    {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return ['ok' => false, 'error' => 'invalid yn_type: ' . $ynType, 'calls' => []];
        }
        if (!in_array($attempt, [1, 2, 3], true)) {
            return ['ok' => false, 'error' => 'invalid attempt: ' . $attempt, 'calls' => []];
        }

        // Column prefix for this attempt: 1 = oski/test, 2 = *_resit, 3 = *_resit2.
        // The *_date / *_time / *_moodle_* columns all share this prefix.
        $prefix = $this->attemptPrefix($ynType, $attempt);

        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'skipped' => true, 'reason' => 'Moodle WS not configured'];
        }

        $dateField = $prefix . '_date';
        $timeField = $prefix . '_time';

        // The N/A flag exists for attempt 1 only — resits have no N/A.
        if ($attempt === 1 && $schedule->{$ynType . '_na'}) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'yn marked N/A'];
        }
        if (empty($schedule->{$dateField})) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'date missing'];
        }

        if ($unscheduled) {
            // Date-only hold: the test-centre has not picked a time yet. No
            // window / cutoff is computed - Moodle records the booking as
            // "unscheduled" and keeps the quiz closed until a full booking
            // (with real times) replaces it. examstart carries the exam date
            // (midnight) so the proctor page can still show "Test sanasi".
            $timeopen = 0;
            $timeclose = 0;
            $timelimit = 0;
            $startCutoff = 0;
            $examTime = $this->examDateMidnight($schedule->{$dateField});
        } else {
            if (empty($schedule->{$timeField})) {
                return ['ok' => false, 'skipped' => true, 'reason' => 'date or time missing'];
            }

            $startsAt = $this->combineDateTime($schedule->{$dateField}, $schedule->{$timeField});
            if (!$startsAt) {
                return $this->fail($schedule, $prefix, 'cannot parse date/time');
            }
            // The real exam start (e.g. 14:00) — sent as-is so the proctor page
            // shows the LMS time, not the computed +window+buffer cutoff.
            $examTime = $startsAt->getTimestamp();

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
        }

        // Resolve the stable middle of the Moodle quiz name - subject + semester
        // + faculty + direction - from the real quiz names Moodle itself
        // recorded for this group in hemis_quiz_results (the Diagnostika
        // import). cm.idnumber is empty on the Moodle install and
        // course.idnumber is unreliable, so the quiz NAME is the only
        // dependable key. We never reconstruct it from tokens - we replay what
        // Moodle already told us.
        $quizMiddle = $this->resolveQuizMiddle($schedule, $ynType);
        if ($quizMiddle === null) {
            // No usable Moodle quiz history for this group, so we cannot derive
            // the real quiz name. Leave it for the proctor to add via the
            // "Add manual booking" fallback on the Moodle proctor page.
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'no usable Moodle quiz history for this group - proctor manual booking needed',
            ];
        }

        $fanId = (string) $schedule->subject_id;
        // Academic year lets Moodle drop same-named quizzes left over from a
        // previous year (the course category name starts with this).
        $academicYear = (string) ($schedule->education_year ?? '');

        // Per-student schedule (individual grafik) — Moodle bookingni faqat
        // shu talabaga tushiramiz. Aks holda guruh sathidagi yozuv —
        // barcha guruh talabalariga tushadi.
        $perStudentHemisId = !empty($schedule->student_hemis_id)
            ? (string) $schedule->student_hemis_id
            : null;

        // YN ga ruxsati yo'q (X) talabalarni Moodle bookingidan chiqarib
        // tashlaymiz: ular kvizni umuman ocha olmasligi kerak. Hisob
        // YnAdmissionService orqali — YN oldi qaydnoma bilan bir xil mantiq.
        $deniedHemisIds = [];
        try {
            $admissionMap = app(YnAdmissionService::class)->computeForGroup(
                (string) $schedule->group_hemis_id,
                (string) $schedule->subject_id,
                (string) $schedule->semester_code,
            );
            foreach ($admissionMap as $hid => $info) {
                if (($info['status'] ?? null) === YnAdmissionService::STATUS_X) {
                    $deniedHemisIds[] = (string) $hid;
                }
            }
        } catch (\Throwable $e) {
            // Admission tekshiruvi ishlamasa, eski (filtrsiz) xulqqa qaytamiz —
            // booking butunlay to'xtab qolmasin.
            Log::warning('MoodleExamBookingService: admission filter failed', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Per-student schedule X talaba bo'lsa — bookingni butunlay skip qilamiz.
        if ($perStudentHemisId !== null && in_array($perStudentHemisId, $deniedHemisIds, true)) {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'student ' . $perStudentHemisId . ' has YN admission = X (jurnal shartlari bajarilmagan)',
            ];
        }

        // ALL admitted students for this booking, ignoring per-student language
        // preference: every student is pushed to every language variant of the
        // exam that actually exists on Moodle. The student picks the language
        // at exam time on the Moodle-side picker (auth/faceid/picker.php).
        // student.exam_language_code is no longer consulted here — it remains
        // an informational field only.
        $usernames = $this->collectStudentUsernames(
            $schedule->group_hemis_id,
            $perStudentHemisId,
            $perStudentHemisId === null ? $deniedHemisIds : []
        );
        if (empty($usernames)) {
            $reason = $perStudentHemisId !== null
                ? 'student ' . $perStudentHemisId . ' not found in group ' . $schedule->group_hemis_id
                : (count($deniedHemisIds) > 0
                    ? 'no admitted students in group ' . $schedule->group_hemis_id . ' (' . count($deniedHemisIds) . ' X talaba chiqarib tashlandi)'
                    : 'no students in group ' . $schedule->group_hemis_id);
            return $this->fail($schedule, $prefix, $reason);
        }

        // The trilingual exam picker lives in Moodle: we push the same
        // booking row to every language variant of this exam quiz that
        // exists on Moodle, and the student chooses at exam time. The set
        // of language tokens to try is derived from services.moodle.lang_map.
        $langTokens = $this->resolveLangQuizTokens();

        $calls = [];
        $allOk = true;
        $pushedTokens = [];
        $skippedNotFoundTokens = [];

        foreach ($langTokens as $langCode) {
            $quizName = $this->buildQuizName($ynType, $langCode, $quizMiddle, $attempt);
            $payload = [
                'wstoken' => $token,
                'wsfunction' => 'local_hemisexport_book_group_exam',
                'moodlewsrestformat' => 'json',
                'quiz_name' => $quizName,
                'fan_id' => $fanId,
                'academic_year' => $academicYear,
                'timeopen' => $timeopen,
                'timeclose' => $timeclose,
                'timelimit' => $timelimit,
                'start_cutoff' => $startCutoff,
                'unscheduled' => $unscheduled ? 1 : 0,
                'exam_time' => $examTime,
                'students' => array_values($usernames),
            ];

            $callResult = $this->call($url, $payload);
            $callResult['lang'] = $langCode;
            $callResult['quiz_name'] = $quizName;
            $callResult['student_count'] = count($usernames);

            // A non-existent quiz variant (this exam isn't offered in this
            // language on Moodle) is NOT a failure - just record it and move
            // on. The book_group_exam WS throws coursenotfound / quiznotfound
            // when the named quiz isn't in the Moodle DB.
            if (!$callResult['ok'] && $this->isQuizNotFound($callResult)) {
                $callResult['skipped_not_found'] = true;
                $skippedNotFoundTokens[] = $langCode;
                $calls[] = $callResult;
                continue;
            }

            $calls[] = $callResult;
            if ($callResult['ok']) {
                $pushedTokens[] = $langCode;
            } else {
                $allOk = false;
            }
        }

        // If every variant was "quiz not found", the exam isn't published in
        // Moodle yet — surface that as a real failure so the proctor "Add
        // manual booking" flow can take over, matching the old behaviour for
        // missing-quiz situations.
        if (empty($pushedTokens) && !empty($skippedNotFoundTokens)) {
            $allOk = false;
        }

        // Per-student informational telemetry: which language variants we
        // successfully pushed for each student in this booking. The picker
        // on the Moodle side now decides which language the student actually
        // uses at exam time.
        foreach ($usernames as $hid) {
            Log::info('moodle.booking.pushed', [
                'student'           => (string) $hid,
                'schedule'          => $schedule->id,
                'yn'                => $ynType,
                'attempt'           => $attempt,
                'lang_tokens_pushed' => $pushedTokens,
            ]);
        }

        $result = [
            'ok' => $allOk,
            'unscheduled' => $unscheduled,
            'attempt' => $attempt,
            'quiz_middle' => $quizMiddle,
            'fan_id' => $fanId,
            'academic_year' => $academicYear,
            'timeopen' => $timeopen,
            'timeclose' => $timeclose,
            'timelimit' => $timelimit,
            'start_cutoff' => $startCutoff,
            'exam_time' => $examTime,
            'calls' => $calls,
        ];

        $this->persistResult($schedule, $prefix, $result);

        return $result;
    }

    /**
     * Column prefix for a (yn_type, attempt) pair — the *_date / *_time /
     * *_moodle_* columns all share it: attempt 1 = "oski"/"test",
     * attempt 2 = "*_resit", attempt 3 = "*_resit2".
     */
    private function attemptPrefix(string $ynType, int $attempt): string
    {
        return match ($attempt) {
            2 => $ynType . '_resit',
            3 => $ynType . '_resit2',
            default => $ynType,
        };
    }

    /**
     * Midnight (Unix ts) of an exam date — used as examstart for unscheduled
     * holds, where only the date is known. Returns 0 if the date won't parse.
     */
    private function examDateMidnight(mixed $date): int
    {
        try {
            $carbon = $date instanceof Carbon
                ? $date->copy()
                : Carbon::parse((string) $date, config('app.timezone'));
            return $carbon->startOfDay()->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
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
     * (group, subject): the "{subject}_{N-sem}_{faculty}_{direction}" segment,
     * which is identical across language and attempt number.
     *
     * Source of truth = hemis_quiz_results.attempt_name, the real quiz names
     * Moodle pushed back during the Diagnostika results import. cm.idnumber is
     * empty on the Moodle install and course.idnumber is unreliable, so the
     * quiz NAME is the only dependable key.
     *
     * Resolution order:
     *  1. This group already sat a {prefix} ({lang}) quiz for THIS subject -
     *     replay that exact middle.
     *  2. First {prefix} for this subject for this group: take the
     *     "{N-sem}_{faculty}_{direction}" tail from any of the group's recorded
     *     quiz names in the matching semester and prepend this schedule's
     *     subject name. The tail is group-specific and identical across
     *     subjects, so this rebuilds the real Moodle quiz name.
     *
     * Returns null when the group has no usable recorded attempt at all - the
     * caller then skips the booking for a proctor to add manually.
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

        // 1. This group already has a {prefix} quiz recorded for this subject -
        //    replay that exact middle.
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
                return $this->normalizeMiddle($middle);
            }
        }

        // 2. First {prefix} for this group+subject. The "{N-sem}_{faculty}_
        //    {direction}" tail is group-specific and identical across subjects,
        //    so lift it off any of the group's recorded quiz names in the same
        //    semester and prepend this schedule's subject name.
        $subjectName = $this->stripGroupSuffix(trim((string) $schedule->subject_name));
        $targetNsem = $this->resolveTargetNsem($schedule);
        if ($subjectName === '' || $targetNsem === null) {
            return null;
        }

        $anyNames = HemisQuizResult::query()
            ->whereIn('student_id', $groupStudentIds)
            ->whereNotNull('attempt_name')
            ->where(function ($q) {
                $q->where('attempt_name', 'LIKE', 'YN test (%')
                  ->orWhere('attempt_name', 'LIKE', 'OSKI (%')
                  ->orWhere('attempt_name', 'LIKE', 'JN (%');
            })
            ->orderByDesc('synced_at')
            ->orderByDesc('id')
            ->pluck('attempt_name');

        foreach ($anyNames as $name) {
            $tail = $this->extractMiddleTail((string) $name);
            if ($tail !== null && str_starts_with($tail, $targetNsem . '_')) {
                return $this->normalizeMiddle($subjectName . '_' . $tail);
            }
        }

        return null;
    }

    /**
     * Strip a trailing punctuation character (typically ".") from the subject
     * portion of a replayed quiz middle. Past `hemis_quiz_results` entries can
     * carry a typo dot at the end of the subject name — e.g. when a tutor
     * created a duplicate Moodle quiz with "...ortepediyasi._8-sem_..." instead
     * of the canonical "...ortepediyasi_8-sem_...". Replaying that name as-is
     * makes the next attempt's booking fail with coursenotfound, since the
     * follow-up quiz (3-urinish here) is only published under the canonical
     * (dotless) name. We normalise by removing any "_<N>-sem" leading dot.
     */
    private function normalizeMiddle(string $middle): string
    {
        return preg_replace('/\.+(?=_\d+-sem(?:_|$))/u', '', $middle);
    }

    /**
     * The "{N}-sem" token for this schedule's semester, e.g. "2-sem".
     * The semesters table stores names like "2-semestr".
     */
    private function resolveTargetNsem(ExamSchedule $schedule): ?string
    {
        $name = Semester::where('code', $schedule->semester_code)->value('name');
        if ($name && preg_match('/(\d+)/', (string) $name, $m)) {
            return $m[1] . '-sem';
        }
        return null;
    }

    /**
     * Drop a trailing parallel-stream suffix — " (a)", " (b)", " (c)" — from a
     * HEMIS subject name. Mark carries it on subject_name for split groups, but
     * the Moodle quiz name never includes it, so it must come off before the
     * quiz name is rebuilt in resolveQuizMiddle() step 2.
     */
    private function stripGroupSuffix(string $name): string
    {
        // Variant harfi lotin ham, kirill ham bo'lishi mumkin (a/b/c/s yoki а/б/в/с).
        return trim(preg_replace('/\s*\([A-Za-zА-Яа-яёЁ0-9]{1,4}\)\s*$/u', '', $name));
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
     * Lift the "{N-sem}_{faculty}_{direction}" tail off a full Moodle quiz
     * name (any type - YN test / OSKI / JN), dropping the type/lang prefix,
     * the subject name and the trailing "_{shakl}".
     *
     * "YN test (uzb)_Gistologiya, sitologiya_2-sem_DAV-2_D_1-urinish" => "2-sem_DAV-2_D"
     * "JN (uzb)_8_Rus tili_2-sem_DAV-2_D_13-mavzu"                    => "2-sem_DAV-2_D"
     */
    private function extractMiddleTail(string $quizName): ?string
    {
        if (preg_match('/_(\d+-sem_.+)_[^_]+$/u', trim($quizName), $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Build the target Moodle quiz name for the given language and attempt,
     * e.g. "YN test (uzb)_{middle}_2-urinish".
     */
    private function buildQuizName(string $ynType, string $langCode, string $quizMiddle, int $attempt = 1): string
    {
        $prefix = $ynType === 'oski' ? 'OSKI' : 'YN test';
        return $prefix . ' (' . $langCode . ')_' . $quizMiddle . '_' . $attempt . '-urinish';
    }

    /**
     * Collect all admitted student usernames (= student_id_number) for the
     * booking — without splitting by exam language. The trilingual picker on
     * the Moodle side decides which language each student actually uses, so
     * the booking row is pushed identically for every language variant of the
     * quiz that exists on Moodle.
     *
     * @param string|null $studentHemisId Per-student schedule (individual grafik)
     *                                     bo'lsa, faqat shu talabaga filtrlaymiz —
     *                                     Moodle booking guruh emas, shu talabagagina
     *                                     tushadi.
     * @return array<int, string> list of usernames
     */
    private function collectStudentUsernames(string $groupHemisId, ?string $studentHemisId = null, array $excludeHemisIds = []): array
    {
        $studentsQuery = Student::where('group_id', $groupHemisId)
            ->whereNotNull('student_id_number');
        if ($studentHemisId !== null) {
            $studentsQuery->where('hemis_id', $studentHemisId);
        }
        if (!empty($excludeHemisIds)) {
            $studentsQuery->whereNotIn('hemis_id', $excludeHemisIds);
        }
        $usernames = $studentsQuery->pluck('student_id_number')
            ->map(fn ($u) => (string) $u)
            ->filter(fn ($u) => $u !== '')
            ->unique()
            ->values()
            ->all();

        return $usernames;
    }

    /**
     * The de-duplicated set of Moodle quiz language tokens we attempt the
     * booking against, derived from services.moodle.lang_map (typically
     * ['uzb', 'rus', 'eng']). Runtime-computed; no new env var required.
     */
    private function resolveLangQuizTokens(): array
    {
        $cached = config('services.moodle.lang_quiz_tokens');
        if (is_array($cached) && !empty($cached)) {
            return array_values(array_unique(array_map('strval', $cached)));
        }

        $map = (array) config('services.moodle.lang_map', []);
        $tokens = array_values(array_unique(array_map(
            fn ($v) => strtolower((string) $v),
            array_filter($map, fn ($v) => is_string($v) && $v !== '')
        )));
        if (empty($tokens)) {
            $tokens = ['uzb', 'rus', 'eng'];
        }
        // Cache on the config repo for subsequent calls in this request /
        // worker lifetime.
        config(['services.moodle.lang_quiz_tokens' => $tokens]);
        return $tokens;
    }

    /**
     * Detect a "quiz/course not found" error from a Moodle WS response: this
     * means the named language variant of the exam quiz isn't published in
     * Moodle, which is expected for mono-lingual exams. We silently skip
     * such variants rather than failing the whole booking.
     */
    private function isQuizNotFound(array $callResult): bool
    {
        $resp = $callResult['response'] ?? null;
        if (!is_array($resp)) {
            return false;
        }
        $code = (string) ($resp['errorcode'] ?? '');
        if ($code === 'quiznotfound' || $code === 'coursenotfound') {
            return true;
        }
        $message = strtolower((string) ($resp['message'] ?? $resp['exception'] ?? ''));
        return str_contains($message, 'quiznotfound')
            || str_contains($message, 'coursenotfound')
            || str_contains($message, 'no such quiz')
            || str_contains($message, 'quiz not found');
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

    /**
     * Persist the push outcome onto the schedule's {$prefix}_moodle_* columns,
     * where $prefix is the attempt-specific column prefix (oski / oski_resit /
     * oski_resit2 / test / test_resit / test_resit2).
     */
    private function persistResult(ExamSchedule $schedule, string $prefix, array $result): void
    {
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

    private function fail(ExamSchedule $schedule, string $prefix, string $message): array
    {
        $result = ['ok' => false, 'error' => $message, 'calls' => []];
        $this->persistResult($schedule, $prefix, $result);
        return $result;
    }
}

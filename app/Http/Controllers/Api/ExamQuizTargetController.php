<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComputerAssignment;
use App\Models\Group;
use App\Models\Student;
use App\Services\ExamAccessGuardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Server-to-server endpoint consulted by the Moodle auth_faceid plugin
 * immediately after a successful FaceID login. If the student has an
 * active YN slot RIGHT NOW (and is on the correct computer), this
 * returns the Moodle quiz idnumber so the plugin can redirect them
 * straight to the quiz view page instead of dumping them on the Moodle
 * front page. The student still presses "Attempt quiz now" themselves —
 * we do not auto-start the attempt.
 */
class ExamQuizTargetController extends Controller
{
    public function __construct(private ExamAccessGuardService $guard) {}

    public function target(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:64'],
            'ip'       => ['nullable', 'string', 'ip'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $username = trim((string) $request->input('username'));
        $ip       = $request->input('ip') ? (string) $request->input('ip') : null;

        $student = Student::query()
            ->where('student_id_number', $username)
            ->first();

        if (!$student) {
            return response()->json([
                'ok' => true,
                'ready' => false,
                'reason' => 'unknown_user',
                'message' => 'Talaba LMS bazasida topilmadi.',
            ]);
        }

        // Reuse the existing access guard so this endpoint and
        // /api/exam-access-check answer consistently. We pass IP if we
        // have one; the guard only blocks on wrong_computer when an IP
        // is provided. Without an IP we still surface the slot info so
        // the student can be steered to the quiz view page — the
        // quizaccess_lmsguard plugin will re-check IP at attempt start.
        $check = $this->guard->check($student, $ip);

        if (empty($check['allowed'])) {
            return response()->json([
                'ok' => true,
                'ready' => false,
                'reason' => $check['reason'] ?? 'denied',
                'message' => $check['message'] ?? null,
                'expected_computer' => $check['expected_computer'] ?? null,
                'computer_number' => $check['computer_number'] ?? null,
                'planned_start' => $check['planned_start'] ?? null,
            ]);
        }

        $assignment = $this->findActiveAssignment($student, now());
        if (!$assignment) {
            return response()->json([
                'ok' => true,
                'ready' => false,
                'reason' => 'no_active_assignment',
                'message' => 'Hozirgi vaqtga sizga belgilangan test slot topilmadi.',
            ]);
        }

        $quizIdnumber = $this->buildQuizIdnumber($assignment, $student);
        if ($quizIdnumber === null) {
            return response()->json([
                'ok' => true,
                'ready' => false,
                'reason' => 'no_quiz_idnumber',
                'message' => 'Test idnumber aniqlanmadi. Iltimos proctor bilan bog\'laning.',
            ]);
        }

        Log::info('exam_quiz_target', [
            'username' => $username,
            'ip' => $ip,
            'yn_type' => $assignment->yn_type,
            'quiz_idnumber' => $quizIdnumber,
        ]);

        return response()->json([
            'ok' => true,
            'ready' => true,
            'quiz_idnumber' => $quizIdnumber,
            'computer_number' => (int) $assignment->computer_number,
            'planned_start' => $assignment->planned_start?->toIso8601String(),
            'planned_end' => $assignment->planned_end?->toIso8601String(),
        ]);
    }

    private function findActiveAssignment(Student $student, Carbon $now): ?ComputerAssignment
    {
        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));

        return ComputerAssignment::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('planned_start', '<=', $now->copy()->addMinutes($window))
            ->where('planned_end', '>=', $now->copy()->subMinutes($window))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->orderBy('planned_start')
            ->first();
    }

    private function buildQuizIdnumber(ComputerAssignment $assignment, Student $student): ?string
    {
        $ynType = trim((string) $assignment->yn_type);
        if ($ynType === '') {
            return null;
        }

        $lang = $this->resolveLang($student);

        // Per-yn_type template wins (so "oski" -> "OSKI (uzb)..." while
        // "test" -> "YN test (uzb)..."). Falls back to the single legacy
        // template so a brand-new yn_type does not silently 500.
        $perType = (array) config('services.moodle.quiz_idnumber_templates', []);
        $template = $perType[strtolower($ynType)]
            ?? (string) config(
                'services.moodle.quiz_idnumber_template',
                'YN {yn} ({lang})_{attempt}-urinish'
            );

        return strtr($template, [
            '{yn}' => strtolower($ynType),
            '{YN}' => strtoupper($ynType),
            '{lang}' => $lang,
            '{attempt}' => '1',
        ]);
    }

    private function resolveLang(Student $student): string
    {
        $override = trim((string) ($student->exam_language_code ?? ''));
        if ($override !== '') {
            return $this->normalizeLang($override);
        }

        $group = Group::query()->where('group_hemis_id', $student->group_id)->first();
        return $this->normalizeLang($group?->education_lang_code ?? 'uz');
    }

    /**
     * Mirror MoodleExamBookingService::normalizeLang — consult the
     * services.moodle.lang_map (which already covers HEMIS numeric codes
     * 11-15 in addition to alpha codes), then fall back to a best-effort
     * short→long mapping.
     */
    private function normalizeLang(string $code): string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return (string) (config('services.moodle.lang_map.uz') ?? 'uzb');
        }
        $map = (array) config('services.moodle.lang_map', []);
        if (isset($map[$code])) {
            return (string) $map[$code];
        }
        if (in_array($code, $map, true)) {
            return $code;
        }
        return match ($code) {
            'uz', 'oz', 'uzb' => 'uzb',
            'ru', 'rus' => 'rus',
            'en', 'eng' => 'eng',
            default => $code,
        };
    }
}

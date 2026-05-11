<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ExamAccessGuardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Server-to-server endpoint consulted by the Moodle auth_faceid plugin
 * BEFORE completing a FaceID login. Lets the LMS reject the login fast
 * when a student is at the wrong test-centre PC during their exam slot,
 * so they're sent to the right computer immediately instead of getting
 * a confusing "wrong_computer" error 30 seconds later when they try to
 * open the quiz.
 *
 * This wraps ExamAccessGuardService::checkForLogin(), which is more
 * permissive than the quiz-attempt guard: it only blocks the precise
 * "wrong_computer" case and lets everything else (no exam slot, IP
 * outside test centre, ...) through. The full guard still runs at
 * quiz-attempt time.
 *
 * Auth: shared X-SYNC-SECRET header, same as /api/exam-access-check.
 */
class ExamLoginCheckController extends Controller
{
    public function __construct(private ExamAccessGuardService $guard) {}

    public function check(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:64'],
            'ip'       => ['required', 'string', 'ip'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $username = trim((string) $request->input('username'));
        $ip       = (string) $request->input('ip');

        $student = Student::query()
            ->where('student_id_number', $username)
            ->first();

        if (!$student) {
            // Unknown username at the LMS — don't claim wrong_computer,
            // just let Moodle proceed (it knows about its own users
            // independently). Login will fail later if the student
            // genuinely doesn't exist on the Moodle side either.
            return response()->json(['allowed' => true]);
        }

        $check = $this->guard->checkForLogin($student, $ip);

        $payload = [
            'allowed' => (bool) ($check['allowed'] ?? true),
            'reason' => $check['reason'] ?? null,
            'message' => $check['message'] ?? null,
            'expected_computer' => isset($check['expected_computer']) ? (int) $check['expected_computer'] : null,
            'computer_number' => isset($check['computer_number']) ? (int) $check['computer_number'] : null,
            'planned_start' => $check['planned_start'] ?? null,
        ];

        if (!$payload['allowed']) {
            Log::info('exam_login_check.blocked', [
                'username' => $username,
                'ip' => $ip,
                'reason' => $payload['reason'],
                'expected_computer' => $payload['expected_computer'],
                'computer_number' => $payload['computer_number'],
            ]);
        }

        return response()->json($payload);
    }
}

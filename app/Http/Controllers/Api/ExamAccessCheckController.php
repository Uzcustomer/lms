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
 * Server-to-server endpoint consulted by the Moodle quizaccess_lmsguard
 * plugin before each YN exam attempt is started or resumed. Authenticates
 * the calling Moodle instance with the same X-SYNC-SECRET shared secret
 * used by the existing photo-sync / descriptor-confirmed callbacks.
 *
 * Request body:
 *   {
 *     "username": "12345678",        // HEMIS username == student_id_number
 *     "ip": "10.0.0.42",             // student's client IP as Moodle sees it
 *     "quiz_idnumber": "yn-2026-..." // optional; only used for logs
 *   }
 *
 * Response (always 200 once auth passes — Moodle decides on `allowed`):
 *   {
 *     "allowed": false,
 *     "reason": "wrong_computer",
 *     "message": "Siz #5 kompyuterga biriktirilgansiz, hozir esa #12 kompyuterdasiz.",
 *     "expected_computer": 5,
 *     "computer_number": 12,
 *     "planned_start": "2026-05-09T09:00:00+05:00"
 *   }
 */
class ExamAccessCheckController extends Controller
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
            'username'      => ['required', 'string', 'max:64'],
            'ip'            => ['required', 'string', 'ip'],
            'quiz_idnumber' => ['nullable', 'string', 'max:191'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $username     = trim((string) $request->input('username'));
        $ip           = (string) $request->input('ip');
        $quizIdnumber = trim((string) $request->input('quiz_idnumber', ''));

        $student = Student::query()
            ->where('student_id_number', $username)
            ->first();

        if (!$student) {
            Log::info('exam_access_check.unknown_user', [
                'username' => $username,
                'ip' => $ip,
                'quiz_idnumber' => $quizIdnumber,
            ]);

            return response()->json([
                'allowed' => false,
                'reason' => 'unknown_user',
                'message' => 'Talaba LMS bazasida topilmadi.',
                'expected_computer' => null,
                'computer_number' => null,
                'planned_start' => null,
            ]);
        }

        $check = $this->guard->check($student, $ip);

        $payload = [
            'allowed' => (bool) ($check['allowed'] ?? false),
            'reason' => $check['reason'] ?? ($check['allowed'] ? 'allowed' : 'denied'),
            'message' => $check['message'] ?? null,
            'expected_computer' => isset($check['expected_computer']) ? (int) $check['expected_computer'] : null,
            'computer_number' => isset($check['computer_number']) ? (int) $check['computer_number'] : null,
            'planned_start' => $check['planned_start'] ?? null,
        ];

        Log::info('exam_access_check', [
            'username' => $username,
            'ip' => $ip,
            'quiz_idnumber' => $quizIdnumber,
            'allowed' => $payload['allowed'],
            'reason' => $payload['reason'],
        ]);

        return response()->json($payload);
    }
}

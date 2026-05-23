<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ExamLandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Server-to-server endpoint called by the Moodle auth_faceid plugin right
 * after a successful FaceID login. It mints a short-lived signed token for
 * the student and returns the absolute URL of the LMS trilingual exam
 * language picker. The plugin redirects the browser there instead of
 * dropping the student straight onto /mod/quiz/view.php — that way mixed-
 * language groups can pick their preferred language at exam time.
 *
 * Auth: shared X-SYNC-SECRET header (mirrors ExamQuizTargetController).
 * Network / config failure on this endpoint is non-fatal on the Moodle
 * side — the plugin falls back to its existing direct-to-quiz redirect.
 */
class ExamLandingTokenController extends Controller
{
    public function __construct(private ExamLandingService $landing) {}

    public function issue(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $username = trim((string) $request->input('username'));

        $student = Student::query()
            ->where('student_id_number', $username)
            ->first();
        if (!$student) {
            return response()->json([
                'ok' => false,
                'error' => 'unknown_user',
            ], 404);
        }

        $token = $this->landing->issueToken($student);

        // Prefer config('app.url') for the absolute base — route()'s default
        // scheme/host can resolve to the request host (Moodle), which would
        // produce a non-functional URL when the browser later follows it.
        $base = rtrim((string) config('app.url', ''), '/');
        $path = route('exam.landing', $token, false); // path only
        $url = $base !== '' ? $base . $path : route('exam.landing', $token);

        Log::info('exam_landing.token_issued', [
            'username' => $username,
            'student_id' => $student->id,
        ]);

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'url'   => $url,
        ]);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\ExamAccessGuardService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces that a student starting/resuming a YN exam is physically seated
 * at the computer assigned to them. Computer identity is derived from the
 * request IP via the configured prefix/offset (see Computer::numberByIp).
 *
 * Apply this middleware to routes that hand the student off to Moodle
 * (e.g. POST /student/exam/start). On failure, returns 403 with a
 * structured payload so the frontend can surface a clear error.
 */
class EnforceAssignedComputer
{
    public function __construct(private ExamAccessGuardService $guard) {}

    public function handle(Request $request, Closure $next)
    {
        $student = Auth::guard('student')->user();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya kerak.'], 401);
        }

        $check = $this->guard->check($student, $request->ip());
        if (!empty($check['allowed'])) {
            $request->attributes->set('exam_access', $check);
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'reason' => $check['reason'] ?? 'denied',
            'message' => $check['message'] ?? 'Bu kompyuterda testni boshlashga ruxsat yo\'q.',
            'expected_computer' => $check['expected_computer'] ?? null,
            'computer_number' => $check['computer_number'] ?? null,
            'planned_start' => $check['planned_start'] ?? null,
        ], 403);
    }
}

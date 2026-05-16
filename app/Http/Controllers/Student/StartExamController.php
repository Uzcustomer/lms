<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\ComputerAssignment;
use App\Services\ExamAccessGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pre-flight check before a student is handed off to Moodle for their YN
 * exam. Validates that the student is physically seated at the computer
 * assigned to them (IP-based) and that the slot is currently active.
 *
 * Frontend flow:
 *   1. Student clicks "Testni boshlash" on /student/exam-schedule.
 *   2. Browser POSTs /student/exam/start; if allowed, response includes
 *      the Moodle URL the student should be redirected to.
 *   3. On denial, the response contains a structured reason so the UI
 *      can render an actionable error.
 */
class StartExamController extends Controller
{
    public function __construct(private ExamAccessGuardService $guard) {}

    public function start(Request $request)
    {
        $student = Auth::guard('student')->user();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya kerak.'], 401);
        }

        $check = $this->guard->check($student, $request->ip());
        if (empty($check['allowed'])) {
            return response()->json([
                'success' => false,
                'reason' => $check['reason'] ?? 'denied',
                'message' => $check['message'] ?? 'Bu kompyuterda testni boshlashga ruxsat yo\'q.',
                'expected_computer' => $check['expected_computer'] ?? null,
                'computer_number' => $check['computer_number'] ?? null,
                'planned_start' => $check['planned_start'] ?? null,
            ], 403);
        }

        $moodleUrl = (string) config('services.moodle.ws_url');
        // Strip the WS path if present; the student needs the site root.
        $moodleSite = preg_replace('#/webservice/.*$#', '', $moodleUrl);

        return response()->json([
            'success' => true,
            'computer_number' => $check['computer_number'],
            'planned_start' => $check['planned_start'],
            'moodle_url' => $moodleSite ?: null,
        ]);
    }

    /**
     * GET endpoint for the student panel: returns the assignment that is
     * currently relevant (today, with reveal_at passed) and whether the
     * computer number should be visible. Used to drive the UI countdown.
     */
    public function status(Request $request)
    {
        $student = Auth::guard('student')->user();
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya kerak.'], 401);
        }

        $now = now();
        $assignment = ComputerAssignment::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->whereDate('planned_start', $now->toDateString())
            ->orderBy('planned_start')
            ->first();

        if (!$assignment) {
            return response()->json(['success' => true, 'has_assignment' => false]);
        }

        $revealed = $assignment->isComputerRevealed($now);
        $detected = Computer::numberByIp($request->ip());

        return response()->json([
            'success' => true,
            'has_assignment' => true,
            'revealed' => $revealed,
            'computer_number' => $revealed ? (int) $assignment->computer_number : null,
            'reveal_at' => optional($assignment->reveal_at)->toIso8601String(),
            'planned_start' => $assignment->planned_start->toIso8601String(),
            'planned_end' => $assignment->planned_end->toIso8601String(),
            'status' => $assignment->status,
            'is_reserve' => (bool) $assignment->is_reserve,
            'detected_computer' => $detected,
            'on_correct_computer' => $revealed && $detected !== null
                ? ((int) $assignment->computer_number === (int) $detected)
                : null,
        ]);
    }
}

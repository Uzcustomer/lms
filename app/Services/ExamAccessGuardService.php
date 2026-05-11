<?php

namespace App\Services;

use App\Models\Computer;
use App\Models\ComputerAssignment;
use App\Models\Student;
use Carbon\Carbon;

class ExamAccessGuardService
{
    /**
     * Check whether the given student is allowed to start their YN exam from
     * the given client IP.
     *
     * @return array{allowed:bool, reason?:string, computer_number?:int, expected_computer?:int, planned_start?:string}
     */
    public function check(Student $student, ?string $clientIp, ?Carbon $now = null): array
    {
        // Seat-binding bypass: when the institution runs the
        // "any-PC-in-the-window" model, this guard becomes a no-op and
        // entry is governed purely by the Moodle-side time window
        // (quizaccess_examwindow ± N minutes around the scheduled
        // time). Flip EXAM_ENFORCE_COMPUTER_BINDING=true in .env to
        // re-enable the strict per-PC checks below.
        if (!config('services.exam_access.enforce_computer_binding', false)) {
            return ['allowed' => true];
        }

        $now ??= now();

        $detectedNumber = Computer::numberByIp($clientIp);
        if ($detectedNumber === null) {
            return [
                'allowed' => false,
                'reason' => 'unknown_ip',
                'message' => "Sizning IP manzilingiz ({$clientIp}) test markazi kompyuterlari ro'yxatida topilmadi.",
            ];
        }

        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));
        $assignment = ComputerAssignment::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('planned_start', '<=', $now->copy()->addMinutes($window))
            ->where('planned_end', '>=', $now->copy()->subMinutes($window))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->orderBy('planned_start')
            ->first();

        if (!$assignment) {
            return [
                'allowed' => false,
                'reason' => 'no_active_assignment',
                'message' => 'Hozirgi vaqtga sizga belgilangan test slot topilmadi.',
                'computer_number' => $detectedNumber,
            ];
        }

        if ((int) $assignment->computer_number !== (int) $detectedNumber) {
            return [
                'allowed' => false,
                'reason' => 'wrong_computer',
                'message' => "Siz #{$assignment->computer_number} kompyuterga biriktirilgansiz, hozir esa #{$detectedNumber} kompyuterdasiz.",
                'computer_number' => $detectedNumber,
                'expected_computer' => (int) $assignment->computer_number,
                'planned_start' => optional($assignment->planned_start)->toIso8601String(),
            ];
        }

        if ($now->lt($assignment->planned_start->copy()->subMinutes($window))) {
            return [
                'allowed' => false,
                'reason' => 'too_early',
                'message' => 'Test boshlanishiga vaqt bor. Iltimos, kuting.',
                'computer_number' => $detectedNumber,
                'planned_start' => $assignment->planned_start->toIso8601String(),
            ];
        }

        return [
            'allowed' => true,
            'computer_number' => (int) $assignment->computer_number,
            'planned_start' => $assignment->planned_start->toIso8601String(),
        ];
    }

    /**
     * Lightweight check used at LOGIN time (FaceID page) to fail fast when
     * a student walks up to the wrong computer during their exam slot.
     *
     * The full ::check() above is too strict for login: it fails on
     * `unknown_ip` (IP not registered as a test-centre PC) and on
     * `no_active_assignment`, both of which are perfectly normal for
     * everyday Moodle login from outside the test centre or on a non-exam
     * day. Blocking those at login would lock students out of homework /
     * results browsing.
     *
     * This method only blocks the one case that matters at login: the
     * student HAS an active exam assignment AND their current IP belongs
     * to a different test-centre PC than the one they were assigned. All
     * other cases — no assignment, IP not in test-centre at all, IP
     * matches assignment — fall through as allowed. The full ::check()
     * still runs at quiz-attempt time to catch anything this method
     * intentionally lets through.
     */
    public function checkForLogin(Student $student, ?string $clientIp, ?Carbon $now = null): array
    {
        // Same seat-binding bypass as ::check(). Without it, every FaceID
        // login would have to pass the wrong-computer check even when
        // the institution explicitly opted out of seat binding. The
        // Moodle-side time-window rule still enforces ±N min around the
        // scheduled exam time, so this only relaxes the SEAT check.
        if (!config('services.exam_access.enforce_computer_binding', false)) {
            return ['allowed' => true];
        }

        $now ??= now();

        $detectedNumber = Computer::numberByIp($clientIp);
        if ($detectedNumber === null) {
            return ['allowed' => true];
        }

        $window = max(1, (int) config('services.moodle.open_window_minutes', 10));
        $assignment = ComputerAssignment::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('planned_start', '<=', $now->copy()->addMinutes($window))
            ->where('planned_end', '>=', $now->copy()->subMinutes($window))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->orderBy('planned_start')
            ->first();

        if (!$assignment) {
            return ['allowed' => true];
        }

        if ((int) $assignment->computer_number !== (int) $detectedNumber) {
            return [
                'allowed' => false,
                'reason' => 'wrong_computer',
                'message' => "Bu sizning kompyuteringiz emas. Siz #{$assignment->computer_number} kompyuterga "
                          . "biriktirilgansiz, hozir esa #{$detectedNumber} kompyuterdasiz. "
                          . "O'z joyingizga o'tib qayta urining.",
                'computer_number' => $detectedNumber,
                'expected_computer' => (int) $assignment->computer_number,
                'planned_start' => optional($assignment->planned_start)->toIso8601String(),
            ];
        }

        return [
            'allowed' => true,
            'computer_number' => (int) $assignment->computer_number,
            'expected_computer' => (int) $assignment->computer_number,
            'planned_start' => $assignment->planned_start->toIso8601String(),
        ];
    }
}

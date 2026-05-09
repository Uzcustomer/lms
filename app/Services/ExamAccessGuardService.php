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
}

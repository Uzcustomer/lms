<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\DeanExamReschedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dekanat tomonidan kech qolgan talabaning imtihon vaqtini SHU KUN
 * ichida boshqa bo'sh slotga ko'chirish.
 *
 * Cheklovlar:
 *   - Bir talabaga bir kun ichida faqat BIR MARTA reschedule qilish mumkin
 *     (`dean_exam_reschedules.dean_reschedule_one_per_day` unique indeksi).
 *   - Yangi vaqt original sana bilan bir xil kunda bo'lishi shart.
 *   - Yangi vaqt ish soatlari ichida va tushlik vaqtiga ust-ust tushmasligi
 *     kerak.
 *   - Yangi vaqtda kamida bitta bo'sh kompyuter mavjud bo'lishi shart
 *     (ExamCapacityService settings: computer_count).
 *
 * Test markazi rolining "edit today" toggle holatiga e'tibor bermaydi —
 * bu alohida dekanat huquqi.
 */
class DeanExamRescheduleService
{
    /**
     * Talaba uchun shu kun ichida dekanat reschedule huquqidan
     * allaqachon foydalanilganmi.
     */
    public function alreadyUsedToday(string $studentHemisId, string $date): bool
    {
        return DeanExamReschedule::where('student_hemis_id', $studentHemisId)
            ->whereDate('used_date', $date)
            ->exists();
    }

    /**
     * Berilgan kun uchun bo'sh slotlar ro'yxati.
     *
     * @return array<int, array{time:string, free:int, capacity:int}>
     */
    public function availableSlots(string $date, ?Carbon $afterTime = null): array
    {
        $settings = ExamCapacityService::getSettingsForDate($date);
        $duration = max(1, (int) ($settings['test_duration_minutes'] ?? 15));
        $buffer = max(0, (int) config('services.moodle.computer_buffer_minutes', 0));
        $slotLen = $duration + $buffer;
        $capacity = max(1, (int) ($settings['computer_count'] ?? 60));

        $workStart = Carbon::parse($date . ' ' . $settings['work_hours_start']);
        $workEnd = Carbon::parse($date . ' ' . $settings['work_hours_end']);

        $lunchStart = !empty($settings['lunch_start'])
            ? Carbon::parse($date . ' ' . $settings['lunch_start'])
            : null;
        $lunchEnd = !empty($settings['lunch_end'])
            ? Carbon::parse($date . ' ' . $settings['lunch_end'])
            : null;

        $cursor = $workStart->copy();
        if ($afterTime && $afterTime->gt($cursor)) {
            // Joriy vaqtdan keyingi slotga yaxlitlash
            $minsFromStart = $workStart->diffInMinutes($afterTime);
            $skipSlots = (int) ceil($minsFromStart / $duration);
            $cursor = $workStart->copy()->addMinutes($skipSlots * $duration);
        }

        $slots = [];
        while ($cursor->copy()->addMinutes($slotLen)->lte($workEnd)) {
            $slotStart = $cursor->copy();
            $slotEnd = $slotStart->copy()->addMinutes($slotLen);

            $overlapsLunch = $lunchStart && $lunchEnd
                && $slotStart->lt($lunchEnd) && $slotEnd->gt($lunchStart);

            if (!$overlapsLunch) {
                $occupied = $this->occupiedComputerCount($slotStart, $slotEnd);
                $free = max(0, $capacity - $occupied);
                if ($free > 0) {
                    $slots[] = [
                        'time' => $slotStart->format('H:i'),
                        'free' => $free,
                        'capacity' => $capacity,
                    ];
                }
            }

            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * Berilgan vaqt oralig'ida band kompyuterlar soni
     * (ComputerAssignment.overlap).
     */
    private function occupiedComputerCount(Carbon $start, Carbon $end): int
    {
        return (int) ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->distinct('computer_number')
            ->count('computer_number');
    }

    /**
     * Berilgan vaqt oralig'ida bo'sh kompyuter raqamini topish.
     * $excludeAssignmentId — qayta belgilanayotgan o'sha talaba assignmenti
     * (eski slotda turibsa, uni hisobdan chiqarish).
     */
    public function pickFreeComputer(Carbon $start, Carbon $end, ?int $excludeAssignmentId = null): ?int
    {
        $totalComputers = (int) config('services.moodle.total_computers', 60);
        $totalComputers = max(1, $totalComputers);

        $occupied = ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->when($excludeAssignmentId, fn ($q) => $q->where('id', '!=', $excludeAssignmentId))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->pluck('computer_number')
            ->unique()
            ->all();

        $available = array_values(array_diff(range(1, $totalComputers), $occupied));
        if (empty($available)) {
            return null;
        }
        // Tasodifiy emas — eng kichik bo'sh raqam — proktor uchun
        // jadval barqaror bo'lishi uchun.
        return $available[0];
    }

    /**
     * Reschedule operatsiyasini bajaradi.
     *
     * @param  int     $deanUserId  Dekanat user id (auditga yoziladi)
     * @param  ComputerAssignment  $assignment  Reschedule qilinadigan talaba assignmenti
     * @param  string  $newTime     'HH:MM' formatda yangi vaqt
     * @param  string|null  $reason  Ixtiyoriy izoh
     * @return array{ok:bool, error?:string, reschedule?:DeanExamReschedule}
     */
    public function reschedule(
        int $deanUserId,
        ComputerAssignment $assignment,
        string $newTime,
        ?string $reason = null
    ): array {
        $originalStart = $assignment->planned_start;
        $originalEnd = $assignment->planned_end;
        $date = $originalStart->format('Y-m-d');

        if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            return ['ok' => false, 'error' => "Vaqt formati noto'g'ri (HH:MM)."];
        }

        $settings = ExamCapacityService::getSettingsForDate($date);
        $duration = max(1, (int) ($settings['test_duration_minutes'] ?? 15));
        $buffer = max(0, (int) config('services.moodle.computer_buffer_minutes', 0));

        try {
            $newStart = Carbon::parse($date . ' ' . $newTime, config('app.timezone'));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => "Yangi vaqtni o'qib bo'lmadi."];
        }
        $newEnd = $newStart->copy()->addMinutes($duration + $buffer);

        // Original sana bilan bir kun bo'lishi shart
        if ($newStart->format('Y-m-d') !== $date) {
            return ['ok' => false, 'error' => 'Yangi vaqt original sana bilan bir kunda bo\'lishi kerak.'];
        }

        // Ish soatlari ichida
        $workStart = Carbon::parse($date . ' ' . $settings['work_hours_start']);
        $workEnd = Carbon::parse($date . ' ' . $settings['work_hours_end']);
        if ($newStart->lt($workStart) || $newEnd->gt($workEnd)) {
            return ['ok' => false, 'error' => "Yangi vaqt ish soatlari ({$settings['work_hours_start']}–{$settings['work_hours_end']}) ichida bo'lishi kerak."];
        }

        // Tushlik bilan ust-ust tushmasin
        if (ExamCapacityService::overlapsLunch($date, $newTime, $duration + $buffer, $settings)) {
            return ['ok' => false, 'error' => 'Yangi vaqt tushlik vaqtiga to\'g\'ri keladi.'];
        }

        // O'tib ketgan vaqtga reschedule qilib bo'lmaydi
        if ($newStart->lte(now())) {
            return ['ok' => false, 'error' => 'Yangi vaqt joriy vaqtdan keyin bo\'lishi kerak.'];
        }

        return DB::transaction(function () use (
            $deanUserId, $assignment, $newStart, $newEnd, $originalStart, $originalEnd, $date, $reason
        ) {
            // Bir kunda bir marta cheklovini transaction ichida ham tekshirib
            // ko'rish — race condition oldini olish. Aslida unique indeks ham bor.
            $alreadyUsed = DeanExamReschedule::where('student_hemis_id', $assignment->student_hemis_id)
                ->whereDate('used_date', $date)
                ->lockForUpdate()
                ->exists();
            if ($alreadyUsed) {
                return ['ok' => false, 'error' => 'Bu talaba uchun bugungi reschedule huquqidan allaqachon foydalanilgan.'];
            }

            $newComputer = $this->pickFreeComputer($newStart, $newEnd, $assignment->id);
            if (!$newComputer) {
                return ['ok' => false, 'error' => 'Tanlangan vaqtda bo\'sh kompyuter qolmadi.'];
            }

            $originalComputer = $assignment->computer_number;

            // History audit ichiga ham qo'shamiz
            $history = $assignment->history ?? [];
            $history[] = [
                'event' => 'dean_reschedule',
                'at' => now()->toIso8601String(),
                'by_user_id' => $deanUserId,
                'from_start' => $originalStart?->toIso8601String(),
                'from_end' => $originalEnd?->toIso8601String(),
                'from_computer' => $originalComputer,
                'to_start' => $newStart->toIso8601String(),
                'to_end' => $newEnd->toIso8601String(),
                'to_computer' => $newComputer,
                'reason' => $reason,
            ];

            $assignment->planned_start = $newStart;
            $assignment->planned_end = $newEnd;
            $assignment->computer_number = $newComputer;
            $assignment->is_pinned = true;
            $assignment->status = ComputerAssignment::STATUS_SCHEDULED;
            $assignment->actual_start = null;
            $assignment->actual_end = null;
            $assignment->history = $history;
            $assignment->save();

            $log = DeanExamReschedule::create([
                'exam_schedule_id' => $assignment->exam_schedule_id,
                'computer_assignment_id' => $assignment->id,
                'student_hemis_id' => $assignment->student_hemis_id,
                'yn_type' => $assignment->yn_type,
                'used_date' => $date,
                'original_start' => $originalStart,
                'original_end' => $originalEnd,
                'original_computer' => $originalComputer,
                'new_start' => $newStart,
                'new_end' => $newEnd,
                'new_computer' => $newComputer,
                'reason' => $reason,
                'created_by' => $deanUserId,
            ]);

            return ['ok' => true, 'reschedule' => $log];
        });
    }
}

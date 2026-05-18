<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\DeanExamReschedule;
use App\Models\ExamSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dekanat tomonidan kech qolgan guruhning butun imtihon vaqtini SHU KUN
 * ichida boshqa bo'sh slotga ko'chirish (guruh sathida).
 *
 * Cheklovlar:
 *   - Bir guruhning bir YN'ini bir kun ichida faqat BIR MARTA ko'chirish
 *     mumkin (`dean_exam_reschedules.dean_reschedule_one_per_group_per_day`
 *     unique indeksi).
 *   - Yangi vaqt original sana bilan bir kunda bo'lishi shart.
 *   - Yangi vaqt ish soatlari ichida va tushlikka tushmasligi kerak.
 *   - Yangi slotda guruhdagi BARCHA talabalar uchun yetarli bo'sh
 *     kompyuter bo'lishi shart (ExamCapacityService settings).
 *
 * Test markazi rolining "edit today" toggle holatiga e'tibor bermaydi —
 * bu alohida dekanat huquqi.
 */
class DeanExamRescheduleService
{
    public function __construct(private ComputerAssignmentService $computerAssignmentService) {}

    /**
     * Berilgan (exam_schedule, yn_type) uchun shu kun ichida dekanat
     * reschedule huquqidan allaqachon foydalanilganmi.
     */
    public function alreadyUsedToday(int $examScheduleId, string $ynType, string $date): bool
    {
        return DeanExamReschedule::where('exam_schedule_id', $examScheduleId)
            ->where('yn_type', $ynType)
            ->whereDate('used_date', $date)
            ->exists();
    }

    /**
     * Berilgan kun uchun bo'sh slotlar ro'yxati. Agar $requiredFree berilsa,
     * kamida shuncha kompyuter bo'sh bo'lgan slotlar qaytariladi.
     *
     * @return array<int, array{time:string, free:int, capacity:int}>
     */
    public function availableSlots(string $date, ?Carbon $afterTime = null, int $requiredFree = 1): array
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
                if ($free >= $requiredFree) {
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
     * Guruh ko'chirilganda hisobdan chiqarish uchun: berilgan (schedule,
     * yn_type) ning o'z ComputerAssignment'larini exclude qilib, vaqt
     * oralig'ida band kompyuterlar sonini sanaydi. Default holatda hech
     * narsa exclude qilinmaydi (umumiy bandlik).
     */
    private function occupiedComputerCount(
        Carbon $start,
        Carbon $end,
        ?int $excludeScheduleId = null,
        ?string $excludeYnType = null,
    ): int {
        return (int) ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->when(
                $excludeScheduleId !== null && $excludeYnType !== null,
                fn ($q) => $q->where(function ($q2) use ($excludeScheduleId, $excludeYnType) {
                    $q2->where('exam_schedule_id', '!=', $excludeScheduleId)
                        ->orWhere('yn_type', '!=', $excludeYnType);
                })
            )
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->distinct('computer_number')
            ->count('computer_number');
    }

    /**
     * Guruhning butun YN vaqtini boshqa slotga ko'chirish.
     *
     * @param  int          $deanUserId  Dekanat user id (auditga yoziladi)
     * @param  ExamSchedule $schedule
     * @param  string       $ynType      'oski' yoki 'test'
     * @param  string       $newTime     'HH:MM' formatda yangi vaqt
     * @param  string|null  $reason      Ixtiyoriy izoh
     * @return array{ok:bool, error?:string, reschedule?:DeanExamReschedule, student_count?:int}
     */
    public function reschedule(
        int $deanUserId,
        ExamSchedule $schedule,
        string $ynType,
        string $newTime,
        ?string $reason = null
    ): array {
        $ynType = strtolower($ynType);
        if (!in_array($ynType, ['oski', 'test'], true)) {
            return ['ok' => false, 'error' => "YN turi noto'g'ri (oski|test)."];
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $newTime)) {
            return ['ok' => false, 'error' => "Vaqt formati noto'g'ri (HH:MM)."];
        }

        $dateField = $ynType . '_date';
        $timeField = $ynType . '_time';
        $naField = $ynType . '_na';

        if ($schedule->{$naField}) {
            return ['ok' => false, 'error' => 'Bu imtihon N/A deb belgilangan.'];
        }
        if (empty($schedule->{$dateField})) {
            return ['ok' => false, 'error' => 'Bu imtihonga sana belgilanmagan.'];
        }

        $date = $schedule->{$dateField} instanceof Carbon
            ? $schedule->{$dateField}->format('Y-m-d')
            : Carbon::parse((string) $schedule->{$dateField})->format('Y-m-d');

        $settings = ExamCapacityService::getSettingsForDate($date);
        $duration = max(1, (int) ($settings['test_duration_minutes'] ?? 15));
        $buffer = max(0, (int) config('services.moodle.computer_buffer_minutes', 0));

        try {
            $newStart = Carbon::parse($date . ' ' . $newTime, config('app.timezone'));
        } catch (\Throwable) {
            return ['ok' => false, 'error' => "Yangi vaqtni o'qib bo'lmadi."];
        }
        $newEnd = $newStart->copy()->addMinutes($duration + $buffer);

        $workStart = Carbon::parse($date . ' ' . $settings['work_hours_start']);
        $workEnd = Carbon::parse($date . ' ' . $settings['work_hours_end']);
        if ($newStart->lt($workStart) || $newEnd->gt($workEnd)) {
            return ['ok' => false, 'error' => "Yangi vaqt ish soatlari ({$settings['work_hours_start']}–{$settings['work_hours_end']}) ichida bo'lishi kerak."];
        }
        if (ExamCapacityService::overlapsLunch($date, $newTime, $duration + $buffer, $settings)) {
            return ['ok' => false, 'error' => 'Yangi vaqt tushlik vaqtiga to\'g\'ri keladi.'];
        }
        if ($newStart->lte(now())) {
            return ['ok' => false, 'error' => 'Yangi vaqt joriy vaqtdan keyin bo\'lishi kerak.'];
        }

        // Guruhdagi talabalar soni (assign() shu mantiqdan foydalanadi)
        $studentCount = (int) Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->count();
        if ($studentCount < 1) {
            return ['ok' => false, 'error' => 'Guruhda imtihonga kiruvchi talabalar topilmadi.'];
        }

        // Yangi slotda guruh uchun yetarli bo'sh kompyuter borligini
        // tekshirish (joriy guruh assignmentlarini exclude qilib).
        $capacity = (int) ($settings['computer_count'] ?? 60);
        $occupied = $this->occupiedComputerCount($newStart, $newEnd, $schedule->id, $ynType);
        $free = max(0, $capacity - $occupied);
        if ($free < $studentCount) {
            return [
                'ok' => false,
                'error' => "Yangi slotda yetarli bo'sh kompyuter yo'q: kerak {$studentCount}, bo'sh {$free}.",
            ];
        }

        return DB::transaction(function () use (
            $deanUserId, $schedule, $ynType, $newTime, $newStart, $date, $timeField, $reason, $studentCount
        ) {
            // Race condition oldini olish — unique indeks ham bor.
            $alreadyUsed = DeanExamReschedule::where('exam_schedule_id', $schedule->id)
                ->where('yn_type', $ynType)
                ->whereDate('used_date', $date)
                ->lockForUpdate()
                ->exists();
            if ($alreadyUsed) {
                return ['ok' => false, 'error' => 'Bu guruh uchun bugungi reschedule huquqidan allaqachon foydalanilgan.'];
            }

            $originalTime = $schedule->{$timeField}
                ? substr((string) $schedule->{$timeField}, 0, 5)
                : null;

            // Guruh sathida vaqtni yangilash — saved() hooki Moodle ga re-push
            // qiladi. assign() yangi vaqt asosida ComputerAssignment'larni
            // qayta yaratadi (eski sxedullarni o'chirib).
            $schedule->{$timeField} = $newTime;
            $schedule->save();

            $assignResult = $this->computerAssignmentService->assign($schedule, $ynType);
            if (empty($assignResult['ok'])) {
                // Transaksiya rollback bo'ladi (exception orqali)
                throw new \RuntimeException(
                    "Kompyuter biriktirishda xatolik: " . ($assignResult['reason'] ?? 'noma\'lum sabab')
                );
            }

            $log = DeanExamReschedule::create([
                'exam_schedule_id' => $schedule->id,
                'yn_type' => $ynType,
                'used_date' => $date,
                'original_time' => $originalTime,
                'new_time' => $newTime,
                'student_count' => $assignResult['count'] ?? $studentCount,
                'reason' => $reason,
                'created_by' => $deanUserId,
            ]);

            return [
                'ok' => true,
                'reschedule' => $log,
                'student_count' => $assignResult['count'] ?? $studentCount,
            ];
        });
    }
}

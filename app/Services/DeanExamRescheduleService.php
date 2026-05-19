<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\DeanExamReschedule;
use App\Models\ExamSchedule;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dekanat tomonidan kech qolgan guruhning butun YN vaqtini SHU KUN ichida
 * boshqa slotga ko'chirish (guruh sathida — butun guruh birga ko'chiriladi).
 *
 * Cheklovlar:
 *   - Bir guruhning bir YN'ini bir kun ichida faqat BIR MARTA ko'chirish
 *     mumkin (`dean_exam_reschedules.dean_reschedule_one_per_group_per_day`
 *     unique indeksi).
 *   - Yangi vaqt original sana bilan bir kunda bo'lishi shart.
 *   - Yangi vaqt ish soatlari ichida va tushlikka tushmasligi kerak.
 *   - Yangi slotda yetarli bo'sh kompyuter bo'lishi kerak. Yetmasa
 *     ogohlantirish chiqadi — dekanat ataylab ($force=true bilan) majbur
 *     qilib o'tkaza oladi.
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
     * Berilgan kun uchun BARCHA slotlarni qaytaradi (ish soatlari ichida,
     * tushlikni o'tkazib). Har slot uchun:
     *   - free: bo'sh kompyuterlar (talabalar) soni; salbiy ham bo'lishi
     *     mumkin emas (0 ga cheklanadi)
     *   - capacity: jami sig'im
     *   - enough: $requiredFree ga yetadimi (yo'qsa UI ogohlantirish chiqaradi)
     *
     * Bandlikni ikkala ko'rsatkichdan eng yomonidan oladi:
     *   - intent_free: ExamCapacityService::concurrentStudentsForSlot
     *     (Bandlik ko'rsatkichi bilan bir xil — exam_schedules sathi)
     *   - physical_free: computer_assignments orqali band kompyuter raqamlari
     *     (avval ko'chirilgan, exam_schedules da ko'rinmaydigan talabalarni
     *     ham hisobga oladi)
     *
     * @return array<int, array{time:string, free:int, capacity:int, enough:bool}>
     */
    public function availableSlots(
        string $date,
        ?Carbon $afterTime = null,
        int $requiredFree = 1,
        ?array $exclude = null
    ): array {
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
                $free = $this->slotFreeCount($date, $slotStart, $slotEnd, $capacity, $exclude);
                $slots[] = [
                    'time' => $slotStart->format('H:i'),
                    'free' => $free,
                    'capacity' => $capacity,
                    'enough' => $free >= $requiredFree,
                ];
            }

            $cursor->addMinutes($duration);
        }

        return $slots;
    }

    /**
     * Slot uchun bo'sh kompyuterlar soni — intent (guruh sathi) va physical
     * (band kompyuter raqamlari) dan eng yomonini oladi.
     */
    private function slotFreeCount(
        string $date,
        Carbon $start,
        Carbon $end,
        int $capacity,
        ?array $exclude
    ): int {
        $concurrent = ExamCapacityService::concurrentStudentsForSlot(
            $date,
            $start->format('H:i'),
            $exclude
        );
        $intentFree = max(0, $capacity - $concurrent);

        $occupiedNumbers = ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->distinct('computer_number')
            ->count('computer_number');
        $physicalFree = max(0, $capacity - $occupiedNumbers);

        return min($intentFree, $physicalFree);
    }

    /**
     * Berilgan ExamSchedule + yn_type uchun bandlik istisnosini quradi —
     * concurrentStudentsForSlot() ga uzatish uchun (guruhning o'z hissasi
     * yangi vaqtda o'ziga qarshi sanalmasin).
     */
    public function excludeKeyFor(ExamSchedule $schedule, string $ynType): array
    {
        return [
            'group_hemis_id' => $schedule->group_hemis_id,
            'subject_id' => $schedule->subject_id,
            'semester_code' => $schedule->semester_code,
            'yn_type' => $ynType,
            'attempt' => 1,
        ];
    }

    /**
     * Guruhning butun YN vaqtini boshqa slotga ko'chirish.
     *
     * @param  bool  $force  true bo'lsa, sig'im yetmasa ham o'tkazadi
     *                       (faqat dekanat ataylab tasdiqlaganida).
     *
     * @return array{ok:bool, error?:string, reschedule?:DeanExamReschedule, student_count?:int}
     */
    public function reschedule(
        int $deanUserId,
        ExamSchedule $schedule,
        string $ynType,
        string $newTime,
        ?string $reason = null,
        bool $force = false
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

        // Guruhdagi talabalar soni
        $studentCount = (int) Student::where('group_id', $schedule->group_hemis_id)
            ->whereNotNull('student_id_number')
            ->count();
        if ($studentCount < 1) {
            return ['ok' => false, 'error' => 'Guruhda imtihonga kiruvchi talabalar topilmadi.'];
        }

        // Sig'im tekshiruvi — $force bo'lsa o'tkazib yuboramiz (faqat
        // ogohlantirish frontendda chiqdi va dekanat ataylab tasdiqladi).
        if (!$force) {
            $capacity = (int) ($settings['computer_count'] ?? 60);
            $free = $this->slotFreeCount(
                $date, $newStart, $newEnd, $capacity,
                $this->excludeKeyFor($schedule, $ynType)
            );
            if ($free < $studentCount) {
                return [
                    'ok' => false,
                    'error' => "Yangi slotda yetarli bo'sh kompyuter yo'q: kerak {$studentCount}, bo'sh {$free}.",
                    'capacity_short' => true,
                    'needed' => $studentCount,
                    'free' => $free,
                ];
            }
        }

        return DB::transaction(function () use (
            $deanUserId, $schedule, $ynType, $newTime, $date, $timeField, $reason, $studentCount
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
                // Transaksiya rollback (exception orqali)
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

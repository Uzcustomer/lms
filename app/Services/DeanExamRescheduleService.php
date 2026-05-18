<?php

namespace App\Services;

use App\Models\ComputerAssignment;
use App\Models\DeanExamReschedule;
use App\Models\ExamSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dekanat tomonidan guruhdagi "qoldi" talabalarni (hali topshirmaganlar,
 * status='scheduled') SHU KUN ichida boshqa bo'sh slotga ko'chirish.
 *
 * "Topshirdi" (finished/abandoned) yoki "in_progress" statusidagi talabalar
 * o'z joylarida qoladi — faqat hali kelmagan/boshlamaganlar ko'chiriladi.
 * Bu Bandlik ko'rsatkichi sahifasidagi "Topshirdi/Qoldi" mantiqi bilan
 * mos.
 *
 * Cheklovlar:
 *   - Bir guruhning bir YN'ini bir kun ichida faqat BIR MARTA ko'chirish
 *     mumkin (`dean_exam_reschedules.dean_reschedule_one_per_group_per_day`
 *     unique indeksi).
 *   - Yangi vaqt original sana bilan bir kunda bo'lishi shart.
 *   - Yangi vaqt ish soatlari ichida va tushlikka tushmasligi kerak.
 *   - Yangi slotda QOLDI talabalar soni uchun yetarli bo'sh kompyuter
 *     bo'lishi shart (ExamCapacityService::concurrentStudentsForSlot
 *     bilan tekshiriladi).
 *
 * Test markazi rolining "edit today" toggle holatiga e'tibor bermaydi —
 * bu alohida dekanat huquqi. exam_schedules.{yn}_time ga tegmaydi —
 * guruhning rasmiy vaqti o'zgarmaydi (Topshirgan talabalar tegmay qolsin).
 * Faqat ko'chiriladigan talabalarning computer_assignments ustunlari
 * yangilanadi.
 */
class DeanExamRescheduleService
{
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
     * Berilgan (schedule, yn_type) uchun shu kunga "qoldi" (status='scheduled')
     * talabalar soni. Bu — dekanat ko'chira oladigan haqiqiy talabalar soni.
     */
    public function pendingCount(int $examScheduleId, string $ynType, string $date): int
    {
        return (int) ComputerAssignment::where('exam_schedule_id', $examScheduleId)
            ->where('yn_type', $ynType)
            ->whereDate('planned_start', $date)
            ->where('status', ComputerAssignment::STATUS_SCHEDULED)
            ->count();
    }

    /**
     * Berilgan kun uchun bo'sh slotlar ro'yxati. Kamida $requiredFree
     * kompyuter bo'sh bo'lgan slotlar qaytariladi.
     *
     * Bandlikni `ExamCapacityService::concurrentStudentsForSlot()` orqali
     * hisoblaydi — Bandlik ko'rsatkichi bilan bir xil mantiq.
     *
     * @return array<int, array{time:string, free:int, capacity:int}>
     */
    public function availableSlots(
        string $date,
        ?Carbon $afterTime = null,
        int $requiredFree = 1
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
                // exam_schedules orqali guruh-darajadagi yuk
                $concurrent = ExamCapacityService::concurrentStudentsForSlot(
                    $date,
                    $slotStart->format('H:i'),
                    null
                );
                // Ilgari ko'chirilgan (dean-moved) talabalar exam_schedules da
                // boshqa vaqtda turgan bo'lishi mumkin — ular computer_assignments
                // orqali band kompyuterlarni egallaydi. Shu sababli ikkala
                // ko'rsatkichdan eng yomonini olamiz.
                $occupiedComputers = $this->occupiedComputerNumbers($slotStart, $slotEnd);
                $intentFree = max(0, $capacity - $concurrent);
                $physicalFree = max(0, $capacity - count($occupiedComputers));
                $free = min($intentFree, $physicalFree);

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
     * Berilgan vaqt oralig'ida band bo'lgan distinct computer_number lar.
     */
    private function occupiedComputerNumbers(Carbon $start, Carbon $end, array $excludeAssignmentIds = []): array
    {
        return ComputerAssignment::query()
            ->where('planned_end', '>', $start)
            ->where('planned_start', '<', $end)
            ->when(!empty($excludeAssignmentIds), fn ($q) => $q->whereNotIn('id', $excludeAssignmentIds))
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ])
            ->pluck('computer_number')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Yangi slotda $needed ta bo'sh kompyuter raqamini topadi (eng kichik
     * raqamdan boshlab). $excludeAssignmentIds — ko'chirilayotgan
     * assignmentlarning o'zlarini hisobdan chiqarish uchun.
     *
     * @return int[]|null  Bo'sh kompyuterlar yetmasa null
     */
    private function pickFreeComputers(Carbon $start, Carbon $end, int $needed, array $excludeAssignmentIds = []): ?array
    {
        $totalComputers = max(1, (int) config('services.moodle.total_computers', 60));
        $occupied = $this->occupiedComputerNumbers($start, $end, $excludeAssignmentIds);
        $available = array_values(array_diff(range(1, $totalComputers), $occupied));
        if (count($available) < $needed) {
            return null;
        }
        return array_slice($available, 0, $needed);
    }

    /**
     * Guruhdagi "qoldi" talabalarini yangi vaqtga ko'chirish.
     *
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

        return DB::transaction(function () use (
            $deanUserId, $schedule, $ynType, $newTime, $newStart, $newEnd,
            $date, $timeField, $reason, $settings
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

            // Faqat "qoldi" — status='scheduled' assignmentlarini lock va o'qish.
            // finished/abandoned/in_progress qatorlar tegmay qoladi.
            $pending = ComputerAssignment::where('exam_schedule_id', $schedule->id)
                ->where('yn_type', $ynType)
                ->whereDate('planned_start', $date)
                ->where('status', ComputerAssignment::STATUS_SCHEDULED)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $count = $pending->count();
            if ($count === 0) {
                return [
                    'ok' => false,
                    'error' => 'Bu guruhda ko\'chirilishi kerak bo\'lgan talaba yo\'q (hammasi topshirgan yoki ro\'yxat hali tuzilmagan).',
                ];
            }

            // Sig'im tekshiruvi: ikkala ko'rsatkichdan eng yomon (guruh-darajadagi
            // intent + computer-darajadagi haqiqiy bandlik).
            $capacity = (int) ($settings['computer_count'] ?? 60);
            $concurrent = ExamCapacityService::concurrentStudentsForSlot($date, $newTime, null);
            $intentFree = max(0, $capacity - $concurrent);
            $pendingIds = $pending->pluck('id')->all();
            $computers = $this->pickFreeComputers($newStart, $newEnd, $count, $pendingIds);

            if ($intentFree < $count) {
                return [
                    'ok' => false,
                    'error' => "Yangi slotda yetarli bandlik yo'q: kerak {$count}, bo'sh {$intentFree}.",
                ];
            }
            if ($computers === null) {
                return [
                    'ok' => false,
                    'error' => "Yangi slotda yetarli bo'sh kompyuter qolmadi (raqamlar bo'yicha).",
                ];
            }

            $originalTime = $schedule->{$timeField}
                ? substr((string) $schedule->{$timeField}, 0, 5)
                : null;

            // Har bir qoldi assignmentni yangi vaqt va yangi kompyuterga
            // o'tkazish. status va actual_* maydonlar tegmaydi (assignment
            // hali boshlanmagan, shuning uchun null bo'lishi kerak).
            foreach ($pending as $i => $a) {
                $oldStart = $a->planned_start;
                $oldEnd = $a->planned_end;
                $oldComputer = $a->computer_number;

                $history = $a->history ?? [];
                $history[] = [
                    'event' => 'dean_reschedule',
                    'at' => now()->toIso8601String(),
                    'by_user_id' => $deanUserId,
                    'from_start' => $oldStart?->toIso8601String(),
                    'from_end' => $oldEnd?->toIso8601String(),
                    'from_computer' => $oldComputer,
                    'to_start' => $newStart->toIso8601String(),
                    'to_end' => $newEnd->toIso8601String(),
                    'to_computer' => $computers[$i],
                    'reason' => $reason,
                ];

                $a->planned_start = $newStart;
                $a->planned_end = $newEnd;
                $a->computer_number = $computers[$i];
                $a->is_pinned = true;
                $a->reveal_at = null;
                $a->reveal_notified = false;
                $a->approach_notified = false;
                $a->ready_notified = false;
                $a->history = $history;
                $a->save();
            }

            $log = DeanExamReschedule::create([
                'exam_schedule_id' => $schedule->id,
                'yn_type' => $ynType,
                'used_date' => $date,
                'original_time' => $originalTime,
                'new_time' => $newTime,
                'student_count' => $count,
                'reason' => $reason,
                'created_by' => $deanUserId,
            ]);

            return [
                'ok' => true,
                'reschedule' => $log,
                'student_count' => $count,
            ];
        });
    }
}

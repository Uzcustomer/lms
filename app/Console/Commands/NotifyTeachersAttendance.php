<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyTeachersAttendance extends Command
{
    protected $signature = 'notify:teachers-attendance {--date=}';

    protected $description = 'O\'qituvchilarga davomat/baho yetishmayotganligi haqida Telegram xabar yuborish';

    public function handle(TelegramService $telegram)
    {
        $date = $this->option('date') ?: Carbon::yesterday()->format('Y-m-d');
        $this->info("Sana: {$date}");

        // 1-QADAM: Shu sanadagi barcha jadvallarni olish
        $schedules = DB::table('schedules as sch')
            ->whereRaw('DATE(sch.lesson_date) = ?', [$date])
            ->whereNull('sch.deleted_at')
            ->select(
                'sch.employee_id',
                'sch.employee_name',
                'sch.subject_id',
                'sch.subject_name',
                'sch.group_id',
                'sch.group_name',
                'sch.training_type_code',
                'sch.training_type_name',
                'sch.lesson_pair_code',
                'sch.lesson_pair_start_time',
                'sch.lesson_pair_end_time',
                DB::raw('DATE(sch.lesson_date) as lesson_date_str')
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->info("Shu sanada jadval topilmadi.");
            return;
        }

        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();

        // 2-QADAM: Davomat va baho mavjudligini ATRIBUT bo'yicha tekshirish (hisobot logikasi bilan bir xil)
        $attendanceSet = DB::table('attendance_controls')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) = ?', [$date])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        $gradeSet = DB::table('student_grades')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereRaw('DATE(lesson_date) = ?', [$date])
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', subject_id, '|', training_type_code, '|', lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        // 3-QADAM: Har bir jadval uchun davomat/baho tekshirish
        $teacherMissing = []; // employee_id => [missing items]

        foreach ($schedules as $sch) {
            $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $gradeKey = $sch->employee_id . '|' . $sch->subject_id
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $hasAttendance = isset($attendanceSet[$attKey]);
            $hasGrades = isset($gradeSet[$gradeKey]);

            if ($hasAttendance && $hasGrades) {
                continue; // Hammasi bajarilgan - xabar kerak emas
            }

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? "{$pairStart}-{$pairEnd}" : '';

            $missing = [];
            if (!$hasAttendance) $missing[] = 'davomat';
            if (!$hasGrades) $missing[] = 'baho';

            $teacherMissing[$sch->employee_id][] = [
                'group' => $sch->group_name,
                'subject' => $sch->subject_name,
                'type' => $sch->training_type_name,
                'time' => $pairTime,
                'missing' => $missing,
            ];
        }

        if (empty($teacherMissing)) {
            $this->info("Barcha o'qituvchilar davomat va baho qo'ygan. Xabar yuborilmaydi.");
            return;
        }

        // 4-QADAM: Telegram chat_id larni olish
        $teachers = Teacher::whereIn('hemis_id', array_keys($teacherMissing))
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id', 'hemis_id');

        $sentCount = 0;
        $dateFormatted = Carbon::parse($date)->format('d.m.Y');

        foreach ($teacherMissing as $employeeId => $items) {
            $chatId = $teachers[$employeeId] ?? null;
            if (!$chatId) {
                continue;
            }

            // Xabar matni
            $lines = [];
            foreach ($items as $item) {
                $missingStr = implode(', ', $item['missing']);
                $line = "  {$item['group']} | {$item['subject']}";
                if ($item['type']) $line .= " | {$item['type']}";
                if ($item['time']) $line .= " | {$item['time']}";
                $line .= "\n  Yetishmayapti: {$missingStr}";
                $lines[] = $line;
            }

            $message = "Hurmatli ustoz!\n\n"
                     . "{$dateFormatted} sanasidagi quyidagi darslarda ma'lumotlar to'liq emas:\n\n"
                     . implode("\n\n", $lines)
                     . "\n\nIltimos, HEMIS tizimida davomat va baholarni to'ldiring.";

            try {
                $telegram->sendToUser($chatId, $message);
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error("Telegram xabar yuborishda xato (employee_id: {$employeeId}): " . $e->getMessage());
            }
        }

        $totalMissing = count($teacherMissing);
        $this->info("Jami: {$totalMissing} o'qituvchida yetishmovchilik. {$sentCount} taga xabar yuborildi.");

        $telegram->notify(
            "Davomat/baho eslatmasi ({$dateFormatted}):\n"
            . "Yetishmovchilik: {$totalMissing} o'qituvchi\n"
            . "Xabar yuborildi: {$sentCount} ta"
        );
    }
}

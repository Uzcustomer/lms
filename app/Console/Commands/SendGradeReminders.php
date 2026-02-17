<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendGradeReminders extends Command
{
    protected $signature = 'reminders:send-grade-reminders';

    protected $description = 'Davomat yoki baho belgilamagan o\'qituvchilarga Telegram orqali eslatma yuborish';

    public function handle(TelegramService $telegram): int
    {
        $today = now()->toDateString();

        $this->info("Bugungi sana: {$today}. Belgilanmagan darslar tekshirilmoqda...");

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1. Bugungi jadvallarni olish (joriy semestr, faol guruhlar)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->where(DB::raw('DATE(sch.lesson_date)'), $today)
            ->whereNull('sch.deleted_at')
            ->where('sem.current', true)
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
                'sch.employee_name',
                'sch.subject_name',
                'sch.group_name',
                'sch.lesson_pair_name',
                'sch.training_type_name'
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('Bugun darslar topilmadi.');
            return self::SUCCESS;
        }

        $this->info("Jami {$schedules->count()} ta dars topildi.");

        // 2. Davomat va baho mavjudligini tekshirish
        $allScheduleIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();

        $attendanceSet = collect();
        foreach (array_chunk($allScheduleIds, 5000) as $chunk) {
            $result = DB::table('attendance_controls')
                ->whereIn('subject_schedule_id', $chunk)
                ->where('load', '>', 0)
                ->distinct()
                ->pluck('subject_schedule_id');
            $attendanceSet = $attendanceSet->merge($result);
        }
        $attendanceSet = $attendanceSet->flip();

        $gradeSet = collect();
        foreach (array_chunk($allScheduleIds, 5000) as $chunk) {
            $result = DB::table('student_grades')
                ->whereIn('subject_schedule_id', $chunk)
                ->whereNotNull('grade')
                ->where('grade', '>', 0)
                ->distinct()
                ->pluck('subject_schedule_id');
            $gradeSet = $gradeSet->merge($result);
        }
        $gradeSet = $gradeSet->flip();

        // 3. O'qituvchi bo'yicha guruhlash va belgilanmagan darslarni aniqlash
        $teacherMissing = [];

        foreach ($schedules as $sch) {
            $hasAttendance = isset($attendanceSet[$sch->schedule_hemis_id]);
            $hasGrades = isset($gradeSet[$sch->schedule_hemis_id]);

            if ($hasAttendance && $hasGrades) {
                continue;
            }

            $employeeId = $sch->employee_id;
            if (!isset($teacherMissing[$employeeId])) {
                $teacherMissing[$employeeId] = [
                    'employee_name' => $sch->employee_name,
                    'lessons' => [],
                ];
            }

            $lessonKey = $sch->subject_name . '|' . $sch->group_name . '|' . ($sch->lesson_pair_name ?? '');

            if (!isset($teacherMissing[$employeeId]['lessons'][$lessonKey])) {
                $missing = [];
                if (!$hasAttendance) {
                    $missing[] = 'davomat';
                }
                if (!$hasGrades) {
                    $missing[] = 'baho';
                }

                $teacherMissing[$employeeId]['lessons'][$lessonKey] = [
                    'subject' => $sch->subject_name,
                    'group' => $sch->group_name,
                    'pair' => $sch->lesson_pair_name ?? '',
                    'type' => $sch->training_type_name ?? '',
                    'missing' => $missing,
                ];
            }
        }

        if (empty($teacherMissing)) {
            $this->info('Barcha o\'qituvchilar davomat va baholarni belgilagan.');
            return self::SUCCESS;
        }

        $this->info(count($teacherMissing) . " ta o'qituvchida belgilanmagan darslar topildi.");

        // 4. Telegram orqali xabar yuborish
        $employeeIds = array_keys($teacherMissing);
        $teachers = Teacher::whereIn('hemis_id', $employeeIds)
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->get()
            ->keyBy('hemis_id');

        $sentCount = 0;
        $skippedCount = 0;

        foreach ($teacherMissing as $employeeId => $data) {
            $teacher = $teachers->get($employeeId);

            if (!$teacher) {
                $skippedCount++;
                continue;
            }

            $message = $this->buildMessage($data, $today);

            if ($telegram->sendToUser($teacher->telegram_chat_id, $message)) {
                $sentCount++;
                $this->line("  Yuborildi: {$data['employee_name']}");
            } else {
                $this->warn("  Xato: {$data['employee_name']}");
            }
        }

        $this->info("Natija: {$sentCount} ta yuborildi, {$skippedCount} ta o'tkazib yuborildi (Telegram ulanmagan).");

        Log::info("SendGradeReminders: {$sentCount} yuborildi, {$skippedCount} o'tkazib yuborildi.", [
            'date' => $today,
        ]);

        return self::SUCCESS;
    }

    private function buildMessage(array $data, string $date): string
    {
        $lines = [];
        $lines[] = "Hurmatli {$data['employee_name']}!";
        $lines[] = '';
        $lines[] = "Bugun ({$date}) quyidagi darslaringiz uchun hali belgilanmagan ma'lumotlar mavjud:";
        $lines[] = '';

        $i = 1;
        foreach ($data['lessons'] as $lesson) {
            $missingStr = implode(', ', $lesson['missing']);
            $pairInfo = $lesson['pair'] ? " ({$lesson['pair']})" : '';
            $typeInfo = $lesson['type'] ? " [{$lesson['type']}]" : '';
            $lines[] = "{$i}. {$lesson['subject']}{$typeInfo} - {$lesson['group']}{$pairInfo}";
            $lines[] = "   Belgilanmagan: {$missingStr}";
            $i++;
        }

        $lines[] = '';
        $lines[] = "Iltimos, tizimga kirib davomat va/yoki baholarni belgilang.";

        return implode("\n", $lines);
    }
}

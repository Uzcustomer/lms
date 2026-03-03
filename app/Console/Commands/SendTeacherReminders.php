<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Services\ScheduleImportService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendTeacherReminders extends Command
{
    protected $signature = 'teachers:send-reminders';

    protected $description = 'Davomat olmagan yoki baho qo\'ymagan o\'qituvchilarga Telegram orqali eslatma yuborish';

    public function handle(TelegramService $telegram, ScheduleImportService $importService): int
    {
        $today = Carbon::today()->format('Y-m-d');

        // Hisobot va guruh xabari bilan bir xil filtrlar
        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        // Bu turlarga faqat davomat tekshiriladi, baho tekshirilmaydi (ma'ruza va h.k.)
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $this->info("Bugungi sana: {$today}");

        // 1-QADAM: HEMIS dan bugungi jadval ma'lumotlarini yangilash
        $this->info("HEMIS dan bugungi jadval yangilanmoqda ({$today})...");
        try {
            $importService->importBetween(
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
            );
            $this->info("Jadval muvaffaqiyatli yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('HEMIS jadval sinxronlashda xato (davom etadi): ' . $e->getMessage());
            $this->warn("HEMIS jadval yangilashda xato: " . $e->getMessage());
        }

        // 1.5-QADAM: Davomat nazoratini yangilash (guruh hisoboti bilan bir xil)
        $this->info("HEMIS dan bugungi davomat nazorati yangilanmoqda...");
        try {
            Artisan::call('import:attendance-controls', [
                '--date' => $today,
                '--silent' => true,
            ]);
            $this->info("Davomat nazorati yangilandi.");
        } catch (\Throwable $e) {
            Log::warning('Davomat nazorati yangilashda xato (davom etadi): ' . $e->getMessage());
            $this->warn("Davomat nazorati yangilashda xato: " . $e->getMessage());
        }

        $this->info("O'qituvchilarga eslatma yuborilmoqda...");

        // 2-QADAM: Jadvaldan ma'lumot olish (yakuniy hisobot bilan bir xil logika)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) = ?', [$today])
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            ->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            })
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
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
            $this->info('Bugun uchun dars jadvali topilmadi.');
            return 0;
        }

        // 3-QADAM: Davomat va baho tekshirish (yakuniy hisobot bilan bir xil logika)
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();

        // Davomat (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        $attendanceByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->pluck('subject_schedule_id')
            ->flip();

        // Davomat (2-usul): atribut kalitlari orqali tekshirish (zaxira)
        $attendanceByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) = ?', [$today])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        // Baho (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        $gradeByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->pluck('subject_schedule_id')
            ->unique()
            ->flip();

        // Baho (2-usul): student → group orqali tekshirish (zaxira)
        $gradeByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.employee_id', $employeeIds)
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) = ?', [$today])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(sg.employee_id, '|', st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.training_type_code, '|', sg.lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        // 4-QADAM: O'qituvchilar bo'yicha guruhlash va tekshirish
        $schedulesByTeacher = $schedules->groupBy('employee_id');

        $sentCount = 0;
        $congratsCount = 0;
        $skippedCount = 0;

        foreach ($schedulesByTeacher as $employeeId => $teacherSchedules) {
            $teacher = Teacher::where('hemis_id', $employeeId)->first();

            if (!$teacher || !$teacher->telegram_chat_id) {
                $skippedCount++;
                continue;
            }

            $missingAttendance = [];
            $missingGrades = [];

            foreach ($teacherSchedules as $schedule) {
                $trainingTypeCode = (int) $schedule->training_type_code;

                // DAVOMAT tekshirish: schedule_hemis_id yoki atribut kaliti orqali
                $attKey = $schedule->employee_id . '|' . $schedule->group_id . '|' . $schedule->subject_id . '|' . $schedule->lesson_date_str
                        . '|' . $schedule->training_type_code . '|' . $schedule->lesson_pair_code;

                $hasAttendance = isset($attendanceByScheduleId[$schedule->schedule_hemis_id])
                              || isset($attendanceByKey[$attKey]);

                if (!$hasAttendance) {
                    $missingAttendance[] = $schedule;
                }

                // BAHO tekshirish: faqat amaliyot turlari uchun
                if (!in_array($trainingTypeCode, $gradeExcludedTypes)) {
                    $gradeKey = $schedule->employee_id . '|' . $schedule->group_id . '|' . $schedule->subject_id . '|' . $schedule->lesson_date_str
                              . '|' . $schedule->training_type_code . '|' . $schedule->lesson_pair_code;

                    $hasGrade = isset($gradeByScheduleId[$schedule->schedule_hemis_id])
                             || isset($gradeByKey[$gradeKey]);

                    if (!$hasGrade) {
                        $missingGrades[] = $schedule;
                    }
                }
            }

            // Agar barcha darslar to'liq bajarilgan bo'lsa — tabrik yuborish (kuniga 1 marta)
            if (empty($missingAttendance) && empty($missingGrades)) {
                $cacheKey = "teacher_congrats_{$employeeId}_{$today}";

                if (!Cache::has($cacheKey)) {
                    $congratsMessage = $this->buildCongratsMessage($teacher, $teacherSchedules, $today);

                    try {
                        $telegram->sendToUser($teacher->telegram_chat_id, $congratsMessage);
                        Cache::put($cacheKey, true, Carbon::tomorrow());
                        $congratsCount++;
                        $this->info("Tabrik yuborildi: {$teacher->full_name}");
                    } catch (\Throwable $e) {
                        Log::error("Telegram tabrik yuborishda xato (Teacher: {$teacher->full_name}): " . $e->getMessage());
                        $this->error("Tabrik xato: {$teacher->full_name} - " . $e->getMessage());
                    }
                }

                continue;
            }

            $message = $this->buildMessage($teacher, $missingAttendance, $missingGrades, $today);

            try {
                $telegram->sendToUser($teacher->telegram_chat_id, $message);
                $sentCount++;
                $this->info("Eslatma yuborildi: {$teacher->full_name}");
            } catch (\Throwable $e) {
                Log::error("Telegram eslatma yuborishda xato (Teacher: {$teacher->full_name}): " . $e->getMessage());
                $this->error("Xato: {$teacher->full_name} - " . $e->getMessage());
            }
        }

        $this->info("Yakunlandi. Eslatmalar: {$sentCount}, Tabriklar: {$congratsCount}, O'tkazib yuborildi: {$skippedCount}");

        return 0;
    }

    private function buildCongratsMessage(Teacher $teacher, $teacherSchedules, string $today): string
    {
        $formattedDate = Carbon::parse($today)->format('d.m.Y');
        $lessonCount = $teacherSchedules->unique(function ($s) {
            return $s->employee_id . '|' . $s->group_id . '|' . $s->subject_id . '|' . $s->training_type_code . '|' . $s->lesson_pair_code;
        })->count();

        $lines = [];
        $lines[] = "Hurmatli {$teacher->full_name}!";
        $lines[] = "";
        $lines[] = "Tabriklaymiz! Siz bugungi ({$formattedDate}) dars jadvalidagi barcha {$lessonCount} ta darsning davomatini oldingiz va baholarini qo'ydingiz.";
        $lines[] = "";
        $lines[] = "Mas'uliyatli mehnatingiz uchun rahmat! Shu zayl davom eting!";

        return implode("\n", $lines);
    }

    private function buildMessage(Teacher $teacher, array $missingAttendance, array $missingGrades, string $today): string
    {
        $lines = [];
        $lines[] = "Hurmatli {$teacher->full_name}!";
        $lines[] = "";
        $lines[] = "Bugungi sana: {$today}";
        $lines[] = "";

        if (!empty($missingAttendance)) {
            $lines[] = "Davomat olinmagan darslar:";
            foreach ($missingAttendance as $schedule) {
                $time = $schedule->lesson_pair_start_time
                    ? " ({$schedule->lesson_pair_start_time}-{$schedule->lesson_pair_end_time})"
                    : "";
                $lines[] = "  - {$schedule->subject_name} | {$schedule->group_name} | {$schedule->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        if (!empty($missingGrades)) {
            $lines[] = "Baho qo'yilmagan darslar:";
            foreach ($missingGrades as $schedule) {
                $time = $schedule->lesson_pair_start_time
                    ? " ({$schedule->lesson_pair_start_time}-{$schedule->lesson_pair_end_time})"
                    : "";
                $lines[] = "  - {$schedule->subject_name} | {$schedule->group_name} | {$schedule->training_type_name}{$time}";
            }
            $lines[] = "";
        }

        $hasBoth = !empty($missingAttendance) && !empty($missingGrades);
        $onlyAttendance = !empty($missingAttendance) && empty($missingGrades);

        if ($hasBoth) {
            $lines[] = "Iltimos, tizimga kirib davomat va baholarni kiriting.";
        } elseif ($onlyAttendance) {
            $lines[] = "Iltimos, tizimga kirib davomatni kiriting.";
        } else {
            $lines[] = "Iltimos, tizimga kirib baholarni kiriting.";
        }

        return implode("\n", $lines);
    }
}

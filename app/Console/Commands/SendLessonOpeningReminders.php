<?php

namespace App\Console\Commands;

use App\Models\LessonOpening;
use App\Models\Teacher;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendLessonOpeningReminders extends Command
{
    protected $signature = 'teachers:send-lesson-opening-reminders';

    protected $description = 'Dars ochilgan o\'qituvchilarga baho eslatmasi: baho qo\'yilsa tasdiq, 1 kun qolsa eslatma';

    public function handle(TelegramService $telegram): int
    {
        $this->info('Dars ochilishi eslatmalari tekshirilmoqda...');

        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Faol lesson_openings ni olish
        $openings = LessonOpening::where('status', 'active')->get();

        if ($openings->isEmpty()) {
            $this->info('Faol dars ochilishlari topilmadi.');
            return 0;
        }

        $confirmedCount = 0;
        $reminderCount = 0;
        $skippedCount = 0;

        foreach ($openings as $opening) {
            $groupHemisId = $opening->group_hemis_id;
            $subjectId = $opening->subject_id;
            $semesterCode = $opening->semester_code;
            $lessonDate = $opening->lesson_date->format('Y-m-d');

            // Shu dars uchun o'qituvchilarni topish
            $teacherSchedules = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereRaw('DATE(lesson_date) = ?', [$lessonDate])
                ->whereNotIn('training_type_code', $gradeExcludedTypes)
                ->whereNull('deleted_at')
                ->select('employee_id', 'subject_name', 'group_name', 'schedule_hemis_id')
                ->get();

            if ($teacherSchedules->isEmpty()) {
                continue;
            }

            $scheduleHemisIds = $teacherSchedules->pluck('schedule_hemis_id')->unique()->toArray();

            // Baho qo'yilganini tekshirish (1-usul: schedule_hemis_id orqali)
            $gradeByScheduleId = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('subject_schedule_id', $scheduleHemisIds)
                ->where(function ($q) {
                    $q->where('grade', '>', 0)
                      ->orWhere('retake_grade', '>', 0)
                      ->orWhere('status', 'recorded');
                })
                ->pluck('subject_schedule_id')
                ->unique()
                ->flip();

            // Baho qo'yilganini tekshirish (2-usul: guruh+fan+sana orqali)
            $gradeByKey = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereNull('sg.deleted_at')
                ->where('st.group_id', $groupHemisId)
                ->where('sg.subject_id', $subjectId)
                ->whereRaw('DATE(sg.lesson_date) = ?', [$lessonDate])
                ->where(function ($q) {
                    $q->where('sg.grade', '>', 0)
                      ->orWhere('sg.retake_grade', '>', 0)
                      ->orWhere('sg.status', 'recorded');
                })
                ->exists();

            // Barcha schedule lar uchun baho bormi
            $allGraded = true;
            foreach ($teacherSchedules as $ts) {
                if (!isset($gradeByScheduleId[$ts->schedule_hemis_id]) && !$gradeByKey) {
                    $allGraded = false;
                    break;
                }
            }

            $employeeIds = $teacherSchedules->pluck('employee_id')->unique();
            $subjectName = $teacherSchedules->first()->subject_name ?? 'Noma\'lum fan';
            $groupName = $teacherSchedules->first()->group_name ?? 'Noma\'lum guruh';
            $lessonDateFormatted = Carbon::parse($lessonDate)->format('d.m.Y');
            $deadlineFormatted = $opening->deadline->format('d.m.Y H:i');

            // Muddatgacha qolgan vaqt
            $hoursLeft = Carbon::now('Asia/Tashkent')->diffInHours($opening->deadline, false);

            foreach ($employeeIds as $employeeId) {
                $teacher = Teacher::where('hemis_id', $employeeId)->first();

                if (!$teacher || !$teacher->telegram_chat_id) {
                    $skippedCount++;
                    continue;
                }

                // HOLAT 1: Baho qo'yilgan — tasdiq xabari
                if ($allGraded) {
                    $cacheKey = "lesson_opening_grade_confirmed_{$opening->id}_{$employeeId}";

                    if (!Cache::has($cacheKey)) {
                        $message = "Hurmatli {$teacher->full_name}!\n\n"
                            . "{$subjectName} ({$groupName}, {$lessonDateFormatted}) darsiga baholar muvaffaqiyatli qo'yildi.\n\n"
                            . "Rahmat!";

                        try {
                            $telegram->sendToUser($teacher->telegram_chat_id, $message);
                            Cache::put($cacheKey, true, Carbon::now()->addDays(7));
                            $confirmedCount++;
                            $this->info("Baho tasdiq: {$teacher->full_name} ({$subjectName}, {$groupName}, {$lessonDateFormatted})");
                        } catch (\Throwable $e) {
                            Log::error("Telegram baho tasdiq xato ({$teacher->full_name}): " . $e->getMessage());
                        }
                    }

                    continue;
                }

                // HOLAT 2: 1 kun (24 soat) qolgan — eslatma
                if ($hoursLeft > 0 && $hoursLeft <= 24) {
                    $cacheKey = "lesson_opening_1day_reminder_{$opening->id}_{$employeeId}";

                    if (!Cache::has($cacheKey)) {
                        $message = "Hurmatli {$teacher->full_name}!\n\n"
                            . "ESLATMA: {$subjectName} ({$groupName}, {$lessonDateFormatted}) darsiga baho qo'yish uchun 1 kun qoldi!\n\n"
                            . "Muhlat: {$deadlineFormatted}\n\n"
                            . "Iltimos, tezroq baholarni kiriting.";

                        try {
                            $telegram->sendToUser($teacher->telegram_chat_id, $message);
                            Cache::put($cacheKey, true, $opening->deadline);
                            $reminderCount++;
                            $this->info("1 kun eslatma: {$teacher->full_name} ({$subjectName}, {$groupName}, {$lessonDateFormatted})");
                        } catch (\Throwable $e) {
                            Log::error("Telegram 1-kun eslatma xato ({$teacher->full_name}): " . $e->getMessage());
                        }
                    }
                }
            }

            // Agar baho to'liq qo'yilgan bo'lsa, opening ni completed qilish
            if ($allGraded) {
                $opening->update(['status' => 'completed']);
            }
        }

        // Muddati o'tganlarni expire qilish
        LessonOpening::expireOverdue();

        $this->info("Yakunlandi. Tasdiqlar: {$confirmedCount}, Eslatmalar: {$reminderCount}, O'tkazildi: {$skippedCount}");

        return 0;
    }
}

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

    protected $description = 'Dars ochilganlarga baho eslatmasi + dars ochilmaganlarga Registrator ofisi eslatmasi';

    public function handle(TelegramService $telegram): int
    {
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // ========================
        // 1-QISM: DARS OCHILGANLAR — baho qo'yilsa tasdiq, 1 kun qolsa eslatma
        // ========================
        $this->info('1-qism: Dars ochilishi eslatmalari tekshirilmoqda...');

        $openings = LessonOpening::where('status', 'active')->get();

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

        $this->info("1-qism yakunlandi. Tasdiqlar: {$confirmedCount}, Eslatmalar: {$reminderCount}, O'tkazildi: {$skippedCount}");

        // ========================
        // 2-QISM: DARS OCHILMAGANLAR — baho qo'yilmagan + dars ochilmagan darslar uchun eslatma
        // ========================
        $this->info('2-qism: Dars ochilmagan + baho qo\'yilmagan darslar tekshirilmoqda...');

        $unopenedReminderCount = $this->sendUnopenedLessonReminders($telegram, $gradeExcludedTypes);

        $this->info("2-qism yakunlandi. Eslatmalar: {$unopenedReminderCount}");

        return 0;
    }

    /**
     * Dars ochilmagan + baho qo'yilmagan darslar uchun o'qituvchilarga eslatma yuborish
     * "Registrator ofisiga murojaat qiling" xabari
     */
    private function sendUnopenedLessonReminders(TelegramService $telegram, array $gradeExcludedTypes): int
    {
        $today = Carbon::today()->format('Y-m-d');

        // O'tgan kunlardagi darslarni olish (baho qo'yilishi kerak bo'lganlar)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $gradeExcludedTypes)
            ->where('sch.education_year_current', true)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) < ?', [$today])
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
                'sch.semester_code',
                DB::raw('DATE(sch.lesson_date) as lesson_date_str')
            )
            ->get();

        if ($schedules->isEmpty()) {
            $this->info('O\'tgan darslarda baho tekshirish uchun jadval topilmadi.');
            return 0;
        }

        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->toArray();

        // Baho qo'yilganlarni tekshirish (1-usul)
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

        // Baho qo'yilganlarni tekshirish (2-usul)
        $gradeRecords = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereIn('sg.subject_id', $subjectIds)
            ->whereNotNull('sg.lesson_date')
            ->where(function ($q) {
                $q->where('sg.grade', '>', 0)
                  ->orWhere('sg.retake_grade', '>', 0)
                  ->orWhere('sg.status', 'recorded');
            })
            ->select('st.group_id', 'sg.subject_id', 'sg.lesson_date')
            ->distinct()
            ->get();

        $gradeByKey = [];
        foreach ($gradeRecords as $row) {
            $date = Carbon::parse($row->lesson_date)->format('Y-m-d');
            $gradeByKey[$row->group_id . '|' . $row->subject_id . '|' . $date] = true;
        }

        // Dars ochilganlarni tekshirish
        $openedKeys = DB::table('lesson_openings')
            ->whereIn('group_hemis_id', $groupHemisIds)
            ->get()
            ->groupBy(function ($item) {
                return $item->group_hemis_id . '|' . $item->subject_id . '|' . $item->semester_code . '|' . Carbon::parse($item->lesson_date)->format('Y-m-d');
            });

        // O'qituvchilar bo'yicha: baho qo'yilmagan + dars ochilmagan darslarni yig'ish
        $teacherUnopenedLessons = [];

        foreach ($schedules as $sch) {
            $gradeKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str;

            $hasGrade = isset($gradeByScheduleId[$sch->schedule_hemis_id])
                || isset($gradeByKey[$gradeKey]);

            if ($hasGrade) {
                continue; // Baho qo'yilgan — o'tkazish
            }

            // Dars ochilganmi?
            $openingKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code . '|' . $sch->lesson_date_str;
            $hasOpening = isset($openedKeys[$openingKey]);

            if ($hasOpening) {
                continue; // Dars ochilgan — bu 1-qismda qarab chiqiladi
            }

            // Dars ochilMAgan + baho qo'yilMAgan
            $employeeId = $sch->employee_id;
            if (!isset($teacherUnopenedLessons[$employeeId])) {
                $teacherUnopenedLessons[$employeeId] = [];
            }

            $uniqueKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str;
            if (!isset($teacherUnopenedLessons[$employeeId][$uniqueKey])) {
                $teacherUnopenedLessons[$employeeId][$uniqueKey] = [
                    'subject_name' => $sch->subject_name,
                    'group_name' => $sch->group_name,
                    'lesson_date' => $sch->lesson_date_str,
                ];
            }
        }

        if (empty($teacherUnopenedLessons)) {
            $this->info('Dars ochilmagan + baho qo\'yilmagan darslar topilmadi.');
            return 0;
        }

        $sentCount = 0;

        foreach ($teacherUnopenedLessons as $employeeId => $lessons) {
            $cacheKey = "unopened_lesson_reminder_{$employeeId}_" . md5(json_encode(array_keys($lessons)));

            if (Cache::has($cacheKey)) {
                continue;
            }

            $teacher = Teacher::where('hemis_id', $employeeId)->first();
            if (!$teacher || !$teacher->telegram_chat_id) {
                continue;
            }

            // Sanalar bo'yicha guruhlash
            $byDate = [];
            foreach ($lessons as $lesson) {
                $byDate[$lesson['lesson_date']][] = $lesson;
            }

            $lines = [];
            $lines[] = "Hurmatli {$teacher->full_name}!\n";

            $lines[] = "Sizda quyidagi kunlarda baholanmay qolgan darslar mavjud:\n";

            foreach ($byDate as $date => $dateLessons) {
                $formattedDate = Carbon::parse($date)->format('d.m.Y');
                $lines[] = "{$formattedDate}:";
                foreach ($dateLessons as $dl) {
                    $lines[] = "  - {$dl['subject_name']} | {$dl['group_name']}";
                }
            }

            $lines[] = "\nIltimos, Registrator ofisi xodimlariga murojaat qiling.";
            $lines[] = "\nHurmat bilan,\nRegistrator ofisi";

            $message = implode("\n", $lines);

            try {
                $telegram->sendToUser($teacher->telegram_chat_id, $message);
                Cache::put($cacheKey, true, Carbon::now()->addHours(12));
                $sentCount++;
                $this->info("Dars ochilmagan eslatma: {$teacher->full_name} ({$this->countLessons($lessons)} ta dars)");
            } catch (\Throwable $e) {
                Log::error("Telegram dars ochilmagan eslatma xato ({$teacher->full_name}): " . $e->getMessage());
            }
        }

        return $sentCount;
    }

    private function countLessons(array $lessons): int
    {
        return count($lessons);
    }
}

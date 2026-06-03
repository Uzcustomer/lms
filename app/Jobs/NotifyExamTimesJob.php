<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bulk notification yuborish — admin "Xabar yuborish" tugmasini bossanda
 * dispatch qilinadi. autoTimeAll vaqt belgilashning o'zida xabar yubormaydi
 * (504 timeout + spam sabab), shu sabab yakuniy vaqtlar tasdiqlangach
 * ushbu job alohida ishga tushiriladi.
 */
class NotifyExamTimesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 daqiqa — ko'p schedule bo'lsa ham yetadi

    public function __construct(
        public string $dateFrom,
        public string $dateTo,
    ) {}

    public function handle(TelegramService $telegram): void
    {
        $specs = [
            ['date' => 'oski_date',         'time' => 'oski_time',         'na' => 'oski_na', 'yn' => 'OSKI', 'attempt' => 1],
            ['date' => 'oski_resit_date',   'time' => 'oski_resit_time',   'na' => null,      'yn' => 'OSKI', 'attempt' => 2],
            ['date' => 'oski_resit2_date',  'time' => 'oski_resit2_time',  'na' => null,      'yn' => 'OSKI', 'attempt' => 3],
            ['date' => 'test_date',         'time' => 'test_time',         'na' => 'test_na', 'yn' => 'Test', 'attempt' => 1],
            ['date' => 'test_resit_date',   'time' => 'test_resit_time',   'na' => null,      'yn' => 'Test', 'attempt' => 2],
            ['date' => 'test_resit2_date',  'time' => 'test_resit2_time',  'na' => null,      'yn' => 'Test', 'attempt' => 3],
        ];

        $rows = ExamSchedule::query()
            ->where(function ($q) use ($specs) {
                foreach ($specs as $s) {
                    $q->orWhere(function ($qq) use ($s) {
                        $qq->whereBetween($s['date'], [$this->dateFrom, $this->dateTo])
                           ->whereNotNull($s['time']);
                    });
                }
            })
            ->get();

        $totalNotified = 0;
        foreach ($rows as $schedule) {
            foreach ($specs as $s) {
                $dateVal = $schedule->{$s['date']};
                $timeVal = $schedule->{$s['time']};
                if (empty($dateVal) || empty($timeVal)) continue;
                if ($s['na'] && !empty($schedule->{$s['na']})) continue;

                $dateStr = $dateVal instanceof Carbon ? $dateVal->format('Y-m-d') : (string) $dateVal;
                if ($dateStr < $this->dateFrom || $dateStr > $this->dateTo) continue;

                $students = $this->collectRecipients($schedule, $s['date'], (int) $s['attempt']);
                if ($students->isEmpty()) continue;

                $ynLabel = $s['yn'];
                if ($s['attempt'] > 1) {
                    $ynLabel .= ' (' . $s['attempt'] . '-urinish)';
                }
                $subjectName = $schedule->subject_name ?: 'Fan';
                $dateFmt = Carbon::parse($dateStr)->format('d.m.Y');
                $timeHM = substr((string) $timeVal, 0, 5);

                // Bulk xabar — faqat fan/sana/vaqt. Komp raqamini
                // ExamScheduleTickJob imtihondan ~5 daqiqa oldin avtomatik
                // yuboradi (notifyReveal), ekranda esa /tv/kompyuter displeyi
                // ko'rsatadi. Bulk xabarda komp № yuborilsa, talaba uzoq
                // muddat oldin ko'rib qoladi va boshqa kompga o'tirib qo'yishi
                // mumkin — shu sabab ataylab kiritilmaydi.
                $totalNotified += $this->sendBatch(
                    $telegram, $students, $subjectName, $ynLabel, $dateFmt, $timeHM
                );
            }
        }

        Log::info('NotifyExamTimesJob: completed', [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'notified' => $totalNotified,
            'schedules' => $rows->count(),
        ]);
    }

    /**
     * Schedule uchun xabar yuboriladigan talabalarni qaytaradi.
     * Per-student row (individual grafik) bo'lsa — faqat shu talabaga.
     * Guruh sathidagi 1-urinishda butun guruh; 2/3-urinishda esa
     * faqat yiqilgan (retaker) talabalar (individual grafikdagilar chiqarib).
     */
    private function collectRecipients(ExamSchedule $schedule, string $dateColumn, int $attempt = 1): \Illuminate\Support\Collection
    {
        if (!empty($schedule->student_hemis_id)) {
            return Student::where('hemis_id', $schedule->student_hemis_id)
                ->whereNotNull('telegram_chat_id')
                ->get();
        }
        // Guruh talabalari — individual grafikdagilarni chiqarib tashlash
        $individualIds = ExamSchedule::where('group_hemis_id', $schedule->group_hemis_id)
            ->where('subject_id', $schedule->subject_id)
            ->where('semester_code', $schedule->semester_code)
            ->whereNotNull('student_hemis_id')
            ->whereNotNull($dateColumn)
            ->pluck('student_hemis_id')
            ->all();

        $query = Student::where('group_id', $schedule->group_hemis_id)
            ->where('student_status_code', 11)
            ->when(!empty($individualIds), fn($q) => $q->whereNotIn('hemis_id', $individualIds));

        // 2/3-urinish — faqat yiqilganlar (1-urinishni o'tganlarga xabar ortiqcha)
        if ($attempt >= 2) {
            $retakers = \App\Services\ExamCapacityService::resitEligibleStudentIds(
                (string) $schedule->group_hemis_id,
                $schedule->subject_id,
                $schedule->semester_code
            );
            if (empty($retakers)) {
                return collect();
            }
            $query->whereIn('hemis_id', $retakers);
        }
        return $query->get();
    }

    private function sendBatch(
        TelegramService $telegram,
        \Illuminate\Support\Collection $students,
        string $subjectName,
        string $ynLabel,
        string $dateFmt,
        string $timeHM
    ): int {
        $message = "📋 <b>{$ynLabel} vaqti belgilandi!</b>\n\n"
            . "📌 Fan: <b>{$subjectName}</b>\n"
            . "📅 Sana: <b>{$dateFmt}</b>\n"
            . "⏰ Vaqt: <b>{$timeHM}</b>";
        $notifTitle = "{$ynLabel} vaqti belgilandi: {$subjectName}";
        $notifMessage = "Fan: {$subjectName}, Sana: {$dateFmt}, Vaqt: {$timeHM}.";

        $notificationRecords = [];
        $sent = 0;
        foreach ($students as $student) {
            try {
                if (!empty($student->telegram_chat_id)) {
                    $telegram->sendToUser($student->telegram_chat_id, $message);
                }
            } catch (\Throwable $e) {
                Log::warning('NotifyExamTimesJob: telegram failed', [
                    'student_id' => $student->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
            $notificationRecords[] = [
                'student_id' => $student->id,
                'type' => 'exam_reminder',
                'title' => $notifTitle,
                'message' => $notifMessage,
                'link' => '/student/exam-schedule',
                'data' => json_encode([
                    'subject' => $subjectName,
                    'yn_label' => $ynLabel,
                    'test_time' => $timeHM,
                    'test_date' => $dateFmt,
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $sent++;
        }
        if (!empty($notificationRecords)) {
            StudentNotification::insert($notificationRecords);
        }
        return $sent;
    }
}

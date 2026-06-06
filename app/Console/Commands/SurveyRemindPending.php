<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\StudentSurveyController;
use App\Models\Student;
use App\Models\StudentSurveyCompletion;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * So'rovnomani hali bajarmagan, Telegram tasdiqlangan talabalarga eslatma
 * yuboradi. Bootstrap'da kuniga 09:00 da rejalashtirilgan; deadline o'tgan
 * bo'lsa hech narsa yubormaydi.
 */
class SurveyRemindPending extends Command
{
    protected $signature = 'survey:remind-pending';
    protected $description = 'Send daily Telegram reminder to students who have not completed the active survey';

    public function handle(TelegramService $telegram, StudentSurveyController $ctrl): int
    {
        $config = config('student_survey');
        if (!$config || empty($config['key']) || empty($config['deadline'])) {
            $this->warn('Survey config not set; skipping.');
            return self::SUCCESS;
        }

        if (strtotime($config['deadline']) < time()) {
            $this->info('Deadline already passed; skipping.');
            return self::SUCCESS;
        }

        $surveyKey = $config['key'];
        $deadlineFormatted = \Carbon\Carbon::parse($config['deadline'])->format('d.m.Y H:i');

        $completedIds = StudentSurveyCompletion::where('survey_key', $surveyKey)
            ->pluck('student_hemis_id')
            ->all();

        $pending = Student::query()
            ->where('student_status_code', 11)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->when(!empty($completedIds), fn($q) => $q->whereNotIn('hemis_id', $completedIds))
            ->select(['hemis_id', 'telegram_chat_id'])
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending students; skipping.');
            return self::SUCCESS;
        }

        $message = $ctrl->buildReminderMessage($config['title'], $deadlineFormatted);

        $sent = 0;
        $failed = 0;
        foreach ($pending as $student) {
            $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $message);
            if ($ok) $sent++; else $failed++;
            usleep(50_000);
        }

        Log::info('Daily survey reminder dispatched', [
            'survey_key' => $surveyKey,
            'pending'    => $pending->count(),
            'sent'       => $sent,
            'failed'     => $failed,
        ]);

        $this->info("Pending: {$pending->count()}, sent: {$sent}, failed: {$failed}");
        return self::SUCCESS;
    }
}

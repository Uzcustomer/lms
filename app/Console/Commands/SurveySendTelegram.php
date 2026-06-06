<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\StudentSurveyController;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSurveyCompletion;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Talabalar so'rovnomasi uchun Telegram xabarlarini terminaldan yuborish.
 * Har talabaga jonli progress, Ctrl+C bilan to'xtatish mumkin.
 *
 * Misol:
 *   php artisan survey:send-telegram announcement
 *   php artisan survey:send-telegram reminder --limit=10 --dry-run
 */
class SurveySendTelegram extends Command
{
    protected $signature = 'survey:send-telegram
                            {kind : announcement | reminder}
                            {--dry-run : Faqat ko\'rsatadi, yubormaydi}
                            {--limit= : Faqat birinchi N talabaga yuborish (test uchun)}';

    protected $description = 'Send survey announcement / reminder via Telegram with live terminal progress';

    public function handle(TelegramService $telegram, StudentSurveyController $ctrl): int
    {
        $kind = $this->argument('kind');
        if (!in_array($kind, ['announcement', 'reminder'], true)) {
            $this->error("kind 'announcement' yoki 'reminder' bo'lishi kerak.");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if (!StudentSurveyController::isActive() && !$dryRun) {
            if (!$this->confirm("Survey OFF holatda. Baribir yuborilsinmi?", false)) {
                $this->info('Bekor qilindi.');
                return self::SUCCESS;
            }
        }

        $config = config('student_survey');
        $surveyKey = $config['key'];
        $deadlineFormatted = \Carbon\Carbon::parse(StudentSurveyController::currentDeadline())->format('d.m.Y H:i');

        // Talabalar tanlovi
        $query = Student::query()
            ->where('student_status_code', 11)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '');

        if ($kind === 'reminder') {
            $completedIds = StudentSurveyCompletion::where('survey_key', $surveyKey)
                ->pluck('student_hemis_id')
                ->all();
            if (!empty($completedIds)) {
                $query->whereNotIn('hemis_id', $completedIds);
            }
        }

        $total = (clone $query)->count();
        if ($limit) $total = min($total, $limit);

        $this->newLine();
        $this->line("<options=bold>Survey Telegram yuborish</>");
        $this->line("  Kind:       <fg=cyan>{$kind}</>");
        $this->line("  Survey key: <fg=cyan>{$surveyKey}</>");
        $this->line("  Deadline:   <fg=cyan>{$deadlineFormatted}</>");
        $this->line("  Talabalar:  <fg=cyan>{$total}</>" . ($limit ? " (limit={$limit})" : ''));
        if ($dryRun) $this->warn("  DRY-RUN — xabar yuborilmaydi");
        $this->newLine();

        if (!$dryRun && !$this->confirm("Davom etilsinmi?", true)) {
            $this->info('Bekor qilindi.');
            return self::SUCCESS;
        }

        // Status flag (UI'da ham ko'rinishi uchun)
        if (!$dryRun) {
            Setting::set('student_survey_tg_status', 'running');
            Setting::set('student_survey_tg_kind', $kind);
            Setting::set('student_survey_tg_total', (string) $total);
            Setting::set('student_survey_tg_sent', '0');
            Setting::set('student_survey_tg_failed', '0');
            Setting::set('student_survey_tg_started_at', now()->toDateTimeString());
            Setting::forget('student_survey_tg_last_error');
        }

        // 3 ta tilda matn
        $titleArr = $config['title'] ?? [];
        $messages = [];
        foreach (['uz', 'ru', 'en'] as $loc) {
            $title = sv_t($titleArr, $loc);
            $messages[$loc] = $kind === 'announcement'
                ? $ctrl->buildAnnouncementMessage($title, $deadlineFormatted, $loc)
                : $ctrl->buildReminderMessage($title, $deadlineFormatted, $loc);
        }

        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  ✓%message%");
        $bar->setMessage('0');

        $sent = 0;
        $failed = 0;
        $i = 0;
        $startTime = microtime(true);

        $query->select(['hemis_id', 'full_name', 'telegram_chat_id', 'department_name'])
            ->chunkById(200, function ($chunk) use (&$sent, &$failed, &$i, $telegram, $messages, $dryRun, $bar, $limit) {
                foreach ($chunk as $student) {
                    if ($limit && $i >= $limit) return false;
                    $i++;

                    $loc = StudentSurveyController::detectStudentLocale($student);
                    $name = mb_substr($student->full_name ?? '—', 0, 30);
                    $chatId = $student->telegram_chat_id;

                    if ($dryRun) {
                        $this->line("  [DRY] #{$i} {$name} ({$chatId}) → <fg=yellow>{$loc}</>");
                        $sent++;
                    } else {
                        $ok = $telegram->sendToUser((string) $chatId, $messages[$loc]);
                        if ($ok) {
                            $sent++;
                            $this->line("  <fg=green>✓</> #{$i} {$name} ({$chatId}) → {$loc}");
                        } else {
                            $failed++;
                            $this->line("  <fg=red>✗</> #{$i} {$name} ({$chatId}) → {$loc}");
                        }
                    }

                    $bar->setMessage("sent={$sent} failed={$failed}");
                    $bar->advance();

                    // Har 25 ta yozuvdan keyin status yangilanadi
                    if (!$dryRun && $i % 25 === 0) {
                        Setting::set('student_survey_tg_sent', (string) $sent);
                        Setting::set('student_survey_tg_failed', (string) $failed);
                    }

                    if (!$dryRun) usleep(50_000);
                }
            }, 'id');

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 1);

        if (!$dryRun) {
            Setting::set('student_survey_tg_sent', (string) $sent);
            Setting::set('student_survey_tg_failed', (string) $failed);
            Setting::set('student_survey_tg_status', 'done');
            Setting::set('student_survey_tg_finished_at', now()->toDateTimeString());
        }

        $this->line("<options=bold>Yakuniy holat:</>");
        $this->line("  Yuborildi:   <fg=green>{$sent}</>");
        $this->line("  Xato:        <fg=red>{$failed}</>");
        $this->line("  Vaqt:        {$elapsed}s");
        $this->newLine();

        Log::info('Survey telegram CLI send finished', [
            'kind' => $kind,
            'sent' => $sent,
            'failed' => $failed,
            'dry_run' => $dryRun,
        ]);

        return self::SUCCESS;
    }
}

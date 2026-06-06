<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\StudentSurveyController;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSurveyCompletion;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
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
                            {--limit= : Faqat birinchi N talabaga yuborish (test uchun)}
                            {--concurrency=25 : Parallel yuboriladigan xabarlar soni (1=serial, 25=tavsiya)}';

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
        $concurrency = max(1, min(30, (int) $this->option('concurrency')));
        $botToken = config('services.telegram.bot_token');
        if (!$dryRun && !$botToken) {
            $this->error('Telegram bot token sozlanmagan (config/services.php).');
            return self::FAILURE;
        }

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
            Setting::set('student_survey_tg_last_error', '');
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
        $bar->setFormat(" %current%/%max% [%bar%] %percent:3s%%  %message%");
        $bar->setMessage('sent=0 failed=0');

        $sent = 0;
        $failed = 0;
        $i = 0;
        $startTime = microtime(true);
        $sendUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // Talabalarni batchlarga ajratib, har batch'ni parallel yuborish.
        // Telegram limit ~30 msg/sec — batchSize=$concurrency, batchdan keyin
        // kerak bo'lsa biroz to'xtab turamiz, lekin batchni iloji boricha to'liq.
        $batch = [];
        $process = function () use (&$batch, &$sent, &$failed, &$i, $bar, $messages, $dryRun, $sendUrl, $concurrency) {
            if (empty($batch)) return;

            if ($dryRun) {
                foreach ($batch as $s) {
                    $i++;
                    $loc = StudentSurveyController::detectStudentLocale($s);
                    $name = mb_substr($s->full_name ?? '—', 0, 30);
                    $this->line("  [DRY] #{$i} {$name} ({$s->telegram_chat_id}) → <fg=yellow>{$loc}</>");
                    $sent++;
                    $bar->setMessage("sent={$sent} failed={$failed}");
                    $bar->advance();
                }
                $batch = [];
                return;
            }

            // Parallel pool — har batchda barcha so'rovlar bir vaqtda jo'naydi.
            $batchStart = microtime(true);
            $responses = Http::pool(function (Pool $pool) use ($batch, $messages, $sendUrl) {
                $reqs = [];
                foreach ($batch as $idx => $s) {
                    $loc = StudentSurveyController::detectStudentLocale($s);
                    $reqs[] = $pool->as((string) $idx)
                        ->timeout(15)
                        ->post($sendUrl, [
                            'chat_id'    => (string) $s->telegram_chat_id,
                            'text'       => $messages[$loc],
                            'parse_mode' => 'HTML',
                        ]);
                }
                return $reqs;
            });

            foreach ($batch as $idx => $s) {
                $i++;
                $loc = StudentSurveyController::detectStudentLocale($s);
                $name = mb_substr($s->full_name ?? '—', 0, 30);
                $resp = $responses[$idx] ?? null;

                $ok = false;
                $note = '';
                if ($resp instanceof \Illuminate\Http\Client\ConnectionException || $resp instanceof \Throwable) {
                    $note = 'conn-error';
                } elseif ($resp && method_exists($resp, 'successful') && $resp->successful()) {
                    $ok = true;
                } else {
                    $status = $resp && method_exists($resp, 'status') ? $resp->status() : '???';
                    $body = $resp && method_exists($resp, 'json') ? ($resp->json('description') ?? '') : '';
                    $note = "HTTP {$status} {$body}";
                }

                if ($ok) {
                    $sent++;
                    $this->line("  <fg=green>✓</> #{$i} {$name} ({$s->telegram_chat_id}) → {$loc}");
                } else {
                    $failed++;
                    $this->line("  <fg=red>✗</> #{$i} {$name} ({$s->telegram_chat_id}) → {$loc}  <fg=gray>[{$note}]</>");
                }

                $bar->setMessage("sent={$sent} failed={$failed}");
                $bar->advance();
            }

            // Telegram limit: 30 msg/sec. Agar batch 1s'dan tezroq bajarilgan
            // bo'lsa, qolgan vaqtni kutamiz (xavfsiz pacing).
            $elapsed = microtime(true) - $batchStart;
            $minBatchTime = $concurrency / 28.0; // ~28 msg/sec — limit ostida
            if ($elapsed < $minBatchTime) {
                usleep((int) (($minBatchTime - $elapsed) * 1_000_000));
            }

            // Progress'ni status keylariga ham yozish (UI uchun)
            Setting::set('student_survey_tg_sent', (string) $sent);
            Setting::set('student_survey_tg_failed', (string) $failed);

            $batch = [];
        };

        $stopFlag = false;
        $query->select(['hemis_id', 'full_name', 'telegram_chat_id', 'department_name'])
            ->chunkById(500, function ($chunk) use (&$batch, $process, $concurrency, $limit, &$i, &$stopFlag) {
                foreach ($chunk as $student) {
                    if ($limit && ($i + count($batch)) >= $limit) {
                        $process();
                        $stopFlag = true;
                        return false;
                    }
                    $batch[] = $student;
                    if (count($batch) >= $concurrency) {
                        $process();
                    }
                }
            }, 'id');

        if (!$stopFlag && !empty($batch)) $process();

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

<?php

namespace App\Console\Commands;

use App\Models\Teacher;
use App\Models\User;
use App\Services\MoodleSyncMonitorService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Har kuni ertalab test_markazi rolidagi foydalanuvchilarga kechagi kun
 * uchun Moodle ↔ LMS sync ↔ Mark reconciliation hisobotini Telegram
 * orqali yuboradi. Farqlar (sync_gap + mark_gap) nol bo'lsa odatda
 * xabar yuborilmaydi (sokin); --force bayrog'i bilan har holda yuboriladi.
 */
class SendKunlikMonitoringReport extends Command
{
    protected $signature = 'kunlik-monitoring:send-report
                            {--date= : Sana (Y-m-d), default kecha}
                            {--chat-id= : Test uchun shaxsiy chat_id (faqat shu chatga yuboradi)}
                            {--force : Farq 0 bo\'lsa ham xabar yuborish}';

    protected $description = 'test_markazi roli foydalanuvchilariga kechagi kunlik monitoring hisobotini Telegram orqali yuboradi';

    public function handle(TelegramService $telegram, MoodleSyncMonitorService $monitor): int
    {
        $date = $this->option('date') ?: Carbon::yesterday()->format('Y-m-d');
        $singleChat = $this->option('chat-id');
        $force = (bool) $this->option('force');

        $this->info("Hisobot sanasi: {$date}");

        $resp = $monitor->getDailySummary($date, $date);
        if (!($resp['ok'] ?? false)) {
            Log::error('KunlikMonitoring report failed: ' . ($resp['error'] ?? 'unknown'));
            $this->error('Moodle WS error: ' . ($resp['error'] ?? 'unknown'));
            return self::FAILURE;
        }

        $days = $resp['days'] ?? [];
        if (empty($days)) {
            $this->info('Ma\'lumot yo\'q');
            return self::SUCCESS;
        }

        $day = $days[0];
        $attemptIds = array_map('intval', (array) ($day['attempt_ids'] ?? []));
        $moodleCount = (int) ($day['count'] ?? count($attemptIds));

        // Sync stage
        $syncedRows = collect();
        if (!empty($attemptIds)) {
            $syncedRows = DB::table('hemis_quiz_results')
                ->whereIn('attempt_id', $attemptIds)
                ->select('id', 'attempt_id')
                ->get();
        }
        $syncedCount = $syncedRows->count();
        $syncGap = $moodleCount - $syncedCount;

        // Mark stage
        $gradedCount = 0;
        if ($syncedRows->isNotEmpty()) {
            $qrIds = $syncedRows->pluck('id')->map(fn($v) => (int) $v)->all();
            $gradedCount = DB::table('student_grades')
                ->whereIn('quiz_result_id', $qrIds)
                ->whereNotNull('quiz_result_id')
                ->distinct()
                ->count('quiz_result_id');
        }
        $markGap = $syncedCount - $gradedCount;

        $this->line("Moodle: {$moodleCount} | Sync: {$syncedCount} | Mark: {$gradedCount}");
        $this->line("Sync gap: {$syncGap} | Mark gap: {$markGap}");

        if ($syncGap === 0 && $markGap === 0 && !$force) {
            $this->info('Farq yo\'q — xabar yuborilmaydi (--force bilan zo\'rlash mumkin)');
            return self::SUCCESS;
        }

        $msg = $this->buildMessage($date, $moodleCount, $syncedCount, $gradedCount, $syncGap, $markGap);

        // Yagona chat (test rejimi)
        if ($singleChat) {
            $ok = $telegram->sendToUser((string) $singleChat, $msg);
            $this->info($ok ? "Yuborildi: {$singleChat}" : "Xato: {$singleChat}");
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        // test_markazi rolidagi barcha foydalanuvchilar (User + Teacher)
        $recipients = $this->collectRecipients();
        if (empty($recipients)) {
            $this->warn('test_markazi rolida telegram_chat_id ulangan foydalanuvchi topilmadi');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($recipients as $chatId => $label) {
            if ($telegram->sendToUser($chatId, $msg)) {
                $sent++;
            } else {
                $failed++;
                Log::warning("KunlikMonitoring report: yuborib bo'lmadi - {$label} ({$chatId})");
            }
        }

        $this->info("Yuborildi: {$sent}, xato: {$failed}, jami: " . count($recipients));
        return self::SUCCESS;
    }

    private function buildMessage(string $date, int $moodle, int $synced, int $graded, int $syncGap, int $markGap): string
    {
        $formatted = Carbon::parse($date)->format('d.m.Y');

        $msg  = "📊 <b>Kunlik test natijalari hisoboti</b>\n";
        $msg .= "Sana: <b>{$formatted}</b>\n\n";
        $msg .= "🅼 Moodle: <b>{$moodle}</b> ta urinish\n";
        $msg .= "📥 LMS sync: <b>{$synced}</b>\n";
        $msg .= "✅ Markda: <b>{$graded}</b>\n\n";

        if ($syncGap > 0) {
            $msg .= "⚠️ <b>Sync farq:</b> {$syncGap} ta natija Moodle'da bor, lekin LMS bazasiga sinxronlanmagan.\n";
        }
        if ($markGap > 0) {
            $msg .= "⚠️ <b>Mark farq:</b> {$markGap} ta natija LMS'da bor, lekin baho (mark) qilib yuklanmagan.\n";
        }
        if ($syncGap === 0 && $markGap === 0) {
            $msg .= "✅ Hammasi joyida — farq yo'q.\n";
        }

        $msg .= "\n🔍 Tafsilot va qo'lda yuklash uchun:\n";
        $msg .= "<i>Admin panel → Test markazi → Kunlik monitoring</i>";

        return $msg;
    }

    /**
     * test_markazi rolida bo'lgan va telegram ulangan foydalanuvchilarni
     * yig'adi (User va Teacher modellaridan, dublikatlar olib tashlanadi).
     *
     * @return array<string,string> chat_id => label
     */
    private function collectRecipients(): array
    {
        $out = [];

        // Users
        try {
            $users = User::role('test_markazi')
                ->whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get(['id', 'name', 'telegram_chat_id']);
            foreach ($users as $u) {
                $chatId = (string) $u->telegram_chat_id;
                if ($chatId !== '') {
                    $out[$chatId] = 'User #' . $u->id . ' ' . ($u->name ?? '');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('test_markazi User lookup failed: ' . $e->getMessage());
        }

        // Teachers
        try {
            $teachers = Teacher::role('test_markazi')
                ->whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->whereNotNull('telegram_verified_at')
                ->get(['id', 'first_name', 'last_name', 'telegram_chat_id']);
            foreach ($teachers as $t) {
                $chatId = (string) $t->telegram_chat_id;
                if ($chatId !== '') {
                    $name = trim(($t->last_name ?? '') . ' ' . ($t->first_name ?? ''));
                    $out[$chatId] = 'Teacher #' . $t->id . ' ' . $name;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('test_markazi Teacher lookup failed: ' . $e->getMessage());
        }

        return $out;
    }
}

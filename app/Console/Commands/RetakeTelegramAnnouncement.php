<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Telegram orqali bir martalik umumiy e'lon — qayta o'qish xizmati haqida.
 * Faqat `telegram_chat_id` va `telegram_verified_at` mavjud talabalarga.
 *
 * Foydalanish:
 *   php artisan retake:telegram-announcement
 *   php artisan retake:telegram-announcement --dry-run
 *   php artisan retake:telegram-announcement --limit=5
 */
class RetakeTelegramAnnouncement extends Command
{
    protected $signature = 'retake:telegram-announcement
                            {--dry-run : Yuborish o\'rniga sanab chiqadi}
                            {--limit= : Faqat shuncha talabaga yuborish (test uchun)}';

    protected $description = "Telegram orqali qayta o'qish haqida umumiy e'lon yuborish";

    public function handle(TelegramService $telegram): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Student::query()
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->orderBy('hemis_id');

        if ($limit) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Telegram'i tasdiqlangan talabalar: {$total}");

        if ($dry) {
            $this->warn("DRY-RUN — xabar yuborilmaydi.");
            return self::SUCCESS;
        }

        if (!$this->confirm("Haqiqatdan {$total} ta talabaga xabar yuborilsinmi?", true)) {
            $this->warn('Bekor qilindi.');
            return self::SUCCESS;
        }

        $text = $this->message();
        $sent = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(100, function ($students) use ($telegram, $text, &$sent, &$failed, $bar) {
            foreach ($students as $s) {
                $ok = $telegram->sendToUser((string) $s->telegram_chat_id, $text);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
                $bar->advance();
                usleep(40000); // ~25 msg/sec — Telegram API limitidan past
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Yuborildi: {$sent} · Xato: {$failed}");

        return self::SUCCESS;
    }

    private function message(): string
    {
        $url = config('app.url') . '/student/retake';
        return "📢 <b>Diqqat!</b>\n\n"
            . "Qayta o'qishga qolgan talabalar diqqatiga.\n\n"
            . "Qayta o'qish sanalari e'lon qilib borilmoqda.\n"
            . "Mark platformasi orqali qayta o'qish xizmatidan\n"
            . "foydalanishingiz mumkin.\n\n"
            . "🔗 {$url}\n\n"
            . "— TDTU Termiz filiali";
    }
}

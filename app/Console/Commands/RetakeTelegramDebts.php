<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Services\Retake\RetakeDebtService;
use App\Services\TelegramService;
use Illuminate\Console\Command;

/**
 * Qarzdorligi bor talabalarga shaxsiy Telegram xabar — qaysi fanlardan
 * qarzdor ekanini ko'rsatadi. Joriy semestr fanlari hisobga olinmaydi
 * (RetakeDebtService::debts allaqachon ularni istisno qiladi).
 *
 * Foydalanish:
 *   php artisan retake:telegram-debts
 *   php artisan retake:telegram-debts --dry-run
 *   php artisan retake:telegram-debts --limit=5
 */
class RetakeTelegramDebts extends Command
{
    protected $signature = 'retake:telegram-debts
                            {--dry-run : Yuborish o\'rniga sanab chiqadi}
                            {--limit= : Faqat shuncha talabaga yuborish (test uchun)}';

    protected $description = "Qarzdor talabalarga Telegram orqali qarzdorlik ro'yxatini yuborish";

    public function handle(TelegramService $telegram, RetakeDebtService $debtService): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $query = Student::query()
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_verified_at')
            ->whereNotNull('specialty_id')
            ->whereNotNull('level_code')
            ->orderBy('hemis_id');

        if ($limit) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Tasdiqlangan Telegram'i bor talabalar: {$total}");

        if (!$dry && !$this->confirm("Bularning qarzdorliklarini tekshirib, qarzdor bo'lganlariga xabar yuborilsinmi?", true)) {
            $this->warn('Bekor qilindi.');
            return self::SUCCESS;
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $url = config('app.url') . '/student/retake';

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(50, function ($students) use ($telegram, $debtService, $url, $dry, &$sent, &$skipped, &$failed, $bar) {
            foreach ($students as $student) {
                $bar->advance();
                try {
                    $debts = $debtService->debts($student);
                } catch (\Throwable $e) {
                    $failed++;
                    continue;
                }
                if ($debts->isEmpty()) {
                    $skipped++;
                    continue;
                }

                $lines = $debts->take(20)->map(fn ($d) =>
                    "• " . $d->subject_name
                    . " (" . ($d->semester_name ?? '—') . ") — "
                    . rtrim(rtrim(number_format((float) $d->credit, 1), '0'), '.') . " kredit"
                )->implode("\n");

                $total = $debts->count();
                $totalCredit = (float) $debts->sum(fn ($d) => (float) $d->credit);
                $more = $total > 20 ? "\n... va yana " . ($total - 20) . " ta fan" : '';

                $text = "⚠️ <b>Hurmatli talaba!</b>\n\n"
                    . "Mark platformasi ma'lumotlariga ko'ra, sizda\n"
                    . "oldingi semestrlarda quyidagi fanlardan\n"
                    . "qarzdorlik mavjud:\n\n"
                    . $lines . $more . "\n\n"
                    . "Jami: <b>{$total}</b> ta fan · <b>"
                    . rtrim(rtrim(number_format($totalCredit, 1), '0'), '.') . "</b> kredit\n\n"
                    . "Iltimos Mark platformasida tekshirib qayta o'qishga\n"
                    . "ariza yuboring.\n\n"
                    . "🔗 {$url}";

                if ($dry) {
                    $sent++;
                    continue;
                }

                $ok = $telegram->sendToUser((string) $student->telegram_chat_id, $text);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                }
                usleep(40000);
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Yuborildi: {$sent} · Qarzi yo'q: {$skipped} · Xato: {$failed}");

        return self::SUCCESS;
    }
}

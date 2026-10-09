<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Shikoyatlar Telegram manzillarini tekshirish: har bir manzilga sinov
 * xabari yuboriladi va Telegram javobi (yuborildi / xato sababi) chiqariladi.
 */
class TestComplaintTelegram extends Command
{
    protected $signature = 'complaints:telegram-test';

    protected $description = "Shikoyatlar xabari boradigan har bir Telegram guruh/mavzuga sinov xabari yuboradi";

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        $raw = config('services.telegram.complaints_chat_id') ?: config('services.telegram.registrar_group_id');
        $targets = TelegramService::chatIds($raw);

        if (!$token || empty($targets)) {
            $this->error('TELEGRAM_BOT_TOKEN yoki TELEGRAM_COMPLAINTS_CHAT_ID (TELEGRAM_REGISTRAR_GROUP_ID) sozlanmagan.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach ($targets as $target) {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                ...TelegramService::target($target),
                'text' => "Sinov: xalqaro talabalar shikoyatlari shu yerga keladi ({$target})",
            ]);

            if ($response->json('ok')) {
                $this->info("✓ {$target} — yuborildi");
            } else {
                $failed++;
                $this->error("✕ {$target} — " . ($response->json('description') ?? ('HTTP ' . $response->status())));
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

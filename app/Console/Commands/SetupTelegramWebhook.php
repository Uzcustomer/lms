<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetupTelegramWebhook extends Command
{
    protected $signature = 'telegram:setup-webhook';
    protected $description = 'Telegram bot webhook ni o\'rnatish';

    public function handle()
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            $this->error('TELEGRAM_BOT_TOKEN .env faylida o\'rnatilmagan!');
            return 1;
        }

        $webhookUrl = url("/telegram/webhook/{$token}");

        $this->info("Webhook URL: {$webhookUrl}");

        $response = Http::post("https://api.telegram.org/bot{$token}/setWebhook", [
            'url' => $webhookUrl,
            // Tugmalar (dars jadvali to'siq so'rovlari) uchun callback_query ham kerak.
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if ($response->successful() && $response->json('ok')) {
            $this->info('Telegram webhook muvaffaqiyatli o\'rnatildi!');
            return 0;
        }

        $this->error('Xatolik: ' . $response->json('description', 'Noma\'lum xato'));
        return 1;
    }
}

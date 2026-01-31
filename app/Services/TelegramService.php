<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    public function notify(string $message): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            Log::warning('Telegram credentials not configured');
            return;
        }

        try {
            Http::retry(3, 1000)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::error('Telegramga yuborishda xato: ' . $e->getMessage());
        }
    }
}

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

    /**
     * Foydalanuvchining shaxsiy Telegram chat_id ga xabar yuborish
     */
    public function sendToUser(string $chatId, string $message): bool
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            Log::warning('Telegram bot token is not configured');
            return false;
        }

        try {
            Http::retry(3, 1000)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ])
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegramga yuborishda xato: ' . $e->getMessage());
            return false;
        }
    }
}

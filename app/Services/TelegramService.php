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
     * Xabar yuborish va message_id qaytarish (keyinroq editMessage uchun)
     */
    public function sendAndGetId(string $chatId, string $message): ?int
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            Log::warning('Telegram bot token is not configured');
            return null;
        }

        try {
            $response = Http::retry(3, 1000)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);

            if ($response->successful()) {
                return $response->json('result.message_id');
            }
        } catch (\Throwable $e) {
            Log::error('Telegram xabar yuborishda xato: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Mavjud xabarni tahrirlash (progress ko'rsatish uchun)
     */
    public function editMessage(string $chatId, int $messageId, string $newText): bool
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            return false;
        }

        try {
            Http::retry(2, 500)
                ->post("https://api.telegram.org/bot{$botToken}/editMessageText", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => $newText,
                ]);

            return true;
        } catch (\Throwable $e) {
            // "message is not modified" xatosini ignore qilish
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                Log::error('Telegram xabarni tahrirlashda xato: ' . $e->getMessage());
            }
            return false;
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
                    'parse_mode' => 'HTML',
                ])
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegramga yuborishda xato: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Telegram ga rasm (photo) yuborish
     */
    public function sendPhoto(string $chatId, string $photoPath, string $caption = ''): bool
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            Log::warning('Telegram bot token is not configured');
            return false;
        }

        try {
            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = $caption;
            }

            Http::retry(3, 1000)
                ->attach('photo', fopen($photoPath, 'r'), 'report.png')
                ->post("https://api.telegram.org/bot{$botToken}/sendPhoto", $params)
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram rasmni yuborishda xato: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Telegram ga hujjat (document) yuborish
     */
    public function sendDocument(string $chatId, string $filePath, string $caption = ''): bool
    {
        $botToken = config('services.telegram.bot_token');

        if (!$botToken) {
            Log::warning('Telegram bot token is not configured');
            return false;
        }

        try {
            $params = ['chat_id' => $chatId];
            if ($caption) {
                $params['caption'] = $caption;
            }

            $fileName = basename($filePath);

            Http::retry(3, 1000)
                ->attach('document', fopen($filePath, 'r'), $fileName)
                ->post("https://api.telegram.org/bot{$botToken}/sendDocument", $params)
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::error('Telegram hujjatni yuborishda xato: ' . $e->getMessage());
            return false;
        }
    }
}

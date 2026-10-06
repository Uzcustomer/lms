<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    /**
     * .env dagi bir yoki bir nechta chat ID: "-1001111,-1002222" -> ['-1001111', '-1002222'].
     * Vergul, nuqtali vergul yoki bo'sh joy bilan ajratish mumkin.
     */
    public static function chatIds($value): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/[\s,;]+/', trim((string) $value)),
            fn ($id) => $id !== ''
        )));
    }

    /**
     * Manzil: "chat_id" yoki "chat_id:mavzu_id".
     *
     * Forum (Topics) guruhida xabarni aniq mavzuga yuborish uchun chat id
     * yoniga mavzu raqami ikki nuqta bilan yoziladi: "-1001234567890:15".
     * Mavzu raqami — mavzudagi istalgan xabar havolasidagi o'rta son
     * (t.me/c/1234567890/15/27 -> 15). Shunday qilib .env dagi istalgan guruh
     * sozlamasi kod o'zgarmasdan mavzuga yo'naltiriladi.
     *
     * @return array{chat_id: string, message_thread_id?: int}
     */
    public static function target(string $chatId): array
    {
        $chatId = trim($chatId);
        $pos = strrpos($chatId, ':');
        if ($pos !== false) {
            $thread = substr($chatId, $pos + 1);
            if ($thread !== '' && ctype_digit($thread)) {
                return ['chat_id' => substr($chatId, 0, $pos), 'message_thread_id' => (int) $thread];
            }
        }

        return ['chat_id' => $chatId];
    }

    public function notify(string $message): void
    {
        $this->notifyChat(config('services.telegram.chat_id'), $message);
    }

    /**
     * Ko'rsatilgan chatga xabar yuborish (chat berilmasa umumiy chat_id ga).
     * Xato bo'lsa jim log qiladi — asosiy amal (baho tuzatish) buzilmaydi.
     */
    public function notifyChat(?string $chatId, string $message): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = $chatId ?: config('services.telegram.chat_id');

        if (!$botToken || !$chatId) {
            Log::warning('Telegram credentials not configured');
            return;
        }

        try {
            Http::retry(3, 1000)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    ...self::target($chatId),
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
                    ...self::target($chatId),
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
            $response = Http::retry(2, 500)
                ->post("https://api.telegram.org/bot{$botToken}/editMessageText", [
                    'chat_id' => self::target($chatId)['chat_id'],
                    'message_id' => $messageId,
                    'text' => $newText,
                ]);

            if (!$response->successful()) {
                $description = $response->json('description') ?? '';
                // "message is not modified" — bu xato emas, o'tkazib yuborish
                if (!str_contains($description, 'message is not modified')) {
                    Log::warning('Telegram editMessage muvaffaqiyatsiz: ' . $description);
                }
                return false;
            }

            return true;
        } catch (\Throwable $e) {
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
                    ...self::target($chatId),
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
            // Multipart: hamma qiymat matn bo'lsin (mavzu raqami ham)
            $params = array_map('strval', self::target($chatId));
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
            // Multipart: hamma qiymat matn bo'lsin (mavzu raqami ham)
            $params = array_map('strval', self::target($chatId));
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

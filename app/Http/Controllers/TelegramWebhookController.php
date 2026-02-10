<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request, string $token)
    {
        // Token tekshiruvi — faqat haqiqiy Telegram so'rovlarini qabul qilish
        if ($token !== config('services.telegram.bot_token')) {
            abort(404);
        }

        $data = $request->all();

        $message = $data['message'] ?? null;
        if (!$message) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $username = $message['from']['username'] ?? null;

        if (!$chatId || !$text) {
            return response()->json(['ok' => true]);
        }

        // /start buyrug'i
        if (str_starts_with($text, '/start')) {
            $code = trim(str_replace('/start', '', $text));

            if ($code) {
                return $this->verifyCode($chatId, $code, $username);
            }

            $this->sendMessage($chatId, "Assalomu alaykum! Tasdiqlash kodingizni yuboring yoki saytdagi havolani bosing.");
            return response()->json(['ok' => true]);
        }

        // Oddiy matn — tasdiqlash kodi deb tekshirish
        return $this->verifyCode($chatId, $text, $username);
    }

    private function verifyCode(int $chatId, string $code, ?string $username)
    {
        $code = strtoupper(trim($code));

        // Avval o'qituvchilardan qidiramiz
        $user = Teacher::where('telegram_verification_code', $code)
            ->whereNull('telegram_verified_at')
            ->first();

        // Topilmasa, talabalardan qidiramiz
        if (!$user) {
            $user = Student::where('telegram_verification_code', $code)
                ->whereNull('telegram_verified_at')
                ->first();
        }

        if (!$user) {
            $this->sendMessage($chatId, "Tasdiqlash kodi topilmadi yoki allaqachon tasdiqlangan. Iltimos, kodingizni tekshiring.");
            return response()->json(['ok' => true]);
        }

        $user->telegram_chat_id = (string) $chatId;
        $user->telegram_verified_at = now();
        if ($username) {
            $user->telegram_username = '@' . $username;
        }
        $user->save();

        $this->sendMessage($chatId, "Telegram hisobingiz muvaffaqiyatli tasdiqlandi! Endi saytga qaytishingiz mumkin.");

        return response()->json(['ok' => true]);
    }

    private function sendMessage(int $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');
        if (!$token) {
            Log::warning('Telegram bot token is not configured');
            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}

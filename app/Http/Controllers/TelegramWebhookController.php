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
        $chatType = $message['chat']['type'] ?? null;
        $text = trim($message['text'] ?? '');
        $username = $message['from']['username'] ?? null;

        if (!$chatId || !$text) {
            return response()->json(['ok' => true]);
        }

        // Faqat shaxsiy chatda ishlash — guruh chatlarini e'tiborsiz qoldirish
        if ($chatType !== 'private') {
            return response()->json(['ok' => true]);
        }

        // /start buyrug'i
        if (str_starts_with($text, '/start')) {
            $code = trim(str_replace('/start', '', $text));

            if ($code) {
                return $this->verifyCode($chatId, $code, $username);
            }

            $this->sendMessage($chatId, "Assalomu alaykum! \xF0\x9F\x91\x8B\n\nBu TDTU LMS tizimi uchun tasdiqlash botidir.\n\nTasdiqlash kodingizni yuboring yoki saytdagi/ilovadagi havolani bosing.");
            return response()->json(['ok' => true]);
        }

        // Oddiy matn — tasdiqlash kodi deb tekshirish
        return $this->verifyCode($chatId, $text, $username);
    }

    private function verifyCode(int $chatId, string $code, ?string $username)
    {
        $code = self::normalizeCode(trim($code));

        // Avval o'qituvchilardan qidiramiz
        $user = Teacher::where('telegram_verification_code', $code)
            ->first();

        // Topilmasa, talabalardan qidiramiz
        if (!$user) {
            $user = Student::where('telegram_verification_code', $code)
                ->first();
        }

        if (!$user) {
            $this->sendMessage($chatId, "Tasdiqlash kodi topilmadi. Iltimos, kodingizni tekshirib qaytadan yuboring.");
            return response()->json(['ok' => true]);
        }

        if ($user->telegram_verified_at) {
            $this->sendMessage($chatId, "Bu tasdiqlash kodi allaqachon ishlatilgan. Agar qayta tasdiqlash kerak bo'lsa, saytdan yangi kod oling.");
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

    /**
     * Chalkashmas belgilar bilan tasdiqlash kodi generatsiya qilish.
     * 0/O, 1/I/L kabi chalkash belgilar chiqarib tashlangan.
     */
    public static function generateVerificationCode(int $length = 6): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $code;
    }

    /**
     * Foydalanuvchi kiritgan kodni normalizatsiya qilish.
     * Chalkash belgilarni to'g'ri belgilarga almashtiradi.
     */
    public static function normalizeCode(string $code): string
    {
        $code = strtoupper($code);
        // 0 -> O, 1 -> I kabi chalkash belgilarni almashtirish
        $code = str_replace(['0', '1'], ['O', 'I'], $code);
        return $code;
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

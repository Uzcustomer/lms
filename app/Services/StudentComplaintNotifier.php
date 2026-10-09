<?php

namespace App\Services;

use App\Models\StudentComplaint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Yangi shikoyat haqida registrator ofisi Telegram guruhiga (yoki uning
 * mavzusiga) xabar: matn, so'ng biriktirilgan rasmlar.
 *
 * Manzil: TELEGRAM_COMPLAINTS_CHAT_ID — bir yoki bir nechta, vergul bilan
 * ("-100111,-100222:15"; ":15" — guruhdagi mavzu). Bo'sh bo'lsa
 * TELEGRAM_REGISTRAR_GROUP_ID. Har bir manzilga alohida yuboriladi.
 */
class StudentComplaintNotifier
{
    public static function notify(StudentComplaint $complaint): void
    {
        $targets = TelegramService::chatIds(config('services.telegram.complaints_chat_id')
            ?: config('services.telegram.registrar_group_id'));

        foreach ($targets as $chatId) {
            self::sendTo($chatId, $complaint);
        }
    }

    private static function sendTo(string $chatId, StudentComplaint $complaint): void
    {
        try {
            $telegram = new TelegramService();
            $images = $complaint->images ?? [];

            $text = "📩 Yangi shikoyat #{$complaint->id} (xalqaro talaba)\n\n"
                . "👤 {$complaint->student_name}\n"
                . ($complaint->group_name ? "👥 Guruh: {$complaint->group_name}\n" : '')
                . ($complaint->student_id_number ? "🆔 ID: {$complaint->student_id_number}\n" : '')
                . "📞 Telefon: {$complaint->phone}\n\n"
                . "📝 " . mb_substr($complaint->message, 0, 3000) . "\n\n"
                . (count($images) ? '🖼 Rasmlar: ' . count($images) . ' ta (quyida)' : '');

            $telegram->notifyChat($chatId, $text);

            foreach (array_values($images) as $i => $path) {
                if (!Storage::disk(StudentComplaint::DISK)->exists($path)) {
                    continue;
                }
                $telegram->sendPhoto(
                    $chatId,
                    Storage::disk(StudentComplaint::DISK)->path($path),
                    "Shikoyat #{$complaint->id} — rasm " . ($i + 1) . '/' . count($images)
                );
            }
        } catch (\Throwable $e) {
            // Telegram nosozligi shikoyatni saqlashga ta'sir qilmasin.
            Log::warning('Shikoyat Telegram xabari yuborilmadi: ' . $e->getMessage(), ['complaint_id' => $complaint->id]);
        }
    }
}

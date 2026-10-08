<?php

namespace App\Services;

use App\Models\StudentComplaint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Yangi shikoyat haqida registrator ofisi Telegram guruhiga (yoki uning
 * mavzusiga) xabar: matn, so'ng biriktirilgan rasmlar.
 *
 * Manzil: TELEGRAM_COMPLAINTS_CHAT_ID ("chat_id:mavzu_id"), bo'lmasa
 * TELEGRAM_REGISTRAR_GROUP_ID.
 */
class StudentComplaintNotifier
{
    public static function notify(StudentComplaint $complaint): void
    {
        $chatId = (string) (config('services.telegram.complaints_chat_id')
            ?: config('services.telegram.registrar_group_id'));
        if ($chatId === '') {
            return;
        }

        try {
            $telegram = new TelegramService();
            $images = $complaint->images ?? [];

            $text = "📩 Yangi shikoyat #{$complaint->id} (xalqaro talaba)\n\n"
                . "👤 {$complaint->student_name}\n"
                . ($complaint->group_name ? "👥 Guruh: {$complaint->group_name}\n" : '')
                . ($complaint->student_id_number ? "🆔 ID: {$complaint->student_id_number}\n" : '')
                . "📞 Telefon: {$complaint->phone}\n\n"
                . "📝 " . mb_substr($complaint->message, 0, 3000) . "\n\n"
                . (count($images) ? '🖼 Rasmlar: ' . count($images) . " ta (quyida)\n" : '')
                . '🔗 ' . route('admin.student-complaints.index');

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

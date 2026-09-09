<?php

namespace App\Services;

use App\Models\DistributionDraftAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Guruhi o'zgargan talabaga Telegram xabari.
 *
 * Ikki joydan chaqiriladi:
 *  - talaba ovoz berganda — darhol, avtomatik (GroupVoteController);
 *  - registrator qo'lda ko'chirganlarga — "Telegram xabar" tugmasidan
 *    (StudentDistributionController), ro'yxatdan tanlab.
 *
 * Ikkalasida ham bir xil matn va bir xil belgilash: notified_at va
 * notified_group_id yoziladi, shunda tugma o'sha talabani qayta yubormaydi.
 */
class DistributionNotifier
{
    public function __construct(private TelegramService $telegram)
    {
    }

    /**
     * Bitta reja bo'yicha xabar yuboradi.
     *
     * Qaytadi: 'sent' — yuborildi; 'no_telegram' — bot ulanmagan;
     *          'failed' — Telegram xatosi.
     */
    public function notify(DistributionDraftAssignment $draft): string
    {
        $chatId = $draft->student?->telegram_chat_id;
        if (empty($chatId)) {
            return 'no_telegram';
        }

        if (!$this->telegram->sendToUser((string) $chatId, $this->message($draft))) {
            return 'failed';
        }

        $this->markNotified($draft);

        return 'sent';
    }

    /** Xabar matni — ikkala yo'lda ham bir xil. */
    public function message(DistributionDraftAssignment $draft): string
    {
        return "<b>Guruhingiz o'zgardi</b>\n\n"
            . 'Hurmatli ' . e($draft->student_name) . ",\n"
            . 'siz <b>' . e((string) $draft->to_group_name) . "</b> guruhiga o'tkazildingiz.\n\n"
            . 'Avvalgi guruh: ' . e((string) ($draft->from_group_name ?: '—')) . "\n\n"
            . "Yangi guruhingiz bo'yicha dars jadvaliga rioya qiling.";
    }

    /**
     * Xabar yuborilganini belgilaydi.
     *
     * Query builder orqali: model save() qilsa updated_at ham yangilanib,
     * talabadagi popup "guruh yana o'zgardi" deb qayta chiqib ketardi.
     */
    private function markNotified(DistributionDraftAssignment $draft): void
    {
        if (!Schema::hasColumn('distribution_draft_assignments', 'notified_at')) {
            return;   // migratsiya hali bajarilmagan
        }

        DistributionDraftAssignment::query()
            ->where('id', $draft->id)
            ->update([
                'notified_at' => now(),
                'notified_group_id' => (int) $draft->to_group_hemis_id,
            ]);
    }

    /**
     * Talaba ovoz bergandan keyin darhol xabar — jarayonni to'xtatmaydi.
     * Telegram ishlamasa ovoz baribir qabul qilingan bo'ladi, xato faqat
     * logga tushadi va registrator tugmasi keyin qayta yuboradi.
     */
    public function notifyQuietly(DistributionDraftAssignment $draft): void
    {
        try {
            $this->notify($draft);
        } catch (\Throwable $e) {
            Log::warning('Taqsimot xabarini yuborib bo\'lmadi: ' . $e->getMessage(), [
                'student_id' => $draft->student_id,
            ]);
        }
    }
}

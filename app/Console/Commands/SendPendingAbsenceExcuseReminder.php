<?php

namespace App\Console\Commands;

use App\Models\AbsenceExcuse;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPendingAbsenceExcuseReminder extends Command
{
    protected $signature = 'absence-excuses:send-pending-reminder';

    protected $description = 'Har kuni 16:00 da kutilmoqda statusidagi sababli arizalar haqida registrator ofisi Telegram guruhiga eslatma yuborish';

    public function handle(TelegramService $telegram): int
    {
        $chatId = config('services.telegram.registrar_group_id');

        if (!$chatId) {
            $this->error('TELEGRAM_REGISTRAR_GROUP_ID sozlanmagan.');
            return 1;
        }

        $pendingExcuses = AbsenceExcuse::where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        if ($pendingExcuses->isEmpty()) {
            $this->info('Kutilmoqda statusidagi ariza topilmadi. Xabar yuborilmadi.');
            return 0;
        }

        $count = $pendingExcuses->count();
        $today = Carbon::now()->format('d.m.Y');

        $lines = [];
        $lines[] = "📋 <b>Sababli arizalar eslatmasi</b>";
        $lines[] = "📅 Sana: {$today} | Soat: 16:00";
        $lines[] = "";
        $lines[] = "⏳ <b>Kutilmoqda: {$count} ta ariza</b>";
        $lines[] = "";

        foreach ($pendingExcuses as $index => $excuse) {
            $num = $index + 1;
            $reasonLabel = AbsenceExcuse::REASONS[$excuse->reason]['label'] ?? $excuse->reason;
            $startDate = $excuse->start_date ? $excuse->start_date->format('d.m.Y') : '—';
            $endDate = $excuse->end_date ? $excuse->end_date->format('d.m.Y') : '—';
            $submittedAt = $excuse->created_at ? $excuse->created_at->format('d.m.Y') : '—';

            $lines[] = "{$num}. <b>{$excuse->student_full_name}</b>";
            $lines[] = "   👥 Guruh: {$excuse->group_name}";
            $lines[] = "   📌 Sabab: {$reasonLabel}";
            $lines[] = "   🗓 Sana: {$startDate} – {$endDate}";
            $lines[] = "   📤 Yuborilgan: {$submittedAt}";
            $lines[] = "";
        }

        $lines[] = "🔗 Arizalarni ko'rish: <a href=\"https://mark.tashmedunitf.uz/admin/absence-excuses?status=pending\">Admin panelga o'tish</a>";

        $message = implode("\n", $lines);

        $success = $telegram->sendToUser($chatId, $message);

        if ($success) {
            $this->info("Eslatma yuborildi. Kutilmoqda: {$count} ta ariza.");
            Log::info("Sababli arizalar eslatmasi yuborildi", ['pending_count' => $count]);
        } else {
            $this->error("Telegram xabar yuborishda xato.");
            Log::error("Sababli arizalar eslatmasini yuborishda xato", ['pending_count' => $count]);
            return 1;
        }

        return 0;
    }
}

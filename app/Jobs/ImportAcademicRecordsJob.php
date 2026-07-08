<?php

namespace App\Jobs;

use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ImportAcademicRecordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // 1 soat timeout — 359k+ yozuv import uchun yetarli
    public int $timeout = 3600;

    // Retry qilmaslik — muvaffaqiyatsiz bo'lsa qayta ishlatmaslik
    public int $tries = 1;

    public function handle(TelegramService $telegram, HemisService $hemisService): void
    {
        if (Cache::get('academic_import_lock')) {
            $telegram->notify("⚠️ Akademik qaydlar importi allaqachon ketayapti — qayta bosmaslik kerak!");
            return;
        }
        Cache::put('academic_import_lock', true, 3600);

        $startTime = microtime(true);
        $skippedCount = 0;
        $lastTelegramPct = -1;

        Cache::put('academic_import_progress', [
            'status'     => 'running',
            'page'       => 0,
            'pages'      => 1,
            'imported'   => 0,
            'percent'    => 0,
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        $telegram->notify("🟢 Akademik qaydlar importi boshlandi");

        try {
            $totalImported = $hemisService->importAcademicRecords(
                function ($page, $totalPages, $imported) use (&$skippedCount, &$lastTelegramPct, $telegram) {
                    $processed  = $page * 200;
                    $skippedCount = max(0, $processed - $imported);
                    $percent    = round(($page / $totalPages) * 100, 1);

                    Cache::put('academic_import_progress', [
                        'status'     => 'running',
                        'page'       => $page,
                        'pages'      => $totalPages,
                        'imported'   => $imported,
                        'percent'    => $percent,
                        'started_at' => now()->toDateTimeString(),
                    ], 3600);

                    // Har 25% da Telegram xabari
                    $milestone = (int) ($percent / 25) * 25;
                    if ($milestone > $lastTelegramPct && $milestone > 0 && $milestone < 100) {
                        $telegram->notify("📊 Akademik qaydlar import: {$milestone}% ({$page}/{$totalPages} sahifa, yangi: {$imported} ta)");
                        $lastTelegramPct = $milestone;
                    }
                }
            );
        } finally {
            Cache::forget('academic_import_lock');
        }

        $duration = round((microtime(true) - $startTime) / 60, 1);

        // Oxirgi muvaffaqiyatli sinxronizatsiya vaqti — progress keshidan
        // uzoqroq saqlanadi (behuda qayta yangilamaslik uchun sahifada ko'rsatiladi).
        Cache::forever('academic_records_last_synced_at', now()->toDateTimeString());

        Cache::put('academic_import_progress', [
            'status'      => 'done',
            'imported'    => $totalImported,
            'skipped'     => $skippedCount,
            'percent'     => 100,
            'duration'    => $duration,
            'finished_at' => now()->toDateTimeString(),
        ], 3600);

        $telegram->notify("✅ Akademik qaydlar importi tugadi!\nYangi/o'zgargan: {$totalImported} ta\nO'tkazib: {$skippedCount} ta\nVaqt: {$duration} daqiqa");

        // student_subjects ham yangilash
        \Illuminate\Support\Facades\Artisan::call('import:student-subjects');
    }
}

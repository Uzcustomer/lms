<?php

namespace App\Jobs;

use App\Services\VedomostSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncVedomostSubmissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function handle(VedomostSubmissionService $service): void
    {
        $lockKey = 'vedomost_submission_sync_lock';
        $progressKey = 'vedomost_submission_sync_progress';
        Cache::forget('vedomost_submission_sync_queued');

        if (Cache::has($lockKey)) {
            // Boshqa sync ishlab turibdi — u tugagach yangi o'zgarishlar uchun yana ishlaydi
            Cache::put('vedomost_submission_sync_rerun', true, now()->addHours(2));
            return;
        }

        Cache::put($lockKey, true, now()->addHours(2));
        Cache::put($progressKey, [
            'status' => 'running',
            'message' => "Joriy semester bo'yicha yangilash ishlamoqda...",
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        try {
            $count = $service->sync();

            Cache::put($progressKey, [
                'status' => 'done',
                'message' => "Joriy semester bo'yicha {$count} ta vedomost yozuvi yangilandi.",
                'count' => $count,
                'updated_at' => now()->toDateTimeString(),
            ], now()->addMinutes(30));
        } catch (\Throwable $e) {
            Cache::put($progressKey, [
                'status' => 'error',
                'message' => "Yangilashda xatolik: {$e->getMessage()}",
                'updated_at' => now()->toDateTimeString(),
            ], now()->addHours(2));

            throw $e;
        } finally {
            Cache::forget($lockKey);
            // Sync paytida yopilish shakli o'zgargan bo'lsa — yana bir marta
            if (Cache::pull('vedomost_submission_sync_rerun')) {
                static::dispatch()->delay(now()->addSeconds(10));
            }
        }
    }
}

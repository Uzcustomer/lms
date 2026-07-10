<?php

namespace App\Jobs;

use App\Services\VedomostSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncVedomostSubmissionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    public function handle(VedomostSubmissionService $service): void
    {
        $lockKey = 'vedomost_submission_sync_lock';
        $progressKey = 'vedomost_submission_sync_progress';

        if (Cache::get($lockKey)) {
            return;
        }

        Cache::put($lockKey, true, 3600);
        $startedAt = now();

        Cache::put($progressKey, [
            'status' => 'running',
            'message' => 'Vedomost yozuvlari yangilanmoqda...',
            'started_at' => $startedAt->toDateTimeString(),
            'finished_at' => null,
            'count' => 0,
        ], 3600);

        try {
            $count = $service->sync();

            Cache::put($progressKey, [
                'status' => 'done',
                'message' => "Joriy semestr bo'yicha {$count} ta vedomost yozuvi yangilandi.",
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
                'count' => $count,
            ], 3600);
        } catch (\Throwable $e) {
            Log::error('SyncVedomostSubmissionsJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Cache::put($progressKey, [
                'status' => 'error',
                'message' => "Yangilashda xatolik: {$e->getMessage()}",
                'started_at' => $startedAt->toDateTimeString(),
                'finished_at' => now()->toDateTimeString(),
                'count' => 0,
            ], 3600);

            throw $e;
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put('vedomost_submission_sync_progress', [
            'status' => 'error',
            'message' => "Yangilashda xatolik: {$e->getMessage()}",
            'started_at' => now()->toDateTimeString(),
            'finished_at' => now()->toDateTimeString(),
            'count' => 0,
        ], 3600);

        Cache::forget('vedomost_submission_sync_lock');
    }
}

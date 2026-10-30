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

/**
 * Talabalarni HEMIS'dan import qilish (students:import bilan bir xil) — Talabalar
 * sahifasidan ishga tushiriladi. Har sahifadan keyin holat keshga yoziladi,
 * sahifa progress bar va tugash vaqtini ko'rsatadi.
 */
class ImportStudentsWithProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PROGRESS_KEY = 'students_import_progress';

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public string $startedBy = '')
    {
    }

    public function handle(HemisService $hemisService, TelegramService $telegram): void
    {
        ini_set('memory_limit', '512M');

        $state = [
            'status' => 'running',
            'page' => 0,
            'pages' => 0,
            'imported' => 0,
            'total' => 0,
            'percent' => 0,
            'started_by' => $this->startedBy,
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'message' => "HEMIS'dan talabalar olinmoqda...",
        ];
        $save = function () use (&$state) {
            $state['updated_at'] = now()->toDateTimeString();
            Cache::put(self::PROGRESS_KEY, $state, now()->addDay());
        };
        $save();

        $telegram->notify("🟢 Talabalar importi boshlandi" . ($this->startedBy !== '' ? " ({$this->startedBy})" : ''));

        try {
            $count = $hemisService->importStudents(function (int $page, int $pageCount, int $imported, int $total) use (&$state, $save) {
                $state['page'] = $page;
                $state['pages'] = max(1, $pageCount);
                $state['imported'] = $imported;
                $state['total'] = $total;
                // Oxirgi qadam (chetlashganlarni belgilash) uchun 99% da to'xtaymiz
                $state['percent'] = min(99, (int) floor(100 * $page / $state['pages']));
                $state['message'] = "Sahifa {$page}/{$state['pages']} — {$imported} ta talaba ishlandi";
                $save();
            });

            $state['status'] = 'done';
            $state['percent'] = 100;
            $state['imported'] = $count;
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = "Import tugadi: {$count} ta talaba yangilandi";
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
            $telegram->notify("✅ Talabalar importi tugadi. Jami: {$count} ta");
        } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = 'Xatolik: ' . mb_substr($e->getMessage(), 0, 300)
                . ". Import yarim qoldi; hech bir talaba chetlashgan deb belgilanmadi.";
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
            $telegram->notify("❌ Talabalar importida xatolik: " . $e->getMessage());
        }
    }

    public function failed(?\Throwable $e): void
    {
        // Worker timeout / crash — holat "running" da qotib qolmasin
        $state = Cache::get(self::PROGRESS_KEY, []);
        if (in_array($state['status'] ?? null, ['running', 'queued'], true)) {
            $state['status'] = 'error';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = "Import to'xtadi: " . mb_substr((string) $e?->getMessage(), 0, 300);
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
        }
    }
}

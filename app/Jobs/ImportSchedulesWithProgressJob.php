<?php

namespace App\Jobs;

use App\Services\ScheduleImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Joriy semestr dars jadvalini HEMIS'dan import qilish (import:schedules mantig'i,
 * lekin faqat joriy semestr sanalari) — "Dars jadval mosligi" sahifasidan ishga
 * tushiriladi. Holat keshda saqlanadi, sahifa uni progress bar sifatida ko'rsatadi.
 */
class ImportSchedulesWithProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PROGRESS_KEY = 'schedule_import_progress';

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public string $startedBy = '')
    {
    }

    public function handle(ScheduleImportService $service): void
    {
        ini_set('memory_limit', '512M');

        $state = [
            'status' => 'running',
            'page' => 0,
            'pages' => 0,
            'total' => 0,
            'started_by' => $this->startedBy,
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'message' => "HEMIS'dan dars jadvali olinmoqda...",
        ];
        Cache::put(self::PROGRESS_KEY, $state, now()->addDay());

        $lastLine = '';

        // Faqat joriy semestr (tezroq). Oraliq aniqlanmasa — butun o'quv yili.
        $range = $service->currentSemesterRange();
        $state['scope'] = $range
            ? 'Joriy semestr: ' . $range['from']->format('d.m.Y') . ' – ' . $range['to']->format('d.m.Y')
            : "Butun o'quv yili (joriy semestr sanalari topilmadi)";
        Cache::put(self::PROGRESS_KEY, $state, now()->addDay());

        try {
            $service->importByEducationYear(
                function ($msg) use (&$lastLine) {
                    $lastLine = (string) $msg;
                },
                false,
                function (int $page, int $pages, int $total) use (&$state) {
                    $state['page'] = $page;
                    $state['pages'] = $pages;
                    $state['total'] = $total;
                    $state['updated_at'] = now()->toDateTimeString();
                    Cache::put(self::PROGRESS_KEY, $state, now()->addDay());
                },
                $range
            );

            $state['status'] = 'done';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            // Servisning yakuniy qatori: "✅ ... | N yozuv | Xm Ys | K o'chirildi"
            $state['message'] = $lastLine !== '' ? $lastLine : 'Import yakunlandi';
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
        } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = 'Xatolik: ' . mb_substr($e->getMessage(), 0, 300);
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));

            throw $e;
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

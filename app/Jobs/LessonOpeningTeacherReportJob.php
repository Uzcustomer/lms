<?php

namespace App\Jobs;

use App\Exports\LessonOpeningTeacherReportExport;
use App\Services\LessonOpeningTeacherReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Dars ochish arizalari hisobotini (o'qituvchilar kesimida) Excelga fon
 * jarayonida tayyorlaydi. Butun semestr jadvali va baholari ko'rilishi mumkin,
 * shuning uchun sahifadagi so'rov (504) o'rniga job navbatda hisoblaydi;
 * frontend holatni tekshirib turadi va tayyor bo'lgach faylni yuklab oladi.
 *
 * Holat ikki joyga yoziladi (JournalExamGradesExportJob dagidek): Cache va
 * storage/app/private dagi meta JSON — Cache tozalansa ham fayl qoladi.
 */
class LessonOpeningTeacherReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param  array{from: ?string, to: ?string}  $filters
     */
    public function __construct(
        private readonly array $filters,
        private readonly string $exportKey,
    ) {}

    public static function dirPath(): string
    {
        $dir = storage_path('app/private/exports/lesson_opening_teacher');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function relativeXlsx(string $exportKey): string
    {
        return 'exports/lesson_opening_teacher/'.md5($exportKey).'.xlsx';
    }

    public static function pathsFor(string $exportKey): array
    {
        $hash = md5($exportKey);
        $dir = self::dirPath();

        return [
            'xlsx' => $dir.'/'.$hash.'.xlsx',
            'meta' => $dir.'/'.$hash.'.json',
        ];
    }

    public function handle(LessonOpeningTeacherReport $report): void
    {
        @ini_set('memory_limit', '1024M');

        try {
            $this->updateStatus('running', 'Baholar tekshirilmoqda...', 20);

            $data = $report->build($this->filters['from'] ?? null, $this->filters['to'] ?? null);

            $this->updateStatus('running', 'Excel fayl yaratilmoqda...', 80);

            $fileName = $this->fileName($data['from']->format('Y-m-d'), $data['to']->format('Y-m-d'));
            Excel::store(
                new LessonOpeningTeacherReportExport($data),
                self::relativeXlsx($this->exportKey),
                'local'
            );

            Log::info('[LessonOpeningTeacherReportJob] Tayyor', [
                'export_key' => $this->exportKey,
                'teachers' => count($data['teachers']),
                'days' => count($data['days']),
            ]);

            $this->updateStatus('done', 'Tayyor', 100, $fileName);
        } catch (\Throwable $e) {
            Log::error('[LessonOpeningTeacherReportJob] Xato: '.$e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
            $this->updateStatus('failed', 'Xato: '.mb_substr($e->getMessage(), 0, 120), 0);
        }
    }

    private function fileName(string $from, string $to): string
    {
        return 'dars-ochish-oqituvchilar-'.$from.'_'.$to.'.xlsx';
    }

    private function updateStatus(string $status, string $message, int $percent, ?string $fileName = null): void
    {
        $payload = [
            'status' => $status,
            'message' => $message,
            'percent' => $percent,
            'file_name' => $fileName,
            'updated_at' => now()->toDateTimeString(),
        ];

        try {
            Cache::put($this->exportKey, $payload, 1800);
        } catch (\Throwable $e) {
            Log::warning('[LessonOpeningTeacherReportJob] Cache::put muvaffaqiyatsiz: '.$e->getMessage());
        }

        try {
            $paths = self::pathsFor($this->exportKey);
            file_put_contents($paths['meta'], json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('[LessonOpeningTeacherReportJob] Meta yozish muvaffaqiyatsiz: '.$e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[LessonOpeningTeacherReportJob] Failed: '.$exception->getMessage());
        $this->updateStatus('failed', mb_substr($exception->getMessage(), 0, 120), 0);
    }
}

<?php

namespace App\Jobs;

use App\Console\Commands\ImportAttendanceControls;
use App\Console\Commands\ImportGrades;
use App\Models\Setting;
use App\Services\ScheduleImportService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncReportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    // Baholar har bir kun uchun alohida olinadi — uzoq oraliq ko'p vaqt oladi
    public int $timeout = 1800;

    private string $dateFrom;
    private string $dateTo;
    private string $syncKey;
    private string $startedBy;

    public function __construct(string $dateFrom, string $dateTo, string $syncKey, string $startedBy = '')
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->syncKey = $syncKey;
        $this->startedBy = $startedBy;
    }

    public function handle(ScheduleImportService $service): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        // Tanlangan oraliqdagi har bir kun uchun baholar alohida olinadi
        $days = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days[] = $day->toDateString();
        }

        // Qadamlar: jadval(1) + davomat(1) + har bir kun baholari
        $totalSteps = 2 + count($days);
        $currentStep = 0;

        try {
            // 1-bosqich: Jadval (schedules) — butun oraliq bitta so'rov
            $this->updateProgress('Jadval yangilanmoqda...', $currentStep, $totalSteps);
            $service->importBetween($from, $to);
            $currentStep++;

            // 2-bosqich: Davomat nazorati — butun oraliq bitta API chaqiruv
            $this->updateProgress("Davomat olinmoqda: {$from->toDateString()} — {$to->toDateString()}", $currentStep, $totalSteps);
            try {
                Artisan::call(ImportAttendanceControls::class, [
                    '--date-from' => $from->toDateString(),
                    '--date-to' => $to->toDateString(),
                    '--silent' => true,
                ]);
            } catch (\Throwable $e) {
                Log::warning("[SyncReportDataJob] Davomat xato: {$e->getMessage()}");
            }
            $currentStep++;

            // 3-bosqich: Baholar — tanlangan har bir kun uchun HEMIS dan
            foreach ($days as $index => $day) {
                $this->updateProgress(
                    'Baholar olinmoqda: ' . $day . ' (' . ($index + 1) . '/' . count($days) . ')',
                    $currentStep,
                    $totalSteps
                );

                try {
                    Artisan::call(ImportGrades::class, [
                        '--date' => $day,
                        '--silent' => true,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("[SyncReportDataJob] Baholar xato ({$day}): {$e->getMessage()}");
                }

                $currentStep++;
            }

            $this->rememberLastSync();
            $this->updateProgress('Tayyor', $totalSteps, $totalSteps, 'done');

        } catch (\Throwable $e) {
            Log::error("[SyncReportDataJob] Xato: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress("Xato: " . mb_substr($e->getMessage(), 0, 120), $currentStep, $totalSteps, 'failed');
        }
    }

    /**
     * Oxirgi yangilanish sahifada doimiy ko'rinadi, shuning uchun keshda
     * emas, sozlamalarda saqlanadi.
     */
    private function rememberLastSync(): void
    {
        try {
            Setting::set('lesson_assignment_last_sync', json_encode([
                'at' => now()->toDateTimeString(),
                'by' => $this->startedBy,
                'from' => $this->dateFrom,
                'to' => $this->dateTo,
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('[SyncReportDataJob] Oxirgi yangilanishni saqlashda xato: ' . $e->getMessage());
        }
    }

    private function updateProgress(string $message, int $current, int $total, string $status = 'running'): void
    {
        // Boshlangan vaqt saqlanib qolsin — sahifada "necha vaqt bo'ldi" shundan
        $existing = Cache::get($this->syncKey);

        Cache::put($this->syncKey, [
            'status' => $status,
            'message' => $message,
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round($current / $total * 100) : 0,
            'started_at' => $existing['started_at'] ?? now()->toDateTimeString(),
            'started_by' => $existing['started_by'] ?? $this->startedBy,
            'updated_at' => now()->toDateTimeString(),
        ], 600);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SyncReportDataJob] Failed: {$exception->getMessage()}", [
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'trace' => mb_substr($exception->getTraceAsString(), 0, 500),
        ]);

        $msg = $exception->getMessage();
        // "attempted too many times" — odatda timeout yoki worker restart sabab
        if (str_contains($msg, 'attempted too many')) {
            $msg = "Vaqt tugadi yoki server qayta ishga tushdi. Qayta urinib ko'ring.";
        }

        Cache::put($this->syncKey, [
            'status' => 'failed',
            'message' => mb_substr($msg, 0, 120),
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 600);
    }
}

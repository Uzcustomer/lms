<?php

namespace App\Jobs;

use App\Console\Commands\ImportAttendanceControls;
use App\Console\Commands\ImportGrades;
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
    public int $timeout = 900; // 15 daqiqa — HEMIS API sekin bo'lishi mumkin

    private string $dateFrom;
    private string $dateTo;
    private string $syncKey;

    public function __construct(string $dateFrom, string $dateTo, string $syncKey)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->syncKey = $syncKey;
    }

    public function handle(ScheduleImportService $service): void
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();
        $today = Carbon::today();
        $totalDays = $from->diffInDays($to) + 1;
        $includestoday = $to->gte($today) && $from->lte($today);

        // Qadamlarni hisoblash:
        // jadval(1) + davomat(1) + baholar(faqat bugungi kun uchun 0 yoki 1)
        $gradeSteps = $includestoday ? 1 : 0;
        $totalSteps = 1 + 1 + $gradeSteps;
        $currentStep = 0;

        try {
            // 1-bosqich: Jadval (schedules) yangilash — butun oraliq bitta so'rov
            $this->updateProgress('Jadval yangilanmoqda...', $currentStep, $totalSteps);
            $service->importBetween($from, $to);
            $currentStep++;

            // 2-bosqich: Davomat nazorati — butun oraliq bitta API chaqiruv
            $this->updateProgress("Davomat: {$from->toDateString()} — {$to->toDateString()}", $currentStep, $totalSteps);
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

            // 3-bosqich: Baholar — faqat bugungi kun uchun va shartli
            // O'tgan kunlar: nightly final import allaqachon qilgan, skip
            // Bugungi kun: live_import_last_success tekshiruv
            if ($includestoday) {
                $todayStr = $today->toDateString();
                $liveImportSuccess = Cache::get('live_import_last_success');
                $isRecent = $liveImportSuccess && Carbon::parse($liveImportSuccess)->diffInMinutes(now()) <= 60;

                if ($isRecent) {
                    $this->updateProgress("Baholar: DB da yangi ({$todayStr})", $currentStep, $totalSteps);
                    Log::info("[SyncReportDataJob] Bugungi baholar skip — live import yaqinda muvaffaqiyatli: {$liveImportSuccess}");
                } else {
                    $this->updateProgress("Baholar: {$todayStr}", $currentStep, $totalSteps);
                    try {
                        Artisan::call(ImportGrades::class, [
                            '--date' => $todayStr,
                            '--silent' => true,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning("[SyncReportDataJob] Baholar xato ({$todayStr}): {$e->getMessage()}");
                    }
                }
                $currentStep++;
            }

            $this->updateProgress('Tayyor', $totalSteps, $totalSteps, 'done');

        } catch (\Throwable $e) {
            Log::error("[SyncReportDataJob] Xato: {$e->getMessage()}", [
                'trace' => $e->getTraceAsString(),
            ]);

            $this->updateProgress("Xato: " . mb_substr($e->getMessage(), 0, 120), $currentStep, $totalSteps, 'failed');
        }
    }

    private function updateProgress(string $message, int $current, int $total, string $status = 'running'): void
    {
        Cache::put($this->syncKey, [
            'status' => $status,
            'message' => $message,
            'current' => $current,
            'total' => $total,
            'percent' => $total > 0 ? round($current / $total * 100) : 0,
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

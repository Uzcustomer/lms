<?php

namespace App\Jobs;

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
    public int $timeout = 600;

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
        $totalDays = $from->diffInDays($to) + 1;

        // Jami qadamlar: jadval(1) + davomat(totalDays) + baholar(totalDays)
        $totalSteps = 1 + $totalDays + $totalDays;
        $currentStep = 0;

        try {
            // 1-bosqich: Jadval (schedules) yangilash
            $this->updateProgress('Jadval yangilanmoqda...', $currentStep, $totalSteps);
            $service->importBetween($from, $to);
            $currentStep++;

            // 2-bosqich: Davomat nazorati (attendance_controls) har bir kun
            $current = $from->copy();
            while ($current->lte($to)) {
                $dateStr = $current->toDateString();
                $this->updateProgress("Davomat: {$dateStr}", $currentStep, $totalSteps);

                Artisan::call('import:attendance-controls', [
                    '--date' => $dateStr,
                    '--silent' => true,
                ]);

                $currentStep++;
                $current->addDay();
            }

            // 3-bosqich: Baholar (student_grades) har bir kun
            $current = $from->copy();
            while ($current->lte($to)) {
                $dateStr = $current->toDateString();
                $this->updateProgress("Baholar: {$dateStr}", $currentStep, $totalSteps);

                Artisan::call('student:import-data', [
                    '--date' => $dateStr,
                    '--silent' => true,
                ]);

                $currentStep++;
                $current->addDay();
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
        ], 600); // 10 daqiqa saqlanadi
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[SyncReportDataJob] Failed: {$exception->getMessage()}");

        Cache::put($this->syncKey, [
            'status' => 'failed',
            'message' => 'Job xato: ' . mb_substr($exception->getMessage(), 0, 120),
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 600);
    }
}

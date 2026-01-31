<?php

namespace App\Jobs;

use App\Services\ScheduleImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportSchedulesPartiallyJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 7200;
    public int $backoff = 60;
    public int $uniqueFor = 3600;

    public function __construct(
        public string $date_from,
        public string $date_to
    )
    {
    }

    public function handle(ScheduleImportService $service): void
    {
        $from = Carbon::parse($this->date_from);
        $to = Carbon::parse($this->date_to);

        $service->importBetween($from, $to);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ImportSchedulesPartialy failed', [
            'from' => $this->date_from,
            'to' => $this->date_to,
            'error' => $exception->getMessage(),
        ]);
    }
}

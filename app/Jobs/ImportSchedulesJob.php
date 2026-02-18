<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use App\Models\Schedule;

class ImportSchedulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 7200;


    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Artisan::call('import:schedules');
            Artisan::call('command:independent-auto-create');
            // student:import-data scheduler orqali boshqariladi (live: har 30 daqiqa, final: 00:30)
            Artisan::call('grades:close-expired');
        } catch (\Exception $e) {
            logger()->error('Import Schedule Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception)
    {
        logger()->error('Import Schedule Failed: ' . $exception->getMessage());
    }
}

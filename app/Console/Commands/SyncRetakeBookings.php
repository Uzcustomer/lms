<?php

namespace App\Console\Commands;

use App\Services\RetakeBookingService;
use Illuminate\Console\Command;

/**
 * Moodle'ga yetib bormagan qayta topshirish bronlarini (moodle_sync = pending
 * yoki failed) qayta yuboradi. Moodle API ga daqiqasiga 60 tadan ortiq
 * so'rov ketmasligi uchun so'rovlar orasida pauza qilinadi.
 */
class SyncRetakeBookings extends Command
{
    protected $signature = 'retake-bookings:sync
        {--limit=200 : Bir ishga tushishda ko\'pi bilan shuncha yozuv}
        {--pause=1000 : So\'rovlar orasidagi pauza, millisekund (1000 = daqiqasiga 60)}';

    protected $description = "Qayta topshirish bronlarini Moodle'ga qayta yuborish (sinxronlanmaganlari)";

    public function handle(RetakeBookingService $service): int
    {
        $pauseMs = max(0, (int) $this->option('pause'));
        $bookings = RetakeBookingService::unsyncedQuery()->limit(max(1, (int) $this->option('limit')))->get();

        $ok = 0;
        $failed = 0;
        foreach ($bookings as $i => $booking) {
            if ($i > 0 && $pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
            $service->sync($booking) ? $ok++ : $failed++;
        }

        $this->info("Yuborildi: {$ok}, yetib bormadi: {$failed}");

        return self::SUCCESS;
    }
}

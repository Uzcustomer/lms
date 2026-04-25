<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TriggerMoodleSync extends Command
{
    protected $signature = 'moodle:trigger-sync';
    protected $description = 'Moodle quiz natijalarini import qilish uchun sync trigger qo\'yadi (Moodle serveridagi cron 3 daqiqa ichida ko\'radi va push skriptni ishga tushiradi)';

    public function handle()
    {
        $now = now()->toIso8601String();
        Setting::set('moodle_sync_requested', $now);

        $message = "Moodle sync trigger qo'yildi: {$now}";
        $this->info($message);
        Log::info('moodle:trigger-sync — ' . $message);

        return self::SUCCESS;
    }
}

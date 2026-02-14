<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('import:attendance-controls')->dailyAt('02:00');
Schedule::command('command:independent-auto-create')->dailyAt('06:00');

// Quiz natijalarni Moodle dan sinxronizatsiya (har 6 soatda)
// MOODLE_CRON_URL .env da sozlangan bo'lishi kerak
Schedule::command('quiz:trigger-moodle-sync')->everySixHours();

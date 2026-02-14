<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('import:attendance-controls')->dailyAt('02:00');
Schedule::command('command:independent-auto-create')->dailyAt('06:00');

// Quiz natijalarni Moodle dan SSH orqali sinxronizatsiya
// Qo'lda: php artisan quiz:trigger-moodle-sync
// Yoki diagnostika sahifasidagi "Moodle Cron" tugmasi orqali

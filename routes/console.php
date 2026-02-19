<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('import:attendance-controls')->dailyAt('02:00');
Schedule::command('command:independent-auto-create')->dailyAt('06:00');

// O'qituvchilarga davomat va baho eslatmalari (har kuni 13:00, 15:00, 17:00, 19:00, 21:00, 23:00)
Schedule::command('teachers:send-reminders')->dailyAt('13:00');
Schedule::command('teachers:send-reminders')->dailyAt('15:00');
Schedule::command('teachers:send-reminders')->dailyAt('17:00');
Schedule::command('teachers:send-reminders')->dailyAt('19:00');
Schedule::command('teachers:send-reminders')->dailyAt('21:00');
Schedule::command('teachers:send-reminders')->dailyAt('23:00');

// Hisobotdan oldin bugungi attendance_controls yangilansin (--date=today, --silent — tez ishlaydi)
Schedule::command('import:attendance-controls --date=' . now()->toDateString() . ' --silent')->dailyAt('13:50');
Schedule::command('import:attendance-controls --date=' . now()->toDateString() . ' --silent')->dailyAt('17:50');
Schedule::command('import:attendance-controls --date=' . now()->toDateString() . ' --silent')->dailyAt('21:50');

// Telegram guruhga umumlashtirilgan hisobot (har kuni 14:00, 18:00, 22:00)
Schedule::command('teachers:send-group-summary')->dailyAt('14:00');
Schedule::command('teachers:send-group-summary')->dailyAt('18:00');
Schedule::command('teachers:send-group-summary')->dailyAt('22:00');

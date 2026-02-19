<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Davomat nazorati: kechasi 02:00 da FINAL import (kechagi va yakunlanmagan kunlarni is_final=true qiladi)
Schedule::command('import:attendance-controls --mode=final')->dailyAt('02:00');
Schedule::command('command:independent-auto-create')->dailyAt('06:00');

// O'qituvchilarga davomat va baho eslatmalari (har kuni 13:00, 15:00, 17:00, 19:00, 21:00, 23:00)
Schedule::command('teachers:send-reminders')->dailyAt('13:00');
Schedule::command('teachers:send-reminders')->dailyAt('15:00');
Schedule::command('teachers:send-reminders')->dailyAt('17:00');
Schedule::command('teachers:send-reminders')->dailyAt('19:00');
Schedule::command('teachers:send-reminders')->dailyAt('21:00');
Schedule::command('teachers:send-reminders')->dailyAt('23:00');

// Telegram guruhga umumlashtirilgan hisobot (har kuni 14:00, 18:00, 22:00)
// Hisobot o'zi ichida attendance_controls live import qiladi (SendAttendanceGroupSummary 1.5-qadam)
Schedule::command('teachers:send-group-summary')->dailyAt('14:00');
Schedule::command('teachers:send-group-summary')->dailyAt('18:00');
Schedule::command('teachers:send-group-summary')->dailyAt('22:00');

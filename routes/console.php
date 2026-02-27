<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Kechki import — bitta command ichida ketma-ket ishlatiladi (bitta Telegram xabar):
// 1. Jadval import (import:schedules)
// 2. Final import — baholar (student:import-data --mode=final)
// 3. Davomat nazorati FINAL (import:attendance-controls --mode=final)
Schedule::command('nightly:run')->dailyAt('00:00')->withoutOverlapping(180);

// 04:00 da retry: FAQAT oldingi run xato bergan bo'lsa qayta ishlaydi
Schedule::command('student:import-data --mode=final')->dailyAt('04:00')->when(function () {
    $lastSuccess = \Illuminate\Support\Facades\Cache::get('final_import_last_success');
    return !$lastSuccess || !Carbon::parse($lastSuccess)->isToday();
});
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
// 14:00 — faqat fakultet va kafedra kesimi
// 18:00 va 22:00 — fakultet, kafedra + o'qituvchilar kesimi (batafsil)
Schedule::command('teachers:send-group-summary')->dailyAt('14:00');
Schedule::command('teachers:send-group-summary --detail')->dailyAt('18:00');
Schedule::command('teachers:send-group-summary --detail')->dailyAt('22:00');

// Ertasi kuni ertalab 08:30 da kechagi kunning yakuniy hisoboti (faqat o'qituvchilar kesimi)
Schedule::command('teachers:send-final-daily-report')->dailyAt('08:30');

// 5 ga da'vogarlar hisoboti: SendAttendanceGroupSummary ichida (1.7-qadam)
// baholar import qilingandan keyin avtomatik chaqiriladi (18:00, 22:00 da)

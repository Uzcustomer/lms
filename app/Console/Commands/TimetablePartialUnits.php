<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use Illuminate\Console\Command;

/**
 * Qisman joylangan darslar (birliklar) ro'yxati: bir kunda guruhning bitta fandan amaliy soatlari bo'linmaydi — darsning bir
 * qismi joylanib, qolgani "Joylanmagan" panelida qolgan holatlar. Har qator: guruh, fan, hafta, kun, joylangan va qolgan soat.
 *
 * --split — bo'lingan birliklar: hamma bo'lak joylangan, lekin turli kunlarda yoki bir kunda oraliq bilan (hafta, guruh, fan, kunlar).
 *
 * Misol:
 *   php artisan timetable:partial-units 7
 *   php artisan timetable:partial-units 7 --week=8
 *   php artisan timetable:partial-units 7 --split
 */
class TimetablePartialUnits extends Command
{
    protected $signature = 'timetable:partial-units
        {board : Doska id}
        {--week=* : Qaysi haftalar (sukut — barchasi)}
        {--split : Bo\'lingan birliklar (turli kunlarda yoki oraliq bilan)}';

    protected $description = 'Qisman joylangan darslar ro\'yxati: guruh, fan, hafta, kun, joylangan va qolgan soat';

    public function handle(): int
    {
        $board = TimetableBoard::find((int) $this->argument('board'));
        if (!$board) {
            $this->error('Doska topilmadi.');

            return self::FAILURE;
        }
        $split = (bool) $this->option('split');
        $items = app(TimetableController::class)->partialUnitsFor($board, (array) $this->option('week'), $split);
        if ($items && $split) {
            $this->table(['Hafta', 'Guruh', 'Fan', "O'qituvchi", 'Kunlar', 'Soat'], array_map(fn ($u) => [
                $u['week'], trim(($u['faculty'] ? $u['faculty'] . ', ' : '') . $u['course'] . '-kurs · ' . $u['group']), $u['subject'], (string) $u['teacher'],
                $u['days_text'], $u['placed_hours'],
            ], $items));
        } elseif ($items) {
            $this->table(['Guruh', 'Fan', "O'qituvchi", 'Hafta', 'Kun', 'Joylangan (soat)', 'Qolgan (soat)'], array_map(fn ($u) => [
                trim(($u['faculty'] ? $u['faculty'] . ', ' : '') . $u['course'] . '-kurs · ' . $u['group']), $u['subject'], (string) $u['teacher'],
                $u['week'], $u['day_name'], $u['placed_hours'], $u['remaining_hours'],
            ], $items));
        }
        $this->info('Jami: ' . count($items));

        return self::SUCCESS;
    }
}

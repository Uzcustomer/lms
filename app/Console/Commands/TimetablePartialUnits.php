<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use Illuminate\Console\Command;

/**
 * Qisman joylangan darslar (birliklar) ro'yxati: bir kunda guruhning bitta fandan amaliy soatlari bo'linmaydi — darsning bir
 * qismi joylanib, qolgani "Joylanmagan" panelida qolgan holatlar. Har qator: guruh, fan, hafta, kun, joylangan va qolgan soat.
 *
 * Misol:
 *   php artisan timetable:partial-units 7
 *   php artisan timetable:partial-units 7 --week=8
 */
class TimetablePartialUnits extends Command
{
    protected $signature = 'timetable:partial-units
        {board : Doska id}
        {--week=* : Qaysi haftalar (sukut — barchasi)}';

    protected $description = 'Qisman joylangan darslar ro\'yxati: guruh, fan, hafta, kun, joylangan va qolgan soat';

    public function handle(): int
    {
        $board = TimetableBoard::find((int) $this->argument('board'));
        if (!$board) {
            $this->error('Doska topilmadi.');

            return self::FAILURE;
        }
        $items = app(TimetableController::class)->partialUnitsFor($board, (array) $this->option('week'));
        if ($items) {
            $this->table(['Guruh', 'Fan', "O'qituvchi", 'Hafta', 'Kun', 'Joylangan (soat)', 'Qolgan (soat)'], array_map(fn ($u) => [
                trim(($u['faculty'] ? $u['faculty'] . ', ' : '') . $u['course'] . '-kurs · ' . $u['group']), $u['subject'], (string) $u['teacher'],
                $u['week'], $u['day_name'], $u['placed_hours'], $u['remaining_hours'],
            ], $items));
        }
        $this->info('Jami: ' . count($items));

        return self::SUCCESS;
    }
}

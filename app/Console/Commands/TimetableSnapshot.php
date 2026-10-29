<?php

namespace App\Console\Commands;

use App\Models\TimetableBoard;
use App\Services\Timetable\BoardSnapshots;
use Illuminate\Console\Command;

/** Dars jadvali doskalarining kunlik nusxasi (versiyasi) va eski avtomatik nusxalarni tozalash. */
class TimetableSnapshot extends Command
{
    protected $signature = 'timetable:snapshot {board? : Doska id (bo\'sh — hammasi)} {--reason=daily} {--keep-days=30}';

    protected $description = 'Dars jadvali doskalarining nusxasini oladi (versiyalar tarixi)';

    public function handle(BoardSnapshots $snapshots): int
    {
        BoardSnapshots::raiseMemoryLimit();
        if (!BoardSnapshots::ready()) {
            $this->error('timetable_board_snapshots jadvali yo\'q — php artisan migrate');

            return 1;
        }
        $q = TimetableBoard::query();
        if ($this->argument('board')) {
            $q->where('id', (int) $this->argument('board'));
        }
        foreach ($q->get() as $board) {
            $s = $snapshots->take($board, (string) $this->option('reason'), 'cron');
            $this->line($board->id . ' ' . $board->name . ': ' . ($s ? $s->cards_count . ' karta, ' . $s->placed_count . ' joylashgan' : 'xato'));
        }
        $this->info('Tozalandi: ' . $snapshots->prune((int) $this->option('keep-days')) . ' ta eski nusxa');

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
use App\Services\Timetable\BoardSnapshots;
use Illuminate\Console\Command;

/**
 * Joylashuvni tiklash: doska nusxasidan (--snapshot=ID) yoki zaxira bazadan (--source-db=NOM — mysqldump
 * alohida bazaga yuklangan). Faqat hozir joylanmagan kartalar tiklanadi; sukut — sinov (--apply bilan yoziladi).
 */
class TimetableRestorePlacement extends Command
{
    protected $signature = 'timetable:restore-placement {board} {--snapshot=} {--source-db=} {--faculty=*} {--specialty=*} {--course=*} {--apply}';

    protected $description = 'Dars jadvali: nusxa yoki zaxira bazadan joylanmagan kartalarning joylashuvini tiklaydi';

    public function handle(BoardSnapshots $snapshots): int
    {
        BoardSnapshots::raiseMemoryLimit();
        $board = TimetableBoard::findOrFail((int) $this->argument('board'));
        if ($this->option('snapshot')) {
            $old = TimetableBoardSnapshot::where('board_id', $board->id)->findOrFail((int) $this->option('snapshot'))->data();
        } elseif ($this->option('source-db')) {
            $base = config('database.default');
            config(['database.connections.tt_restore_source' => array_merge(config("database.connections.$base"), ['database' => $this->option('source-db')])]);
            $old = $snapshots->collect($board, 'tt_restore_source');
            $this->info('Zaxira bazada: ' . count($old['cards']) . ' karta, ' . count($old['overrides']) . ' haftalik yozuv');
            // Zaxiradagi holat ham nusxa sifatida saqlanadi (keyin oynadan qayta tiklash mumkin).
            if ($this->option('apply')) {
                $snapshots->take($board, 'imported_backup', 'restore-placement', $old);
            }
        } else {
            $this->error('--snapshot=ID yoki --source-db=BAZA kerak');

            return 1;
        }
        $this->line('Har bir joylashuv to\'qnashuvga tekshiriladi — bir necha daqiqa ketishi mumkin.');
        $scope = ['faculty_names' => $this->option('faculty'), 'specialty_names' => $this->option('specialty'), 'courses' => $this->option('course')];
        $r = $snapshots->restorePlacement($board, $old, !$this->option('apply'), $scope, true,
            app(\App\Http\Controllers\Admin\TimetableController::class)->restoreConflictChecker(),
            function (int $done, int $total) {
                // Har bir joylashuv to'qnashuvga tekshiriladi — ko'p bo'lsa uzoq; jarayon shu yerda ko'rinadi.
                $this->output->write("\r  Tekshirildi: {$done}/{$total}" . ($done === $total ? "\n" : ''));
            });
        $this->info(($r['dry_run'] ? 'SINOV (yozilmadi)' : 'YOZILDI') . ": {$r['cards']} karta ({$r['units']} dars birligi) — ichki {$r['internal']}, tashqi {$r['external']}, "
            . "haftalik ko'chirish {$r['week_moves']}, o'qituvchi/xona {$r['teacher_room']}; manbada topilmadi {$r['no_source']}, manbada ham joylanmagan {$r['source_unplaced']}, "
            . "to'qnashuv sababli yozilmadi {$r['conflicts']}");
        foreach (array_merge($r['examples'], $r['conflict_examples']) as $e) {
            $this->line('  ' . $e);
        }
        if ($r['dry_run']) {
            $this->comment('Yozish uchun --apply qo\'shing.');
        }

        return 0;
    }
}

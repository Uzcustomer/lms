<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Doska davrida har o'quv reja qaysi semestr bilan chiqishini ko'rsatadi (faqat o'qiydi — hech narsa o'zgartirmaydi).
 * Yangi qoida — o'quv reja sanalari (curriculum_weeks) bo'yicha; --compare — eski qoida (education_year + juft/toqlik) bilan
 * solishtirish: farqlar va shu davrda semestri topilmagan guruhlar alohida ro'yxatda.
 *
 * Misol:
 *   php artisan timetable:board-semesters 12 --compare
 *   php artisan timetable:board-semesters 12 --compare --only-diff
 *   php artisan timetable:board-semesters 12 --plan="General medicine"
 */
class TimetableBoardSemesters extends Command
{
    protected $signature = 'timetable:board-semesters
        {board? : Doska id (sukut — eng oxirgisi)}
        {--compare : Eski qoida (juft/toqlik + o\'quv yili) bilan solishtirish}
        {--only-diff : Faqat farq qilgan o\'quv rejalar}
        {--plan= : O\'quv reja nomi bo\'yicha filtr (qismi)}';

    protected $description = 'Doska davridagi semestrlar — o\'quv reja sanalari bo\'yicha (eski qoida bilan solishtirish)';

    public function handle(): int
    {
        $board = $this->argument('board') ? TimetableBoard::find($this->argument('board')) : TimetableBoard::orderByDesc('id')->first();
        if (!$board) {
            $this->error('Doska topilmadi.');

            return self::FAILURE;
        }
        /** @var TimetableController $ctl */
        $ctl = app(TimetableController::class);
        $info = $ctl->boardSemesterInfo($board);
        $legacy = $this->option('compare') ? $ctl->boardSemesterCodesLegacy($board) : [];
        [$from, $to] = $info['period'];
        $this->info("Doska #{$board->id} «{$board->name}» ({$board->academic_year}, {$board->semester_parity}) — davr: {$from} – {$to}");

        $ids = array_values(array_unique(array_merge(array_keys($info['codes']), array_keys($legacy), $info['missing'])));
        $plans = DB::table('curricula as c')->leftJoin('departments as d', 'd.department_hemis_id', '=', 'c.department_hemis_id')
            ->whereIn('c.curricula_hemis_id', $ids)->get(['c.curricula_hemis_id', 'c.name', 'd.name as faculty'])->keyBy('curricula_hemis_id');
        $filter = mb_strtolower(trim((string) $this->option('plan')));
        $rows = [];
        $diff = [];
        foreach ($ids as $id) {
            $p = $plans[$id] ?? null;
            $name = (string) ($p->name ?? '—');
            if ($filter !== '' && !str_contains(mb_strtolower($name), $filter)) {
                continue;
            }
            $d = $info['detail'][$id] ?? null;
            $new = $info['codes'][$id] ?? '—';
            $old = $legacy[$id] ?? '—';
            $changed = $this->option('compare') && (string) $new !== (string) $old;
            if ($this->option('only-diff') && !$changed) {
                continue;
            }
            $row = [$id, mb_substr((string) ($p->faculty ?? '—'), 0, 30), mb_substr($name, 0, 55), $new,
                $d ? ($d['source'] === 'dates' ? $d['from'] . ' – ' . $d['to'] . ' (' . $d['days'] . ' kun)' : 'eski qoida (haftalari yo\'q)') : 'davrga kesishgan semestr yo\'q'];
            if ($this->option('compare')) {
                $row[] = $old;
                $row[] = $changed ? 'FARQ' : '';
            }
            $rows[] = $row;
            if ($changed) {
                $diff[] = $row;
            }
        }
        usort($rows, fn ($a, $b) => [$a[1], $a[2]] <=> [$b[1], $b[2]]);
        $head = ['O\'quv reja id', 'Fakultet', 'O\'quv reja', 'Semestr (yangi)', 'Semestr sanalari'];
        if ($this->option('compare')) {
            array_push($head, 'Semestr (eski)', '');
        }
        $this->table($head, $rows);

        if ($this->option('compare')) {
            $this->newLine();
            $this->warn('Farq qilgan o\'quv rejalar: ' . count($diff));
            if ($diff) {
                $this->table($head, $diff);
            }
        }
        $warnings = $ctl->boardSemesterWarnings($board);
        $this->newLine();
        $this->warn('Shu davrda semestri topilmagan guruhlar (faol talabasi bor): ' . count($warnings));
        foreach ($warnings as $w) {
            $this->line('  - ' . $w);
        }

        return self::SUCCESS;
    }
}

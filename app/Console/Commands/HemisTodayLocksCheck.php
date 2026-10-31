<?php

namespace App\Console\Commands;

use App\Services\HemisSync\HemisDayClient;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Bugungi darslar (HEMIS)" qulfini tekshirish: shu kunda har guruh bo'yicha bazadagi va HEMIS API'dagi davomat (load > 0)
 * va baholar — necha dars (subject_schedule) belgilangan, yonma-yon. Farq bo'lsa — qulf aniqlashni yoki importni tekshirish
 * kerak. Hech narsa yozmaydi.
 */
class HemisTodayLocksCheck extends Command
{
    protected $signature = 'hemis:today-locks-check
        {date : Sana (Y-m-d)}
        {--group= : HEMIS guruh id yoki nomi (vergul bilan bir nechta); berilmasa — shu kunda jadvali bor barcha guruhlar}';

    protected $description = "Bugungi darslar qulfi: guruh bo'yicha bazadagi va HEMIS API'dagi davomat (load > 0) va baholar soni";

    public function handle(HemisDayClient $client): int
    {
        $date = (string) $this->argument('date');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->error('Sana Y-m-d ko\'rinishida bo\'lsin');

            return self::FAILURE;
        }
        $day = [Carbon::parse($date)->startOfDay(), Carbon::parse($date)->endOfDay()];
        $q = DB::table('schedules')->whereNull('deleted_at')->whereBetween('lesson_date', $day);
        if ($only = array_filter(array_map('trim', explode(',', (string) $this->option('group'))))) {
            $ids = array_map('intval', array_filter($only, 'ctype_digit'));
            $names = array_values(array_diff($only, array_filter($only, 'ctype_digit')));
            $q->where(fn ($w) => $w->whereIn('group_id', $ids)->orWhereIn('group_name', $names));
        }
        $lessons = $q->get(['schedule_hemis_id', 'group_id', 'group_name'])->groupBy('group_id');
        if ($lessons->isEmpty()) {
            $this->warn("{$date}: bazada shu kun jadvali yo'q (guruh filtri bilan).");

            return self::SUCCESS;
        }
        $gids = $lessons->keys()->map(fn ($g) => (int) $g)->all();
        $ids = $lessons->flatten(1)->pluck('schedule_hemis_id')->map(fn ($i) => (int) $i)->all();
        $notDeleted = fn ($t) => fn ($q) => $q->when(Schema::hasColumn($t, 'deleted_at'), fn ($x) => $x->whereNull('deleted_at'));
        $dbAtt = DB::table('attendance_controls')->whereIn('subject_schedule_id', $ids)->where('load', '>', 0)->tap($notDeleted('attendance_controls'))
            ->distinct()->pluck('subject_schedule_id')->map(fn ($i) => (int) $i)->flip()->all();
        $dbGrade = DB::table('student_grades')->whereIn('subject_schedule_id', $ids)->tap($notDeleted('student_grades'))
            ->distinct()->pluck('subject_schedule_id')->map(fn ($i) => (int) $i)->flip()->all();
        $apiAtt = $client->dayList('attendance-control-list', $gids, $date);
        $apiGrade = $client->dayList('student-grade-list', $gids, $date);
        $lessonsOf = fn (?array $items, bool $att) => $items === null ? null : array_flip(array_values(array_unique(array_map(
            fn ($it) => (int) ($it['_subject_schedule'] ?? ($it['subject_schedule']['id'] ?? 0)),
            array_filter($items, fn ($it) => !$att || (int) ($it['load'] ?? 2) > 0)))));

        $rows = [];
        $diffs = 0;
        foreach ($lessons as $g => $list) {
            $mine = $list->pluck('schedule_hemis_id')->map(fn ($i) => (int) $i)->flip()->all();
            $count = fn ($set) => $set === null ? 'xato' : count(array_intersect_key($set, $mine));
            $att = [$count($dbAtt), $count($lessonsOf($apiAtt[(int) $g] ?? null, true))];
            $grade = [$count($dbGrade), $count($lessonsOf($apiGrade[(int) $g] ?? null, false))];
            $diff = $att[0] !== $att[1] || $grade[0] !== $grade[1];
            $diffs += (int) $diff;
            $rows[] = [$g, $list->first()->group_name, count($mine), $att[0], $att[1], $grade[0], $grade[1], $diff ? '≠' : ''];
        }
        usort($rows, fn ($a, $b) => strnatcasecmp((string) $a[1], (string) $b[1]));
        $this->table(['HEMIS id', 'Guruh', 'Darslar', 'Davomat: baza', 'Davomat: API', 'Baho: baza', 'Baho: API', 'Farq'], $rows);
        $this->line("{$date}: " . count($rows) . " ta guruh, farqi bor: {$diffs} ta (son — davomati / bahosi bor darslar; davomat — load > 0).");

        return self::SUCCESS;
    }
}

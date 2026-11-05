<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fan chegara sanada (sukut bo'yicha 26.10.2026) yoki undan keyin tugagan bo'lsa — fan tugashi:
 * oxirgi dars, OSKI va Test sanalarining eng kechi — shu fanning chegara sanadan oldin:
 *   - otrabotka qilingan NB (sababli ham, sababsiz ham) — otrabotka bahosi;
 *   - topshirilgan OSKI — OSKI bahosi (Test bahosiga tegilmaydi)
 * grades:admin-upgrade formulasi bo'yicha oshiriladi (60 dan pasti — yo'q). Chegara sanagacha
 * butunlay tugagan fanlarga tegilmaydi. Guruhlar grades:upgrade-cycle dagidek tanlanadi.
 * Sukut bo'yicha faqat hisobot, yozish — --force; --revert — shu baholarni qaytaradi;
 * --revert-finished — tugagan fanlarda (avvalgi versiya) oshirib yuborilgan baholarni qaytaradi.
 */
class UpgradeNbOskiGrades extends UpgradeCycleGrades
{
    protected $signature = 'grades:upgrade-nb-oski
        {groups?* : Guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--faculty=* : Fakultet nomidan qism, masalan "1-son davolash" (bir nechta bo\'lishi mumkin)}
        {--specialty=* : --faculty ichida faqat shu yo\'nalishlar (nomidan qism)}
        {--courses=4,5,6 : --faculty da qaysi kurslar}
        {--cutoff=2026-10-26 : Shu sanadan oldin otrabotka qilingan NB va topshirilgan OSKI oshiriladi}
        {--since=2026-09-01 : O\'quv yili boshi — undan oldingi darslar/imtihonlarga tegilmaydi}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : Oshirilgan baholarni eski holiga qaytarish}
        {--revert-finished : Chegara sanagacha tugagan fanlarda oshirib yuborilgan NB/OSKI baholarini qaytarish}';

    protected $description = "Chegara sanadan oldin otrabotka qilingan NB va topshirilgan OSKI baholarini oshirish (admin)";

    private const OSKI = 101;

    public function handle(): int
    {
        if (!Schema::hasColumn('student_grades', 'upgraded_by_admin')) {
            $this->error("Avval migratsiya: php artisan migrate");

            return self::FAILURE;
        }

        $cutoff = Carbon::parse($this->option('cutoff'))->startOfDay();
        $since = Carbon::parse($this->option('since'))->startOfDay();
        $force = (bool) $this->option('force');
        $revert = (bool) $this->option('revert');
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlineDays = Deadline::pluck('deadline_days', 'level_code');
        $excluded = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $hasAttempt = Schema::hasColumn('student_grades', 'attempt');
        $cut = $cutoff->format('d.m');

        $table = [];
        $samples = [];
        $total = 0;
        $revertFinished = (bool) $this->option('revert-finished');
        $wrong = 0;
        $groups = $this->selectedGroups($table, 10);
        if ($groups === null) {
            return self::FAILURE;
        }
        $brief = (bool) array_filter($this->option('faculty'));
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));
        $columns = ['id', 'student_hemis_id', 'subject_id', 'subject_name', 'lesson_date', 'retake_graded_at', 'grade', 'retake_grade',
            'reason', 'status', 'upgraded_by_admin', 'admin_original_grade'];

        $from = $this->gradesTable();
        $this->output->progressStart($groups->count());
        foreach ($groups as $group) {
            $this->output->progressAdvance();
            $students = DB::table('students')->where('group_id', $group->group_hemis_id)->pluck('level_code', 'hemis_id');
            if ($students->isEmpty()) {
                continue;
            }
            $rows = DB::table($from)
                ->whereIn('student_hemis_id', $students->keys()->map(fn ($id) => (string) $id)->all())
                ->whereNull('deleted_at')
                ->where('lesson_date', '>=', $since)
                ->where(fn ($q) => $q
                    // Kunlik darsdagi NB (sababli/sababsiz), otrabotkasi chegara sanadan oldin qo'yilgan
                    ->where(fn ($q) => $q->whereNotIn('training_type_code', $excluded)
                        ->where('reason', 'absent')
                        ->whereNull('grade')
                        ->whereNotNull('retake_grade')
                        ->where('retake_graded_at', '<', $cutoff))
                    // Chegara sanadan oldin topshirilgan OSKI (barcha urinishlar)
                    ->orWhere(fn ($q) => $q->where('training_type_code', self::OSKI)
                        ->where('lesson_date', '<', $cutoff)
                        ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('retake_grade'))))
                ->get(array_merge($columns, ['training_type_code'], $hasAttempt ? ['attempt'] : []));
            [$oski, $nb] = $rows->partition(fn ($r) => (int) $r->training_type_code === self::OSKI);
            $ends = $this->subjectEnds($group->group_hemis_id, $since);

            foreach (['NB otrabotka' => $nb, 'OSKI' => $oski] as $kind => $kindRows) {
                foreach ($kindRows->groupBy('subject_id') as $subjectId => $rows) {
                    $end = $ends[(string) $subjectId] ?? null;
                    $subject = $rows->first()->subject_name;
                    $dates = $rows->map(fn ($r) => Carbon::parse($kind === 'OSKI' ? $r->lesson_date : $r->retake_graded_at)->format('d.m'))
                        ->unique()->sort()->values();
                    $when = $dates->count() > 3 ? $dates->first() . ' – ' . $dates->last() : $dates->implode(', ');
                    $attempts = $kind === 'OSKI' && $hasAttempt ? $rows->pluck('attempt')->filter()->unique()->sort()->implode(',') : '';
                    $row = [$group->name, $courseOf[$group->group_hemis_id] ?? '', $subject, $end['label'] ?? '?',
                        $kind . ($attempts ? " ({$attempts}-urinish)" : ''), $when, $rows->count()];

                    // Fan chegara sanagacha butunlay tugagan — tegilmaydi; avvalgi versiya oshirib
                    // yuborgan bo'lsa — ko'rsatiladi va --revert-finished bilan qaytariladi
                    if ($end === null || $end['end']->lt($cutoff)) {
                        $upgraded = $rows->filter(fn ($r) => $r->upgraded_by_admin);
                        if ($upgraded->isEmpty()) {
                            continue;
                        }
                        $wrong += $upgraded->count();
                        $none = [];
                        $r = $revertFinished
                            ? AdminUpgradeGrades::applyRows($upgraded, $students, $limit, $deadlineDays, true, $force, $group->name, $none)
                            : ['changed' => 0];
                        $table[] = array_merge($row, [$r['changed'], '', "FAN TUGAGAN — noto'g'ri oshirilgan: {$upgraded->count()}"
                            . ($revertFinished ? ($force ? ', qaytarildi' : ', qaytariladi') : '')]);
                        continue;
                    }

                    $sampleCount = count($samples);
                    $r = AdminUpgradeGrades::applyRows($rows, $students, $limit, $deadlineDays, $revert, $force, $group->name, $samples);
                    array_splice($samples, $brief ? 0 : $sampleCount + 3);
                    $total += $r['changed'];
                    $table[] = array_merge($row, [$r['changed'], $r['below'], $r['same'] ? "allaqachon: {$r['same']}" : '']);
                }
            }
        }

        $this->output->progressFinish();

        $this->table(['Guruh', 'Kurs', 'Fan', 'Fan tugashi (eng kechi)', 'Tur', "Sana ({$cut} gacha)", 'Baholar',
            $revert ? 'Qaytariladi' : 'Oshiriladi', '<60', 'Izoh'], $table);
        if ($wrong) {
            $this->warn("Tugagan fanlarda noto'g'ri oshirilgan baholar: {$wrong} ta."
                . ($revertFinished ? '' : ' Qaytarish uchun: --revert-finished --force'));
        }
        if ($samples) {
            $this->line('Namunalar:');
            $this->table(['Guruh', 'Talaba', 'Dars/imtihon sanasi', $revert ? 'Hozir' : 'Eski', $revert ? 'Qaytadi' : 'Yangi'], $samples);
        }

        if ($force) {
            $this->info('Jami ' . ($revert ? 'qaytarildi' : 'oshirildi') . ": {$total} ta baho.");
        } else {
            $this->info('[hisobot] Jami ' . ($revert ? 'qaytariladi' : 'oshiriladi') . ": {$total} ta baho.");
            $this->comment('Bu faqat hisobot. Yozish uchun --force qo\'shing.');
        }

        return self::SUCCESS;
    }

    /**
     * Guruh fanlarining tugash sanasi: oxirgi dars (schedules), OSKI va Test (exam_schedules,
     * guruh sathidagi qatorlar) sanalarining eng kechi.
     *
     * @return array<string, array{end: Carbon, label: string}> subject_id => ...
     */
    private function subjectEnds($groupHemisId, Carbon $since): array
    {
        $dates = [];
        $add = function ($subjectId, string $kind, $date) use (&$dates, $since) {
            if ($date === null || Carbon::parse($date)->lt($since)) {
                return;
            }
            $d = Carbon::parse($date)->startOfDay();
            $prev = $dates[(string) $subjectId][$kind] ?? null;
            if ($prev === null || $d->gt($prev)) {
                $dates[(string) $subjectId][$kind] = $d;
            }
        };

        DB::table('schedules')->whereNull('deleted_at')->where('group_id', $groupHemisId)
            ->where('lesson_date', '>=', $since)
            ->groupBy('subject_id')
            ->get(['subject_id', DB::raw('MAX(lesson_date) AS last_lesson')])
            ->each(fn ($r) => $add($r->subject_id, 'dars', $r->last_lesson));

        if (Schema::hasTable('exam_schedules')) {
            DB::table('exam_schedules')->where('group_hemis_id', (string) $groupHemisId)
                ->when(Schema::hasColumn('exam_schedules', 'student_hemis_id'), fn ($q) => $q->whereNull('student_hemis_id'))
                ->get(['subject_id', 'oski_date', 'test_date'])
                ->each(function ($r) use ($add) {
                    $add($r->subject_id, 'OSKI', $r->oski_date);
                    $add($r->subject_id, 'Test', $r->test_date);
                });
        }

        $ends = [];
        foreach ($dates as $subjectId => $kinds) {
            $ends[$subjectId] = [
                'end' => collect($kinds)->max(),
                'label' => collect($kinds)->map(fn ($d, $k) => "{$k} " . $d->format('d.m'))->implode(', '),
            ];
        }

        return $ends;
    }

    /**
     * Fan sharti yo'q so'rovda MySQL lesson_date/deleted_at indeksini tanlab, katta jadvalni
     * har guruh uchun aylanib chiqadi — talaba bo'yicha indeks majburan ishlatiladi.
     */
    private function gradesTable()
    {
        $index = 'idx_sg_student_subject_semester';
        if (DB::getDriverName() === 'mysql' && DB::select('SHOW INDEX FROM student_grades WHERE Key_name = ?', [$index])) {
            return DB::raw("student_grades FORCE INDEX ({$index})");
        }

        return 'student_grades';
    }
}

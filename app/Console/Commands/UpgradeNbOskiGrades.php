<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chegara sanadan (sukut bo'yicha 26.10.2026) oldin:
 *   - otrabotka qilingan NB (sababli ham, sababsiz ham) — otrabotka bahosi;
 *   - topshirilgan OSKI — OSKI bahosi (Test bahosiga tegilmaydi, Test sanasi qanday bo'lmasin)
 * grades:admin-upgrade formulasi bo'yicha oshiriladi (60 dan pasti — yo'q). Guruhlar
 * grades:upgrade-cycle dagidek tanlanadi (guruh nomi yoki --faculty/--specialty/--courses).
 * Sukut bo'yicha faqat hisobot, yozish — --force; --revert — shu baholarni qaytaradi.
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
        {--revert : Oshirilgan baholarni eski holiga qaytarish}';

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
        $groups = $this->selectedGroups($table, 9);
        if ($groups === null) {
            return self::FAILURE;
        }
        $brief = (bool) array_filter($this->option('faculty'));
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));
        $columns = ['id', 'student_hemis_id', 'subject_name', 'lesson_date', 'retake_graded_at', 'grade', 'retake_grade', 'reason',
            'status', 'upgraded_by_admin', 'admin_original_grade'];

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

            foreach (['NB otrabotka' => $nb, 'OSKI' => $oski] as $kind => $kindRows) {
                foreach ($kindRows->groupBy('subject_name') as $subject => $rows) {
                    $dates = $rows->map(fn ($r) => Carbon::parse($kind === 'OSKI' ? $r->lesson_date : $r->retake_graded_at)->format('d.m'))
                        ->unique()->sort()->values();
                    $when = $dates->count() > 3 ? $dates->first() . ' – ' . $dates->last() : $dates->implode(', ');
                    $attempts = $kind === 'OSKI' && $hasAttempt ? $rows->pluck('attempt')->filter()->unique()->sort()->implode(',') : '';

                    $sampleCount = count($samples);
                    $r = AdminUpgradeGrades::applyRows($rows, $students, $limit, $deadlineDays, $revert, $force, $group->name, $samples);
                    array_splice($samples, $brief ? 0 : $sampleCount + 3);
                    $total += $r['changed'];
                    $table[] = [$group->name, $courseOf[$group->group_hemis_id] ?? '', $subject, $kind . ($attempts ? " ({$attempts}-urinish)" : ''),
                        $when, $rows->count(), $r['changed'], $r['below'], $r['same'] ? "allaqachon: {$r['same']}" : ''];
                }
            }
        }

        $this->output->progressFinish();

        $this->table(['Guruh', 'Kurs', 'Fan', 'Tur', "Sana ({$cut} gacha)", 'Baholar', $revert ? 'Qaytariladi' : 'Oshiriladi', '<60', 'Izoh'], $table);
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

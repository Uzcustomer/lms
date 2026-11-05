<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sikl bilan o'tiladigan fanlar: sikl chegara sanadan (sukut bo'yicha 26.10.2026) oldin boshlanib,
 * shu sana yoki undan keyin tugagan bo'lsa — siklning chegara sanadan OLDINGI kunlik baholari
 * grades:admin-upgrade formulasi bo'yicha oshiriladi (NB ustidagi otrabotka ham, 60 dan pasti — yo'q).
 * Sikl chegaralari — guruh dars jadvalidagi (schedules) fanning birinchi va oxirgi dars kuni.
 *
 * Guruh nomi berilsa, faqat uning kichik guruhlari (a, b, c) olinadi. Sukut bo'yicha faqat hisobot,
 * yozish — --force; --revert — shu oraliqda oshirilgan baholarni qaytaradi.
 */
class UpgradeCycleGrades extends Command
{
    protected $signature = 'grades:upgrade-cycle
        {groups* : Guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--cutoff=2026-10-26 : Shu sanadan oldingi baholar oshiriladi}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : Oshirilgan baholarni eski holiga qaytarish}';

    protected $description = "Chegara sanani kesib o'tgan sikl fanlarining undan oldingi kunlik baholarini oshirish (admin)";

    public function handle(): int
    {
        if (!Schema::hasColumn('student_grades', 'upgraded_by_admin')) {
            $this->error("Avval migratsiya: php artisan migrate");

            return self::FAILURE;
        }

        $cutoff = Carbon::parse($this->option('cutoff'))->startOfDay();
        $force = (bool) $this->option('force');
        $revert = (bool) $this->option('revert');
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlineDays = Deadline::pluck('deadline_days', 'level_code');
        $excluded = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
        $cut = $cutoff->format('d.m');

        $table = [];
        $samples = [];
        $total = 0;
        foreach ($this->argument('groups') as $groupName) {
            $groups = AdminUpgradeGrades::subgroups($groupName);
            if ($groups->isEmpty()) {
                $table[] = [$groupName, '', '', 'GURUH TOPILMADI', '', '', '', ''];
                continue;
            }

            foreach ($groups as $group) {
                $students = DB::table('students')->where('group_id', $group->group_hemis_id)->pluck('level_code', 'hemis_id');
                // Fan sikllari: birinchi va oxirgi dars kuni (o'tgan yillar/semestrlar — chegara sanadan 2 oy oldin tugaganlar — ko'rsatilmaydi)
                $cycles = DB::table('schedules')
                    ->whereNull('deleted_at')
                    ->where('group_id', $group->group_hemis_id)
                    ->groupBy('subject_id', 'subject_name', 'semester_code')
                    ->havingRaw('MAX(lesson_date) >= ?', [$cutoff->copy()->subMonths(2)])
                    ->orderByRaw('MIN(lesson_date)')
                    ->get([DB::raw('subject_id, subject_name, semester_code, MIN(lesson_date) AS cycle_start, MAX(lesson_date) AS cycle_end')]);

                foreach ($cycles as $c) {
                    $start = Carbon::parse($c->cycle_start)->startOfDay();
                    $end = Carbon::parse($c->cycle_end)->startOfDay();
                    $period = $start->format('d.m') . ' – ' . $end->format('d.m.Y');
                    if ($end->lt($cutoff)) {
                        $table[] = [$group->name, $c->subject_name, $period, "{$cut} gacha tugagan", '', '', '', ''];
                        continue;
                    }
                    if ($start->gte($cutoff)) {
                        $table[] = [$group->name, $c->subject_name, $period, "{$cut} dan keyin boshlangan", '', '', '', ''];
                        continue;
                    }

                    $rows = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->whereIn('student_hemis_id', $students->keys()->map(fn ($id) => (string) $id)->all() ?: ['0'])
                        ->where('subject_id', (string) $c->subject_id)
                        ->where('semester_code', (string) $c->semester_code)
                        ->where('lesson_date', '>=', $start)
                        ->where('lesson_date', '<', $cutoff)
                        ->whereNotIn('training_type_code', $excluded)
                        ->where(fn ($q) => $q->whereNotNull('grade')->orWhereNotNull('retake_grade'))
                        ->get(['id', 'student_hemis_id', 'lesson_date', 'grade', 'retake_grade', 'reason', 'status',
                            'upgraded_by_admin', 'admin_original_grade']);

                    $r = AdminUpgradeGrades::applyRows($rows, $students, $limit, $deadlineDays, $revert, $force, $group->name, $samples);
                    $total += $r['changed'];
                    $table[] = [$group->name, $c->subject_name, $period, $rows->count(), $r['changed'], $r['below'], $r['retake'],
                        $r['same'] ? "allaqachon: {$r['same']}" : ''];
                }
            }
        }

        $this->table(['Guruh', 'Fan', 'Sikl', "Baholar ({$cut} gacha)", $revert ? 'Qaytariladi' : 'Oshiriladi', '<60', 'Shundan otrabotka', 'Izoh'], $table);
        if ($samples) {
            $this->line('Namunalar:');
            $this->table(['Guruh', 'Talaba', 'Sana', $revert ? 'Hozir' : 'Eski', $revert ? 'Qaytadi' : 'Yangi'], array_slice($samples, 0, 20));
        }

        if ($force) {
            $this->info('Jami ' . ($revert ? 'qaytarildi' : 'oshirildi') . ": {$total} ta baho.");
        } else {
            $this->info('[hisobot] Jami ' . ($revert ? 'qaytariladi' : 'oshiriladi') . ": {$total} ta baho.");
            $this->comment('Bu faqat hisobot. Yozish uchun --force qo\'shing.');
        }

        return self::SUCCESS;
    }
}

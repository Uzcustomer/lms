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
 * Guruh nomi berilsa, faqat uning kichik guruhlari (a, b, c) olinadi; --faculty berilsa — shu fakultetning
 * --courses kurslaridagi (sukut: 4, 5, 6) barcha guruhlari. Sukut bo'yicha faqat hisobot,
 * yozish — --force; --revert — shu oraliqda oshirilgan baholarni qaytaradi.
 */
class UpgradeCycleGrades extends Command
{
    protected $signature = 'grades:upgrade-cycle
        {groups?* : Guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--faculty=* : Fakultet nomidan qism, masalan "1-son davolash" (bir nechta bo\'lishi mumkin)}
        {--specialty=* : --faculty ichida faqat shu yo\'nalishlar (nomidan qism), masalan "Stomatologiya"}
        {--courses=4,5,6 : --faculty da qaysi kurslar}
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
        $groups = $this->selectedGroups($table, 9);
        if ($groups === null) {
            return self::FAILURE;
        }
        // Fakultet bo'yicha jadval katta — u yerda faqat oshiriladigan sikllar va namunasiz
        $brief = (bool) array_filter($this->option('faculty'));
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));

        foreach ($groups as $group) {
            $course = $courseOf[$group->group_hemis_id] ?? '';
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
                if ($end->lt($cutoff) || $start->gte($cutoff)) {
                    if (!$brief) {
                        $table[] = [$group->name, $course, $c->subject_name, $period,
                            $end->lt($cutoff) ? "{$cut} gacha tugagan" : "{$cut} dan keyin boshlangan", '', '', '', ''];
                    }
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

                $sampleCount = count($samples);
                $r = AdminUpgradeGrades::applyRows($rows, $students, $limit, $deadlineDays, $revert, $force, $group->name, $samples);
                // Namunalar har guruh/fandan 4 tadan — hammasi ko'rinsin
                array_splice($samples, $brief ? 0 : $sampleCount + 4);
                $total += $r['changed'];
                $table[] = [$group->name, $course, $c->subject_name, $period, $rows->count(), $r['changed'], $r['below'], $r['retake'],
                    $r['same'] ? "allaqachon: {$r['same']}" : ''];
            }
        }

        $this->table(['Guruh', 'Kurs', 'Fan', 'Sikl', "Baholar ({$cut} gacha)", $revert ? 'Qaytariladi' : 'Oshiriladi', '<60', 'Shundan otrabotka', 'Izoh'], $table);
        if ($samples) {
            $this->line('Namunalar:');
            $this->table(['Guruh', 'Talaba', 'Sana', $revert ? 'Hozir' : 'Eski', $revert ? 'Qaytadi' : 'Yangi'], $samples);
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
     * Argumentdagi guruhlarning kichik guruhlari + --faculty guruhlari. Topilmagan guruh nomlari
     * $table ga "GURUH TOPILMADI" qatori ($cols ustunli) bo'lib qo'shiladi; xato bo'lsa — null.
     */
    protected function selectedGroups(array &$table, int $cols)
    {
        $faculties = array_filter($this->option('faculty'));
        if (!$faculties && !$this->argument('groups')) {
            $this->error('Guruh yoki --faculty bering. Masalan: ' . $this->getName() . ' --faculty="1-son davolash"');

            return null;
        }

        $selected = collect();
        foreach ($this->argument('groups') as $groupName) {
            $groups = AdminUpgradeGrades::subgroups($groupName);
            if ($groups->isEmpty()) {
                $table[] = array_pad([$groupName, '', '', '', 'GURUH TOPILMADI'], $cols, '');
                continue;
            }
            $selected = $selected->merge($groups);
        }
        if ($faculties) {
            $groups = $this->facultyGroups($faculties);
            if ($groups === null) {
                return null;
            }
            $selected = $selected->merge($groups);
        }

        return $selected->unique('group_hemis_id')->values();
    }

    /**
     * Fakultet(lar)ning (--specialty berilsa — faqat shu yo'nalishlar) --courses kurslaridagi guruhlari;
     * topilmasa — mavjud fakultet/yo'nalishlar ro'yxati va null.
     */
    protected function facultyGroups(array $faculties)
    {
        $likeAny = function ($q, string $column, array $values) {
            $q->where(function ($q) use ($column, $values) {
                foreach ($values as $v) {
                    $q->orWhere($column, 'like', '%' . $v . '%');
                }
            });
        };
        $groups = DB::table('groups')
            ->tap(fn ($q) => $likeAny($q, 'department_name', $faculties))
            ->get(['group_hemis_id', 'name', 'department_name', 'specialty_name']);
        if ($groups->isEmpty()) {
            $this->error('Fakultet topilmadi: ' . implode(', ', $faculties) . '. Mavjudlari:');
            DB::table('groups')->distinct()->orderBy('department_name')->pluck('department_name')->each(fn ($n) => $this->line("  {$n}"));

            return null;
        }

        $specialties = array_filter($this->option('specialty'));
        if ($specialties) {
            $all = $groups;
            $groups = $groups->filter(fn ($g) => collect($specialties)->contains(fn ($s) => mb_stripos((string) $g->specialty_name, $s) !== false))->values();
            if ($groups->isEmpty()) {
                $this->error("Yo'nalish topilmadi: " . implode(', ', $specialties) . '. Bu fakultetdagilari:');
                $all->pluck('specialty_name')->unique()->sort()->each(fn ($n) => $this->line("  {$n}"));

                return null;
            }
        }

        // Kurs: level_code 11 = 1-kurs … 16 = 6-kurs
        $levels = collect(explode(',', (string) $this->option('courses')))->map(fn ($c) => (string) (10 + (int) trim($c)))->all();
        $courseOf = $this->courses($groups->pluck('group_hemis_id'));
        $levelOf = array_map(fn ($c) => (string) (10 + $c), $courseOf);
        $selected = $groups->filter(fn ($g) => in_array($levelOf[$g->group_hemis_id] ?? null, $levels, true))->sortBy('name')->values();

        $this->info('Fakultet: ' . $groups->pluck('department_name')->unique()->implode(', ')
            . " — {$selected->count()} ta guruh (" . $this->option('courses') . '-kurslar)');
        $this->info("Yo'nalishlar: " . $selected->pluck('specialty_name')->unique()->sort()->implode(', '));

        return $selected;
    }

    /** group_hemis_id => kurs (guruh talabalarining eng ko'p level_code i bo'yicha: 15 → 5). */
    protected function courses($groupHemisIds): array
    {
        return DB::table('students')
            ->whereIn('group_id', collect($groupHemisIds)->all() ?: [0])
            ->whereNotNull('level_code')
            ->groupBy('group_id', 'level_code')
            ->get(['group_id', 'level_code', DB::raw('COUNT(*) AS n')])
            ->sortByDesc('n')
            ->unique('group_id')
            ->mapWithKeys(fn ($r) => [$r->group_id => (int) $r->level_code - 10])
            ->all();
    }
}

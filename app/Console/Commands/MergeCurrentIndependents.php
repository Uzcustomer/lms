<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Joriy semestrda bitta fanda qolgan bir nechta avtomatik MT topshirig'ini bittaga birlashtirish.
 *
 * independents:dedupe talaba fayli/bahosi bor dublikatlarga tegmagan — talabaga bitta fandan
 * bir nechta topshiriq ko'rinib qolgan. Bu buyruq ularning bog'lanishlarini (fayl, baho,
 * baho tarixi) bitta topshiriqqa ko'chiradi va bo'shab qolganini o'chiradi.
 *
 * Xavfsizlik:
 *  - faqat joriy semestr (guruhning faol talabalari semestri) va avtomatik yozuvlar (user_id = 0);
 *  - talabaning ikki topshiriqda alohida fayli bo'lsa — o'sha fayl ko'chirilmaydi, topshiriq
 *    qoladi va hisobotga chiqadi (qaysi fayl kerakligini odam hal qiladi);
 *  - sukut bo'yicha faqat hisobot; o'zgartirish --force bilan.
 */
class MergeCurrentIndependents extends Command
{
    protected $signature = 'independents:merge-current {--force : Haqiqatan birlashtirish (bo\'lmasa faqat hisobot)}';

    protected $description = "Mustaqil ta'lim: joriy semestrda bitta fandagi bir nechta topshiriqni bittaga birlashtirish";

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $current = DB::table('students')
            ->where('student_status_code', '11')
            ->whereNotNull('group_id')
            ->whereNotNull('semester_code')
            ->distinct()
            ->get(['group_id', 'semester_code'])
            ->map(fn ($r) => $r->group_id . '|' . $r->semester_code)
            ->flip();

        $combos = DB::table('independents')
            ->where('user_id', 0)
            ->select('group_hemis_id', 'subject_hemis_id', 'semester_code')
            ->groupBy('group_hemis_id', 'subject_hemis_id', 'semester_code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->filter(fn ($c) => $current->has($c->group_hemis_id . '|' . $c->semester_code))
            ->values();

        $this->info(($force ? '' : '[hisobot] ') . "Joriy semestrda bir nechta topshirig'i bor fan: {$combos->count()}");

        $stats = ['merged' => 0, 'deleted' => 0, 'subs' => 0, 'grades' => 0, 'history' => 0];
        $conflicts = [];

        foreach ($combos as $combo) {
            $rows = DB::table('independents')
                ->where('user_id', 0)
                ->where('group_hemis_id', $combo->group_hemis_id)
                ->where('subject_hemis_id', $combo->subject_hemis_id)
                ->where('semester_code', $combo->semester_code)
                ->orderBy('id')
                ->get(['id', 'group_name', 'subject_name', 'teacher_name']);
            $ids = $rows->pluck('id')->all();

            $subs = DB::table('independent_submissions')->whereIn('independent_id', $ids)->get(['id', 'independent_id', 'student_id']);
            $gradeCounts = DB::table('student_grades')->whereIn('independent_id', $ids)
                ->selectRaw('independent_id, COUNT(*) n')->groupBy('independent_id')->pluck('n', 'independent_id');

            // Qoladigani: fayli ko'p, keyin bahosi ko'p, o'qituvchisi yozilgan, eng eskisi
            $keeper = $rows->sortBy(fn ($r) => [
                -$subs->where('independent_id', $r->id)->count(),
                -(int) ($gradeCounts[$r->id] ?? 0),
                empty($r->teacher_name) ? 1 : 0,
                $r->id,
            ])->first();

            $keeperStudents = $subs->where('independent_id', $keeper->id)->pluck('student_id')->flip();
            $moveSubs = [];
            $blocked = [];
            foreach ($subs->where('independent_id', '!=', $keeper->id) as $sub) {
                if ($keeperStudents->has($sub->student_id)) {
                    $blocked[$sub->independent_id] = true;      // talabaning ikkinchi fayli — tegilmaydi
                    continue;
                }
                $moveSubs[] = $sub->id;
                $keeperStudents[$sub->student_id] = true;
            }
            $others = array_values(array_diff($ids, [$keeper->id]));
            $drop = array_values(array_diff($others, array_keys($blocked)));

            if ($blocked) {
                $conflicts[] = $rows->first()->group_name . ' — ' . $rows->first()->subject_name
                    . ' (' . count($blocked) . " ta topshiriqda talabaning ikkinchi fayli bor)";
            }

            $stats['merged']++;
            $stats['subs'] += count($moveSubs);
            $stats['grades'] += (int) collect($others)->sum(fn ($id) => $gradeCounts[$id] ?? 0);
            $stats['history'] += DB::table('independent_grade_history')->whereIn('independent_id', $others)->count();
            $stats['deleted'] += count($drop);

            if (!$force) {
                continue;
            }

            DB::transaction(function () use ($keeper, $others, $moveSubs, $drop) {
                if ($moveSubs) {
                    DB::table('independent_submissions')->whereIn('id', $moveSubs)->update(['independent_id' => $keeper->id]);
                }
                DB::table('independent_grade_history')->whereIn('independent_id', $others)->update(['independent_id' => $keeper->id]);
                DB::table('student_grades')->whereIn('independent_id', $others)->update(['independent_id' => $keeper->id]);
                if ($drop) {
                    DB::table('independents')->whereIn('id', $drop)->delete();
                }
            });
        }

        $this->info(($force ? "Birlashtirildi" : "Birlashtiriladi") . ": {$stats['merged']} ta fan");
        $this->info("  ko'chiriladigan: fayl {$stats['subs']}, baho {$stats['grades']}, baho tarixi {$stats['history']}");
        $this->info(($force ? "  o'chirildi" : "  o'chiriladi") . ": {$stats['deleted']} ta bo'sh qolgan topshiriq");

        if ($conflicts) {
            $this->warn(count($conflicts) . " ta fanda talaba ikki topshiriqqa alohida fayl yuklagan — o'sha topshiriqlar qoldi:");
            foreach (array_slice($conflicts, 0, 30) as $line) {
                $this->line('  ' . $line);
            }
        }
        if (!$force) {
            $this->comment("Bu faqat hisobot. Birlashtirish uchun: php artisan independents:merge-current --force");
        }

        return self::SUCCESS;
    }
}

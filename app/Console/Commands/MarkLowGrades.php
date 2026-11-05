<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Minimal chegara oshirilgandan keyin (masalan 60 → 70) joriy semestrdagi chegaradan past,
 * lekin past baho deb belgilanmagan kunlik baholarni belgilaydi — import yangi baholarga
 * qanday qo'ysa, shunday: reason = low_grade, status = pending, deadline = dars + kurs kunlari.
 * Shundan keyin ular otrabotka sahifasida, jurnalda va bron qilishda qarz bo'lib ko'rinadi.
 *
 * Faqat: faol talabalar, joriy semestr, ma'ruza/MT/nazoratdan tashqari, otrabotkasi
 * qo'yilmagan, muddati hali o'tmagan darslar. 6-kurs (5 ballik) — tegilmaydi.
 * Sukut bo'yicha faqat hisobot; o'zgartirish --force bilan.
 */
class MarkLowGrades extends Command
{
    protected $signature = 'grades:mark-low {--force : Haqiqatan belgilash (bo\'lmasa faqat hisobot)}';

    protected $description = "Joriy semestrda minimal chegaradan past baholarni past baho (otrabotka) deb belgilash";

    public function handle(): int
    {
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlines = Deadline::all()->keyBy('level_code');
        $today = Carbon::today();

        $rows = DB::table('student_grades as g')
            ->join('students as s', 's.hemis_id', '=', 'g.student_hemis_id')
            ->where('s.student_status_code', '11')
            ->where('s.level_code', '!=', '16')
            ->whereColumn('g.semester_code', 's.semester_code')
            ->whereNull('g.deleted_at')
            ->whereNotNull('g.grade')
            ->where('g.grade', '<', $limit)
            ->whereNull('g.reason')
            ->where('g.status', 'recorded')
            ->whereNull('g.retake_grade')
            ->whereNotIn('g.training_type_code', config('app.training_type_code', [11, 99, 100, 101, 102, 103]))
            ->get(['g.id', 'g.lesson_date', 'g.grade', 's.level_code']);

        $mark = [];
        $expired = 0;
        foreach ($rows as $r) {
            $days = $deadlines->get($r->level_code)?->deadline_days;
            $deadline = $days !== null
                ? Carbon::parse($r->lesson_date)->addDays($days)->endOfDay()
                : Carbon::parse($r->lesson_date)->addWeek()->endOfDay();
            if ($deadline->lt($today)) {
                $expired++;   // muddati o'tgan — qayta topshirib bo'lmaydi, tegilmaydi
                continue;
            }
            $mark[$deadline->toDateTimeString()][] = $r->id;
        }

        $count = collect($mark)->sum(fn ($ids) => count($ids));
        $this->info(($this->option('force') ? '' : '[hisobot] ') . "Chegara: {$limit}");
        $this->info("Chegaradan past, belgilanmagan baholar: {$rows->count()}");
        $this->info("  muddati o'tgan (tegilmaydi): {$expired}");
        $this->info(($this->option('force') ? "  belgilandi" : "  belgilanadi") . " (past baho, otrabotka): {$count}");

        if (!$this->option('force')) {
            $this->comment("Bu faqat hisobot. Belgilash uchun: php artisan grades:mark-low --force");

            return self::SUCCESS;
        }

        foreach ($mark as $deadline => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('student_grades')->whereIn('id', $chunk)
                    ->where('status', 'recorded')->whereNull('reason')
                    ->update(['reason' => 'low_grade', 'status' => 'pending', 'deadline' => $deadline, 'updated_at' => now()]);
            }
        }

        return self::SUCCESS;
    }
}

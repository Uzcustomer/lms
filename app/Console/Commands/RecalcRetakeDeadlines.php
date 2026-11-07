<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Qayta topshirish muddatlari yakshanbasiz hisoblanadigan bo'ldi (Deadline::retakeDeadline).
 * Avval yozilgan NB / past baho muddatlarini (dars + kurs kunlari, yakshanba bilan) yangi
 * qoidaga o'tkazadi. Faqat eski formula bo'yicha yozilganlari: sababli ariza oralig'i yoki
 * qo'lda qo'yilgan muddat tegilmaydi. Yangi muddati bugundan oldin bo'lganlar ham tegilmaydi.
 * Sukut bo'yicha faqat hisobot; o'zgartirish --force bilan.
 */
class RecalcRetakeDeadlines extends Command
{
    protected $signature = 'grades:recalc-retake-deadlines {--force : Haqiqatan o\'zgartirish (bo\'lmasa faqat hisobot)}';

    protected $description = "NB / past baho qayta topshirish muddatlarini yakshanbasiz qayta hisoblash";

    public function handle(): int
    {
        $days = Deadline::pluck('deadline_days', 'level_code');
        $today = Carbon::today();
        // Eng uzun muddat + yakshanbalar zaxirasi — undan eski darslar baribir o'tib ketgan
        $since = $today->copy()->subDays((int) ($days->max() ?? 7) * 2 + 7)->toDateString();

        $rows = DB::table('student_grades as g')
            ->join('students as s', 's.hemis_id', '=', 'g.student_hemis_id')
            ->whereNull('g.deleted_at')
            ->whereIn('g.reason', ['absent', 'low_grade'])
            ->whereNotNull('g.deadline')
            ->whereDate('g.lesson_date', '>=', $since)
            ->get(['g.id', 'g.lesson_date', 'g.deadline', 's.level_code']);

        $update = [];
        $reopened = 0;
        foreach ($rows as $r) {
            $n = (int) ($days[$r->level_code] ?? 7);
            $old = Carbon::parse($r->deadline);
            if ($old->toDateString() !== Carbon::parse($r->lesson_date)->addDays($n)->toDateString()) {
                continue;   // eski formula emas — sababli oraliq yoki qo'lda
            }
            $new = Deadline::retakeDeadline($r->lesson_date, $n);
            if ($new->toDateString() === $old->toDateString() || $new->lt($today)) {
                continue;
            }
            if ($old->lt($today)) {
                $reopened++;
            }
            $update[$new->toDateTimeString()][] = $r->id;
        }

        $count = collect($update)->sum(fn ($ids) => count($ids));
        $this->info(($this->option('force') ? '' : '[hisobot] ') . "Ko'rilgan NB / past baho yozuvlari ({$since} dan): {$rows->count()}");
        $this->info(($this->option('force') ? '  uzaytirildi' : '  uzaytiriladi') . ": {$count}");
        $this->info("  shundan muddati o'tib, yana ochiladiganlar: {$reopened}");

        if (!$this->option('force')) {
            $this->comment('Bu faqat hisobot. O\'zgartirish uchun: php artisan grades:recalc-retake-deadlines --force');

            return self::SUCCESS;
        }

        foreach ($update as $deadline => $ids) {
            foreach (array_chunk($ids, 1000) as $chunk) {
                DB::table('student_grades')->whereIn('id', $chunk)->update(['deadline' => $deadline]);
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\AssignComputersJob;
use App\Models\ExamSchedule;
use Illuminate\Console\Command;

/**
 * Avval kompyuter biriktirish faqat 1-urinish uchun ishlardi —
 * 2/3-urinish (resit) vaqtlari saqlangan bo'lsa ham hech qanday
 * computer_assignments yozuvi yaratilmasdi. Endi (attempt ustuni
 * qo'shilganidan keyin) bu komanda eski 2/3-urinish vaqtlari uchun
 * AssignComputersJob ni ishga tushiradi.
 */
class BackfillResitComputerAssignments extends Command
{
    protected $signature = 'computers:backfill-resit-assignments
        {--attempt=* : Faqat shu urinish(lar) uchun (2,3). Default: ikkalasi.}
        {--yn=* : Faqat shu YN turi(lari) uchun (oski,test). Default: ikkalasi.}
        {--from= : Faqat shu sanadan boshlab (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--to= : Faqat shu sanagacha (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--dry-run : Hech narsa navbatga qo\'shilmaydi, faqat nechta yozuv mosligini ko\'rsatadi}';

    protected $description = '2/3-urinish (resit) sana+vaqt belgilangan ExamSchedule yozuvlari uchun '
        . 'AssignComputersJob ni dispatch qiladi. Avvalgi kod resit\'lar uchun biriktirish '
        . 'yaratmas edi — bu bir martalik backfill.';

    public function handle(): int
    {
        $attempts = $this->option('attempt') ?: [2, 3];
        $attempts = array_map('intval', (array) $attempts);
        $attempts = array_values(array_filter($attempts, fn($a) => in_array($a, [2, 3], true)));
        if (empty($attempts)) {
            $this->error('Hech bo\'lmaganda 2 yoki 3-urinish bo\'lishi kerak.');
            return self::FAILURE;
        }

        $yns = $this->option('yn') ?: ['oski', 'test'];
        $yns = array_values(array_filter(array_map('strtolower', (array) $yns), fn($y) => in_array($y, ['oski', 'test'], true)));

        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — hech narsa navbatga qo\'shilmaydi.');
        }

        $columnMap = [
            'oski' => [
                2 => ['date' => 'oski_resit_date',  'time' => 'oski_resit_time'],
                3 => ['date' => 'oski_resit2_date', 'time' => 'oski_resit2_time'],
            ],
            'test' => [
                2 => ['date' => 'test_resit_date',  'time' => 'test_resit_time'],
                3 => ['date' => 'test_resit2_date', 'time' => 'test_resit2_time'],
            ],
        ];

        $totalDispatched = 0;

        foreach ($yns as $yn) {
            foreach ($attempts as $attempt) {
                $cols = $columnMap[$yn][$attempt];
                $dateField = $cols['date'];
                $timeField = $cols['time'];

                // Faqat guruh sathidagi yozuvlar (per-student emas) —
                // AssignComputersJob group_hemis_id bo'yicha barcha talabalarni biriktiradi.
                $query = ExamSchedule::query()
                    ->whereNull('student_hemis_id')
                    ->whereNotNull($dateField)
                    ->whereNotNull($timeField);

                if ($from) {
                    $query->whereDate($dateField, '>=', $from);
                }
                if ($to) {
                    $query->whereDate($dateField, '<=', $to);
                }

                $count = $query->count();
                $this->line("  {$yn} attempt={$attempt}: {$count} ta yozuv");

                if ($dryRun || $count === 0) {
                    $totalDispatched += $count;
                    continue;
                }

                $query->select('id')->orderBy('id')->chunk(500, function ($chunk) use ($yn, $attempt, &$totalDispatched) {
                    foreach ($chunk as $row) {
                        AssignComputersJob::dispatch((int) $row->id, $yn, $attempt);
                        $totalDispatched++;
                    }
                });
            }
        }

        $verb = $dryRun ? 'mos keladi' : 'navbatga qo\'shildi';
        $this->info("Jami {$totalDispatched} ta yozuv {$verb}.");
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\BookMoodleGroupExam;
use App\Models\ExamSchedule;
use Illuminate\Console\Command;

class BackfillMoodleUnscheduled extends Command
{
    protected $signature = 'moodle:backfill-unscheduled
        {--from= : Faqat shu sanadan boshlab (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--to= : Faqat shu sanagacha (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--dry-run : Hech narsa navbatga qo\'shilmaydi, faqat nechta yozuv mosligini ko\'rsatadi}';

    protected $description = 'Sanasi bor, vaqti yo\'q (N/A emas) ExamSchedule yozuvlari uchun '
        . 'Moodle\'ga "unscheduled" hold dispatch qiladi. ExamSchedule::booted() hook\'i '
        . 'faqat oldinga (saqlanganda) ishlaydi, shuning uchun kod deploy bo\'lishidan oldin '
        . 'yaratilgan eski yozuvlar uchun bir martalik shu komanda ishlatiladi.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — hech narsa navbatga qo\'shilmaydi.');
        }

        $total = 0;

        foreach (['oski', 'test'] as $yn) {
            $dateField = $yn . '_date';
            $timeField = $yn . '_time';
            $naField = $yn . '_na';

            // Mirrors ExamSchedule::booted(): date set, time empty, not N/A.
            $query = ExamSchedule::query()
                ->whereNotNull($dateField)
                ->where(function ($q) use ($timeField) {
                    $q->whereNull($timeField)->orWhere($timeField, '');
                })
                ->where(function ($q) use ($naField) {
                    $q->whereNull($naField)->orWhere($naField, false);
                });

            if ($from) {
                $query->whereDate($dateField, '>=', $from);
            }
            if ($to) {
                $query->whereDate($dateField, '<=', $to);
            }

            $count = 0;
            $query->orderBy('id')->chunkById(200, function ($schedules) use ($yn, $dryRun, &$count) {
                foreach ($schedules as $schedule) {
                    if (!$dryRun) {
                        BookMoodleGroupExam::dispatch($schedule->id, $yn, true);
                    }
                    $count++;
                }
            });

            $verb = $dryRun ? 'mos keldi' : 'dispatch qilindi';
            $this->info(sprintf('  %-5s: %d ta %s', $yn, $count, $verb));
            $total += $count;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Jami: {$total} ta yozuv uchun unscheduled hold dispatch qilinishi mumkin.");
        } else {
            $this->info("Jami: {$total} ta unscheduled hold navbatga qo'shildi. "
                . "Queue worker ularni Moodle'ga yuboradi.");
        }

        return self::SUCCESS;
    }
}

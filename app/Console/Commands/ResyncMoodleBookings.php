<?php

namespace App\Console\Commands;

use App\Jobs\BookMoodleGroupExam;
use App\Models\ExamSchedule;
use Illuminate\Console\Command;

class ResyncMoodleBookings extends Command
{
    protected $signature = 'moodle:resync-bookings
        {--from= : Faqat shu sanadan boshlab (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--to= : Faqat shu sanagacha (Y-m-d) imtihon sanasiga ega yozuvlar}
        {--yn=both : Qaysi imtihon turi: oski | test | both}
        {--dry-run : Hech narsa navbatga qo\'shilmaydi, faqat hisoblaydi}';

    protected $description = 'Barcha ExamSchedule yozuvlarini (ham vaqtli, ham vaqtsiz) Moodle\'ga '
        . 'qayta push qiladi — Moodle tomonda examstart (test sanasi/vaqti) to\'lishi uchun. '
        . 'moodle:backfill-unscheduled faqat vaqtsizlarni qamraydi; bu komanda eski kod bilan '
        . 'push qilingan vaqtli (scheduled) bookinglarni ham yangilaydi. ExamSchedule::booted() '
        . 'bilan bir xil mantiq: N/A yoki sanasiz yozuvlar o\'tkazib yuboriladi; vaqtsizlar '
        . '"unscheduled" hold, vaqtlilar to\'liq booking sifatida ketadi.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        $ynOpt = strtolower((string) $this->option('yn'));
        $yns = match ($ynOpt) {
            'oski' => ['oski'],
            'test' => ['test'],
            default => ['oski', 'test'],
        };

        if ($dryRun) {
            $this->warn('DRY RUN — hech narsa navbatga qo\'shilmaydi.');
        }

        $total = 0;
        $totalHold = 0;
        $totalBooking = 0;

        foreach ($yns as $yn) {
            $dateField = $yn . '_date';
            $timeField = $yn . '_time';
            $naField = $yn . '_na';

            // Mirrors ExamSchedule::booted(): a date is required, N/A is skipped.
            $query = ExamSchedule::query()
                ->whereNotNull($dateField)
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
            $hold = 0;
            $query->orderBy('id')->chunkById(200,
                function ($schedules) use ($yn, $timeField, $dryRun, &$count, &$hold) {
                    foreach ($schedules as $schedule) {
                        // Date set but no time yet -> unscheduled hold;
                        // date + time -> full booking.
                        $unscheduled = empty($schedule->{$timeField});
                        if (!$dryRun) {
                            BookMoodleGroupExam::dispatch($schedule->id, $yn, $unscheduled);
                        }
                        $count++;
                        if ($unscheduled) {
                            $hold++;
                        }
                    }
                });

            $this->info(sprintf('  %-5s: %d ta (%d hold, %d booking)',
                $yn, $count, $hold, $count - $hold));
            $total += $count;
            $totalHold += $hold;
            $totalBooking += ($count - $hold);
        }

        $this->newLine();
        $verb = $dryRun ? 'topildi' : 'navbatga qo\'shildi';
        $this->info("Jami: {$total} ta {$verb} ({$totalHold} hold, {$totalBooking} booking).");
        if (!$dryRun) {
            $this->info("Queue worker ularni Moodle'ga yuboradi.");
        }

        return self::SUCCESS;
    }
}

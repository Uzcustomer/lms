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
        {--attempt=all : Qaysi urinish: 1 | 2 | 3 | all}
        {--dry-run : Hech narsa navbatga qo\'shilmaydi, faqat hisoblaydi}';

    protected $description = 'Barcha ExamSchedule yozuvlarini (ham vaqtli, ham vaqtsiz, '
        . 'barcha urinishlar) Moodle\'ga qayta push qiladi — Moodle tomonda examstart va '
        . 'quiz mosligi to\'lishi uchun. moodle:backfill-unscheduled faqat 1-urinish '
        . 'vaqtsizlarini qamraydi; bu komanda eski kod bilan push qilingan vaqtli bookinglarni '
        . 'va 2-/3-urinishlarni ham yangilaydi. ExamSchedule::booted() bilan bir xil mantiq: '
        . 'N/A (faqat 1-urinish) yoki sanasiz yozuvlar o\'tkazib yuboriladi; vaqtsizlar '
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

        $attemptOpt = (string) $this->option('attempt');
        $attempts = in_array($attemptOpt, ['1', '2', '3'], true) ? [(int) $attemptOpt] : [1, 2, 3];

        if ($dryRun) {
            $this->warn('DRY RUN — hech narsa navbatga qo\'shilmaydi.');
        }

        $total = 0;
        $totalHold = 0;

        foreach ($yns as $yn) {
            foreach ($attempts as $attempt) {
                // Attempt-specific date/time column prefix:
                // 1 = oski/test, 2 = *_resit, 3 = *_resit2.
                $prefix = match ($attempt) {
                    2 => $yn . '_resit',
                    3 => $yn . '_resit2',
                    default => $yn,
                };
                $dateField = $prefix . '_date';
                $timeField = $prefix . '_time';

                // Mirrors ExamSchedule::booted(): a date is required; the N/A
                // flag skips attempt 1 only (resits have no N/A).
                $query = ExamSchedule::query()->whereNotNull($dateField);
                if ($attempt === 1) {
                    $naField = $yn . '_na';
                    $query->where(function ($q) use ($naField) {
                        $q->whereNull($naField)->orWhere($naField, false);
                    });
                }
                if ($from) {
                    $query->whereDate($dateField, '>=', $from);
                }
                if ($to) {
                    $query->whereDate($dateField, '<=', $to);
                }

                $count = 0;
                $hold = 0;
                $query->orderBy('id')->chunkById(200,
                    function ($schedules) use ($yn, $attempt, $timeField, $dryRun, &$count, &$hold) {
                        foreach ($schedules as $schedule) {
                            // Date set but no time yet -> unscheduled hold;
                            // date + time -> full booking.
                            $unscheduled = empty($schedule->{$timeField});
                            if (!$dryRun) {
                                BookMoodleGroupExam::dispatch($schedule->id, $yn, $unscheduled, $attempt);
                            }
                            $count++;
                            if ($unscheduled) {
                                $hold++;
                            }
                        }
                    });

                $this->info(sprintf('  %-5s %d-urinish: %d ta (%d hold, %d booking)',
                    $yn, $attempt, $count, $hold, $count - $hold));
                $total += $count;
                $totalHold += $hold;
            }
        }

        $this->newLine();
        $verb = $dryRun ? 'topildi' : 'navbatga qo\'shildi';
        $this->info("Jami: {$total} ta {$verb} ({$totalHold} hold, "
            . ($total - $totalHold) . ' booking).');
        if (!$dryRun) {
            $this->info("Queue worker ularni Moodle'ga yuboradi.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\VedomostSubmission;
use App\Services\VedomostSubmissionNotifier;
use App\Support\WorkdayCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendVedomostDeadlineWarnings extends Command
{
    protected $signature = 'vedomost:deadline-warnings';

    protected $description = "Vedomost topshirish muddati bo'yicha ogohlantirish (oz qoldi / kechikdi)";

    /** Muddat tugashiga shuncha ish kuni qolganda "oz qoldi" ogohlantirishi. */
    private const SOON_WORKDAYS = 1;

    public function handle(VedomostSubmissionNotifier $notifier): int
    {
        if (!VedomostSubmissionNotifier::enabled()) {
            $this->info("Vedomost xabarlari o'chirilgan — ogohlantirishlar yuborilmaydi.");
            return self::SUCCESS;
        }

        $today = now()->startOfDay();

        // Faqat hali topshirilmagan (yoki rad etilib, qayta topshirilishi kerak) vedomostlar
        $items = VedomostSubmission::whereIn('status', [
            VedomostSubmission::STATUS_PENDING,
            VedomostSubmission::STATUS_REJECTED,
        ])->whereNotNull('deadline')->get();

        $sent = 0;
        foreach ($items as $v) {
            $deadline = Carbon::parse($v->deadline)->startOfDay();

            $stage = null;
            if ($today->gt($deadline)) {
                $stage = 'overdue';
            } elseif (WorkdayCalculator::addWorkdays($today, self::SOON_WORKDAYS)->gte($deadline)) {
                // Muddatga <= SOON_WORKDAYS ish kuni qoldi (va hali o'tmagan)
                $stage = 'soon';
            }

            if (!$stage || $v->warning_stage === $stage) {
                continue; // ogohlantirish kerak emas yoki shu bosqich allaqachon yuborilgan
            }

            $notifier->notifyDeadlineWarning($v, $stage);
            $v->forceFill(['warning_stage' => $stage, 'warned_at' => now()])->saveQuietly();
            $sent++;
        }

        $this->info("Tugadi. {$sent} ta ogohlantirish yuborildi.");
        return self::SUCCESS;
    }
}

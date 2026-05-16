<?php

namespace App\Console\Commands;

use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeApplicationLog;
use App\Models\RetakeApplicationWindow;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CloseExpiredRetakeWindows extends Command
{
    protected $signature = 'retake:close-expired-windows
                            {--dry-run : Faqat hisoblab chiqsin, hech narsani o\'zgartirmasin}';

    protected $description = 'Muddati o\'tgan oynalardagi pending qayta o\'qish arizalarini avtomatik rad etadi';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        // Muddati o'tgan oynalar
        $expiredWindows = RetakeApplicationWindow::query()
            ->whereDate('end_date', '<', $today)
            ->pluck('id');

        if ($expiredWindows->isEmpty()) {
            $this->info('Muddati o\'tgan oyna topilmadi.');
            return self::SUCCESS;
        }

        // Bu oynalardagi pending arizalarni topamiz
        $pendingApps = RetakeApplication::query()
            ->where('final_status', RetakeApplication::STATUS_PENDING)
            ->whereHas('group', fn ($q) => $q->whereIn('window_id', $expiredWindows))
            ->get();

        $count = $pendingApps->count();
        if ($count === 0) {
            $this->info('Muddati o\'tgan oynalarda pending ariza yo\'q.');
            return self::SUCCESS;
        }

        $this->info("{$count} ta pending ariza avtomatik rad etiladi" . ($dryRun ? ' (dry-run)' : '') . '...');
        $bar = $this->output->createProgressBar($count);

        if ($dryRun) {
            foreach ($pendingApps as $app) {
                $bar->advance();
            }
            $bar->finish();
            $this->newLine(2);
            $this->info('Dry-run tugadi. Aslida hech narsa o\'zgartirilmadi.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($pendingApps, $bar) {
            foreach ($pendingApps as $app) {
                $app->update([
                    'final_status' => RetakeApplication::STATUS_REJECTED,
                    'rejected_by' => RetakeApplication::REJECTED_BY_WINDOW_CLOSED,
                    'academic_dept_reason' => 'Oyna muddati o\'tdi, ariza avtomatik rad etildi',
                ]);

                RetakeApplicationLog::create([
                    'application_id' => $app->id,
                    'group_id' => $app->group_id,
                    'user_id' => null,
                    'user_type' => 'system',
                    'user_name' => null,
                    'action' => RetakeApplicationLog::ACTION_AUTO_REJECTED_WINDOW_CLOSED,
                    'reason' => 'Oyna muddati o\'tdi',
                ]);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Tayyor. {$count} ta ariza rad etildi.");

        return self::SUCCESS;
    }
}

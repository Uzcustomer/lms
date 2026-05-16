<?php

namespace App\Console\Commands;

use App\Models\FaceIdLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FaceIdCleanupCommand extends Command
{
    protected $signature = 'faceid:cleanup {--temp-hours=2 : Keep temp snapshot files newer than this amount of hours} {--logs-days=30 : Keep face_id_logs snapshots newer than this amount of days}';

    protected $description = 'Cleanup FaceID temp images and old snapshots in face_id_logs.';

    public function handle(): int
    {
        $tempHours = max((int) $this->option('temp-hours'), 1);
        $logsDays = max((int) $this->option('logs-days'), 1);

        $removedFiles = $this->cleanupTempFiles($tempHours);
        $clearedSnapshots = $this->cleanupOldSnapshots($logsDays);

        $this->info("FaceID cleanup done. temp_files_removed={$removedFiles}, snapshots_cleared={$clearedSnapshots}");

        return self::SUCCESS;
    }

    private function cleanupTempFiles(int $keepHours): int
    {
        $dir = public_path('uploads/face-temp');
        if (!is_dir($dir)) {
            return 0;
        }

        $threshold = Carbon::now()->subHours($keepHours)->getTimestamp();
        $removed = 0;

        foreach (File::files($dir) as $file) {
            try {
                if ($file->getMTime() < $threshold) {
                    File::delete($file->getPathname());
                    $removed++;
                }
            } catch (\Throwable $e) {
                // continue
            }
        }

        return $removed;
    }

    private function cleanupOldSnapshots(int $keepDays): int
    {
        $cutoff = Carbon::now()->subDays($keepDays);

        return FaceIdLog::query()
            ->whereNotNull('snapshot')
            ->where('created_at', '<', $cutoff)
            ->update(['snapshot' => null]);
    }
}

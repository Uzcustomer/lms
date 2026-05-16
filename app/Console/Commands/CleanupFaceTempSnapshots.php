<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupFaceTempSnapshots extends Command
{
    protected $signature = 'faceid:cleanup-temp
        {--minutes=60 : Shu vaqtdan eski fayllar ochiriladi (default 60 daqiqa)}';

    protected $description = 'public/uploads/face-temp/ papkasidagi orphan snapshot fayllarini tozalash';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $dir = public_path('uploads/face-temp');

        if (!is_dir($dir)) {
            $this->info("Papka mavjud emas: {$dir}");
            return self::SUCCESS;
        }

        $threshold = time() - ($minutes * 60);
        $deleted = 0;
        $kept = 0;
        $totalBytes = 0;

        $files = glob($dir . '/*') ?: [];
        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $mtime = @filemtime($file);
            if ($mtime !== false && $mtime < $threshold) {
                $size = @filesize($file) ?: 0;
                if (@unlink($file)) {
                    $deleted++;
                    $totalBytes += $size;
                }
            } else {
                $kept++;
            }
        }

        $this->info(sprintf(
            "FaceID temp tozalandi: o'chirilgan=%d, qoldirilgan=%d, bo'shatilgan=%s",
            $deleted, $kept, $this->formatBytes($totalBytes)
        ));
        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}

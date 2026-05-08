<?php

namespace App\Console\Commands;

use App\Models\StudentPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BulkRejectStudentPhotos extends Command
{
    protected $signature = 'student-photos:bulk-reject
        {--file= : Path to a file with student_id_numbers (JSON array or one per line)}
        {--reason=Yuz aniqlanmadi (Moodle enroll) : Rejection reason saved on each photo}
        {--reviewer=system (bulk no-face) : Value stored in reviewed_by_name}
        {--dry-run : Show what would change without writing}';

    protected $description = 'Approved StudentPhoto rows whose student_id_number is in the given list are bulk-rejected.';

    public function handle(): int
    {
        $file = (string) $this->option('file');
        if ($file === '' || !is_file($file)) {
            $this->error("File topilmadi: {$file}");
            return self::FAILURE;
        }

        $idnumbers = $this->parseIds(file_get_contents($file));
        $requested = count($idnumbers);
        if ($requested === 0) {
            $this->error('Faylda hech qanday ID yo\'q.');
            return self::FAILURE;
        }

        $photos = StudentPhoto::query()
            ->whereIn('student_id_number', $idnumbers)
            ->where('status', StudentPhoto::STATUS_APPROVED)
            ->get(['id', 'student_id_number', 'full_name']);

        $matchedIds = $photos->pluck('student_id_number')->unique()->values();
        $missing = array_values(array_diff($idnumbers, $matchedIds->all()));

        $this->info("Faylda: {$requested}");
        $this->info("Mos approved rasm: {$photos->count()} ({$matchedIds->count()} ta ID)");
        $this->info("Topilmadi yoki approved emas: " . count($missing));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run — hech narsa yozilmadi.');
            return self::SUCCESS;
        }

        if (!$this->confirm("{$photos->count()} ta rasmni rad etish — davom etamizmi?", false)) {
            $this->warn('Bekor qilindi.');
            return self::SUCCESS;
        }

        $reason = (string) $this->option('reason');
        $reviewer = (string) $this->option('reviewer');
        $now = now();

        $updated = StudentPhoto::query()
            ->whereIn('id', $photos->pluck('id'))
            ->update([
                'status' => StudentPhoto::STATUS_REJECTED,
                'reviewed_by_name' => $reviewer,
                'reviewed_at' => $now,
                'rejection_reason' => $reason,
                'updated_at' => $now,
            ]);

        Log::info('student-photos:bulk-reject completed', [
            'requested' => $requested,
            'matched' => $photos->count(),
            'updated' => $updated,
            'reason' => $reason,
            'reviewer' => $reviewer,
            'file' => $file,
        ]);

        $this->info("Rad etildi: {$updated}");
        return self::SUCCESS;
    }

    /** @return string[] */
    private function parseIds(string $raw): array
    {
        $trimmed = trim($raw);
        $items = [];

        if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = array_values($decoded);
            }
        }

        if (!$items) {
            $items = preg_split('/[\s,;]+/', $trimmed) ?: [];
        }

        $items = array_map(fn($v) => trim((string) $v), $items);
        $items = array_filter($items, fn($v) => $v !== '');
        return array_values(array_unique($items));
    }
}

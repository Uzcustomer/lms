<?php

namespace App\Console\Commands;

use App\Jobs\SendStudentPhotoToMoodle;
use App\Models\StudentPhoto;
use App\Services\MoodleStudentPhotoService;
use Illuminate\Console\Command;

class SyncStudentPhotosToMoodle extends Command
{
    protected $signature = 'moodle:sync-student-photos
                            {--queue : Dispatch via queue (default: run synchronously)}
                            {--retry-failed : Also retry photos that previously failed}
                            {--all : Re-send every approved photo, even if already synced}
                            {--limit=0 : Maximum number of photos to process (0 = no limit)}
                            {--id=* : Process only specific student_photos.id values}';

    protected $description = 'Tasdiqlangan talaba rasmlarini Moodle ga (local_hemisexport_save_student_photo) yuboradi. Default: hali sinxronlanmagan rasmlar.';

    public function handle(MoodleStudentPhotoService $service): int
    {
        $query = StudentPhoto::query()
            ->where('status', StudentPhoto::STATUS_APPROVED);

        $ids = (array) $this->option('id');
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        } elseif (!$this->option('all')) {
            $query->where(function ($q) {
                $q->whereNull('moodle_synced_at');
                if ($this->option('retry-failed')) {
                    $q->orWhere('moodle_sync_status', MoodleStudentPhotoService::STATUS_FAILED);
                }
            });
        }

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Yuboradigan rasm topilmadi.');
            return self::SUCCESS;
        }

        $this->info("Rasmlar soni: {$total}");
        $useQueue = (bool) $this->option('queue');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $counts = ['synced' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0, 'queued' => 0];

        $query->orderBy('id')->chunkById(200, function ($chunk) use ($service, $useQueue, &$counts, $bar) {
            foreach ($chunk as $photo) {
                if ($useQueue) {
                    SendStudentPhotoToMoodle::dispatch($photo->id);
                    $counts['queued']++;
                } else {
                    $result = $service->send($photo);
                    $status = (string) ($result['status'] ?? ($result['ok'] ? 'synced' : 'failed'));
                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        foreach ($counts as $k => $v) {
            if ($v > 0) {
                $this->line(sprintf('  %-10s %d', $k . ':', $v));
            }
        }

        return self::SUCCESS;
    }
}

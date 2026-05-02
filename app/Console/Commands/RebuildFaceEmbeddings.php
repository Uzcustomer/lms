<?php

namespace App\Console\Commands;

use App\Models\StudentPhoto;
use App\Services\FaceIdService;
use Illuminate\Console\Command;

class RebuildFaceEmbeddings extends Command
{
    protected $signature = 'faceid:rebuild-embeddings
        {--only-missing : Faqat embedding yoq yozuvlarni qayta ishlash}
        {--limit=0 : Maksimum nechta yozuv (0 = chegarasiz)}
        {--push-cache : Tayyor bolgach Python /refresh-cache ga yuborish}';

    protected $description = 'Tasdiqlangan student_photos rasmlari uchun ArcFace embedding\'larini hisoblab DB ga yozadi';

    public function handle(): int
    {
        $onlyMissing = (bool) $this->option('only-missing');
        $limit = (int) $this->option('limit');
        $pushCache = (bool) $this->option('push-cache');

        $query = StudentPhoto::where('status', StudentPhoto::STATUS_APPROVED);
        if ($onlyMissing) {
            $query->whereNull('face_embedding');
        }

        $availableTotal = (clone $query)->count();
        $total = ($limit > 0 && $limit < $availableTotal) ? $limit : $availableTotal;

        $this->info("Qayta ishlanadi: {$total} ta rasm" . ($limit > 0 ? " (chegara: {$limit}, jami mos: {$availableTotal})" : ''));
        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0;
        $failed = 0;
        $skipped = 0;
        $processed = 0;

        $query->orderBy('id')->chunkById(50, function ($photos) use (&$ok, &$failed, &$skipped, &$processed, $bar, $limit) {
            foreach ($photos as $photo) {
                if ($limit > 0 && $processed >= $limit) {
                    return false; // chunkById iteratsiyani to'xtatadi
                }
                $processed++;

                // Local fayl yo'lini berish HTTP yuklashdan tezroq
                $localPath = public_path($photo->photo_path);
                $source = file_exists($localPath) ? $localPath : asset($photo->photo_path);

                $embedding = FaceIdService::extractEmbedding($source);
                if (!$embedding) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $photo->face_embedding = $embedding;
                $photo->embedding_extracted_at = now();
                $photo->saveQuietly();
                $ok++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info("Muvaffaqiyatli: {$ok}");
        if ($failed) $this->warn("Xato: {$failed}");
        if ($skipped) $this->line("Skip: {$skipped}");

        if ($pushCache) {
            $this->pushCacheToPython();
        }

        return self::SUCCESS;
    }

    private function pushCacheToPython(): void
    {
        $this->info('Python /refresh-cache ga yuborilmoqda...');

        $items = [];
        StudentPhoto::where('status', StudentPhoto::STATUS_APPROVED)
            ->whereNotNull('face_embedding')
            ->select('student_id_number', 'full_name', 'face_embedding')
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$items) {
                foreach ($chunk as $row) {
                    $items[] = [
                        'student_id_number' => $row->student_id_number,
                        'full_name'         => $row->full_name,
                        'embedding'         => $row->face_embedding,
                    ];
                }
            });

        if (empty($items)) {
            $this->warn('Cache uchun yozuv yo\'q.');
            return;
        }

        $result = FaceIdService::refreshArcFaceCache($items, true);
        if (!$result) {
            $this->error('Cache yangilanmadi (Python service javob bermadi)');
            return;
        }

        $this->info("Python cache yangilandi. cache_size={$result['cache_size']}, added={$result['added_or_updated']}");
        if (!empty($result['failed'])) {
            $this->warn('Cache failed entries: ' . count($result['failed']));
        }
    }
}

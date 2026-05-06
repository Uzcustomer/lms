<?php

namespace App\Jobs;

use App\Models\StudentPhoto;
use App\Services\MoodleStudentPhotoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendStudentPhotoToMoodle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [30, 120, 300, 900, 1800];

    public function __construct(public int $studentPhotoId) {}

    public function handle(MoodleStudentPhotoService $service): void
    {
        $photo = StudentPhoto::find($this->studentPhotoId);
        if (!$photo) {
            Log::info('SendStudentPhotoToMoodle: photo missing', [
                'id' => $this->studentPhotoId,
            ]);
            return;
        }

        $result = $service->send($photo);

        if (!empty($result['ok'])) {
            return;
        }

        // Skipped (not approved / missing config / no idnumber) — do not retry.
        if (($result['status'] ?? null) === MoodleStudentPhotoService::STATUS_SKIPPED) {
            return;
        }

        // 4xx from Moodle (validation / capability / unknown user) — no point retrying.
        $http = (int) ($result['http_status'] ?? 0);
        if ($http >= 400 && $http < 500) {
            Log::warning('SendStudentPhotoToMoodle: 4xx, no retry', [
                'photo_id' => $this->studentPhotoId,
                'http_status' => $http,
                'error' => $result['error'] ?? null,
            ]);
            return;
        }

        // Transient: trigger queue retry.
        $this->release($this->backoff[$this->attempts() - 1] ?? 1800);
    }
}

<?php

namespace App\Services;

use App\Models\StudentPhoto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoodleStudentPhotoService
{
    public const STATUS_SYNCED = 'synced';
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly ?string $wsUrl = null,
        private readonly ?string $wsToken = null,
    ) {}

    /**
     * Push an approved StudentPhoto file to the Moodle local_hemisexport plugin.
     *
     * @return array{ok:bool, status:string, reason?:string, response?:mixed, error?:string, sha256?:string, http_status?:int}
     */
    public function send(StudentPhoto $photo): array
    {
        if (!$photo->isApproved()) {
            return $this->skip($photo, 'photo is not approved');
        }
        if (empty($photo->student_id_number)) {
            return $this->skip($photo, 'student_id_number is empty');
        }
        if (empty($photo->photo_path)) {
            return $this->skip($photo, 'photo_path is empty');
        }

        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');
        if ($url === '' || $token === '') {
            return $this->skip($photo, 'Moodle WS not configured');
        }

        $absolute = public_path($photo->photo_path);
        if (!is_file($absolute) || !is_readable($absolute)) {
            return $this->fail($photo, 'photo file missing on disk: ' . $photo->photo_path);
        }

        $bytes = @file_get_contents($absolute);
        if ($bytes === false || $bytes === '') {
            return $this->fail($photo, 'unable to read photo file');
        }

        $sha256 = hash('sha256', $bytes);

        // Idempotency: skip if Moodle already has this exact file.
        if ($photo->moodle_file_hash === $sha256 && $photo->moodle_synced_at) {
            $photo->forceFill([
                'moodle_sync_status' => self::STATUS_UNCHANGED,
                'moodle_sync_error' => null,
            ])->saveQuietly();
            return [
                'ok' => true,
                'status' => self::STATUS_UNCHANGED,
                'sha256' => $sha256,
            ];
        }

        $mime = $this->detectMime($absolute);

        $payload = [
            'wstoken' => $token,
            'wsfunction' => 'local_hemisexport_save_student_photo',
            'moodlewsrestformat' => 'json',
            'idnumber' => (string) $photo->student_id_number,
            'image_base64' => base64_encode($bytes),
            'sha256' => $sha256,
            'mime' => $mime,
            'uploaded_at' => optional($photo->reviewed_at ?? $photo->updated_at)->toIso8601String(),
            'version' => 1,
        ];

        $callResult = $this->call($url, $payload);

        if (!$callResult['ok']) {
            $errorText = $callResult['error']
                ?? (is_array($callResult['response'] ?? null)
                    ? json_encode($callResult['response'], JSON_UNESCAPED_UNICODE)
                    : (string) ($callResult['response'] ?? 'unknown'));

            $photo->forceFill([
                'moodle_sync_status' => self::STATUS_FAILED,
                'moodle_sync_error' => $errorText,
                'moodle_response' => $callResult['response'] ?? null,
            ])->saveQuietly();

            return [
                'ok' => false,
                'status' => self::STATUS_FAILED,
                'error' => $errorText,
                'http_status' => $callResult['http_status'] ?? null,
                'response' => $callResult['response'] ?? null,
            ];
        }

        $body = $callResult['response'];
        $remoteStatus = is_array($body) ? (string) ($body['status'] ?? '') : '';
        $localStatus = $remoteStatus === 'unchanged' ? self::STATUS_UNCHANGED : self::STATUS_SYNCED;

        $photo->forceFill([
            'moodle_synced_at' => now(),
            'moodle_sync_status' => $localStatus,
            'moodle_sync_error' => null,
            'moodle_file_hash' => $sha256,
            'moodle_response' => $body,
        ])->saveQuietly();

        return [
            'ok' => true,
            'status' => $localStatus,
            'sha256' => $sha256,
            'response' => $body,
            'http_status' => $callResult['http_status'] ?? null,
        ];
    }

    private function detectMime(string $absolutePath): string
    {
        $mime = @mime_content_type($absolutePath);
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return $mime;
        }
        return 'image/jpeg';
    }

    /**
     * @return array{ok:bool, http_status?:int, response?:mixed, error?:string}
     */
    private function call(string $url, array $payload): array
    {
        $timeout = max(10, (int) config('services.moodle.ws_timeout', 30));
        try {
            // image_base64 may be ~1MB; asForm + POST handles it.
            $resp = Http::asForm()->timeout($timeout)->post($url, $payload);
            $body = $resp->json();
            // Accept either {success:true,...} or {status:"saved"|"unchanged",...}
            $okFlag = is_array($body) && (
                !empty($body['success'])
                || in_array((string) ($body['status'] ?? ''), ['saved', 'unchanged'], true)
            );
            $ok = $resp->successful() && $okFlag;
            return [
                'ok' => $ok,
                'http_status' => $resp->status(),
                'response' => $body ?? $resp->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Moodle save_student_photo failed', [
                'error' => $e->getMessage(),
                'idnumber' => $payload['idnumber'] ?? null,
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function skip(StudentPhoto $photo, string $reason): array
    {
        // Reflect the skip reason on the row so admins can see why nothing was sent.
        $photo->forceFill([
            'moodle_sync_status' => self::STATUS_SKIPPED,
            'moodle_sync_error' => $reason,
        ])->saveQuietly();

        return ['ok' => false, 'status' => self::STATUS_SKIPPED, 'reason' => $reason];
    }

    private function fail(StudentPhoto $photo, string $error): array
    {
        $photo->forceFill([
            'moodle_sync_status' => self::STATUS_FAILED,
            'moodle_sync_error' => $error,
        ])->saveQuietly();

        return ['ok' => false, 'status' => self::STATUS_FAILED, 'error' => $error];
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PhotoQualityGate
{
    public const MIN_FACE_HEIGHT_RATIO = 0.35;

    /**
     * Read a local image file and return it as a `data:image/...;base64,...`
     * URI suitable for inline POST to the face-compare service. Lets the
     * AI service load the image directly from the request body instead of
     * fetching it back via HTTP (which stalls when the service runs outside
     * the Laravel container and the public APP_URL is unreachable from there).
     */
    public static function fileToDataUri(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            if ($f) {
                $detected = finfo_file($f, $absolutePath);
                finfo_close($f);
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    public static function checkPath(string $absolutePath): array
    {
        $dataUri = self::fileToDataUri($absolutePath);
        if ($dataUri === null) {
            return [
                'reachable' => true,
                'passed' => false,
                'reason' => 'Rasm faylini o\'qib bo\'lmadi: ' . $absolutePath,
            ];
        }
        return self::requestQualityCheck($dataUri, ['path' => $absolutePath]);
    }

    public static function checkUrl(string $imageUrl): array
    {
        return self::requestQualityCheck($imageUrl, ['image_url' => $imageUrl]);
    }

    private static function requestQualityCheck(string $imageField, array $logContext): array
    {
        $serviceUrl = rtrim((string) config('services.face_compare.url'), '/');
        $timeout = (int) config('services.face_compare.timeout', 60);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->post($serviceUrl . '/quality-check', ['image' => $imageField]);
        } catch (\Throwable $e) {
            Log::error('PhotoQualityGate: service unreachable', $logContext + [
                'error' => $e->getMessage(),
            ]);
            return [
                'reachable' => false,
                'passed' => false,
                'reason' => 'AI sifat servisiga ulanib bo\'lmadi: ' . $e->getMessage(),
            ];
        }

        if (!$response->successful()) {
            return [
                'reachable' => true,
                'passed' => false,
                'reason' => 'AI sifat servis xatoligi: ' . substr((string) $response->body(), 0, 200),
            ];
        }

        $data = $response->json() ?: [];

        return self::evaluate($data);
    }

    public static function evaluate(array $data): array
    {
        $serviceOk = (bool) ($data['passed'] ?? false);
        $score = (float) ($data['quality_score'] ?? 0);
        $issues = is_array($data['issues'] ?? null) ? $data['issues'] : [];
        $ok = is_array($data['ok'] ?? null) ? $data['ok'] : [];
        $metrics = is_array($data['metrics'] ?? null) ? $data['metrics'] : [];

        $faceRatio = self::extractFaceHeightRatio($metrics, $issues);
        $tooSmall = $faceRatio !== null && $faceRatio < self::MIN_FACE_HEIGHT_RATIO;

        $reasons = [];
        if (!$serviceOk) {
            foreach ($issues as $msg) {
                $reasons[] = (string) $msg;
            }
        }
        if ($tooSmall) {
            $reasons[] = sprintf(
                'Yuz juda kichik (kadr balandligining %d%%i, kerak: ≥%d%%). Iltimos, talabani yelkasidan yuqori qilib qayta suratga oling.',
                (int) round($faceRatio * 100),
                (int) round(self::MIN_FACE_HEIGHT_RATIO * 100)
            );
        }

        return [
            'reachable' => true,
            'passed' => $serviceOk && !$tooSmall,
            'quality_score' => $score,
            'service_passed' => $serviceOk,
            'issues' => $issues,
            'ok' => $ok,
            'metrics' => $metrics,
            'face_height_ratio' => $faceRatio,
            'reason' => $reasons ? implode('; ', array_unique($reasons)) : null,
        ];
    }

    private static function extractFaceHeightRatio(array $metrics, array $issues): ?float
    {
        foreach (['face_height_ratio', 'face_height_pct', 'face_to_image_height', 'face_height'] as $k) {
            if (isset($metrics[$k]) && is_numeric($metrics[$k])) {
                $v = (float) $metrics[$k];
                return $v > 1.0 ? $v / 100.0 : $v;
            }
        }
        // Fallback: face_compare service emits a localized hint like
        // "yuz balandlikning 29%ni egallaydi" inside issues — extract the percent.
        foreach ($issues as $msg) {
            $s = (string) $msg;
            if (mb_stripos($s, 'yuz balandlik') !== false && preg_match('/(\d{1,3})\s*%/u', $s, $m)) {
                return ((int) $m[1]) / 100.0;
            }
            if (mb_stripos($s, 'face height') !== false && preg_match('/(\d{1,3})\s*%/u', $s, $m)) {
                return ((int) $m[1]) / 100.0;
            }
        }
        return null;
    }
}

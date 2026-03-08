<?php

namespace App\Services;

use App\Models\FaceIdLog;
use App\Models\FaceIdDescriptor;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaceIdService
{
    // ───────────────────────────── Settings ─────────────────────────────

    public static function isGloballyEnabled(): bool
    {
        return (bool) Setting::get('faceid_global_enabled', true);
    }

    public static function isEnabledForStudent(Student $student): bool
    {
        return self::isGloballyEnabled() && (bool) $student->face_id_enabled;
    }

    public static function getThreshold(): float
    {
        // Euclidean distance threshold: 0.4 ~ 90% yaqinlik
        return (float) Setting::get('faceid_threshold', 0.40);
    }

    public static function getLivenessConfig(): array
    {
        return [
            'blinks_required'    => (int) Setting::get('faceid_blinks_required', 2),
            'head_turn_required' => (bool) Setting::get('faceid_head_turn_required', true),
            'timeout_seconds'    => (int) Setting::get('faceid_liveness_timeout', 30),
        ];
    }

    public static function getSettings(): array
    {
        return [
            'global_enabled'       => self::isGloballyEnabled(),
            'threshold'            => self::getThreshold(),
            'blinks_required'      => (int) Setting::get('faceid_blinks_required', 2),
            'head_turn_required'   => (bool) Setting::get('faceid_head_turn_required', true),
            'liveness_timeout'     => (int) Setting::get('faceid_liveness_timeout', 30),
            'save_snapshots'       => (bool) Setting::get('faceid_save_snapshots', true),
            'max_snapshot_kb'      => (int) Setting::get('faceid_max_snapshot_kb', 50),
        ];
    }

    // ───────────────────────────── Logging ──────────────────────────────

    public static function logAttempt(array $data): FaceIdLog
    {
        $settings = self::getSettings();

        $snapshot = null;
        if ($settings['save_snapshots'] && !empty($data['snapshot'])) {
            // Strip data URI prefix if present
            $raw = preg_replace('/^data:image\/\w+;base64,/', '', $data['snapshot']);
            // Size check (base64 length ≈ 4/3 × bytes)
            $estimatedKb = strlen($raw) * 3 / 4 / 1024;
            if ($estimatedKb <= $settings['max_snapshot_kb'] * 1.1) {
                $snapshot = $raw;
            }
        }

        return FaceIdLog::create([
            'student_id'        => $data['student_id']        ?? null,
            'student_id_number' => $data['student_id_number'] ?? null,
            'result'            => $data['result']            ?? 'failed',
            'confidence'        => $data['confidence']        ?? null,
            'distance'          => $data['distance']          ?? null,
            'failure_reason'    => $data['failure_reason']    ?? null,
            'snapshot'          => $snapshot,
            'ip_address'        => $data['ip_address']        ?? null,
            'user_agent'        => $data['user_agent']        ?? null,
        ]);
    }

    // ───────────────────────── Descriptor cache ─────────────────────────

    /**
     * Talabaning yuz deskriptorini saqlash (brauzerdan yuborilgan descriptor).
     */
    public static function saveDescriptor(Student $student, array $descriptor, ?string $sourceUrl = null): FaceIdDescriptor
    {
        return FaceIdDescriptor::updateOrCreate(
            ['student_id' => $student->id],
            [
                'descriptor'       => $descriptor,
                'source_image_url' => $sourceUrl,
                'enrolled_at'      => now(),
            ]
        );
    }

    /**
     * Saqlangan deskriptorni qaytarish.
     */
    public static function getDescriptor(Student $student): ?array
    {
        $row = FaceIdDescriptor::where('student_id', $student->id)->first();
        return $row ? $row->descriptor : null;
    }

    /**
     * Ikki deskriptor orasidagi Euclidean masofani hisoblash (PHP serverda).
     * face-api.js 128-dim Float32Array deskriptorlar.
     */
    public static function euclideanDistance(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 1.0;
        }
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $diff = $v - $b[$i];
            $sum += $diff * $diff;
        }
        return sqrt($sum);
    }

    /**
     * Masofa → yaqinlik foizi (0-100)
     */
    public static function distanceToConfidence(float $distance): float
    {
        // face-api.js uchun: 0 = to'liq mos, ~0.6 = farqli
        // 0 → 100%, 0.6 → 0% (linear approximation)
        return max(0.0, min(100.0, (1 - $distance / 0.6) * 100));
    }

    // ─────────────────────── Student photo proxy ────────────────────────

    /**
     * HEMIS rasmini server orqali yuklash (CORS oldini olish).
     * @return array{content: string, mime: string}|null
     */
    public static function fetchStudentPhoto(Student $student): ?array
    {
        if (empty($student->image)) {
            return null;
        }

        $url = $student->image;

        // Agar nisbiy URL bo'lsa, HEMIS base URL qo'shamiz
        if (!str_starts_with($url, 'http')) {
            $base = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz'), '/');
            $url  = $base . '/' . ltrim($url, '/');
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(10)
                ->get($url);

            if (!$response->successful()) {
                Log::warning('[FaceID] Student rasm yuklanmadi', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            return [
                'content' => $response->body(),
                'mime'    => $response->header('Content-Type') ?? 'image/jpeg',
            ];
        } catch (\Throwable $e) {
            Log::warning('[FaceID] Student rasm fetch xatosi', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }
}

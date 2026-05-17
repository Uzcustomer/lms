<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * local_quizexport_get_daily_summary funksiyasini chaqirib, Moodle'da
 * tugatilgan attempt'larning kunlik ro'yxatini oladi. Kunlik monitoring
 * hisoboti shu ma'lumotni hemis_quiz_results va student_grades bilan
 * solishtiradi.
 */
class MoodleSyncMonitorService
{
    public function __construct(
        private readonly ?string $wsUrl = null,
        private readonly ?string $wsToken = null,
    ) {}

    /**
     * @return array{ok:bool, days?:array<int,array{date:string,count:int,attempt_ids:int[]}>, error?:string}
     */
    public function getDailySummary(string $dateFrom, string $dateTo): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'error' => 'Moodle WS not configured'];
        }

        $payload = [
            'wstoken' => $token,
            'wsfunction' => 'local_quizexport_get_daily_summary',
            'moodlewsrestformat' => 'json',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        $timeout = max(10, (int) config('services.moodle.ws_timeout', 30));

        try {
            $resp = Http::asForm()->timeout($timeout)->post($url, $payload);
            $body = $resp->json();

            if (!$resp->successful() || !is_array($body)) {
                return [
                    'ok' => false,
                    'error' => 'HTTP ' . $resp->status() . ': ' . substr((string) $resp->body(), 0, 200),
                ];
            }

            if (isset($body['exception'])) {
                return [
                    'ok' => false,
                    'error' => 'Moodle exception: ' . ($body['message'] ?? $body['errorcode'] ?? 'unknown'),
                ];
            }

            $err = $body['debug_info']['error'] ?? null;
            if ($err) {
                return ['ok' => false, 'error' => $err];
            }

            return [
                'ok' => true,
                'days' => $body['days'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('MoodleSyncMonitorService failed', [
                'error' => $e->getMessage(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

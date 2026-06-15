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
        return $this->call('local_quizexport_get_daily_summary', [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], function ($body) {
            $err = $body['debug_info']['error'] ?? null;
            if ($err) {
                return ['ok' => false, 'error' => $err];
            }
            return ['ok' => true, 'days' => $body['days'] ?? []];
        });
    }

    /**
     * Diagnostic endpoint — eski (ishlovchi) va yangi funksiyani bir vaqtda
     * sinab ko'rib, raw javobni qaytaradi. "Access control exception"
     * kabi xatolarni debug qilish uchun ishlatiladi.
     *
     * @return array{old:array, new:array, ws_url:string, has_token:bool}
     */
    public function diagnose(): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.quiz_ws_token') ?: config('services.moodle.ws_token');

        $old = $this->rawCall('local_quizexport_get_results', [
            'page' => 1,
            'limit' => 1,
        ]);
        $new = $this->rawCall('local_quizexport_get_daily_summary', [
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to'   => now()->format('Y-m-d'),
        ]);

        return [
            'ws_url'    => $url,
            'has_token' => $token !== '',
            'token_preview' => $token !== '' ? substr($token, 0, 6) . '...' . substr($token, -4) : null,
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * Umumiy WS chaqirig'i — handler argumenti orqali javobni qayta ishlaydi.
     */
    private function call(string $wsFunction, array $args, callable $handler): array
    {
        $raw = $this->rawCall($wsFunction, $args);

        if (!$raw['ok']) {
            return ['ok' => false, 'error' => $raw['error']];
        }

        $body = $raw['body'];

        if (is_array($body) && isset($body['exception'])) {
            $parts = [];
            if (!empty($body['errorcode']))  $parts[] = 'errorcode=' . $body['errorcode'];
            if (!empty($body['message']))    $parts[] = $body['message'];
            if (!empty($body['debuginfo']))  $parts[] = 'debug=' . $body['debuginfo'];
            return [
                'ok' => false,
                'error' => 'Moodle exception: ' . implode(' | ', $parts),
            ];
        }

        return $handler($body);
    }

    /**
     * @return array{ok:bool, http_status?:int, body?:mixed, error?:string}
     */
    private function rawCall(string $wsFunction, array $args): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.quiz_ws_token') ?: config('services.moodle.ws_token');
        if ($url === '' || $token === '') {
            return ['ok' => false, 'error' => 'Moodle WS not configured'];
        }

        $payload = array_merge([
            'wstoken' => $token,
            'wsfunction' => $wsFunction,
            'moodlewsrestformat' => 'json',
        ], $args);

        $timeout = max(10, (int) config('services.moodle.ws_timeout', 30));

        try {
            $resp = Http::asForm()->timeout($timeout)->post($url, $payload);
            $body = $resp->json();

            if (!$resp->successful()) {
                return [
                    'ok' => false,
                    'http_status' => $resp->status(),
                    'body' => $body,
                    'error' => 'HTTP ' . $resp->status() . ': ' . substr((string) $resp->body(), 0, 300),
                ];
            }

            return [
                'ok' => true,
                'http_status' => $resp->status(),
                'body' => $body,
            ];
        } catch (\Throwable $e) {
            Log::warning('MoodleSyncMonitorService failed', [
                'error' => $e->getMessage(),
                'ws_function' => $wsFunction,
            ]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

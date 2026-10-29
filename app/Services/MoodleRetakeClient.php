<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Moodle local_otrabotka web-servisi: talaba qarzlari (get_debts) va qayta
 * topshirish vaqtini bron qilish (book_retake).
 *
 * Token (services.moodle.retake_token) faqat so'rov tanasida ketadi —
 * logga, javobga va frontend'ga chiqmaydi. Tarmoq xatosi, HTTP xatosi va
 * Moodle exception javobi bir xil — MoodleRetakeException.
 */
class MoodleRetakeClient
{
    public function getDebts(int $studentHemisId): array
    {
        $body = $this->call('local_otrabotka_get_debts', ['student_hemis_id' => $studentHemisId]);

        return [
            'found' => (bool) ($body['found'] ?? false),
            'debts' => array_values(array_filter($body['debts'] ?? [], 'is_array')),
        ];
    }

    /**
     * @return array{ok: bool, code: string, message: string, deadline: ?string}
     */
    public function bookRetake(array $params): array
    {
        $body = $this->call('local_otrabotka_book_retake', array_filter(
            $params,
            fn ($v) => $v !== null
        ));

        return [
            'ok' => (bool) ($body['ok'] ?? false),
            'code' => (string) ($body['code'] ?? ''),
            'message' => (string) ($body['message'] ?? ''),
            'deadline' => !empty($body['deadline']) ? (string) $body['deadline'] : null,
        ];
    }

    private function call(string $function, array $params): array
    {
        $url = (string) config('services.moodle.ws_url');
        $token = (string) config('services.moodle.retake_token');
        if ($url === '' || $token === '') {
            throw new MoodleRetakeException('Moodle otrabotka sozlanmagan (MOODLE_WS_URL / RETAKE_BOOKING_TOKEN)');
        }

        try {
            $resp = Http::asForm()
                ->timeout((int) config('services.moodle.retake_timeout', 10))
                ->post($url, $params + [
                    'wstoken' => $token,
                    'moodlewsrestformat' => 'json',
                    'wsfunction' => $function,
                ]);
        } catch (\Throwable $e) {
            throw new MoodleRetakeException($function . ': ' . class_basename($e) . ' — ' . $this->scrub($e->getMessage()), 0, null);
        }

        $body = $resp->json();
        if (!$resp->successful() || !is_array($body)) {
            throw new MoodleRetakeException($function . ': HTTP ' . $resp->status());
        }
        if (isset($body['exception']) || isset($body['errorcode'])) {
            throw new MoodleRetakeException(
                $function . ': ' . ($body['errorcode'] ?? 'exception') . ' — ' . $this->scrub((string) ($body['message'] ?? '')),
                0,
                null,
                (string) ($body['errorcode'] ?? '')
            );
        }

        return $body;
    }

    /** Xato matnida token bo'lsa ham logga tushmasin */
    private function scrub(string $text): string
    {
        $token = (string) config('services.moodle.retake_token');

        return $token !== '' ? str_replace($token, '***', $text) : $text;
    }
}

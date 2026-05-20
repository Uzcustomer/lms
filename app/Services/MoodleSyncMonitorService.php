<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Moodle'dan kunlik tugatilgan (finished) imtihon urinishlarini olib,
 * kunlik monitoring hisoboti uchun {sana => {count, attempt_ids}}
 * ko'rinishida qaytaradi. Hisobot bu ma'lumotni LMS'ning
 * hemis_quiz_results va student_grades jadvallari bilan solishtiradi.
 *
 * Eslatma: bu yerda Moodle plaginining local_quizexport_get_results
 * funksiyasi ishlatiladi (allaqachon ro'yxatdan o'tgan, ishlaydigan).
 * U sana filtri qabul qilmaydi — qa.id (since_attempt_id) kursori bilan
 * sahifalanadi. Kunlik guruhlash LMS tarafida bajariladi, shu sababli
 * Moodle plaginiga alohida funksiya yoki upgrade talab qilinmaydi.
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
        try {
            $from = \Carbon\Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
            $to   = \Carbon\Carbon::createFromFormat('Y-m-d', $dateTo)->startOfDay();
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Sana formati noto\'g\'ri'];
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        $fromStr = $from->format('Y-m-d');
        $toStr   = $to->format('Y-m-d');

        // get_results sana filtri qabul qilmaydi — qa.id (since_attempt_id)
        // kursori bilan sahifalanadi. Diapazon boshidan OLDINGI oxirgi sync
        // qilingan attempt'dan boshlaymiz: shu nuqtadan keyingi BARCHA Moodle
        // urinishlari (sync qilingan ham, qilinmagan ham) qaytariladi —
        // shu sababli sync_gap to'g'ri hisoblanadi.
        $sinceAttemptId = 0;
        try {
            $sinceAttemptId = (int) (DB::table('hemis_quiz_results')
                ->whereNotNull('date_finish')
                ->where('date_finish', '<', $fromStr . ' 00:00:00')
                ->max('attempt_id') ?? 0);
        } catch (\Throwable $e) {
            $sinceAttemptId = 0;
        }

        $byDay = []; // 'Y-m-d' => ['count' => int, 'attempt_ids' => int[]]
        $limit = 1000;
        $maxPages = 300; // himoya chegarasi (cheksiz tsikldan saqlanish)
        $reachedRange = false;

        for ($p = 0; $p < $maxPages; $p++) {
            $raw = $this->rawCall('local_quizexport_get_results', [
                'page'             => 1,
                'limit'            => $limit,
                'since_attempt_id' => $sinceAttemptId,
                'mode'             => 'full',
            ]);
            if (!$raw['ok']) {
                return ['ok' => false, 'error' => $raw['error']];
            }
            $body = $raw['body'];

            // Moodle exception (accessexception va h.k.).
            if (is_array($body) && isset($body['exception'])) {
                $parts = [];
                if (!empty($body['errorcode'])) $parts[] = 'errorcode=' . $body['errorcode'];
                if (!empty($body['message']))   $parts[] = $body['message'];
                if (!empty($body['debuginfo'])) $parts[] = 'debug=' . $body['debuginfo'];
                return ['ok' => false, 'error' => 'Moodle exception: ' . implode(' | ', $parts)];
            }

            $records = is_array($body) ? ($body['records'] ?? []) : [];
            if (empty($records)) {
                break;
            }

            $maxIdInBatch  = $sinceAttemptId;
            $allAfterRange = true; // butun paket diapazondan keyinmi
            foreach ($records as $rec) {
                $aid = (int) ($rec['attempt_id'] ?? 0);
                if ($aid > $maxIdInBatch) {
                    $maxIdInBatch = $aid;
                }
                $df = (string) ($rec['date_finish'] ?? '');
                if ($df === '') {
                    continue;
                }
                $day = substr($df, 0, 10); // 'Y-m-d'
                if ($day <= $toStr) {
                    $allAfterRange = false;
                }
                if ($day < $fromStr || $day > $toStr) {
                    continue; // diapazondan tashqarida
                }
                $reachedRange = true;
                if (!isset($byDay[$day])) {
                    $byDay[$day] = ['count' => 0, 'attempt_ids' => []];
                }
                $byDay[$day]['count']++;
                $byDay[$day]['attempt_ids'][] = $aid;
            }

            $hasNext = (bool) ($body['pagination']['has_next'] ?? false);
            // To'xtatish: yangi attempt yo'q; YOKI kursor siljimadi; YOKI
            // diapazonga kirib bo'lib, endi butun paket diapazondan keyinda
            // (qa.id ≈ vaqt — diapazonni o'tib ketdik).
            if (!$hasNext
                || $maxIdInBatch <= $sinceAttemptId
                || ($reachedRange && $allAfterRange)) {
                break;
            }
            $sinceAttemptId = $maxIdInBatch;
        }

        // To'liq diapazon — natijasiz kunlar ham 0 bilan chiqsin.
        $days = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date'        => $key,
                'count'       => $byDay[$key]['count'] ?? 0,
                'attempt_ids' => $byDay[$key]['attempt_ids'] ?? [],
            ];
            $cursor->addDay();
        }

        return ['ok' => true, 'days' => $days];
    }

    /**
     * Diagnostic endpoint — get_results WS funksiyasini ikki rejimda sinab
     * ko'rib, raw javobni qaytaradi. Token / ulanish / "accessexception"
     * kabi xatolarni tekshirish uchun.
     *
     * @return array{ws_url:string, has_token:bool, token_preview:?string, old:array, new:array}
     */
    public function diagnose(): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');

        $old = $this->rawCall('local_quizexport_get_results', [
            'page' => 1,
            'limit' => 1,
            'mode' => 'ids_grade',
        ]);
        $new = $this->rawCall('local_quizexport_get_results', [
            'page' => 1,
            'limit' => 1,
            'mode' => 'full',
        ]);

        return [
            'ws_url'        => $url,
            'has_token'     => $token !== '',
            'token_preview' => $token !== '' ? substr($token, 0, 6) . '...' . substr($token, -4) : null,
            'old' => $old,
            'new' => $new,
        ];
    }

    /**
     * @return array{ok:bool, http_status?:int, body?:mixed, error?:string}
     */
    private function rawCall(string $wsFunction, array $args): array
    {
        $url = $this->wsUrl ?: (string) config('services.moodle.ws_url');
        $token = $this->wsToken ?: (string) config('services.moodle.ws_token');
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

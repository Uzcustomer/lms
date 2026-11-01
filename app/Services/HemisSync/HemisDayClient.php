<?php

namespace App\Services\HemisSync;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * HEMIS REST ro'yxatlari ("Bugungi darslar"): bir kunlik, guruh bo'yicha (schedule-list, attendance-control-list,
 * student-grade-list). HEMIS bir vaqtdagi ko'p so'rovni rad etadi — bir vaqtda ko'pi bilan CONCURRENCY ta so'rov,
 * muvaffaqiyatsizi (timeout, 5xx, 429, success: false) RETRY_DELAYS_MS kutib qayta yuboriladi.
 */
class HemisDayClient
{
    public const CONCURRENCY = 4;

    /** Qayta urinishlar oldidan kutish (ms): 1-urinish muvaffaqiyatsiz bo'lsa 0.5 s, 2-sidan keyin 1.5 s. */
    public const RETRY_DELAYS_MS = [500, 1500];

    public const CONNECT_TIMEOUT = 5;

    public const TIMEOUT = 30;

    /** HEMIS REST manzili: services.hemis.base_url (".../rest" yoki ".../rest/v1/") + /v1/data/{endpoint}. */
    public static function url(string $endpoint): string
    {
        $base = rtrim((string) config('services.hemis.base_url', 'https://student.ttatf.uz/rest/v1/'), '/');

        return (str_ends_with($base, '/v1') ? $base : $base . '/v1') . '/data/' . $endpoint;
    }

    /**
     * Bir nechta GET so'rov: [kalit => parametrlar] → [kalit => Response | null]. null — qayta urinishlardan keyin ham
     * muvaffaqiyatsiz (yoki qayta urinib bo'lmaydigan 4xx).
     */
    public function getMany(string $endpoint, array $requests): array
    {
        $token = (string) config('services.hemis.token');
        $url = self::url($endpoint);
        $out = [];
        $pending = array_map('strval', array_keys($requests));
        $requests = array_combine($pending, array_values($requests));
        foreach (array_merge([0], self::RETRY_DELAYS_MS) as $delay) {
            if (!$pending) {
                break;
            }
            if ($delay > 0) {
                Sleep::for($delay)->milliseconds();
            }
            $retry = [];
            foreach (array_chunk($pending, self::CONCURRENCY) as $batch) {
                $responses = Http::pool(fn ($pool) => array_map(fn ($k) => $pool->as($k)->withoutVerifying()
                    ->connectTimeout(self::CONNECT_TIMEOUT)->timeout(self::TIMEOUT)->withToken($token)->get($url, $requests[$k]), $batch));
                foreach ($batch as $k) {
                    $r = $responses[$k] ?? null;
                    if ($r instanceof Response && $r->successful() && $r->json('success') !== false) {
                        $out[$k] = $r;
                    } elseif (!$r instanceof Response || $r->serverError() || $r->status() === 429 || $r->successful()) {
                        $retry[] = $k;   // timeout/ulanish, 5xx, 429, success: false
                    } else {
                        $out[$k] = null;
                    }
                }
            }
            $pending = $retry;
        }
        foreach ($pending as $k) {
            $out[$k] = null;
        }

        return $out;
    }

    /**
     * Har guruh uchun shu kunning barcha sahifalari (_group + lesson_date_from/to): [guruh => yozuvlar | null].
     * Biror sahifa olinmasa — guruh null (chala ro'yxat ishlatilmaydi).
     */
    public function dayList(string $endpoint, array $groupIds, string $date): array
    {
        $groupIds = array_values(array_unique(array_map('intval', array_filter($groupIds))));
        $base = ['lesson_date_from' => Carbon::parse($date)->startOfDay()->timestamp,
            'lesson_date_to' => Carbon::parse($date)->endOfDay()->timestamp, 'limit' => 200];
        $page = fn (int $g, int $p) => $base + ['_group' => $g, 'page' => $p];
        $out = [];
        $more = [];
        foreach ($this->getMany($endpoint, array_combine(array_map(fn ($g) => $g . '|1', $groupIds), array_map(fn ($g) => $page($g, 1), $groupIds)) ?: []) as $k => $r) {
            $g = (int) explode('|', $k)[0];
            $out[$g] = $r ? (array) $r->json('data.items', []) : null;
            for ($p = 2; $r && $p <= (int) $r->json('data.pagination.pageCount', 1); $p++) {
                $more[$g . '|' . $p] = $page($g, $p);
            }
        }
        // Keyingi sahifalar faqat 1-sahifasi olingan guruhlar uchun.
        foreach ($more ? $this->getMany($endpoint, $more) : [] as $k => $r) {
            $g = (int) explode('|', $k)[0];
            $out[$g] = $r && $out[$g] !== null ? array_merge($out[$g], (array) $r->json('data.items', [])) : null;
        }

        return $out;
    }
}

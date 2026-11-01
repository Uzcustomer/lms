<?php

namespace App\Services\HemisSync;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * HEMIS REST ro'yxatlari ("Bugungi darslar"): bir kunlik, guruh bo'yicha (schedule-list, attendance-control-list,
 * student-grade-list). HEMIS bir vaqtdagi ko'p so'rovni rad etadi — bir vaqtda ko'pi bilan CONCURRENCY ta so'rov
 * (sirpanuvchi oyna: biri tugashi bilan keyingisi — to'da eng sekin so'rovni kutmaydi), muvaffaqiyatsizi (timeout, 5xx,
 * 429, success: false) RETRY_DELAYS_MS kutib qayta yuboriladi. Guruhlar ko'p bo'lsa — kunning umumiy ro'yxati (guruh
 * filtrisiz, sahifalab), sahifalari guruhlar sonidan kam bo'lsa: so'rovlar soni kamayadi.
 */
class HemisDayClient
{
    public const CONCURRENCY = 4;

    /** Kunning umumiy ro'yxati shuncha va undan ko'p guruh so'ralganda sinab ko'riladi. */
    public const BULK_MIN_GROUPS = 6;

    /** Umumiy ro'yxat olinadigan ro'yxatlar (baho — har talaba bo'yicha, kunlik ro'yxati juda katta). */
    public const BULK_ENDPOINTS = ['schedule-list', 'attendance-control-list'];

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
            foreach ($this->sendLimited($url, $token, array_intersect_key($requests, array_flip($pending))) as $k => $r) {
                if ($r instanceof Response && $r->successful() && $r->json('success') !== false) {
                    $out[$k] = $r;
                } elseif (!$r instanceof Response || $r->serverError() || $r->status() === 429 || $r->successful()) {
                    $retry[] = $k;   // timeout/ulanish, 5xx, 429, success: false
                } else {
                    $out[$k] = null;
                }
            }
            $pending = $retry;
        }
        foreach ($pending as $k) {
            $out[$k] = null;
        }

        return $out;
    }

    /** Guzzle ishlovchisi (curl multi) — barcha so'rovlar uchun bitta, Http::pool dagidek. */
    protected function handler(): callable
    {
        return \GuzzleHttp\Utils::chooseHandler();
    }

    /**
     * So'rovlar sirpanuvchi oynada: bir vaqtda ko'pi bilan CONCURRENCY ta, biri tugashi bilan keyingisi yuboriladi (Http::pool
     * dagidek bitta curl multi). Qaytaradi: [kalit => Response | ulanish xatosi (Throwable)] — so'rovlar tartibida.
     */
    private function sendLimited(string $url, string $token, array $requests): array
    {
        $handler = $this->handler();
        $make = function () use ($requests, $handler, $url, $token) {
            foreach ($requests as $k => $q) {
                yield (string) $k => Http::setHandler($handler)->async()->withoutVerifying()->connectTimeout(self::CONNECT_TIMEOUT)
                    ->timeout(self::TIMEOUT)->withToken($token)->get($url, $q);
            }
        };
        $got = [];
        \GuzzleHttp\Promise\Each::ofLimit($make(), self::CONCURRENCY,
            function ($r, $k) use (&$got) {
                $got[(string) $k] = $r;
            },
            function ($e, $k) use (&$got) {
                $got[(string) $k] = $e;
            })->wait();

        return array_replace(array_fill_keys(array_keys($requests), null), $got);
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
        if (count($groupIds) >= max(1, (int) config('services.hemis.day_bulk_min_groups', self::BULK_MIN_GROUPS)) && in_array($endpoint, self::BULK_ENDPOINTS, true)
            && ($bulk = $this->bulkDayList($endpoint, $groupIds, $base)) !== null) {
            return $bulk;
        }
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

    /**
     * Kunning umumiy ro'yxati (guruh filtrisiz): 1-sahifa, so'ng qolgan sahifalar birga; yozuvlar guruh bo'yicha ajratiladi.
     * null — guruhlab so'rash kerak: sahifalar guruhlar sonidan kam emas (arzon emas), biror sahifa olinmadi yoki yozuvda
     * guruh yo'q (chala/aniqlanmaydigan ro'yxat ishlatilmaydi).
     */
    private function bulkDayList(string $endpoint, array $groupIds, array $base): ?array
    {
        $first = $this->getMany($endpoint, ['1' => $base + ['page' => 1]])['1'] ?? null;
        if (!$first) {
            return null;
        }
        $pages = max(1, (int) $first->json('data.pagination.pageCount', 1));
        if ($pages >= count($groupIds)) {
            return null;
        }
        $items = (array) $first->json('data.items', []);
        if ($pages > 1) {
            $rest = range(2, $pages);
            foreach ($this->getMany($endpoint, array_combine($rest, array_map(fn ($p) => $base + ['page' => $p], $rest))) as $r) {
                if (!$r) {
                    return null;
                }
                array_push($items, ...array_values((array) $r->json('data.items', [])));
            }
        }
        $out = array_fill_keys($groupIds, []);
        foreach ($items as $it) {
            $g = (int) ($it['group']['id'] ?? 0);
            if ($g === 0) {
                return null;
            }
            if (isset($out[$g])) {
                $out[$g][] = $it;
            }
        }

        return $out;
    }
}

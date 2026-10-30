<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * AI tekshiruv jarayoni logi: har qadam vaqti bilan keshga yoziladi (ishchi halok bo'lsa ham
 * oxirgi qadam saqlanib qoladi) va vedomost sahifasida «Jarayonni ko'rish» ostida ko'rinadi.
 *
 * Kalit — PDF yo'li: bitta PDF barcha guruhcha yozuvlariga tegishli, holat so'rovi istalgan
 * yozuv orqali kelishi mumkin.
 */
class VedomostAiProgress
{
    private const TTL_HOURS = 48;

    private const MAX_LINES = 300;

    private static ?string $key = null;

    private static float $started = 0.0;

    public static function keyFor(?string $pdfPath): ?string
    {
        return $pdfPath ? 'vedomost-ai-log:' . sha1($pdfPath) : null;
    }

    public static function start(?string $pdfPath, string $message): void
    {
        self::$key = self::keyFor($pdfPath);
        self::$started = microtime(true);
        if (self::$key) {
            Cache::put(self::$key, [], now()->addHours(self::TTL_HOURS));
        }
        self::log($message);
    }

    /** Mavjud logga yozishni davom ettirish (tozalamasdan) — masalan job muvaffaqiyatsiz tugaganda. */
    public static function attach(?string $pdfPath): void
    {
        self::$key = self::keyFor($pdfPath);
    }

    /** @param  string  $level  info | ok | warn | error */
    public static function log(string $message, string $level = 'info'): void
    {
        if (!self::$key) {
            return;
        }
        $lines = Cache::get(self::$key, []);
        $lines[] = [
            't' => now()->format('H:i:s'),
            's' => self::$started ? round(microtime(true) - self::$started, 1) : 0,
            'l' => $level,
            'm' => mb_substr($message, 0, 500),
        ];
        Cache::put(self::$key, array_slice($lines, -self::MAX_LINES), now()->addHours(self::TTL_HOURS));
    }

    /** @return array<int, array{t: string, s: float, l: string, m: string}> */
    public static function lines(?string $pdfPath): array
    {
        $key = self::keyFor($pdfPath);

        return $key ? Cache::get($key, []) : [];
    }

    public static function stop(): void
    {
        self::$key = null;
        self::$started = 0.0;
    }
}

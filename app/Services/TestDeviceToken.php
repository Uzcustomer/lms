<?php

namespace App\Services;

use App\Models\TestKioskDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Sinf kompyuterining maxfiy belgisi (cookie) bilan ishlash.
 *
 * Cookie HttpOnly — sahifadagi skript yoki kengaytma uni o'qiy olmaydi;
 * Laravel uni shifrlaydi; bazada faqat sha256 xeshi turadi.
 */
class TestDeviceToken
{
    public const COOKIE = 'fan_test_device';

    /** Eski belgi shu vaqt ichida ham qabul qilinadi (parallel so'rovlar). */
    public const GRACE_MINUTES = 5;

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function fromRequest(Request $request): string
    {
        return trim((string) $request->cookie(self::COOKIE));
    }

    /** Belgiga mos kompyuter (joriy yoki oldingi belgi bo'yicha). */
    public static function deviceFor(string $token): ?TestKioskDevice
    {
        if ($token === '') {
            return null;
        }

        $hash = self::hash($token);

        return TestKioskDevice::where('token_hash', $hash)
            ->orWhere('previous_token_hash', $hash)
            ->first();
    }

    /**
     * So'rov yuborgan ro'yxatdagi faol kompyuter (joriy belgi yoki yangilangandan
     * keyingi qisqa muddatdagi oldingi belgi). Belgini yangilamaydi va nusxa
     * tekshiruvini qilmaydi — kutish ekrani va holat so'rovlari uchun.
     */
    public static function resolve(Request $request): ?TestKioskDevice
    {
        $token = self::fromRequest($request);
        $device = self::deviceFor($token);
        if (!$device || !$device->isActive()) {
            return null;
        }

        if (!hash_equals((string) $device->token_hash, self::hash($token))) {
            $inGrace = $device->rotated_at && $device->rotated_at->gt(now()->subMinutes(self::GRACE_MINUTES));
            if (!$inGrace) {
                return null;
            }
        }

        return $device;
    }

    /** Kompyuterga yangi belgi beradi; oldingisi qisqa muddat qabul qilinadi. */
    public static function rotate(TestKioskDevice $device, ?string $currentToken = null): string
    {
        $token = Str::random(64);
        $device->forceFill([
            'previous_token_hash' => $currentToken !== null ? self::hash($currentToken) : null,
            'token_hash' => self::hash($token),
            'rotated_at' => now(),
        ])->save();

        return $token;
    }

    public static function cookie(Request $request, string $token): Cookie
    {
        $secure = $request->isSecure() || str_starts_with((string) config('app.url'), 'https://');

        // 5 yil — kompyuter qayta belgilanmaguncha amal qiladi.
        return cookie(self::COOKIE, $token, 60 * 24 * 365 * 5, '/', null, $secure, true, false, 'lax');
    }

    public static function forget(): Cookie
    {
        return cookie()->forget(self::COOKIE);
    }
}

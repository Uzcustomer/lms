<?php

namespace App\Http\Middleware;

use App\Services\TestDeviceToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fan testi faqat ro'yxatdan o'tgan sinf kompyuterlarida ochiladi.
 *
 * .env: FAN_TEST_REQUIRE_DEVICE=true bo'lganda yoqiladi. Boshqa joyda oddiy
 * 404 qaytadi. Belgi test boshlash sahifasi ochilganda yangilanadi; eski
 * belgi yangilanishdan GRACE_MINUTES dan keyin kelsa — u nusxalangan:
 * kompyuter bekor qilinadi va logga yoziladi.
 */
class RegisteredTestDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('services.fan_test.require_device')) {
            return $next($request);
        }

        $token = TestDeviceToken::fromRequest($request);
        $device = TestDeviceToken::deviceFor($token);

        if (!$device || !$device->isActive()) {
            Log::info('[FanTest] Ro\'yxatdan o\'tmagan kompyuterdan so\'rov', [
                'url' => $request->path(),
                'device_id' => $device?->id,
                'has_cookie' => $token !== '',
            ]);
            abort(404);
        }

        $hash = TestDeviceToken::hash($token);
        $isCurrent = hash_equals($device->token_hash, $hash);

        if (!$isCurrent) {
            $inGrace = $device->rotated_at
                && $device->rotated_at->gt(now()->subMinutes(TestDeviceToken::GRACE_MINUTES));

            if (!$inGrace) {
                // Belgi ikki joyda ishlatilgan: biri yangisini olgan, ikkinchisi
                // eskisi bilan kelgan. Qaysi biri asl ekanini bilib bo'lmaydi —
                // ikkalasi ham to'xtatiladi, admin qayta belgilaydi.
                $device->forceFill([
                    'revoked_at' => now(),
                    'revoked_reason' => 'Belgi nusxasi aniqlandi: eski belgi qayta ishlatildi',
                ])->save();

                Log::warning('[FanTest] Kompyuter belgisi nusxalangan — bekor qilindi', [
                    'device_id' => $device->id,
                    'device_name' => $device->name,
                    'url' => $request->path(),
                ]);
                abort(404);
            }
        }

        if (!$device->last_used_at || $device->last_used_at->lt(now()->subMinute())) {
            $device->forceFill(['last_used_at' => now()])->save();
        }

        $response = $next($request);

        // Belgi faqat test boshlash sahifasida yangilanadi: test davomidagi
        // parallel so'rovlar (rasm, yuz kuzatuvi) eski/yangi belgi chalkashligiga
        // tushmasin.
        $shouldRotate = $isCurrent
            && $request->isMethod('GET')
            && $request->routeIs('kiosk.fan-testi.show')
            && (!$device->rotated_at || $device->rotated_at->lt(now()->subMinute()));

        if ($shouldRotate) {
            $newToken = TestDeviceToken::rotate($device, $token);
            $response->headers->setCookie(TestDeviceToken::cookie($request, $newToken));
        }

        return $response;
    }
}

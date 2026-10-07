<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fan testi sahifalarini faqat universitet tarmog'idan ochadi.
 *
 * Ruxsat etilgan manzillar .env dagi FAN_TEST_ALLOWED_NETWORKS da vergul
 * bilan beriladi (IP yoki CIDR, masalan "213.230.64.10,10.0.0.0/8").
 * Ro'yxat bo'sh bo'lsa cheklov o'chiq — sozlanmagan serverda testlar
 * to'xtab qolmasin.
 *
 * Tarmoqdan tashqarida oddiy 404 qaytadi: sahifa borligi ham, sababi ham
 * aytilmaydi.
 */
class UniversityNetworkOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $networks = config('services.fan_test.allowed_networks', []);

        if (empty($networks) || IpUtils::checkIp((string) $request->ip(), $networks)) {
            return $next($request);
        }

        abort(404);
    }
}

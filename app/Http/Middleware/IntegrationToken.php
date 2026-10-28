<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integratsiya API (/api/integration/*) uchun token tekshiruvi.
 *
 * Token faqat "Authorization: Bearer <token>" sarlavhasidan olinadi — URL
 * parametrida qabul qilinmaydi, aks holda server loglariga yozilib qoladi.
 * Ruxsat etilgan tokenlar: config('services.integration.tokens'), vergul bilan.
 * 32 belgidan qisqa tokenlar e'tiborga olinmaydi (taxmin qilib topilmasin).
 */
class IntegrationToken
{
    private const MIN_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->bearerToken();

        if ($provided === '' || !$this->isAllowed($provided)) {
            return response()->json(['success' => false, 'error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function isAllowed(string $provided): bool
    {
        $tokens = array_filter(
            array_map('trim', explode(',', (string) config('services.integration.tokens'))),
            fn ($t) => strlen($t) >= self::MIN_LENGTH
        );

        foreach ($tokens as $token) {
            if (hash_equals($token, $provided)) {
                return true;
            }
        }

        return false;
    }
}

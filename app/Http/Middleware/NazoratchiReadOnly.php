<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nazoratchi roli uchun yozish operatsiyalarini bloklaydi (faqat o'qish).
 *
 * Faol rol "nazoratchi" bo'lsa, GET/HEAD/OPTIONS dan tashqari barcha
 * so'rovlar 403 bilan rad etiladi.
 */
class NazoratchiReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (is_active_nazoratchi() && !in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Nazoratchi faqat ko\'rish huquqiga ega.',
                ], 403);
            }
            abort(403, 'Nazoratchi faqat ko\'rish huquqiga ega.');
        }

        return $next($request);
    }
}

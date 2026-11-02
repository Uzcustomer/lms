<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * So'rov xotirasi monitoringi: memory_get_peak_usage chegaradan (sukut 100 MB, config app.memory_warn_mb) oshsa — logga ogohlantirish
 * (marshrut, URL, foydalanuvchi, faol rol, javob hajmi). Javob yuborilgach (terminate) yoziladi — foydalanuvchini kutdirmaydi.
 */
class LogPeakMemory
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        $limitMb = (int) config('app.memory_warn_mb', 100);
        $peak = memory_get_peak_usage(true);
        if ($limitMb <= 0 || $peak <= $limitMb * 1024 * 1024) {
            return;
        }
        try {
            $user = $request->user() ?? auth()->guard('teacher')->user() ?? auth()->guard('web')->user();
            $content = method_exists($response, 'getContent') ? $response->getContent() : null;
            Log::warning('Yuqori xotira: ' . round($peak / 1048576) . ' MB', [
                'route' => optional($request->route())->getName(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'user_id' => $user?->getKey(),
                'active_role' => $request->hasSession() ? $request->session()->get('active_role') : null,
                'status' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'response_kb' => is_string($content) ? (int) round(strlen($content) / 1024) : null,
                'peak_mb' => round($peak / 1048576, 1),
                'limit' => ini_get('memory_limit'),
            ]);
        } catch (\Throwable $e) {
            // Monitoring so'rovni yiqitmasin.
        }
    }
}

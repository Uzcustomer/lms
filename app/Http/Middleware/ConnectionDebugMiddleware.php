<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ConnectionDebugMiddleware
{
    /**
     * Har bir requestda server ulanish holatini tekshirib, logga yozadi.
     * Faqat muammo bo'lganda yoki sekin requestlarda yozadi (ortiqcha log bo'lmasligi uchun).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // DB ping faqat login route'larida — har requestda emas (timeout sabab bo'lishi mumkin)
        $isLoginRoute = str_contains($request->path(), 'login');
        if ($isLoginRoute) {
            $dbStatus = $this->checkDatabaseConnection();
            if (!$dbStatus['connected']) {
                try {
                    Log::channel('connection_debug')->error('DATABASE ULANISH YO\'QOLDI (login)', [
                        'url' => $request->fullUrl(),
                        'ip' => $request->ip(),
                        'db_error' => $dbStatus['error'] ?? 'unknown',
                    ]);
                } catch (\Throwable $e) {
                    // Log yozish xatosi asosiy javobni buzmasligi kerak
                }
            }
        }

        // Requestni davom ettiramiz
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            try {
                Log::channel('connection_debug')->error('REQUEST XATOLIK BILAN TUGADI', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'duration_ms' => $duration,
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            } catch (\Throwable $logEx) {
                // Log yozish xatosi asosiy xatoni yutib yubormasligi kerak
            }
            throw $e;
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        try {
            // Sekin requestlarni log qilish (3 sekunddan oshsa)
            if ($duration > 3000) {
                Log::channel('connection_debug')->warning('SEKIN REQUEST ANIQLANDI', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'duration_ms' => $duration,
                    'status_code' => $response->getStatusCode(),
                ]);
            }

            // Server xatolik (5xx) bo'lsa log qilish
            if ($response->getStatusCode() >= 500) {
                Log::channel('connection_debug')->error('SERVER XATOLIK (5xx)', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'duration_ms' => $duration,
                ]);
            }

            // 419 (CSRF/Session expired) bo'lsa log qilish
            if ($response->getStatusCode() === 419) {
                Log::channel('connection_debug')->warning('SESSION/CSRF MUDDATI TUGADI (419)', [
                    'url' => $request->fullUrl(),
                    'session_driver' => config('session.driver'),
                    'duration_ms' => $duration,
                ]);
            }
        } catch (\Throwable $e) {
            // Log yozish xatosi asosiy javobni buzmasligi kerak
        }

        return $response;
    }

    /**
     * Database ulanishini tekshirish va ping qilish.
     */
    private function checkDatabaseConnection(): array
    {
        try {
            $startDb = microtime(true);
            DB::connection()->getPdo();
            $ping = DB::select('SELECT 1');
            $dbDuration = round((microtime(true) - $startDb) * 1000, 2);

            return [
                'connected' => true,
                'ping_ms' => $dbDuration,
                'driver' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'driver' => config('database.default'),
                'host' => config('database.connections.' . config('database.default') . '.host'),
            ];
        }
    }
}

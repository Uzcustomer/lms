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
        $debugInfo = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toDateTimeString(),
        ];

        // 1. Database ulanishini tekshirish
        $dbStatus = $this->checkDatabaseConnection();
        $debugInfo['database'] = $dbStatus;

        // Agar DB ulanish muammo bo'lsa — darhol log yozamiz
        if (!$dbStatus['connected']) {
            try {
                Log::channel('connection_debug')->error('DATABASE ULANISH YO\'QOLDI', $debugInfo);
            } catch (\Throwable $e) {
                // Log yozish xatosi asosiy javobni buzmasligi kerak
            }
        }

        // Requestni davom ettiramiz
        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $debugInfo['duration_ms'] = $duration;
            $debugInfo['error'] = [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ];

            try {
                Log::channel('connection_debug')->error('REQUEST XATOLIK BILAN TUGADI', $debugInfo);
            } catch (\Throwable $logEx) {
                // Log yozish xatosi asosiy xatoni yutib yubormasligi kerak
            }
            throw $e;
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $debugInfo['duration_ms'] = $duration;
        $debugInfo['status_code'] = $response->getStatusCode();

        // 2. Sekin requestlarni log qilish (3 sekunddan oshsa)
        // try-catch: log yozish xatosi asosiy javobni buzmaydi
        try {
            if ($duration > 3000) {
                $debugInfo['query_count'] = count(DB::getQueryLog());
                Log::channel('connection_debug')->warning('SEKIN REQUEST ANIQLANDI', $debugInfo);
            }

            // 3. Server xatolik (5xx) bo'lsa log qilish
            if ($response->getStatusCode() >= 500) {
                Log::channel('connection_debug')->error('SERVER XATOLIK (5xx)', $debugInfo);
            }

            // 4. 419 (CSRF/Session expired) bo'lsa log qilish — bu ko'pincha "ulanish uzildi" deb ko'rinadi
            if ($response->getStatusCode() === 419) {
                $debugInfo['session_id'] = session()->getId();
                $debugInfo['session_driver'] = config('session.driver');
                Log::channel('connection_debug')->warning('SESSION/CSRF MUDDATI TUGADI (419)', $debugInfo);
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

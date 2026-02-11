<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $teacher = Auth::guard('teacher')->user();

        if (!$teacher) {
            return $next($request);
        }

        // Impersonatsiya rejimida majburiy tekshiruvlar o'tkazilmaydi
        if (session('impersonating')) {
            return $next($request);
        }

        $allowedRoutes = [
            'teacher.force-change-password*',
            'teacher.complete-profile*',
            'teacher.verify-telegram*',
            'teacher.logout',
        ];

        $isAllowed = false;
        foreach ($allowedRoutes as $route) {
            if ($request->routeIs($route)) {
                $isAllowed = true;
                break;
            }
        }

        if (!$isAllowed) {
            // 1. Parolni o'zgartirish majburiy
            if ($teacher->must_change_password) {
                return redirect()->route('teacher.force-change-password');
            }

            // 2. Telefon raqami majburiy
            if (!$teacher->isProfileComplete()) {
                return redirect()->route('teacher.complete-profile');
            }

            // 3. Telegram tasdiqlash — 7 kun muhlat o'tgandan keyin block
            if ($teacher->isTelegramDeadlinePassed()) {
                return redirect()->route('teacher.complete-profile');
            }
        }

        return $next($request);
    }
}

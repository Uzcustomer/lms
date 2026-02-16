<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMultiGuardAuth
{
    /**
     * Admin sahifalariga teacher va web guardlardan kirish.
     * Web guard (admin) birinchi tekshiriladi — agar admin login bo'lsa, u ishlatiladi.
     * Agar web guard yo'q bo'lsa, teacher guard tekshiriladi (registrator_ofisi va boshqalar).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Web guard (admin) birinchi — admin login bo'lsa, teacher guard'ni e'tiborsiz qoldirish
        if (Auth::guard('web')->check()) {
            Auth::shouldUse('web');
            return $next($request);
        }

        // Teacher guard orqali kirilgan bo'lsa (registrator_ofisi va boshqa rollar)
        // Rol tekshiruvini route middleware (RoleMiddleware) bajaradi
        $teacher = Auth::guard('teacher')->user();
        if ($teacher) {
            Auth::shouldUse('teacher');
            return $next($request);
        }

        // Autentifikatsiya yo'q - login sahifasiga
        return redirect()->route('admin.login');
    }
}

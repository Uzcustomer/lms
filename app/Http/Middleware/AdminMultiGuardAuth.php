<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $url = $request->path();
        $webCheck = Auth::guard('web')->check();
        $teacherCheck = Auth::guard('teacher')->check();
        $studentCheck = Auth::guard('student')->check();

        Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: Guard holati", [
            'web' => $webCheck ? ('✅ id=' . Auth::guard('web')->id()) : '❌',
            'teacher' => $teacherCheck ? ('✅ id=' . Auth::guard('teacher')->id()) : '❌',
            'student' => $studentCheck ? ('✅ id=' . Auth::guard('student')->id()) : '❌',
            'session.impersonating' => session('impersonating'),
            'session.impersonated_name' => session('impersonated_name'),
            'session.active_role' => session('active_role'),
            'session_id' => session()->getId(),
        ]);

        // Web guard (admin) birinchi — admin login bo'lsa, teacher guard'ni e'tiborsiz qoldirish
        if ($webCheck) {
            Auth::shouldUse('web');

            // Agar admin login bo'lsa, lekin eski impersonatsiya session ma'lumotlari qolgan bo'lsa — tozalash
            if (session('impersonating')) {
                Log::channel('daily')->warning("🛡️ MIDDLEWARE [{$url}]: ⚠️ Web guard aktiv LEKIN impersonating=true! Stale data tozalanmoqda", [
                    'impersonated_name' => session('impersonated_name'),
                    'impersonator_id' => session('impersonator_id'),
                ]);

                // Teacher/student guardlarni tozalash (agar hali aktiv bo'lsa)
                foreach (['teacher', 'student'] as $guard) {
                    if (Auth::guard($guard)->check()) {
                        Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: Stale {$guard} guard logout qilinmoqda");
                        Auth::guard($guard)->logout();
                    }
                }
                session()->forget([
                    'impersonating',
                    'impersonator_id',
                    'impersonator_guard',
                    'impersonated_name',
                    'impersonator_active_role',
                ]);

                Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: ✅ Stale impersonation data tozalandi", [
                    'impersonating_after' => session('impersonating'),
                ]);
            }

            Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: → WEB guard ishlatilmoqda, user_id=" . Auth::guard('web')->id());
            return $next($request);
        }

        // Teacher guard orqali kirilgan bo'lsa (registrator_ofisi va boshqa rollar)
        // Rol tekshiruvini route middleware (RoleMiddleware) bajaradi
        $teacher = Auth::guard('teacher')->user();
        if ($teacher) {
            Auth::shouldUse('teacher');
            Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: → TEACHER guard ishlatilmoqda, teacher_id={$teacher->id}, name={$teacher->full_name}");
            return $next($request);
        }

        // Autentifikatsiya yo'q - login sahifasiga
        Log::channel('daily')->warning("🛡️ MIDDLEWARE [{$url}]: ❌ Hech qanday guard aktiv emas, admin.login ga redirect");
        return redirect()->route('admin.login');
    }
}

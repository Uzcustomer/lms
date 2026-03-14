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
     * Guard session kalitlari — stale guard ma'lumotlarini tozalash uchun.
     */
    private const GUARD_SESSION_KEYS = [
        'login_teacher_59ba36addc2b2f9401580f014c7f58ea4e30989d',
        'login_student_59ba36addc2b2f9401580f014c7f58ea4e30989d',
    ];

    private const IMPERSONATION_SESSION_KEYS = [
        'impersonating',
        'impersonator_id',
        'impersonator_guard',
        'impersonated_name',
        'impersonator_active_role',
    ];

    /**
     * Admin sahifalariga teacher va web guardlardan kirish.
     * Web guard (admin) birinchi tekshiriladi — agar admin login bo'lsa, u ishlatiladi.
     * Agar web guard yo'q bo'lsa, teacher guard tekshiriladi (registrator_ofisi va boshqalar).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $t0 = microtime(true);
        $url = $request->path();

        $webCheck = Auth::guard('web')->check();
        $teacherCheck = Auth::guard('teacher')->check();
        $studentCheck = Auth::guard('student')->check();

        $guardCheckMs = round((microtime(true) - $t0) * 1000);
        // Faqat sekin guard check'larni log qilish (100ms dan oshsa — DB/session muammo)
        if ($guardCheckMs > 100) {
            Log::channel('daily')->warning("[MIDDLEWARE TIMING] guard_check sekin: {$guardCheckMs}ms", ['url' => $url]);
        }

        // Web guard (admin) birinchi — admin login bo'lsa
        if ($webCheck) {
            Auth::shouldUse('web');

            if (session('impersonating') || $teacherCheck || $studentCheck) {
                foreach (['teacher', 'student'] as $guard) {
                    if (Auth::guard($guard)->check()) {
                        Auth::guard($guard)->logout();
                    }
                }
                foreach (self::GUARD_SESSION_KEYS as $key) {
                    session()->forget($key);
                }
                session()->forget(self::IMPERSONATION_SESSION_KEYS);
            }

            return $next($request);
        }

        // Teacher guard orqali kirilgan bo'lsa
        $teacher = Auth::guard('teacher')->user();
        if ($teacher) {
            Auth::shouldUse('teacher');
            return $next($request);
        }

        // Autentifikatsiya yo'q - login sahifasiga
        return redirect()->route('admin.login');
    }
}

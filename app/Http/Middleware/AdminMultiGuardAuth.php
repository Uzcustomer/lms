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

        // Web guard (admin) birinchi — admin login bo'lsa
        if ($webCheck) {
            Auth::shouldUse('web');

            // Agar admin login bo'lsa, lekin eski teacher/student yoki impersonation
            // session ma'lumotlari qolgan bo'lsa — HAMMASI'ni tozalash
            if (session('impersonating') || $teacherCheck || $studentCheck) {
                Log::channel('daily')->warning("🛡️ MIDDLEWARE [{$url}]: ⚠️ Web guard aktiv LEKIN stale data bor! Tozalanmoqda", [
                    'impersonating' => session('impersonating'),
                    'impersonated_name' => session('impersonated_name'),
                    'teacherCheck' => $teacherCheck,
                    'studentCheck' => $studentCheck,
                ]);

                // Auth object'larni logout qilish
                foreach (['teacher', 'student'] as $guard) {
                    if (Auth::guard($guard)->check()) {
                        Auth::guard($guard)->logout();
                    }
                }

                // Qo'lda session kalitlarini o'chirish
                foreach (self::GUARD_SESSION_KEYS as $key) {
                    session()->forget($key);
                }
                session()->forget(self::IMPERSONATION_SESSION_KEYS);

                Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: ✅ Stale data tozalandi");
            }

            Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: → WEB guard ishlatilmoqda, user_id=" . Auth::guard('web')->id());
            return $next($request);
        }

        // Teacher guard orqali kirilgan bo'lsa (registrator_ofisi va boshqa rollar)
        // LEKIN: agar impersonation rejimida bo'lsa, teacher'ni admin sahifaga kiritmaslik
        $teacher = Auth::guard('teacher')->user();
        if ($teacher) {
            // Agar impersonation rejimida bo'lsa — bu teacher admin sahifaga kirolmaydi
            if (session('impersonating')) {
                Log::channel('daily')->warning("🛡️ MIDDLEWARE [{$url}]: ⚠️ Teacher guard + impersonation! Admin sahifaga kiritilmaydi, login'ga redirect");
                return redirect()->route('admin.login');
            }

            Auth::shouldUse('teacher');
            Log::channel('daily')->info("🛡️ MIDDLEWARE [{$url}]: → TEACHER guard ishlatilmoqda, teacher_id={$teacher->id}, name={$teacher->full_name}");
            return $next($request);
        }

        // Autentifikatsiya yo'q - login sahifasiga
        Log::channel('daily')->warning("🛡️ MIDDLEWARE [{$url}]: ❌ Hech qanday guard aktiv emas, admin.login ga redirect");
        return redirect()->route('admin.login');
    }
}

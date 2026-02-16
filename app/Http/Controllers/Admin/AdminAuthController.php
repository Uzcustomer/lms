<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    /**
     * Guard session kalitlari — eski guard ma'lumotlarini qo'lda tozalash uchun.
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

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        Log::channel('daily')->info("🔑 ADMIN LOGIN: ========== Boshlanmoqda ==========", [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        // Guard holatini tekshirish
        Log::channel('daily')->info("🔑 ADMIN LOGIN: Login oldidan guard holati", [
            'web_check' => Auth::guard('web')->check(),
            'web_id' => Auth::guard('web')->id(),
            'teacher_check' => Auth::guard('teacher')->check(),
            'teacher_id' => Auth::guard('teacher')->id(),
            'student_check' => Auth::guard('student')->check(),
            'student_id' => Auth::guard('student')->id(),
            'session.impersonating' => session('impersonating'),
            'session.impersonated_name' => session('impersonated_name'),
            'session.impersonator_id' => session('impersonator_id'),
            'session.active_role' => session('active_role'),
            'session_id' => session()->getId(),
            'session_has_teacher_key' => session()->has(self::GUARD_SESSION_KEYS[0]),
            'session_has_student_key' => session()->has(self::GUARD_SESSION_KEYS[1]),
            'all_session_keys' => array_keys(session()->all()),
        ]);

        // ============================================================
        // AGRESSIV TOZALASH: Eski impersonatsiya yoki teacher/student
        // guardlarini to'liq tozalash — Auth::logout() + qo'lda session
        // kalitlarini o'chirish.
        // ============================================================

        // 1. Auth guard objectlarini logout qilish
        foreach (['teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->warning("🔑 ADMIN LOGIN: ⚠️ {$guard} guard hali aktiv! Logout qilinmoqda, id=" . Auth::guard($guard)->id());
                Auth::guard($guard)->logout();
            }
        }

        // 2. Guard session kalitlarini QO'LDA o'chirish
        //    (Auth::logout() ba'zan session key'ni to'liq tozalamaydi)
        foreach (self::GUARD_SESSION_KEYS as $key) {
            $request->session()->forget($key);
        }

        // 3. Impersonation session kalitlarini tozalash
        $hadImpersonationData = session('impersonating');
        $request->session()->forget(self::IMPERSONATION_SESSION_KEYS);

        if ($hadImpersonationData) {
            Log::channel('daily')->warning("🔑 ADMIN LOGIN: ⚠️ Eski impersonation session data tozalandi");
        }

        Log::channel('daily')->info("🔑 ADMIN LOGIN: Tozalash tugadi, Auth::attempt qilinmoqda", [
            'web_check_after' => Auth::guard('web')->check(),
            'teacher_check_after' => Auth::guard('teacher')->check(),
            'session_has_teacher_key_after' => session()->has(self::GUARD_SESSION_KEYS[0]),
            'session.impersonating_after' => session('impersonating'),
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            Log::channel('daily')->info("🔑 ADMIN LOGIN: ✅ Auth::attempt muvaffaqiyatli", [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'roles' => $user->getRoleNames()->toArray(),
            ]);

            $staffRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());
            if ($user->hasRole($staffRoleValues)) {
                $request->session()->regenerate();

                // Session regenerate dan keyin yana bir marta tekshirish:
                // teacher/student kalitlari qayta tiklanmaganligiga ishonch hosil qilish
                foreach (self::GUARD_SESSION_KEYS as $key) {
                    if ($request->session()->has($key)) {
                        Log::channel('daily')->warning("🔑 ADMIN LOGIN: ⚠️ Session regenerate dan keyin {$key} hali bor! O'chirilmoqda");
                        $request->session()->forget($key);
                    }
                }

                Log::channel('daily')->info("🔑 ADMIN LOGIN: ✅ YAKUNIY holat", [
                    'web_check' => Auth::guard('web')->check(),
                    'web_id' => Auth::guard('web')->id(),
                    'teacher_check' => Auth::guard('teacher')->check(),
                    'student_check' => Auth::guard('student')->check(),
                    'session.impersonating' => session('impersonating'),
                    'session.active_role' => session('active_role'),
                    'new_session_id' => session()->getId(),
                    'session_has_teacher_key' => session()->has(self::GUARD_SESSION_KEYS[0]),
                    'all_session_keys' => array_keys(session()->all()),
                ]);

                ActivityLogService::logLogin('web');
                Log::channel('daily')->info("🔑 ADMIN LOGIN: ========== TUGADI, admin.dashboard ga redirect ==========");

                // redirect()->intended() O'RNIGA to'g'ridan-to'g'ri redirect
                // (intended ba'zan eski teacher URL'ni qaytarishi mumkin)
                return redirect()->route('admin.dashboard');
            } else {
                Log::channel('daily')->warning("🔑 ADMIN LOGIN: ❌ Staff roli yo'q, logout qilinmoqda", [
                    'user_roles' => $user->getRoleNames()->toArray(),
                    'required_roles' => $staffRoleValues,
                ]);
                Auth::logout();
                return back()->withErrors([
                    'email' => 'You do not have permission to access the admin area.',
                ]);
            }
        }

        Log::channel('daily')->warning("🔑 ADMIN LOGIN: ❌ Auth::attempt muvaffaqiyatsiz (noto'g'ri login/parol)", [
            'email' => $credentials['email'],
        ]);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $isTeacher = Auth::guard('teacher')->check();

        Log::channel('daily')->info("🚪 ADMIN LOGOUT: Boshlanmoqda", [
            'is_teacher' => $isTeacher,
            'web_check' => Auth::guard('web')->check(),
            'web_id' => Auth::guard('web')->id(),
            'teacher_check' => Auth::guard('teacher')->check(),
            'teacher_id' => Auth::guard('teacher')->id(),
            'session.impersonating' => session('impersonating'),
        ]);

        ActivityLogService::logLogout($isTeacher ? 'teacher' : 'web');

        // Barcha guardlardan logout qilish
        if ($isTeacher) {
            Auth::guard('teacher')->logout();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::channel('daily')->info("🚪 ADMIN LOGOUT: Tugadi, redirect qilinmoqda");

        return redirect($isTeacher ? route('teacher.login') : '/');
    }
}

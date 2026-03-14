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
        $t0 = microtime(true);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        Log::channel('daily')->info("🔑 ADMIN LOGIN: ========== Boshlanmoqda ==========", [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        // Guard tozalash
        $t1 = microtime(true);
        foreach (['teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }
        foreach (self::GUARD_SESSION_KEYS as $key) {
            $request->session()->forget($key);
        }
        $request->session()->forget(self::IMPERSONATION_SESSION_KEYS);
        Log::channel('daily')->info("[ADMIN LOGIN TIMING] guard_cleanup: " . round((microtime(true) - $t1) * 1000) . "ms");

        $t2 = microtime(true);
        if (Auth::attempt($credentials)) {
            Log::channel('daily')->info("[ADMIN LOGIN TIMING] auth_attempt(ok): " . round((microtime(true) - $t2) * 1000) . "ms");
            $user = Auth::user();

            $t3 = microtime(true);
            $staffRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());
            $hasRole = $user->hasRole($staffRoleValues);
            Log::channel('daily')->info("[ADMIN LOGIN TIMING] role_check: " . round((microtime(true) - $t3) * 1000) . "ms", [
                'user_id' => $user->id,
                'has_staff_role' => $hasRole,
            ]);

            if ($hasRole) {
                $t4 = microtime(true);
                $request->session()->regenerate();
                Log::channel('daily')->info("[ADMIN LOGIN TIMING] session_regenerate: " . round((microtime(true) - $t4) * 1000) . "ms");

                foreach (self::GUARD_SESSION_KEYS as $key) {
                    $request->session()->forget($key);
                }

                $t5 = microtime(true);
                ActivityLogService::logLogin('web');
                Log::channel('daily')->info("[ADMIN LOGIN TIMING] activity_log: " . round((microtime(true) - $t5) * 1000) . "ms");

                Log::channel('daily')->info("[ADMIN LOGIN TIMING] TOTAL: " . round((microtime(true) - $t0) * 1000) . "ms");

                return redirect()->route('admin.dashboard');
            } else {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'You do not have permission to access the admin area.',
                ]);
            }
        }
        Log::channel('daily')->info("[ADMIN LOGIN TIMING] auth_attempt(fail): " . round((microtime(true) - $t2) * 1000) . "ms");

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

        // Barcha guardlardan logout qilish (barchasi tozalanadi)
        Auth::guard('student')->logout();
        Auth::guard('teacher')->logout();
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::channel('daily')->info("🚪 ADMIN LOGOUT: Tugadi, redirect qilinmoqda");

        return redirect($isTeacher ? route('teacher.login') : '/');
    }
}

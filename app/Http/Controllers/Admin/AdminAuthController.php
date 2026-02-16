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
            'all_session_keys' => array_keys(session()->all()),
        ]);

        // Eski impersonatsiya yoki teacher/student guardlarini tozalash
        // (oldingi impersonatsiya tugallanmagan bo'lsa)
        foreach (['teacher', 'student'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Log::channel('daily')->warning("🔑 ADMIN LOGIN: ⚠️ {$guard} guard hali aktiv! Logout qilinmoqda, id=" . Auth::guard($guard)->id());
                Auth::guard($guard)->logout();
            }
        }

        $impersonationKeys = [
            'impersonating',
            'impersonator_id',
            'impersonator_guard',
            'impersonated_name',
            'impersonator_active_role',
        ];
        $hadImpersonationData = session('impersonating');
        $request->session()->forget($impersonationKeys);

        if ($hadImpersonationData) {
            Log::channel('daily')->warning("🔑 ADMIN LOGIN: ⚠️ Eski impersonation session data tozalandi");
        }

        Log::channel('daily')->info("🔑 ADMIN LOGIN: Tozalash tugadi, Auth::attempt qilinmoqda", [
            'web_check_after_cleanup' => Auth::guard('web')->check(),
            'teacher_check_after_cleanup' => Auth::guard('teacher')->check(),
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

                Log::channel('daily')->info("🔑 ADMIN LOGIN: ✅ Session regenerate tugadi, YAKUNIY holat", [
                    'web_check' => Auth::guard('web')->check(),
                    'web_id' => Auth::guard('web')->id(),
                    'teacher_check' => Auth::guard('teacher')->check(),
                    'student_check' => Auth::guard('student')->check(),
                    'session.impersonating' => session('impersonating'),
                    'session.active_role' => session('active_role'),
                    'new_session_id' => session()->getId(),
                    'all_session_keys' => array_keys(session()->all()),
                ]);

                ActivityLogService::logLogin('web');
                Log::channel('daily')->info("🔑 ADMIN LOGIN: ========== TUGADI, admin.dashboard ga redirect ==========");
                return redirect()->intended(route('admin.dashboard'));
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

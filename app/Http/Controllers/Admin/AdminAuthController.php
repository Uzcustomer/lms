<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAuthController extends Controller
{
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $staffRoleValues = array_map(fn ($r) => $r->value, ProjectRole::staffRoles());
            if ($user->hasRole($staffRoleValues)) {
                $request->session()->regenerate();

                // Eng yuqori huquqli rolni default active_role sifatida o'rnatish
                // ProjectRole enum tartibi = prioritet tartibi (superadmin > admin > ... > oqituvchi)
                $userRoles = $user->getRoleNames()->toArray();
                $defaultRole = collect($staffRoleValues)
                    ->first(fn ($role) => in_array($role, $userRoles)) ?? ($userRoles[0] ?? '');
                session(['active_role' => $defaultRole]);

                ActivityLogService::logLogin('web');
                return redirect()->intended(route('admin.dashboard'));
            } else {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'You do not have permission to access the admin area.',
                ]);
            }
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $isTeacher = Auth::guard('teacher')->check();

        ActivityLogService::logLogout($isTeacher ? 'teacher' : 'web');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($isTeacher ? route('teacher.login') : '/');
    }
}

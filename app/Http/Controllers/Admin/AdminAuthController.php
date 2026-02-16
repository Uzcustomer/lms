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

        // Barcha guardlardan logout qilish
        if ($isTeacher) {
            Auth::guard('teacher')->logout();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($isTeacher ? route('teacher.login') : '/');
    }
}

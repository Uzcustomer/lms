<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules;

class TeacherAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('teacher')->check()) {
            return redirect()->intended(route('teacher.dashboard'));
        } else {
            return view('teacher.login');
        }
    }

    public function login(Request $request)
    {
        if (Auth::guard('teacher')->check()) {
            return redirect()->intended(route('teacher.dashboard'));
        }

        $credentials = $request->validate([
            'login' => ['required'],
            'password' => ['required'],
        ]);

        if (Auth::guard('teacher')->attempt($credentials)) {
            $request->session()->regenerate();
            return redirect()->intended(route('teacher.dashboard'));
        }

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('teacher')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function editCredentials()
    {
        $teacher = auth()->user();
        return view('teacher.update-login', compact('teacher'));
    }

    public function update_credentials(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string', 'max:255', 'unique:teachers,login,' . auth()->id()],
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $teacher = auth()->user();

        if (!Hash::check($request->current_password, $teacher->password)) {
            return back()->withErrors(['current_password' => 'Joriy parol noto\'g\'ri.']);
        }

        $teacher->login = $request->login;
        $teacher->password = Hash::make($request->password);
        $teacher->save();

        return back()->with('success', 'Login va parol muvaffaqiyatli yangilandi.');
    }
}

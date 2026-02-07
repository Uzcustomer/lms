<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
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

            $teacher = Auth::guard('teacher')->user();
            if ($teacher->must_change_password) {
                return redirect()->route('teacher.force-change-password');
            }

            if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
                return redirect()->route('teacher.complete-profile');
            }

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

    public function showForceChangePassword()
    {
        $teacher = Auth::guard('teacher')->user();
        if (!$teacher || !$teacher->must_change_password) {
            return redirect()->route('teacher.dashboard');
        }
        return view('teacher.force-change-password');
    }

    public function forceChangePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $teacher = Auth::guard('teacher')->user();
        $teacher->password = Hash::make($request->password);
        $teacher->must_change_password = false;
        $teacher->save();

        return redirect()->route('teacher.dashboard')->with('success', 'Parol muvaffaqiyatli o\'zgartirildi.');
    }

    public function showCompleteProfile()
    {
        $teacher = Auth::guard('teacher')->user();
        if (!$teacher) {
            return redirect()->route('teacher.login');
        }

        // Telefon bor va telegram muhlat o'tmagan — sahifani ko'rsatish shart emas
        if ($teacher->isProfileComplete() && !$teacher->isTelegramDeadlinePassed() && $teacher->isTelegramVerified()) {
            return redirect()->route('teacher.dashboard');
        }

        $botUsername = config('services.telegram.bot_username', '');
        $verificationCode = $teacher->telegram_verification_code;

        return view('teacher.complete-profile', compact('teacher', 'botUsername', 'verificationCode'));
    }

    public function savePhone(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+998\d{9}$/'],
        ], [
            'phone.regex' => 'Telefon raqami +998XXXXXXXXX formatida bo\'lishi kerak.',
        ]);

        $teacher = Auth::guard('teacher')->user();
        $teacher->phone = $request->phone;
        $teacher->save();

        // Telefon saqlandi — dashboardga yo'naltiramiz (telegram hali majburiy emas)
        return redirect()->route('teacher.dashboard')
            ->with('success', 'Telefon raqami saqlandi. Telegram hisobingizni 7 kun ichida tasdiqlang.');
    }

    public function saveTelegram(Request $request)
    {
        $request->validate([
            'telegram_username' => ['required', 'string', 'regex:/^@[a-zA-Z0-9_]{5,32}$/'],
        ], [
            'telegram_username.regex' => 'Telegram username @username formatida bo\'lishi kerak (kamida 5 belgi).',
        ]);

        $teacher = Auth::guard('teacher')->user();
        $teacher->telegram_username = $request->telegram_username;

        // Tasdiqlash kodi generatsiya qilish
        $code = strtoupper(Str::random(6));
        $teacher->telegram_verification_code = $code;
        $teacher->telegram_verified_at = null;
        $teacher->telegram_chat_id = null;
        $teacher->save();

        return redirect()->route('teacher.complete-profile')
            ->with('success', 'Telegram username saqlandi. Endi botga tasdiqlash kodini yuboring.');
    }

    public function checkTelegramVerification()
    {
        $teacher = Auth::guard('teacher')->user();

        if ($teacher->telegram_verified_at) {
            return response()->json(['verified' => true]);
        }

        return response()->json(['verified' => false]);
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

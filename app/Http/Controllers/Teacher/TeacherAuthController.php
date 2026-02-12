<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use App\Models\Setting;

class TeacherAuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::guard('teacher')->check()) {
            $teacher = Auth::guard('teacher')->user();
            Log::info('[Teacher Login Page] Eski sessiya mavjud, dashboardga yo\'naltirilmoqda', [
                'teacher_id' => $teacher?->id,
                'teacher_name' => $teacher?->full_name,
                'must_change_password' => $teacher?->must_change_password,
            ]);
            return redirect()->intended(route('teacher.dashboard'));
        } else {
            return view('teacher.login');
        }
    }

    public function login(Request $request)
    {
        if (Auth::guard('teacher')->check()) {
            Log::info('[Teacher Login] Allaqachon autentifikatsiya qilingan, dashboardga yo\'naltirilmoqda');
            return redirect()->intended(route('teacher.dashboard'));
        }

        $credentials = $request->validate([
            'login' => ['required'],
            'password' => ['required'],
        ]);

        // Foydalanuvchini bazadan qidiramiz
        $teacher = Teacher::where('login', $credentials['login'])->first();

        if (!$teacher) {
            Log::warning('[Teacher Login] Foydalanuvchi topilmadi', ['login' => $credentials['login']]);
            return back()
                ->with('login_diagnostic', "Foydalanuvchi topilmadi: '{$credentials['login']}' login bazada yo'q.")
                ->withErrors(['login' => "Login yoki parol noto'g'ri."])
                ->withInput($request->only('login', '_profile'));
        }

        // Parolni qo'lda tekshiramiz (diagnostika uchun)
        $passwordValid = Hash::check($credentials['password'], $teacher->getAuthPassword());
        Log::info('[Teacher Login] Parol tekshiruvi', [
            'login' => $credentials['login'],
            'teacher_id' => $teacher->id,
            'is_active' => $teacher->is_active,
            'has_password' => !empty($teacher->getAuthPassword()),
            'password_valid' => $passwordValid,
            'password_starts_with' => substr($teacher->getAuthPassword(), 0, 7),
            'must_change_password' => $teacher->must_change_password,
            'has_telegram' => !empty($teacher->telegram_chat_id),
            'roles' => $teacher->getRoleNames()->toArray(),
        ]);

        if (Auth::guard('teacher')->attempt($credentials)) {
            $teacher = Auth::guard('teacher')->user();

            Log::info('[Teacher Login] Muvaffaqiyatli kirish', [
                'teacher_id' => $teacher->id,
                'full_name' => $teacher->full_name,
                'has_telegram_chat_id' => !empty($teacher->telegram_chat_id),
                'must_change_password' => $teacher->must_change_password,
            ]);

            // Telegram 2FA: agar foydalanuvchi Telegram tasdiqlangan bo'lsa
            if ($teacher->telegram_chat_id) {
                Log::info('[Teacher Login] Telegram 2FA faol, verify sahifasiga yo\'naltirilmoqda', [
                    'teacher_id' => $teacher->id,
                ]);
                // Login qilingan holatda emas — logout qilib, 2FA tekshiruvga yo'naltiramiz
                Auth::guard('teacher')->logout();
                return $this->sendLoginCode($teacher, $request);
            }

            $request->session()->regenerate();
            ActivityLogService::logLogin('teacher');

            if ($teacher->must_change_password) {
                Log::info('[Teacher Login] Parol o\'zgartirish majburiy, force-change sahifasiga yo\'naltirilmoqda');
                return redirect()->route('teacher.force-change-password');
            }

            if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
                return redirect()->route('teacher.complete-profile');
            }

            return redirect()->intended(route('teacher.dashboard'));
        }

        Log::warning('[Teacher Login] Kirish muvaffaqiyatsiz (attempt rad etdi)', [
            'login' => $credentials['login'],
            'teacher_id' => $teacher->id,
            'manual_password_check' => $passwordValid,
        ]);

        $diagnostic = "Teacher topildi (ID: {$teacher->id}, {$teacher->full_name}). "
            . "Parol tekshiruvi: " . ($passwordValid ? 'TO\'G\'RI' : 'NOTO\'G\'RI') . ". "
            . "birth_date: " . ($teacher->birth_date ?? 'YO\'Q') . ", "
            . "is_active: " . ($teacher->is_active ? 'Ha' : 'Yo\'q') . ", "
            . "must_change_password: " . ($teacher->must_change_password ? 'Ha' : 'Yo\'q') . ", "
            . "Rollar: " . ($teacher->getRoleNames()->join(', ') ?: 'yo\'q');

        return back()
            ->with('login_diagnostic', $diagnostic)
            ->withErrors(['login' => "Login yoki parol noto'g'ri."])
            ->withInput($request->only('login', '_profile'));
    }

    /**
     * Login tasdiqlash kodini yaratib, Telegramga yuborish
     */
    private function sendLoginCode(Teacher $teacher, Request $request)
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $teacher->login_code = $code;
        $teacher->login_code_expires_at = now()->addMinutes(5);
        $teacher->save();

        $telegramService = new TelegramService();
        $sent = $telegramService->sendToUser(
            $teacher->telegram_chat_id,
            "Tizimga kirish uchun tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi. Agar siz kirmoqchi bo'lmagan bo'lsangiz, bu xabarni e'tiborsiz qoldiring."
        );

        if (!$sent) {
            // Telegram yuborilmasa, oddiy login qilish
            Auth::guard('teacher')->login($teacher);
            $request->session()->regenerate();
            ActivityLogService::logLogin('teacher');

            if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
                return redirect()->route('teacher.complete-profile');
            }

            return redirect()->intended(route('teacher.dashboard'));
        }

        $request->session()->put('login_verify_teacher_id', $teacher->id);

        return redirect()->route('teacher.verify-login');
    }

    /**
     * Login tasdiqlash sahifasini ko'rsatish
     */
    public function showVerifyLogin(Request $request)
    {
        if (!$request->session()->has('login_verify_teacher_id')) {
            return redirect()->route('teacher.login');
        }

        $teacher = Teacher::find($request->session()->get('login_verify_teacher_id'));
        if (!$teacher) {
            $request->session()->forget('login_verify_teacher_id');
            return redirect()->route('teacher.login');
        }

        $maskedChat = substr($teacher->telegram_username ?? 'Telegram', 0, 3) . '***';

        return view('auth.verify-login', [
            'guard' => 'teacher',
            'maskedContact' => $maskedChat,
        ]);
    }

    /**
     * Login tasdiqlash kodini tekshirish
     */
    public function verifyLoginCode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $teacherId = $request->session()->get('login_verify_teacher_id');
        if (!$teacherId) {
            return redirect()->route('teacher.login')
                ->withErrors(['code' => 'Sessiya tugagan. Qaytadan kiring.']);
        }

        $teacher = Teacher::find($teacherId);
        if (!$teacher) {
            $request->session()->forget('login_verify_teacher_id');
            return redirect()->route('teacher.login');
        }

        // Muddati tekshiruvi
        if (!$teacher->login_code_expires_at || $teacher->login_code_expires_at->isPast()) {
            $teacher->login_code = null;
            $teacher->login_code_expires_at = null;
            $teacher->save();
            $request->session()->forget('login_verify_teacher_id');

            return redirect()->route('teacher.login')
                ->withErrors(['code' => 'Tasdiqlash kodi muddati tugagan. Qaytadan kiring.']);
        }

        // Kod tekshiruvi
        if ($teacher->login_code !== $request->code) {
            return back()->withErrors(['code' => 'Tasdiqlash kodi noto\'g\'ri.']);
        }

        // Tasdiqlash muvaffaqiyatli
        $teacher->login_code = null;
        $teacher->login_code_expires_at = null;
        $teacher->save();

        $request->session()->forget('login_verify_teacher_id');

        Auth::guard('teacher')->login($teacher);
        $request->session()->regenerate();
        ActivityLogService::logLogin('teacher');

        if ($teacher->must_change_password) {
            return redirect()->route('teacher.force-change-password');
        }

        if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
            return redirect()->route('teacher.complete-profile');
        }

        return redirect()->intended(route('teacher.dashboard'));
    }

    /**
     * Tasdiqlash kodini qayta yuborish
     */
    public function resendLoginCode(Request $request)
    {
        $teacherId = $request->session()->get('login_verify_teacher_id');
        if (!$teacherId) {
            return redirect()->route('teacher.login');
        }

        $teacher = Teacher::find($teacherId);
        if (!$teacher || !$teacher->telegram_chat_id) {
            $request->session()->forget('login_verify_teacher_id');
            return redirect()->route('teacher.login');
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $teacher->login_code = $code;
        $teacher->login_code_expires_at = now()->addMinutes(5);
        $teacher->save();

        $telegramService = new TelegramService();
        $telegramService->sendToUser(
            $teacher->telegram_chat_id,
            "Tizimga kirish uchun yangi tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
        );

        return back()->with('success', 'Yangi tasdiqlash kodi Telegramga yuborildi.');
    }

    public function logout(Request $request)
    {
        ActivityLogService::logLogout('teacher');
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
        $teacher->password = $request->password;
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
            'phone' => ['required', 'string', 'regex:/^\+\d{7,15}$/'],
        ], [
            'phone.regex' => 'Telefon raqami noto\'g\'ri formatda. Masalan: +998901234567',
        ]);

        $teacher = Auth::guard('teacher')->user();
        $teacher->phone = $request->phone;
        $teacher->save();

        // Telefon saqlandi — dashboardga yo'naltiramiz (telegram hali majburiy emas)
        $days = Setting::get('telegram_deadline_days', 7);

        return redirect()->route('teacher.dashboard')
            ->with('success', "Telefon raqami saqlandi. Telegram hisobingizni {$days} kun ichida tasdiqlang.");
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
        $teacher->password = $request->password;
        $teacher->save();

        return back()->with('success', 'Login va parol muvaffaqiyatli yangilandi.');
    }
}

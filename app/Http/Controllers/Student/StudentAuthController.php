<?php

namespace App\Http\Controllers\Student;


use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        // Agar oldingi sessiya hali faol bo'lsa — avval logout qilamiz
        if (Auth::guard('student')->check()) {
            Auth::guard('student')->logout();
        }

        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginId = $request->login;

        // 1-bosqich: HEMIS API ga login so'rovi
        Log::channel('student_auth')->info('[HEMIS LOGIN] So\'rov yuborilmoqda', [
            'login' => $loginId,
            'ip' => $request->ip(),
        ]);

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->post('https://student.ttatf.uz/rest/v1/auth/login', [
                    'login' => $loginId,
                    'password' => $request->password,
                ]);
        } catch (\Exception $e) {
            Log::channel('student_auth')->error('[HEMIS LOGIN] API ulanish xatosi (timeout/network)', [
                'login' => $loginId,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            // HEMIS ishlamayotgan bo'lsa — lokal parol bilan urinish
            return $this->tryLocalPassword($request, hemsFailed: true);
        }

        Log::channel('student_auth')->info('[HEMIS LOGIN] API javob olindi', [
            'login' => $loginId,
            'hemis_status' => $response->status(),
            'hemis_success' => $response->json('success'),
            'hemis_message' => $response->json('message'),
            'hemis_error' => $response->json('error'),
        ]);

        if ($response->successful() && $response->json('success')) {
            $token = $response->json('data.token');

            // 2-bosqich: Talaba ma'lumotlarini olish
            Log::channel('student_auth')->info('[HEMIS ACCOUNT/ME] So\'rov yuborilmoqda', [
                'login' => $loginId,
            ]);

            try {
                $studentDataResponse = Http::withoutVerifying()
                    ->timeout(15)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                    ])->get('https://student.ttatf.uz/rest/v1/account/me');
            } catch (\Exception $e) {
                Log::channel('student_auth')->error('[HEMIS ACCOUNT/ME] API ulanish xatosi (timeout/network)', [
                    'login' => $loginId,
                    'error' => $e->getMessage(),
                ]);
                return back()->withErrors(['login' => 'HEMIS serveriga ulanishda xatolik. Qaytadan urinib ko\'ring.'])->onlyInput('login', '_profile');
            }

            Log::channel('student_auth')->info('[HEMIS ACCOUNT/ME] API javob olindi', [
                'login' => $loginId,
                'hemis_status' => $studentDataResponse->status(),
                'hemis_success' => $studentDataResponse->json('success'),
                'hemis_message' => $studentDataResponse->json('message'),
            ]);

            if ($studentDataResponse->successful()) {
                $studentData = $studentDataResponse->json('data');

                // 3-bosqich: Bazada talabani topish/yaratish
                $existingStudent = Student::where('student_id_number', $studentData['student_id_number'])->first();

                Log::channel('student_auth')->info('[LMS DB] Talaba qidirilmoqda', [
                    'login' => $loginId,
                    'hemis_student_id_number' => $studentData['student_id_number'] ?? null,
                    'hemis_id' => $studentData['id'] ?? null,
                    'bazada_topildi' => $existingStudent ? true : false,
                    'bazadagi_id' => $existingStudent?->id,
                ]);

                $student = Student::updateOrCreate(
                    ['student_id_number' => $studentData['student_id_number']],
                    [
                        'token' => $token,
                        'token_expires_at' => now()->addDays(7),
                        'must_change_password' => false,
                    ]
                );

                // Telegram 2FA: vaqtincha o'chirilgan
                // if ($student->telegram_chat_id) {
                //     return $this->sendLoginCode($student, $request);
                // }

                // Boshqa guardlarni tozalash (admin/teacher session qoldiqlarini yo'q qilish)
                $this->clearOtherGuards($request);

                Auth::guard('student')->login($student);
                $request->session()->regenerate();
                ActivityLogService::logLogin('student');

                Log::channel('student_auth')->info('[LOGIN SUCCESS] Talaba tizimga kirdi', [
                    'login' => $loginId,
                    'student_id' => $student->id,
                    'student_id_number' => $student->student_id_number,
                    'full_name' => $student->full_name,
                    'auth_method' => 'hemis',
                ]);

                if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
                    return redirect()->route('student.complete-profile');
                }

                return redirect()->intended(route('student.dashboard'))->with('studentData', $studentData);
            } else {
                Log::channel('student_auth')->warning('[HEMIS ACCOUNT/ME] Muvaffaqiyatsiz javob', [
                    'login' => $loginId,
                    'hemis_status' => $studentDataResponse->status(),
                    'hemis_body' => mb_substr($studentDataResponse->body(), 0, 500),
                ]);
                return back()->withErrors(['login' => 'Talabaning ma\'lumotlarini olishda xatolik.'])->onlyInput('login', '_profile');
            }
        } else {
            Log::channel('student_auth')->warning('[HEMIS LOGIN] HEMIS rad etdi', [
                'login' => $loginId,
                'hemis_status' => $response->status(),
                'hemis_body' => mb_substr($response->body(), 0, 500),
            ]);

            return $this->tryLocalPassword($request, hemsFailed: false);
        }
    }

    /**
     * HEMIS ishlamasa — lokal parol bilan kirish
     */
    private function tryLocalPassword(Request $request, bool $hemsFailed = false)
    {
        $loginId = $request->login;

        Log::channel('student_auth')->info('[LOCAL AUTH] Lokal parol tekshiruvi boshlanmoqda', [
            'login' => $loginId,
            'hemis_failed' => $hemsFailed,
            'ip' => $request->ip(),
        ]);

        $student = Student::where('student_id_number', $loginId)->first();

        if (!$student) {
            Log::channel('student_auth')->warning('[LOCAL AUTH] Talaba bazada topilmadi', [
                'login' => $loginId,
                'hemis_failed' => $hemsFailed,
                'sabab' => 'student_id_number bazada mavjud emas',
            ]);
            return back()->withErrors(['login' => "Login yoki parol noto'g'ri."])->onlyInput('login', '_profile');
        }

        // Lokal parol mavjud emas
        if (!$student->local_password) {
            Log::channel('student_auth')->warning('[LOCAL AUTH] Lokal parol yo\'q', [
                'login' => $loginId,
                'student_id' => $student->id,
                'hemis_failed' => $hemsFailed,
                'sabab' => 'local_password NULL — faqat HEMIS orqali kirish mumkin',
            ]);
            if ($hemsFailed) {
                return back()->withErrors(['login' => "HEMIS tizimi vaqtincha ishlamayapti. Iltimos, keyinroq urinib ko'ring."])->onlyInput('login', '_profile');
            }
            return back()->withErrors(['login' => "Login yoki parol noto'g'ri."])->onlyInput('login', '_profile');
        }

        // Lokal parol muddati tugagan
        if ($student->local_password_expires_at && $student->local_password_expires_at->isPast()) {
            Log::channel('student_auth')->warning('[LOCAL AUTH] Lokal parol muddati tugagan', [
                'login' => $loginId,
                'student_id' => $student->id,
                'expired_at' => $student->local_password_expires_at->toDateTimeString(),
            ]);
            return back()->withErrors(['login' => "Vaqtinchalik parol muddati tugagan. Admin bilan bog'laning."])->onlyInput('login', '_profile');
        }

        // Parol tekshiruvi
        if (!Hash::check($request->password, $student->local_password)) {
            Log::channel('student_auth')->warning('[LOCAL AUTH] Lokal parol noto\'g\'ri', [
                'login' => $loginId,
                'student_id' => $student->id,
                'hemis_failed' => $hemsFailed,
                'sabab' => 'Kiritilgan parol lokal parolga mos kelmadi',
            ]);
            if ($hemsFailed) {
                return back()->withErrors(['login' => "HEMIS tizimi vaqtincha ishlamayapti va lokal parol noto'g'ri."])->onlyInput('login', '_profile');
            }
            return back()->withErrors(['login' => "Login yoki parol noto'g'ri."])->onlyInput('login', '_profile');
        }

        // Telegram 2FA: vaqtincha o'chirilgan
        // if ($student->telegram_chat_id) {
        //     return $this->sendLoginCode($student, $request);
        // }

        // Boshqa guardlarni tozalash (admin/teacher session qoldiqlarini yo'q qilish)
        $this->clearOtherGuards($request);

        Auth::guard('student')->login($student);
        $request->session()->regenerate();
        ActivityLogService::logLogin('student');

        Log::channel('student_auth')->info('[LOGIN SUCCESS] Talaba lokal parol bilan kirdi', [
            'login' => $loginId,
            'student_id' => $student->id,
            'student_id_number' => $student->student_id_number,
            'full_name' => $student->full_name,
            'auth_method' => 'local_password',
        ]);

        if ($student->must_change_password) {
            return redirect()->route('student.password.edit');
        }

        if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
            return redirect()->route('student.complete-profile');
        }

        return redirect()->intended(route('student.dashboard'));
    }

    /**
     * Login tasdiqlash kodini yaratib, Telegramga yuborish
     */
    private function sendLoginCode(Student $student, Request $request)
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $student->login_code = $code;
        $student->login_code_expires_at = now()->addMinutes(5);
        $student->save();

        $telegramService = new TelegramService();
        $sent = $telegramService->sendToUser(
            $student->telegram_chat_id,
            "Tizimga kirish uchun tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi. Agar siz kirmoqchi bo'lmagan bo'lsangiz, bu xabarni e'tiborsiz qoldiring."
        );

        if (!$sent) {
            // Telegram yuborilmasa, oddiy login qilish
            $this->clearOtherGuards($request);
            Auth::guard('student')->login($student);
            $request->session()->regenerate();
            ActivityLogService::logLogin('student');

            if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
                return redirect()->route('student.complete-profile');
            }

            return redirect()->intended(route('student.dashboard'));
        }

        $request->session()->put('login_verify_student_id', $student->id);

        return redirect()->route('student.verify-login');
    }

    /**
     * Login tasdiqlash sahifasini ko'rsatish
     */
    public function showVerifyLogin(Request $request)
    {
        if (!$request->session()->has('login_verify_student_id')) {
            return redirect()->route('student.login');
        }

        $student = Student::find($request->session()->get('login_verify_student_id'));
        if (!$student) {
            $request->session()->forget('login_verify_student_id');
            return redirect()->route('student.login');
        }

        $maskedChat = substr($student->telegram_username ?? 'Telegram', 0, 3) . '***';

        return view('auth.verify-login', [
            'guard' => 'student',
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

        $studentId = $request->session()->get('login_verify_student_id');
        if (!$studentId) {
            return redirect()->route('student.login')
                ->withErrors(['code' => 'Sessiya tugagan. Qaytadan kiring.']);
        }

        $student = Student::find($studentId);
        if (!$student) {
            $request->session()->forget('login_verify_student_id');
            return redirect()->route('student.login');
        }

        // Muddati tekshiruvi
        if (!$student->login_code_expires_at || $student->login_code_expires_at->isPast()) {
            $student->login_code = null;
            $student->login_code_expires_at = null;
            $student->save();
            $request->session()->forget('login_verify_student_id');

            return redirect()->route('student.login')
                ->withErrors(['code' => 'Tasdiqlash kodi muddati tugagan. Qaytadan kiring.']);
        }

        // Kod tekshiruvi
        if ($student->login_code !== $request->code) {
            return back()->withErrors(['code' => 'Tasdiqlash kodi noto\'g\'ri.']);
        }

        // Tasdiqlash muvaffaqiyatli
        $student->login_code = null;
        $student->login_code_expires_at = null;
        $student->save();

        $request->session()->forget('login_verify_student_id');

        // Boshqa guardlarni tozalash (admin/teacher session qoldiqlarini yo'q qilish)
        $this->clearOtherGuards($request);

        Auth::guard('student')->login($student);
        $request->session()->regenerate();
        ActivityLogService::logLogin('student');

        if ($student->must_change_password) {
            return redirect()->route('student.password.edit');
        }

        if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
            return redirect()->route('student.complete-profile');
        }

        return redirect()->intended(route('student.dashboard'));
    }

    /**
     * Tasdiqlash kodini qayta yuborish
     */
    public function resendLoginCode(Request $request)
    {
        $studentId = $request->session()->get('login_verify_student_id');
        if (!$studentId) {
            return redirect()->route('student.login');
        }

        $student = Student::find($studentId);
        if (!$student || !$student->telegram_chat_id) {
            $request->session()->forget('login_verify_student_id');
            return redirect()->route('student.login');
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $student->login_code = $code;
        $student->login_code_expires_at = now()->addMinutes(5);
        $student->save();

        $telegramService = new TelegramService();
        $telegramService->sendToUser(
            $student->telegram_chat_id,
            "Tizimga kirish uchun yangi tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
        );

        return back()->with('success', 'Yangi tasdiqlash kodi Telegramga yuborildi.');
    }

    public function editPassword()
    {
        if (session('impersonating')) {
            return redirect()->route('student.dashboard');
        }

        return view('student.change-password');
    }

    public function updatePassword(Request $request)
    {
        $student = Auth::guard('student')->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (!$student || !$student->local_password || !Hash::check($request->current_password, $student->local_password)) {
            return back()->withErrors(['current_password' => 'Joriy vaqtinchalik parol noto‘g‘ri.']);
        }

        $changedPasswordDays = (int) Setting::get('changed_password_days', 30);

        $student->local_password = $request->password;
        $student->local_password_expires_at = now()->addDays($changedPasswordDays);
        $student->must_change_password = false;
        $student->save();

        return redirect()->route('student.dashboard')->with('success', "Parol muvaffaqiyatli yangilandi. Parol {$changedPasswordDays} kun amal qiladi. Shu muddat ichida HEMIS parolingizni tiklang.");
    }


    public function showCompleteProfile()
    {
        $student = Auth::guard('student')->user();
        if (!$student) {
            return redirect()->route('student.login');
        }

        if ($student->isProfileComplete() && !$student->isTelegramDeadlinePassed() && $student->isTelegramVerified()) {
            return redirect()->route('student.dashboard');
        }

        $botUsername = config('services.telegram.bot_username', '');
        $verificationCode = $student->telegram_verification_code;

        return view('student.complete-profile', compact('student', 'botUsername', 'verificationCode'));
    }

    public function savePhone(Request $request)
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+\d{7,15}$/'],
        ], [
            'phone.regex' => 'Telefon raqami noto\'g\'ri formatda. Masalan: +998901234567',
        ]);

        $student = Auth::guard('student')->user();
        $student->phone = $request->phone;
        $student->save();

        $days = Setting::get('telegram_deadline_days', 7);

        return redirect()->route('student.dashboard')
            ->with('success', "Telefon raqami saqlandi. Telegram hisobingizni {$days} kun ichida tasdiqlang.");
    }

    public function saveTelegram(Request $request)
    {
        $request->validate([
            'telegram_username' => ['required', 'string', 'regex:/^@[a-zA-Z0-9_]{5,32}$/'],
        ], [
            'telegram_username.regex' => 'Telegram username @username formatida bo\'lishi kerak (kamida 5 belgi).',
        ]);

        $student = Auth::guard('student')->user();
        $student->telegram_username = $request->telegram_username;

        $code = strtoupper(Str::random(6));
        $student->telegram_verification_code = $code;
        $student->telegram_verified_at = null;
        $student->telegram_chat_id = null;
        $student->save();

        return redirect()->route('student.complete-profile')
            ->with('success', 'Telegram username saqlandi. Endi botga tasdiqlash kodini yuboring.');
    }

    public function checkTelegramVerification()
    {
        $student = Auth::guard('student')->user();

        if ($student->telegram_verified_at) {
            return response()->json(['verified' => true]);
        }

        return response()->json(['verified' => false]);
    }

    public function refreshToken()
    {
        $refreshToken = Cookie::get('refresh-token');

        if ($refreshToken) {
            $response = Http::withoutVerifying()->withHeaders([
                'Cookie' => 'refresh-token=' . $refreshToken,
            ])->post('https://student.ttatf.uz/rest/v1/auth/refresh-token');

            if ($response->successful()) {
                $newToken = $response->json('data.token');

                // Yangilangan tokenni bazaga yozish
                $student = Auth::user();
                $student->token = $newToken;
                $student->token_expires_at = now()->addDays(7);
                $student->save();

                return response()->json(['message' => 'Token yangilandi']);
            }
        }

        return response()->json(['message' => 'Tokenni yangilab bo‘lmadi'], 401);
    }

    public function logout(Request $request)
    {
        ActivityLogService::logLogout('student');
        $student = Auth::guard('student')->user();
        if ($student) {
            $student->token = null;
            $student->token_expires_at = null;
            $student->save();
        }

        // Barcha guardlardan logout qilish (session qoldiqlarini to'liq tozalash)
        Auth::guard('web')->logout();
        Auth::guard('teacher')->logout();
        Auth::guard('student')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Boshqa guardlarni tozalash — student login qilishdan oldin
     * admin yoki teacher session qoldiqlari bo'lsa, ularni yo'q qilish.
     */
    private function clearOtherGuards(Request $request): void
    {
        foreach (['web', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        $request->session()->forget([
            'impersonating',
            'impersonator_id',
            'impersonator_guard',
            'impersonated_name',
            'impersonator_active_role',
            'active_role',
        ]);
    }
}

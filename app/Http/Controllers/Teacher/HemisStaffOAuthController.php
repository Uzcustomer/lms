<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Xodimlar HEMIS (hemis.ttatf.uz) orqali kirishi — OAuth 2.0 (talabalardagi HemisOAuthController kabi,
 * lekin xodimlar portalining alohida OAuth ilovasi bilan). Xodim LMS bazasidan Xodim ID
 * (employee_id_number) yoki login bo'yicha topiladi; kirgandan keyingi qadamlar
 * TeacherAuthController::login() bilan bir xil.
 */
class HemisStaffOAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $config = config('services.hemis_staff_oauth');
        if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['base_url'])) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => "HEMIS orqali kirish hali sozlanmagan. Login va parol bilan kiring."]);
        }

        $state = Str::random(40);
        $request->session()->put('hemis_staff_oauth_state', $state);

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
        ]);

        return redirect(rtrim($config['base_url'], '/') . "/oauth/authorize?{$query}");
    }

    public function callback(Request $request)
    {
        try {
            return $this->handleCallback($request);
        } catch (\Throwable $e) {
            Log::channel('student_auth')->error('[HEMIS STAFF OAUTH] Kutilmagan xato', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'ip' => $request->ip(),
            ]);

            // Texnik xato matni foydalanuvchiga ko'rsatilmaydi — faqat logda
            return redirect()->route('teacher.login')
                ->withErrors(['login' => "HEMIS orqali kirishda xatolik yuz berdi. Qaytadan urinib ko'ring yoki login va parol bilan kiring."]);
        }
    }

    private function handleCallback(Request $request)
    {
        $fail = fn (string $message) => redirect()->route('teacher.login')->withErrors(['login' => $message]);

        // State tekshiruvi (CSRF himoya)
        $savedState = $request->session()->pull('hemis_staff_oauth_state');
        if (!$savedState || !hash_equals($savedState, (string) $request->input('state'))) {
            Log::channel('student_auth')->warning('[HEMIS STAFF OAUTH] State mos kelmadi', ['ip' => $request->ip()]);

            return $fail("Xavfsizlik tekshiruvidan o'tilmadi. Qaytadan urinib ko'ring.");
        }

        if ($request->has('error')) {
            Log::channel('student_auth')->warning('[HEMIS STAFF OAUTH] HEMIS xatosi', [
                'error' => $request->input('error'),
                'error_description' => $request->input('error_description'),
            ]);

            return $fail('HEMIS tizimidan kirish rad etildi.');
        }

        $code = $request->input('code');
        if (!$code) {
            return $fail('HEMIS tizimidan kod olinmadi.');
        }

        $config = config('services.hemis_staff_oauth');
        $baseUrl = rtrim($config['base_url'], '/');

        // 1. Code → access token
        $tokenResponse = Http::timeout(15)->asForm()->post("{$baseUrl}/oauth/access-token", [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);
        $accessToken = $tokenResponse->successful() ? $tokenResponse->json('access_token') : null;
        if (!$accessToken) {
            Log::channel('student_auth')->warning('[HEMIS STAFF OAUTH] Token olinmadi', [
                'status' => $tokenResponse->status(),
                'body' => mb_substr($tokenResponse->body(), 0, 300),
            ]);

            return $fail('HEMIS tizimidan javob olinmadi. Qaytadan urinib ko\'ring.');
        }

        // 2. Foydalanuvchi ma'lumotlari
        $userResponse = Http::timeout(15)->withToken($accessToken)->get("{$baseUrl}/oauth/api/user");
        if (!$userResponse->successful()) {
            Log::channel('student_auth')->warning('[HEMIS STAFF OAUTH] Foydalanuvchi ma\'lumoti olinmadi', [
                'status' => $userResponse->status(),
            ]);

            return $fail("HEMIS'dan xodim ma'lumotlari olinmadi.");
        }

        $userData = (array) $userResponse->json();
        $login = trim((string) ($userData['login'] ?? ''));
        $employeeIdNumber = trim((string) ($userData['employee_id_number'] ?? $login));

        Log::channel('student_auth')->info('[HEMIS STAFF OAUTH] Xodim ma\'lumotlari olindi', [
            'login' => $login,
            'employee_id_number' => $employeeIdNumber,
            'type' => $userData['type'] ?? null,
            'keys' => array_keys($userData),
        ]);

        // 3. LMS bazasidan xodimni topish — Xodim ID, keyin login bo'yicha
        $teacher = null;
        if ($employeeIdNumber !== '') {
            $teacher = Teacher::where('employee_id_number', $employeeIdNumber)->first();
        }
        if (!$teacher && $login !== '') {
            $teacher = Teacher::where('login', $login)->first();
        }
        if (!$teacher) {
            Log::channel('student_auth')->warning('[HEMIS STAFF OAUTH] Xodim LMS bazasida topilmadi', [
                'login' => $login,
                'employee_id_number' => $employeeIdNumber,
            ]);

            return $fail("Siz LMS tizimida topilmadingiz (Xodim ID: {$employeeIdNumber}). Admin bilan bog'laning.");
        }

        // 4. Kirish — TeacherAuthController::login() dagi kabi
        Auth::guard('web')->logout();
        Auth::guard('student')->logout();
        $request->session()->forget([
            'impersonating', 'impersonator_id', 'impersonator_guard',
            'impersonated_name', 'impersonator_active_role', 'active_role',
        ]);

        Auth::guard('teacher')->login($teacher);
        $request->session()->regenerate();

        $roles = $teacher->getRoleNames()->toArray();
        if (in_array('oqituvchi', $roles)) {
            session(['active_role' => 'oqituvchi']);
        } elseif (count($roles) > 0) {
            session(['active_role' => $roles[0]]);
        }

        ActivityLogService::logLogin('teacher');
        Log::channel('student_auth')->info('[LOGIN SUCCESS] Xodim HEMIS OAuth orqali kirdi', [
            'teacher_id' => $teacher->id,
            'full_name' => $teacher->full_name,
            'auth_method' => 'hemis_staff_oauth',
        ]);

        if ($teacher->must_change_password) {
            return redirect()->route('teacher.force-change-password');
        }

        if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
            return redirect()->route('teacher.complete-profile');
        }

        return redirect()->intended(route('teacher.dashboard'));
    }

    /** HEMIS'dagi OAuth ilovaga yozilgan manzil bilan harfma-harf bir xil bo'lishi kerak. */
    private function redirectUri(): string
    {
        return config('services.hemis_staff_oauth.redirect_uri') ?: route('auth.hemis-staff.callback');
    }
}

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

class TeacherHemisOAuthController extends Controller
{
    /**
     * HEMIS OAuth sahifasiga yo'naltirish (hemis.ttatf.uz)
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('hemis_teacher_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.hemis_teacher_oauth.client_id'),
            'redirect_uri' => config('services.hemis_teacher_oauth.redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        $baseUrl = config('services.hemis_teacher_oauth.base_url');

        return redirect("{$baseUrl}/oauth/authorize?{$query}");
    }

    /**
     * HEMIS OAuth callback — code ni tokenga almashtirish va login qilish
     */
    public function callback(Request $request)
    {
        try {
            return $this->handleCallback($request);
        } catch (\Exception $e) {
            Log::channel('student_auth')->error('[HEMIS TEACHER OAUTH] Kutilmagan xato', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 1000),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'Xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    private function handleCallback(Request $request)
    {
        // State tekshiruvi (CSRF himoya)
        $savedState = $request->session()->pull('hemis_teacher_oauth_state');
        if (!$savedState || $savedState !== $request->input('state')) {
            Log::channel('student_auth')->warning('[HEMIS TEACHER OAUTH] State mos kelmadi', [
                'expected' => $savedState,
                'received' => $request->input('state'),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'Xavfsizlik tekshiruvidan o\'tilmadi. Qaytadan urinib ko\'ring.']);
        }

        if ($request->has('error')) {
            Log::channel('student_auth')->warning('[HEMIS TEACHER OAUTH] HEMIS xatosi', [
                'error' => $request->input('error'),
                'error_description' => $request->input('error_description'),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'HEMIS tizimidan kirish rad etildi.']);
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'HEMIS tizimidan kod olinmadi.']);
        }

        $baseUrl = config('services.hemis_teacher_oauth.base_url');

        // 1-bosqich: Code ni access token ga almashtirish
        Log::channel('student_auth')->info('[HEMIS TEACHER OAUTH] Token so\'ralmoqda', [
            'base_url' => $baseUrl,
            'ip' => $request->ip(),
        ]);

        $tokenResponse = Http::withoutVerifying()
            ->timeout(15)
            ->asForm()
            ->post("{$baseUrl}/oauth/access-token", [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.hemis_teacher_oauth.client_id'),
                'client_secret' => config('services.hemis_teacher_oauth.client_secret'),
                'redirect_uri' => config('services.hemis_teacher_oauth.redirect_uri'),
                'code' => $code,
            ]);

        Log::channel('student_auth')->info('[HEMIS TEACHER OAUTH] Token javobi', [
            'status' => $tokenResponse->status(),
            'body' => mb_substr($tokenResponse->body(), 0, 500),
        ]);

        if (!$tokenResponse->successful()) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'HEMIS tizimidan token olinmadi (status: ' . $tokenResponse->status() . ').']);
        }

        $accessToken = $tokenResponse->json('access_token');
        if (!$accessToken) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'HEMIS javobida access_token topilmadi.']);
        }

        // 2-bosqich: Foydalanuvchi ma'lumotlarini olish
        $userResponse = Http::withoutVerifying()
            ->timeout(15)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get("{$baseUrl}/oauth/api/user", [
                'fields' => 'id,uuid,type,name,login,picture,email,university_id,phone',
            ]);

        Log::channel('student_auth')->info('[HEMIS TEACHER OAUTH] User javobi', [
            'status' => $userResponse->status(),
            'body' => mb_substr($userResponse->body(), 0, 1000),
        ]);

        if (!$userResponse->successful()) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'Foydalanuvchi ma\'lumotlarini olishda xatolik (status: ' . $userResponse->status() . ').']);
        }

        $userData = $userResponse->json();
        $hemisLogin = $userData['login'] ?? null;
        $hemisId = $userData['id'] ?? null;
        $employeeIdNumber = $userData['employee_id_number'] ?? $hemisLogin;

        Log::channel('student_auth')->info('[HEMIS TEACHER OAUTH] Foydalanuvchi ma\'lumotlari olindi', [
            'hemis_id' => $hemisId,
            'login' => $hemisLogin,
            'employee_id_number' => $employeeIdNumber,
            'name' => $userData['name'] ?? $userData['full_name'] ?? null,
            'keys' => array_keys($userData),
        ]);

        if (!$employeeIdNumber && !$hemisId) {
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'HEMIS tizimidan xodim ma\'lumotlari olinmadi. Kalitlar: ' . implode(', ', array_keys($userData))]);
        }

        // 3-bosqich: Bazada xodimni topish (login, employee_id_number, hemis_id bo'yicha)
        $teacher = Teacher::where('login', $hemisLogin)->first();

        if (!$teacher && $employeeIdNumber) {
            $teacher = Teacher::where('employee_id_number', $employeeIdNumber)->first();
        }

        if (!$teacher && $hemisId) {
            $teacher = Teacher::where('hemis_id', $hemisId)->first();
        }

        if (!$teacher) {
            Log::channel('student_auth')->warning('[HEMIS TEACHER OAUTH] Xodim bazada topilmadi', [
                'employee_id_number' => $employeeIdNumber,
                'hemis_id' => $hemisId,
                'login' => $hemisLogin,
            ]);
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'Siz LMS tizimida ro\'yxatdan o\'tmagansiz (login: ' . $hemisLogin . '). Admin bilan bog\'laning.']);
        }

        // Boshqa guardlarni tozalash
        foreach (['web', 'student'] as $guard) {
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

        Auth::guard('teacher')->login($teacher);
        $request->session()->regenerate();

        // Default active_role ni o'rnatish
        $roles = $teacher->getRoleNames()->toArray();
        if (in_array('oqituvchi', $roles)) {
            session(['active_role' => 'oqituvchi']);
        } elseif (count($roles) > 0) {
            session(['active_role' => $roles[0]]);
        }

        try {
            ActivityLogService::logLogin('teacher');
        } catch (\Exception $e) {
            Log::channel('student_auth')->warning('[HEMIS TEACHER OAUTH] Activity log xatosi (davom etiladi)', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('student_auth')->info('[LOGIN SUCCESS] Xodim HEMIS OAuth orqali kirdi', [
            'teacher_id' => $teacher->id,
            'login' => $teacher->login,
            'full_name' => $teacher->full_name,
            'auth_method' => 'hemis_teacher_oauth',
        ]);

        if ($teacher->must_change_password) {
            return redirect()->route('teacher.force-change-password');
        }

        if (!$teacher->isProfileComplete() || $teacher->isTelegramDeadlinePassed()) {
            return redirect()->route('teacher.complete-profile');
        }

        return redirect()->intended(route('teacher.dashboard'));
    }
}

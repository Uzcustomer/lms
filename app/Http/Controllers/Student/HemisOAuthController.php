<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HemisOAuthController extends Controller
{
    /**
     * HEMIS OAuth sahifasiga yo'naltirish
     */
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('hemis_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.hemis_oauth.client_id'),
            'redirect_uri' => config('services.hemis_oauth.redirect_uri'),
            'response_type' => 'code',
            'state' => $state,
        ]);

        $baseUrl = config('services.hemis_oauth.base_url');

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
            Log::channel('student_auth')->error('[HEMIS OAUTH] Kutilmagan xato', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 1000),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('student.login')
                ->withErrors(['login' => 'Xatolik yuz berdi: ' . $e->getMessage()]);
        }
    }

    private function handleCallback(Request $request)
    {
        // State tekshiruvi (CSRF himoya)
        $savedState = $request->session()->pull('hemis_oauth_state');
        if (!$savedState || $savedState !== $request->input('state')) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] State mos kelmadi', [
                'expected' => $savedState,
                'received' => $request->input('state'),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'Xavfsizlik tekshiruvidan o\'tilmadi. Qaytadan urinib ko\'ring.']);
        }

        if ($request->has('error')) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] HEMIS xatosi', [
                'error' => $request->input('error'),
                'error_description' => $request->input('error_description'),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan kirish rad etildi.']);
        }

        $code = $request->input('code');
        if (!$code) {
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan kod olinmadi.']);
        }

        $baseUrl = config('services.hemis_oauth.base_url');

        // 1-bosqich: Code ni access token ga almashtirish
        Log::channel('student_auth')->info('[HEMIS OAUTH] Token so\'ralmoqda', [
            'base_url' => $baseUrl,
            'ip' => $request->ip(),
        ]);

        $tokenResponse = Http::withoutVerifying()
            ->timeout(15)
            ->asForm()
            ->post("{$baseUrl}/oauth/access-token", [
                'grant_type' => 'authorization_code',
                'client_id' => config('services.hemis_oauth.client_id'),
                'client_secret' => config('services.hemis_oauth.client_secret'),
                'redirect_uri' => config('services.hemis_oauth.redirect_uri'),
                'code' => $code,
            ]);

        Log::channel('student_auth')->info('[HEMIS OAUTH] Token javobi', [
            'status' => $tokenResponse->status(),
            'body' => mb_substr($tokenResponse->body(), 0, 500),
        ]);

        if (!$tokenResponse->successful()) {
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan token olinmadi (status: ' . $tokenResponse->status() . ').']);
        }

        $accessToken = $tokenResponse->json('access_token');
        if (!$accessToken) {
            return redirect()->route('student.login')
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

        Log::channel('student_auth')->info('[HEMIS OAUTH] User javobi', [
            'status' => $userResponse->status(),
            'body' => mb_substr($userResponse->body(), 0, 1000),
        ]);

        if (!$userResponse->successful()) {
            return redirect()->route('student.login')
                ->withErrors(['login' => 'Foydalanuvchi ma\'lumotlarini olishda xatolik (status: ' . $userResponse->status() . ').']);
        }

        $userData = $userResponse->json();
        $hemisLogin = $userData['login'] ?? null;
        $hemisId = $userData['id'] ?? null;
        $studentIdNumber = $userData['student_id_number'] ?? $hemisLogin;

        Log::channel('student_auth')->info('[HEMIS OAUTH] Foydalanuvchi ma\'lumotlari olindi', [
            'hemis_id' => $hemisId,
            'login' => $hemisLogin,
            'student_id_number' => $studentIdNumber,
            'name' => $userData['name'] ?? $userData['full_name'] ?? null,
            'keys' => array_keys($userData),
        ]);

        if (!$studentIdNumber && !$hemisId) {
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan talaba ma\'lumotlari olinmadi. Kalitlar: ' . implode(', ', array_keys($userData))]);
        }

        // 3-bosqich: Bazada talabani topish (student_id_number yoki hemis_id bo'yicha)
        $student = Student::where('student_id_number', $studentIdNumber)->first();

        if (!$student && $hemisLogin && $hemisLogin !== $studentIdNumber) {
            $student = Student::where('student_id_number', $hemisLogin)->first();
        }

        if (!$student && $hemisId) {
            $student = Student::where('hemis_id', $hemisId)->first();
        }

        if (!$student) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Talaba bazada topilmadi', [
                'student_id_number' => $studentIdNumber,
                'hemis_id' => $hemisId,
                'login' => $hemisLogin,
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'Siz LMS tizimida ro\'yxatdan o\'tmagansiz (login: ' . $hemisLogin . ', id: ' . $hemisId . '). Admin bilan bog\'laning.']);
        }

        // Token saqlash (ixtiyoriy — ustun yo'q bo'lsa xato bermaydi)
        try {
            $student->update([
                'token' => $accessToken,
                'token_expires_at' => now()->addDays(7),
            ]);
        } catch (\Exception $e) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Token saqlashda xato (davom etiladi)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Boshqa guardlarni tozalash
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

        Auth::guard('student')->login($student);
        $request->session()->regenerate();

        try {
            ActivityLogService::logLogin('student');
        } catch (\Exception $e) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Activity log xatosi (davom etiladi)', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::channel('student_auth')->info('[LOGIN SUCCESS] Talaba HEMIS OAuth orqali kirdi', [
            'student_id' => $student->id,
            'student_id_number' => $student->student_id_number,
            'full_name' => $student->full_name,
            'auth_method' => 'hemis_oauth',
        ]);

        if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
            return redirect()->route('student.complete-profile');
        }

        return redirect()->intended(route('student.dashboard'));
    }
}

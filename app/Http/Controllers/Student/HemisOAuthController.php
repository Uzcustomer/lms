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
            'ip' => $request->ip(),
        ]);

        try {
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
        } catch (\Exception $e) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] Token so\'rovda xatolik', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS serveriga ulanishda xatolik.']);
        }

        if (!$tokenResponse->successful()) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Token olishda xatolik', [
                'status' => $tokenResponse->status(),
                'body' => mb_substr($tokenResponse->body(), 0, 500),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan token olinmadi.']);
        }

        $accessToken = $tokenResponse->json('access_token');
        if (!$accessToken) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Token javobda access_token topilmadi', [
                'body' => mb_substr($tokenResponse->body(), 0, 500),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan token olinmadi.']);
        }

        // 2-bosqich: Foydalanuvchi ma'lumotlarini olish
        Log::channel('student_auth')->info('[HEMIS OAUTH] Foydalanuvchi ma\'lumotlari so\'ralmoqda');

        try {
            $userResponse = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                ])->get("{$baseUrl}/oauth/api/user");
        } catch (\Exception $e) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] User ma\'lumotlari olishda xatolik', [
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS serveriga ulanishda xatolik.']);
        }

        if (!$userResponse->successful()) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] User ma\'lumotlari olinmadi', [
                'status' => $userResponse->status(),
                'body' => mb_substr($userResponse->body(), 0, 500),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'Foydalanuvchi ma\'lumotlarini olishda xatolik.']);
        }

        $userData = $userResponse->json();
        $studentIdNumber = $userData['student_id_number'] ?? ($userData['login'] ?? null);

        Log::channel('student_auth')->info('[HEMIS OAUTH] Foydalanuvchi ma\'lumotlari olindi', [
            'student_id_number' => $studentIdNumber,
            'name' => $userData['name'] ?? $userData['full_name'] ?? null,
        ]);

        if (!$studentIdNumber) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] student_id_number topilmadi', [
                'user_data_keys' => array_keys($userData),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan talaba raqami olinmadi.']);
        }

        // 3-bosqich: Bazada talabani topish
        $student = Student::where('student_id_number', $studentIdNumber)->first();

        if (!$student) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] Talaba bazada topilmadi', [
                'student_id_number' => $studentIdNumber,
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'Siz LMS tizimida ro\'yxatdan o\'tmagansiz. Admin bilan bog\'laning.']);
        }

        // Token saqlash
        $student->update([
            'token' => $accessToken,
            'token_expires_at' => now()->addDays(7),
        ]);

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
        ActivityLogService::logLogin('student');

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

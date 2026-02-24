<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;

class HemisOAuthController extends Controller
{
    private function getProvider(string $role): GenericProvider
    {
        $config = config('services.hemis_oauth');

        $redirectUri = $role === 'student'
            ? $config['redirect_student']
            : $config['redirect_teacher'];

        return new GenericProvider([
            'clientId'                => $config['client_id'],
            'clientSecret'            => $config['client_secret'],
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => $config['base_url'] . '/oauth/authorize',
            'urlAccessToken'          => $config['base_url'] . '/oauth/access-token',
            'urlResourceOwnerDetails' => $config['base_url'] . '/rest/v1/account/me',
        ]);
    }

    /**
     * HEMIS OAuth sahifasiga yo'naltirish (student)
     */
    public function redirectStudent(Request $request)
    {
        return $this->redirect($request, 'student');
    }

    /**
     * HEMIS OAuth sahifasiga yo'naltirish (teacher)
     */
    public function redirectTeacher(Request $request)
    {
        return $this->redirect($request, 'teacher');
    }

    /**
     * HEMIS OAuth callback (student)
     */
    public function callbackStudent(Request $request)
    {
        return $this->callback($request, 'student');
    }

    /**
     * HEMIS OAuth callback (teacher)
     */
    public function callbackTeacher(Request $request)
    {
        return $this->callback($request, 'teacher');
    }

    private function redirect(Request $request, string $role)
    {
        $provider = $this->getProvider($role);

        $authorizationUrl = $provider->getAuthorizationUrl();
        $request->session()->put('hemis_oauth_state', $provider->getState());
        $request->session()->put('hemis_oauth_role', $role);

        return redirect($authorizationUrl);
    }

    private function callback(Request $request, string $role)
    {
        $savedState = $request->session()->pull('hemis_oauth_state');

        // Xatolikni tekshirish
        if ($request->has('error')) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] Xatolik qaytdi', [
                'role' => $role,
                'error' => $request->input('error'),
                'error_description' => $request->input('error_description'),
            ]);
            return redirect()->route("{$role}.login")
                ->withErrors(['login' => 'HEMIS tizimiga kirishda xatolik yuz berdi. Qaytadan urinib ko\'ring.']);
        }

        // State tekshiruvi (CSRF himoyasi)
        if (empty($request->input('state')) || $request->input('state') !== $savedState) {
            Log::channel('student_auth')->warning('[HEMIS OAUTH] State mos kelmadi', [
                'role' => $role,
                'expected' => $savedState,
                'received' => $request->input('state'),
            ]);
            return redirect()->route("{$role}.login")
                ->withErrors(['login' => 'Sessiya muddati tugagan. Qaytadan urinib ko\'ring.']);
        }

        if (!$request->has('code')) {
            return redirect()->route("{$role}.login")
                ->withErrors(['login' => 'HEMIS tizimidan tasdiqlash kodi olinmadi.']);
        }

        try {
            $provider = $this->getProvider($role);

            // Authorization code orqali access token olish
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->input('code'),
            ]);

            Log::channel('student_auth')->info('[HEMIS OAUTH] Token olindi', [
                'role' => $role,
                'token_expires' => $accessToken->getExpires(),
            ]);

            // Foydalanuvchi ma'lumotlarini olish
            $resourceOwner = $provider->getResourceOwner($accessToken);
            $userData = $resourceOwner->toArray();

            // data ichida bo'lishi mumkin
            if (isset($userData['data'])) {
                $userData = $userData['data'];
            }

            Log::channel('student_auth')->info('[HEMIS OAUTH] Foydalanuvchi ma\'lumotlari olindi', [
                'role' => $role,
                'hemis_id' => $userData['id'] ?? null,
                'student_id_number' => $userData['student_id_number'] ?? null,
                'employee_id_number' => $userData['employee_id_number'] ?? null,
            ]);

            if ($role === 'student') {
                return $this->loginStudent($request, $userData, $accessToken->getToken());
            } else {
                return $this->loginTeacher($request, $userData, $accessToken->getToken());
            }

        } catch (\Exception $e) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] Token olishda xatolik', [
                'role' => $role,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);
            return redirect()->route("{$role}.login")
                ->withErrors(['login' => 'HEMIS tizimiga ulanishda xatolik. Qaytadan urinib ko\'ring.']);
        }
    }

    private function loginStudent(Request $request, array $userData, string $token)
    {
        $studentIdNumber = $userData['student_id_number'] ?? null;

        if (!$studentIdNumber) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] student_id_number topilmadi', [
                'hemis_data_keys' => array_keys($userData),
            ]);
            return redirect()->route('student.login')
                ->withErrors(['login' => 'HEMIS tizimidan talaba ma\'lumotlari olinmadi.']);
        }

        $student = Student::updateOrCreate(
            ['student_id_number' => $studentIdNumber],
            [
                'token' => $token,
                'token_expires_at' => now()->addDays(7),
                'must_change_password' => false,
            ]
        );

        // Boshqa guardlarni tozalash
        $this->clearOtherGuards($request, 'student');

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

    private function loginTeacher(Request $request, array $userData, string $token)
    {
        // Teacher'ni HEMIS ID yoki employee_id_number orqali topish
        $teacher = null;
        if (isset($userData['employee_id_number'])) {
            $teacher = Teacher::where('employee_id_number', $userData['employee_id_number'])->first();
        }
        if (!$teacher && isset($userData['id'])) {
            $teacher = Teacher::where('hemis_id', $userData['id'])->first();
        }

        if (!$teacher) {
            Log::channel('student_auth')->error('[HEMIS OAUTH] Teacher bazada topilmadi', [
                'employee_id_number' => $userData['employee_id_number'] ?? null,
                'hemis_id' => $userData['id'] ?? null,
            ]);
            return redirect()->route('teacher.login')
                ->withErrors(['login' => 'Sizning ma\'lumotlaringiz tizimda topilmadi. Admin bilan bog\'laning.']);
        }

        // Boshqa guardlarni tozalash
        $this->clearOtherGuards($request, 'teacher');

        Auth::guard('teacher')->login($teacher);
        $request->session()->regenerate();

        // Active role ni o'rnatish
        $roles = $teacher->getRoleNames();
        if ($roles->isNotEmpty()) {
            session(['active_role' => $roles->first()]);
        } else {
            session(['active_role' => 'oqituvchi']);
        }

        ActivityLogService::logLogin('teacher');

        Log::channel('student_auth')->info('[LOGIN SUCCESS] Teacher HEMIS OAuth orqali kirdi', [
            'teacher_id' => $teacher->id,
            'employee_id_number' => $teacher->employee_id_number,
            'full_name' => $teacher->full_name,
            'auth_method' => 'hemis_oauth',
        ]);

        if ($teacher->must_change_password) {
            return redirect()->route('teacher.force-change-password');
        }

        if (method_exists($teacher, 'isProfileComplete') && !$teacher->isProfileComplete()) {
            return redirect()->route('teacher.complete-profile');
        }

        return redirect()->intended(route('teacher.dashboard'));
    }

    /**
     * Boshqa guardlarni tozalash
     */
    private function clearOtherGuards(Request $request, string $currentGuard): void
    {
        $guards = ['web', 'student', 'teacher'];

        foreach ($guards as $guard) {
            if ($guard !== $currentGuard && Auth::guard($guard)->check()) {
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

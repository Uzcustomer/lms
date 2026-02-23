<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ActivityLogService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    /**
     * Student login — tries HEMIS first, falls back to local password
     */
    public function studentLogin(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $student = null;
        $hemisLogin = false;
        $clientIp = $request->ip();

        // Try HEMIS authentication first
        $response = Http::withoutVerifying()
            ->withHeaders([
                'X-Forwarded-For' => $clientIp,
                'X-Real-IP' => $clientIp,
            ])
            ->post('https://student.ttatf.uz/rest/v1/auth/login', [
                'login' => $request->login,
                'password' => $request->password,
            ]);

        if ($response->successful() && $response->json('success')) {
            $hemisToken = $response->json('data.token');

            $studentDataResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $hemisToken,
                'X-Forwarded-For' => $clientIp,
                'X-Real-IP' => $clientIp,
            ])->get('https://student.ttatf.uz/rest/v1/account/me');

            if ($studentDataResponse->successful()) {
                $studentData = $studentDataResponse->json('data');

                $student = Student::updateOrCreate(
                    ['student_id_number' => $studentData['student_id_number']],
                    [
                        'token' => $hemisToken,
                        'token_expires_at' => now()->addDays(7),
                        'must_change_password' => false,
                    ]
                );
                $hemisLogin = true;
            }
        }

        // Fallback to local password
        if (!$student) {
            $student = Student::where('student_id_number', $request->login)->first();

            if (
                !$student ||
                !$student->local_password ||
                ($student->local_password_expires_at && $student->local_password_expires_at->isPast()) ||
                !Hash::check($request->password, $student->local_password)
            ) {
                return response()->json([
                    'message' => "Login yoki parol noto'g'ri.",
                ], 401);
            }
        }

        // Check if Telegram 2FA is needed
        if ($student->telegram_chat_id) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $student->login_code = $code;
            $student->login_code_expires_at = now()->addMinutes(5);
            $student->save();

            $telegramService = new TelegramService();
            $sent = $telegramService->sendToUser(
                $student->telegram_chat_id,
                "Tizimga kirish uchun tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
            );

            if ($sent) {
                return response()->json([
                    'requires_2fa' => true,
                    'student_id' => $student->id,
                    'masked_telegram' => substr($student->telegram_username ?? 'Telegram', 0, 3) . '***',
                    'message' => 'Tasdiqlash kodi Telegramga yuborildi.',
                ]);
            }
        }

        // No 2FA needed — issue token
        return $this->issueStudentToken($student);
    }

    /**
     * Verify Telegram 2FA code for student
     */
    public function studentVerify2fa(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $student = Student::find($request->student_id);

        if (!$student) {
            return response()->json(['message' => 'Talaba topilmadi.'], 404);
        }

        if (!$student->login_code_expires_at || $student->login_code_expires_at->isPast()) {
            $student->login_code = null;
            $student->login_code_expires_at = null;
            $student->save();
            return response()->json(['message' => 'Tasdiqlash kodi muddati tugagan.'], 422);
        }

        if ($student->login_code !== $request->code) {
            return response()->json(['message' => "Tasdiqlash kodi noto'g'ri."], 422);
        }

        $student->login_code = null;
        $student->login_code_expires_at = null;
        $student->save();

        return $this->issueStudentToken($student);
    }

    /**
     * Resend 2FA code for student
     */
    public function studentResend2fa(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'integer'],
        ]);

        $student = Student::find($request->student_id);

        if (!$student || !$student->telegram_chat_id) {
            return response()->json(['message' => 'Talaba topilmadi.'], 404);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $student->login_code = $code;
        $student->login_code_expires_at = now()->addMinutes(5);
        $student->save();

        $telegramService = new TelegramService();
        $telegramService->sendToUser(
            $student->telegram_chat_id,
            "Yangi tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
        );

        return response()->json(['message' => 'Yangi tasdiqlash kodi yuborildi.']);
    }

    /**
     * Teacher login
     */
    public function teacherLogin(Request $request): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $teacher = Teacher::where('login', $request->login)->first();

        if (!$teacher || !Hash::check($request->password, $teacher->password)) {
            return response()->json([
                'message' => "Login yoki parol noto'g'ri.",
            ], 401);
        }

        // Check if Telegram 2FA is needed
        if ($teacher->telegram_chat_id) {
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $teacher->login_code = $code;
            $teacher->login_code_expires_at = now()->addMinutes(5);
            $teacher->save();

            $telegramService = new TelegramService();
            $sent = $telegramService->sendToUser(
                $teacher->telegram_chat_id,
                "Tizimga kirish uchun tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
            );

            if ($sent) {
                return response()->json([
                    'requires_2fa' => true,
                    'teacher_id' => $teacher->id,
                    'masked_telegram' => substr($teacher->telegram_username ?? 'Telegram', 0, 3) . '***',
                    'message' => 'Tasdiqlash kodi Telegramga yuborildi.',
                ]);
            }
        }

        return $this->issueTeacherToken($teacher);
    }

    /**
     * Verify Telegram 2FA code for teacher
     */
    public function teacherVerify2fa(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => ['required', 'integer'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $teacher = Teacher::find($request->teacher_id);

        if (!$teacher) {
            return response()->json(['message' => "O'qituvchi topilmadi."], 404);
        }

        if (!$teacher->login_code_expires_at || $teacher->login_code_expires_at->isPast()) {
            $teacher->login_code = null;
            $teacher->login_code_expires_at = null;
            $teacher->save();
            return response()->json(['message' => 'Tasdiqlash kodi muddati tugagan.'], 422);
        }

        if ($teacher->login_code !== $request->code) {
            return response()->json(['message' => "Tasdiqlash kodi noto'g'ri."], 422);
        }

        $teacher->login_code = null;
        $teacher->login_code_expires_at = null;
        $teacher->save();

        return $this->issueTeacherToken($teacher);
    }

    /**
     * Resend 2FA code for teacher
     */
    public function teacherResend2fa(Request $request): JsonResponse
    {
        $request->validate([
            'teacher_id' => ['required', 'integer'],
        ]);

        $teacher = Teacher::find($request->teacher_id);

        if (!$teacher || !$teacher->telegram_chat_id) {
            return response()->json(['message' => "O'qituvchi topilmadi."], 404);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $teacher->login_code = $code;
        $teacher->login_code_expires_at = now()->addMinutes(5);
        $teacher->save();

        $telegramService = new TelegramService();
        $telegramService->sendToUser(
            $teacher->telegram_chat_id,
            "Yangi tasdiqlash kodi: {$code}\n\nKod 5 daqiqa amal qiladi."
        );

        return response()->json(['message' => 'Yangi tasdiqlash kodi yuborildi.']);
    }

    /**
     * Logout — revoke current token
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Tizimdan chiqdingiz.']);
    }

    /**
     * Get current user profile
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $guard = $user instanceof Student ? 'student' : 'teacher';

        return response()->json([
            'user' => $user,
            'guard' => $guard,
            'roles' => $user->getRoleNames(),
        ]);
    }

    // --- Private Helpers ---

    private function issueStudentToken(Student $student): JsonResponse
    {
        // Revoke old tokens
        $student->tokens()->delete();

        $token = $student->createToken('mobile-app', ['student'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $student,
            'guard' => 'student',
            'must_change_password' => (bool) $student->must_change_password,
            'profile_complete' => $student->isProfileComplete(),
            'telegram_verified' => $student->isTelegramVerified(),
            'telegram_days_left' => $student->telegramDaysLeft(),
            'bot_username' => config('services.telegram.bot_username', ''),
        ]);
    }

    private function issueTeacherToken(Teacher $teacher): JsonResponse
    {
        // Revoke old tokens
        $teacher->tokens()->delete();

        $token = $teacher->createToken('mobile-app', ['teacher'], now()->addDays(30));

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $teacher,
            'guard' => 'teacher',
            'roles' => $teacher->getRoleNames(),
            'must_change_password' => (bool) $teacher->must_change_password,
            'profile_complete' => $teacher->isProfileComplete(),
            'telegram_verified' => $teacher->isTelegramVerified(),
        ]);
    }

    /**
     * Image proxy — fetch external image to avoid CORS on web.
     */
    public function imageProxy(Request $request)
    {
        $url = $request->query('url');
        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL'], 400);
        }

        // Only allow image URLs from trusted HEMIS domains
        $host = parse_url($url, PHP_URL_HOST);
        $allowed = ['hemis.ttatf.uz', 'student.hemis.uz', 'hemis.uz'];
        if (!in_array($host, $allowed)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        try {
            $response = Http::timeout(10)->get($url);
            if (!$response->successful()) {
                return response()->json(['error' => 'Failed to fetch image'], 502);
            }

            $contentType = $response->header('Content-Type') ?? 'image/jpeg';

            return response($response->body(), 200)
                ->header('Content-Type', $contentType)
                ->header('Cache-Control', 'public, max-age=86400');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch image'], 502);
        }
    }
}

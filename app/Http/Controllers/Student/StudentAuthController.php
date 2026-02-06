<?php

namespace App\Http\Controllers\Student;


use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $response = Http::withoutVerifying()->post('https://student.ttatf.uz/rest/v1/auth/login', [
            'login' => $request->login,
            'password' => $request->password,
        ]);

        if ($response->successful() && $response->json('success')) {
            $token = $response->json('data.token');

            $refreshToken = Cookie::get('refresh-token');

            $studentDataResponse = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->get('https://student.ttatf.uz/rest/v1/account/me');

            if ($studentDataResponse->successful()) {
                $studentData = $studentDataResponse->json('data');

                $student = Student::updateOrCreate(
                    ['student_id_number' => $studentData['student_id_number']],
                    [
                        'token' => $token,
                        'token_expires_at' => now()->addDays(7),
                        'must_change_password' => false,
                    ]
                );

                Auth::guard('student')->login($student);

                return redirect()->intended(route('student.dashboard'))->with('studentData', $studentData);
            } else {
                return back()->withErrors(['login' => 'Talabaning ma\'lumotlarini olishda xatolik.']);
            }
        } else {
            $student = Student::where('student_id_number', $request->login)->first();

            if (
                $student &&
                $student->local_password &&
                (!$student->local_password_expires_at || $student->local_password_expires_at->isFuture()) &&
                Hash::check($request->password, $student->local_password)
            ) {
                Auth::guard('student')->login($student);

                if ($student->must_change_password) {
                    return redirect()->route('student.password.edit');
                }

                return redirect()->intended(route('student.dashboard'));
            }

            return back()->withErrors(['login' => "Login yoki parol noto'g'ri."]);
        }
    }

    public function editPassword()
    {
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

        $student->local_password = Hash::make($request->password);
        $student->local_password_expires_at = now()->addDays($changedPasswordDays);
        $student->must_change_password = false;
        $student->save();

        return redirect()->route('student.dashboard')->with('success', "Parol muvaffaqiyatli yangilandi. Parol {$changedPasswordDays} kun amal qiladi. Shu muddat ichida HEMIS parolingizni tiklang.");
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
        $student = Auth::guard('student')->user();
        if ($student) {
            $student->token = null;
            $student->token_expires_at = null;
            $student->save();
        }

        Auth::guard('student')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

<?php

namespace App\Http\Controllers\Student;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cookie;

class StudentAuthController extends Controller
{
    public function login(Request $request)
    {
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
                    ]
                );

                Auth::guard('student')->login($student);

                return redirect()->route('student.dashboard')->with('studentData', $studentData);
            } else {
                return back()->withErrors(['login' => 'Talabaning ma\'lumotlarini olishda xatolik.']);
            }
        } else {
            return back()->withErrors(['login' => "Login yoki parol noto'g'ri."]);
        }
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

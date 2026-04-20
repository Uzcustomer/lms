<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\ActivityLogService;
use App\Services\FaceIdService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FaceIdController extends Controller
{
    /**
     * Face ID login sahifasini ko'rsatish.
     */
    public function showPage()
    {
        if (Auth::guard('student')->check()) {
            return redirect()->route('student.dashboard');
        }

        if (!FaceIdService::isGloballyEnabled()) {
            return redirect()->route('student.login')
                ->with('info', 'Face ID hozircha o\'chirilgan. Odatiy parol bilan kiring.');
        }

        $settings = FaceIdService::getSettings();
        return view('student.face-login', compact('settings'));
    }

    /**
     * Talabani student_id_number bo'yicha tekshirish.
     * Mavjud bo'lsa — foto URL va Face ID holati qaytariladi.
     */
    public function checkStudent(Request $request)
    {
        $request->validate(['student_id_number' => 'required|string|max:50']);

        $idNumber = trim($request->student_id_number);

        // Rate limit: 10 urinish / daqiqa bitta IP uchun
        $key = 'faceid_check:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['error' => 'Juda ko\'p so\'rov. Biroz kuting.'], 429);
        }
        RateLimiter::hit($key, 60);

        $student = Student::where('student_id_number', $idNumber)->first();

        if (!$student) {
            return response()->json(['error' => 'Talaba topilmadi.'], 404);
        }

        if (!FaceIdService::isEnabledForStudent($student)) {
            return response()->json(['error' => 'Bu talaba uchun Face ID o\'chirilgan. Parol bilan kiring.'], 403);
        }

        // Saqlangan deskriptor bormi?
        $hasDescriptor = FaceIdService::getDescriptor($student) !== null;

        return response()->json([
            'student_id'     => $student->id,
            'full_name'      => $student->full_name,
            'photo_url'      => route('student.face-id.photo', ['id' => $student->id]),
            'has_descriptor' => $hasDescriptor,
        ]);
    }

    /**
     * Talaba rasmini proxy orqali berish (CORS muammosini hal qilish).
     */
    public function getPhoto(int $id)
    {
        $student = Student::find($id);

        if (!$student || empty($student->image)) {
            abort(404, 'Rasm topilmadi');
        }

        $photo = FaceIdService::fetchStudentPhoto($student);

        if (!$photo) {
            abort(404, 'Rasm yuklab bo\'lmadi');
        }

        return response($photo['content'])
            ->header('Content-Type', $photo['mime'])
            ->header('Cache-Control', 'private, max-age=3600')
            ->header('Access-Control-Allow-Origin', '*');
    }

    /**
     * Talabaning yuz deskriptorini saqlash (enrollment).
     * Admin yoki talabaning o'zi descriptor yuboradi.
     */
    public function saveDescriptor(Request $request)
    {
        $request->validate([
            'student_id'  => 'required|integer',
            'descriptor'  => 'required|array|size:128',
            'source_url'  => 'nullable|string|max:500',
        ]);

        $student = Student::find($request->student_id);
        if (!$student) {
            return response()->json(['error' => 'Talaba topilmadi'], 404);
        }

        FaceIdService::saveDescriptor($student, $request->descriptor, $request->source_url);

        return response()->json(['success' => true, 'message' => 'Deskriptor saqlandi']);
    }

    /**
     * Face ID tekshiruvi natijasini qabul qilish va login yaratish.
     *
     * Ikkita rejim:
     *  1. client_only = true  → brauzer o'zi distance hisoblagan (test rejimi)
     *  2. client_only = false → server saqlangan descriptor bilan taqqoslaydi
     */
    public function verifyAndLogin(Request $request)
    {
        $request->validate([
            'student_id_number' => 'required|string|max:50',
            'liveness_passed'   => 'required|boolean',
            'descriptor'        => 'nullable|array|size:128',  // live face descriptor
            'distance'          => 'nullable|numeric|min:0|max:2',
            'confidence'        => 'nullable|numeric|min:0|max:100',
            'snapshot'          => 'nullable|string|max:200000',
        ]);

        $idNumber = trim($request->student_id_number);

        // Rate limit: 5 urinish / daqiqa bitta IP uchun
        $key = 'faceid_verify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => 'Juda ko\'p urinish. 1 daqiqa kuting.'], 429);
        }
        RateLimiter::hit($key, 60);

        $commonLog = [
            'ip_address'        => $request->ip(),
            'user_agent'        => $request->userAgent(),
            'student_id_number' => $idNumber,
        ];

        // 1. Talabani topish
        $student = Student::where('student_id_number', $idNumber)->first();

        if (!$student) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'not_found',
                'failure_reason' => 'Talaba bazada topilmadi',
            ]));
            return response()->json(['error' => 'Talaba topilmadi.'], 404);
        }

        $commonLog['student_id'] = $student->id;

        // 2. Face ID yoqilganmi?
        if (!FaceIdService::isEnabledForStudent($student)) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'disabled',
                'failure_reason' => 'Face ID o\'chirilgan',
            ]));
            return response()->json(['error' => 'Face ID o\'chirilgan. Parol bilan kiring.'], 403);
        }

        // 3. Jonlilik tekshiruvi
        if (!$request->liveness_passed) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'liveness_failed',
                'failure_reason' => 'Liveness tekshiruvi muvaffaqiyatsiz',
                'snapshot'       => $request->snapshot,
            ]));
            return response()->json(['success' => false, 'message' => 'Jonlilik tekshiruvi o\'tmadi. Iltimos, ko\'zingizni yumib-oching va boshingizni burting.'], 422);
        }

        $threshold = FaceIdService::getThreshold();

        // 4. Server-side descriptor taqqoslash (agar saqlangan bo'lsa)
        if ($request->descriptor) {
            $storedDescriptor = FaceIdService::getDescriptor($student);

            if ($storedDescriptor) {
                // Server o'zi hisoblaydi — ishonchli
                $distance   = FaceIdService::euclideanDistance($request->descriptor, $storedDescriptor);
                $confidence = FaceIdService::distanceToConfidence($distance);

                Log::info('[FaceID] Server-side taqqoslash', [
                    'student' => $idNumber,
                    'distance' => round($distance, 4),
                    'confidence' => round($confidence, 1),
                    'threshold' => $threshold,
                ]);

                if ($distance > $threshold) {
                    FaceIdService::logAttempt(array_merge($commonLog, [
                        'result'         => 'failed',
                        'confidence'     => round($confidence / 100, 4),
                        'distance'       => round($distance, 4),
                        'failure_reason' => "Yuz mos kelmadi (distance={$distance}, threshold={$threshold})",
                        'snapshot'       => $request->snapshot,
                    ]));
                    return response()->json([
                        'success'    => false,
                        'message'    => 'Yuz mos kelmadi. Iltimos, qayta urinib ko\'ring.',
                        'distance'   => round($distance, 4),
                        'confidence' => round($confidence, 1),
                    ], 422);
                }
            } else {
                // Saqlangan descriptor yo'q — client tomonidan yuborilgan natijaga ishonish (test rejimi)
                $distance   = $request->distance   ?? 1.0;
                $confidence = $request->confidence ?? 0.0;

                if ($distance > $threshold) {
                    FaceIdService::logAttempt(array_merge($commonLog, [
                        'result'         => 'failed',
                        'confidence'     => round(($confidence) / 100, 4),
                        'distance'       => round($distance, 4),
                        'failure_reason' => "Yuz mos kelmadi (client-side, no stored descriptor)",
                        'snapshot'       => $request->snapshot,
                    ]));
                    return response()->json([
                        'success'    => false,
                        'message'    => 'Yuz mos kelmadi.',
                        'distance'   => round($distance, 4),
                        'confidence' => round($confidence, 1),
                    ], 422);
                }
            }
        } else {
            // Descriptor yuborilmagan
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'failed',
                'failure_reason' => 'Descriptor yuborilmadi',
                'snapshot'       => $request->snapshot,
            ]));
            return response()->json(['error' => 'Yuz ma\'lumotlari topilmadi.'], 422);
        }

        // 5. Muvaffaqiyatli — login
        $finalDistance   = isset($distance) ? round($distance, 4) : ($request->distance ?? null);
        $finalConfidence = isset($confidence) ? round($confidence / 100, 4) : (($request->confidence ?? 0) / 100);

        FaceIdService::logAttempt(array_merge($commonLog, [
            'result'     => 'success',
            'confidence' => $finalConfidence,
            'distance'   => $finalDistance,
            'snapshot'   => $request->snapshot,
        ]));

        // Boshqa guard sessionlarni tozalash
        foreach (['web', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        Auth::guard('student')->login($student);
        $request->session()->regenerate();
        ActivityLogService::logLogin('student');

        Log::info('[FaceID] Login muvaffaqiyatli', [
            'student'    => $idNumber,
            'distance'   => $finalDistance,
            'confidence' => round($finalConfidence * 100, 1),
        ]);

        // Parol o'zgartirish yoki profil to'ldirish kerakmi?
        if ($student->must_change_password) {
            return response()->json(['success' => true, 'redirect' => route('student.password.edit')]);
        }

        if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
            return response()->json(['success' => true, 'redirect' => route('student.complete-profile')]);
        }

        return response()->json(['success' => true, 'redirect' => route('student.dashboard')]);
    }
}

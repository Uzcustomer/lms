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

        // ArcFace yoqilgan bo'lsa — student_photos da tasdiqlangan rasm bo'lishi shart
        if (FaceIdService::isArcFaceEnabled() && !FaceIdService::hasApprovedPhoto($student)) {
            return response()->json([
                'error' => 'Sizning rasmingiz hali tasdiqlanmagan. Face ID orqali kirish uchun avval tutoringizdan rasm yuklattiring. Hozircha login va parol yoki HEMIS orqali kiring.',
            ], 403);
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
     * 1:N identifikatsiya va login.
     * Talaba ID kerak emas — server kelgan rasm uchun ArcFace embedding'ni
     * cache'dagi barcha tasdiqlangan talabalar bilan solishtiradi va eng yaqinini
     * login qiladi (similarity_percent >= threshold bo'lsa).
     */
    public function identifyAndLogin(Request $request)
    {
        $request->validate([
            'snapshot' => 'required|string|max:500000',
        ]);

        // Rate limit
        $key = 'faceid_identify:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['error' => 'Juda ko\'p urinish. 1 daqiqa kuting.'], 429);
        }
        RateLimiter::hit($key, 60);

        if (!FaceIdService::isGloballyEnabled() || !FaceIdService::isArcFaceEnabled()) {
            return response()->json(['error' => 'Face ID hozircha o\'chirilgan.'], 403);
        }

        $commonLog = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Live snapshot ni vaqtinchalik faylga yozish
        $liveTmp = FaceIdService::saveTemporarySnapshot($request->snapshot);
        if (!$liveTmp) {
            return response()->json(['error' => 'Yuz rasmini saqlashda xato. Qayta urinib ko\'ring.'], 422);
        }

        try {
            $result = FaceIdService::identifyViaArcFace($liveTmp['url'], 1);
        } finally {
            FaceIdService::deleteTemporarySnapshot($liveTmp['rel']);
        }

        if (!$result || empty($result['matches'])) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'failed',
                'failure_reason' => 'ArcFace identify javob bermadi yoki cache bo\'sh',
                'snapshot'       => $request->snapshot,
            ]));
            return response()->json(['error' => 'Yuz tekshirish xizmati javob bermadi.'], 503);
        }

        $best = $result['matches'][0];
        $sid = $best['student_id_number'] ?? null;
        $similarityPercent = (float) ($best['similarity_percent'] ?? 0);

        $student = $sid ? Student::where('student_id_number', $sid)->first() : null;
        if (!$student) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'student_id_number' => $sid,
                'result'            => 'not_found',
                'failure_reason'    => 'Cache mos kelgan, lekin DB da talaba topilmadi',
                'snapshot'          => $request->snapshot,
            ]));
            return response()->json(['error' => 'Talaba topilmadi.'], 404);
        }

        $commonLog['student_id'] = $student->id;
        $commonLog['student_id_number'] = $student->student_id_number;

        if (!FaceIdService::isEnabledForStudent($student)) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'disabled',
                'failure_reason' => 'Face ID o\'chirilgan',
                'snapshot'       => $request->snapshot,
            ]));
            return response()->json(['error' => 'Bu talaba uchun Face ID o\'chirilgan.'], 403);
        }

        $threshold = FaceIdService::getArcFaceThreshold();
        Log::info('[FaceID/Identify] Eng yaqin match', [
            'student'    => $sid,
            'similarity' => round($similarityPercent, 2),
            'threshold'  => $threshold,
            'cache_size' => $result['cache_size'] ?? null,
        ]);

        if ($similarityPercent < $threshold) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result'         => 'failed',
                'confidence'     => round($similarityPercent / 100, 4),
                'failure_reason' => "Yuz topildi, lekin similarity past ({$similarityPercent}% < {$threshold}%)",
                'snapshot'       => $request->snapshot,
            ]));
            return response()->json([
                'success'    => false,
                'message'    => 'Yuz to\'g\'ri tanilmadi. Iltimos, yorug\' joyda kameraga to\'g\'ri qarab qayta urinib ko\'ring.',
                'confidence' => round($similarityPercent, 1),
            ], 422);
        }

        // Muvaffaqiyatli — login
        FaceIdService::logAttempt(array_merge($commonLog, [
            'result'     => 'success',
            'confidence' => round($similarityPercent / 100, 4),
            'snapshot'   => $request->snapshot,
        ]));

        foreach (['web', 'teacher'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::guard($guard)->logout();
            }
        }

        Auth::guard('student')->login($student);
        $request->session()->regenerate();
        ActivityLogService::logLogin('student');

        Log::info('[FaceID/Identify] Login muvaffaqiyatli', [
            'student'   => $sid,
            'similarity' => round($similarityPercent, 1),
        ]);

        if ($student->must_change_password) {
            return response()->json(['success' => true, 'redirect' => route('student.password.edit'), 'student_name' => $student->full_name, 'confidence' => round($similarityPercent, 1)]);
        }

        if (!$student->isProfileComplete() || $student->isTelegramDeadlinePassed()) {
            return response()->json(['success' => true, 'redirect' => route('student.complete-profile'), 'student_name' => $student->full_name, 'confidence' => round($similarityPercent, 1)]);
        }

        return response()->json(['success' => true, 'redirect' => route('student.dashboard'), 'student_name' => $student->full_name, 'confidence' => round($similarityPercent, 1)]);
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

        // 4. ArcFace (server-side, Python service) orqali taqqoslash
        // student_photos dagi tasdiqlangan rasm bilan live snapshot solishtiriladi
        $arcFaceEnabled = FaceIdService::isArcFaceEnabled();
        $usedArcFace = false;
        $distance = null;
        $confidence = null;

        if ($arcFaceEnabled) {
            $approvedPhoto = FaceIdService::getApprovedStudentPhoto($student);
            if (!$approvedPhoto) {
                FaceIdService::logAttempt(array_merge($commonLog, [
                    'result'         => 'failed',
                    'failure_reason' => 'student_photos da tasdiqlangan rasm yo\'q',
                    'snapshot'       => $request->snapshot,
                ]));
                return response()->json([
                    'error' => 'Sizning rasmingiz hali tasdiqlanmagan. Parol orqali kiring.',
                ], 403);
            }

            if (empty($request->snapshot)) {
                FaceIdService::logAttempt(array_merge($commonLog, [
                    'result'         => 'failed',
                    'failure_reason' => 'Live snapshot yuborilmadi',
                ]));
                return response()->json(['error' => 'Yuz ma\'lumotlari topilmadi.'], 422);
            }

            // Live snapshot ni vaqtinchalik faylga yozish
            $liveTmp = FaceIdService::saveTemporarySnapshot($request->snapshot);
            if (!$liveTmp) {
                return response()->json(['error' => 'Yuz rasmini saqlashda xato. Qayta urinib ko\'ring.'], 422);
            }

            try {
                $referenceUrl = asset($approvedPhoto->photo_path);
                $compareResult = FaceIdService::compareViaArcFace($liveTmp['url'], $referenceUrl);
            } finally {
                FaceIdService::deleteTemporarySnapshot($liveTmp['rel']);
            }

            if (!$compareResult) {
                FaceIdService::logAttempt(array_merge($commonLog, [
                    'result'         => 'failed',
                    'failure_reason' => 'ArcFace service javob bermadi',
                    'snapshot'       => $request->snapshot,
                ]));
                return response()->json(['error' => 'Yuz tekshirish xizmati javob bermadi. Birozdan keyin qayta urinib ko\'ring yoki parol bilan kiring.'], 503);
            }

            $usedArcFace = true;
            $similarityPercent = $compareResult['similarity_percent'];
            $arcThreshold = FaceIdService::getArcFaceThreshold();
            $distance = $compareResult['distance'];
            $confidence = $similarityPercent;

            Log::info('[FaceID/ArcFace] Taqqoslash', [
                'student'    => $idNumber,
                'similarity' => round($similarityPercent, 2),
                'threshold'  => $arcThreshold,
                'distance'   => round($distance, 4),
                'match'      => $compareResult['match'],
            ]);

            if ($similarityPercent < $arcThreshold) {
                FaceIdService::logAttempt(array_merge($commonLog, [
                    'result'         => 'failed',
                    'confidence'     => round($similarityPercent / 100, 4),
                    'distance'       => round($distance, 4),
                    'failure_reason' => "ArcFace: yuz mos kelmadi ({$similarityPercent}% < {$arcThreshold}%)",
                    'snapshot'       => $request->snapshot,
                ]));
                return response()->json([
                    'success'    => false,
                    'message'    => 'Yuz mos kelmadi. Iltimos, kameraga to\'g\'ridan qarab qayta urinib ko\'ring.',
                    'distance'   => round($distance, 4),
                    'confidence' => round($similarityPercent, 1),
                ], 422);
            }
        } else {
            // Eski mexanizm: face-api.js descriptor (fallback)
            $threshold = FaceIdService::getThreshold();
            if (!$request->descriptor) {
                FaceIdService::logAttempt(array_merge($commonLog, [
                    'result'         => 'failed',
                    'failure_reason' => 'Descriptor yuborilmadi',
                    'snapshot'       => $request->snapshot,
                ]));
                return response()->json(['error' => 'Yuz ma\'lumotlari topilmadi.'], 422);
            }

            $storedDescriptor = FaceIdService::getDescriptor($student);
            if ($storedDescriptor) {
                $distance   = FaceIdService::euclideanDistance($request->descriptor, $storedDescriptor);
                $confidence = FaceIdService::distanceToConfidence($distance);
            } else {
                $distance   = $request->distance ?? 1.0;
                $confidence = $request->confidence ?? 0.0;
            }

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
        }

        // 5. Muvaffaqiyatli — login
        $finalDistance   = $distance !== null ? round($distance, 4) : null;
        $finalConfidence = $confidence !== null ? round($confidence / 100, 4) : 0;

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
            'method'     => $usedArcFace ? 'arcface' : 'face-api',
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

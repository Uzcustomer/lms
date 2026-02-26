<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StudentApiController;
use App\Http\Controllers\Api\V1\TeacherApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — /api/v1/...
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public (no auth) ──────────────────────────────────
    Route::post('/student/login', [AuthController::class, 'studentLogin']);
    Route::post('/student/verify-2fa', [AuthController::class, 'studentVerify2fa']);
    Route::post('/student/resend-2fa', [AuthController::class, 'studentResend2fa']);

    Route::post('/teacher/login', [AuthController::class, 'teacherLogin']);
    Route::post('/teacher/verify-2fa', [AuthController::class, 'teacherVerify2fa']);
    Route::post('/teacher/resend-2fa', [AuthController::class, 'teacherResend2fa']);

    // ── Image proxy (no auth, domain-restricted) ──────────
    Route::get('/image-proxy', [AuthController::class, 'imageProxy']);

    // ── Authenticated (Sanctum) ───────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Common
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Student endpoints ─────────────────────────────
        Route::prefix('student')->group(function () {
            Route::get('/dashboard', [StudentApiController::class, 'dashboard']);
            Route::get('/profile', [StudentApiController::class, 'profile']);
            Route::get('/schedule', [StudentApiController::class, 'schedule']);
            Route::get('/subjects', [StudentApiController::class, 'subjects']);
            Route::get('/subjects/{subjectId}/grades', [StudentApiController::class, 'subjectGrades']);
            Route::post('/subjects/{subjectId}/mt-upload', [StudentApiController::class, 'mtUpload']);
            Route::get('/subjects/{subjectId}/mt-submissions', [StudentApiController::class, 'mtSubmissions']);
            Route::get('/pending-lessons', [StudentApiController::class, 'pendingLessons']);
            Route::get('/attendance', [StudentApiController::class, 'attendance']);

            // Sababli ariza (excuse requests)
            Route::get('/excuse-requests', [StudentApiController::class, 'excuseRequests']);
            Route::post('/excuse-requests', [StudentApiController::class, 'createExcuseRequest']);

            // Profile completion
            Route::post('/complete-profile/phone', [StudentApiController::class, 'savePhone']);
            Route::post('/complete-profile/telegram', [StudentApiController::class, 'saveTelegram']);
            Route::get('/complete-profile/telegram/check', [StudentApiController::class, 'checkTelegramVerification']);
        });

        // ── Teacher endpoints ─────────────────────────────
        Route::prefix('teacher')->group(function () {
            Route::get('/dashboard', [TeacherApiController::class, 'dashboard']);
            Route::get('/profile', [TeacherApiController::class, 'profile']);
            Route::get('/students', [TeacherApiController::class, 'students']);
            Route::get('/groups', [TeacherApiController::class, 'groups']);
            Route::get('/semesters', [TeacherApiController::class, 'semesters']);
            Route::get('/subjects', [TeacherApiController::class, 'subjects']);
            Route::get('/students/{studentId}/subjects/{subjectId}/grades', [TeacherApiController::class, 'studentGradeDetails']);
            Route::get('/group-student-grades', [TeacherApiController::class, 'groupStudentGrades']);

            // Active subjects list (journal index for mobile)
            Route::get('/active-subjects', [TeacherApiController::class, 'activeSubjects']);

            // Journal — full journal with 3 tabs (Ma'ruza, Amaliy, MT)
            Route::get('/journal', [TeacherApiController::class, 'journal']);

            // Grade saving
            Route::post('/grades/lesson', [TeacherApiController::class, 'saveOpenedLessonGrade']);
            Route::post('/grades/mt', [TeacherApiController::class, 'saveMtGrade']);
        });
    });
});

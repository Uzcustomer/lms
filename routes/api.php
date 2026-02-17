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
            Route::get('/pending-lessons', [StudentApiController::class, 'pendingLessons']);
            Route::get('/attendance', [StudentApiController::class, 'attendance']);
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
        });
    });
});

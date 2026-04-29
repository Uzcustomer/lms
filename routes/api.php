<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AbsenceExcuseApiController;
use App\Http\Controllers\Api\V1\ChatApiController;
use App\Http\Controllers\Api\V1\Retake\AcademicDeptRetakeController;
use App\Http\Controllers\Api\V1\Retake\DeanRetakeController;
use App\Http\Controllers\Api\V1\Retake\RegistrarRetakeController;
use App\Http\Controllers\Api\V1\Retake\StudentRetakeController;
use App\Http\Controllers\Api\V1\StudentApiController;
use App\Http\Controllers\Api\V1\TeacherApiController;
use App\Http\Controllers\Api\V1\TutorApiController;
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
            Route::get('/contract', [StudentApiController::class, 'contract']);
            Route::get('/exam-schedule', [StudentApiController::class, 'examSchedule']);
            Route::get('/rating', [StudentApiController::class, 'studentRating']);

            // Profile completion
            Route::post('/complete-profile/phone', [StudentApiController::class, 'savePhone']);
            Route::post('/complete-profile/telegram', [StudentApiController::class, 'saveTelegram']);
            Route::get('/complete-profile/telegram/check', [StudentApiController::class, 'checkTelegramVerification']);

            // Absence excuses
            Route::get('/excuses/reasons', [AbsenceExcuseApiController::class, 'reasons']);
            Route::get('/excuses', [AbsenceExcuseApiController::class, 'index']);
            Route::post('/excuses', [AbsenceExcuseApiController::class, 'store']);
            Route::get('/excuses/{id}', [AbsenceExcuseApiController::class, 'show']);
            Route::post('/excuses/missed-assessments', [AbsenceExcuseApiController::class, 'missedAssessments']);
            Route::get('/excuses/{id}/download', [AbsenceExcuseApiController::class, 'download']);
            Route::get('/excuses/{id}/download-pdf', [AbsenceExcuseApiController::class, 'downloadPdf']);
        });

        // ── Chat endpoints ───────────────────────────────
        Route::prefix('chat')->group(function () {
            Route::get('/contacts', [ChatApiController::class, 'contacts']);
            Route::get('/messages/{contactId}', [ChatApiController::class, 'messages']);
            Route::post('/send', [ChatApiController::class, 'send']);
            Route::get('/group', [ChatApiController::class, 'groupMessages']);
            Route::post('/group/send', [ChatApiController::class, 'groupSend']);
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

        // ── Tutor endpoints ───────────────────────────────
        Route::prefix('tutor')->group(function () {
            Route::get('/profile', [TeacherApiController::class, 'profile']);
            Route::get('/profile/groups', [TutorApiController::class, 'groups']);
            Route::get('/groups/{groupId}/students', [TutorApiController::class, 'groupStudents']);
            Route::get('/students/{studentId}', [TutorApiController::class, 'studentProfile']);
            Route::get('/students/{studentId}/academic-records', [TutorApiController::class, 'studentAcademicRecords']);
        });

        // ── Retake (Qayta o'qish) — Talaba ────────────────
        Route::prefix('student/retake')->group(function () {
            Route::get('/curriculum', [StudentRetakeController::class, 'curriculum']);
            Route::get('/period/active', [StudentRetakeController::class, 'activePeriod']);
            Route::get('/applications', [StudentRetakeController::class, 'index']);
            Route::post('/applications', [StudentRetakeController::class, 'store'])
                ->middleware('throttle:5,1'); // 5 ta urinish / daqiqa
            Route::get('/applications/{id}', [StudentRetakeController::class, 'show'])
                ->whereNumber('id');
            Route::get('/applications/{id}/document', [StudentRetakeController::class, 'downloadDocument'])
                ->whereNumber('id');
            Route::get('/applications/{id}/tasdiqnoma', [StudentRetakeController::class, 'downloadTasdiqnoma'])
                ->whereNumber('id');
        });

        // ── Retake — Dekan ────────────────────────────────
        Route::prefix('dean/retake')->group(function () {
            Route::get('/applications', [DeanRetakeController::class, 'index']);
            Route::get('/applications/{id}', [DeanRetakeController::class, 'show'])->whereNumber('id');
            Route::post('/applications/{id}/approve', [DeanRetakeController::class, 'approve'])->whereNumber('id');
            Route::post('/applications/{id}/reject', [DeanRetakeController::class, 'reject'])->whereNumber('id');
        });

        // ── Retake — Registrator ──────────────────────────
        Route::prefix('registrar/retake')->group(function () {
            Route::get('/applications', [RegistrarRetakeController::class, 'index']);
            Route::get('/applications/{id}', [RegistrarRetakeController::class, 'show'])->whereNumber('id');
            Route::post('/applications/{id}/approve', [RegistrarRetakeController::class, 'approve'])->whereNumber('id');
            Route::post('/applications/{id}/reject', [RegistrarRetakeController::class, 'reject'])->whereNumber('id');
        });

        // ── Retake — O'quv bo'limi ────────────────────────
        Route::prefix('academic/retake')->group(function () {
            // Qabul oynalari (faqat POST + GET — adolatlilik qoidasi: PUT/DELETE yo'q)
            Route::get('/periods', [AcademicDeptRetakeController::class, 'periodsIndex']);
            Route::get('/periods/active', [AcademicDeptRetakeController::class, 'periodsActive']);
            Route::post('/periods', [AcademicDeptRetakeController::class, 'periodsStore']);

            // Arizalar (academic_dept ko'lami)
            Route::get('/applications', [AcademicDeptRetakeController::class, 'applicationsIndex']);
            Route::get('/applications/grouped', [AcademicDeptRetakeController::class, 'applicationsGrouped']);
            Route::post('/applications/{id}/reject', [AcademicDeptRetakeController::class, 'applicationsReject'])
                ->whereNumber('id');

            // Guruhlar
            Route::get('/groups', [AcademicDeptRetakeController::class, 'groupsIndex']);
            Route::post('/groups', [AcademicDeptRetakeController::class, 'groupsStore']);
            Route::put('/groups/{id}', [AcademicDeptRetakeController::class, 'groupsUpdate'])->whereNumber('id');

            // O'qituvchilar (guruh shakllantirish uchun)
            Route::get('/teachers', [AcademicDeptRetakeController::class, 'teachers']);
        });
    });
});

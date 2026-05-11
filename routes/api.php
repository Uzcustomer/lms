<?php

use App\Http\Controllers\Api\ExamAccessCheckController;
use App\Http\Controllers\Api\ExamLoginCheckController;
use App\Http\Controllers\Api\ExamLayoutController;
use App\Http\Controllers\Api\ExamQuizTargetController;
use App\Http\Controllers\Api\MoodleDescriptorCallbackController;
use App\Http\Controllers\Api\MoodleDescriptorFailedCallbackController;
use App\Http\Controllers\Api\MoodlePhotoSyncController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AbsenceExcuseApiController;
use App\Http\Controllers\Api\V1\ChatApiController;
use App\Http\Controllers\Api\V1\ClubApiController;
use App\Http\Controllers\Api\V1\ExamAppealApiController;
use App\Http\Controllers\Api\V1\StudentApiController;
use App\Http\Controllers\Api\V1\TeacherApiController;
use App\Http\Controllers\Api\V1\TutorApiController;
use Illuminate\Support\Facades\Route;

// Moodle (local_hemisexport) → LMS pull-back endpoint (server-to-server,
// shared-secret auth via X-SYNC-SECRET). Re-queues approved photos for
// the listed idnumbers via the existing SendStudentPhotoToMoodle job.
Route::post('/sync-photos-to-moodle', [MoodlePhotoSyncController::class, 'syncPhotos'])
    ->middleware('throttle:5,1')
    ->name('api.moodle.sync-photos');

// Moodle plugin → LMS descriptor confirmation. After local_hemisexport
// successfully extracts a face descriptor for an approved photo, it POSTs
// the idnumber list here so we can mark the row as fully usable in Moodle
// and finally notify the tutor.
Route::post('/moodle-descriptor-confirmed', [MoodleDescriptorCallbackController::class, 'confirm'])
    ->middleware('throttle:30,1')
    ->name('api.moodle.descriptor-confirmed');

// Moodle plugin → LMS descriptor FAILURE. When face-api.js cannot detect a
// face on a photo we already approved and pushed, Moodle calls this so we
// can flip the photo back to 'rejected', clear the cached ArcFace embedding
// and notify the tutor — instead of silently leaving an unusable approved
// photo in Mark.
Route::post('/moodle-descriptor-failed', [MoodleDescriptorFailedCallbackController::class, 'fail'])
    ->middleware('throttle:60,1')
    ->name('api.moodle.descriptor-failed');

// Moodle quizaccess_lmsguard plugin → LMS pre-attempt check. Verifies the
// student's IP matches the computer assigned to them and the slot is
// active. Same X-SYNC-SECRET shared secret as the other Moodle callbacks.
Route::post('/exam-access-check', [ExamAccessCheckController::class, 'check'])
    ->middleware('throttle:120,1')
    ->name('api.moodle.exam-access-check');

// Moodle auth_faceid plugin → LMS pre-login check. Lighter than
// exam-access-check: only blocks "wrong_computer" so students walking
// up to the wrong PC during their exam slot are turned away at the
// FaceID screen instead of getting an error 30 seconds later when they
// try to open the quiz.
Route::post('/exam-login-check', [ExamLoginCheckController::class, 'check'])
    ->middleware('throttle:240,1')
    ->name('api.moodle.exam-login-check');

// Moodle proctor dashboard (auth_faceid plugin) → today's full computer
// grid with current/next student per cell. Same X-SYNC-SECRET auth.
Route::post('/exam-layout-today', [ExamLayoutController::class, 'today'])
    ->middleware('throttle:60,1')
    ->name('api.moodle.exam-layout-today');

// Moodle auth_faceid plugin → after a successful FaceID login, asks the
// LMS whether this student has an active YN slot right now and, if so,
// which Moodle quiz idnumber to redirect them to.
Route::post('/exam-quiz-target', [ExamQuizTargetController::class, 'target'])
    ->middleware('throttle:120,1')
    ->name('api.moodle.exam-quiz-target');

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
            Route::post('/exam-language', [StudentApiController::class, 'saveExamLanguage']);

            // Absence excuses
            Route::get('/excuses/reasons', [AbsenceExcuseApiController::class, 'reasons']);
            Route::get('/excuses', [AbsenceExcuseApiController::class, 'index']);
            Route::post('/excuses', [AbsenceExcuseApiController::class, 'store']);
            Route::get('/excuses/{id}', [AbsenceExcuseApiController::class, 'show']);
            Route::post('/excuses/missed-assessments', [AbsenceExcuseApiController::class, 'missedAssessments']);
            Route::get('/excuses/{id}/download', [AbsenceExcuseApiController::class, 'download']);
            Route::get('/excuses/{id}/download-pdf', [AbsenceExcuseApiController::class, 'downloadPdf']);

            // Clubs
            Route::get('/clubs', [ClubApiController::class, 'index']);
            Route::get('/clubs/my', [ClubApiController::class, 'myClubs']);
            Route::post('/clubs/join', [ClubApiController::class, 'join']);
            Route::post('/clubs/cancel', [ClubApiController::class, 'cancel']);

            // Exam appeals (apellyatsiya)
            Route::get('/appeals', [ExamAppealApiController::class, 'index']);
            Route::get('/appeals/available-grades', [ExamAppealApiController::class, 'availableGrades']);
            Route::post('/appeals', [ExamAppealApiController::class, 'store']);
            Route::get('/appeals/{id}', [ExamAppealApiController::class, 'show']);
            Route::post('/appeals/{id}/comment', [ExamAppealApiController::class, 'addComment']);
            Route::get('/appeals/{id}/download', [ExamAppealApiController::class, 'download']);
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
    });
});

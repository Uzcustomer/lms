<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\IndependentController;
use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\OraliqNazoratController;
use App\Http\Controllers\Admin\OskiController;
use App\Http\Controllers\Admin\QuizResultController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\ExamTestController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QaytnomaController;
use App\Http\Controllers\AbsenceReportController;
use App\Http\Controllers\VedomostController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Student\StudentAuthController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StudentController as AdminStudentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Teacher\TeacherAuthController;
use App\Http\Controllers\Teacher\TeacherMainController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\Admin\PasswordSettingsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\Admin\ScheduleController;
use App\Http\Controllers\Admin\LectureScheduleController;
use App\Http\Controllers\Admin\ImpersonateController;
use App\Http\Controllers\MoodleImportController;


Route::get('/', function () {
    return view('welcome');
})->name('welcome');


Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    });

    Route::get('/login', function () {
        if (auth()->check()) {
            return redirect()->route('admin.dashboard');
        } else {
            return response()
                ->view('auth.login')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }
    })->name('login');


    Route::middleware([\App\Http\Middleware\AdminMultiGuardAuth::class, \Spatie\Permission\Middleware\RoleMiddleware::class . ':superadmin|admin|kichik_admin|inspeksiya|oquv_prorektori|registrator_ofisi|oquv_bolimi|buxgalteriya|manaviyat|tyutor|dekan|kafedra_mudiri|fan_masuli|oqituvchi|test_markazi'])->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Role switching
        Route::post('/switch-role', function (\Illuminate\Http\Request $request) {
            $user = auth()->user();
            $role = $request->input('role');
            if ($user && $user->hasRole($role)) {
                session(['active_role' => $role]);
            }
            return back();
        })->name('switch-role');

        Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
        Route::get('/students/filter/departments', [AdminStudentController::class, 'getFilterDepartments'])->name('students.filter.departments');
        Route::get('/students/filter/specialties', [AdminStudentController::class, 'getFilterSpecialties'])->name('students.filter.specialties');
        Route::get('/students/filter/groups', [AdminStudentController::class, 'getFilterGroups'])->name('students.filter.groups');
        Route::get('/students/filter/levels', [AdminStudentController::class, 'getFilterLevels'])->name('students.filter.levels');
        Route::get('/students/filter/semesters', [AdminStudentController::class, 'getFilterSemesters'])->name('students.filter.semesters');
        Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
        Route::post('/students/{student}/reset-local-password', [AdminStudentController::class, 'resetLocalPassword'])->name('students.reset-local-password');
        Route::post('/students/bulk-reset-password', [AdminStudentController::class, 'bulkResetLocalPassword'])->name('students.bulk-reset-password');

        Route::prefix('qaytnoma')->name('qaytnoma.')->group(function () {
            Route::get('', [QaytnomaController::class, 'index'])->name('index');
            Route::get('/create', [QaytnomaController::class, 'create'])->name('create');
            Route::post('/store', [QaytnomaController::class, 'store'])->name('store');
            Route::delete('/delete/{id}', [QaytnomaController::class, 'delete'])->name('delete');
            Route::get('/edit/{id}', [QaytnomaController::class, 'edit'])->name('edit');
            Route::put('/edit/{id}', [QaytnomaController::class, 'edit_save'])->name('edit.save');
        });

        Route::get('/absence-report', [AbsenceReportController::class, 'index'])->name('absence_report.index');
        Route::get('/absence-report/data', [AbsenceReportController::class, 'data'])->name('absence_report.data');
        Route::get('/absence-report/detail', [AbsenceReportController::class, 'detail'])->name('absence_report.detail');

        Route::prefix('independent')->name('independent.')->group(function () {
            Route::get('', [IndependentController::class, 'index'])->name('index');
            Route::get('/create', [IndependentController::class, 'create'])->name('create');
            Route::post('/store', [IndependentController::class, 'store'])->name('store');
            Route::delete('/delete/{id}', [IndependentController::class, 'delete'])->name('delete');
            Route::get('/edit/{id}', [IndependentController::class, 'edit'])->name('edit');
            Route::put('/edit/{id}', [IndependentController::class, 'edit_save'])->name('edit.save');
            Route::get('/grade/{id}', [IndependentController::class, 'grade'])->name('grade');
            Route::get('/grade/form/{id}', [IndependentController::class, 'grade_form'])->name('grade_form');
            Route::post('/grade', [IndependentController::class, 'grade_save'])->name('grade.save');
        });

        Route::prefix('oraliqnazorat')->name('oraliqnazorat.')->group(function () {
            Route::get('', [OraliqNazoratController::class, 'index'])->name('index');
            Route::get('/create', [OraliqNazoratController::class, 'create'])->name('create');
            Route::post('/store', [OraliqNazoratController::class, 'store'])->name('store');
            Route::delete('/delete/{id}', [OraliqNazoratController::class, 'delete'])->name('delete');
            Route::get('/edit/{id}', [OraliqNazoratController::class, 'edit'])->name('edit');
            Route::put('/edit/{id}', [OraliqNazoratController::class, 'edit_save'])->name('edit.save');
            Route::get('/grade/{id}', [OraliqNazoratController::class, 'grade'])->name('grade');
            Route::get('/grade./form/{id}', [OraliqNazoratController::class, 'grade_form'])->name('grade_form');
            Route::post('/grade', [OraliqNazoratController::class, 'grade_save'])->name('grade.save');
        });

        Route::prefix('oski')->name('oski.')->group(function () {
            Route::get('', [OskiController::class, 'index'])->name('index');
            Route::get('/create', [OskiController::class, 'create'])->name('create');
            Route::post('/store', [OskiController::class, 'store'])->name('store');
            Route::post('/import', [OskiController::class, 'import'])->name('import');
            Route::delete('/delete/{id}', [OskiController::class, 'delete'])->name('delete');
            Route::get('/edit/{id}', [OskiController::class, 'edit'])->name('edit');
            Route::put('/edit/{id}', [OskiController::class, 'edit_save'])->name('edit.save');
            Route::get('/grade/form/{id}', [OskiController::class, 'grade_form'])->name('grade_form');
            Route::get('/grade/{id}', [OskiController::class, 'grade'])->name('grade');
            Route::post('/grade', [OskiController::class, 'grade_save'])->name('grade.save');
            Route::post('/sababli/save', [OskiController::class, 'sababli_save'])->name('sababli.save');
            Route::get('/delete/file/{id}', [OskiController::class, 'delete_file'])->name('file.delete');
        });

        Route::prefix('examtest')->name('examtest.')->group(function () {
            Route::get('', [ExamTestController::class, 'index'])->name('index');
            Route::get('/create', [ExamTestController::class, 'create'])->name('create');
            Route::post('/store', [ExamTestController::class, 'store'])->name('store');
            Route::post('/import', [ExamTestController::class, 'import'])->name('import');
            Route::delete('/delete/{id}', [ExamTestController::class, 'delete'])->name('delete');
            Route::get('/edit/{id}', [ExamTestController::class, 'edit'])->name('edit');
            Route::put('/edit/{id}', [ExamTestController::class, 'edit_save'])->name('edit.save');
            Route::get('/grade/{id}', [ExamTestController::class, 'grade'])->name('grade');
            Route::get('/grade/form/{id}', [ExamTestController::class, 'grade_form'])->name('grade_form');
            Route::post('/grade', [ExamTestController::class, 'grade_save'])->name('grade.save');
            Route::post('/sababli/save', [ExamTestController::class, 'sababli_save'])->name('sababli.save');
            Route::get('/delete/file/{id}', [ExamTestController::class, 'delete_file'])->name('file.delete');
        });
        Route::prefix('vedomost')->name('vedomost.')->group(function () {
            Route::get('', [VedomostController::class, 'index'])->name('index');
            Route::get('/create', [VedomostController::class, 'create'])->name('create');
            Route::post('/store', [VedomostController::class, 'store'])->name('store');
            Route::delete('/delete/{id}', [VedomostController::class, 'delete'])->name('delete');

        });

        Route::prefix('journal')->name('journal.')->group(function () {
            Route::get('', [JournalController::class, 'index'])->name('index');
            Route::get('/show/{groupId}/{subjectId}/{semesterCode}', [JournalController::class, 'show'])->name('show');
            Route::post('/save-mt-grade', [JournalController::class, 'saveMtGrade'])->name('save-mt-grade');
            Route::post('/save-retake-grade', [JournalController::class, 'saveRetakeGrade'])->name('save-retake-grade');
            Route::post('/create-retake-grade', [JournalController::class, 'createRetakeGrade'])->name('create-retake-grade');
            Route::post('/open-lesson', [JournalController::class, 'openLesson'])->name('open-lesson');
            Route::post('/close-lesson', [JournalController::class, 'closeLesson'])->name('close-lesson');
            Route::get('/download-lesson-file/{lessonOpening}', [JournalController::class, 'downloadLessonFile'])->name('download-lesson-file');
            Route::post('/save-opened-lesson-grade', [JournalController::class, 'saveOpenedLessonGrade'])->name('save-opened-lesson-grade');
            Route::get('/get-specialties', [JournalController::class, 'getSpecialties'])->name('get-specialties');
            Route::get('/get-level-codes', [JournalController::class, 'getLevelCodes'])->name('get-level-codes');
            Route::get('/get-semesters', [JournalController::class, 'getSemesters'])->name('get-semesters');
            Route::get('/get-subjects', [JournalController::class, 'getSubjects'])->name('get-subjects');
            Route::get('/get-groups', [JournalController::class, 'getGroups'])->name('get-groups');
            // Ikki tomonlama bog'liq filtrlar
            Route::get('/get-faculties-by-specialty', [JournalController::class, 'getFacultiesBySpecialty'])->name('get-faculties-by-specialty');
            Route::get('/get-level-codes-by-semester', [JournalController::class, 'getLevelCodesBySemester'])->name('get-level-codes-by-semester');
            Route::get('/get-education-years-by-level', [JournalController::class, 'getEducationYearsByLevel'])->name('get-education-years-by-level');
            Route::get('/get-faculties-by-group', [JournalController::class, 'getFacultiesByGroup'])->name('get-faculties-by-group');
            Route::get('/get-specialties-by-group', [JournalController::class, 'getSpecialtiesByGroup'])->name('get-specialties-by-group');
            Route::get('/get-filters-by-subject', [JournalController::class, 'getFiltersBySubject'])->name('get-filters-by-subject');
            Route::get('/get-filters-by-group', [JournalController::class, 'getFiltersByGroup'])->name('get-filters-by-group');
            Route::get('/get-filters-by-semester', [JournalController::class, 'getFiltersBySemester'])->name('get-filters-by-semester');
            Route::get('/get-sidebar-options', [JournalController::class, 'getSidebarOptions'])->name('get-sidebar-options');
            Route::get('/get-topics', [JournalController::class, 'getTopics'])->name('get-topics');
            Route::get('/download-submission/{submissionId}', [JournalController::class, 'downloadSubmission'])->name('download-submission');
            Route::get('/download-history-file/{historyId}', [JournalController::class, 'downloadHistoryFile'])->name('download-history-file');
            Route::post('/sync-schedule', [JournalController::class, 'syncSchedule'])->name('sync-schedule');
            Route::post('/submit-to-yn', [JournalController::class, 'submitToYn'])->name('submit-to-yn');
            Route::get('/get-yn-consents', [JournalController::class, 'getYnConsents'])->name('get-yn-consents');
        });

        Route::get('/get-filter-options', [AdminStudentController::class, 'getFilterOptions'])->name('get-filter-options');
        Route::get('/get-curricula', [AdminStudentController::class, 'getCurricula'])->name('get-curricula');
        Route::get('/get-subjects', [AdminStudentController::class, 'getSubjects'])->name('get-subjects');
        Route::get('/get-shakl', [AdminStudentController::class, 'getshakl'])->name('get-shakl');
        Route::get('/get-shakl-oski', [AdminStudentController::class, 'getshakl_oski'])->name('get-shakl_oski');
        Route::get('/get-students-shakl', [AdminStudentController::class, 'getStudentsShakl'])->name('get-students-shakl');
        Route::get('/get/shakl/oski', [AdminStudentController::class, 'getStudentsShaklOski'])->name('get-students-shakl-oski');


        Route::get('/students/list', [AdminStudentController::class, 'getStudents'])->name('students.list');
        Route::get('/students/{hemis_id}/grades', [AdminStudentController::class, 'grades'])->name('students.grades');
        Route::get('/students/{hemisId}/attendance', [AdminStudentController::class, 'attendance'])->name('students.attendance');
        Route::get('/students/{hemis_id}/low-grades', [AdminStudentController::class, 'low_grades'])->name('students.low-grades');
        Route::get('/student-performances', [AdminStudentController::class, 'student_low'])->name('student-performances.index');
        Route::get('/student-performances/{hemisId}', [AdminStudentController::class, 'student_low'])->name('student-performances.index');
        Route::put('/student-grades/{gradeId}', [AdminStudentController::class, 'updateGrade'])->name('student-grades.update');
        Route::put('/student-grades/{gradeId}/status', [AdminStudentController::class, 'updateStatus'])->name('student-grades.update-status');

        Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
        Route::get('/teachers/export-excel', [TeacherController::class, 'exportExcel'])->name('teachers.export-excel');
        Route::get('/teachers/{teacher}', [TeacherController::class, 'show'])->name('teachers.show');
        Route::get('/teachers/{teacher}/edit', [TeacherController::class, 'edit'])->name('teachers.edit');
        Route::put('/teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
        Route::put('/teachers/{teacher}/roles', [TeacherController::class, 'updateRoles'])->name('teachers.update-roles');
        Route::post('/teachers/{teacher}/reset-password', [TeacherController::class, 'resetPassword'])->name('teachers.reset-password');
        Route::put('/teachers/{teacher}/contact', [TeacherController::class, 'updateContact'])->name('teachers.update-contact');

        Route::post('/teachers/import', [TeacherController::class, 'importTeachers'])->name('teachers.import');

        // Unified settings page
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/deadlines', [SettingsController::class, 'updateDeadlines'])->name('settings.update.deadlines');
        Route::post('/settings/marking-system-scores', [SettingsController::class, 'updateMarkingSystemScores'])->name('settings.update.marking-system-scores');
        Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.update.password');
        Route::post('/settings/telegram', [SettingsController::class, 'updateTelegram'])->name('settings.update.telegram');

        // Old routes — redirect to unified settings
        Route::get('/deadlines', fn () => redirect()->route('admin.settings', ['tab' => 'deadlines']))->name('deadlines');
        Route::get('/deadlines/edit', fn () => redirect()->route('admin.settings', ['tab' => 'deadlines']))->name('deadlines.edit');
        Route::post('/deadlines/update', [SettingsController::class, 'updateDeadlines'])->name('deadlines.update');

        Route::get('/student-grades', [AdminStudentController::class, 'studentGradesWeek'])->name('student-grades-week');
        Route::get('/get-groups-by-department', [AdminStudentController::class, 'getGroupsByDepartment'])->name('get-groups-by-department');
        Route::get('/get-semesters-new-hemis', [AdminStudentController::class, 'getSemestersNew_hemis'])->name('get-semesters-new-hemis');
        Route::get('/get-groups-by-department-hemis', [AdminStudentController::class, 'getGroupsByDepartment_hemis'])->name('get-groups-by-department-hemis');
        Route::get('/get-subjects-new-hemis', [AdminStudentController::class, 'getSubjectsNew_hemis'])->name('get-subjects-new-hemis');
        Route::get('/get-semesters-new', [AdminStudentController::class, 'getSemestersNew'])->name('get-semesters-new');
        Route::get('/get-level-codes', [AdminStudentController::class, 'getLevelCodes'])->name('get-level-codes');
        Route::get('/get-subjects-new', [AdminStudentController::class, 'getSubjectsNew'])->name('get-subjects-new');
        Route::get('/student-grades-week/export', [AdminStudentController::class, 'exportStudentGrades'])->name('student-grades-week.export');
        Route::get('/student-grades-week/export-box', [AdminStudentController::class, 'exportStudentGradesBox'])->name('student-grades-week.export-box');
        Route::post('/student-grades-update-via-excel/import', [AdminStudentController::class, 'import'])->name('student-import-grades');


        Route::get('/reports/jn', [ReportController::class, 'jnReport'])->name('reports.jn');
        Route::get('/reports/jn/data', [ReportController::class, 'jnReportData'])->name('reports.jn.data');

        Route::get('/reports/lesson-assignment', [ReportController::class, 'lessonAssignment'])->name('reports.lesson-assignment');
        Route::get('/reports/lesson-assignment/data', [ReportController::class, 'lessonAssignmentData'])->name('reports.lesson-assignment.data');

        Route::get('/reports/schedule-report', [ReportController::class, 'scheduleReport'])->name('reports.schedule-report');
        Route::get('/reports/schedule-report/data', [ReportController::class, 'scheduleReportData'])->name('reports.schedule-report.data');

        Route::get('/reports/absence', [ReportController::class, 'absenceReport'])->name('reports.absence');
        Route::get('/reports/absence/data', [ReportController::class, 'absenceReportData'])->name('reports.absence.data');

        Route::get('/reports/load-vs-pair', [ReportController::class, 'loadVsPairReport'])->name('reports.load-vs-pair');
        Route::get('/reports/load-vs-pair/data', [ReportController::class, 'loadVsPairReportData'])->name('reports.load-vs-pair.data');

        Route::get('/reports/debtors', [ReportController::class, 'debtorsReport'])->name('reports.debtors');
        Route::get('/reports/debtors/data', [ReportController::class, 'debtorsReportData'])->name('reports.debtors.data');

        Route::get('/reports/sababli-check', [ReportController::class, 'sababliCheckReport'])->name('reports.sababli-check');
        Route::get('/reports/sababli-check/data', [ReportController::class, 'sababliCheckData'])->name('reports.sababli-check.data');

        Route::get('/lesson-histories', [LessonController::class, 'historyIndex'])->name('lesson.histories-index');

        Route::get('/lessons/create', [LessonController::class, 'index'])->name('lessons.create');
        Route::post('/lessons/store', [LessonController::class, 'store'])->name('lessons.store');
        Route::delete('/lessons/{id}', [LessonController::class, 'destroy'])->name('lesson.destroy');

        Route::get('/get-groups', [LessonController::class, 'getGroups'])->name('get.groups');
        Route::get('/get-groups/semester', [LessonController::class, 'getGroups_semester'])->name('get.groups_semester');
        Route::get('/get-students', [LessonController::class, 'getStudents'])->name('get.students');
        Route::get('/get-semesters', [LessonController::class, 'getSemesters'])->name('get.semesters');
        Route::get('/get-subjects', [LessonController::class, 'getSubjects'])->name('get.subjects');
        Route::get('/get-training-types', [LessonController::class, 'getTrainingTypes'])->name('get.training-types');
        Route::get('/get-teacher', [LessonController::class, 'getTeacher'])->name('get.teacher');
        Route::get('/get-subject-teacher', [LessonController::class, 'getSubjectTeacher'])->name('get.subject-teacher');
        Route::get('/get-schedule-dates', [LessonController::class, 'getScheduleDates'])->name('get.dates');
        Route::get('/get-lesson-pairs', [LessonController::class, 'getLessonPairs'])->name('get.pairs');
        Route::get('/get-filtered-students', [LessonController::class, 'getFilteredStudents'])->name('get.filtered-students');
        Route::post('/import-schedules', [ScheduleController::class, 'importSchedules'])->name('import-schedules');

        Route::resource('users', \App\Http\Controllers\Admin\UserController::class);

        Route::prefix('activity-log')->name('activity-log.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::get('/{activityLog}', [ActivityLogController::class, 'show'])->name('show');
        });

        // Diagnostika sahifasi (yangi dizayn)
        Route::prefix('diagnostika')->name('diagnostika.')->group(function () {
            Route::get('/', [QuizResultController::class, 'diagnostikaPage'])->name('index');
            Route::get('/data', [QuizResultController::class, 'diagnostikaData'])->name('data');
            Route::get('/tartibga-sol', [QuizResultController::class, 'tartibgaSol'])->name('tartibga-sol');
        });

        // Sistemaga yuklangan natijalar sahifasi
        Route::prefix('saqlangan-hisobot')->name('saqlangan-hisobot.')->group(function () {
            Route::get('/', [QuizResultController::class, 'saqlanganHisobotPage'])->name('index');
            Route::get('/data', [QuizResultController::class, 'saqlanganHisobot'])->name('data');
        });

        // Test markazi: Quiz natijalar API (diagnostika, upload, import, export, destroy)
        Route::prefix('quiz-results')->name('quiz-results.')->group(function () {
            Route::get('/export', [QuizResultController::class, 'exportExcel'])->name('export');
            Route::post('/import', [QuizResultController::class, 'import'])->name('import');
            Route::post('/diagnostika', [QuizResultController::class, 'diagnostika'])->name('diagnostika');
            Route::post('/upload', [QuizResultController::class, 'uploadToGrades'])->name('upload');
            Route::post('/trigger-cron', [QuizResultController::class, 'triggerCron'])->name('trigger-cron');
            Route::delete('/{id}', [QuizResultController::class, 'destroy'])->name('destroy');
        });

        // Ma'ruza jadvalini joylashtirish
        Route::prefix('lecture-schedule')->name('lecture-schedule.')->group(function () {
            Route::get('/', [LectureScheduleController::class, 'index'])->name('index');
            Route::post('/import', [LectureScheduleController::class, 'import'])->name('import');
            Route::get('/data', [LectureScheduleController::class, 'data'])->name('data');
            Route::get('/compare', [LectureScheduleController::class, 'compare'])->name('compare');
            Route::get('/conflicts', [LectureScheduleController::class, 'conflicts'])->name('conflicts');
            Route::post('/move', [LectureScheduleController::class, 'move'])->name('move');
            Route::post('/store', [LectureScheduleController::class, 'store'])->name('store');
            Route::put('/item/{id}', [LectureScheduleController::class, 'update'])->name('update');
            Route::delete('/item/{id}', [LectureScheduleController::class, 'destroyItem'])->name('destroy-item');
            Route::get('/export/{id}', [LectureScheduleController::class, 'export'])->name('export');
            Route::delete('/{id}', [LectureScheduleController::class, 'destroy'])->name('destroy');
            Route::get('/template', [LectureScheduleController::class, 'downloadTemplate'])->name('template');
        });

        // Superadmin: boshqa foydalanuvchi sifatida kirish (impersonate)
        Route::post('/impersonate/student/{student}', [ImpersonateController::class, 'impersonateStudent'])->name('impersonate.student');
        Route::post('/impersonate/teacher/{teacher}', [ImpersonateController::class, 'impersonateTeacher'])->name('impersonate.teacher');

        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

    });

    // Faqat admin uchun sinxronizatsiya va sozlamalar route'lari
    Route::middleware(['auth:web', \Spatie\Permission\Middleware\RoleMiddleware::class . ':superadmin|admin'])->group(function () {
        // Old routes — redirect to unified settings
        Route::get('/password-settings', fn () => redirect()->route('admin.settings', ['tab' => 'password']))->name('password-settings.index');
        Route::post('/password-settings', [SettingsController::class, 'updatePassword'])->name('password-settings.update');
        Route::get('/synchronizes', fn () => redirect()->route('admin.settings', ['tab' => 'sync']))->name('synchronizes');
        Route::post('/synchronize', [DashboardController::class, 'importSchedulesPartialy'])->name('synchronize');
        Route::post('/synchronize/curricula', [DashboardController::class, 'importCurricula'])->name('synchronize.curricula');
        Route::post('/synchronize/curriculum-subjects', [DashboardController::class, 'importCurriculumSubjects'])->name('synchronize.curriculum-subjects');
        Route::post('/synchronize/groups', [DashboardController::class, 'importGroups'])->name('synchronize.groups');
        Route::post('/synchronize/semesters', [DashboardController::class, 'importSemesters'])->name('synchronize.semesters');
        Route::post('/synchronize/specialties-departments', [DashboardController::class, 'importSpecialtiesDepartments'])->name('synchronize.specialties-departments');
        Route::post('/synchronize/students', [DashboardController::class, 'importStudents'])->name('synchronize.students');
        Route::post('/synchronize/teachers', [DashboardController::class, 'importTeachers'])->name('synchronize.teachers');
        Route::post('/synchronize/attendance-controls', [DashboardController::class, 'importAttendanceControls'])->name('synchronize.attendance-controls');
        Route::post('/synchronize/curriculum-subject-teachers', [DashboardController::class, 'importCurriculumSubjectTeachers'])->name('synchronize.curriculum-subject-teachers');
        Route::post('/synchronize/marking-systems', [SettingsController::class, 'syncMarkingSystems'])->name('synchronize.marking-systems');
    });
});

// Impersonatsiyani to'xtatish (har qanday guard'dan)
Route::post('/stop-impersonation', [ImpersonateController::class, 'stopImpersonation'])->name('impersonate.stop');

// Impersonatsiya paytida teacher'dan student'ga o'tish
Route::post('/switch-impersonate/student/{student}', [ImpersonateController::class, 'switchToStudent'])->name('impersonate.switch-to-student');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('student')->name('student.')->group(function () {

    Route::middleware('guest:student')->group(function () {
        Route::post('/login', [StudentAuthController::class, 'login'])->name('login.post');
        //        Route::post('/refresh-token', [StudentAuthController::class, 'refreshToken'])->name('refresh-token');
    });

    // Telegram 2FA login tasdiqlash (auth kerak emas)
    Route::get('/verify-login', [StudentAuthController::class, 'showVerifyLogin'])->name('verify-login');
    Route::post('/verify-login', [StudentAuthController::class, 'verifyLoginCode'])->name('verify-login.post');
    Route::post('/verify-login/resend', [StudentAuthController::class, 'resendLoginCode'])->name('verify-login.resend');

    Route::middleware(['auth:student'])->group(function () {
        Route::get('/change-password', [StudentAuthController::class, 'editPassword'])->name('password.edit');
        Route::put('/change-password', [StudentAuthController::class, 'updatePassword'])->name('password.update');

        Route::get('/complete-profile', [StudentAuthController::class, 'showCompleteProfile'])->name('complete-profile');
        Route::post('/complete-profile/phone', [StudentAuthController::class, 'savePhone'])->name('complete-profile.phone');
        Route::post('/complete-profile/telegram', [StudentAuthController::class, 'saveTelegram'])->name('complete-profile.telegram');
        Route::get('/verify-telegram/check', [StudentAuthController::class, 'checkTelegramVerification'])->name('verify-telegram.check');
    });

    Route::get('/login', function () {
        if (auth()->guard('student')->check()) {
            return redirect()->route('student.dashboard');
        } else {
            return response()
                ->view('student.login')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        }
    })->name('login');

    Route::middleware(['auth:student', 'force.student.contact'])->group(function () {
        Route::get('/', function () {
            return redirect()->route('student.dashboard');
        });
        Route::get('/dashboard', [StudentController::class, 'dashboard'])->name('dashboard');
        Route::get('/schedule', [StudentController::class, 'getSchedule'])->name('schedule');
        Route::get('/attendance', [StudentController::class, 'getAttendance'])->name('attendance');
        Route::get('/subjects', [StudentController::class, 'getSubjects'])->name('subjects');
        Route::get('/subject/{id}', [StudentController::class, 'getSubjectGrades'])->name('subject.grades');
        Route::get('/pending-lessons', [StudentController::class, 'getPendingLessons'])->name('pending-lessons');
        Route::get('/independents', [StudentController::class, 'getIndependents'])->name('independents');
        Route::post('/independents/{id}/submit', [StudentController::class, 'submitIndependent'])->name('independents.submit');
        Route::get('/independents/download/{submissionId}', [StudentController::class, 'downloadSubmission'])->name('independents.download');
        Route::post('/yn-consent', [StudentController::class, 'submitYnConsent'])->name('yn-consent');
        Route::get('/profile-my', [StudentController::class, 'profile'])->name('profile');

        Route::post('/logout', [StudentAuthController::class, 'logout'])->name('logout');
    });
});

Route::prefix('teacher')->name('teacher.')->group(function () {
    Route::post('/login', [TeacherAuthController::class, 'login'])->name('login.post');
    Route::get('/login', [TeacherAuthController::class, 'showLoginForm'])->name('login');

    // Telegram 2FA login tasdiqlash (auth kerak emas)
    Route::get('/verify-login', [TeacherAuthController::class, 'showVerifyLogin'])->name('verify-login');
    Route::post('/verify-login', [TeacherAuthController::class, 'verifyLoginCode'])->name('verify-login.post');
    Route::post('/verify-login/resend', [TeacherAuthController::class, 'resendLoginCode'])->name('verify-login.resend');

    Route::middleware(['auth:teacher'])->group(function () {
        Route::get('/force-change-password', [TeacherAuthController::class, 'showForceChangePassword'])->name('force-change-password');
        Route::post('/force-change-password', [TeacherAuthController::class, 'forceChangePassword'])->name('force-change-password.post');

        Route::get('/complete-profile', [TeacherAuthController::class, 'showCompleteProfile'])->name('complete-profile');
        Route::post('/complete-profile/phone', [TeacherAuthController::class, 'savePhone'])->name('complete-profile.phone');
        Route::post('/complete-profile/telegram', [TeacherAuthController::class, 'saveTelegram'])->name('complete-profile.telegram');
        Route::get('/verify-telegram/check', [TeacherAuthController::class, 'checkTelegramVerification'])->name('verify-telegram.check');
    });

    Route::middleware(['auth:teacher', 'force.password.change', \Spatie\Permission\Middleware\RoleMiddleware::class . ':superadmin|admin|kichik_admin|inspeksiya|oquv_prorektori|registrator_ofisi|oquv_bolimi|buxgalteriya|manaviyat|tyutor|dekan|kafedra_mudiri|fan_masuli|oqituvchi|test_markazi'])->group(function () {
        Route::get('/', function () {
            return redirect()->route('teacher.dashboard');
        });
        Route::get('/dashboard', [TeacherMainController::class, 'index'])->name('dashboard');
        Route::get('/info-me', [TeacherMainController::class, 'info'])->name('info-me');

        // Role switching
        Route::post('/switch-role', function (\Illuminate\Http\Request $request) {
            $user = auth()->user();
            $role = $request->input('role');
            if ($user && $user->hasRole($role)) {
                session(['active_role' => $role]);
            }
            return back();
        })->name('switch-role');
        Route::get('/students', [TeacherMainController::class, 'students'])->name('students');
        Route::get('/student/{studentId}/subject/{subjectId}', [TeacherMainController::class, 'studentDetails'])->name('student.details');
        Route::put('/student-grades/{gradeId}', [TeacherMainController::class, 'updateGrade'])->name('update.grade');
        Route::get('/student-grades-week/export', [TeacherMainController::class, 'exportStudentGrades'])->name('student-grades-week.export');
        Route::get('/student-grades-week/export-box', [TeacherMainController::class, 'exportStudentGradesBox'])->name('student-grades-week.export-box');

        Route::get('/student-grades-week-for-teacher', [TeacherMainController::class, 'studentGradesWeek'])->name('student-grades-week-teacher');
        Route::get('/get-semesters-new', [TeacherMainController::class, 'getSemestersNew'])->name('get-semesters-new');
        Route::get('/get-semesters', [TeacherMainController::class, 'getSemesters'])->name('get-semesters');
        Route::get('/get-subjects-new', [TeacherMainController::class, 'getSubjectsNew'])->name('get-subjects-new');
        Route::get('/get-subjects', [TeacherMainController::class, 'getSubjects'])->name('get-subjects');
        Route::get('/get-shakl-oski', [AdminStudentController::class, 'getshakl_oski'])->name('get-shakl_oski');
        Route::get('/get-students-shakl', [AdminStudentController::class, 'getStudentsShakl'])->name('get-students-shakl');
        Route::get('/get/shakl/oski', [AdminStudentController::class, 'getStudentsShaklOski'])->name('get-students-shakl-oski');
        Route::get('/get-shakl', [AdminStudentController::class, 'getshakl'])->name('get-shakl');
        Route::get('/get-semesters-new-hemis', [AdminStudentController::class, 'getSemestersNew_hemis'])->name('get-semesters-new-hemis');
        Route::get('/get-groups-by-department-hemis', [AdminStudentController::class, 'getGroupsByDepartment_hemis'])->name('get-groups-by-department-hemis');
        Route::get('/get-level-codes', [AdminStudentController::class, 'getLevelCodes'])->name('get-level-codes');
        Route::get('/get-subjects-new-hemis', [AdminStudentController::class, 'getSubjectsNew_hemis'])->name('get-subjects-new-hemis');
        Route::get('/student-grades', [\App\Http\Controllers\Teacher\StudentGradesController::class, 'index'])->name('student-grades.index');
        Route::put('/update-credentials', [TeacherAuthController::class, 'update_credentials'])->name('update_credentials');
        Route::get('/edit-credentials', [TeacherAuthController::class, 'editCredentials'])->name('edit_credentials');
        Route::post('/logout', [TeacherAuthController::class, 'logout'])->name('logout');

        Route::get('/get-teacher', [LessonController::class, 'getTeacher'])->name('get-teacher');
        Route::get('/get-groups-by-department', [AdminStudentController::class, 'getGroupsByDepartment'])->name('get-groups-by-department');
        // Route::get('/get-semesters-new', [AdminStudentController::class, 'getSemestersNew'])->name('get-semesters-new');
        // Route::get('/get-subjects-new', [AdminStudentController::class, 'getSubjectsNew'])->name('get-subjects-new');
        Route::prefix('independent')->name('independent.')->group(function () {
            Route::get('', [IndependentController::class, 'index_teacher'])->name('index');
            Route::get('/create', [IndependentController::class, 'create_teacher'])->name('create');
            Route::post('/store', [IndependentController::class, 'store'])->name('store');
            Route::get('/grade/{id}', [IndependentController::class, 'grade_teacher'])->name('grade');
            Route::post('/grade', [IndependentController::class, 'grade_save'])->name('grade.save');
        });
        // Route::get('/independent', [IndependentController::class, 'index_teacher'])->name('independent.index');
        // Route::get('/independent/grade/{id}', [IndependentController::class, 'grade_teacher'])->name('independent.grade');
        // Route::post('/independent/grade', [IndependentController::class, 'grade_save'])->name('independent.grade.save');

        Route::get('/qaytnoma', [QaytnomaController::class, 'index_teacher'])->name('qaytnoma.index');
        Route::get('/qaytnoma/create', [QaytnomaController::class, 'create_teacher'])->name('qaytnoma.create');
        Route::post('/qaytnoma/store', [QaytnomaController::class, 'store'])->name('qaytnoma.store');

        Route::prefix('oraliqnazorat')->name('oraliqnazorat.')->group(function () {
            Route::get('', [OraliqNazoratController::class, 'index_teacher'])->name('index');
            Route::get('/create', [OraliqNazoratController::class, 'create_teacher'])->name('create');
            Route::post('/store', [OraliqNazoratController::class, 'store'])->name('store');
            Route::get('/grade/{id}', [OraliqNazoratController::class, 'grade_teacher'])->name('grade');
            Route::post('/grade', [OraliqNazoratController::class, 'grade_save'])->name('grade.save');
        });

        // Route::get('/oraliqnazorat', [OraliqNazoratController::class, 'index_teacher'])->name('oraliqnazorat.index');
        // Route::get('/oraliqnazorat/grade/{id}', [OraliqNazoratController::class, 'grade_teacher'])->name('oraliqnazorat.grade');
        // Route::post('/oraliqnazorat/grade', [OraliqNazoratController::class, 'grade_save'])->name('oraliqnazorat.grade.save');

        Route::get('/oski', [OskiController::class, 'index_teacher'])->name('oski.index');
        Route::get('/oski/grade/{id}', [OskiController::class, 'grade_teacher'])->name('oski.grade');
        Route::post('/oski/grade', [OskiController::class, 'grade_save'])->name('oski.grade.save');
        Route::get('/oski/create', [OskiController::class, 'create_teacher'])->name('oski.create');
        Route::post('/oski/store', [OskiController::class, 'store'])->name('oski.store');


        Route::get('/examtest', [ExamTestController::class, 'index_teacher'])->name('examtest.index');
        Route::get('/examtest/create', [ExamTestController::class, 'create_teacher'])->name('examtest.create');
        Route::post('/examtest/store', [ExamTestController::class, 'store'])->name('examtest.store');

        Route::prefix('vedomost')->name('vedomost.')->group(function () {
            Route::get('', [VedomostController::class, 'index_teacher'])->name('index');
            Route::get('/create', [VedomostController::class, 'teacherCreate'])->name('create');
            Route::post('/store', [VedomostController::class, 'store'])->name('store');

        });

        // Diagnostika sahifasi (yangi dizayn)
        Route::prefix('diagnostika')->name('diagnostika.')->group(function () {
            Route::get('/', [QuizResultController::class, 'diagnostikaPage'])->name('index');
            Route::get('/data', [QuizResultController::class, 'diagnostikaData'])->name('data');
            Route::get('/tartibga-sol', [QuizResultController::class, 'tartibgaSol'])->name('tartibga-sol');
        });

        // Sistemaga yuklangan natijalar sahifasi
        Route::prefix('saqlangan-hisobot')->name('saqlangan-hisobot.')->group(function () {
            Route::get('/', [QuizResultController::class, 'saqlanganHisobotPage'])->name('index');
            Route::get('/data', [QuizResultController::class, 'saqlanganHisobot'])->name('data');
        });

        // Test markazi: Quiz natijalar API (diagnostika, upload, import, export, destroy)
        Route::prefix('quiz-results')->name('quiz-results.')->group(function () {
            Route::get('/export', [QuizResultController::class, 'exportExcel'])->name('export');
            Route::post('/import', [QuizResultController::class, 'import'])->name('import');
            Route::post('/diagnostika', [QuizResultController::class, 'diagnostika'])->name('diagnostika');
            Route::post('/upload', [QuizResultController::class, 'uploadToGrades'])->name('upload');
            Route::post('/trigger-cron', [QuizResultController::class, 'triggerCron'])->name('trigger-cron');
            Route::delete('/{id}', [QuizResultController::class, 'destroy'])->name('destroy');
        });

        // Ma'ruza jadvalini joylashtirish
        Route::prefix('lecture-schedule')->name('lecture-schedule.')->group(function () {
            Route::get('/', [LectureScheduleController::class, 'index'])->name('index');
            Route::post('/import', [LectureScheduleController::class, 'import'])->name('import');
            Route::get('/data', [LectureScheduleController::class, 'data'])->name('data');
            Route::get('/compare', [LectureScheduleController::class, 'compare'])->name('compare');
            Route::get('/conflicts', [LectureScheduleController::class, 'conflicts'])->name('conflicts');
            Route::post('/move', [LectureScheduleController::class, 'move'])->name('move');
            Route::post('/store', [LectureScheduleController::class, 'store'])->name('store');
            Route::put('/item/{id}', [LectureScheduleController::class, 'update'])->name('update');
            Route::delete('/item/{id}', [LectureScheduleController::class, 'destroyItem'])->name('destroy-item');
            Route::get('/export/{id}', [LectureScheduleController::class, 'export'])->name('export');
            Route::delete('/{id}', [LectureScheduleController::class, 'destroy'])->name('destroy');
            Route::get('/template', [LectureScheduleController::class, 'downloadTemplate'])->name('template');
        });

        Route::get('/get-groups/semester', [LessonController::class, 'getGroups_semester'])->name('get.groups_semester');
        Route::get('/get-students', [LessonController::class, 'getStudents'])->name('get.students');
        // Route::get('/get-semesters', [LessonController::class, 'getSemesters'])->name('get.semesters');
        // Route::get('/get-subjects', [LessonController::class, 'getSubjects'])->name('get.subjects');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// Moodle → LMSTTATF sync endpoint (server-to-server)
Route::post('/moodle/import', [MoodleImportController::class, 'import'])
    ->name('moodle.import');
Route::get('/moodle/should-sync', [MoodleImportController::class, 'shouldSync'])
    ->name('moodle.should-sync');

// Telegram bot webhook (CSRF excluded in bootstrap/app.php)
Route::post('/telegram/webhook/{token}', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');


require __DIR__ . '/auth.php';

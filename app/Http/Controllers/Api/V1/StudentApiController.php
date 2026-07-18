<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicRecord;
use App\Models\AdmissionIndicator;
use App\Models\Attendance;
use App\Models\Curriculum;
use App\Services\HemisService;
use App\Models\CurriculumSubject;
use App\Models\StudentSubject;
use App\Models\CurriculumWeek;
use App\Models\Independent;
use App\Models\IndependentGradeHistory;
use App\Models\IndependentSubmission;
use App\Models\MarkingSystemScore;
use App\Models\ExamSchedule;
use App\Models\StudentRating;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\RetakeMustaqilSubmission;
use App\Models\RetakeSetting;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeDebtService;
use App\Services\Retake\RetakeJournalService;
use App\Services\Retake\RetakeWindowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StudentApiController extends Controller
{
    /**
     * Dashboard — GPA, absent count, debt count, recent grades
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();

        $avgGpa = $student->avg_gpa ?? 0;

        $totalAbsent = Attendance::where('student_id', $student->id)->count();

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $currentSemesterId = $student->semester_id;

        // Talabaga biriktirilgan fanlar asosida qarzdorlikni hisoblash.
        // student_subjects jadvali bo'sh bo'lsa (import qilinmagan), academic_records ga fallback qilinadi.
        $hasStudentSubjects = StudentSubject::where('student_hemis_id', $student->hemis_id)->exists();

        if ($hasStudentSubjects) {
            // Biriktirilgan fanlar asosida: academic_records da yozuv yo'q YOKI baho past
            $debtRecords = StudentSubject::from('student_subjects as ss')
                ->select([
                    'ss.subject_name',
                    'ss.semester_id',
                    \DB::raw('COALESCE(ar.semester_name, sem.name) as semester_name'),
                    'ar.credit',
                    'ar.total_acload',
                    'ar.total_point',
                    'ar.grade',
                    'ar.retraining_status',
                ])
                ->leftJoin('academic_records as ar', function ($join) use ($student) {
                    $join->on('ar.student_id', '=', \DB::raw((int) $student->hemis_id))
                         ->on('ar.subject_id', '=', 'ss.subject_id')
                         ->on('ar.semester_id', '=', 'ss.semester_id');
                    if ($student->curriculum_id !== null) {
                        $join->where('ar.curriculum_id', '=', $student->curriculum_id);
                    }
                })
                ->leftJoin('semesters as sem', 'sem.code', '=', 'ss.semester_id')
                ->where('ss.student_hemis_id', $student->hemis_id)
                ->when($currentSemesterId, fn($q) => $q->where('ss.semester_id', '!=', $currentSemesterId))
                ->where(function ($q) {
                    $q->whereNull('ar.id')                        // academic record umuman yo'q
                      ->orWhereNull('ar.grade')                   // baho kiritilmagan
                      ->orWhereIn('ar.grade', ['2', '0'])         // past baho
                      ->orWhere('ar.retraining_status', true);    // qayta o'qish
                })
                ->orderBy('ss.semester_id')
                ->orderBy('ss.subject_name')
                ->get();
        } else {
            // Fallback: faqat academic_records dan (eski mantiq)
            $debtRecords = AcademicRecord::where('student_id', $student->hemis_id)
                ->when($student->curriculum_id !== null, fn($q) => $q->where('curriculum_id', $student->curriculum_id))
                ->where(function ($q) {
                    $q->whereNull('grade')
                      ->orWhereIn('grade', ['2', '0'])
                      ->orWhere('retraining_status', true);
                })
                ->when($currentSemesterId, fn($q) => $q->where('semester_id', '!=', $currentSemesterId))
                ->orderBy('semester_name')
                ->orderBy('subject_name')
                ->get();
        }

        $debtSubjectsCount = $debtRecords->count();

        $recentGrades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'employee_name' => $g->employee_name,
            ]);

        // Semester-level averages for comparison
        $semesterAvgs = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->select('semester_code', DB::raw('AVG(CAST(grade AS DECIMAL(10,2))) as semester_avg'))
            ->groupBy('semester_code')
            ->orderBy('semester_code')
            ->pluck('semester_avg', 'semester_code');

        $currentSemesterCode = $student->semester_code;
        $semesterKeys = $semesterAvgs->keys()->toArray();
        $currentIndex = array_search($currentSemesterCode, $semesterKeys);

        $currentSemesterAvg = $semesterAvgs[$currentSemesterCode] ?? null;
        $prevSemesterAvg = ($currentIndex !== false && $currentIndex > 0)
            ? $semesterAvgs[$semesterKeys[$currentIndex - 1]]
            : null;

        // Semester-level GPA (5-point scale) for the GPA trend chip.
        $semesterGpas = AcademicRecord::where('student_id', $student->hemis_id)
            ->whereNotNull('grade')
            ->whereNotIn('grade', ['', '0'])
            ->select('semester_id', DB::raw('AVG(CAST(grade AS DECIMAL(10,2))) as semester_gpa'))
            ->groupBy('semester_id')
            ->orderBy('semester_id')
            ->pluck('semester_gpa', 'semester_id');

        $gpaKeys = array_map('strval', $semesterGpas->keys()->toArray());
        $currentGpaIndex = array_search((string) $currentSemesterId, $gpaKeys);
        $currentSemesterGpa = ($currentGpaIndex !== false)
            ? $semesterGpas[$semesterGpas->keys()[$currentGpaIndex]]
            : null;
        $prevSemesterGpa = ($currentGpaIndex !== false && $currentGpaIndex > 0)
            ? $semesterGpas[$semesterGpas->keys()[$currentGpaIndex - 1]]
            : null;

        // Attendance streak — consecutive days since the student's last absence.
        // An absence = a lesson with absent_on > 0 OR absent_off > 0.
        $lastAbsenceDate = Attendance::where('student_id', $student->id)
            ->where(function ($q) {
                $q->where('absent_on', '>', 0)
                  ->orWhere('absent_off', '>', 0);
            })
            ->max('lesson_date');
        $firstLessonDate = Attendance::where('student_id', $student->id)
            ->min('lesson_date');
        $attendanceStreak = null;
        $today = Carbon::now()->startOfDay();
        if ($lastAbsenceDate) {
            $attendanceStreak = Carbon::parse($lastAbsenceDate)->startOfDay()->diffInDays($today);
        } elseif ($firstLessonDate) {
            $attendanceStreak = Carbon::parse($firstLessonDate)->startOfDay()->diffInDays($today);
        }

        return response()->json([
            'data' => [
                'student_name' => $student->full_name,
                'gpa' => (float) $avgGpa,
                'avg_grade' => $student->avg_grade ?? 0,
                'current_semester_avg' => $currentSemesterAvg ? round((float) $currentSemesterAvg, 2) : null,
                'prev_semester_avg' => $prevSemesterAvg ? round((float) $prevSemesterAvg, 2) : null,
                'current_semester_gpa' => $currentSemesterGpa ? round((float) $currentSemesterGpa, 2) : null,
                'prev_semester_gpa' => $prevSemesterGpa ? round((float) $prevSemesterGpa, 2) : null,
                'attendance_streak_days' => $attendanceStreak,
                'debt_subjects' => $debtSubjectsCount,
                'debt_by_semester' => $debtBySemester,
                'total_absences' => $totalAbsent,
                'recent_grades' => $recentGrades,
            ],
        ]);
    }

    /**
     * Profile — student info
     */
    public function profile(Request $request): JsonResponse
    {
        $student = $request->user();
        $admissionInfo = null;

        // Calculate course number from semester name (e.g. "8-semestr" -> kurs 4)
        $course = null;
        if ($student->semester_name && preg_match('/(\d+)/', $student->semester_name, $matches)) {
            $semNum = (int) $matches[1];
            $course = (int) ceil($semNum / 2);
        }
        if (!$course && $student->year_of_enter) {
            $enterYear = (int) $student->year_of_enter;
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');
            $course = $currentMonth >= 9
                ? $currentYear - $enterYear + 1
                : $currentYear - $enterYear;
        }
        if (Schema::hasTable('admission_indicators')) {
            $admissionInfo = AdmissionIndicator::query()
                ->where('student_id', $student->id)
                ->orderByDesc('qabul_yili')
                ->orderByDesc('id')
                ->first(['qabul_yili', 'toplagan_bali']);
        }


        return response()->json([
            'data' => [
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'image' => $student->image,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'phone' => $student->phone ?? '',
                'hemis_phone' => $student->other['phone'] ?? '',
                'email' => $student->other['email'] ?? '',
                'telegram_username' => $student->telegram_username ?? '',
                'telegram_verified' => $student->isTelegramVerified(),
                'telegram_days_left' => $student->telegramDaysLeft(),
                'profile_complete' => $student->isProfileComplete(),
                'gender' => $student->gender,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'specialty_name' => $student->specialty_name,
                'level_code' => $student->level_code,
                'level_name' => $student->level_name,
                'course' => $course,
                'education_type_name' => $student->education_type_name,
                'education_form_name' => $student->education_form_name ?? null,
                'education_year_code' => $student->education_year_code,
                'education_year_name' => $student->education_year_name,
                'year_of_enter' => $student->year_of_enter,
                'semester_code' => $student->semester_code,
                'semester_name' => $student->semester_name,
                'province_name' => $student->province_name,
                'district_name' => $student->district_name,
                'avg_gpa' => $student->avg_gpa,
                'avg_grade' => $student->avg_grade,
                'total_credit' => $student->total_credit ?? null,
                'payment_form_code' => $student->payment_form_code,
                'payment_form_name' => $student->payment_form_name,
                'is_graduate' => $student->is_graduate,
            ],
                'admission_year' => $admissionInfo?->qabul_yili,
                'admission_score' => $admissionInfo?->toplagan_bali,
        ]);
    }

    /**
     * Mobile uchun qayta o'qish arizasi overview.
     * Web sahifadagi asosiy bloklar va jurnal kartasini JSON ko'rinishida qaytaradi.
     */
    public function retakeOverview(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        /** @var RetakeWindowService $windowService */
        $windowService = app(RetakeWindowService::class);
        /** @var RetakeDebtService $debtService */
        $debtService = app(RetakeDebtService::class);
        /** @var RetakeApplicationService $applicationService */
        $applicationService = app(RetakeApplicationService::class);

        $window = $windowService->activeWindowForStudent($student);

        $activeApplications = RetakeApplication::query()
            ->forStudent((int) $student->hemis_id)
            ->whereIn('final_status', [
                RetakeApplication::STATUS_PENDING,
                RetakeApplication::STATUS_APPROVED,
            ])
            ->with(['retakeGroup.teacher'])
            ->get()
            ->keyBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id);

        $history = RetakeApplicationGroup::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->with(['applications.retakeGroup.teacher', 'window.session'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $groupsAwaitingPayment = $history->filter(fn (RetakeApplicationGroup $g) => $g->requires_payment)->values();
        $groupsPaymentVerifying = $history->filter(fn (RetakeApplicationGroup $g) => $g->payment_awaiting_verification)->values();

        $currentSemester = $applicationService->currentSemesterNumber($student);
        $remainingSlots = $applicationService->currentSemesterRemainingSlots($student, $window?->id);
        $creditPrice = RetakeSetting::creditPrice();
        $receiptMaxMb = RetakeSetting::receiptMaxMb();

        $debts = $debtService->debts($student)->map(function ($d) use ($activeApplications, $currentSemester) {
            $key = $d->subject_id . '|' . $d->semester_id;
            /** @var RetakeApplication|null $app */
            $app = $activeApplications->get($key);
            $group = $app?->retakeGroup;
            $semNum = preg_match('/(\d+)/', (string) ($d->semester_name ?: $d->semester_id), $mm) ? (int) $mm[1] : null;

            return [
                'subject_id' => (string) $d->subject_id,
                'subject_name' => (string) $d->subject_name,
                'semester_id' => (string) $d->semester_id,
                'semester_name' => (string) ($d->semester_name ?: $d->semester_id),
                'credit' => (float) $d->credit,
                'debt_reason' => $d->debt_reason ?? null,
                'is_current_semester' => $currentSemester !== null && $semNum !== null && $semNum === (int) $currentSemester,
                'active_status' => $app?->studentDisplayStatus(),
                'is_active' => $app !== null,
                'final_status' => $app?->final_status,
                'retake_group' => $group ? [
                    'id' => $group->id,
                    'name' => $group->name,
                    'teacher_name' => $group->teacher_name ?? ($group->teacher?->full_name ?? null),
                    'teacher_phones' => $group->teacher_phones ?? [],
                    'start_date' => optional($group->start_date)->format('Y-m-d'),
                    'end_date' => optional($group->end_date)->format('Y-m-d'),
                ] : null,
            ];
        })->values();

        $journalApplications = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($a) => $a->retakeGroup !== null)
            ->values();

        return response()->json([
            'data' => [
                'journal_card' => [
                    'title' => "Qayta o'qish jurnali",
                    'description' => "Tasdiqlangan qayta o'qish fanlaringiz bo'yicha baholar, guruh, o'qituvchi va mustaqil ta'lim topshiriqlarini shu yerda ko'rishingiz mumkin.",
                    'count' => $journalApplications->count(),
                    'has_items' => $journalApplications->isNotEmpty(),
                ],
                'warning_card' => [
                    'title' => 'Hurmatli talaba!',
                    'message' => "Joriy semestr fanlaridan aktiv (kutilayotgan + tasdiqlangan) arizalaringiz bilan birga jami 3 tadan ko'p ariza topshira olmaysiz. Boshqa (oldingi) semestrlardagi qarzlaringizga limit yo'q — barchasiga ariza topshirishingiz mumkin. Rad etilgan arizalar bu hisobga kirmaydi.",
                ],
                'window' => $window ? [
                    'id' => $window->id,
                    'specialty_name' => $student->specialty_name,
                    'level_name' => $student->level_name ?? $student->level_code,
                    'semester_name' => $window->semester_name,
                    'start_date' => optional($window->start_date)->format('Y-m-d'),
                    'end_date' => optional($window->end_date)->format('Y-m-d'),
                    'status' => $window->status,
                    'is_open' => $window->isOpen(),
                    'status_label' => $window->isOpen()
                        ? 'Ariza qabul ochiq'
                        : ($window->status === 'study' ? "O'qish davri - ariza qabul tugadi" : 'Muddat tugagan'),
                ] : null,
                'window_missing_message' => $window
                    ? null
                    : "Sizning yo'nalishingiz va kursingiz uchun qayta o'qish ariza qabul qilish oynasi hali ochilmagan.",
                'remaining_slots' => $remainingSlots,
                'current_semester' => $currentSemester,
                'credit_price' => $creditPrice,
                'receipt_max_mb' => $receiptMaxMb,
                'window_open' => (bool) ($window && $window->isOpen()),
                'debts' => $debts,
                'groups_awaiting_payment' => $groupsAwaitingPayment->map(function (RetakeApplicationGroup $group) {
                    return [
                        'id' => $group->id,
                        'receipt_amount' => (float) $group->receipt_amount,
                        'payment_verification_status' => $group->payment_verification_status,
                        'payment_rejection_reason' => $group->payment_rejection_reason,
                        'approved_subjects_count' => $group->applications
                            ->where('dean_status', 'approved')
                            ->where('registrar_status', 'approved')
                            ->count(),
                    ];
                })->values(),
                'groups_payment_verifying' => $groupsPaymentVerifying->map(function (RetakeApplicationGroup $group) {
                    return [
                        'id' => $group->id,
                        'payment_uploaded_at' => optional($group->payment_uploaded_at)->format('Y-m-d H:i:s'),
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Mobile uchun qayta o'qish jurnali ro'yxati.
     */
    public function retakeJournalIndex(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $applications = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($a) => $a->retakeGroup !== null)
            ->values();

        return response()->json([
            'data' => [
                'count' => $applications->count(),
                'items' => $applications->map(fn (RetakeApplication $app) => $this->mapRetakeJournalCard($app))->values(),
            ],
        ]);
    }

    /**
     * Mobile uchun qayta o'qish jurnalining bitta kartasi.
     */
    public function retakeJournalShow(Request $request, int $applicationId): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $app = RetakeApplication::query()
            ->where('id', $applicationId)
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->with(['retakeGroup.teacher'])
            ->first();

        if (!$app || !$app->retakeGroup) {
            return response()->json(['message' => 'Bu jurnal sizga tegishli emas yoki guruh topilmadi'], 403);
        }

        /** @var RetakeJournalService $journalService */
        $journalService = app(RetakeJournalService::class);
        $group = $app->retakeGroup;
        $mustaqil = RetakeMustaqilSubmission::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $app->id)
            ->first();

        $dates = $journalService->lessonDates($group);
        $gradesMap = $journalService->gradesMap($group);
        $appGrades = $gradesMap[$app->id] ?? [];
        $isEditable = $journalService->isEditable($group);

        return response()->json([
            'data' => [
                'application' => [
                    'id' => $app->id,
                    'subject_id' => (string) $app->subject_id,
                    'subject_name' => (string) $app->subject_name,
                    'semester_id' => (string) $app->semester_id,
                    'semester_name' => (string) $app->semester_name,
                    'credit' => (float) $app->credit,
                    'joriy_score' => $app->joriy_score !== null ? (float) $app->joriy_score : null,
                    'joriy_graded_by_name' => $app->joriy_graded_by_name,
                    'joriy_graded_at' => optional($app->joriy_graded_at)->format('Y-m-d H:i:s'),
                    'oske_score' => $app->oske_score !== null ? (float) $app->oske_score : null,
                    'test_score' => $app->test_score !== null ? (float) $app->test_score : null,
                    'final_grade_value' => $app->final_grade_value !== null ? (float) $app->final_grade_value : null,
                ],
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'teacher_name' => $group->teacher_name ?? ($group->teacher?->full_name ?? null),
                    'teacher_phones' => $group->teacher_phones ?? [],
                    'start_date' => optional($group->start_date)->format('Y-m-d'),
                    'end_date' => optional($group->end_date)->format('Y-m-d'),
                    'assessment_type' => $group->assessment_type,
                    'assessment_type_label' => match ($group->assessment_type) {
                        'oske' => 'OSKE',
                        'test' => 'TEST',
                        'oske_test' => 'OSKE + TEST',
                        'sinov_fan' => 'Sinov fan',
                        default => '—',
                    },
                    'oske_date' => optional($group->oske_date)->format('Y-m-d'),
                    'test_date' => optional($group->test_date)->format('Y-m-d'),
                    'status' => $group->status,
                    'status_label' => $isEditable ? 'Davom etmoqda' : $group->statusLabel(),
                ],
                'is_editable' => $isEditable,
                'lesson_dates' => $dates,
                'daily_grades' => collect($dates)->map(function (string $date) use ($appGrades) {
                    $grade = $appGrades[$date] ?? null;

                    return [
                        'lesson_date' => $date,
                        'grade' => $grade?->grade !== null ? (float) $grade->grade : null,
                        'comment' => $grade?->comment,
                        'graded_by_name' => $grade?->graded_by_name,
                        'graded_at' => optional($grade?->graded_at)->format('Y-m-d H:i:s'),
                    ];
                })->values(),
                'mustaqil' => $mustaqil ? [
                    'id' => $mustaqil->id,
                    'original_filename' => $mustaqil->original_filename,
                    'student_comment' => $mustaqil->student_comment,
                    'submitted_at' => optional($mustaqil->submitted_at)->format('Y-m-d H:i:s'),
                    'grade' => $mustaqil->grade !== null ? (float) $mustaqil->grade : null,
                    'teacher_comment' => $mustaqil->teacher_comment,
                    'graded_by_name' => $mustaqil->graded_by_name,
                    'graded_at' => optional($mustaqil->graded_at)->format('Y-m-d H:i:s'),
                    'attempt_count' => (int) ($mustaqil->attempt_count ?? 0),
                    'is_passed' => $mustaqil->isPassed(),
                    'can_resubmit' => $mustaqil->canResubmit(),
                    'attempts_exhausted' => $mustaqil->attemptsExhausted(),
                ] : null,
                'mustaqil_rules' => [
                    'max_file_mb' => RetakeMustaqilSubmission::MAX_FILE_MB,
                    'max_attempts' => RetakeMustaqilSubmission::MAX_ATTEMPTS,
                    'pass_grade' => RetakeMustaqilSubmission::PASS_GRADE,
                ],
            ],
        ]);
    }

    /**
     * Schedule — by semester and week
     */
    public function schedule(Request $request): JsonResponse
    {
        $student = $request->user();

        // Get semesters
        $semesters = Semester::where('curriculum_hemis_id', $student->curriculum_id)
            ->get()
            ->map(fn($sem) => [
                'id' => $sem->semester_hemis_id,
                'name' => $sem->name,
                'code' => $sem->code,
                'current' => (bool) $sem->current,
            ]);

        $studentSemester = $semesters->firstWhere('code', $student->semester_code);
        $currentSemester = $semesters->firstWhere('current', true);
        $selectedSemesterId = $request->input(
            'semester_id',
            $studentSemester['id'] ?? $currentSemester['id'] ?? $semesters->first()['id'] ?? null
        );
        $selectedSemester = $semesters->firstWhere('id', $selectedSemesterId);

        if (!$selectedSemester) {
            return response()->json(['message' => 'Semestr topilmadi.'], 404);
        }

        // Get weeks
        $weeks = CurriculumWeek::where('semester_hemis_id', $selectedSemesterId)
            ->orderBy('start_date')
            ->get()
            ->map(fn($week) => [
                'id' => $week->curriculum_week_hemis_id,
                'start_date' => $week->start_date->format('Y-m-d'),
                'end_date' => $week->end_date->format('Y-m-d'),
            ])->values();

        // Find current or selected week
        $currentDate = Carbon::now();
        $currentWeek = $weeks->first(fn($w) =>
            $currentDate->between(Carbon::parse($w['start_date']), Carbon::parse($w['end_date']))
        );
        if (!$currentWeek) {
            $currentWeek = $weeks->first(fn($w) => Carbon::parse($w['start_date'])->isAfter($currentDate));
        }

        $selectedWeekId = $request->input('week_id', $currentWeek['id'] ?? null);
        $selectedWeek = $weeks->firstWhere('id', $selectedWeekId);

        if ($selectedWeek) {
            $weekStart = Carbon::parse($selectedWeek['start_date']);
            $weekEnd = Carbon::parse($selectedWeek['end_date']);
        } else {
            $weekStart = $currentDate->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->addDays(5);
        }

        // Query schedule
        $scheduleQuery = Schedule::where('group_id', $student->group_id)
            ->where('semester_code', $selectedSemester['code']);

        if ($weekStart && $weekEnd) {
            $scheduleQuery->whereBetween('lesson_date', [$weekStart, $weekEnd]);
        }

        $scheduleRecords = $scheduleQuery->get();

        $groupedSchedule = $scheduleRecords
            ->groupBy(fn($lesson) => Carbon::parse($lesson->lesson_date)->format('Y-m-d'))
            ->map(function ($dayLessons, $date) {
                $lessons = $dayLessons
                    ->unique(fn($l) => $l->subject_id . $l->lesson_pair_start_time . $l->lesson_pair_end_time . $l->auditorium_code . $l->employee_id)
                    ->map(fn($l) => [
                        'subject_name' => $l->subject_name,
                        'subject_id' => $l->subject_id,
                        'employee_name' => $l->employee_name,
                        'auditorium_name' => $l->auditorium_name ?? '',
                        'lesson_pair_code' => $l->lesson_pair_code ?? null,
                        'lesson_pair_start_time' => $l->lesson_pair_start_time,
                        'lesson_pair_end_time' => $l->lesson_pair_end_time,
                        'training_type_name' => $l->training_type_name,
                    ])
                    ->sortBy('lesson_pair_start_time')
                    ->values();

                return [
                    'date' => $date,
                    'day_name' => Carbon::parse($date)->locale('uz')->dayName,
                    'lessons' => $lessons,
                ];
            })
            ->sortKeys()
            ->values();

        // Build days map keyed by day_name for mobile app
        $days = [];
        foreach ($groupedSchedule as $day) {
            $days[$day['day_name']] = $day['lessons'];
        }

        $weekLabel = $weekStart->format('d.m') . ' - ' . $weekEnd->format('d.m.Y');

        return response()->json([
            'data' => [
                'semesters' => $semesters,
                'selected_semester_id' => $selectedSemesterId,
                'weeks' => $weeks,
                'selected_week_id' => $selectedWeekId,
                'week_label' => $weekLabel,
                'days' => (object) $days,
                'schedule' => $groupedSchedule,
            ],
        ]);
    }

    /**
     * Subjects with all grade breakdowns (JB, MT, ON, OSKI, Test)
     */
    public function subjects(Request $request): JsonResponse
    {
        $student = $request->user();
        $semesterCode = $student->semester_code;
        $studentHemisId = $student->hemis_id;
        $groupHemisId = $student->group_id;

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $gradingCutoffDate = Carbon::now('Asia/Tashkent')->endOfDay();

        $getEffectiveGrade = function ($row) {
            if ($row->status === 'pending') return null;
            if ($row->reason === 'absent' && $row->grade === null) {
                return $row->retake_grade !== null ? $row->retake_grade : null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            if ($row->status === 'recorded') return $row->grade;
            if ($row->status === 'closed') return $row->grade;
            if ($row->retake_grade !== null) return $row->retake_grade;
            return null;
        };

        $curriculumSubjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $semesterCode)
            ->where('is_active', true)
            ->get();

        // MT (Independent) data
        $allIndependents = Independent::where('group_hemis_id', $groupHemisId)->get();
        $independentsByHemisId = $allIndependents->groupBy('subject_hemis_id');
        $indHemisIds = $allIndependents->pluck('subject_hemis_id')->unique()->filter()->toArray();
        $hemisToSubjectId = [];
        if (!empty($indHemisIds)) {
            $hemisToSubjectId = CurriculumSubject::whereIn('curriculum_subject_hemis_id', $indHemisIds)
                ->pluck('subject_id', 'curriculum_subject_hemis_id')->toArray();
        }
        $independentsBySubjectId = collect();
        foreach ($allIndependents as $ind) {
            $resolvedSubjectId = $hemisToSubjectId[$ind->subject_hemis_id] ?? null;
            if ($resolvedSubjectId) {
                if (!$independentsBySubjectId->has($resolvedSubjectId)) {
                    $independentsBySubjectId[$resolvedSubjectId] = collect();
                }
                $independentsBySubjectId[$resolvedSubjectId]->push($ind);
            }
        }
        $independentsByName = $allIndependents->groupBy('subject_name');

        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);
        $timeParts = explode(':', $mtDeadlineTime);
        $mtHour = (int) ($timeParts[0] ?? 17);
        $mtMinute = (int) ($timeParts[1] ?? 0);

        $subjects = $curriculumSubjects->map(function ($cs) use (
            $semesterCode, $studentHemisId, $groupHemisId, $educationYearCode,
            $excludedTrainingCodes, $gradingCutoffDate, $getEffectiveGrade,
            $student, $independentsByHemisId, $independentsBySubjectId, $independentsByName,
            $mtHour, $mtMinute, $mtMaxResubmissions, $mtDeadlineTime
        ) {
            $subjectId = $cs->subject_id;

            // Education year code from schedule
            $subjectEducationYearCode = $educationYearCode;
            $scheduleEducationYear = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->value('education_year_code');
            if ($scheduleEducationYear) {
                $subjectEducationYearCode = $scheduleEducationYear;
            }

            // JB (Amaliyot) grades
            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')
                ->get();

            // Determine earliest schedule date from ALL training types (JB + MT + Ma'ruza)
            // to match JournalController behavior for education_year_code fallback filtering
            $allScheduleDatesForMin = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotNull('lesson_date')
                ->pluck('lesson_date');
            $minScheduleDate = $allScheduleDatesForMin->min();
            $maxScheduleDate = $allScheduleDatesForMin->max();

            $jbGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode);
                    if ($minScheduleDate !== null) {
                        $q2->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->where('lesson_date', '>=', $minScheduleDate);
                        });
                    }
                }))
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $jbColumns = $jbScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
                ->merge($jbGradesRaw->map(fn($g) => ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code]))
                ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
                ->sortBy('date')
                ->values();

            $jbPairsPerDay = [];
            foreach ($jbColumns as $col) {
                $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $jbLessonDates = $jbColumns->pluck('date')->unique()->sort()->values()->toArray();
            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, fn($date) =>
                Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate)
            ));
            $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
            $totalJbDaysForAverage = count($jbLessonDatesForAverage);

            $jbGradesByDatePair = [];
            foreach ($jbGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $jbGradesByDatePair[$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
                }
            }

            $dailySum = 0;
            foreach ($jbLessonDates as $date) {
                $dayGrades = $jbGradesByDatePair[$date] ?? [];
                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                if (isset($jbLessonDatesForAverageLookup[$date])) {
                    $dailySum += $dayAverage;
                }
            }
            $jnAverage = $totalJbDaysForAverage > 0
                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                : 0;

            // MT (Mustaqil ta'lim)
            $mtGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $mtColumns = $mtGradesRaw->map(fn($g) => ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code])
                ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
                ->sortBy('date')
                ->values();

            $mtPairsPerDay = [];
            foreach ($mtColumns as $col) {
                $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
            }
            $mtLessonDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();
            $totalMtDays = count($mtLessonDates);

            $mtGradesByDatePair = [];
            foreach ($mtGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $mtGradesByDatePair[$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
                }
            }

            $mtDailySum = 0;
            foreach ($mtLessonDates as $date) {
                $dayGrades = $mtGradesByDatePair[$date] ?? [];
                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                $mtDailySum += round(array_sum($dayGrades) / $pairsInDay, 0, PHP_ROUND_HALF_UP);
            }
            $mtAverage = $totalMtDays > 0
                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                : 0;

            // Manual MT grade override
            $manualMt = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->value('grade');
            if ($manualMt !== null) {
                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
            }

            // ON, OSKI, Test, Quiz (100, 101, 102, 103)
            $otherGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [100, 101, 102, 103])
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode)
                        ->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                        });
                }))
                ->when($subjectEducationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('training_type_code', 'grade', 'retake_grade', 'status', 'reason', 'quiz_result_id', 'attempt')
                ->get();

            $otherGrades = ['on' => null, 'oski' => null, 'test' => null];
            $otherByType = [];
            foreach ($otherGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $typeCode = $g->training_type_code;
                    // Legacy code 103 quiz grades: resolve to OSKI(101) or Test(102) via quiz_result
                    if ($typeCode == 103 && $g->quiz_result_id) {
                        $quizType = DB::table('hemis_quiz_results')->where('id', $g->quiz_result_id)->value('quiz_type');
                        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
                        if (in_array($quizType, $oskiTypes)) {
                            $typeCode = 101;
                        } elseif (in_array($quizType, $testTypes)) {
                            $typeCode = 102;
                        }
                    }
                    $attempt = (int) ($g->attempt ?? 1);
                    $otherByType[$typeCode][$attempt][] = $effectiveGrade;
                }
            }
            // 2-urinish 1-urinishni almashtiradi (o'rtachalanmaydi).
            $pickLatestAttempt = function (?array $byAttempt): ?float {
                if (empty($byAttempt)) {
                    return null;
                }
                $latest = max(array_keys($byAttempt));
                $grades = $byAttempt[$latest];
                if (empty($grades)) {
                    return null;
                }
                return round(array_sum($grades) / count($grades), 0, PHP_ROUND_HALF_UP);
            };
            $otherGrades['on'] = $pickLatestAttempt($otherByType[100] ?? null);
            $otherGrades['oski'] = $pickLatestAttempt($otherByType[101] ?? null);
            $otherGrades['test'] = $pickLatestAttempt($otherByType[102] ?? null);
            $closingForm = strtolower(str_replace('-', '_', trim((string) ($cs->closing_form ?? ''))));
            $requiresOski = in_array($closingForm, ['oski', 'oske', 'oski_test', 'oske_test'], true);
            $requiresTest = in_array($closingForm, ['test', 'oski_test', 'oske_test'], true);
            $hasRequiredFinalGrades =
                (!$requiresOski || $otherGrades['oski'] !== null) &&
                (!$requiresTest || $otherGrades['test'] !== null);
            $hasAnyFinalGrade = $otherGrades['oski'] !== null || $otherGrades['test'] !== null;
            $ynCanCalculate = false;

            if (!$requiresOski && !$requiresTest) {
                $lastLessonDateReached = $maxScheduleDate !== null &&
                    Carbon::parse($maxScheduleDate, 'Asia/Tashkent')->endOfDay()->lte($gradingCutoffDate);
                $ynCanCalculate = $hasAnyFinalGrade || $lastLessonDateReached;
            } else {
                $examSchedule = DB::table('exam_schedules')
                    ->where('group_hemis_id', $groupHemisId)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->select('oski_date', 'test_date')
                    ->orderByDesc('id')
                    ->first();
                $today = Carbon::now('Asia/Tashkent')->toDateString();
                $oskiDateReached = !$requiresOski ||
                    ($examSchedule?->oski_date && Carbon::parse($examSchedule->oski_date, 'Asia/Tashkent')->toDateString() <= $today);
                $testDateReached = !$requiresTest ||
                    ($examSchedule?->test_date && Carbon::parse($examSchedule->test_date, 'Asia/Tashkent')->toDateString() <= $today);

                $ynCanCalculate = $hasRequiredFinalGrades || ($oskiDateReached && $testDateReached);
            }

            // Attendance
            $absentOff = DB::table('attendances')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_code', [99, 100, 101, 102])
                ->sum('absent_off');

            $nonAuditoriumCodes = ['17'];
            $auditoriumHours = 0;
            if (is_array($cs->subject_details)) {
                foreach ($cs->subject_details as $detail) {
                    $trainingCode = (string) ($detail['trainingType']['code'] ?? '');
                    if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                        $auditoriumHours += (float) ($detail['academic_load'] ?? 0);
                    }
                }
            }
            if ($auditoriumHours <= 0) {
                $auditoriumHours = $cs->total_acload ?? 0;
            }
            $davomatPercent = $auditoriumHours > 0 ? round(($absentOff / $auditoriumHours) * 100, 2) : 0;

            $total = null;
            $gradeComponents = array_filter([$jnAverage, $mtAverage, $otherGrades['on'], $otherGrades['oski'], $otherGrades['test']], fn($v) => $v !== null && $v > 0);
            if (!empty($gradeComponents)) {
                $total = (int) round(array_sum($gradeComponents) / count($gradeComponents));
            }

            // MT submission data
            $mtData = null;
            $subjectIndependents = $independentsByHemisId->get($cs->curriculum_subject_hemis_id)
                ?? $independentsBySubjectId->get($cs->subject_id)
                ?? $independentsByName->get($cs->subject_name);

            if ($subjectIndependents && $subjectIndependents->count() > 0) {
                $independent = $subjectIndependents->sortByDesc('deadline')->first();
                $submission = $independent->submissionByStudent($student->id);
                $grade = StudentGrade::where('student_id', $student->id)
                    ->where('independent_id', $independent->id)
                    ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                    ->first();

                $deadlineDateTime = Carbon::parse($independent->deadline)->setTime($mtHour, $mtMinute, 0);
                $isOverdue = Carbon::now()->gt($deadlineDateTime);
                $submissionCount = $submission?->submission_count ?? 0;

                $mtHistoryCount = DB::table('mt_grade_history')
                    ->where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $independent->subject_hemis_id)
                    ->where('semester_code', $independent->semester_code)
                    ->count();
                $remainingAttempts = max(0, $mtMaxResubmissions - $mtHistoryCount);

                try {
                    $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
                } catch (\Exception $e) {
                    $studentMinLimit = 56;
                }
                $gradeLocked = $grade && $grade->grade >= $studentMinLimit;

                $canSubmit = !$isOverdue && !$gradeLocked;
                if ($submission && $grade && $grade->grade < $studentMinLimit) {
                    $canSubmit = $canSubmit && $remainingAttempts > 0;
                }

                $mtData = [
                    'independent_id' => $independent->id,
                    'deadline' => Carbon::parse($independent->deadline)->format('d.m.Y'),
                    'deadline_time' => $mtDeadlineTime,
                    'is_overdue' => $isOverdue,
                    'has_submission' => (bool) $submission,
                    'file_name' => $submission?->file_original_name,
                    'grade' => $grade?->grade,
                    'grade_locked' => $gradeLocked,
                    'submission_count' => $submissionCount,
                    'remaining_attempts' => $remainingAttempts,
                    'can_submit' => $canSubmit,
                ];
            }

            return [
                'subject_name' => $cs->subject_name,
                'credit' => $cs->credit,
                'subject_id' => $subjectId,
                'closing_form' => ((function () use ($cs) {
                    $apiClosingForm = $cs->closing_form;
                    $normalizedClosingForm = strtolower(str_replace('-', '_', trim((string) ($cs->closing_form ?? ''))));
                    if ($normalizedClosingForm === 'sinov') {
                        return 'sinov_test';
                    }
                    return $apiClosingForm;
                })()),
                'yn_can_calculate' => $ynCanCalculate,
                'employee_name' => null,
                'grades' => [
                    'jn' => $jnAverage > 0 ? $jnAverage : null,
                    'mt' => $mtAverage > 0 ? $mtAverage : null,
                    'on' => $otherGrades['on'],
                    'oski' => $otherGrades['oski'],
                    'test' => $otherGrades['test'],
                    'total' => $total,
                ],
                'dav_percent' => $davomatPercent,
                'absent_hours' => $absentOff,
                'auditorium_hours' => $auditoriumHours,
                'mt_submission' => $mtData,
            ];
        });

        return response()->json([
            'data' => $subjects->values(),
        ]);
    }

    /**
     * Grades detail for a specific subject
     */
    public function subjectGrades(Request $request, $subjectId): JsonResponse
    {
        $student = $request->user();
        $semester = $student->semester_code;
        $groupHemisId = $student->group_id;

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        // Get education_year_code from schedule (like subjects endpoint)
        $scheduleEducationYear = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->value('education_year_code');
        if ($scheduleEducationYear) {
            $educationYearCode = $scheduleEducationYear;
        }

        // Get minScheduleDate for education_year_code fallback (matches web journal)
        $minScheduleDate = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotNull('lesson_date')
            ->min('lesson_date');

        $grades = DB::table('student_grades')
            ->where('student_hemis_id', $student->hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->whereNotNull('lesson_date')
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode);
                if ($minScheduleDate !== null) {
                    $q2->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->where('lesson_date', '>=', $minScheduleDate);
                    });
                }
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->orderBy('lesson_date', 'desc')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'status' => $g->status,
                'reason' => $g->reason,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'training_type_code' => $g->training_type_code,
                'employee_name' => $g->employee_name,
                'lesson_pair_name' => $g->lesson_pair_name,
                'lesson_pair_start_time' => $g->lesson_pair_start_time,
                'lesson_pair_end_time' => $g->lesson_pair_end_time,
            ]);

        $scheduleDates = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->select('lesson_date', 'training_type_code', 'training_type_name')
            ->orderBy('lesson_date', 'asc')
            ->get()
            ->map(fn($s) => [
                'lesson_date' => $s->lesson_date,
                'training_type_code' => $s->training_type_code,
                'training_type_name' => $s->training_type_name,
            ]);

        return response()->json([
            'data' => [
                'subject_id' => $subjectId,
                'grades' => $grades,
                'schedule_dates' => $scheduleDates,
            ],
        ]);
    }

    /**
     * Pending/retake lessons
     */
    public function pendingLessons(Request $request): JsonResponse
    {
        $student = $request->user();

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $pendingLessons = DB::table('student_grades')
            ->where('student_hemis_id', $student->hemis_id)
            ->whereIn('status', ['pending', 'retake'])
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->orderBy('lesson_date')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'status' => $g->status,
                'reason' => $g->reason,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'employee_name' => $g->employee_name,
                'deadline' => $g->deadline,
            ]);

        return response()->json([
            'data' => $pendingLessons,
        ]);
    }

    /**
     * Upload MT (Mustaqil ta'lim) file — uses existing Independent system
     */
    public function mtUpload(Request $request, $subjectId): JsonResponse
    {
        $allowedExtensions = ['zip', 'doc', 'docx', 'ppt', 'pptx', 'pdf'];

        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                function ($attribute, $value, $fail) use ($allowedExtensions) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (!in_array($ext, $allowedExtensions)) {
                        $fail('Faqat zip, doc, docx, ppt, pptx, pdf formatlar qabul qilinadi.');
                    }
                },
            ],
        ]);

        $student = $request->user();

        // Find Independent record for this subject
        $cs = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $student->semester_code)
            ->where('subject_id', $subjectId)
            ->first();

        if (!$cs) {
            return response()->json(['message' => 'Fan topilmadi.'], 404);
        }

        $independent = Independent::where('group_hemis_id', $student->group_id)
            ->where(function ($q) use ($cs) {
                $q->where('subject_hemis_id', $cs->curriculum_subject_hemis_id)
                  ->orWhere('subject_name', $cs->subject_name);
            })
            ->orderBy('deadline', 'desc')
            ->first();

        if (!$independent) {
            return response()->json(['message' => 'MT topshiriq topilmadi.'], 404);
        }

        // YN lock check
        $ynLocked = StudentGrade::where('student_hemis_id', $student->hemis_id)
            ->where('subject_id', $independent->subject_hemis_id)
            ->where('semester_code', $independent->semester_code)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return response()->json(['message' => 'YN ga yuborilgan. Fayl yuklash mumkin emas.'], 422);
        }

        // Grade lock check
        $existingGrade = StudentGrade::where('student_id', $student->id)
            ->where('independent_id', $independent->id)
            ->first();

        try {
            $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
        } catch (\Exception $e) {
            $studentMinLimit = 56;
        }

        if ($existingGrade && $existingGrade->grade >= $studentMinLimit) {
            return response()->json(['message' => 'Baho ' . $studentMinLimit . ' va undan yuqori — qayta yuklash mumkin emas.'], 422);
        }

        // Deadline check
        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $timeParts = explode(':', $mtDeadlineTime);
        $hour = (int) ($timeParts[0] ?? 17);
        $minute = (int) ($timeParts[1] ?? 0);
        $deadlineTime = Carbon::parse($independent->deadline)->setTime($hour, $minute, 0);

        if (Carbon::now()->gt($deadlineTime)) {
            return response()->json(['message' => 'Topshiriq muddati tugagan (muddat: ' . $independent->deadline . ' soat ' . $mtDeadlineTime . ')'], 422);
        }

        // Resubmission check
        $existing = IndependentSubmission::where('independent_id', $independent->id)
            ->where('student_id', $student->id)
            ->first();

        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);

        if ($existing && $existingGrade && $existingGrade->grade < $studentMinLimit) {
            $mtHistoryCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $independent->subject_hemis_id)
                ->where('semester_code', $independent->semester_code)
                ->count();
            if ($mtHistoryCount >= $mtMaxResubmissions) {
                return response()->json(['message' => 'Qayta yuklash imkoniyati tugagan (maksimum ' . $mtMaxResubmissions . ' marta).'], 422);
            }
        }

        $file = $request->file('file');
        $filePath = $file->store('independent-submissions/' . $student->hemis_id, 'public');

        // Archive old grade on resubmission
        if ($existingGrade && $existingGrade->grade < $studentMinLimit && $existing) {
            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $independent->subject_hemis_id)
                ->where('semester_code', $independent->semester_code)
                ->count();

            DB::table('mt_grade_history')->insert([
                'student_hemis_id' => $student->hemis_id,
                'subject_id' => $existingGrade->subject_id,
                'semester_code' => $independent->semester_code,
                'attempt_number' => $attemptCount + 1,
                'grade' => $existingGrade->grade,
                'file_path' => $existing->file_path,
                'file_original_name' => $existing->file_original_name,
                'graded_by' => $existingGrade->employee_name ?? 'Admin',
                'graded_at' => $existingGrade->updated_at ?? $existingGrade->created_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('student_grades')->where('id', $existingGrade->id)->delete();
        } elseif ($existing && $existing->file_path && !$existingGrade) {
            Storage::disk('public')->delete($existing->file_path);
        }

        $newCount = $existing ? $existing->submission_count + 1 : 1;

        $submission = IndependentSubmission::updateOrCreate([
            'independent_id' => $independent->id,
            'student_id' => $student->id,
        ], [
            'student_hemis_id' => $student->hemis_id,
            'file_path' => $filePath,
            'file_original_name' => $file->getClientOriginalName(),
            'submitted_at' => now(),
            'submission_count' => $newCount,
            'viewed_at' => null,
        ]);

        return response()->json([
            'message' => 'Fayl muvaffaqiyatli yuklandi.',
            'data' => [
                'id' => $submission->id,
                'file_name' => $submission->file_original_name,
                'submission_count' => $submission->submission_count,
            ],
        ]);
    }

    /**
     * Get MT submissions for a subject
     */
    public function mtSubmissions(Request $request, $subjectId): JsonResponse
    {
        $student = $request->user();

        $cs = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $student->semester_code)
            ->where('subject_id', $subjectId)
            ->first();

        if (!$cs) {
            return response()->json(['data' => []]);
        }

        $independent = Independent::where('group_hemis_id', $student->group_id)
            ->where(function ($q) use ($cs) {
                $q->where('subject_hemis_id', $cs->curriculum_subject_hemis_id)
                  ->orWhere('subject_name', $cs->subject_name);
            })
            ->orderBy('deadline', 'desc')
            ->first();

        if (!$independent) {
            return response()->json(['data' => []]);
        }

        $submission = IndependentSubmission::where('independent_id', $independent->id)
            ->where('student_id', $student->id)
            ->first();

        $grade = StudentGrade::where('student_id', $student->id)
            ->where('independent_id', $independent->id)
            ->first();

        $gradeHistory = [];
        try {
            $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
                ->where('student_id', $student->id)
                ->orderBy('submission_number')
                ->get()
                ->map(fn($h) => [
                    'submission_number' => $h->submission_number,
                    'grade' => $h->grade,
                    'graded_at' => $h->created_at?->toISOString(),
                ]);
        } catch (\Exception $e) {}

        return response()->json([
            'data' => [
                'independent_id' => $independent->id,
                'subject_name' => $independent->subject_name,
                'deadline' => $independent->deadline,
                'has_submission' => (bool) $submission,
                'file_name' => $submission?->file_original_name,
                'submitted_at' => $submission?->submitted_at?->toISOString(),
                'submission_count' => $submission?->submission_count ?? 0,
                'grade' => $grade?->grade,
                'grade_history' => $gradeHistory,
            ],
        ]);
    }

    /**
     * Attendance records
     */
    public function attendance(Request $request): JsonResponse
    {
        $student = $request->user();
        $semester = $student->semester_code;

        $attendanceData = Attendance::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $semester)
            ->orderBy('lesson_date', 'desc')
            ->get()
            ->map(fn($item) => [
                'semester' => $item->semester_name,
                'date' => Carbon::parse($item->lesson_date)->format('Y-m-d'),
                'subject' => $item->subject_name,
                'training_type' => $item->training_type_name,
                'employee' => $item->employee_name,
                'lesson_pair' => $item->lesson_pair_name,
                'start_time' => $item->lesson_pair_start_time,
                'end_time' => $item->lesson_pair_end_time,
                'is_absent' => $item->absent_on > 0,
                'hours' => $item->absent_on == 0 ? 2 : 0,
            ]);

        return response()->json([
            'data' => [
                'attendance' => $attendanceData,
            ],
        ]);
    }

    /**
     * Save phone number for profile completion
     */
    public function savePhone(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+\d{7,15}$/'],
        ], [
            'phone.regex' => 'Telefon raqami noto\'g\'ri formatda. Masalan: +998901234567',
        ]);

        $student = $request->user();
        $isNewPhone = empty($student->phone);
        $student->phone = $request->phone;
        if ($isNewPhone || !$student->phone_added_at) {
            $student->phone_added_at = now();
        }
        $student->save();

        $days = (int) Setting::get('telegram_deadline_days', 19);

        return response()->json([
            'message' => "Telefon raqami saqlandi. Telegram hisobingizni {$days} kun ichida tasdiqlang.",
            'profile_complete' => $student->isProfileComplete(),
            'telegram_days_left' => $student->telegramDaysLeft(),
        ]);
    }

    /**
     * Save telegram username and generate verification code
     */
    public function saveTelegram(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_username' => ['required', 'string', 'regex:/^@[a-zA-Z0-9_]{5,32}$/'],
        ], [
            'telegram_username.regex' => 'Telegram username @username formatida bo\'lishi kerak (kamida 5 belgi).',
        ]);

        $student = $request->user();
        $student->telegram_username = $request->telegram_username;

        $code = \App\Http\Controllers\TelegramWebhookController::generateVerificationCode();
        $student->telegram_verification_code = $code;
        $student->telegram_verified_at = null;
        $student->telegram_chat_id = null;
        $student->save();

        $botUsername = config('services.telegram.bot_username', '');

        return response()->json([
            'message' => 'Telegram username saqlandi. Endi botga tasdiqlash kodini yuboring.',
            'verification_code' => $code,
            'bot_username' => $botUsername,
            'bot_link' => $botUsername ? "https://t.me/{$botUsername}?start={$code}" : null,
        ]);
    }

    /**
     * Check telegram verification status
     */
    public function checkTelegramVerification(Request $request): JsonResponse
    {
        $student = $request->user();

        return response()->json([
            'verified' => $student->isTelegramVerified(),
            'telegram_username' => $student->telegram_username,
            'telegram_days_left' => $student->telegramDaysLeft(),
        ]);
    }

    /**
     * Talaba imtihon tilini tanlaydi (group default ustidan).
     * Allowed values: keys/values of services.moodle.lang_map (uz/ru/en yoki uzb/rus/eng).
     */
    public function saveExamLanguage(Request $request): JsonResponse
    {
        $map = (array) config('services.moodle.lang_map', []);
        $allowed = array_values(array_unique(array_merge(array_keys($map), array_values($map))));

        $request->validate([
            'language_code' => ['nullable', 'string', 'in:' . implode(',', $allowed ?: ['uzb', 'rus', 'eng'])],
        ]);

        $student = $request->user();
        $student->exam_language_code = $request->input('language_code') ?: null;
        $student->save();

        return response()->json([
            'ok' => true,
            'exam_language_code' => $student->exam_language_code,
            'group_default_language_code' => optional(\App\Models\Group::where('group_hemis_id', $student->group_id)->first())->education_lang_code,
        ]);
    }

    /**
     * Contract — shartnoma ma'lumotlari (lokal DB dan)
     */
    public function contract(Request $request): JsonResponse
    {
        $student = $request->user();

        $currentSemester = Semester::where('current', true)->first();
        $educationYearCode = $currentSemester?->education_year ?? $student->education_year_code;

        // edu_year formati: "2025-2026 o`quv yili"
        $items = \App\Models\ContractList::where('student_hemis_id', $student->hemis_id)
            ->where('edu_year', 'like', $educationYearCode . '%')
            ->orderByDesc('education_year')
            ->get();

        $contracts = [];
        $totalAmount = 0;
        $paidAmount = 0;

        // Kontrakt summa turi — ruscha -> o'zbekcha
        $sumTypeTranslations = [
            'С стипендией' => 'Stipendiya bilan',
            'Без стипендии' => 'Stipendiyasiz',
            'Со скидкой' => 'Chegirma bilan',
            'Полная оплата' => "To'liq to'lov",
            'Грант' => 'Grant',
        ];

        foreach ($items as $item) {
            $amount = (float) ($item->edu_contract_sum ?? 0);
            $paid = (float) ($item->paid_credit_amount ?? 0);
            $unpaid = (float) ($item->unpaid_credit_amount ?? ($amount - $paid));

            $totalAmount += $amount;
            $paidAmount += $paid;

            $status = $unpaid <= 0 ? 'paid' : 'unpaid';

            $sumTypeName = $item->edu_contract_sum_type_name;
            $sumTypeNameUz = $sumTypeTranslations[$sumTypeName] ?? $sumTypeName;

            $contracts[] = [
                'id' => $item->hemis_id,
                'key' => $item->key,
                'education_year' => $item->education_year,
                'edu_year' => $item->edu_year,
                'edu_course' => $item->edu_course,
                'contract_amount' => $amount,
                'paid_amount' => $paid,
                'unpaid_amount' => $unpaid,
                'status' => $status,
                'contract_number' => $item->contract_number,
                'edu_contract_type_name' => $item->edu_contract_type_name,
                'edu_contract_sum_type_name' => $sumTypeNameUz,
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
            ];
        }

        // Kurs va semestr
        $course = null;
        if ($student->semester_name && preg_match('/(\d+)/', $student->semester_name, $matches)) {
            $semNum = (int) $matches[1];
            $course = (int) ceil($semNum / 2);
        }

        return response()->json([
            'data' => [
                'contracts' => $contracts,
                'summary' => [
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $totalAmount - $paidAmount,
                ],
                'student_name' => $student->full_name,
                'education_year' => $educationYearCode,
                'course' => $course,
                'semester_name' => $student->semester_name,
            ],
        ]);
    }

    public function examSchedule(Request $request): JsonResponse
    {
        $student = $request->user();

        $schedules = ExamSchedule::where('group_hemis_id', $student->group_id)
            ->where('semester_code', $student->semester_code)
            ->where(function ($query) use ($student) {
                $query->where('education_year', $student->education_year_code)
                    ->orWhereNull('education_year');
            })
            ->get();

        // Personal computer assignments for this student across the schedules above
        $assignments = \App\Models\ComputerAssignment::query()
            ->whereIn('exam_schedule_id', $schedules->pluck('id'))
            ->where('student_id_number', $student->student_id_number)
            ->get()
            ->keyBy(fn($a) => $a->exam_schedule_id . ':' . $a->yn_type);

        $exams = $schedules->flatMap(function ($exam) use ($assignments) {
            $items = [];

            if (!$exam->oski_na && $exam->oski_date) {
                $a = $assignments->get($exam->id . ':oski');
                $items[] = [
                    'subject_name' => $exam->subject_name,
                    'exam_type' => 'OSKI',
                    'date' => $exam->oski_date->format('Y-m-d'),
                    'time' => $exam->oski_time,
                    'computer_number' => $a?->computer_number,
                    'planned_start' => $a?->planned_start?->format('Y-m-d H:i'),
                    'planned_end' => $a?->planned_end?->format('Y-m-d H:i'),
                    'status' => $a?->status,
                ];
            }

            if (!$exam->test_na && $exam->test_date) {
                $a = $assignments->get($exam->id . ':test');
                $items[] = [
                    'subject_name' => $exam->subject_name,
                    'exam_type' => 'Test',
                    'date' => $exam->test_date->format('Y-m-d'),
                    'time' => $exam->test_time,
                    'computer_number' => $a?->computer_number,
                    'planned_start' => $a?->planned_start?->format('Y-m-d H:i'),
                    'planned_end' => $a?->planned_end?->format('Y-m-d H:i'),
                    'status' => $a?->status,
                ];
            }

            return $items;
        })
            ->sortBy('date')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $exams,
        ]);
    }

    public function studentRating(Request $request): JsonResponse
    {
        $student = $request->user();
        $myRating = StudentRating::where('student_hemis_id', $student->hemis_id)->first();

        $query = StudentRating::query();

        $filterType = $request->input('filter', 'group');
        if ($filterType === 'group' && $myRating) {
            $query->where('group_name', $myRating->group_name);
        } elseif ($filterType === 'specialty' && $myRating) {
            $query->where('specialty_code', $myRating->specialty_code)
                  ->where('level_code', $myRating->level_code);
        } elseif ($filterType === 'department' && $myRating) {
            $query->where('department_code', $myRating->department_code)
                  ->where('level_code', $myRating->level_code);
        }

        $students = $query->orderByDesc('jn_average')->get();

        $rank = 0;
        $myRank = 0;
        $list = [];
        $limit = ($filterType === 'group') ? null : 100;

        foreach ($students as $i => $s) {
            $rank = $i + 1;
            $isMe = $s->student_hemis_id == $student->hemis_id;
            if ($isMe) $myRank = $rank;

            if ($limit === null || $rank <= $limit || $isMe) {
                $list[] = [
                    'rank' => $rank,
                    'full_name' => $s->full_name,
                    'group_name' => $s->group_name,
                    'jn_average' => (float) $s->jn_average,
                    'subjects_count' => $s->subjects_count,
                    'is_me' => $isMe,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'my_rank' => $myRank,
                'my_jn_average' => $myRating ? (float) $myRating->jn_average : 0,
                'total_students' => count($students),
                'filter' => $filterType,
                'students' => $list,
            ],
        ]);
    }

    /**
     * Paginated list of notifications for the authenticated student.
     * Mirrors what the web /student/notifications page shows — but the API
     * never auto-marks as read (the web page does). Mobile uses a separate
     * mark-as-read endpoint.
     */
    public function notifications(Request $request): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('student_notifications')) {
            return response()->json([
                'data' => [],
                'unread_count' => 0,
                'total' => 0,
            ]);
        }

        $student = $request->user();
        $perPage = min((int) $request->input('per_page', 30), 100);
        $unreadOnly = $request->boolean('unread_only');

        $query = \App\Models\StudentNotification::where('student_id', $student->id)
            ->orderByDesc('created_at');
        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        $paginated = $query->paginate($perPage);
        $unreadCount = \App\Models\StudentNotification::where('student_id', $student->id)
            ->whereNull('read_at')->count();

        return response()->json([
            'data' => collect($paginated->items())->map(fn($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'link' => $n->link,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
            'unread_count' => $unreadCount,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
        ]);
    }

    /** Just the unread count — for the bell-icon badge. */
    public function notificationsUnreadCount(Request $request): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('student_notifications')) {
            return response()->json(['count' => 0]);
        }

        $count = \App\Models\StudentNotification::where('student_id', $request->user()->id)
            ->whereNull('read_at')->count();

        return response()->json(['count' => $count]);
    }

    /** Mark a single notification as read. */
    public function markNotificationRead(Request $request, $id): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        \App\Models\StudentNotification::where('id', $id)
            ->where('student_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /** Mark all notifications as read. */
    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        \App\Models\StudentNotification::where('student_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function mapRetakeJournalCard(RetakeApplication $app): array
    {
        $group = $app->retakeGroup;

        return [
            'application_id' => $app->id,
            'subject_id' => (string) $app->subject_id,
            'subject_name' => (string) $app->subject_name,
            'semester_id' => (string) $app->semester_id,
            'semester_name' => (string) $app->semester_name,
            'credit' => (float) $app->credit,
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'teacher_name' => $group->teacher_name ?? ($group->teacher?->full_name ?? null),
                'teacher_phones' => $group->teacher_phones ?? [],
                'start_date' => optional($group->start_date)->format('Y-m-d'),
                'end_date' => optional($group->end_date)->format('Y-m-d'),
                'status' => $group->status,
                'status_label' => $group->statusLabel(),
            ] : null,
        ];
    }
}

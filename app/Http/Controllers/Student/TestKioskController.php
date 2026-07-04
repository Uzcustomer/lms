<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TestSubject;
use App\Models\TestSubjectLesson;
use App\Models\TestSubjectLessonTest;
use App\Models\TestSubjectLessonTestAnswer;
use App\Models\TestSubjectLessonTestAttempt;
use App\Services\FaceIdService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class TestKioskController extends Controller
{
    public function index()
    {
        return view('student.test-kiosk.index');
    }

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'student_id_number' => ['required', 'string', 'max:50'],
        ]);

        $studentIdNumber = trim((string) $validated['student_id_number']);
        $student = Student::query()
            ->where('student_id_number', $studentIdNumber)
            ->first();

        if (!$student) {
            return back()
                ->withInput()
                ->withErrors(['student_id_number' => 'Bunday Student ID topilmadi.']);
        }

        return redirect()->route('student.test-kiosk.student', $studentIdNumber);
    }

    public function faceVerify(Request $request)
    {
        $request->validate([
            'student_id_number' => ['required', 'string', 'max:50'],
            'snapshot' => ['required', 'string', 'max:500000'],
            'liveness_passed' => ['required', 'boolean'],
        ]);

        $idNumber = trim((string) $request->input('student_id_number'));
        $key = 'test_kiosk_face_verify:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response()->json(['error' => 'Juda ko‘p urinish. Biroz kuting.'], 429);
        }

        RateLimiter::hit($key, 60);

        $commonLog = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'student_id_number' => $idNumber,
        ];

        $student = Student::query()
            ->where('student_id_number', $idNumber)
            ->first();

        if (!$student) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'not_found',
                'failure_reason' => 'Kiosk: talaba topilmadi',
            ]));

            return response()->json(['error' => 'Talaba topilmadi.'], 404);
        }

        $commonLog['student_id'] = $student->id;

        if (!FaceIdService::isGloballyEnabled() || !FaceIdService::isArcFaceEnabled()) {
            return response()->json(['error' => 'Face ID hozircha o‘chiq emas.'], 403);
        }

        if (!FaceIdService::isEnabledForStudent($student)) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'disabled',
                'failure_reason' => 'Kiosk: Face ID o‘chirilgan',
            ]));

            return response()->json(['error' => 'Bu talaba uchun Face ID o‘chirilgan.'], 403);
        }

        if (!$request->boolean('liveness_passed')) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'liveness_failed',
                'failure_reason' => 'Kiosk: liveness muvaffaqiyatsiz',
                'snapshot' => $request->input('snapshot'),
            ]));

            return response()->json(['error' => 'Jonlilik tekshiruvi o‘tmadi.'], 422);
        }

        $approvedPhoto = FaceIdService::getApprovedStudentPhoto($student);
        if (!$approvedPhoto) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'failed',
                'failure_reason' => 'Kiosk: approved rasm topilmadi',
                'snapshot' => $request->input('snapshot'),
            ]));

            return response()->json(['error' => 'Tasdiqlangan rasm topilmadi.'], 403);
        }

        $liveTmp = FaceIdService::saveTemporarySnapshot((string) $request->input('snapshot'));
        if (!$liveTmp) {
            return response()->json(['error' => 'Yuz rasmini saqlashda xato.'], 422);
        }

        try {
            $compareResult = FaceIdService::compareViaArcFace($liveTmp['url'], asset($approvedPhoto->photo_path));
        } finally {
            FaceIdService::deleteTemporarySnapshot($liveTmp['rel']);
        }

        if (!$compareResult) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'failed',
                'failure_reason' => 'Kiosk: ArcFace service javob bermadi',
                'snapshot' => $request->input('snapshot'),
            ]));

            return response()->json(['error' => 'Yuz tekshirish xizmati javob bermadi.'], 503);
        }

        $similarityPercent = (float) $compareResult['similarity_percent'];
        $distance = (float) $compareResult['distance'];
        $threshold = FaceIdService::getArcFaceThreshold();

        Log::info('[TestKiosk/FaceID] Taqqoslash', [
            'student' => $idNumber,
            'similarity' => round($similarityPercent, 2),
            'threshold' => $threshold,
            'distance' => round($distance, 4),
            'match' => $compareResult['match'] ?? false,
        ]);

        if ($similarityPercent < $threshold) {
            FaceIdService::logAttempt(array_merge($commonLog, [
                'result' => 'failed',
                'confidence' => round($similarityPercent / 100, 4),
                'distance' => round($distance, 4),
                'failure_reason' => "Kiosk: yuz mos kelmadi ({$similarityPercent}% < {$threshold}%)",
                'snapshot' => $request->input('snapshot'),
            ]));

            return response()->json([
                'error' => 'Yuz mos kelmadi. Iltimos, kameraga to‘g‘ri qarab qayta urinib ko‘ring.',
                'confidence' => round($similarityPercent, 1),
            ], 422);
        }

        FaceIdService::logAttempt(array_merge($commonLog, [
            'result' => 'success',
            'confidence' => round($similarityPercent / 100, 4),
            'distance' => round($distance, 4),
            'snapshot' => $request->input('snapshot'),
        ]));

        return response()->json([
            'success' => true,
            'redirect' => route('student.test-kiosk.student', $student->student_id_number),
            'student_name' => $student->full_name,
            'confidence' => round($similarityPercent, 1),
        ]);
    }

    public function student(string $studentIdNumber)
    {
        $student = $this->resolveStudent($studentIdNumber);
        $subjects = $this->loadAssignedSubjects($student);
        $lessonRows = $this->buildLessonRows($student, $subjects);

        return view('student.test-kiosk.student', [
            'student' => $student,
            'lessonRows' => $lessonRows,
            'now' => now('Asia/Tashkent'),
        ]);
    }

    public function show(string $studentIdNumber, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveKioskContext($studentIdNumber, $testSubject, $lesson);
        $language = $this->resolveLanguage(request()->query('lang'));

        $attempt = $attempt ?: $this->ensureAttempt($student, $testSubject, $lesson, $lessonTest);
        $questions = $this->resolveOrderedQuestions($lessonTest, $attempt);

        $attempt->load([
            'answers.question.options',
            'answers.selectedOption',
        ]);

        $elapsedSeconds = $attempt->started_at
            ? $attempt->started_at->diffInSeconds(now('Asia/Tashkent'))
            : 0;

        $remainingSeconds = max(
            0,
            ($lessonTest->duration_minutes * 60) - $elapsedSeconds
        );

        return view('student.test-kiosk.show', [
            'student' => $student,
            'testSubject' => $testSubject,
            'lesson' => $lesson,
            'lessonTest' => $lessonTest,
            'attempt' => $attempt,
            'questions' => $questions,
            'remainingSeconds' => $remainingSeconds,
            'language' => $language,
        ]);
    }

    public function submit(Request $request, string $studentIdNumber, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveKioskContext($studentIdNumber, $testSubject, $lesson);
        $language = $this->resolveLanguage($request->input('lang'));
        $attempt = $attempt ?: $this->ensureAttempt($student, $testSubject, $lesson, $lessonTest);

        if ($attempt->status === 'submitted') {
            return redirect()
                ->route('student.test-kiosk.tests.show', [$student->student_id_number, $testSubject, $lesson, 'lang' => $language])
                ->with('success', 'Test natijasi allaqachon saqlangan.');
        }

        $questions = $this->resolveOrderedQuestions($lessonTest, $attempt);
        $answersPayload = $request->input('answers', []);

        DB::transaction(function () use ($answersPayload, $questions, $attempt, $lessonTest, $language) {
            $score = 0;
            $totalPoints = 0;
            $answersCount = 0;

            foreach ($questions as $question) {
                $totalPoints += (int) $question->points;
                $selectedOptionId = null;
                $answerText = null;
                $isCorrect = false;

                if ($question->type === 'single_choice') {
                    $selectedOptionId = (int) data_get($answersPayload, $question->id . '.selected_option_id');
                    $selectedOption = $question->options->firstWhere('id', $selectedOptionId);
                    if ($selectedOption) {
                        $answersCount++;
                    }
                    $isCorrect = (bool) ($selectedOption?->is_correct);
                } else {
                    $answerText = trim((string) data_get($answersPayload, $question->id . '.answer_text', ''));
                    if ($answerText !== '') {
                        $answersCount++;
                    }
                    $correctAnswer = trim((string) ($question->correctAnswerFor($language) ?? ''));
                    $isCorrect = $question->case_sensitive
                        ? $answerText === $correctAnswer
                        : mb_strtolower($answerText) === mb_strtolower($correctAnswer);
                }

                $pointsEarned = $isCorrect ? (int) $question->points : 0;
                $score += $pointsEarned;

                TestSubjectLessonTestAnswer::query()->updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $question->id,
                    ],
                    [
                        'selected_option_id' => $selectedOptionId ?: null,
                        'answer_text' => $answerText !== '' ? $answerText : null,
                        'is_correct' => $isCorrect,
                        'points_earned' => $pointsEarned,
                        'answered_at' => now('Asia/Tashkent'),
                    ]
                );
            }

            $percent = $totalPoints > 0
                ? round(($score / $totalPoints) * 100, 2)
                : 0;

            $attempt->update([
                'status' => 'submitted',
                'submitted_at' => now('Asia/Tashkent'),
                'duration_seconds' => $attempt->started_at
                    ? $attempt->started_at->diffInSeconds(now('Asia/Tashkent'))
                    : null,
                'answers_count' => $answersCount,
                'total_points' => $totalPoints,
                'score' => $score,
                'percent' => $percent,
                'is_passed' => $percent >= (int) ($lessonTest->pass_percent ?? 0),
            ]);
        });

        return redirect()
            ->route('student.test-kiosk.tests.show', [$student->student_id_number, $testSubject, $lesson, 'lang' => $language])
            ->with('submission_result', [
                'message' => 'Test javoblaringiz muvaffaqqiyatli saqlandi.',
                'score' => $attempt->fresh()->score,
                'total_points' => $attempt->fresh()->total_points,
                'percent' => $attempt->fresh()->percent,
                'is_passed' => (bool) $attempt->fresh()->is_passed,
            ]);
    }

    private function resolveStudent(string $studentIdNumber): Student
    {
        $student = Student::query()
            ->where('student_id_number', trim($studentIdNumber))
            ->first();

        abort_unless($student, 404);

        return $student;
    }

    private function resolveKioskContext(string $studentIdNumber, TestSubject $testSubject, TestSubjectLesson $lesson): array
    {
        foreach ([
            'test_subject_lesson_test_attempts',
            'test_subject_lesson_test_answers',
        ] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Test moduli migratsiyasi hali ishga tushirilmagan.');
        }

        $student = $this->resolveStudent($studentIdNumber);
        $testSubject = $this->resolveAccessibleSubject($testSubject, $student);
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);
        abort_unless($testSubject->is_active && $lesson->is_active, 404);

        $lessonTest = $lesson->lessonTest()
            ->with([
                'questions' => fn ($query) => $query->where('is_active', true)->with('options'),
            ])
            ->first();
        abort_unless($lessonTest, 404);
        abort_unless($lessonTest->is_published, 403);

        $attempt = TestSubjectLessonTestAttempt::query()
            ->where('test_subject_lesson_test_id', $lessonTest->id)
            ->where('student_id', $student->id)
            ->first();

        $now = now('Asia/Tashkent');
        $canAccessClosedResult = $attempt && $attempt->status === 'submitted';
        $scheduledNow = $this->isLessonScheduledNow($lesson, $now);

        abort_unless($scheduledNow || $canAccessClosedResult, 403);
        abort_unless($lessonTest->is_open || $canAccessClosedResult, 403);

        if ($lessonTest->questions->isEmpty()) {
            abort(403, 'Bu test uchun savollar hali tayyor emas.');
        }

        return [$student, $lessonTest, $attempt];
    }

    private function loadAssignedSubjects(Student $student): Collection
    {
        foreach ([
            'test_subjects',
            'test_subject_groups',
            'test_subject_lessons',
            'test_subject_lesson_tests',
        ] as $table) {
            if (!Schema::hasTable($table)) {
                return collect();
            }
        }

        $today = now('Asia/Tashkent')->toDateString();

        return TestSubject::query()
            ->with([
                'groups',
                'lessons' => fn ($query) => $query
                    ->where('is_active', true)
                    ->whereDate('lesson_date', $today)
                    ->orderBy('lesson_date')
                    ->orderBy('starts_at')
                    ->orderBy('topic_order'),
                'lessons.lessonTest' => fn ($query) => $query->withCount([
                    'questions' => fn ($q) => $q->where('is_active', true),
                ]),
            ])
            ->where('is_active', true)
            ->where(function ($query) use ($today) {
                $query->whereNull('starts_on')
                    ->orWhereDate('starts_on', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->whereHas('groups', function ($query) use ($student) {
                $query->where(function ($sub) use ($student) {
                    $sub->where('group_hemis_id', $student->group_id)
                        ->orWhere('group_id', $student->group_id);
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function buildLessonRows(Student $student, Collection $subjects): Collection
    {
        if ($subjects->isEmpty()) {
            return collect();
        }

        $attemptsByTestId = $this->attemptsByTestId($student, $subjects);
        $now = now('Asia/Tashkent');

        return $subjects->flatMap(function (TestSubject $subject) use ($student, $attemptsByTestId, $now) {
            return $subject->lessons->map(function (TestSubjectLesson $lesson) use ($student, $subject, $attemptsByTestId, $now) {
                $lessonTest = $lesson->lessonTest;
                $attempt = $lessonTest ? $attemptsByTestId->get($lessonTest->id) : null;
                $questionCount = (int) ($lessonTest?->questions_count ?? 0);
                $scheduledNow = $this->isLessonScheduledNow($lesson, $now);
                $isPast = $this->lessonEndsAt($lesson)?->lt($now);
                $isFuture = $this->lessonStartsAt($lesson)?->gt($now);
                $isSubmitted = $attempt && $attempt->status === 'submitted';
                $canStart = $lessonTest
                    && $lessonTest->is_open
                    && $lessonTest->is_published
                    && $questionCount > 0
                    && !$isSubmitted
                    && $scheduledNow;

                return [
                    'subject' => $subject,
                    'lesson' => $lesson,
                    'attempt' => $attempt,
                    'question_count' => $questionCount,
                    'scheduled_now' => $scheduledNow,
                    'is_future' => (bool) $isFuture,
                    'is_past' => (bool) $isPast,
                    'is_submitted' => $isSubmitted,
                    'can_start' => $canStart,
                    'status_text' => $this->resolveStatusText($lessonTest, $questionCount, $scheduledNow, (bool) $isFuture, (bool) $isPast, $isSubmitted),
                    'status_variant' => $this->resolveStatusVariant($lessonTest, $scheduledNow, (bool) $isFuture, (bool) $isPast, $isSubmitted, $canStart),
                    'test_route' => $lessonTest
                        ? route('student.test-kiosk.tests.show', [$student->student_id_number, $subject, $lesson])
                        : null,
                ];
            });
        })->sortBy(function ($row) {
            return sprintf(
                '%s %s %05d',
                optional($row['lesson']->lesson_date)->format('Y-m-d') ?: '9999-12-31',
                $row['lesson']->starts_at ?: '23:59:59',
                (int) ($row['lesson']->topic_order ?? 0)
            );
        })->values();
    }

    private function resolveStatusText(?TestSubjectLessonTest $lessonTest, int $questionCount, bool $scheduledNow, bool $isFuture, bool $isPast, bool $isSubmitted): string
    {
        if (!$lessonTest) {
            return 'Bu mavzu uchun test hali yaratilmagan.';
        }

        if ($isSubmitted) {
            return 'Siz bu testni allaqachon yakunlagansiz.';
        }

        if (!$lessonTest->is_published) {
            return 'Test hali nashr qilinmagan.';
        }

        if ($questionCount <= 0) {
            return 'Savollar hali kiritilmagan.';
        }

        if ($isFuture) {
            return 'Dars vaqti kelmagan. Belgilangan soatda test sahifasi ochiladi.';
        }

        if ($scheduledNow && $lessonTest->is_open) {
            return 'Test ochiq. Testni boshlashingiz mumkin.';
        }

        if ($scheduledNow && !$lessonTest->is_open) {
            return 'Dars vaqti keldi, lekin o‘qituvchi testni hali ochmadi.';
        }

        if ($isPast) {
            return 'Bu dars vaqti o‘tib ketgan.';
        }

        return 'Test hali ochilmagan.';
    }

    private function resolveStatusVariant(?TestSubjectLessonTest $lessonTest, bool $scheduledNow, bool $isFuture, bool $isPast, bool $isSubmitted, bool $canStart): string
    {
        if ($canStart) {
            return 'green';
        }
        if ($isSubmitted) {
            return 'blue';
        }
        if (!$lessonTest || !$lessonTest->is_published) {
            return 'gray';
        }
        if ($scheduledNow) {
            return 'orange';
        }
        if ($isFuture) {
            return 'blue';
        }
        if ($isPast) {
            return 'gray';
        }

        return 'gray';
    }

    private function attemptsByTestId(Student $student, Collection $subjects): Collection
    {
        $testIds = $subjects->pluck('lessons')
            ->flatten()
            ->pluck('lessonTest.id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($testIds)) {
            return collect();
        }

        return TestSubjectLessonTestAttempt::query()
            ->where('student_id', $student->id)
            ->whereIn('test_subject_lesson_test_id', $testIds)
            ->get()
            ->keyBy('test_subject_lesson_test_id');
    }

    private function resolveAccessibleSubject(TestSubject $testSubject, Student $student): TestSubject
    {
        $testSubject->loadMissing([
            'groups',
            'lessons' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('lesson_date')
                ->orderBy('topic_order'),
            'lessons.lessonTest' => fn ($query) => $query->withCount([
                'questions' => fn ($q) => $q->where('is_active', true),
            ]),
        ]);

        abort_unless($testSubject->is_active, 404);

        $today = now('Asia/Tashkent')->toDateString();
        if ($testSubject->starts_on) {
            abort_unless($testSubject->starts_on->format('Y-m-d') <= $today, 403);
        }
        if ($testSubject->ends_on) {
            abort_unless($testSubject->ends_on->format('Y-m-d') >= $today, 403);
        }

        $groupLinked = $testSubject->groups->first(function ($group) use ($student) {
            return (string) $group->group_hemis_id === (string) $student->group_id
                || (string) $group->group_id === (string) $student->group_id;
        });

        abort_unless($groupLinked, 403);

        return $testSubject;
    }

    private function ensureAttempt(Student $student, TestSubject $testSubject, TestSubjectLesson $lesson, TestSubjectLessonTest $lessonTest): TestSubjectLessonTestAttempt
    {
        $attempt = TestSubjectLessonTestAttempt::query()->firstOrCreate(
            [
                'test_subject_lesson_test_id' => $lessonTest->id,
                'student_id' => $student->id,
            ],
            [
                'test_subject_id' => $testSubject->id,
                'test_subject_lesson_id' => $lesson->id,
                'student_hemis_id' => $student->hemis_id,
                'status' => 'in_progress',
                'started_at' => now('Asia/Tashkent'),
            ]
        );

        if (!$attempt->started_at) {
            $attempt->update(['started_at' => now('Asia/Tashkent')]);
        }

        return $attempt;
    }

    private function resolveOrderedQuestions(TestSubjectLessonTest $lessonTest, TestSubjectLessonTestAttempt $attempt): Collection
    {
        $questions = $lessonTest->questions->where('is_active', true)->values();
        $questionOrder = collect($attempt->question_order ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter();

        $availableIds = $questions->pluck('id')->map(fn ($id) => (int) $id)->values();
        $needsRefresh = $questionOrder->isEmpty()
            || $questionOrder->diff($availableIds)->isNotEmpty()
            || $availableIds->diff($questionOrder)->isNotEmpty();

        if ($needsRefresh) {
            $ordered = $lessonTest->shuffle_questions
                ? $questions->shuffle()->values()
                : $questions->sortBy('sort_order')->values();

            $attempt->update([
                'question_order' => $ordered->pluck('id')->map(fn ($id) => (int) $id)->all(),
            ]);

            return $ordered;
        }

        return $questionOrder
            ->map(fn ($id) => $questions->firstWhere('id', $id))
            ->filter()
            ->values();
    }

    private function resolveLanguage(?string $language): string
    {
        return in_array($language, ['uz', 'ru', 'en'], true) ? $language : 'uz';
    }

    private function lessonStartsAt(TestSubjectLesson $lesson): ?Carbon
    {
        $date = optional($lesson->lesson_date)->format('Y-m-d');
        if (!$date) {
            return null;
        }

        return Carbon::parse($date . ' ' . ($lesson->starts_at ?: '00:00:00'), 'Asia/Tashkent');
    }

    private function lessonEndsAt(TestSubjectLesson $lesson): ?Carbon
    {
        $date = optional($lesson->lesson_date)->format('Y-m-d');
        if (!$date) {
            return null;
        }

        return Carbon::parse($date . ' ' . ($lesson->ends_at ?: '23:59:59'), 'Asia/Tashkent');
    }

    private function isLessonScheduledNow(TestSubjectLesson $lesson, Carbon $now): bool
    {
        $lessonDate = optional($lesson->lesson_date)->format('Y-m-d');
        if ($lessonDate !== $now->toDateString()) {
            return false;
        }

        $startsAt = $this->lessonStartsAt($lesson);
        $endsAt = $this->lessonEndsAt($lesson);

        if (!$startsAt || !$endsAt) {
            return true;
        }

        return $now->between($startsAt, $endsAt, true);
    }
}

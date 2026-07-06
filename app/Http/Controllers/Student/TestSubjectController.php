<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TestSubject;
use App\Models\TestSubjectLesson;
use App\Models\TestSubjectLessonTest;
use App\Models\TestSubjectLessonTestAnswer;
use App\Models\TestSubjectLessonTestAttempt;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestSubjectController extends Controller
{
    public function index()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        return view('student.test-subjects.index', [
            'student' => $student,
            'subjects' => $this->loadAssignedSubjects($student),
        ]);
    }

    public function subject(TestSubject $testSubject)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $subject = $this->resolveAccessibleSubject($testSubject, $student);
        $now = now('Asia/Tashkent');
        $attemptsByTestId = $this->attemptsByTestId($student, $subject);

        $lessons = $subject->lessons->map(function (TestSubjectLesson $lesson) use ($attemptsByTestId, $now, $subject) {
            $lessonTest = $lesson->lessonTest;
            $attempt = $lessonTest ? $attemptsByTestId->get($lessonTest->id) : null;
            $questionCount = (int) ($lessonTest?->questions_count ?? 0);
            $isScheduledNow = $this->isLessonScheduledNow($lesson, $now);
            $isPast = $this->lessonEndsAt($lesson)?->lt($now)
                || (optional($lesson->lesson_date)->format('Y-m-d') < $now->toDateString());
            $isFuture = $this->lessonStartsAt($lesson)?->gt($now)
                || (optional($lesson->lesson_date)->format('Y-m-d') > $now->toDateString());
            $isSubmitted = $attempt && $attempt->status === 'submitted';
            $canContinueAttempt = $lessonTest
                && $attempt
                && $attempt->status === 'in_progress'
                && $this->attemptHasRemainingTime($lessonTest, $attempt, $now);
            $isEffectivelyOpen = $lessonTest
                && $lessonTest->is_open
                && !$isPast;
            $canStart = $lessonTest
                && ($isEffectivelyOpen || $canContinueAttempt)
                && $lessonTest->is_published
                && $questionCount > 0
                && !$isSubmitted
                && ($isScheduledNow || $canContinueAttempt);

            return [
                'id' => $lesson->id,
                'topic_order' => (int) ($lesson->topic_order ?? 0),
                'topic_title' => $lesson->topic_title ?: (($lesson->topic_order ?? 1) . '-mavzu'),
                'lesson_date' => optional($lesson->lesson_date)->format('d.m.Y'),
                'lesson_time' => $this->lessonTimeText($lesson),
                'question_count' => $questionCount,
                'is_open' => (bool) $isEffectivelyOpen,
                'is_published' => (bool) ($lessonTest?->is_published),
                'is_submitted' => $isSubmitted,
                'is_scheduled_now' => $isScheduledNow,
                'is_past' => $isPast,
                'is_future' => $isFuture,
                'attempt_percent' => $attempt?->percent,
                'attempt_is_passed' => (bool) ($attempt?->is_passed),
                'attempt_status' => $attempt?->status,
                'can_start' => $canStart,
                'test_route' => $lessonTest
                    ? route('student.test-subjects.tests.show', [$subject, $lesson])
                    : null,
            ];
        })->values();

        return view('student.test-subjects.subject', [
            'student' => $student,
            'testSubject' => $subject,
            'lessons' => $lessons,
            'now' => $now,
        ]);
    }

    public function show(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveStudentContext($testSubject, $lesson);
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

        $remainingSeconds = $this->resolveRemainingSeconds($lesson, $lessonTest, $attempt, $elapsedSeconds);

        return view('student.test-subjects.show', [
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

    public function submit(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveStudentContext($testSubject, $lesson);
        $language = $this->resolveLanguage($request->input('lang'));
        $attempt = $attempt ?: $this->ensureAttempt($student, $testSubject, $lesson, $lessonTest);

        if ($attempt->status === 'submitted') {
            return redirect()
                ->route('student.test-subjects.tests.show', [$testSubject, $lesson, 'lang' => $language])
                ->with('success', 'Test natijasi allaqachon saqlangan.');
        }

        $questions = $this->resolveOrderedQuestions($lessonTest, $attempt);
        $answersPayload = $request->input('answers', []);

        DB::transaction(function () use ($answersPayload, $questions, $attempt, $lessonTest) {
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
            ->route('student.test-subjects.tests.show', [$testSubject, $lesson, 'lang' => $language])
            ->with('success', 'Test javoblaringiz saqlandi.');
    }

    private function resolveStudentContext(TestSubject $testSubject, TestSubjectLesson $lesson): array
    {
        foreach ([
            'test_subject_lesson_test_attempts',
            'test_subject_lesson_test_answers',
        ] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Test moduli migratsiyasi hali ishga tushirilmagan.');
        }

        /** @var Student $student */
        $student = Auth::guard('student')->user();
        $testSubject = $this->resolveAccessibleSubject($testSubject, $student);
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);
        abort_unless($testSubject->is_active && $lesson->is_active, 404);

        $now = now('Asia/Tashkent');
        $scheduledNow = $this->isLessonScheduledNow($lesson, $now);

        $lessonTest = $lesson->lessonTest()
            ->with([
                'questions' => fn($query) => $query->where('is_active', true)->with('options'),
            ])
            ->first();
        abort_unless($lessonTest, 404);
        abort_unless($lessonTest->is_published, 403);

        $attempt = TestSubjectLessonTestAttempt::query()
            ->where('test_subject_lesson_test_id', $lessonTest->id)
            ->where('student_id', $student->id)
            ->first();

        $canAccessClosedResult = $attempt && $attempt->status === 'submitted';
        $canContinueAttempt = $attempt
            && $attempt->status === 'in_progress'
            && $this->attemptHasRemainingTime($lessonTest, $attempt, $now);
        $isPast = $this->lessonEndsAt($lesson)?->lt($now)
            || (optional($lesson->lesson_date)->format('Y-m-d') < $now->toDateString());
        $isEffectivelyOpen = $lessonTest->is_open && !$isPast;
        abort_unless($scheduledNow || $canAccessClosedResult || $canContinueAttempt, 403);
        abort_unless($isEffectivelyOpen || $canAccessClosedResult || $canContinueAttempt, 403);

        if ($lessonTest->questions->isEmpty()) {
            abort(403, 'Bu test uchun savollar hali tayyor emas.');
        }

        return [$student, $lessonTest, $attempt];
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

    private function resolveRemainingSeconds(
        TestSubjectLesson $lesson,
        TestSubjectLessonTest $lessonTest,
        TestSubjectLessonTestAttempt $attempt,
        int $elapsedSeconds
    ): int {
        return max(0, ($lessonTest->duration_minutes * 60) - $elapsedSeconds);
    }

    private function attemptHasRemainingTime(
        TestSubjectLessonTest $lessonTest,
        TestSubjectLessonTestAttempt $attempt,
        ?Carbon $now = null
    ): bool {
        if (!$attempt->started_at) {
            return false;
        }

        $now = $now ?: now('Asia/Tashkent');
        $expiresAt = $attempt->started_at->copy()->addMinutes((int) $lessonTest->duration_minutes);

        return $expiresAt->gt($now);
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

        $today = Carbon::now('Asia/Tashkent')->toDateString();

        $subjects = TestSubject::query()
            ->with([
                'groups',
                'lessons' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('lesson_date')
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

        if ($subjects->isEmpty()) {
            return collect();
        }

        $attemptsByTestId = $this->attemptsByTestId($student, $subjects);
        $now = now('Asia/Tashkent');

        return $subjects->map(function (TestSubject $subject) use ($attemptsByTestId, $now) {
            $lessons = $subject->lessons->map(function (TestSubjectLesson $lesson) use ($attemptsByTestId, $now, $subject) {
                $lessonTest = $lesson->lessonTest;
                $attempt = $lessonTest ? $attemptsByTestId->get($lessonTest->id) : null;
                $questionCount = (int) ($lessonTest?->questions_count ?? 0);
                $isSubmitted = $attempt && $attempt->status === 'submitted';
                $isScheduledNow = $this->isLessonScheduledNow($lesson, $now);

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->topic_title ?: (($lesson->topic_order ?? 1) . '-mavzu'),
                    'date' => optional($lesson->lesson_date)->format('d.m.Y'),
                    'time' => $this->lessonTimeText($lesson),
                    'question_count' => $questionCount,
                    'is_submitted' => $isSubmitted,
                    'can_start' => $lessonTest
                        && $lessonTest->is_open
                        && $lessonTest->is_published
                        && $questionCount > 0
                        && !$isSubmitted
                        && $isScheduledNow,
                    'route' => $lessonTest
                        ? route('student.test-subjects.tests.show', [$subject, $lesson])
                        : null,
                ];
            })->values();

            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'teacher_name' => $subject->teacher_name,
                'group_name' => $subject->groups->pluck('group_name')->filter()->implode(', '),
                'starts_on' => optional($subject->starts_on)->format('d.m.Y'),
                'ends_on' => optional($subject->ends_on)->format('d.m.Y'),
                'subject_route' => route('student.test-subjects.subject', $subject),
                'open_count' => collect($lessons)->where('can_start', true)->count(),
                'lessons_count' => $lessons->count(),
                'lessons' => $lessons,
            ];
        })->values();
    }

    private function attemptsByTestId(Student $student, $subjects): Collection
    {
        $subjectsCollection = $subjects instanceof Collection ? $subjects : collect([$subjects]);

        $testIds = $subjectsCollection->pluck('lessons')
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

    private function lessonTimeText(TestSubjectLesson $lesson): ?string
    {
        if (!$lesson->starts_at && !$lesson->ends_at) {
            return null;
        }

        return trim(
            ($lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--')
            . ' - ' .
            ($lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--')
        );
    }
}

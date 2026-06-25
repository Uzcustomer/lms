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
    public function show(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveStudentContext($testSubject, $lesson);

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

        return view('student.test-subjects.show', [
            'student' => $student,
            'testSubject' => $testSubject,
            'lesson' => $lesson,
            'lessonTest' => $lessonTest,
            'attempt' => $attempt,
            'questions' => $questions,
            'remainingSeconds' => $remainingSeconds,
        ]);
    }

    public function submit(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$student, $lessonTest, $attempt] = $this->resolveStudentContext($testSubject, $lesson);
        $attempt = $attempt ?: $this->ensureAttempt($student, $testSubject, $lesson, $lessonTest);

        if ($attempt->status === 'submitted') {
            return redirect()
                ->route('student.test-subjects.tests.show', [$testSubject, $lesson])
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
                    $correctAnswer = trim((string) ($question->correct_answer_text ?? ''));
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
            ->route('student.test-subjects.tests.show', [$testSubject, $lesson])
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
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);
        abort_unless($testSubject->is_active && $lesson->is_active, 404);

        $groupLinked = $testSubject->groups()
            ->where(function ($query) use ($student) {
                $query->where('group_hemis_id', $student->group_id)
                    ->orWhere('group_id', $student->group_id);
            })
            ->exists();
        abort_unless($groupLinked, 403);

        $today = now('Asia/Tashkent')->toDateString();
        abort_unless(optional($lesson->lesson_date)->format('Y-m-d') === $today, 403);

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
        abort_unless($lessonTest->is_open || $canAccessClosedResult, 403);

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
}

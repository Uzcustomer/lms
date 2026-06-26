<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TestSubject;
use App\Models\TestSubjectLesson;
use App\Models\TestSubjectLessonTest;
use App\Models\TestSubjectLessonTestAttempt;
use App\Models\TestSubjectLessonTestQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestSubjectLessonTestController extends Controller
{
    public function edit(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson);
        $test = $test ?: $this->ensureDraftTest($teacher->id, $testSubject, $lesson);

        $test->load(['questions.options']);

        return view('teacher.test-subjects.test-builder', [
            'teacher' => $teacher,
            'testSubject' => $testSubject->loadMissing(['groups']),
            'lesson' => $lesson,
            'test' => $test,
        ]);
    }

    public function results(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        foreach ([
            'test_subject_lesson_tests',
            'test_subject_lesson_test_questions',
            'test_subject_lesson_test_options',
            'test_subject_lesson_test_attempts',
            'test_subject_lesson_test_answers',
        ] as $table) {
            abort_unless(Schema::hasTable($table), 503, 'Test natijalari moduli hali to‘liq ishga tushirilmagan.');
        }

        [, $test] = $this->resolveContext($testSubject, $lesson, true);

        $testSubject->loadMissing('groups');
        $test->load([
            'questions.options',
            'attempts.student',
            'attempts.answers.question.options',
            'attempts.answers.selectedOption',
        ]);

        $groupKeys = $testSubject->groups
            ->pluck('group_hemis_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($groupKeys->isEmpty()) {
            $groupKeys = $testSubject->groups
                ->pluck('group_id')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();
        }

        $students = Student::query()
            ->select(['id', 'hemis_id', 'full_name', 'group_id', 'group_name', 'student_id_number'])
            ->when(
                $groupKeys->isNotEmpty(),
                fn ($query) => $query->whereIn('group_id', $groupKeys->all()),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->orderBy('full_name')
            ->get();

        $attemptsByStudentId = $test->attempts
            ->whereIn('student_id', $students->pluck('id'))
            ->keyBy('student_id');

        $submittedAttempts = $attemptsByStudentId
            ->filter(fn ($attempt) => $attempt->status === 'submitted')
            ->values();

        $studentRows = $students->map(function (Student $student, int $index) use ($attemptsByStudentId, $test) {
            /** @var TestSubjectLessonTestAttempt|null $attempt */
            $attempt = $attemptsByStudentId->get($student->id);
            $answersByQuestion = $attempt?->answers?->keyBy('question_id') ?? collect();

            $questionDetails = $test->questions->map(function ($question) use ($answersByQuestion) {
                $answer = $answersByQuestion->get($question->id);
                $selectedOption = $answer?->selectedOption;
                $correctOption = $question->options->firstWhere('is_correct', true);

                return [
                    'question_no' => $question->sort_order ?: $question->id,
                    'prompt' => $question->prompt,
                    'type' => $question->type,
                    'points' => (int) $question->points,
                    'points_earned' => (int) ($answer->points_earned ?? 0),
                    'is_answered' => (bool) $answer,
                    'is_correct' => (bool) ($answer->is_correct ?? false),
                    'selected_option' => $selectedOption?->option_text,
                    'correct_option' => $correctOption?->option_text,
                    'answer_text' => $answer?->answer_text,
                    'correct_answer_text' => $question->correct_answer_text,
                ];
            })->values();

            return [
                'row_no' => $index + 1,
                'student' => $student,
                'attempt' => $attempt,
                'question_details' => $questionDetails,
            ];
        })->values();

        $questionSummaries = $test->questions->map(function ($question) use ($submittedAttempts) {
            $answers = $submittedAttempts
                ->flatMap(fn ($attempt) => $attempt->answers)
                ->where('question_id', $question->id)
                ->values();

            $answeredCount = $answers->count();
            $correctCount = $answers->where('is_correct', true)->count();
            $incorrectCount = $answeredCount - $correctCount;
            $unansweredCount = max(0, $submittedAttempts->count() - $answeredCount);

            return [
                'question_no' => $question->sort_order ?: $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type,
                'correct_count' => $correctCount,
                'incorrect_count' => $incorrectCount,
                'unanswered_count' => $unansweredCount,
            ];
        })->values();

        $summary = [
            'total_students' => $students->count(),
            'submitted_count' => $submittedAttempts->count(),
            'not_submitted_count' => max(0, $students->count() - $submittedAttempts->count()),
            'passed_count' => $submittedAttempts->where('is_passed', true)->count(),
            'failed_count' => $submittedAttempts->where('is_passed', false)->count(),
            'average_percent' => round((float) $submittedAttempts->avg('percent'), 2),
        ];

        return view('teacher.test-subjects.results', [
            'testSubject' => $testSubject,
            'lesson' => $lesson,
            'test' => $test,
            'summary' => $summary,
            'studentRows' => $studentRows,
            'questionSummaries' => $questionSummaries,
        ]);
    }

    public function upsert(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson);
        $test = $test ?: $this->ensureDraftTest($teacher->id, $testSubject, $lesson);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'pass_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
            'show_result_after_submit' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
            'is_open' => ['nullable', 'boolean'],
        ]);

        $wantsPublished = $request->boolean('is_published') || $request->boolean('is_open');
        $existingQuestionCount = $test?->questions()->count() ?? 0;
        if ($wantsPublished && $existingQuestionCount === 0) {
            throw ValidationException::withMessages([
                'is_published' => 'Savollar kiritilmaguncha testni nashr qilish yoki ochish mumkin emas.',
            ]);
        }

        $payload = [
            'test_subject_id' => $testSubject->id,
            'test_subject_lesson_id' => $lesson->id,
            'teacher_id' => $teacher->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'pass_percent' => $validated['pass_percent'] ?? null,
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'show_result_after_submit' => $request->boolean('show_result_after_submit'),
            'is_published' => $wantsPublished ? true : $request->boolean('is_published'),
            'is_open' => $request->boolean('is_open'),
            'updated_by' => $teacher->id,
        ];

        if (!$test) {
            $payload['created_by'] = $teacher->id;
        }

        $test = TestSubjectLessonTest::query()->updateOrCreate(
            ['test_subject_lesson_id' => $lesson->id],
            $payload
        );

        $test->update([
            'opened_at' => $test->is_open ? now() : null,
            'closed_at' => $test->is_open ? null : ($test->closed_at ?? now()),
        ]);

        return redirect()
            ->route('teacher.test-subjects.tests.edit', [$testSubject, $lesson])
            ->with('success', 'Test sozlamalari saqlandi.');
    }

    public function storeQuestion(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson);
        $test = $test ?: $this->ensureDraftTest($teacher->id, $testSubject, $lesson);
        $validated = $this->validateQuestion($request);
        $imagePath = $this->storeQuestionImage($request);

        DB::transaction(function () use ($validated, $request, $test, $imagePath) {
            $question = $test->questions()->create([
                'type' => $validated['type'],
                'prompt' => $validated['prompt'],
                'prompt_translations' => $this->buildTranslations(
                    $validated['prompt'],
                    $validated['prompt_ru'] ?? null,
                    $validated['prompt_en'] ?? null,
                ),
                'image_path' => $imagePath,
                'helper_text' => $validated['helper_text'] ?? null,
                'helper_text_translations' => $this->buildTranslations(
                    $validated['helper_text'] ?? null,
                    $validated['helper_text_ru'] ?? null,
                    $validated['helper_text_en'] ?? null,
                ),
                'correct_answer_text' => $validated['type'] === 'fill_in_blank'
                    ? ($validated['correct_answer_text'] ?? null)
                    : null,
                'correct_answer_translations' => $validated['type'] === 'fill_in_blank'
                    ? $this->buildTranslations(
                        $validated['correct_answer_text'] ?? null,
                        $validated['correct_answer_text_ru'] ?? null,
                        $validated['correct_answer_text_en'] ?? null,
                    )
                    : null,
                'case_sensitive' => $validated['type'] === 'fill_in_blank' ? $request->boolean('case_sensitive') : false,
                'points' => $validated['points'],
                'sort_order' => ((int) $test->questions()->max('sort_order')) + 1,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($validated['type'] === 'single_choice') {
                $this->syncSingleChoiceOptions(
                    $question,
                    $validated['options'] ?? [],
                    (int) $validated['correct_option_number']
                );
            }
        });

        return redirect()
            ->route('teacher.test-subjects.tests.edit', [$testSubject, $lesson])
            ->with('success', 'Savol qo‘shildi.');
    }

    public function updateQuestion(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson, TestSubjectLessonTestQuestion $question)
    {
        [, $test] = $this->resolveContext($testSubject, $lesson, true);
        abort_unless((int) $question->lesson_test_id === (int) $test->id, 404);

        $validated = $this->validateQuestion($request);
        $newImagePath = $this->storeQuestionImage($request);

        DB::transaction(function () use ($validated, $request, $question, $newImagePath) {
            $removeCurrentImage = $request->boolean('remove_question_image');
            $oldImagePath = $question->image_path;

            $question->update([
                'type' => $validated['type'],
                'prompt' => $validated['prompt'],
                'prompt_translations' => $this->buildTranslations(
                    $validated['prompt'],
                    $validated['prompt_ru'] ?? null,
                    $validated['prompt_en'] ?? null,
                ),
                'image_path' => $newImagePath
                    ?: ($removeCurrentImage ? null : $question->image_path),
                'helper_text' => $validated['helper_text'] ?? null,
                'helper_text_translations' => $this->buildTranslations(
                    $validated['helper_text'] ?? null,
                    $validated['helper_text_ru'] ?? null,
                    $validated['helper_text_en'] ?? null,
                ),
                'correct_answer_text' => $validated['type'] === 'fill_in_blank'
                    ? ($validated['correct_answer_text'] ?? null)
                    : null,
                'correct_answer_translations' => $validated['type'] === 'fill_in_blank'
                    ? $this->buildTranslations(
                        $validated['correct_answer_text'] ?? null,
                        $validated['correct_answer_text_ru'] ?? null,
                        $validated['correct_answer_text_en'] ?? null,
                    )
                    : null,
                'case_sensitive' => $validated['type'] === 'fill_in_blank' ? $request->boolean('case_sensitive') : false,
                'points' => $validated['points'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($validated['type'] === 'single_choice') {
                $this->syncSingleChoiceOptions(
                    $question,
                    $validated['options'] ?? [],
                    (int) $validated['correct_option_number']
                );
            } else {
                $question->options()->delete();
            }

            if (($newImagePath || $removeCurrentImage) && $oldImagePath) {
                Storage::disk('public')->delete($oldImagePath);
            }
        });

        return redirect()
            ->route('teacher.test-subjects.tests.edit', [$testSubject, $lesson])
            ->with('success', 'Savol yangilandi.');
    }

    public function destroyQuestion(TestSubject $testSubject, TestSubjectLesson $lesson, TestSubjectLessonTestQuestion $question)
    {
        [, $test] = $this->resolveContext($testSubject, $lesson, true);
        abort_unless((int) $question->lesson_test_id === (int) $test->id, 404);

        if ($question->image_path) {
            Storage::disk('public')->delete($question->image_path);
        }

        $question->delete();
        $this->syncQuestionOrdering($test);

        return redirect()
            ->route('teacher.test-subjects.tests.edit', [$testSubject, $lesson])
            ->with('success', 'Savol o‘chirildi.');
    }

    private function resolveContext(TestSubject $testSubject, TestSubjectLesson $lesson, bool $mustHaveTest = false): array
    {
        $teacher = auth()->guard('teacher')->user();
        abort_unless((int) $testSubject->teacher_id === (int) $teacher->id, 403);
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);

        $test = $lesson->lessonTest;

        if ($mustHaveTest) {
            abort_unless($test, 404);
        }

        return [$teacher, $test];
    }

    private function validateQuestion(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['single_choice', 'fill_in_blank'])],
            'prompt' => ['required', 'string'],
            'prompt_ru' => ['nullable', 'string'],
            'prompt_en' => ['nullable', 'string'],
            'question_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:4096'],
            'remove_question_image' => ['nullable', 'boolean'],
            'helper_text' => ['nullable', 'string'],
            'helper_text_ru' => ['nullable', 'string'],
            'helper_text_en' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'correct_answer_text' => ['nullable', 'string', 'max:255'],
            'correct_answer_text_ru' => ['nullable', 'string', 'max:255'],
            'correct_answer_text_en' => ['nullable', 'string', 'max:255'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:255'],
            'options.*.text_ru' => ['nullable', 'string', 'max:255'],
            'options.*.text_en' => ['nullable', 'string', 'max:255'],
            'correct_option_number' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['type'] === 'fill_in_blank' && empty($validated['correct_answer_text'])) {
            throw ValidationException::withMessages([
                'correct_answer_text' => 'Fill in blank savol uchun to‘g‘ri javob majburiy.',
            ]);
        }

        if ($validated['type'] === 'single_choice') {
            $options = $this->normalizeOptions($validated['options'] ?? []);
            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'options' => 'Multiple choice uchun kamida 2 ta variant kiriting.',
                ]);
            }
            $correctNo = (int) ($validated['correct_option_number'] ?? 0);
            if ($correctNo < 1 || $correctNo > count($options)) {
                throw ValidationException::withMessages([
                    'correct_option_number' => 'To‘g‘ri variant raqami noto‘g‘ri.',
                ]);
            }
        }

        return $validated;
    }

    private function storeQuestionImage(Request $request): ?string
    {
        if (!$request->hasFile('question_image')) {
            return null;
        }

        return $request->file('question_image')->store('test-subject-questions', 'public');
    }

    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->map(function ($option) {
                $text = trim((string) data_get($option, 'text'));
                if ($text === '') {
                    return null;
                }

                return [
                    'text' => $text,
                    'text_ru' => trim((string) data_get($option, 'text_ru')),
                    'text_en' => trim((string) data_get($option, 'text_en')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function syncSingleChoiceOptions(TestSubjectLessonTestQuestion $question, array $optionsInput, int $correctOptionNumber): void
    {
        $options = $this->normalizeOptions($optionsInput);
        $question->options()->delete();

        foreach ($options as $index => $optionText) {
            $question->options()->create([
                'option_text' => $optionText['text'],
                'option_text_translations' => $this->buildTranslations(
                    $optionText['text'],
                    $optionText['text_ru'] ?? null,
                    $optionText['text_en'] ?? null,
                ),
                'sort_order' => $index + 1,
                'is_correct' => ($index + 1) === $correctOptionNumber,
            ]);
        }
    }

    private function buildTranslations(?string $uz, ?string $ru = null, ?string $en = null): ?array
    {
        $translations = [
            'uz' => trim((string) ($uz ?? '')),
            'ru' => trim((string) ($ru ?? '')),
            'en' => trim((string) ($en ?? '')),
        ];

        $filtered = array_filter($translations, fn ($value) => $value !== '');

        return !empty($filtered) ? $filtered : null;
    }

    private function syncQuestionOrdering(TestSubjectLessonTest $test): void
    {
        $test->load('questions');

        $test->questions
            ->sortBy([
                fn (TestSubjectLessonTestQuestion $question) => $question->sort_order ?? PHP_INT_MAX,
                fn (TestSubjectLessonTestQuestion $question) => $question->id,
            ])
            ->values()
            ->each(function (TestSubjectLessonTestQuestion $question, int $index) {
                if ((int) $question->sort_order !== $index + 1) {
                    $question->update(['sort_order' => $index + 1]);
                }
            });
    }

    private function ensureDraftTest(int $teacherId, TestSubject $testSubject, TestSubjectLesson $lesson): TestSubjectLessonTest
    {
        return TestSubjectLessonTest::query()->firstOrCreate(
            ['test_subject_lesson_id' => $lesson->id],
            [
                'test_subject_id' => $testSubject->id,
                'teacher_id' => $teacherId,
                'title' => ($lesson->topic_title ?: ('Mavzu ' . $lesson->topic_order)) . ' testi',
                'description' => null,
                'duration_minutes' => 20,
                'pass_percent' => 60,
                'shuffle_questions' => false,
                'show_result_after_submit' => true,
                'is_published' => false,
                'is_open' => false,
                'created_by' => $teacherId,
                'updated_by' => $teacherId,
            ]
        );
    }
}

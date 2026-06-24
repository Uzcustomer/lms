<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TestSubject;
use App\Models\TestSubjectLesson;
use App\Models\TestSubjectLessonTest;
use App\Models\TestSubjectLessonTestQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestSubjectLessonTestController extends Controller
{
    public function edit(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson);

        $test?->load(['questions.options']);

        return view('teacher.test-subjects.test-builder', [
            'teacher' => $teacher,
            'testSubject' => $testSubject->loadMissing(['groups']),
            'lesson' => $lesson,
            'test' => $test,
        ]);
    }

    public function upsert(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson);

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
        [$teacher, $test] = $this->resolveContext($testSubject, $lesson, true);
        $validated = $this->validateQuestion($request);

        DB::transaction(function () use ($validated, $request, $test) {
            $question = $test->questions()->create([
                'type' => $validated['type'],
                'prompt' => $validated['prompt'],
                'helper_text' => $validated['helper_text'] ?? null,
                'correct_answer_text' => $validated['type'] === 'fill_in_blank'
                    ? ($validated['correct_answer_text'] ?? null)
                    : null,
                'case_sensitive' => $validated['type'] === 'fill_in_blank' ? $request->boolean('case_sensitive') : false,
                'points' => $validated['points'],
                'sort_order' => ((int) $test->questions()->max('sort_order')) + 1,
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($validated['type'] === 'single_choice') {
                $this->syncSingleChoiceOptions(
                    $question,
                    $validated['options_text'],
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

        DB::transaction(function () use ($validated, $request, $question) {
            $question->update([
                'type' => $validated['type'],
                'prompt' => $validated['prompt'],
                'helper_text' => $validated['helper_text'] ?? null,
                'correct_answer_text' => $validated['type'] === 'fill_in_blank'
                    ? ($validated['correct_answer_text'] ?? null)
                    : null,
                'case_sensitive' => $validated['type'] === 'fill_in_blank' ? $request->boolean('case_sensitive') : false,
                'points' => $validated['points'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            if ($validated['type'] === 'single_choice') {
                $this->syncSingleChoiceOptions(
                    $question,
                    $validated['options_text'],
                    (int) $validated['correct_option_number']
                );
            } else {
                $question->options()->delete();
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
            'helper_text' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'correct_answer_text' => ['nullable', 'string', 'max:255'],
            'options_text' => ['nullable', 'string'],
            'correct_option_number' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validated['type'] === 'fill_in_blank' && empty($validated['correct_answer_text'])) {
            throw ValidationException::withMessages([
                'correct_answer_text' => 'Fill in blank savol uchun to‘g‘ri javob majburiy.',
            ]);
        }

        if ($validated['type'] === 'single_choice') {
            $options = $this->parseOptionsText($validated['options_text'] ?? '');
            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'options_text' => 'Multiple choice uchun kamida 2 ta variant kiriting.',
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

    private function parseOptionsText(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }

    private function syncSingleChoiceOptions(TestSubjectLessonTestQuestion $question, string $optionsText, int $correctOptionNumber): void
    {
        $options = $this->parseOptionsText($optionsText);
        $question->options()->delete();

        foreach ($options as $index => $optionText) {
            $question->options()->create([
                'option_text' => $optionText,
                'sort_order' => $index + 1,
                'is_correct' => ($index + 1) === $correctOptionNumber,
            ]);
        }
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
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\FanTesti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FanTestiController extends Controller
{
    private const ALLOWED_DEPARTMENT = 'Patalogik anatomiya, sud tibbiyoti, tibbiyot xuquqi kafedrasi';

    public function index(Request $request)
    {
        $teacher = $this->teacher();
        $subjects = $this->subjectsFor($teacher);

        $query = FanTesti::query()
            ->with('subject')
            ->whereIn('curriculum_subject_id', $subjects->pluck('id'))
            ->latest();

        if ($request->filled('subject_id') && $subjects->contains('id', (int) $request->integer('subject_id'))) {
            $query->where('curriculum_subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('subject', fn ($subject) => $subject->where('subject_name', 'like', "%{$search}%"));
            });
        }

        return view('teacher.fan-testlari.index', [
            'collections' => $query->paginate(15)->withQueryString(),
            'subjects' => $subjects,
        ]);
    }

    public function create()
    {
        $subjects = $this->subjectsFor($this->teacher());

        return view('teacher.fan-testlari.builder', [
            'collection' => null,
            'subjects' => $subjects,
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher();
        $subjects = $this->subjectsFor($teacher);
        $validated = $this->validateSettings($request, $subjects);

        $collection = FanTesti::create([
            ...$validated,
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'show_result_after_submit' => $request->boolean('show_result_after_submit', true),
            'is_active' => $request->boolean('is_active', true),
            'questions' => [],
            'created_by' => $teacher->id,
            'updated_by' => $teacher->id,
        ]);

        return redirect()
            ->route('teacher.fan-testlari.edit', $collection)
            ->with('success', 'Test to\'plami yaratildi. Endi savollarni kiriting.');
    }

    public function edit(FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        return view('teacher.fan-testlari.builder', [
            'collection' => $fanTesti->load('subject'),
            'subjects' => $this->subjectsFor($this->teacher()),
        ]);
    }

    public function update(Request $request, FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);
        $teacher = $this->teacher();
        $validated = $this->validateSettings($request, $this->subjectsFor($teacher));

        $fanTesti->update([
            ...$validated,
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'show_result_after_submit' => $request->boolean('show_result_after_submit', true),
            'is_active' => $request->boolean('is_active', true),
            'updated_by' => $teacher->id,
        ]);

        return back()->with('success', 'Test to\'plami sozlamalari saqlandi.');
    }

    public function storeQuestion(Request $request, FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);
        $question = $this->validatedQuestion($request);
        $question = $this->prepareQuestion($request, $question);

        $questions = $fanTesti->questions ?? [];
        $questions[] = $question;
        $fanTesti->update([
            'questions' => array_values($questions),
            'updated_by' => $this->teacher()->id,
        ]);

        return back()->with('success', 'Savol qo\'shildi.');
    }

    public function updateQuestion(Request $request, FanTesti $fanTesti, int $question)
    {
        $this->authorizeCollection($fanTesti);
        $questions = $fanTesti->questions ?? [];
        abort_unless(array_key_exists($question, $questions), 404);

        $validated = $this->validatedQuestion($request);
        $newQuestion = $this->prepareQuestion($request, $validated, $questions[$question]);
        $questions[$question] = $newQuestion;

        $fanTesti->update([
            'questions' => array_values($questions),
            'updated_by' => $this->teacher()->id,
        ]);

        return back()->with('success', 'Savol yangilandi.');
    }

    public function destroyQuestion(FanTesti $fanTesti, int $question)
    {
        $this->authorizeCollection($fanTesti);
        $questions = $fanTesti->questions ?? [];
        abort_unless(array_key_exists($question, $questions), 404);

        $this->deleteQuestionImage($questions[$question]['image_path'] ?? null);
        array_splice($questions, $question, 1);
        $fanTesti->update([
            'questions' => array_values($questions),
            'updated_by' => $this->teacher()->id,
        ]);

        return back()->with('success', 'Savol o\'chirildi.');
    }

    public function destroy(FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        foreach ($fanTesti->questions ?? [] as $question) {
            $this->deleteQuestionImage($question['image_path'] ?? null);
        }

        $fanTesti->delete();

        return redirect()
            ->route('teacher.fan-testlari.index')
            ->with('success', 'Test to\'plami o\'chirildi.');
    }

    private function teacher()
    {
        $teacher = auth()->guard('teacher')->user();
        abort_unless($teacher, 403);
        abort_unless($this->isAllowedDepartment($teacher), 403);

        return $teacher;
    }

    private function subjectsFor($teacher)
    {
        abort_unless($this->isAllowedDepartment($teacher), 403);

        return CurriculumSubject::query()
            ->where('is_active', true)
            ->where('department_id', $teacher->department_hemis_id)
            ->orderBy('subject_name')
            ->orderBy('semester_name')
            ->get([
                'id', 'subject_name', 'subject_code', 'semester_name',
                'department_id', 'department_name',
            ]);
    }

    private function isAllowedDepartment($teacher): bool
    {
        $teacherDepartment = trim((string) ($teacher->department ?? ''));
        if ($this->normalizeDepartment($teacherDepartment) === $this->normalizeDepartment(self::ALLOWED_DEPARTMENT)) {
            return true;
        }

        if (!$teacher->department_hemis_id) {
            return false;
        }

        $departmentName = \App\Models\Department::query()
            ->where('department_hemis_id', $teacher->department_hemis_id)
            ->value('name');

        return $this->normalizeDepartment((string) $departmentName) === $this->normalizeDepartment(self::ALLOWED_DEPARTMENT);
    }

    private function normalizeDepartment(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    private function authorizeCollection(FanTesti $fanTesti): void
    {
        $allowedSubjectIds = $this->subjectsFor($this->teacher())->pluck('id');
        abort_unless($allowedSubjectIds->contains((int) $fanTesti->curriculum_subject_id), 403);
    }

    private function validateSettings(Request $request, $subjects): array
    {
        return $request->validate([
            'curriculum_subject_id' => [
                'required', 'integer',
                Rule::in($subjects->pluck('id')->map(fn ($id) => (int) $id)->all()),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:300'],
            'pass_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function validatedQuestion(Request $request): array
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
            'correct_explanation' => ['nullable', 'string'],
            'correct_explanation_ru' => ['nullable', 'string'],
            'correct_explanation_en' => ['nullable', 'string'],
            'correct_answer_text' => ['nullable', 'string', 'max:255'],
            'correct_answer_text_ru' => ['nullable', 'string', 'max:255'],
            'correct_answer_text_en' => ['nullable', 'string', 'max:255'],
            'case_sensitive' => ['nullable', 'boolean'],
            'points' => ['required', 'integer', 'min:1', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'correct_option_number' => ['nullable', 'integer', 'min:1'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:255'],
            'options.*.text_ru' => ['nullable', 'string', 'max:255'],
            'options.*.text_en' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['type'] === 'fill_in_blank' && trim((string) ($validated['correct_answer_text'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'correct_answer_text' => 'To\'g\'ri javobni kiriting.',
            ]);
        }

        if ($validated['type'] === 'single_choice') {
            $options = collect($validated['options'] ?? [])
                ->filter(fn ($option) => trim((string) ($option['text'] ?? '')) !== '')
                ->values();
            if ($options->count() < 2) {
                throw ValidationException::withMessages([
                    'options' => 'Bitta javobli savol uchun kamida 2 ta variant kerak.',
                ]);
            }
            $correct = (int) ($validated['correct_option_number'] ?? 0);
            if ($correct < 1 || $correct > $options->count()) {
                throw ValidationException::withMessages([
                    'correct_option_number' => 'To\'g\'ri javob variantini tanlang.',
                ]);
            }
            $validated['options'] = $options->all();
            $validated['correct_option_number'] = $correct;
        }

        return $validated;
    }

    private function prepareQuestion(Request $request, array $validated, ?array $oldQuestion = null): array
    {
        $imagePath = $oldQuestion['image_path'] ?? null;
        if ($request->hasFile('question_image')) {
            $imagePath = $request->file('question_image')->store('fan-test-questions', 'public');
            $this->deleteQuestionImage($oldQuestion['image_path'] ?? null);
        } elseif ($request->boolean('remove_question_image')) {
            $this->deleteQuestionImage($imagePath);
            $imagePath = null;
        }

        $question = [
            'type' => $validated['type'],
            'prompt' => trim($validated['prompt']),
            'prompt_ru' => trim((string) ($validated['prompt_ru'] ?? '')),
            'prompt_en' => trim((string) ($validated['prompt_en'] ?? '')),
            'image_path' => $imagePath,
            'helper_text' => trim((string) ($validated['helper_text'] ?? '')),
            'helper_text_ru' => trim((string) ($validated['helper_text_ru'] ?? '')),
            'helper_text_en' => trim((string) ($validated['helper_text_en'] ?? '')),
            'correct_explanation' => trim((string) ($validated['correct_explanation'] ?? '')),
            'correct_explanation_ru' => trim((string) ($validated['correct_explanation_ru'] ?? '')),
            'correct_explanation_en' => trim((string) ($validated['correct_explanation_en'] ?? '')),
            'correct_answer_text' => $validated['type'] === 'fill_in_blank' ? trim((string) ($validated['correct_answer_text'] ?? '')) : null,
            'correct_answer_text_ru' => $validated['type'] === 'fill_in_blank' ? trim((string) ($validated['correct_answer_text_ru'] ?? '')) : null,
            'correct_answer_text_en' => $validated['type'] === 'fill_in_blank' ? trim((string) ($validated['correct_answer_text_en'] ?? '')) : null,
            'case_sensitive' => $validated['type'] === 'fill_in_blank' && $request->boolean('case_sensitive'),
            'points' => (int) $validated['points'],
            'is_active' => $request->boolean('is_active', true),
            'options' => [],
        ];

        if ($validated['type'] === 'single_choice') {
            foreach ($validated['options'] as $index => $option) {
                $question['options'][] = [
                    'text' => trim((string) ($option['text'] ?? '')),
                    'text_ru' => trim((string) ($option['text_ru'] ?? '')),
                    'text_en' => trim((string) ($option['text_en'] ?? '')),
                    'is_correct' => ($index + 1) === (int) $validated['correct_option_number'],
                ];
            }
        }

        return $question;
    }

    private function deleteQuestionImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}

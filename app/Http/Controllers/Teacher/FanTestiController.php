<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumSubjectTeacher;
use App\Models\FanTesti;
use App\Models\Group;
use App\Models\FanTestiAttempt;
use App\Models\FanTestiAttemptAnswer;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FanTestiController extends Controller
{
    private const ALLOWED_DEPARTMENT = 'Patologik anatomiya, sud tibbiyoti huquqi kafedrasi';

    public function index()
    {
        return $this->create();
    }

    public function create()
    {
        $teacher = $this->teacher();
        $subjects = $this->subjectsFor($teacher);

        return view('teacher.fan-testlari.builder', [
            'collection' => null,
            'subjects' => $subjects,
            'collections' => $this->collectionsFor($subjects),
        ]);
    }

    public function store(Request $request)
    {
        $teacher = $this->teacher();
        $subjects = $this->subjectsFor($teacher);
        $validated = $this->validateSettings($request, $subjects);

        $validated['curriculum_subject_id'] = $validated['curriculum_subject_id'] ?? null;

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
        $subjects = $this->subjectsFor($this->teacher());
        $collection = $fanTesti->load('subject');

        return view('teacher.fan-testlari.builder', [
            'collection' => $collection,
            'subjects' => $subjects,
            'collections' => $this->collectionsFor($subjects),
            'allowedGroups' => $this->allowedGroupsFor($collection),
        ]);
    }

    /**
     * Test fani biriktirilgan guruhlar (hemis id).
     *
     * Bitta fan bir necha o'quv reja va semestrda o'qitiladi, shuning uchun
     * faqat subject_id bo'yicha izlash barcha kurslardagi guruhlarni qaytarib
     * yuboradi. Shu sababli biriktirma o'quv reja (curriculum) va semestr
     * bo'yicha ham toraytiriladi — natijada aynan shu semestrdagi guruhlar
     * qoladi. Bu maydonlar bo'sh bo'lsa keng qidiruvga qaytiladi.
     */
    /**
     * Fan biriktirilgan guruhlar (hemis id).
     *
     * HEMIS biriktirmasida curriculum_id = fanning curricula_hemis_id si,
     * semester_id esa fanning semester_code i — semesters jadvali orqali
     * o'tilmaydi. Bu ikki shartsiz bitta subject_id barcha yillar va
     * rejalardagi guruhlarni qaytaradi (bitta fanga yuzlab guruh).
     *
     * Shartlar bajarilmasa bo'sh ro'yxat qaytadi: noto'g'ri keng ro'yxatdan
     * ko'ra hech nima ko'rsatmagan ma'qul, chunki kiosk aynan shu ro'yxat
     * bo'yicha talabani kiritadi.
     */
    private function subjectGroupIds(?CurriculumSubject $subject)
    {
        if (!Schema::hasTable('curriculum_subject_teachers')
            || !$subject?->subject_id
            || !$subject->curricula_hemis_id
            || !$subject->semester_code) {
            return collect();
        }

        return CurriculumSubjectTeacher::query()
            ->where('subject_id', $subject->subject_id)
            ->where('curriculum_id', $subject->curricula_hemis_id)
            ->where('semester_id', $subject->semester_code)
            ->where('active', true)
            ->whereNotNull('group_id')
            ->pluck('group_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function allowedGroupsFor(FanTesti $fanTesti)
    {
        $groupIds = $this->subjectGroupIds($fanTesti->subject);

        if ($groupIds->isEmpty()) {
            return collect();
        }

        return Group::query()
            ->whereIn('group_hemis_id', $groupIds)
            ->orderBy('name')
            ->pluck('name');
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

    /**
     * Test sahifasini talabalar uchun yopadi yoki qayta ochadi.
     * Kiosk kirishi is_active bo'yicha tekshiriladi, shu sababli bayroqni
     * o'zgartirish testni darhol yopadi — mavjud urinishlarga tegilmaydi.
     */
    public function toggleActive(FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        $willOpen = !$fanTesti->is_active;
        if ($willOpen && !$fanTesti->curriculum_subject_id) {
            return back()->with('error', "Avval to'plamga fan biriktiring — aks holda qaysi guruh ishlashi aniqlanmaydi.");
        }
        $fanTesti->update([
            'is_active' => $willOpen,
            'updated_by' => $this->teacher()->id,
        ]);

        return back()->with('success', $willOpen
            ? 'Test sahifasi ochildi — talabalar havola orqali kira oladi.'
            : 'Test sahifasi yopildi.');
    }

    /**
     * Qoralamaga fan biriktiradi — shundan keyin guruhlar aniqlanadi va
     * to'plamni talabalarga ochish mumkin bo'ladi.
     */
    public function attachSubject(Request $request, FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        $subjects = $this->subjectsFor($this->teacher());

        $data = $request->validate([
            'curriculum_subject_id' => [
                'required', 'integer',
                Rule::in($subjects->pluck('id')->map(fn ($id) => (int) $id)->all()),
            ],
            'name' => ['required', 'string', 'max:255'],
        ], [], [
            'curriculum_subject_id' => 'Fan',
            'name' => "To'plam nomi",
        ]);

        $fanTesti->update([
            'curriculum_subject_id' => (int) $data['curriculum_subject_id'],
            'name' => trim($data['name']),
            'updated_by' => $this->teacher()->id,
        ]);

        return back()->with('success', "To'plam fanga biriktirildi. Endi uni talabalarga ochish mumkin.");
    }

    /**
     * O'qituvchi uchun sinov ko'rinishi: testni talaba ko'rgan holicha
     * ochadi va ishlab ko'rish imkonini beradi.
     *
     * Hech narsa saqlanmaydi — urinish ham, javob ham bazaga yozilmaydi,
     * jurnalda ko'rinmaydi. Shu sababli fani biriktirilmagan qoralamani ham
     * sinab ko'rsa bo'ladi.
     */
    public function preview(FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        $questions = $this->previewQuestions($fanTesti);
        if ($questions->isEmpty()) {
            return back()->with('error', "Bu to'plamda hali savol yo'q.");
        }

        return view('kiosk.fan-testi.take', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $this->previewAttempt($fanTesti),
            'questions' => $questions->values(),
            'secondsLeft' => max(1, (int) $fanTesti->duration_minutes) * 60,
            'preview' => true,
        ]);
    }

    /** Sinov javoblarini baholaydi va natijani ko'rsatadi (saqlamaydi). */
    public function previewSubmit(Request $request, FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        $questions = $this->previewQuestions($fanTesti)->values();
        $given = (array) $request->input('answers', []);

        $kiosk = app(\App\Http\Controllers\FanTestiKioskController::class);
        $rows = $kiosk->gradePreview($questions->all(), $given);

        $answers = collect($rows)->map(fn ($row) => new FanTestiAttemptAnswer($row));
        $correct = $answers->where('is_correct', true)->count();
        $score = (int) $answers->sum('points_earned');
        $total = (int) $answers->sum('points_possible');
        $percent = $total > 0 ? round($score / $total * 100, 1) : 0.0;

        $attempt = $this->previewAttempt($fanTesti);
        $attempt->status = 'submitted';
        $attempt->questions_count = $questions->count();
        $attempt->answers_count = $answers->whereNotNull('answered_at')->count();
        $attempt->correct_count = $correct;
        $attempt->total_points = $total;
        $attempt->score = $score;
        $attempt->percent = $percent;
        $attempt->is_passed = $percent >= (float) ($fanTesti->pass_percent ?? 60);
        $attempt->submitted_at = now();
        $attempt->duration_seconds = null;
        $attempt->setRelation('answers', $answers);

        return view('kiosk.fan-testi.result', [
            'test' => $fanTesti->load('subject'),
            'attempt' => $attempt,
            'preview' => true,
        ]);
    }

    /** Sinov uchun saqlanmaydigan urinish namunasi. */
    private function previewAttempt(FanTesti $fanTesti): FanTestiAttempt
    {
        $teacher = $this->teacher();

        $attempt = new FanTestiAttempt([
            'fan_testi_id' => $fanTesti->id,
            'student_name' => $teacher->short_name ?: $teacher->full_name,
            'student_id_number' => 'Sinov',
            'group_name' => null,
            'status' => 'in_progress',
        ]);
        $attempt->questions_snapshot = $this->previewQuestions($fanTesti)->values()->all();

        return $attempt;
    }

    /** Sinovda faol savollar (javobi belgilanmagani ham ko'rsatiladi). */
    private function previewQuestions(FanTesti $fanTesti)
    {
        return collect($fanTesti->questions ?? [])
            ->filter(fn ($question) => ($question['is_active'] ?? true) !== false)
            ->values();
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

    /** Test jurnali: guruhlar kesimida topshirilgan testlar va javoblar. */
    public function journal(Request $request)
    {
        $subjects = $this->subjectsFor($this->teacher());

        if (!Schema::hasTable('fan_testi_attempts')) {
            return view('teacher.fan-testlari.journal', [
                'collections' => collect(),
                'selected' => null,
                'groups' => collect(),
                'summary' => null,
                'subjectOptions' => collect(),
                'allGroups' => collect(),
                'migrationPending' => true,
            ]);
        }

        $collections = $this->collectionsFor($subjects);

        // Mavzu (fan) filtri: tanlangan fan bo'yicha to'plamlar toraytiriladi.
        $subjectId = $request->integer('subject_id') ?: null;
        $visibleCollections = $subjectId
            ? $collections->where('curriculum_subject_id', $subjectId)->values()
            : $collections;

        $subjectOptions = $collections
            ->map(fn (FanTesti $item) => $item->subject)
            ->filter()
            ->unique('id')
            ->sortBy('subject_name')
            ->values();

        $selected = $request->filled('test_id')
            ? $visibleCollections->firstWhere('id', (int) $request->integer('test_id'))
            : $visibleCollections->first();

        if (!$selected) {
            return view('teacher.fan-testlari.journal', [
                'collections' => $visibleCollections,
                'selected' => null,
                'groups' => collect(),
                'summary' => null,
                'subjectOptions' => $subjectOptions,
                'allGroups' => collect(),
                'migrationPending' => false,
            ]);
        }

        $search = trim((string) $request->input('student'));

        $attempts = FanTestiAttempt::query()
            ->with('answers')
            ->where('fan_testi_id', $selected->id)
            ->when($request->filled('group'), fn ($query) => $query->where('group_name', $request->string('group')))
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('student_name', 'like', "%{$search}%")
                    ->orWhere('student_id_number', 'like', "%{$search}%");
            }))
            ->orderBy('student_name')
            ->get();

        $groups = $attempts
            ->groupBy(fn (FanTestiAttempt $attempt) => $attempt->group_name ?: 'Guruhsiz')
            ->map(fn ($rows, $name) => [
                'name' => $name,
                'attempts' => $rows,
                'submitted_count' => $rows->where('status', '!=', 'in_progress')->count(),
                'passed_count' => $rows->where('is_passed', true)->count(),
                'average_percent' => round((float) $rows->where('status', '!=', 'in_progress')->avg('percent'), 1),
            ])
            ->sortKeys()
            ->values();

        $finished = $attempts->where('status', '!=', 'in_progress');

        return view('teacher.fan-testlari.journal', [
            'collections' => $visibleCollections,
            'selected' => $selected,
            'groups' => $groups,
            'subjectOptions' => $subjectOptions,
            'allGroups' => FanTestiAttempt::query()
                ->where('fan_testi_id', $selected->id)
                ->whereNotNull('group_name')
                ->distinct()
                ->orderBy('group_name')
                ->pluck('group_name'),
            'summary' => [
                'total' => $attempts->count(),
                'submitted' => $finished->count(),
                'in_progress' => $attempts->where('status', 'in_progress')->count(),
                'passed' => $attempts->where('is_passed', true)->count(),
                'average_percent' => round((float) $finished->avg('percent'), 1),
            ],
            'migrationPending' => false,
        ]);
    }

    /**
     * Jurnaldan bitta urinishni o'chiradi — talaba testni qaytadan
     * topshira oladi (kiosk bir talabaga bitta urinish beradi).
     */
    public function destroyAttempt(FanTestiAttempt $attempt)
    {
        $collection = FanTesti::find($attempt->fan_testi_id);
        abort_unless($collection, 404);
        $this->authorizeCollection($collection);

        $attempt->delete();

        return back()->with('success', 'Talaba urinishi o\'chirildi — u testni qaytadan topshira oladi.');
    }

    /** Tanlangan test bo'yicha barcha urinishlarni tozalaydi. */
    public function clearAttempts(Request $request, FanTesti $fanTesti)
    {
        $this->authorizeCollection($fanTesti);

        $query = FanTestiAttempt::query()->where('fan_testi_id', $fanTesti->id);
        if ($request->filled('group')) {
            $query->where('group_name', $request->string('group'));
        }
        $removed = $query->count();
        $query->delete();

        return back()->with('success', $removed . ' ta urinish o\'chirildi.');
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

        $departmentTeacherHemisIds = session('active_role') === 'kafedra_mudiri'
            ? Teacher::query()
                ->where('department_hemis_id', $teacher->department_hemis_id)
                ->where('is_active', true)
                ->whereNotNull('hemis_id')
                ->pluck('hemis_id')
            : collect($teacher->hemis_id ? [$teacher->hemis_id] : []);

        $assignments = CurriculumSubjectTeacher::query()
            ->whereIn('employee_id', $departmentTeacherHemisIds)
            ->where('active', true)
            ->whereNotNull('subject_id')
            ->get(['subject_id']);

        $assignedSubjectIds = $assignments->pluck('subject_id')->unique()->values();

        $subjects = CurriculumSubject::query()
            ->where('is_active', true)
            ->where('department_id', $teacher->department_hemis_id)
            ->whereIn('subject_id', $assignedSubjectIds)
            ->orderBy('subject_name')
            ->orderBy('semester_name')
            ->get([
                'id', 'subject_id', 'subject_name', 'subject_code', 'semester_name',
                'semester_code', 'curricula_hemis_id', 'department_id', 'department_name',
            ]);

        $this->attachCurriculumLabels($subjects);
        $this->attachGroupCounts($subjects);

        // Joriy semestr (kuzgi — toq, bahorgi — juft) birinchi turadi, so'ng
        // guruhi borlar, keyin fan nomi. Guruhsiz variantlar pastga tushadi.
        $currentIsOdd = (int) date('n') >= 8 || (int) date('n') <= 1;

        return $subjects
            ->sortBy(function ($subject) use ($currentIsOdd) {
                $number = max(0, (int) $subject->semester_code - 10);
                $matchesSeason = $number > 0 && ($number % 2 === 1) === $currentIsOdd;

                return [
                    $subject->group_count > 0 ? 0 : 1,
                    $matchesSeason ? 0 : 1,
                    (string) $subject->subject_name,
                    -$subject->group_count,
                ];
            })
            ->values();
    }

    private function collectionsFor($subjects)
    {
        $ownerIds = $this->collectionOwnerIds($this->teacher());

        $collections = FanTesti::query()
            ->with('subject')
            ->where(function ($query) use ($subjects, $ownerIds) {
                $query->whereIn('curriculum_subject_id', $subjects->pluck('id'))
                    // Fani hali biriktirilmagan o'z qoralamalari
                    ->orWhere(fn ($draft) => $draft->whereNull('curriculum_subject_id')
                        ->whereIn('created_by', $ownerIds));
            })
            ->latest()
            ->get();

        // Jadval va jurnal filtri fan yonida reja nomini ko'rsatadi.
        $this->attachCurriculumLabels(
            $collections->map(fn (FanTesti $item) => $item->subject)->filter()
        );

        return $collections;
    }

    /**
     * Har fan yozuviga unga biriktirilgan guruhlar sonini qo'yadi.
     *
     * Bitta so'rovda yig'iladi: fan bo'yicha ro'yxat uzun bo'lishi mumkin,
     * har biriga alohida so'rov yubormaymiz. Kalitlar — subject_id,
     * curriculum_id va semester_id (fanning semester_code i).
     */
    private function attachGroupCounts($subjects): void
    {
        foreach ($subjects as $subject) {
            $subject->group_count = 0;
        }

        if (!Schema::hasTable('curriculum_subject_teachers') || $subjects->isEmpty()) {
            return;
        }

        try {
            $rows = CurriculumSubjectTeacher::query()
                ->select('subject_id', 'curriculum_id', 'semester_id')
                ->selectRaw('COUNT(DISTINCT group_id) as groups_count')
                ->whereIn('subject_id', $subjects->pluck('subject_id')->filter()->unique())
                ->where('active', true)
                ->whereNotNull('group_id')
                ->groupBy('subject_id', 'curriculum_id', 'semester_id')
                ->get();

            $counts = [];
            foreach ($rows as $row) {
                $counts[$row->subject_id . '|' . $row->curriculum_id . '|' . $row->semester_id] = (int) $row->groups_count;
            }

            foreach ($subjects as $subject) {
                $key = $subject->subject_id . '|' . $subject->curricula_hemis_id . '|' . $subject->semester_code;
                $subject->group_count = $counts[$key] ?? 0;
            }
        } catch (\Throwable $exception) {
            // Sanoq bezak — u bo'lmasa ham ro'yxat ishlaydi.
        }
    }

    /** Fan yozuvlariga o'quv reja nomini tayyor satr qilib biriktiradi. */
    private function attachCurriculumLabels($subjects): void
    {
        try {
            $ids = $subjects->pluck('curricula_hemis_id')->filter()->unique();
            $names = $ids->isEmpty() || !Schema::hasTable('curricula')
                ? collect()
                : Curriculum::query()->whereIn('curricula_hemis_id', $ids)->pluck('name', 'curricula_hemis_id');

            foreach ($subjects as $subject) {
                $subject->curriculum_label = (string) ($names[$subject->curricula_hemis_id] ?? '');
            }
        } catch (\Throwable $exception) {
            foreach ($subjects as $subject) {
                $subject->curriculum_label = '';
            }
        }
    }

    private function isAllowedDepartment($teacher): bool
    {
        $teacherDepartment = trim((string) ($teacher->department ?? ''));
        if ($this->matchesAllowedDepartment($teacherDepartment)) {
            return true;
        }

        if (!$teacher->department_hemis_id) {
            return false;
        }

        $departmentName = \App\Models\Department::query()
            ->where('department_hemis_id', $teacher->department_hemis_id)
            ->value('name');

        return $this->matchesAllowedDepartment((string) $departmentName);
    }

    private function normalizeDepartment(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }

    private function matchesAllowedDepartment(string $name): bool
    {
        $normalized = $this->normalizeDepartment($name);
        $normalized = str_replace('x', 'h', $normalized);
        $normalized = str_replace('patalogik', 'patologik', $normalized);

        return str_contains($normalized, 'patologik anatomiya')
            && str_contains($normalized, 'sud tibbiyoti')
            && str_contains($normalized, 'huquqi');
    }

    private function authorizeCollection(FanTesti $fanTesti): void
    {
        $teacher = $this->teacher();

        // Fansiz qoralamani faqat egasi (kafedra mudiri uchun — kafedradoshi)
        // tahrirlaydi: fan bo'yicha tekshirish bu yerda ishlamaydi.
        if (!$fanTesti->curriculum_subject_id) {
            abort_unless($this->collectionOwnerIds($teacher)->contains((int) $fanTesti->created_by), 403);
            return;
        }

        $allowedSubjectIds = $this->subjectsFor($teacher)->pluck('id');
        abort_unless($allowedSubjectIds->contains((int) $fanTesti->curriculum_subject_id), 403);
    }

    /**
     * Qoralama to'plam egalari: o'qituvchining o'zi, kafedra mudiri uchun
     * esa butun kafedra — u kafedrasining testlarini boshqaradi.
     */
    private function collectionOwnerIds($teacher)
    {
        if (session('active_role') === 'kafedra_mudiri' && $teacher->department_hemis_id) {
            return Teacher::query()
                ->where('department_hemis_id', $teacher->department_hemis_id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id);
        }

        return collect([(int) $teacher->id]);
    }

    private function validateSettings(Request $request, $subjects): array
    {
        return $request->validate([
            // Fan ixtiyoriy: dars jadvali tayyor bo'lmaguncha to'plam
            // "qoralama" bo'lib turadi va keyinroq biriktiriladi.
            'curriculum_subject_id' => [
                'nullable', 'integer',
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
            'type' => ['required', Rule::in(['single_choice', 'multiple_choice', 'true_false', 'fill_in_blank', 'matching', 'ordering'])],
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
            'correct_option_numbers' => ['nullable', 'array'],
            'correct_option_numbers.*' => ['integer', 'min:1'],
            'true_false_answer' => ['nullable', 'in:0,1'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['nullable', 'string', 'max:255'],
            'options.*.text_ru' => ['nullable', 'string', 'max:255'],
            'options.*.text_en' => ['nullable', 'string', 'max:255'],
            'pairs' => ['nullable', 'array'],
            'pairs.*.left' => ['nullable', 'string', 'max:255'],
            'pairs.*.left_ru' => ['nullable', 'string', 'max:255'],
            'pairs.*.left_en' => ['nullable', 'string', 'max:255'],
            'pairs.*.right' => ['nullable', 'string', 'max:255'],
            'pairs.*.right_ru' => ['nullable', 'string', 'max:255'],
            'pairs.*.right_en' => ['nullable', 'string', 'max:255'],
            'steps' => ['nullable', 'array'],
            'steps.*.text' => ['nullable', 'string', 'max:255'],
            'steps.*.text_ru' => ['nullable', 'string', 'max:255'],
            'steps.*.text_en' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validated['type'] === 'fill_in_blank' && trim((string) ($validated['correct_answer_text'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'correct_answer_text' => 'To\'g\'ri javobni kiriting.',
            ]);
        }

        if (in_array($validated['type'], ['single_choice', 'multiple_choice'], true)) {
            $options = collect($validated['options'] ?? [])
                ->filter(fn ($option) => trim((string) ($option['text'] ?? '')) !== '')
                ->values();
            if ($options->count() < 2) {
                throw ValidationException::withMessages([
                    'options' => 'Variantli savol uchun kamida 2 ta variant kerak.',
                ]);
            }
            $validated['options'] = $options->all();

            if ($validated['type'] === 'single_choice') {
                $correct = (int) ($validated['correct_option_number'] ?? 0);
                if ($correct < 1 || $correct > $options->count()) {
                    throw ValidationException::withMessages([
                        'correct_option_number' => 'To\'g\'ri javob variantini tanlang.',
                    ]);
                }
                $validated['correct_option_number'] = $correct;
            } else {
                $picked = collect($validated['correct_option_numbers'] ?? [])
                    ->map(fn ($number) => (int) $number)
                    ->filter(fn ($number) => $number >= 1 && $number <= $options->count())
                    ->unique()
                    ->sort()
                    ->values();
                if ($picked->count() < 2) {
                    throw ValidationException::withMessages([
                        'correct_option_numbers' => 'Ko\'p javobli savolda kamida 2 ta to\'g\'ri variant belgilang.',
                    ]);
                }
                if ($picked->count() >= $options->count()) {
                    throw ValidationException::withMessages([
                        'correct_option_numbers' => 'Barcha variantlar to\'g\'ri bo\'lishi mumkin emas — kamida bittasi noto\'g\'ri qolsin.',
                    ]);
                }
                $validated['correct_option_numbers'] = $picked->all();
            }
        }

        if ($validated['type'] === 'matching') {
            $pairs = collect($validated['pairs'] ?? [])
                ->filter(fn ($pair) => trim((string) ($pair['left'] ?? '')) !== ''
                    && trim((string) ($pair['right'] ?? '')) !== '')
                ->values();
            if ($pairs->count() < 2) {
                throw ValidationException::withMessages([
                    'pairs' => 'Moslashtirish savolida kamida 2 ta to\'liq juftlik kerak.',
                ]);
            }
            $validated['pairs'] = $pairs->all();
        }

        if ($validated['type'] === 'ordering') {
            $steps = collect($validated['steps'] ?? [])
                ->filter(fn ($step) => trim((string) ($step['text'] ?? '')) !== '')
                ->values();
            if ($steps->count() < 3) {
                throw ValidationException::withMessages([
                    'steps' => 'Ketma-ketlik savolida kamida 3 ta bosqich kerak.',
                ]);
            }
            $validated['steps'] = $steps->all();
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
            'pairs' => [],
            'steps' => [],
        ];

        if (in_array($validated['type'], ['single_choice', 'multiple_choice'], true)) {
            $correctNumbers = $validated['type'] === 'single_choice'
                ? [(int) $validated['correct_option_number']]
                : array_map('intval', $validated['correct_option_numbers']);

            foreach ($validated['options'] as $index => $option) {
                $question['options'][] = [
                    'text' => trim((string) ($option['text'] ?? '')),
                    'text_ru' => trim((string) ($option['text_ru'] ?? '')),
                    'text_en' => trim((string) ($option['text_en'] ?? '')),
                    'is_correct' => in_array($index + 1, $correctNumbers, true),
                ];
            }
        }

        // To'g'ri/Noto'g'ri — ikkita doimiy variantli maxsus holat.
        if ($validated['type'] === 'true_false') {
            $isTrue = (string) ($validated['true_false_answer'] ?? '1') === '1';
            $question['options'] = [
                ['text' => "To'g'ri", 'text_ru' => 'Верно', 'text_en' => 'True', 'is_correct' => $isTrue],
                ['text' => "Noto'g'ri", 'text_ru' => 'Неверно', 'text_en' => 'False', 'is_correct' => !$isTrue],
            ];
        }

        if ($validated['type'] === 'matching') {
            foreach ($validated['pairs'] as $pair) {
                $question['pairs'][] = [
                    'left' => trim((string) ($pair['left'] ?? '')),
                    'left_ru' => trim((string) ($pair['left_ru'] ?? '')),
                    'left_en' => trim((string) ($pair['left_en'] ?? '')),
                    'right' => trim((string) ($pair['right'] ?? '')),
                    'right_ru' => trim((string) ($pair['right_ru'] ?? '')),
                    'right_en' => trim((string) ($pair['right_en'] ?? '')),
                ];
            }
        }

        // Bosqichlar kiritilgan tartibda to'g'ri hisoblanadi, talabaga aralashtirib beriladi.
        if ($validated['type'] === 'ordering') {
            foreach ($validated['steps'] as $step) {
                $question['steps'][] = [
                    'text' => trim((string) ($step['text'] ?? '')),
                    'text_ru' => trim((string) ($step['text_ru'] ?? '')),
                    'text_en' => trim((string) ($step['text_en'] ?? '')),
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

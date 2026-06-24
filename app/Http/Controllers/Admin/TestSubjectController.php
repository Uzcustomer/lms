<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Group;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TestSubject;
use App\Models\TestSubjectLesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestSubjectController extends Controller
{
    private const TARGET_EDUCATION_YEAR = '2025-2026';

    public function index()
    {
        $subjects = TestSubject::query()
            ->with(['groups', 'lessons', 'teacher'])
            ->latest()
            ->paginate(20);

        return view('admin.test-subjects.index', compact('subjects'));
    }

    public function create()
    {
        [$groups, $levels] = $this->groupPayload();

        $faculties = Department::query()
            ->where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name'])
            ->mapWithKeys(fn (Department $department) => [(string) $department->department_hemis_id => $department->name]);

        $specialties = Specialty::query()
            ->whereIn('specialty_hemis_id', collect($groups)->pluck('specialty_hemis_id')->filter()->unique()->values())
            ->orderBy('name')
            ->get(['specialty_hemis_id', 'name', 'department_hemis_id'])
            ->map(fn (Specialty $specialty) => [
                'specialty_hemis_id' => (string) $specialty->specialty_hemis_id,
                'name' => $specialty->name,
                'department_hemis_id' => (string) $specialty->department_hemis_id,
            ])
            ->values();

        $teachers = Teacher::query()
            ->where(fn ($query) => $query->whereNull('is_active')->orWhere('is_active', true))
            ->orderBy('full_name')
            ->get(['id', 'hemis_id', 'full_name'])
            ->map(fn (Teacher $teacher) => [
                'id' => $teacher->id,
                'hemis_id' => (string) $teacher->hemis_id,
                'full_name' => $teacher->full_name,
            ])
            ->values();

        return view('admin.test-subjects.create', [
            'faculties' => $faculties,
            'specialties' => $specialties,
            'teachers' => $teachers,
            'groups' => $groups,
            'levels' => $levels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'faculty_hemis_id' => ['nullable', 'integer'],
            'specialty_hemis_id' => ['nullable', 'integer'],
            'level_code' => ['nullable', 'string', 'max:20'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.lesson_date' => ['required', 'date'],
            'lessons.*.starts_at' => ['nullable', 'date_format:H:i'],
            'lessons.*.ends_at' => ['nullable', 'date_format:H:i', 'after:lessons.*.starts_at'],
            'lessons.*.topic_title' => ['nullable', 'string', 'max:255'],
        ]);

        $faculty = !empty($validated['faculty_hemis_id'])
            ? Department::query()->where('department_hemis_id', $validated['faculty_hemis_id'])->first()
            : null;

        $specialty = !empty($validated['specialty_hemis_id'])
            ? Specialty::query()->where('specialty_hemis_id', $validated['specialty_hemis_id'])->first()
            : null;

        $teacher = !empty($validated['teacher_id'])
            ? Teacher::query()->find($validated['teacher_id'])
            : null;

        [, $levels] = $this->groupPayload();
        $levelName = null;
        if (!empty($validated['level_code'])) {
            $levelName = collect($levels)->firstWhere('code', (string) $validated['level_code'])['name']
                ?? ($validated['level_code'] . '-kurs');
        }

        $groups = Group::query()
            ->whereIn('id', $validated['group_ids'])
            ->get(['id', 'group_hemis_id', 'name']);

        DB::transaction(function () use ($validated, $faculty, $specialty, $teacher, $levelName, $groups) {
            $subject = TestSubject::query()->create([
                'name' => $validated['name'],
                'faculty_hemis_id' => $faculty?->department_hemis_id,
                'faculty_name' => $faculty?->name,
                'specialty_hemis_id' => $specialty?->specialty_hemis_id,
                'specialty_name' => $specialty?->name,
                'level_code' => $validated['level_code'] ?? null,
                'level_name' => $levelName,
                'teacher_id' => $teacher?->id,
                'teacher_hemis_id' => $teacher?->hemis_id,
                'teacher_name' => $teacher?->full_name,
                'starts_on' => $validated['starts_on'],
                'ends_on' => $validated['ends_on'],
                'is_active' => true,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            foreach ($groups as $group) {
                $subject->groupLinks()->create([
                    'group_id' => $group->id,
                    'group_hemis_id' => $group->group_hemis_id,
                    'group_name' => $group->name,
                ]);
            }

            foreach (array_values($validated['lessons']) as $index => $lesson) {
                $subject->lessons()->create([
                    'lesson_date' => $lesson['lesson_date'],
                    'starts_at' => $lesson['starts_at'] ?? null,
                    'ends_at' => $lesson['ends_at'] ?? null,
                    'topic_order' => $index + 1,
                    'topic_title' => $lesson['topic_title'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()
            ->route('admin.test-subjects.index')
            ->with('success', 'Test fan yaratildi.');
    }

    public function show(TestSubject $testSubject)
    {
        $testSubject->load(['groups', 'lessons', 'teacher']);

        $groupHemisIds = $testSubject->groups
            ->pluck('group_hemis_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values();

        $studentsByGroup = Student::query()
            ->whereIn('group_id', $groupHemisIds)
            ->orderBy('full_name')
            ->get(['hemis_id', 'student_id_number', 'full_name', 'group_id', 'group_name'])
            ->groupBy(fn (Student $student) => (string) $student->group_id);

        return view('admin.test-subjects.show', compact('testSubject', 'studentsByGroup'));
    }

    public function storeLesson(Request $request, TestSubject $testSubject)
    {
        $validated = $this->validateLesson($request);

        $testSubject->lessons()->create([
            'lesson_date' => $validated['lesson_date'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'topic_order' => ((int) $testSubject->lessons()->max('topic_order')) + 1,
            'topic_title' => $validated['topic_title'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncLessonOrdering($testSubject);

        return redirect()
            ->route('admin.test-subjects.show', $testSubject)
            ->with('success', 'Dars jadvali qo‘shildi.');
    }

    public function updateLesson(Request $request, TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);

        $validated = $this->validateLesson($request);

        $lesson->update([
            'lesson_date' => $validated['lesson_date'],
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'topic_title' => $validated['topic_title'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncLessonOrdering($testSubject);

        return redirect()
            ->route('admin.test-subjects.show', $testSubject)
            ->with('success', 'Dars jadvali yangilandi.');
    }

    public function destroyLesson(TestSubject $testSubject, TestSubjectLesson $lesson)
    {
        abort_unless((int) $lesson->test_subject_id === (int) $testSubject->id, 404);

        $lesson->delete();
        $this->syncLessonOrdering($testSubject);

        return redirect()
            ->route('admin.test-subjects.show', $testSubject)
            ->with('success', 'Dars jadvali o‘chirildi.');
    }

    public function destroy(TestSubject $testSubject)
    {
        $testSubject->delete();

        return redirect()
            ->route('admin.test-subjects.index')
            ->with('success', 'Test fan o\'chirildi.');
    }

    private function validateLesson(Request $request): array
    {
        return $request->validate([
            'lesson_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'topic_title' => ['required', 'string', 'max:255'],
        ]);
    }

    private function syncLessonOrdering(TestSubject $testSubject): void
    {
        $testSubject->load('lessons');

        $testSubject->lessons
            ->sortBy([
                fn (TestSubjectLesson $lesson) => $lesson->topic_order ?? PHP_INT_MAX,
                fn (TestSubjectLesson $lesson) => $lesson->id,
            ])
            ->values()
            ->each(function (TestSubjectLesson $lesson, int $index) {
                if ((int) $lesson->topic_order !== $index + 1) {
                    $lesson->update(['topic_order' => $index + 1]);
                }
            });
    }

    private function groupPayload(): array
    {
        $groups = Group::query()
            ->whereHas('curriculum', function ($query) {
                $query->where('education_year_name', 'like', self::TARGET_EDUCATION_YEAR . '%');
            })
            ->with(['curriculum.semesters'])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'group_hemis_id',
                'department_hemis_id',
                'specialty_hemis_id',
                'curriculum_hemis_id',
            ])
            ->map(function (Group $group) {
                $semester = $group->curriculum?->semesters
                    ?->sortByDesc(fn ($item) => (int) ($item->current ? 1 : 0))
                    ->first();

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'group_hemis_id' => (string) $group->group_hemis_id,
                    'department_hemis_id' => (string) $group->department_hemis_id,
                    'specialty_hemis_id' => (string) $group->specialty_hemis_id,
                    'level_code' => (string) ($semester?->level_code ?? ''),
                    'level_name' => (string) ($semester?->level_name ?? ''),
                ];
            })
            ->values()
            ->all();

        $levels = collect($groups)
            ->filter(fn ($group) => $group['level_code'] !== '')
            ->map(fn ($group) => [
                'code' => $group['level_code'],
                'name' => $group['level_name'] ?: ($group['level_code'] . '-kurs'),
            ])
            ->unique('code')
            ->sortBy(fn ($level) => (int) $level['code'])
            ->values()
            ->all();

        return [$groups, $levels];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Specialty;
use App\Models\Teacher;
use App\Models\TestSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestSubjectController extends Controller
{
    private const TARGET_EDUCATION_YEAR = '2025-2026';

    public function index()
    {
        $subjects = TestSubject::query()
            ->with(['lessons', 'groups', 'teacher'])
            ->latest()
            ->paginate(20);

        return view('admin.test-subjects.index', compact('subjects'));
    }

    public function create()
    {
        $baseGroups = $this->testSubjectGroupsQuery()
            ->with(['curriculum.semesters'])
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'group_hemis_id',
                'department_hemis_id',
                'specialty_hemis_id',
                'curriculum_hemis_id',
            ]);

        $facultyHemisIds = $baseGroups->pluck('department_hemis_id')->filter()->unique()->values();
        $specialtyHemisIds = $baseGroups->pluck('specialty_hemis_id')->filter()->unique()->values();
        $curriculumIds = $baseGroups->pluck('curriculum_hemis_id')->filter()->unique()->values();

        $faculties = Department::query()
            ->where('structure_type_code', 11)
            ->where('active', true)
            ->whereIn('department_hemis_id', $facultyHemisIds)
            ->orderBy('name')
            ->get(['department_hemis_id', 'name'])
            ->map(fn (Department $faculty) => [
                'department_hemis_id' => (string) $faculty->department_hemis_id,
                'name' => $faculty->name,
            ])
            ->values();

        $specialties = Specialty::query()
            ->whereIn('specialty_hemis_id', $specialtyHemisIds)
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

        $kafedraMap = CurriculumSubject::query()
            ->whereIn('curricula_hemis_id', $curriculumIds)
            ->whereNotNull('department_id')
            ->whereNotNull('department_name')
            ->select('curricula_hemis_id', 'department_id', 'department_name')
            ->get()
            ->groupBy('curricula_hemis_id');

        $groups = $baseGroups->map(function (Group $group) {
            $semester = $group->curriculum?->semesters
                ?->sortByDesc(fn ($item) => (int) ($item->current ? 1 : 0))
                ->first();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'group_hemis_id' => $group->group_hemis_id,
                'department_hemis_id' => (string) $group->department_hemis_id,
                'specialty_hemis_id' => (string) $group->specialty_hemis_id,
                'curriculum_hemis_id' => $group->curriculum_hemis_id,
                'level_code' => (string) ($semester?->level_code ?? ''),
                'level_name' => $semester?->level_name,
            ];
        })->values();

        $departments = $groups
            ->flatMap(function (array $group) use ($kafedraMap) {
                return ($kafedraMap->get($group['curriculum_hemis_id']) ?? collect())
                    ->map(fn ($item) => [
                        'department_id' => (string) $item->department_id,
                        'department_name' => $item->department_name,
                        'faculty_hemis_id' => $group['department_hemis_id'],
                        'specialty_hemis_id' => $group['specialty_hemis_id'],
                    ]);
            })
            ->unique(fn ($item) => implode('|', [
                $item['department_id'],
                $item['faculty_hemis_id'],
                $item['specialty_hemis_id'],
            ]))
            ->sortBy('department_name')
            ->values();

        $levels = $groups
            ->filter(fn ($group) => $group['level_code'] !== '')
            ->map(fn ($group) => [
                'code' => $group['level_code'],
                'name' => (string) ($group['level_name'] ?: ($group['level_code'] . '-kurs')),
            ])
            ->unique('code')
            ->sortBy(fn ($level) => (int) $level['code'])
            ->values();

        return view('admin.test-subjects.create', compact(
            'faculties',
            'specialties',
            'departments',
            'teachers',
            'groups',
            'levels'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'faculty_hemis_id' => ['nullable', 'integer'],
            'specialty_hemis_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'level_code' => ['nullable', 'string', 'max:20'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'exists:groups,id'],
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.lesson_date' => ['required', 'date'],
            'lessons.*.starts_at' => ['nullable', 'date_format:H:i'],
            'lessons.*.ends_at' => ['nullable', 'date_format:H:i'],
            'lessons.*.topic_title' => ['nullable', 'string', 'max:255'],
        ]);

        $faculty = !empty($validated['faculty_hemis_id'])
            ? Department::query()->where('department_hemis_id', $validated['faculty_hemis_id'])->first()
            : null;

        $specialty = !empty($validated['specialty_hemis_id'])
            ? Specialty::query()->where('specialty_hemis_id', $validated['specialty_hemis_id'])->first()
            : null;

        $department = !empty($validated['department_id'])
            ? CurriculumSubject::query()
                ->where('department_id', $validated['department_id'])
                ->whereNotNull('department_name')
                ->select('department_id', 'department_name')
                ->first()
            : null;

        $teacher = !empty($validated['teacher_id'])
            ? Teacher::query()->find($validated['teacher_id'])
            : null;

        $levelName = null;
        if (!empty($validated['level_code'])) {
            $levelName = collect($this->availableLevels())->firstWhere('code', (string) $validated['level_code'])['name']
                ?? ($validated['level_code'] . '-kurs');
        }

        $groups = Group::query()
            ->whereIn('id', $validated['group_ids'])
            ->get(['id', 'group_hemis_id', 'name']);

        DB::transaction(function () use ($validated, $faculty, $specialty, $department, $teacher, $levelName, $groups) {
            $subject = TestSubject::query()->create([
                'name' => $validated['name'],
                'faculty_hemis_id' => $faculty?->department_hemis_id,
                'faculty_name' => $faculty?->name,
                'department_hemis_id' => $department?->department_id,
                'department_name' => $department?->department_name,
                'specialty_hemis_id' => $specialty?->specialty_hemis_id,
                'specialty_name' => $specialty?->name,
                'level_code' => $validated['level_code'] ?? null,
                'level_name' => $levelName,
                'teacher_id' => $teacher?->id,
                'teacher_hemis_id' => $teacher?->hemis_id,
                'teacher_name' => $teacher?->full_name,
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
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
            ->with('success', 'Test fan muvaffaqiyatli yaratildi.');
    }

    public function show(TestSubject $testSubject)
    {
        $testSubject->load(['teacher', 'groups', 'lessons']);

        return view('admin.test-subjects.show', compact('testSubject'));
    }

    private function availableLevels(): array
    {
        return $this->testSubjectGroupsQuery()
            ->with(['curriculum.semesters'])
            ->get()
            ->map(function (Group $group) {
                $semester = $group->curriculum?->semesters
                    ?->sortByDesc(fn ($item) => (int) ($item->current ? 1 : 0))
                    ->first();

                return [
                    'code' => (string) ($semester?->level_code ?? ''),
                    'name' => (string) ($semester?->level_name ?? ''),
                ];
            })
            ->filter(fn ($level) => $level['code'] !== '')
            ->unique('code')
            ->values()
            ->all();
    }

    private function testSubjectGroupsQuery()
    {
        return Group::query()
            ->whereHas('curriculum', function ($query) {
                $query->where('education_year_name', 'like', self::TARGET_EDUCATION_YEAR . '%');
            });
    }
}

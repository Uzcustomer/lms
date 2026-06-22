<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Group;
use App\Models\Specialty;
use App\Models\Teacher;
use App\Models\TestSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TestSubjectController extends Controller
{
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
        $departments = Department::query()
            ->orderBy('name')
            ->get(['department_hemis_id', 'name']);

        $specialties = Specialty::query()
            ->orderBy('name')
            ->get(['specialty_hemis_id', 'name', 'department_hemis_id']);

        $teachers = Teacher::query()
            ->where(function ($query) {
                $query->whereNull('is_active')
                    ->orWhere('is_active', true);
            })
            ->orderBy('full_name')
            ->get(['id', 'hemis_id', 'full_name']);

        $groups = Group::query()
            ->with(['curriculum.semesters'])
            ->orderBy('name')
            ->get()
            ->map(function (Group $group) {
                $semester = $group->curriculum?->semesters
                    ?->sortByDesc(fn ($item) => (int) ($item->current ? 1 : 0))
                    ->first();

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'group_hemis_id' => $group->group_hemis_id,
                    'department_hemis_id' => $group->department_hemis_id,
                    'specialty_hemis_id' => $group->specialty_hemis_id,
                    'level_code' => $semester?->level_code,
                    'level_name' => $semester?->level_name,
                    'label' => trim($group->name . ' | ' . ($semester?->level_name ?? 'Kurs noma’lum')),
                ];
            })
            ->values();

        $levels = $groups
            ->filter(fn ($group) => !empty($group['level_code']))
            ->map(fn ($group) => [
                'code' => (string) $group['level_code'],
                'name' => (string) ($group['level_name'] ?: ($group['level_code'] . '-kurs')),
            ])
            ->unique('code')
            ->sortBy(fn ($level) => (int) $level['code'])
            ->values();

        return view('admin.test-subjects.create', compact(
            'departments',
            'specialties',
            'teachers',
            'groups',
            'levels'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department_hemis_id' => ['nullable', 'integer'],
            'specialty_hemis_id' => ['nullable', 'integer'],
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

        $department = !empty($validated['department_hemis_id'])
            ? Department::query()->where('department_hemis_id', $validated['department_hemis_id'])->first()
            : null;
        $specialty = !empty($validated['specialty_hemis_id'])
            ? Specialty::query()->where('specialty_hemis_id', $validated['specialty_hemis_id'])->first()
            : null;
        $teacher = !empty($validated['teacher_id'])
            ? Teacher::query()->find($validated['teacher_id'])
            : null;

        $levelName = null;
        if (!empty($validated['level_code'])) {
            $levelName = collect($this->availableLevels())->firstWhere('code', (string) $validated['level_code'])['name'] ?? ($validated['level_code'] . '-kurs');
        }

        $groups = Group::query()
            ->whereIn('id', $validated['group_ids'])
            ->get(['id', 'group_hemis_id', 'name']);

        DB::transaction(function () use ($validated, $department, $specialty, $teacher, $levelName, $groups) {
            $subject = TestSubject::query()->create([
                'name' => $validated['name'],
                'department_hemis_id' => $department?->department_hemis_id,
                'department_name' => $department?->name,
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
        $testSubject->load([
            'teacher',
            'groups',
            'lessons',
        ]);

        return view('admin.test-subjects.show', compact('testSubject'));
    }

    private function availableLevels(): array
    {
        return Group::query()
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
}

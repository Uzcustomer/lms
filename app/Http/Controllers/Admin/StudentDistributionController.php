<?php

namespace App\Http\Controllers\Admin;

use App\Exports\StudentDistributionAssignmentsExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Group;
use App\Models\StudentDistributionGroup;
use App\Models\StudentDistributionAssignment;
use App\Models\StudentGroupChangeApplication;
use App\Models\StudentGroupChangePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StudentDistributionController extends Controller
{
    public function index()
    {
        $groups = collect();
        if (Schema::hasTable('student_distribution_groups')) {
            $query = StudentDistributionGroup::query();
            if (Schema::hasColumn('student_distribution_groups', 'is_active')) {
                $query->where('is_active', true);
            }
            $groups = $query->orderBy('faculty_name')->orderBy('specialty_name')
                ->orderBy('course')->orderBy('group_name')->get();
        }

        $catalog = $this->catalogGroups($groups);
        $applications = Schema::hasTable('student_group_change_applications')
            ? StudentGroupChangeApplication::query()->latest()->limit(500)->get()
            : collect();

        return view('admin.student-distribution.index-v2', [
            'groups' => $groups,
            'groupPayloads' => $groups->map(fn (StudentDistributionGroup $group) => $this->groupPayload($group))->values(),
            'catalogPayloads' => $catalog,
            'applicationPayloads' => $applications->map(fn (StudentGroupChangeApplication $application) => $this->applicationPayload($application))->values(),
            'faculties' => $groups->pluck('faculty_name')->filter()->unique()->values(),
            'specialties' => $groups->pluck('specialty_name')->filter()->unique()->values(),
            'courses' => $groups->pluck('course')->filter()->unique()->sort()->values(),
        ]);
    }

    public function catalog(): JsonResponse
    {
        $saved = Schema::hasTable('student_distribution_groups')
            ? StudentDistributionGroup::query()->where('is_active', true)->get()
            : collect();

        return response()->json(['groups' => $this->catalogGroups($saved)]);
    }

    public function storeGroups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groups' => 'required|array|min:1|max:5000',
            'groups.*.key' => 'required|string|max:64',
            'groups.*.capacity' => 'required|integer|min:0|max:1000',
            'groups.*.free_places' => 'required|integer|min:0|max:1000',
        ]);

        $catalog = $this->catalogGroups()->keyBy('key');
        $selected = collect($data['groups']);
        $invalid = $selected->first(fn (array $row) => !$catalog->has($row['key']));
        if ($invalid) {
            return response()->json(['message' => 'Tanlangan guruhlardan biri LMS bazasida topilmadi.'], 422);
        }

        foreach ($selected as $row) {
            if ((int) $row['free_places'] > (int) $row['capacity']) {
                return response()->json(['message' => 'Bo\'sh joy guruh sig\'imidan katta bo\'lishi mumkin emas.'], 422);
            }
        }

        $importKey = (string) Str::uuid();
        DB::transaction(function () use ($selected, $catalog, $importKey) {
            foreach ($selected as $row) {
                $source = $catalog->get($row['key']);
                $group = [
                    'faculty_name' => $source['faculty_name'],
                    'specialty_name' => $source['specialty_name'],
                    'course' => $source['course'],
                    'group_name' => $source['group_name'],
                ];
                $scopeHash = $this->groupScopeHash($group);
                $capacity = (int) $row['capacity'];
                $freePlaces = (int) $row['free_places'];

                $record = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->where('scope_hash', $scopeHash)
                    ->first();

                $values = $group + [
                    'group_hemis_id' => $source['group_hemis_id'],
                    'capacity' => $capacity,
                    'occupied_count' => max(0, $capacity - $freePlaces),
                    'free_places' => $freePlaces,
                    'source_file' => null,
                    'uploaded_by' => Auth::id(),
                    'scope_hash' => $scopeHash,
                    'is_active' => true,
                ];

                if ($record) {
                    $record->update($values);
                } else {
                    StudentDistributionGroup::query()->create($values + [
                        'import_key' => $importKey,
                        'is_source' => false,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => $selected->count() . ' ta guruh DB ga saqlandi.',
            'groups' => $this->activeGroupPayloads(),
        ]);
    }

    public function storeSourceGroups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'groups' => 'present|array|max:5000',
            'groups.*.key' => 'required|string|max:64',
            'groups.*.student_count' => 'required|integer|min:0|max:1000',
        ]);

        $catalog = $this->catalogGroups()->keyBy('key');
        $selected = collect($data['groups'])->unique('key')->values();
        $invalid = $selected->first(fn (array $row) => !$catalog->has($row['key']));
        if ($invalid) {
            return response()->json(['message' => 'Tanlangan source guruhlardan biri LMS katalogida topilmadi.'], 422);
        }

        $importKey = (string) Str::uuid();
        DB::transaction(function () use ($selected, $catalog, $importKey) {
            StudentDistributionGroup::query()->where('is_active', true)->update(['is_source' => false]);

            foreach ($selected as $row) {
                $source = $catalog->get($row['key']);
                $base = [
                    'faculty_name' => $source['faculty_name'],
                    'specialty_name' => $source['specialty_name'],
                    'course' => $source['course'],
                    'group_name' => $source['group_name'],
                ];
                $scopeHash = $this->groupScopeHash($base);
                $studentCount = (int) $row['student_count'];
                $record = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->where('scope_hash', $scopeHash)
                    ->first();

                $values = $base + [
                    'group_hemis_id' => $source['group_hemis_id'],
                    'capacity' => $studentCount,
                    'occupied_count' => $studentCount,
                    'free_places' => 0,
                    'source_file' => null,
                    'uploaded_by' => Auth::id(),
                    'scope_hash' => $scopeHash,
                    'is_source' => true,
                    'is_active' => true,
                ];

                if ($record) {
                    $record->update($values);
                } else {
                    StudentDistributionGroup::query()->create($values + [
                        'import_key' => $importKey,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => $selected->count() . ' ta source guruh talabalar soni bilan draftga saqlandi.',
            'groups' => $this->activeGroupPayloads(),
        ]);
    }
    public function groups(Request $request): JsonResponse
    {
        $groups = $this->filteredGroups($request)
            ->orderBy('faculty_name')->orderBy('specialty_name')
            ->orderBy('course')->orderBy('group_name')->get()
            ->map(fn (StudentDistributionGroup $group) => $this->groupPayload($group))->values();

        return response()->json(['groups' => $groups]);
    }

    public function destroyGroup(int $group): JsonResponse
    {
        $deletedName = DB::transaction(function () use ($group) {
            $record = StudentDistributionGroup::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->findOrFail($group);

            $assignments = StudentDistributionAssignment::query()
                ->where(function (Builder $query) use ($record) {
                    $query->where('source_group_id', $record->id)
                        ->orWhere('target_group_id', $record->id);
                })
                ->lockForUpdate()
                ->get();

            foreach ($assignments as $assignment) {
                if ((int) $assignment->source_group_id === (int) $record->id) {
                    $target = StudentDistributionGroup::query()
                        ->lockForUpdate()
                        ->find($assignment->target_group_id);
                    if ($target) {
                        $target->update([
                            'occupied_count' => max(0, (int) $target->occupied_count - 1),
                            'free_places' => min((int) $target->capacity, (int) $target->free_places + 1),
                        ]);
                    }
                } else {
                    $source = StudentDistributionGroup::query()
                        ->lockForUpdate()
                        ->find($assignment->source_group_id);
                    if ($source) {
                        $source->update([
                            'occupied_count' => min((int) $source->capacity, (int) $source->occupied_count + 1),
                            'free_places' => max(0, (int) $source->free_places - 1),
                        ]);
                    }
                }
                $assignment->delete();
            }

            if ($record->is_source && Schema::hasTable('student_group_change_permissions')) {
                $studentIds = $this->studentsForDistributionGroup($record)->pluck('id');
                if ($studentIds->isNotEmpty()) {
                    StudentGroupChangePermission::query()
                        ->whereIn('student_id', $studentIds)
                        ->update(['enabled' => false]);
                }
            }

            $name = $record->group_name;
            $record->delete();

            return $name;
        }, 3);

        return response()->json([
            'message' => $deletedName . ' draft guruhi o\'chirildi.',
            'groups' => $this->activeGroupPayloads(),
        ]);
    }

    public function destroyAllGroups(): JsonResponse
    {
        $deletedCount = DB::transaction(function () {
            $records = StudentDistributionGroup::query()
                ->where('is_active', true)
                ->lockForUpdate()
                ->get();

            if ($records->isEmpty()) {
                return 0;
            }

            if (Schema::hasTable('student_group_change_permissions')) {
                $studentIds = $records
                    ->where('is_source', true)
                    ->flatMap(fn (StudentDistributionGroup $group) => $this->studentsForDistributionGroup($group)->pluck('id'))
                    ->unique()
                    ->values();

                if ($studentIds->isNotEmpty()) {
                    StudentGroupChangePermission::query()
                        ->whereIn('student_id', $studentIds)
                        ->update(['enabled' => false]);
                }
            }

            $ids = $records->pluck('id');
            if (Schema::hasTable('student_distribution_assignments')) {
                StudentDistributionAssignment::query()
                    ->whereIn('source_group_id', $ids)
                    ->orWhereIn('target_group_id', $ids)
                    ->delete();
            }
            if (Schema::hasTable('student_group_change_applications')) {
                StudentGroupChangeApplication::query()
                    ->whereIn('source_group_id', $ids)
                    ->orWhereIn('target_group_id', $ids)
                    ->delete();
            }

            StudentDistributionGroup::query()->whereIn('id', $ids)->delete();
            return $records->count();
        }, 3);

        return response()->json([
            'message' => $deletedCount . ' ta yuklangan draft guruh o\'chirildi.',
            'groups' => [],
        ]);
    }

    public function students(Request $request): JsonResponse
    {
        $request->validate([
            'faculty' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'course' => 'nullable|integer|min:1|max:6',
            'group_id' => 'nullable|integer|exists:student_distribution_groups,id',
            'unassigned' => 'nullable|boolean',
        ]);

        $group = $request->filled('group_id')
            ? StudentDistributionGroup::findOrFail($request->integer('group_id')) : null;

        $query = Student::query()->select([
            'id', 'full_name', 'student_id_number', 'hemis_id', 'image',
            'department_name', 'specialty_name', 'level_code', 'level_name', 'group_id', 'group_name',
        ]);
        $query->when($request->filled('faculty'), fn (Builder $q) => $q->where('department_name', $request->string('faculty')));
        $query->when($request->filled('specialty'), fn (Builder $q) => $q->where('specialty_name', $request->string('specialty')));
        $query->when($request->filled('course'), fn (Builder $q) => $this->whereCourse($q, $request->integer('course')));
        $query->when($group, function (Builder $q) use ($group) {
            $q->where(function (Builder $nested) use ($group) {
                $nested->where('group_name', $group->group_name);
                if ($group->group_hemis_id !== null && ctype_digit((string) $group->group_hemis_id)) {
                    $nested->orWhere('group_id', (int) $group->group_hemis_id);
                }
            });
        });
        $query->when($request->boolean('unassigned'), function (Builder $q) {
            $q->where(function (Builder $nested) {
                $nested->whereNull('group_name')->orWhere('group_name', '');
            });
        });

        $studentRows = $query->orderBy('full_name')->limit(500)->get();
        $drafts = collect();
        if (Schema::hasTable('student_distribution_assignments') && $studentRows->isNotEmpty()) {
            $drafts = StudentDistributionAssignment::query()
                ->with('targetGroup:id,group_name')
                ->whereIn('student_id', $studentRows->pluck('id'))
                ->get()
                ->keyBy('student_id');
        }

        $students = $studentRows->map(function (Student $student) use ($drafts) {
            $draft = $drafts->get($student->id);

            return [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'hemis_id' => $student->hemis_id,
                'image' => $student->image,
                'faculty' => $student->department_name,
                'specialty' => $student->specialty_name,
                'course' => $this->courseNumber($student->level_code, $student->level_name),
                'group_name' => $student->group_name,
                'draft_target_group_id' => $draft?->target_group_id,
                'draft_target_group_name' => $draft?->targetGroup?->group_name,
                'permission_enabled' => StudentGroupChangePermission::query()
                    ->where('student_id', $student->id)->where('enabled', true)->exists(),
            ];
        })->values();

        return response()->json(['students' => $students]);
    }

    public function assignStudent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'distribution_group_id' => 'required|integer|exists:student_distribution_groups,id',
        ]);

        try {
            $result = DB::transaction(function () use ($data) {
                $student = Student::query()->findOrFail($data['student_id']);
                $draft = StudentDistributionAssignment::query()
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();
                $target = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($data['distribution_group_id']);

                if ($target->is_source) {
                    abort(422, 'Talabalarni ko\'chiriladigan guruhga joylab bo\'lmaydi.');
                }
                if (!$this->studentMatchesGroup($student, $target)) {
                    abort(422, 'Talaba tanlangan guruhning fakultet, yo\'nalish yoki kursiga mos emas.');
                }

                $source = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->where('faculty_name', $student->department_name)
                    ->where('specialty_name', $target->specialty_name)
                    ->where('course', $target->course)
                    ->where('group_name', $student->group_name)
                    ->lockForUpdate()
                    ->first();

                if (!$source || !$source->is_source) {
                    abort(422, 'Bu talabaning guruhi taqsimlanadigan guruhlar ro\'yxatida yo\'q.');
                }
                if ($source->id === $target->id) {
                    abort(422, 'Talaba ayni guruhning o\'ziga biriktirilmaydi.');
                }
                if ($draft && (int) $draft->target_group_id === (int) $target->id) {
                    abort(422, 'Talaba bu guruhga draftda allaqachon biriktirilgan.');
                }
                if ($target->free_places < 1) {
                    abort(422, 'Tanlangan guruhning draft bo\'sh joyi qolmagan.');
                }

                if ($draft) {
                    $previousTarget = StudentDistributionGroup::query()
                        ->lockForUpdate()
                        ->find($draft->target_group_id);
                    if ($previousTarget) {
                        $previousTarget->update([
                            'occupied_count' => max(0, $previousTarget->occupied_count - 1),
                            'free_places' => min($previousTarget->capacity, $previousTarget->free_places + 1),
                        ]);
                    }
                } else {
                    $source->update([
                        'occupied_count' => max(0, $source->occupied_count - 1),
                        'free_places' => min($source->capacity, $source->free_places + 1),
                    ]);
                }

                $target->update([
                    'occupied_count' => $target->occupied_count + 1,
                    'free_places' => max(0, $target->free_places - 1),
                ]);

                $assignment = StudentDistributionAssignment::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'source_group_id' => $source->id,
                        'target_group_id' => $target->id,
                        'original_group_hemis_id' => $student->group_id ? (string) $student->group_id : null,
                        'original_group_name' => $student->group_name,
                        'student_name' => $student->full_name,
                        'student_id_number' => $student->student_id_number,
                        'assigned_by' => Auth::id(),
                    ]
                );

                return [
                    'group' => $this->groupPayload($target->fresh()),
                    'assignment_id' => $assignment->id,
                ];
            });

            return response()->json([
                'message' => 'Talaba faqat draft taqsimotga biriktirildi. LMS guruhi o\'zgarmadi.',
                'group' => $result['group'],
                'assignment_id' => $result['assignment_id'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }
    }
    public function setGroupChangePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'enabled' => 'required|boolean',
        ]);
        $permission = StudentGroupChangePermission::updateOrCreate(
            ['student_id' => $data['student_id']],
            ['enabled' => $data['enabled'], 'enabled_by_id' => Auth::id(), 'enabled_at' => $data['enabled'] ? now() : null]
        );


        return response()->json([
            'message' => $data['enabled'] ? 'Talabaga guruhni o\'zgartirish arizasiga ruxsat berildi.' : 'Talaba uchun xizmat yopildi.',
            'enabled' => (bool) $permission->enabled,
        ]);
    }
    public function permissionGroups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_group_id' => 'required|integer|exists:student_distribution_groups,id',
        ]);
        $target = StudentDistributionGroup::query()
            ->where('is_active', true)
            ->where('is_source', false)
            ->findOrFail($data['target_group_id']);

        $groups = StudentDistributionGroup::query()
            ->where('is_active', true)
            ->where('is_source', true)
            ->where('faculty_name', $target->faculty_name)
            ->where('specialty_name', $target->specialty_name)
            ->where('course', $target->course)
            ->orderBy('group_name')
            ->get()
            ->map(fn (StudentDistributionGroup $group) => $this->permissionGroupPayload($group))
            ->values();

        return response()->json(['groups' => $groups]);
    }

    public function setGroupChangePermissions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'distribution_group_ids' => 'required|array|min:1|max:500',
            'distribution_group_ids.*' => 'required|integer|distinct|exists:student_distribution_groups,id',
            'enabled' => 'required|boolean',
        ]);
        $groupIds = collect($data['distribution_group_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $groups = StudentDistributionGroup::query()
            ->whereIn('id', $groupIds)
            ->where('is_active', true)
            ->where('is_source', true)
            ->get();

        if ($groups->count() !== $groupIds->count()) {
            return response()->json(['message' => 'Faqat taqsimlanadigan faol guruhlarga ruxsat berish mumkin.'], 422);
        }

        $studentIds = $groups->flatMap(fn (StudentDistributionGroup $group) => $this->studentsForDistributionGroup($group)->pluck('id'))
            ->unique()->values();
        if ($studentIds->isEmpty()) {
            return response()->json(['message' => 'Tanlangan guruhlarda talaba topilmadi.'], 422);
        }

        $now = now();
        $rows = $studentIds->map(fn (int $studentId) => [
            'student_id' => $studentId,
            'enabled' => (bool) $data['enabled'],
            'enabled_by_id' => Auth::id(),
            'enabled_at' => $data['enabled'] ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();
        StudentGroupChangePermission::query()->upsert(
            $rows,
            ['student_id'],
            ['enabled', 'enabled_by_id', 'enabled_at', 'updated_at']
        );

        return response()->json([
            'message' => $data['enabled']
                ? $groups->count() . ' ta guruhdagi ' . $studentIds->count() . ' ta talabaga ariza xizmati ochildi.'
                : $groups->count() . ' ta guruhdagi ' . $studentIds->count() . ' ta talaba uchun xizmat yopildi.',
            'student_count' => $studentIds->count(),
        ]);
    }

    public function applications(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('student_group_change_applications'), 503, 'Arizalar jadvali hali migratsiya qilinmagan.');
        $request->validate([
            'faculty' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'course' => 'nullable|integer|min:1|max:6',
        ]);

        $applications = StudentGroupChangeApplication::query()
            ->when($request->filled('faculty'), fn (Builder $query) => $query->where('faculty_name', $request->string('faculty')))
            ->when($request->filled('specialty'), fn (Builder $query) => $query->where('specialty_name', $request->string('specialty')))
            ->when($request->filled('course'), fn (Builder $query) => $query->where('course', $request->integer('course')))
            ->latest()
            ->limit(500)
            ->get()
            ->map(fn (StudentGroupChangeApplication $application) => $this->applicationPayload($application))
            ->values();

        return response()->json(['applications' => $applications]);
    }

    public function approveApplication(int $application): JsonResponse
    {
        try {
            $result = DB::transaction(function () use ($application) {
                $record = StudentGroupChangeApplication::query()->lockForUpdate()->findOrFail($application);
                if ($record->status !== 'pending') {
                    abort(422, "Faqat kutilayotgan arizaga ruxsat berish mumkin.");
                }

                $student = Student::query()->findOrFail($record->student_id);
                $source = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($record->source_group_id);
                $target = StudentDistributionGroup::query()
                    ->where('is_active', true)
                    ->where('is_source', false)
                    ->lockForUpdate()
                    ->findOrFail($record->target_group_id);

                if (!$source->is_source) {
                    abort(422, "Talabaning source guruhi faol taqsimlash ro'yxatida emas.");
                }

                $draft = StudentDistributionAssignment::query()
                    ->where('student_id', $student->id)
                    ->lockForUpdate()
                    ->first();

                if (!$draft || (int) $draft->target_group_id !== (int) $target->id) {
                    if ($target->free_places < 1) {
                        abort(422, "Tanlangan guruhda bo'sh joy qolmagan.");
                    }

                    if ($draft) {
                        $previousTarget = StudentDistributionGroup::query()
                            ->lockForUpdate()
                            ->find($draft->target_group_id);
                        if ($previousTarget) {
                            $previousTarget->update([
                                'occupied_count' => max(0, $previousTarget->occupied_count - 1),
                                'free_places' => min($previousTarget->capacity, $previousTarget->free_places + 1),
                            ]);
                        }
                    } else {
                        $source->update([
                            'occupied_count' => max(0, $source->occupied_count - 1),
                            'free_places' => min($source->capacity, $source->free_places + 1),
                        ]);
                    }

                    $target->update([
                        'occupied_count' => $target->occupied_count + 1,
                        'free_places' => max(0, $target->free_places - 1),
                    ]);

                    StudentDistributionAssignment::query()->updateOrCreate(
                        ['student_id' => $student->id],
                        [
                            'source_group_id' => $source->id,
                            'target_group_id' => $target->id,
                            'original_group_hemis_id' => $student->group_id ? (string) $student->group_id : null,
                            'original_group_name' => $student->group_name,
                            'student_name' => $student->full_name,
                            'student_id_number' => $student->student_id_number,
                            'assigned_by' => Auth::id(),
                        ]
                    );
                }

                $record->update([
                    'status' => 'approved',
                    'reviewed_by_id' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
                StudentGroupChangePermission::query()
                    ->where('student_id', $student->id)
                    ->update(['enabled' => false]);

                return [
                    'application' => $this->applicationPayload($record->fresh()),
                    'group' => $this->groupPayload($target->fresh()),
                ];
            }, 3);

            return response()->json([
                'message' => "Arizaga ruxsat berildi. Talaba draft guruhga qo'shildi.",
                'application' => $result['application'],
                'group' => $result['group'],
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
    }

    public function exportAssignments(Request $request)
    {
        abort_unless(Schema::hasTable('student_distribution_assignments'), 503, 'Taqsimot jadvali hali migratsiya qilinmagan.');

        $filters = $request->validate([
            'faculty' => 'nullable|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'course' => 'nullable|integer|min:1|max:6',
        ]);

        $fileName = 'talabalar-taqsimoti-' . now()->format('Y-m-d-Hi') . '.xlsx';

        return (new StudentDistributionAssignmentsExport($filters))->download($fileName);
    }

    public function destroyApplication(int $application): JsonResponse
    {
        try {
            DB::transaction(function () use ($application) {
                $record = StudentGroupChangeApplication::query()->lockForUpdate()->findOrFail($application);
                if ($record->status !== 'pending') {
                    abort(422, "Faqat kutilayotgan arizani o'chirish mumkin.");
                }
                $record->delete();
            });

            return response()->json(['message' => "Ariza o'chirildi."]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
    }



    private function catalogGroups(?Collection $savedGroups = null): Collection
    {
        $savedGroups ??= Schema::hasTable('student_distribution_groups')
            ? StudentDistributionGroup::query()->where('is_active', true)->get()
            : collect();
        $savedByScope = $savedGroups->keyBy(fn (StudentDistributionGroup $group) => $group->scope_hash);

        $studentGroups = Student::query()
            ->whereNotNull('group_name')->where('group_name', '<>', '')
            ->whereNotNull('department_name')->whereNotNull('specialty_name')
            ->select([
                'department_name', 'specialty_name', 'level_code', 'level_name', 'group_id', 'group_name',
                DB::raw('COUNT(*) as student_count'),
            ])
            ->groupBy('department_name', 'specialty_name', 'level_code', 'level_name', 'group_id', 'group_name')
            ->get()
            ->map(function ($row) {
                $course = $this->courseNumber($row->level_code, $row->level_name);
                if (!$course) {
                    return null;
                }

                return [
                    'faculty_name' => trim((string) $row->department_name),
                    'specialty_name' => trim((string) $row->specialty_name),
                    'course' => $course,
                    'group_name' => trim((string) $row->group_name),
                    'group_hemis_id' => $row->group_id ? (string) $row->group_id : null,
                    'student_count' => (int) $row->student_count,
                ];
            })
            ->filter()
            ->values();

        $studentByHemisId = $studentGroups->filter(fn (array $group) => $group['group_hemis_id'])
            ->keyBy(fn (array $group) => (string) $group['group_hemis_id']);
        $studentByName = $studentGroups->keyBy(fn (array $group) => $this->catalogNameKey(
            $group['faculty_name'], $group['specialty_name'], $group['group_name']
        ));
        $courseByCohort = $studentGroups->mapWithKeys(function (array $group) {
            $key = $this->catalogCohortKey(
                $group['faculty_name'], $group['specialty_name'], $group['group_name']
            );

            return $key ? [$key => $group['course']] : [];
        });

        $lmsQuery = Group::query();
        if (Schema::hasColumn('groups', 'active')) {
            $lmsQuery->where('active', true);
        }

        $lmsGroups = $lmsQuery->get([
            'group_hemis_id', 'name', 'department_name', 'specialty_name',
        ])->map(function (Group $group) use ($studentByHemisId, $studentByName, $courseByCohort) {
            $match = $studentByHemisId->get((string) $group->group_hemis_id)
                ?? $studentByName->get($this->catalogNameKey(
                    $group->department_name, $group->specialty_name, $group->name
                ));
            $course = $match['course'] ?? null;
            if (!$course) {
                $cohortKey = $this->catalogCohortKey(
                    $group->department_name, $group->specialty_name, $group->name
                );
                $course = $cohortKey ? $courseByCohort->get($cohortKey) : null;
            }
            $course ??= $this->inferCourseFromGroupName($group->name);
            if (!$course) {
                return null;
            }

            return [
                'faculty_name' => trim((string) $group->department_name),
                'specialty_name' => trim((string) $group->specialty_name),
                'course' => (int) $course,
                'group_name' => trim((string) $group->name),
                'group_hemis_id' => (string) $group->group_hemis_id,
                'student_count' => (int) ($match['student_count'] ?? 0),
            ];
        })->filter();

        return $studentGroups->concat($lmsGroups)
            ->map(function (array $base) use ($savedByScope) {
                $scopeHash = $this->groupScopeHash($base);
                $saved = $savedByScope->get($scopeHash);

                return $base + [
                    'key' => $scopeHash,
                    'saved_id' => $saved?->id,
                    'capacity' => $saved ? (int) $saved->capacity : (int) $base['student_count'],
                    'free_places' => $saved ? (int) $saved->free_places : 0,
                    'is_saved' => (bool) $saved,
                    'is_source' => (bool) ($saved?->is_source),
                ];
            })
            ->unique('key')
            ->sortBy(fn (array $group) => implode('|', [
                $group['faculty_name'], $group['specialty_name'], $group['course'], $group['group_name'],
            ]))
            ->values();
    }

    private function catalogNameKey($faculty, $specialty, $groupName): string
    {
        return mb_strtolower(trim((string) $faculty) . '|' . trim((string) $specialty) . '|' . trim((string) $groupName));
    }

    private function catalogCohortKey($faculty, $specialty, $groupName): ?string
    {
        if (!preg_match('/(?:^|\/)[a-z]?(\d{2})-/i', (string) $groupName, $match)) {
            return null;
        }

        return mb_strtolower(trim((string) $faculty) . '|' . trim((string) $specialty) . '|' . $match[1]);
    }

    private function inferCourseFromGroupName($groupName): ?int
    {
        if (!preg_match('/(?:^|\/)[a-z]?(\d{2})-/i', (string) $groupName, $match)) {
            return null;
        }

        $academicStartYear = now()->month >= 7 ? now()->year : now()->year - 1;
        $course = ((int) substr((string) $academicStartYear, -2)) - (int) $match[1] + 1;

        return $course >= 1 && $course <= 6 ? $course : null;
    }
    private function activeGroupPayloads(): Collection
    {
        return StudentDistributionGroup::query()->where('is_active', true)
            ->orderBy('faculty_name')->orderBy('specialty_name')
            ->orderBy('course')->orderBy('group_name')->get()
            ->map(fn (StudentDistributionGroup $group) => $this->groupPayload($group))->values();
    }

    private function filteredGroups(Request $request): Builder
    {
        return StudentDistributionGroup::query()->where('is_active', true)
            ->when($request->filled('faculty'), fn (Builder $q) => $q->where('faculty_name', $request->string('faculty')))
            ->when($request->filled('specialty'), fn (Builder $q) => $q->where('specialty_name', $request->string('specialty')))
            ->when($request->filled('course'), fn (Builder $q) => $q->where('course', $request->integer('course')))
            ->when($request->boolean('available_only'), fn (Builder $q) => $q->where('free_places', '>', 0)->where('is_source', false))
            ->when($request->boolean('source_only'), fn (Builder $q) => $q->where('is_source', true));
    }

    private function whereCourse(Builder $query, int $course): Builder
    {
        return $query->where(function (Builder $nested) use ($course) {
            $nested->whereIn('level_code', [(string) $course, (string) (10 + $course), $course, 10 + $course])
                ->orWhere('level_name', 'like', $course . '-kurs%')
                ->orWhere('level_name', 'like', $course . ' kurs%');
        });
    }

    private function studentsForDistributionGroup(StudentDistributionGroup $group): Builder
    {
        return Student::query()
            ->where('department_name', $group->faculty_name)
            ->where('specialty_name', $group->specialty_name)
            ->where(fn (Builder $query) => $this->whereCourse($query, (int) $group->course))
            ->where(function (Builder $query) use ($group) {
                $query->where('group_name', $group->group_name);
                if ($group->group_hemis_id !== null && ctype_digit((string) $group->group_hemis_id)) {
                    $query->orWhere('group_id', (int) $group->group_hemis_id);
                }
            });
    }

    private function permissionGroupPayload(StudentDistributionGroup $group): array
    {
        $studentIds = $this->studentsForDistributionGroup($group)->pluck('id');
        $enabledCount = $studentIds->isEmpty() ? 0 : StudentGroupChangePermission::query()
            ->whereIn('student_id', $studentIds)
            ->where('enabled', true)
            ->count();

        return $this->groupPayload($group) + [
            'student_count' => $studentIds->count(),
            'permission_count' => $enabledCount,
            'all_enabled' => $studentIds->isNotEmpty() && $enabledCount === $studentIds->count(),
        ];
    }

    private function applicationPayload(StudentGroupChangeApplication $application): array
    {
        return [
            'id' => $application->id,
            'student_name' => $application->student_name,
            'student_id_number' => $application->student_id_number,
            'faculty_name' => $application->faculty_name,
            'specialty_name' => $application->specialty_name,
            'course' => (int) $application->course,
            'source_group_name' => $application->source_group_name,
            'target_group_name' => $application->target_group_name,
            'reason' => $application->reason,
            'status' => $application->status,
            'created_at' => optional($application->created_at)->format('d.m.Y H:i'),
        ];
    }

    private function distributionFacultiesCompatible(
        ?string $sourceFaculty,
        ?string $sourceGroup,
        ?string $targetFaculty,
        ?string $targetGroup
    ): bool {
        if (mb_strtolower(trim((string) $sourceFaculty)) === mb_strtolower(trim((string) $targetFaculty))) {
            return true;
        }

        return preg_match('/^d[12](?:\\/|$)/i', trim((string) $sourceGroup)) === 1
            && preg_match('/^d[12](?:\\/|$)/i', trim((string) $targetGroup)) === 1;
    }

    private function studentMatchesGroup(Student $student, StudentDistributionGroup $group): bool
    {
        return $this->distributionFacultiesCompatible(
            $student->department_name,
            $student->group_name,
            $group->faculty_name,
            $group->group_name
        )
            && trim((string) $student->specialty_name) === trim((string) $group->specialty_name)
            && $this->courseNumber($student->level_code, $student->level_name) === (int) $group->course;
    }

    private function groupPayload(StudentDistributionGroup $group): array
    {
        return [
            'id' => $group->id,
            'faculty_name' => $group->faculty_name,
            'specialty_name' => $group->specialty_name,
            'course' => (int) $group->course,
            'group_name' => $group->group_name,
            'group_hemis_id' => $group->group_hemis_id,
            'capacity' => (int) $group->capacity,
            'occupied_count' => (int) $group->occupied_count,
            'free_places' => (int) $group->free_places,
            'is_source' => (bool) $group->is_source,
        ];
    }

    private function groupScopeHash(array $group): string
    {
        return hash('sha256', implode("\x1F", [
            mb_strtolower(trim((string) $group['faculty_name'])),
            mb_strtolower(trim((string) $group['specialty_name'])),
            (string) $group['course'],
            mb_strtolower(trim((string) $group['group_name'])),
        ]));
    }

    private function courseNumber($levelCode, $levelName): ?int
    {
        $code = (int) $levelCode;
        if ($code >= 11 && $code <= 16) {
            return $code - 10;
        }
        if ($code >= 1 && $code <= 6) {
            return $code;
        }
        if (preg_match('/([1-6])\s*[- ]?\s*kurs/i', (string) $levelName, $match)) {
            return (int) $match[1];
        }

        return null;
    }
}

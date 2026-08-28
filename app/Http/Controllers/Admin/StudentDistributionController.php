<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDistributionGroup;
use App\Models\StudentGroupChangePermission;
use App\Models\StudentGroupHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentDistributionController extends Controller
{
    private const MAX_DISPLAY_ROWS = 10000;

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

        return view('admin.student-distribution.index-v2', [
            'groups' => $groups,
            'groupPayloads' => $groups->map(fn (StudentDistributionGroup $group) => $this->groupPayload($group))->values(),
            'faculties' => $groups->pluck('faculty_name')->filter()->unique()->values(),
            'specialties' => $groups->pluck('specialty_name')->filter()->unique()->values(),
            'courses' => $groups->pluck('course')->filter()->unique()->sort()->values(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'student_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:20480',
        ], [
            'student_file.required' => 'Excel faylni tanlang.',
            'student_file.file' => 'Yuklangan faylni o\'qib bo\'lmadi.',
            'student_file.mimes' => 'Faqat XLSX, XLS yoki CSV fayl yuklang.',
            'student_file.max' => 'Fayl hajmi 20 MB dan oshmasligi kerak.',
        ]);

        $file = $request->file('student_file');

        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getSheet(0);
            $rawRows = $sheet->toArray(null, true, true, true);
            $nonEmptyRows = array_values(array_filter($rawRows, function ($row) {
                foreach ($row as $value) {
                    if ($this->cellText($value) !== '') return true;
                }
                return false;
            }));

            if (!$nonEmptyRows) {
                return back()->withInput()->withErrors(['student_file' => 'Excel faylda to\'ldirilgan qator topilmadi.']);
            }

            $headers = array_values(array_shift($nonEmptyRows));
            $columnCount = count($headers);
            foreach ($nonEmptyRows as $row) $columnCount = max($columnCount, count($row));
            $headers = $this->normalizeRow($headers, $columnCount, true);

            $totalRows = count($nonEmptyRows);
            $truncated = $totalRows > self::MAX_DISPLAY_ROWS;
            $rows = array_map(
                fn ($row) => $this->normalizeRow($row, $columnCount),
                array_slice($nonEmptyRows, 0, self::MAX_DISPLAY_ROWS)
            );

            return $this->importGroups($headers, $nonEmptyRows, $columnCount, $file->getClientOriginalName());
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput()->withErrors(['student_file' => 'Excel faylni o\'qishda xatolik yuz berdi. Fayl formatini tekshiring.']);
        }
    }

    private function importGroups(array $headers, array $rows, int $columnCount, string $fileName)
    {
        $mapping = $this->mapHeaders($headers);
        $missing = array_values(array_filter([
            'fakultet' => $mapping['faculty_name'] === null,
            'yonalish' => $mapping['specialty_name'] === null,
            'kurs' => $mapping['course'] === null,
            'guruh' => $mapping['group_name'] === null,
            'sigim yoki bosh joy' => $mapping['capacity'] === null && $mapping['free_places'] === null,
        ], fn ($missing) => $missing));
        if ($missing) {
            return back()->withInput()->withErrors([
                'student_file' => 'Excel ustunlari yetishmayapti: ' . implode(', ', array_keys($missing)) . '.',
            ]);
        }
        $importKey = (string) Str::uuid();
        $parsedGroups = [];
        foreach ($rows as $row) {
            $group = $this->parseGroupRow($this->normalizeRow($row, $columnCount), $mapping);
            if ($group !== null) {
                $parsedGroups[] = $group + [
                    'source_file' => $fileName,
                    'uploaded_by' => Auth::id(),
                    'import_key' => $importKey,
                    'scope_hash' => $this->groupScopeHash($group),
                    'is_active' => true,
                ];
            }
        }
        if (!$parsedGroups) {
            return back()->withInput()->withErrors(['student_file' => 'Exceldan yaroqli guruh qatorlari topilmadi.']);
        }
        DB::transaction(function () use ($parsedGroups) {
            StudentDistributionGroup::query()->where('is_active', true)->update(['is_active' => false]);
            foreach (array_chunk($parsedGroups, 500) as $chunk) StudentDistributionGroup::query()->insert($chunk);
        });
        return redirect()->route('admin.student-distribution.index')
            ->with('success', count($parsedGroups) . ' ta guruh malumoti saqlandi.');
    }
    public function groups(Request $request): JsonResponse
    {
        $groups = $this->filteredGroups($request)
            ->orderBy('faculty_name')->orderBy('specialty_name')
            ->orderBy('course')->orderBy('group_name')->get()
            ->map(fn (StudentDistributionGroup $group) => $this->groupPayload($group))->values();

        return response()->json(['groups' => $groups]);
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

        $students = $query->orderBy('full_name')->limit(500)->get()->map(function (Student $student) {
            return [
                'id' => $student->id, 'name' => $student->full_name,
                'student_id_number' => $student->student_id_number, 'hemis_id' => $student->hemis_id,
                'image' => $student->image, 'faculty' => $student->department_name,
                'specialty' => $student->specialty_name,
                'course' => $this->courseNumber($student->level_code, $student->level_name),
                'group_name' => $student->group_name,
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
                $student = Student::query()->lockForUpdate()->findOrFail($data['student_id']);
                $target = StudentDistributionGroup::query()->where('is_active', true)->lockForUpdate()
                    ->findOrFail($data['distribution_group_id']);

                if (!$this->studentMatchesGroup($student, $target)) {
                    abort(422, 'Talaba tanlangan guruhning fakultet, yonalish yoki kursiga mos emas.');
                }
                if ($target->free_places < 1) abort(422, 'Tanlangan guruhda bosh joy qolmagan.');

                $oldGroup = StudentDistributionGroup::query()->where('is_active', true)
                    ->where('faculty_name', $target->faculty_name)->where('specialty_name', $target->specialty_name)
                    ->where('course', $target->course)->where('group_name', $student->group_name)
                    ->lockForUpdate()->first();

                if ($oldGroup && $oldGroup->id !== $target->id) {
                    $oldGroup->update([
                        'occupied_count' => max(0, $oldGroup->occupied_count - 1),
                        'free_places' => min($oldGroup->capacity, $oldGroup->free_places + 1),
                    ]);
                }
                if (!$oldGroup || $oldGroup->id !== $target->id) {
                    $target->update([
                        'occupied_count' => $target->occupied_count + 1,
                        'free_places' => max(0, $target->free_places - 1),
                    ]);
                }

                if ($student->group_name) {
                    StudentGroupHistory::query()->where('student_id', $student->id)
                        ->whereNull('ended_at')->update(['ended_at' => now()]);
                }
                $student->update([
                    'group_id' => $this->numericGroupId($target->group_hemis_id),
                    'group_name' => $target->group_name,
                ]);
                StudentGroupHistory::create([
                    'student_id' => $student->id, 'group_hemis_id' => $target->group_hemis_id,
                    'group_name' => $target->group_name, 'specialty_name' => $student->specialty_name,
                    'education_year_name' => $student->education_year_name, 'started_at' => now(),
                ]);

                return $this->groupPayload($target->fresh());
            });

            return response()->json(['message' => 'Talaba guruhga muvaffaqiyatli otkazildi.', 'group' => $result]);
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
            'message' => $data['enabled'] ? 'Talabaga guruhni ozgartirish arizasiga ruxsat berildi.' : 'Talaba uchun xizmat yopildi.',
            'enabled' => (bool) $permission->enabled,
        ]);
    }

    private function filteredGroups(Request $request): Builder
    {
        return StudentDistributionGroup::query()->where('is_active', true)
            ->when($request->filled('faculty'), fn (Builder $q) => $q->where('faculty_name', $request->string('faculty')))
            ->when($request->filled('specialty'), fn (Builder $q) => $q->where('specialty_name', $request->string('specialty')))
            ->when($request->filled('course'), fn (Builder $q) => $q->where('course', $request->integer('course')))
            ->when($request->boolean('available_only'), fn (Builder $q) => $q->where('free_places', '>', 0));
    }

    private function whereCourse(Builder $query, int $course): Builder
    {
        return $query->where(function (Builder $nested) use ($course) {
            $nested->whereIn('level_code', [(string) $course, (string) (10 + $course), $course, 10 + $course])
                ->orWhere('level_name', 'like', $course . '-kurs%')
                ->orWhere('level_name', 'like', $course . ' kurs%');
        });
    }

    private function studentMatchesGroup(Student $student, StudentDistributionGroup $group): bool
    {
        return trim((string) $student->department_name) === trim((string) $group->faculty_name)
            && trim((string) $student->specialty_name) === trim((string) $group->specialty_name)
            && $this->courseNumber($student->level_code, $student->level_name) === (int) $group->course;
    }

    private function groupPayload(StudentDistributionGroup $group): array
    {
        return [
            'id' => $group->id, 'faculty_name' => $group->faculty_name,
            'specialty_name' => $group->specialty_name, 'course' => (int) $group->course,
            'group_name' => $group->group_name, 'group_hemis_id' => $group->group_hemis_id,
            'capacity' => (int) $group->capacity, 'occupied_count' => (int) $group->occupied_count,
            'free_places' => (int) $group->free_places,
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

    private function parseGroupRow(array $values, array $mapping): ?array
    {
        $value = fn (string $key): string => $this->cellText($values[$mapping[$key]] ?? '');
        $faculty = $value('faculty_name'); $specialty = $value('specialty_name');
        $groupName = $value('group_name'); $course = $this->extractInteger($value('course'));
        $capacity = $this->extractInteger($value('capacity')); $freePlaces = $this->extractInteger($value('free_places'));
        $occupied = $this->extractInteger($value('occupied_count'));

        if ($faculty === '' || $specialty === '' || $groupName === '' || !$course) return null;
        if ($capacity === null && $freePlaces !== null) $capacity = $freePlaces + ($occupied ?? 0);
        if ($capacity === null || $capacity < 0) return null;
        if ($freePlaces === null) $freePlaces = max(0, $capacity - ($occupied ?? 0));
        if ($occupied === null) $occupied = max(0, $capacity - $freePlaces);

        return [
            'faculty_name' => $faculty, 'specialty_name' => $specialty, 'course' => $course,
            'group_name' => $groupName, 'group_hemis_id' => $value('group_hemis_id') ?: null,
            'capacity' => $capacity, 'occupied_count' => max(0, min($capacity, $occupied)),
            'free_places' => max(0, min($capacity, $freePlaces)),
            'created_at' => now(), 'updated_at' => now(),
        ];
    }

    private function mapHeaders(array $headers): array
    {
        $aliases = [
            'faculty_name' => ['fakultet', 'faculty', 'faculty name'],
            'specialty_name' => ['yonalish', 'yonalish nomi', 'mutaxassislik', 'specialty', 'direction'],
            'course' => ['kurs', 'course'],
            'group_name' => ['guruh', 'guruh nomi', 'group', 'group name'],
            'group_hemis_id' => ['guruh id', 'group id', 'group hemis id', 'hemis group'],
            'capacity' => ['sigim', 'sigimi', 'jami joy', 'capacity', 'total places', 'jami'],
            'occupied_count' => ['band', 'band joy', 'occupied', 'students', 'talabalar soni'],
            'free_places' => ['bosh joy', 'bosh urin', 'bosh orin', 'free', 'available', 'qoldiq'],
        ];
        $normalized = array_map(fn ($header) => $this->normalizeHeader($header), $headers);
        $mapping = [];
        foreach ($aliases as $key => $headerAliases) {
            $mapping[$key] = null;
            foreach ($normalized as $index => $header) {
                foreach ($headerAliases as $alias) {
                    $normalizedAlias = $this->normalizeHeader($alias);
                    if ($header === $normalizedAlias || str_contains($header, $normalizedAlias)) {
                        $mapping[$key] = $index;
                        break 2;
                    }
                }
            }
        }
        return $mapping;
    }

    private function normalizeHeader(string $value): string
    {
        $value = str_replace(["'", '"', '?', '?'], '', mb_strtolower(trim($value)));
        return trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
    }

    private function extractInteger(string $value): ?int
    {
        if ($value === '') return null;
        return preg_match('/-?\d+/', str_replace(',', '.', $value), $match) ? (int) $match[0] : null;
    }

    private function courseNumber($levelCode, $levelName): ?int
    {
        $code = (int) $levelCode;
        if ($code >= 11 && $code <= 16) return $code - 10;
        if ($code >= 1 && $code <= 6) return $code;
        if (preg_match('/([1-6])\s*[- ]?\s*kurs/i', (string) $levelName, $match)) return (int) $match[1];
        return null;
    }

    private function numericGroupId($value): ?int
    {
        return ctype_digit((string) $value) ? (int) $value : null;
    }

    private function normalizeRow(array $row, int $columnCount, bool $header = false): array
    {
        $values = array_values($row);
        $normalized = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $value = $this->cellText($values[$index] ?? '');
            $normalized[] = $value !== '' || !$header ? $value : 'Ustun ' . ($index + 1);
        }
        return $normalized;
    }

    private function cellText($value): string
    {
        if ($value instanceof \DateTimeInterface) return $value->format('d.m.Y H:i');
        return trim((string) $value);
    }
}

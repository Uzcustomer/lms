<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DistributionGroupStudentsExport;
use App\Http\Controllers\Controller;
use App\Models\DistributionDraftAssignment;
use App\Models\DistributionGroupCapacity;
use App\Models\DistributionSourceGroup;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Talabalarni taqsimlash — registrator ofisi.
 *
 * Sahifa ikki tomondan iborat:
 *   chapda  — "Bo'sh guruhlarni to'ldirish" (barcha guruhlar ro'yxati)
 *   o'ngda  — "Taqsimlanadigan guruhlar" (checkbox bilan belgilanadi)
 *
 * Guruhlar `students` jadvalidan yig'iladi: kurs (level_code) faqat talabada
 * bor, `groups` jadvalida yo'q. Shu sababli fakultet, yo'nalish, kurs va
 * biriktirilgan talabalar soni bir manbadan olinadi va bir-biriga mos keladi.
 */
class StudentDistributionController extends Controller
{
    public function index()
    {
        $groups = $this->groupCatalog();

        return view('admin.student-distribution.index', [
            'groupPayloads' => $groups->values(),
            'faculties' => $groups->pluck('faculty_name')->filter()->unique()->sort()->values(),
            'specialties' => $groups->pluck('specialty_name')->filter()->unique()->sort()->values(),
            'courses' => $groups->pluck('course')->filter()->unique()->sort()->values(),
        ]);
    }

    /** Guruhlar ro'yxatini JSON ko'rinishida qaytaradi (yangilash tugmasi uchun). */
    public function groups(): JsonResponse
    {
        return response()->json(['groups' => $this->groupCatalog()->values()]);
    }

    /** Bitta guruhdagi talabalar — qatorga bosilganda modalda ko'rsatiladi. */
    public function groupStudents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_hemis_id' => ['required', 'integer'],
        ]);

        $groupId = (int) $data['group_hemis_id'];
        $group = $this->groupCatalog()->firstWhere('group_hemis_id', $groupId);

        if (!$group) {
            return response()->json(['message' => 'Guruh ro\'yxatda topilmadi.'], 404);
        }

        $drafts = Schema::hasTable('distribution_draft_assignments')
            ? DistributionDraftAssignment::query()->get()->keyBy('student_id')
            : collect();

        // Guruhning asl talabalari — rejaga ko'ra ketganlari ham ko'rinadi,
        // qayerga ko'chirilgani bilan birga.
        $students = Student::query()
            ->where('student_status_code', 11)
            ->where('group_id', $groupId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_id_number'])
            ->map(function ($student) use ($drafts) {
                $draft = $drafts->get($student->id);

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_id_number' => (string) $student->student_id_number,
                    'moved_to' => $draft ? $draft->to_group_name : null,
                    'moved_to_id' => $draft ? (int) $draft->to_group_hemis_id : null,
                ];
            })
            ->values();

        return response()->json([
            'group' => $group,
            'students' => $students,
        ]);
    }

    /**
     * Talabani boshqa guruhga ko'chirish rejasi.
     *
     * LMS dagi students.group_id o'zgartirilmaydi — bu faqat reja. Maqsadli
     * guruh talabaning fakulteti, yo'nalishi va kursiga mos bo'lishi hamda
     * bo'sh joyi qolgan bo'lishi shart.
     */
    public function assignStudent(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_draft_assignments'),
            503,
            'Taqsimot rejasi jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'to_group_hemis_id' => ['required', 'integer'],
        ]);

        $student = Student::query()
            ->where('student_status_code', 11)
            ->find($data['student_id']);

        if (!$student) {
            return response()->json(['message' => 'Talaba topilmadi.'], 404);
        }

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        $target = $catalog->get((int) $data['to_group_hemis_id']);

        if (!$target) {
            return response()->json(['message' => 'Maqsadli guruh ro\'yxatda topilmadi.'], 404);
        }

        $existing = DistributionDraftAssignment::query()->where('student_id', $student->id)->first();
        $fromGroupId = $existing ? (int) $existing->from_group_hemis_id : (int) $student->group_id;
        $source = $catalog->get($fromGroupId);

        if ($fromGroupId === (int) $target['group_hemis_id']) {
            return response()->json(['message' => 'Talaba allaqachon shu guruhda.'], 422);
        }

        if (!$this->groupsCompatible($source, $target)) {
            return response()->json([
                'message' => 'Maqsadli guruh talabaning fakulteti, yo\'nalishi yoki kursiga mos emas.',
            ], 422);
        }

        if ($target['free_places'] === null || $target['free_places'] < 1) {
            return response()->json(['message' => 'Tanlangan guruhda bo\'sh joy qolmagan.'], 422);
        }

        DistributionDraftAssignment::updateOrCreate(
            ['student_id' => $student->id],
            [
                'from_group_hemis_id' => $fromGroupId,
                'to_group_hemis_id' => (int) $target['group_hemis_id'],
                'student_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'from_group_name' => $source['group_name'] ?? $student->group_name,
                'to_group_name' => $target['group_name'],
                'assigned_by' => Auth::id(),
            ]
        );

        return response()->json([
            'message' => $student->full_name . ' — ' . $target['group_name'] . ' guruhiga rejalashtirildi.',
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /** Rejalashtirilgan ko'chirishni bekor qiladi. */
    public function unassignStudent(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_draft_assignments'),
            503,
            'Taqsimot rejasi jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate(['student_id' => ['required', 'integer']]);

        DistributionDraftAssignment::query()->where('student_id', $data['student_id'])->delete();

        return response()->json([
            'message' => 'Reja bekor qilindi.',
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /**
     * Talabani qabul qila oladigan guruhlar: bir xil fakultet, yo'nalish va
     * kurs, bo'sh joyi bor, o'zi emas va taqsimlanadigan guruh emas.
     */
    public function targetGroups(Request $request): JsonResponse
    {
        $data = $request->validate(['group_hemis_id' => ['required', 'integer']]);

        $catalog = $this->groupCatalog();
        $source = $catalog->firstWhere('group_hemis_id', (int) $data['group_hemis_id']);

        if (!$source) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $targets = $catalog
            ->filter(fn ($group) => $group['group_hemis_id'] !== $source['group_hemis_id']
                && !$group['is_source']
                && $group['free_places'] !== null
                && $group['free_places'] > 0
                && $this->groupsCompatible($source, $group))
            ->values();

        return response()->json(['groups' => $targets]);
    }

    private function groupsCompatible(?array $source, array $target): bool
    {
        if (!$source) {
            return false;
        }

        return $source['faculty_name'] === $target['faculty_name']
            && $source['specialty_name'] === $target['specialty_name']
            && $source['course'] === $target['course'];
    }

    /** O'ng tomondagi checkboxlar holatini saqlaydi. */
    public function storeSourceGroups(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_source_groups'),
            503,
            'Taqsimlanadigan guruhlar jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate([
            'group_hemis_ids' => ['present', 'array', 'max:5000'],
            'group_hemis_ids.*' => ['required', 'integer'],
        ]);

        $selected = collect($data['group_hemis_ids'])->map(fn ($id) => (int) $id)->unique();
        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');

        $unknown = $selected->first(fn (int $id) => !$catalog->has($id));
        if ($unknown !== null) {
            return response()->json(['message' => 'Tanlangan guruhlardan biri ro\'yxatda topilmadi.'], 422);
        }

        DB::transaction(function () use ($selected, $catalog) {
            DistributionSourceGroup::query()
                ->whereNotIn('group_hemis_id', $selected->all())
                ->delete();

            foreach ($selected as $id) {
                $group = $catalog->get($id);

                DistributionSourceGroup::updateOrCreate(
                    ['group_hemis_id' => $id],
                    [
                        'group_name' => $group['group_name'],
                        'faculty_name' => $group['faculty_name'],
                        'specialty_name' => $group['specialty_name'],
                        'level_code' => $group['level_code'],
                        'student_count' => $group['student_count'],
                        'selected_by' => Auth::id(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => $selected->count() . ' ta guruh taqsimlanadigan sifatida belgilandi.',
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /**
     * Bitta guruhning sig'imini qo'lda o'zgartiradi.
     *
     * Standartga teng qiymat yuborilsa yozuv o'chiriladi — shunda guruh yana
     * kurs bo'yicha standart sig'imga qaytadi va keyinchalik standart
     * o'zgartirilsa avtomatik ergashadi.
     */
    public function updateCapacity(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_group_capacities'),
            503,
            'Sig\'im jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate([
            'group_hemis_id' => ['required', 'integer'],
            'capacity' => ['required', 'integer', 'min:0', 'max:200'],
        ]);

        $groupId = (int) $data['group_hemis_id'];
        $group = $this->groupCatalog()->firstWhere('group_hemis_id', $groupId);

        if (!$group) {
            return response()->json(['message' => 'Guruh ro\'yxatda topilmadi.'], 422);
        }

        $capacity = (int) $data['capacity'];
        $default = DistributionGroupCapacity::defaultFor($group['course']);

        if ($default !== null && $capacity === $default) {
            DistributionGroupCapacity::query()->where('group_hemis_id', $groupId)->delete();
        } else {
            DistributionGroupCapacity::updateOrCreate(
                ['group_hemis_id' => $groupId],
                ['capacity' => $capacity, 'updated_by' => Auth::id()]
            );
        }

        return response()->json([
            'group' => $this->groupCatalog()->firstWhere('group_hemis_id', $groupId),
        ]);
    }

    /**
     * Filtrga mos guruhlar va ulardagi talabalarni Excelga chiqaradi.
     *
     * Filtrlar sahifadagi panel bilan bir xil ishlaydi, shuning uchun
     * o'qituvchi ekranda nimani ko'rsa, faylda ham o'sha chiqadi.
     */
    public function exportStudents(Request $request)
    {
        $filters = $request->validate([
            'faculty' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:255'],
            'only_sources' => ['nullable', 'boolean'],
            'side' => ['nullable', 'string', 'in:left,right'],
            'mode' => ['nullable', 'string', 'in:old,new,both'],
        ]);

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        $groups = $this->groupCatalog()
            ->when(!empty($filters['faculty']), fn ($rows) => $rows->where('faculty_name', $filters['faculty']))
            ->when(!empty($filters['specialty']), fn ($rows) => $rows->where('specialty_name', $filters['specialty']))
            ->when(!empty($filters['course']), fn ($rows) => $rows->where('course', (int) $filters['course']))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn ($row) => str_contains(mb_strtolower((string) $row['group_name']), $search)
            ))
            ->when(!empty($filters['only_sources']), fn ($rows) => $rows->where('is_source', true))
            ->values();

        $heading = ($filters['side'] ?? 'left') === 'right'
            ? 'Taqsimlanadigan guruhlar'
            : 'Bo\'sh guruhlarni to\'ldirish';

        $scope = collect([
            $filters['faculty'] ?? null,
            $filters['specialty'] ?? null,
            !empty($filters['course']) ? $filters['course'] . '-kurs' : null,
        ])->filter()->implode(' · ');

        if ($scope !== '') {
            $heading .= '   (' . $scope . ')';
        }

        $mode = $filters['mode'] ?? 'old';
        $modes = $mode === 'both' ? ['old', 'new'] : [$mode];

        $fileName = 'guruh-talabalari-' . $mode . '-' . now()->format('Y-m-d-Hi') . '.xlsx';

        return (new DistributionGroupStudentsExport($groups, $heading, $modes))->download($fileName);
    }

    /**
     * Guruhlar katalogi: nomi, fakulteti, yo'nalishi, kursi va talabalar soni.
     *
     * Uch shart bo'yicha cheklanadi:
     *  - faqat o'qiyotgan talabalar (student_status_code = 11);
     *  - faqat bakalavr (education_type_name ichida "bakalavr") — loyihaning
     *    boshqa joylarida ham shu usul ishlatiladi, alohida kod yo'q;
     *  - faqat `groups` jadvalida faol deb belgilangan guruhlar.
     */
    private function groupCatalog(): Collection
    {
        $sourceIds = Schema::hasTable('distribution_source_groups')
            ? DistributionSourceGroup::query()->pluck('group_hemis_id')->map(fn ($id) => (int) $id)->flip()
            : collect();

        $activeGroupIds = Group::query()
            ->where('active', true)
            ->pluck('group_hemis_id');

        $overrides = Schema::hasTable('distribution_group_capacities')
            ? DistributionGroupCapacity::query()->pluck('capacity', 'group_hemis_id')
            : collect();

        // Reja bo'yicha kelgan va ketgan talabalar soni. LMS ma'lumoti
        // o'zgarmagani uchun guruh sig'imi shu yerda hisoblab qo'shiladi.
        $incoming = collect();
        $outgoing = collect();
        if (Schema::hasTable('distribution_draft_assignments')) {
            $incoming = DistributionDraftAssignment::query()
                ->selectRaw('to_group_hemis_id, COUNT(*) as total')
                ->groupBy('to_group_hemis_id')
                ->pluck('total', 'to_group_hemis_id');
            $outgoing = DistributionDraftAssignment::query()
                ->selectRaw('from_group_hemis_id, COUNT(*) as total')
                ->groupBy('from_group_hemis_id')
                ->pluck('total', 'from_group_hemis_id');
        }

        return Student::query()
            ->where('student_status_code', 11)
            ->whereRaw('LOWER(education_type_name) LIKE ?', ['%bakalavr%'])
            ->whereIn('group_id', $activeGroupIds)
            ->whereNotNull('group_id')
            ->whereNotNull('group_name')
            ->select([
                'group_id',
                'group_name',
                'department_name',
                'specialty_name',
                'level_code',
                'level_name',
                DB::raw('COUNT(*) as student_count'),
            ])
            ->groupBy('group_id', 'group_name', 'department_name', 'specialty_name', 'level_code', 'level_name')
            ->orderBy('department_name')
            ->orderBy('specialty_name')
            ->orderBy('level_code')
            ->orderBy('group_name')
            ->get()
            ->map(function ($row) use ($sourceIds, $overrides, $incoming, $outgoing) {
                $groupId = (int) $row->group_id;
                $course = $this->toCourse($row->level_code);
                $lmsCount = (int) $row->student_count;

                $movedIn = (int) $incoming->get($groupId, 0);
                $movedOut = (int) $outgoing->get($groupId, 0);
                $students = max(0, $lmsCount + $movedIn - $movedOut);

                $default = DistributionGroupCapacity::defaultFor($course);
                $capacity = $overrides->has($groupId) ? (int) $overrides->get($groupId) : $default;

                return [
                    'group_hemis_id' => $groupId,
                    'group_name' => $row->group_name,
                    'faculty_name' => $row->department_name,
                    'specialty_name' => $row->specialty_name,
                    'level_code' => (string) $row->level_code,
                    'course' => $course,
                    'level_name' => $row->level_name,
                    'lms_student_count' => $lmsCount,
                    'student_count' => $students,
                    'moved_in' => $movedIn,
                    'moved_out' => $movedOut,
                    'capacity' => $capacity,
                    'is_custom_capacity' => $overrides->has($groupId),
                    // Musbat — bo'sh joy, manfiy — ortiqcha talaba.
                    'free_places' => $capacity === null ? null : $capacity - $students,
                    'is_source' => $sourceIds->has($groupId),
                ];
            });
    }

    /**
     * HEMIS level_code ni kurs raqamiga aylantiradi.
     *
     * HEMIS odatda 11..16 beradi (11 = 1-kurs ... 16 = 6-kurs), lekin toza
     * 1..8 ko'rinishi ham uchraydi — StudentController dagi bilan bir xil
     * mantiq, ikkala ko'rinish ham qo'llab-quvvatlanadi.
     */
    private function toCourse($raw): ?int
    {
        $number = (int) $raw;

        if ($number >= 11 && $number <= 20) {
            $number -= 10;
        }

        return $number >= 1 && $number <= 8 ? $number : null;
    }
}

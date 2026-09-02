<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DistributionGroupStudentsExport;
use App\Http\Controllers\Controller;
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

        $fileName = 'guruh-talabalari-' . now()->format('Y-m-d-Hi') . '.xlsx';

        return (new DistributionGroupStudentsExport($groups, $heading))->download($fileName);
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
            ->map(fn ($row) => [
                'group_hemis_id' => (int) $row->group_id,
                'group_name' => $row->group_name,
                'faculty_name' => $row->department_name,
                'specialty_name' => $row->specialty_name,
                'level_code' => (string) $row->level_code,
                'course' => $this->toCourse($row->level_code),
                'level_name' => $row->level_name,
                'student_count' => (int) $row->student_count,
                'is_source' => $sourceIds->has((int) $row->group_id),
            ]);
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

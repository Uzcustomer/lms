<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistributionSourceGroup;
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
            'courses' => $groups->pluck('level_code')->filter()->unique()->sort()->values(),
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
     * Guruhlar katalogi: nomi, fakulteti, yo'nalishi, kursi va talabalar soni.
     *
     * Faqat o'qiyotgan (student_status_code = 11) talabalar hisobga olinadi —
     * chetlashtirilgan yoki bitirgan talabalar guruh sig'imiga kirmasligi kerak.
     */
    private function groupCatalog(): Collection
    {
        $sourceIds = Schema::hasTable('distribution_source_groups')
            ? DistributionSourceGroup::query()->pluck('group_hemis_id')->map(fn ($id) => (int) $id)->flip()
            : collect();

        return Student::query()
            ->where('student_status_code', 11)
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
                'level_name' => $row->level_name,
                'student_count' => (int) $row->student_count,
                'is_source' => $sourceIds->has((int) $row->group_id),
            ]);
    }
}

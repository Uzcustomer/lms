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
 * Ta'lim tili esa `groups` jadvalidan (HEMIS dagi educationLang) olinadi.
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
                    'full_group_mode' => $draft ? (bool) $draft->full_group_mode : false,
                ];
            })
            ->values();

        return response()->json([
            'group' => $group,
            'students' => $students,
        ]);
    }

    /**
     * Talaba ismi bo'yicha qidiruv — o'ng paneldagi filtr uchun.
     * Har bir natijada talabaning guruhi ham qaytariladi.
     */
    public function searchStudents(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        $drafts = Schema::hasTable('distribution_draft_assignments')
            ? DistributionDraftAssignment::query()->get()->keyBy('student_id')
            : collect();

        $students = Student::query()
            ->where('student_status_code', 11)
            ->whereIn('group_id', $catalog->keys()->all())
            ->where('full_name', 'like', '%' . trim($data['q']) . '%')
            ->orderBy('full_name')
            ->limit(50)
            ->get(['id', 'group_id', 'full_name', 'student_id_number'])
            ->map(function ($student) use ($catalog, $drafts) {
                $group = $catalog->get((int) $student->group_id);
                $draft = $drafts->get($student->id);

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_id_number' => (string) $student->student_id_number,
                    'group_hemis_id' => (int) $student->group_id,
                    'group_name' => $group['group_name'] ?? '',
                    'moved_to' => $draft ? $draft->to_group_name : null,
                ];
            })
            ->values();

        return response()->json(['students' => $students]);
    }

    /**
     * Talabani boshqa guruhga ko'chirish rejasi.
     *
     * LMS dagi students.group_id o'zgartirilmaydi — bu faqat reja. Maqsadli
     * guruh talabaning yo'nalishi, kursi va ta'lim tiliga mos bo'lishi hamda
     * bo'sh joyi qolgan bo'lishi shart.
     *
     * "To'liq guruh" rejimida (full_group_mode) ikki cheklov yumshaydi:
     * bo'sh joyi yo'q guruhga ham ko'chirish mumkin (guruh "ortiqcha" bo'lib
     * qoladi) va o'zbek/rus guruhidan ingliz guruhiga o'tishga ruxsat beriladi.
     * Boshqa yo'nalish yoki kursga bu rejimda ham o'tib bo'lmaydi.
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
            'full_group_mode' => ['nullable', 'boolean'],
        ]);

        $fullMode = (bool) ($data['full_group_mode'] ?? false);

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

        if (!$this->groupsCompatible($source, $target, $fullMode)) {
            return response()->json([
                'message' => $fullMode
                    ? 'Maqsadli guruh talabaning yo\'nalishi yoki kursiga mos emas, yoki ta\'lim tili boshqa (faqat ingliz guruhiga o\'tish mumkin).'
                    : 'Maqsadli guruh talabaning yo\'nalishi, kursi yoki ta\'lim tiliga mos emas.',
            ], 422);
        }

        if (!$fullMode && ($target['free_places'] === null || $target['free_places'] < 1)) {
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
                'full_group_mode' => $fullMode,
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
     * Talabani qabul qila oladigan guruhlar: bir xil yo'nalish, kurs va
     * ta'lim tili, bo'sh joyi bor, o'zi emas va taqsimlanadigan guruh emas.
     *
     * Fakultet shart emas: bitta yo'nalish bir nechta fakultetga (1-son,
     * 2-son davolash) bo'lingan bo'lishi mumkin, talaba ularning istalgan
     * guruhiga o'tishi mumkin.
     *
     * "To'liq guruh" rejimida (full_group_mode=1) to'la va ortiqcha guruhlar
     * ham chiqadi, ingliz guruhlari esa tilidan qat'i nazar ko'rsatiladi.
     * Taqsimlanadigan guruh bu rejimda ham maqsad bo'la olmaydi — u
     * bo'shatilayotgan guruh.
     */
    public function targetGroups(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_hemis_id' => ['required', 'integer'],
            'full_group_mode' => ['nullable', 'boolean'],
        ]);

        $fullMode = (bool) ($data['full_group_mode'] ?? false);

        $catalog = $this->groupCatalog();
        $source = $catalog->firstWhere('group_hemis_id', (int) $data['group_hemis_id']);

        if (!$source) {
            return response()->json(['message' => 'Guruh topilmadi.'], 404);
        }

        $targets = $catalog
            ->filter(fn ($group) => $group['group_hemis_id'] !== $source['group_hemis_id']
                && !$group['is_source']
                && ($fullMode || ($group['free_places'] !== null && $group['free_places'] > 0))
                && $this->groupsCompatible($source, $group, $fullMode))
            ->values();

        return response()->json(['groups' => $targets]);
    }

    /**
     * Ikki guruh bir-biriga mos keladimi.
     *
     * Yo'nalish va kurs har doim bir xil bo'lishi shart. Ta'lim tili oddiy
     * rejimda bir xil bo'lishi kerak; "to'liq guruh" rejimida esa maqsad
     * ingliz guruhi bo'lsa, o'zbek/rus guruhidan ham o'tish mumkin. Teskari
     * yo'nalish (ingliz → o'zbek/rus) va o'zbek ↔ rus o'tishlariga ruxsat yo'q.
     */
    private function groupsCompatible(?array $source, array $target, bool $fullMode = false): bool
    {
        if (!$source) {
            return false;
        }

        if ($source['specialty_name'] !== $target['specialty_name']
            || $source['course'] !== $target['course']) {
            return false;
        }

        if ($this->languageKey($source) === $this->languageKey($target)) {
            return true;
        }

        return $fullMode && $this->isEnglish($target);
    }

    /**
     * Guruh ingliz tilida o'qiydimi.
     *
     * HEMIS ta'lim tilini ham harfli (en), ham raqamli (14) kod bilan beradi;
     * nomi esa "Ingliz" yoki "English" bo'ladi. Ishonchli bo'lishi uchun
     * avval nom, keyin kod tekshiriladi.
     */
    private function isEnglish(array $group): bool
    {
        $name = mb_strtolower(trim((string) ($group['language_name'] ?? '')));
        if ($name !== '' && (str_contains($name, 'ingliz') || str_contains($name, 'english') || str_contains($name, 'англ'))) {
            return true;
        }

        $code = mb_strtolower(trim((string) ($group['language_code'] ?? '')));

        return in_array($code, ['en', 'eng', 'en-us', '14'], true);
    }

    /**
     * Ta'lim tilini solishtirish uchun kalit.
     *
     * HEMIS kodi (uz, ru, en) bo'lsa — shu, bo'lmasa nomi olinadi. Ikkala
     * guruhda ham til ko'rsatilmagan bo'lsa, bo'sh kalitlar teng chiqadi va
     * til bo'yicha cheklov qo'yilmaydi.
     */
    private function languageKey(array $group): string
    {
        $value = $group['language_code'] ?: ($group['language_name'] ?? '');

        return mb_strtolower(trim((string) $value));
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

        // Faol guruhlar va ularning ta'lim tili — til faqat `groups` da bor.
        $activeGroups = Group::query()
            ->where('active', true)
            ->get(['group_hemis_id', 'education_lang_code', 'education_lang_name'])
            ->keyBy(fn ($group) => (int) $group->group_hemis_id);

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
            ->whereIn('group_id', $activeGroups->keys())
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
            ->map(function ($row) use ($sourceIds, $activeGroups, $overrides, $incoming, $outgoing) {
                $groupId = (int) $row->group_id;
                $course = $this->toCourse($row->level_code);
                $lmsCount = (int) $row->student_count;
                $active = $activeGroups->get($groupId);

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
                    'language_code' => $active?->education_lang_code ?: null,
                    'language_name' => $active?->education_lang_name ?: null,
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

<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DistributionExport;
use App\Http\Controllers\Controller;
use App\Models\DistributionDraftAssignment;
use App\Models\DistributionGroupCapacity;
use App\Models\DistributionSourceGroup;
use App\Models\Group;
use App\Models\DistributionVote;
use App\Models\DistributionVotingGroup;
use App\Models\DistributionVotingStudent;
use App\Models\Student;
use App\Services\DistributionCatalog;
use App\Services\DistributionNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
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
    public function __construct(private DistributionCatalog $catalog)
    {
    }

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

        // Faol ovozlar — rejani bekor qilishda (×) talaba ovoz bergani
        // ogohlantiriladi va ovozi eski deb belgilanadi.
        $voteTo = $this->activeVoteTargets();

        // Guruhning asl talabalari — rejaga ko'ra ketganlari ham ko'rinadi,
        // qayerga ko'chirilgani bilan birga.
        $students = Student::query()
            ->where('student_status_code', 11)
            ->where('group_id', $groupId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'student_id_number'])
            ->map(function ($student) use ($drafts, $groupId, $voteTo) {
                $draft = $drafts->get($student->id);

                // Reja bajarilgan: talaba LMS da allaqachon maqsad guruhda (HEMIS
                // ko'chirishni qo'llagan). U shu guruhning oddiy talabasi — "o'z
                // guruhiga ko'chirilgan" bo'lib ko'rinmasin; qayerdan kelgani
                // belgi sifatida qoladi (done_from).
                $done = $draft && $this->catalog->isDraftApplied($draft, $groupId);

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'student_id_number' => (string) $student->student_id_number,
                    'moved_to' => ($draft && !$done) ? $draft->to_group_name : null,
                    'moved_to_id' => ($draft && !$done) ? (int) $draft->to_group_hemis_id : null,
                    'full_group_mode' => ($draft && !$done) ? (bool) $draft->full_group_mode : false,
                    'done_from' => $done ? $draft->from_group_name : null,
                    'vote_to' => $voteTo->get((int) $student->id),
                ];
            })
            ->values();

        // Rejaga ko'ra shu guruhga kelgan talabalar — chap panelda guruh
        // ochilganda alohida ko'rsatiladi va qaytarish mumkin bo'ladi.
        // Bajarilgan rejalar (talaba allaqachon shu guruhda) chiqarilmaydi —
        // ular yuqoridagi asl talabalar ro'yxatida bor.
        $incoming = collect();
        if (Schema::hasTable('distribution_draft_assignments')) {
            $incoming = $this->catalog->pendingDrafts()
                ->where('distribution_draft_assignments.to_group_hemis_id', $groupId)
                ->orderBy('distribution_draft_assignments.student_name')
                ->get(['distribution_draft_assignments.*'])
                ->map(fn (DistributionDraftAssignment $draft) => [
                    'student_id' => $draft->student_id,
                    'full_name' => $draft->student_name,
                    'student_id_number' => (string) $draft->student_id_number,
                    'from_group_name' => $draft->from_group_name,
                    'vote_to' => $voteTo->get((int) $draft->student_id),
                    'incoming' => true,
                ])
                ->values();
        }

        return response()->json([
            'group' => $group,
            'students' => $students,
            'incoming' => $incoming,
        ]);
    }

    /** HEMIS dan guruhlar ro'yxatini darhol yangilaydi (import:groups). */
    public function syncGroups(): JsonResponse
    {
        $started = microtime(true);
        $exitCode = Artisan::call('import:groups');
        $seconds = round(microtime(true) - $started, 1);

        if ($exitCode !== 0) {
            return response()->json([
                'message' => 'Guruhlarni yangilashda xatolik yuz berdi (kod: ' . $exitCode . ').',
            ], 500);
        }

        return response()->json([
            'message' => 'Guruhlar HEMIS dan yangilandi (' . $seconds . ' s).',
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /**
     * Barcha rejalashtirilgan ko'chirishlarni bekor qiladi — hamma talaba
     * o'z guruhiga qaytadi, guruhlar asl holatiga keladi. LMS ga tegilmaydi.
     */
    public function resetDrafts(): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_draft_assignments'),
            503,
            'Taqsimot rejasi jadvali hali migratsiya qilinmagan.'
        );

        $count = DistributionDraftAssignment::query()->count();
        DistributionDraftAssignment::query()->delete();

        return response()->json([
            'message' => $count . " ta talaba o'z guruhiga qaytarildi. Guruhlar asl holatiga keldi.",
            'groups' => $this->groupCatalog()->values(),
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
                if ($draft && $this->catalog->isDraftApplied($draft, $student->group_id)) {
                    $draft = null;   // HEMISda allaqachon ko'chirilgan
                }

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
     * guruh talabaning yo'nalishi va kursiga mos bo'lishi shart: bularda o'quv
     * rejasi boshqa bo'ladi.
     *
     * Ta'lim tili va fakultet cheklov emas: registrator boshqa tildagi guruhga
     * ham, bir yo'nalishning boshqa "N-son" fakultetiga ham (1-son davolash ↔
     * 2-son davolash) o'tkaza oladi. UI tanlashdan oldin ikkalasini ham
     * ogohlantirib chiqadi. Talaba ovozida esa til ham, fakultet ham bir xil
     * bo'lishi shart.
     *
     * "To'liq guruh" rejimida (full_group_mode) sig'im tekshirilmaydi:
     * bo'sh joyi yo'q guruhga ham ko'chirish mumkin (guruh "ortiqcha" bo'ladi).
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

        // Til farqiga ruxsat: qo'lda ko'chirishda registrator boshqa tildagi
        // guruhga ham o'tkaza oladi (UI tanlashdan oldin ogohlantiradi).
        if (!$this->groupsCompatible($source, $target, true)) {
            return response()->json([
                'message' => 'Maqsadli guruh talabaning yo\'nalishi, kursi yoki o\'quv rejasiga mos emas '
                    . '(fakultet faqat bir yo\'nalishning "N-son" juftlari orasida almashishi mumkin; '
                    . 'Xalqaro ta\'lim fakultetida faqat bir xil o\'quv reja ichida).',
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

    /**
     * Bir nechta talabani bitta guruhga birdaniga ko'chirish rejasi.
     *
     * Qoidalar yakka ko'chirish bilan bir xil. Avval hamma talaba tekshiriladi,
     * bittasi ham o'tmasa — hech kim ko'chirilmaydi va xatolar ro'yxati
     * qaytadi. Oddiy rejimda guruhda hamma uchun joy yetishi shart; "to'liq
     * guruh" rejimida sig'im tekshirilmaydi.
     */
    public function assignStudents(Request $request): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_draft_assignments'),
            503,
            'Taqsimot rejasi jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer'],
            'to_group_hemis_id' => ['required', 'integer'],
            'full_group_mode' => ['nullable', 'boolean'],
        ]);

        $fullMode = (bool) ($data['full_group_mode'] ?? false);
        $ids = collect($data['student_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        $target = $catalog->get((int) $data['to_group_hemis_id']);

        if (!$target) {
            return response()->json(['message' => 'Maqsadli guruh ro\'yxatda topilmadi.'], 404);
        }

        $students = Student::query()
            ->where('student_status_code', 11)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $existing = DistributionDraftAssignment::query()
            ->whereIn('student_id', $ids)
            ->get()
            ->keyBy('student_id');

        $errors = [];
        $plans = [];

        foreach ($ids as $id) {
            $student = $students->get($id);
            if (!$student) {
                $errors[] = 'ID ' . $id . ': talaba topilmadi.';
                continue;
            }

            $draft = $existing->get($id);
            $fromGroupId = $draft ? (int) $draft->from_group_hemis_id : (int) $student->group_id;
            $source = $catalog->get($fromGroupId);

            if ($fromGroupId === (int) $target['group_hemis_id']) {
                $errors[] = $student->full_name . ': allaqachon shu guruhda.';
                continue;
            }

            if (!$this->groupsCompatible($source, $target, true)) {
                $errors[] = $student->full_name . ': yo\'nalishi, kursi yoki fakulteti mos emas.';
                continue;
            }

            $plans[] = [
                'student' => $student,
                'from_group_hemis_id' => $fromGroupId,
                'from_group_name' => $source['group_name'] ?? $student->group_name,
            ];
        }

        if ($errors) {
            return response()->json([
                'message' => "Ko'chirib bo'lmadi:\n" . implode("\n", $errors),
            ], 422);
        }

        // Rejadagi joyi allaqachon shu guruhda bo'lgan talaba joy egallamaydi —
        // faqat yangi kelayotganlar hisoblanadi.
        $newcomers = collect($plans)->filter(function ($plan) use ($existing, $target) {
            $draft = $existing->get($plan['student']->id);

            return !$draft || (int) $draft->to_group_hemis_id !== (int) $target['group_hemis_id'];
        })->count();

        if (!$fullMode) {
            $free = $target['free_places'];
            if ($free === null || $free < $newcomers) {
                return response()->json([
                    'message' => $target['group_name'] . ' guruhida ' . ($free === null ? 'sig\'im belgilanmagan' : 'faqat ' . max(0, $free) . ' ta bo\'sh joy bor')
                        . ', ' . $newcomers . ' ta talaba tanlangan.',
                ], 422);
            }
        }

        DB::transaction(function () use ($plans, $target, $fullMode) {
            foreach ($plans as $plan) {
                DistributionDraftAssignment::updateOrCreate(
                    ['student_id' => $plan['student']->id],
                    [
                        'from_group_hemis_id' => $plan['from_group_hemis_id'],
                        'to_group_hemis_id' => (int) $target['group_hemis_id'],
                        'student_name' => $plan['student']->full_name,
                        'student_id_number' => $plan['student']->student_id_number,
                        'from_group_name' => $plan['from_group_name'],
                        'to_group_name' => $target['group_name'],
                        'full_group_mode' => $fullMode,
                        'assigned_by' => Auth::id(),
                    ]
                );
            }
        });

        return response()->json([
            'message' => count($plans) . ' ta talaba ' . $target['group_name'] . ' guruhiga rejalashtirildi.',
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

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            // Talaba ovoz bergan bo'lsa — ovozi eski deb belgilanadi va u
            // qaytadan ovoz bera oladi (ovoz berish ochiq bo'lsa).
            'archive_vote' => ['nullable', 'boolean'],
        ]);

        $archived = 0;

        DB::transaction(function () use ($data, &$archived) {
            DistributionDraftAssignment::query()->where('student_id', $data['student_id'])->delete();

            if (!empty($data['archive_vote'])) {
                $archived = $this->archiveVotes(collect([(int) $data['student_id']]));
            }
        });

        return response()->json([
            'message' => 'Reja bekor qilindi.' . ($archived ? ' Ovozi eski deb belgilandi.' : ''),
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

        // Registrator ro'yxatida boshqa tildagi guruhlar ham ko'rinadi — ular
        // "To'liq guruh" rejimidan qat'i nazar tanlanishi mumkin, UI esa
        // tanlashdan oldin til farqini ogohlantirib chiqadi.
        $targets = $catalog
            ->filter(fn ($group) => $group['group_hemis_id'] !== $source['group_hemis_id']
                && !$group['is_source']
                && ($fullMode || ($group['free_places'] !== null && $group['free_places'] > 0))
                && $this->groupsCompatible($source, $group, true))
            ->values();

        return response()->json(['groups' => $targets]);
    }

    private function groupsCompatible(?array $source, array $target, bool $fullMode = false): bool
    {
        return $this->catalog->compatible($source, $target, $fullMode);
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

    /** Tanlangan guruhlar talabalariga ovoz berishni ochadi. */
    public function openVoting(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('distribution_voting_groups'), 503, 'Ovoz berish jadvali hali migratsiya qilinmagan.');

        $data = $request->validate([
            'group_hemis_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'group_hemis_ids.*' => ['required', 'integer'],
        ]);

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        $openedIds = [];

        foreach (collect($data['group_hemis_ids'])->unique() as $id) {
            $group = $catalog->get((int) $id);
            if (!$group) {
                continue;
            }

            DistributionVotingGroup::updateOrCreate(
                ['group_hemis_id' => (int) $id],
                ['group_name' => $group['group_name'], 'opened_by' => Auth::id()]
            );
            $openedIds[] = (int) $id;
        }

        // Rejasi bekor qilingan, lekin ovozi faol qolgan talabalar qaytadan
        // ovoz bera olishi uchun ularning ovozi eski deb belgilanadi.
        $archived = $openedIds
            ? $this->archiveStaleVotes(
                Student::query()
                    ->where('student_status_code', 11)
                    ->whereIn('group_id', $openedIds)
                    ->pluck('id')
            )
            : 0;

        return response()->json([
            'message' => count($openedIds) . ' ta guruh talabalariga ovoz berish ochildi.'
                . ($archived ? ' ' . $archived . " ta talabaning rejasi bekor qilingan ovozi eski deb belgilandi — ular qaytadan ovoz bera oladi." : ''),
            'voting_open_count' => DistributionVotingGroup::query()->count(),
        ]);
    }

    /** Tanlangan talabalarga ovoz berishni ochadi (modal ichidan). */
    public function openVotingStudents(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('distribution_voting_students'), 503, 'Ovoz berish jadvali hali migratsiya qilinmagan.');

        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'student_ids.*' => ['required', 'integer'],
        ]);

        $students = Student::query()
            ->where('student_status_code', 11)
            ->whereIn('id', collect($data['student_ids'])->unique())
            ->get(['id', 'group_id']);

        // Qo'lda ko'chirilgan talabaning guruhi hal qilingan — unga ovoz
        // berish ochilmaydi, aks holda ovozi rejani buzib yuborardi.
        $assigned = $this->assignedStudentIds($students->pluck('id'));
        $openedIds = collect();

        foreach ($students as $student) {
            if ($assigned->has((int) $student->id)) {
                continue;
            }

            DistributionVotingStudent::updateOrCreate(
                ['student_id' => $student->id],
                ['group_hemis_id' => (int) $student->group_id, 'opened_by' => Auth::id()]
            );
            $openedIds->push((int) $student->id);
        }

        $opened = $openedIds->count();
        $skipped = $students->count() - $opened;
        $archived = $this->archiveStaleVotes($openedIds);

        return response()->json([
            'message' => $opened . ' ta talabaga ovoz berish ochildi.'
                . ($archived ? ' ' . $archived . " tasining avvalgi ovozi eski deb belgilandi." : '')
                . ($skipped ? ' ' . $skipped . " ta talaba qo'lda ko'chirilgani uchun o'tkazib yuborildi." : ''),
            'voting_student_count' => DistributionVotingStudent::query()->count(),
        ]);
    }

    /** Ovoz berishni butunlay yopadi (barcha guruhlar uchun). */
    /**
     * Ovoz berishni yopadi.
     *
     * Guruh yoki talaba ro'yxati berilsa — faqat o'shalar yopiladi; ikkalasi
     * ham bo'sh kelsa hammasi yopiladi. Berilgan ovozlarga tegilmaydi: yopish
     * shundan keyin ovoz berishni to'xtatadi, avvalgi tanlovni bekor qilmaydi.
     */
    public function closeVoting(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('distribution_voting_groups'), 503, 'Ovoz berish jadvali hali migratsiya qilinmagan.');

        $data = $request->validate([
            'group_hemis_ids' => ['nullable', 'array', 'max:5000'],
            'group_hemis_ids.*' => ['required', 'integer'],
            'student_ids' => ['nullable', 'array', 'max:5000'],
            'student_ids.*' => ['required', 'integer'],
        ]);

        $groupIds = collect($data['group_hemis_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();
        $hasStudents = Schema::hasTable('distribution_voting_students');

        if ($groupIds->isEmpty() && $studentIds->isEmpty()) {
            DistributionVotingGroup::query()->delete();
            if ($hasStudents) {
                DistributionVotingStudent::query()->delete();
            }
            $message = 'Ovoz berish hammaga yopildi.';
        } else {
            $closedGroups = 0;
            $closedStudents = 0;

            if ($groupIds->isNotEmpty()) {
                $closedGroups = DistributionVotingGroup::query()
                    ->whereIn('group_hemis_id', $groupIds->all())->delete();

                // Guruh yopilganda unga tegishli yakka ruxsatlar ham ketadi,
                // aks holda guruh yopiq ko'rinib, talabada popup qolib ketardi.
                if ($hasStudents) {
                    $closedStudents += DistributionVotingStudent::query()
                        ->whereIn('group_hemis_id', $groupIds->all())->delete();
                }
            }

            if ($studentIds->isNotEmpty() && $hasStudents) {
                $closedStudents += DistributionVotingStudent::query()
                    ->whereIn('student_id', $studentIds->all())->delete();
            }

            $parts = [];
            if ($closedGroups) {
                $parts[] = $closedGroups . ' ta guruh';
            }
            if ($closedStudents) {
                $parts[] = $closedStudents . ' ta talaba';
            }
            $message = $parts
                ? 'Ovoz berish yopildi: ' . implode(' · ', $parts) . '.'
                : 'Yopiladigan ochiq ovoz topilmadi.';
        }

        return response()->json([
            'message' => $message,
            'voting_open_count' => DistributionVotingGroup::query()->count(),
            'voting_student_count' => $hasStudents ? DistributionVotingStudent::query()->count() : 0,
        ]);
    }

    /**
     * Ovoz berish ochiq guruhlar va yakka talabalar ro'yxati — yopishda
     * tanlash uchun. Har qatorda nechta talaba hali ovoz bermagani ko'rinadi.
     */
    public function openVotings(): JsonResponse
    {
        if (!Schema::hasTable('distribution_voting_groups')) {
            return response()->json(['groups' => [], 'students' => []]);
        }

        $voted = Schema::hasTable('distribution_votes')
            ? DistributionVote::query()->active()->pluck('student_id')->map(fn ($id) => (int) $id)->flip()
            : collect();

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');

        // Ochiq guruhlardagi talabalar — qaysi biri ovoz berganini sanash uchun.
        $openGroups = DistributionVotingGroup::query()->orderBy('group_name')->get();
        $memberIds = $openGroups->isNotEmpty()
            ? Student::query()
                ->where('student_status_code', 11)
                ->whereIn('group_id', $openGroups->pluck('group_hemis_id')->all())
                ->get(['id', 'group_id'])
                ->groupBy(fn ($student) => (int) $student->group_id)
            : collect();

        // Qo'lda ko'chirilganlar ovoz bermaydi — ular ham "hal bo'lgan" hisobga
        // kiradi, aks holda qator hech qachon to'liq ko'rinmasdi.
        $assigned = $this->assignedStudentIds($memberIds->flatten()->pluck('id'));

        $groups = $openGroups->map(function (DistributionVotingGroup $row) use ($catalog, $memberIds, $voted, $assigned) {
            $groupId = (int) $row->group_hemis_id;
            $members = $memberIds->get($groupId, collect());
            $group = $catalog->get($groupId);

            return [
                'group_hemis_id' => $groupId,
                'group_name' => $row->group_name ?: ($group['group_name'] ?? ('#' . $groupId)),
                'faculty_name' => $group['faculty_name'] ?? '',
                'specialty_name' => $group['specialty_name'] ?? '',
                'course' => $group['course'] ?? null,
                'student_count' => $members->count(),
                'voted_count' => $members->filter(fn ($student) => $voted->has((int) $student->id)
                    || $assigned->has((int) $student->id))->count(),
            ];
        })->values();

        $students = collect();
        if (Schema::hasTable('distribution_voting_students')) {
            $openStudents = DistributionVotingStudent::query()->get();
            $names = $openStudents->isNotEmpty()
                ? Student::query()->whereIn('id', $openStudents->pluck('student_id')->all())
                    ->get(['id', 'full_name', 'student_id_number', 'group_name'])->keyBy('id')
                : collect();

            $students = $openStudents
                ->map(function (DistributionVotingStudent $row) use ($names, $voted) {
                    $student = $names->get($row->student_id);

                    return [
                        'student_id' => (int) $row->student_id,
                        'full_name' => $student->full_name ?? ('#' . $row->student_id),
                        'student_id_number' => (string) ($student->student_id_number ?? ''),
                        'group_name' => $student->group_name ?? '',
                        'has_voted' => $voted->has((int) $row->student_id),
                    ];
                })
                ->sortBy('full_name')
                ->values();
        }

        return response()->json(['groups' => $groups, 'students' => $students]);
    }

    /** Berilgan ovozlar ro'yxati va ochiq guruhlar soni. */
    public function votes(): JsonResponse
    {
        if (!Schema::hasTable('distribution_votes')) {
            return response()->json(['votes' => [], 'voting_open_count' => 0]);
        }

        $query = DistributionVote::query();
        if (DistributionVote::supportsArchive()) {
            // Eski ovozlar ro'yxat oxirida, alohida bo'limda.
            $query->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END');
        }
        $all = $query
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->get();

        // Ovozdagi nomlar ovoz berilgan paytdagi nomlar. Keyin guruh HEMISda qayta
        // nomlangan, talaba HEMISda ko'chirilgan yoki registrator rejani o'zgartirgan
        // bo'lishi mumkin — joriy holatni yoniga qo'shib beramiz.
        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        $studentIds = $all->pluck('student_id')->filter()->unique()->values();
        $current = $studentIds->isEmpty()
            ? collect()
            : Student::query()->whereIn('id', $studentIds->all())->get(['id', 'group_id', 'group_name'])->keyBy('id');
        $drafts = Schema::hasTable('distribution_draft_assignments') && $studentIds->isNotEmpty()
            ? DistributionDraftAssignment::query()->whereIn('student_id', $studentIds->all())->get()->keyBy('student_id')
            : collect();

        $votes = $all->map(function (DistributionVote $vote) use ($catalog, $current, $drafts) {
                $st = $current->get((int) $vote->student_id);
                $draft = $drafts->get((int) $vote->student_id);
                $toNow = $catalog->get((int) $vote->to_group_hemis_id);
                $curGroupId = $st ? (int) $st->group_id : 0;
                $curGroupName = $st ? (($catalog->get($curGroupId)['group_name'] ?? null) ?: $st->group_name) : null;

                // holat: done — HEMISda bajarilgan; replaced — reja boshqa guruhga o'zgartirilgan;
                // moved — talaba HEMISda boshqa guruhga o'tgan; pending — reja kutmoqda
                // archived — reja bekor qilingan, ovoz eski deb belgilangan (talaba
                // qaytadan ovoz bera oladi); qolgan holatlar faqat faol ovozga tegishli.
                $archived = $vote->isArchived();
                $state = 'pending';
                if ($archived) {
                    $state = 'archived';
                } elseif ($curGroupId > 0 && $curGroupId === (int) $vote->to_group_hemis_id) {
                    $state = 'done';
                } elseif ($draft && (int) $draft->to_group_hemis_id !== (int) $vote->to_group_hemis_id) {
                    $state = 'replaced';
                } elseif ($curGroupId > 0 && $curGroupId !== (int) $vote->from_group_hemis_id) {
                    $state = 'moved';
                }

                return [
                    'id' => $vote->id,
                    'student_id' => $vote->student_id,
                    'student_name' => $vote->student_name,
                    'student_id_number' => $vote->student_id_number,
                    'from_group_name' => $vote->from_group_name,
                    'to_group_name' => $vote->to_group_name,
                    'to_group_hemis_id' => $vote->to_group_hemis_id,
                    // maqsadli guruhning hozirgi nomi (HEMISda qayta nomlangan bo'lsa farq qiladi)
                    'to_group_now' => $toNow['group_name'] ?? null,
                    'current_group_name' => $curGroupName,
                    'draft_to_group_name' => ($draft && !$archived) ? $draft->to_group_name : null,
                    'state' => $state,
                    'status' => $vote->status,
                    'archived' => $archived,
                    'archived_at' => $archived ? $vote->archived_at->format('d.m.Y H:i') : null,
                    'voted_at' => optional($vote->created_at)->format('d.m.Y H:i'),
                ];
            })
            ->values();

        return response()->json([
            'votes' => $votes,
            'voting_open_count' => Schema::hasTable('distribution_voting_groups')
                ? DistributionVotingGroup::query()->count()
                : 0,
            'voting_student_count' => Schema::hasTable('distribution_voting_students')
                ? DistributionVotingStudent::query()->count()
                : 0,
        ]);
    }

    /**
     * Tanlangan ovozlarni o'chiradi (test va tozalash uchun).
     * Tasdiqlangan ovoz o'chirilsa, unga mos reja (draft) ham bekor bo'ladi
     * va band qilingan joy qaytadi.
     */
    public function deleteVotes(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('distribution_votes'), 503, 'Ovozlar jadvali hali migratsiya qilinmagan.');

        $data = $request->validate([
            'vote_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'vote_ids.*' => ['required', 'integer'],
        ]);

        $deleted = 0;

        foreach (collect($data['vote_ids'])->unique() as $id) {
            $vote = DistributionVote::query()->find((int) $id);
            if (!$vote) {
                continue;
            }

            DB::transaction(function () use ($vote) {
                // Eski ovozning rejasi allaqachon bekor qilingan — hozirgi reja
                // talabaning yangi ovoziga tegishli, unga tegilmaydi.
                if ($vote->status === 'approved' && !$vote->isArchived()
                    && Schema::hasTable('distribution_draft_assignments')) {
                    DistributionDraftAssignment::query()
                        ->where('student_id', $vote->student_id)
                        ->where('to_group_hemis_id', $vote->to_group_hemis_id)
                        ->delete();
                }

                $vote->delete();
            });

            $deleted++;
        }

        return response()->json([
            'message' => $deleted . " ta ovoz o'chirildi.",
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /**
     * Tanlangan ovozlarni tasdiqlaydi: har biri rejaga (draft) aylanadi va
     * joy band qilinadi. Sig'im yetmagan ovozlar tasdiqlashsiz qoladi va
     * natijada sabab bilan qaytariladi. LMS dagi guruhga tegilmaydi.
     */
    public function approveVotes(Request $request): JsonResponse
    {
        abort_unless(Schema::hasTable('distribution_votes'), 503, 'Ovozlar jadvali hali migratsiya qilinmagan.');
        abort_unless(Schema::hasTable('distribution_draft_assignments'), 503, 'Taqsimot rejasi jadvali hali migratsiya qilinmagan.');

        $data = $request->validate([
            'vote_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'vote_ids.*' => ['required', 'integer'],
        ]);

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
        // Tasdiqlash davomida band qilingan joylarni xotirada kuzatamiz —
        // har ovozdan keyin katalogni qayta hisoblamaslik uchun.
        $taken = [];
        $approved = 0;
        $failed = [];

        foreach (collect($data['vote_ids'])->unique() as $voteId) {
            $vote = DistributionVote::query()->find((int) $voteId);
            if (!$vote || $vote->status !== 'pending' || $vote->isArchived()) {
                continue;
            }

            $target = $catalog->get((int) $vote->to_group_hemis_id);
            if (!$target) {
                $failed[] = $vote->student_name . ' — tanlangan guruh topilmadi';
                continue;
            }

            $free = $target['free_places'];
            $used = $taken[$target['group_hemis_id']] ?? 0;
            if ($free === null || $free - $used < 1) {
                $failed[] = $vote->student_name . ' — ' . $target['group_name'] . " guruhida bo'sh joy qolmadi";
                continue;
            }

            $student = Student::query()->find($vote->student_id);
            if (!$student) {
                $failed[] = $vote->student_name . ' — talaba topilmadi';
                continue;
            }

            DB::transaction(function () use ($vote, $student, $target) {
                DistributionDraftAssignment::updateOrCreate(
                    ['student_id' => $student->id],
                    [
                        'from_group_hemis_id' => (int) $vote->from_group_hemis_id,
                        'to_group_hemis_id' => (int) $target['group_hemis_id'],
                        'student_name' => $student->full_name,
                        'student_id_number' => $student->student_id_number,
                        'from_group_name' => $vote->from_group_name,
                        'to_group_name' => $target['group_name'],
                        'assigned_by' => Auth::id(),
                    ]
                );

                $vote->update([
                    'status' => 'approved',
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);
            });

            $taken[$target['group_hemis_id']] = $used + 1;
            $approved++;
        }

        $message = $approved . ' ta ovoz tasdiqlandi va joylar band qilindi.';
        if ($failed) {
            $message .= ' ' . count($failed) . " ta ovoz o'tmadi.";
        }

        return response()->json([
            'message' => $message,
            'failed' => $failed,
            'groups' => $this->groupCatalog()->values(),
        ]);
    }

    /**
     * Filtrga mos guruhlar va ulardagi talabalarni Excelga chiqaradi.
     *
     * Filtrlar sahifadagi panel bilan bir xil ishlaydi, shuning uchun
     * o'qituvchi ekranda nimani ko'rsa, faylda ham o'sha chiqadi.
     */
    /**
     * Guruhi o'zgargan talabalarga Telegram xabarini yuboradi.
     *
     * Bir talabaga bir marta yuboriladi (notified_at). Reja o'zgarib, talaba
     * boshqa guruhga ko'chirilsa — yangi xabar ketadi (notified_group_id
     * solishtiriladi). Telegram ulanmagan talabalar o'tkazib yuboriladi va
     * javobda sanaladi: ular popup orqali xabardor bo'ladi.
     */
    public function notifyStudents(Request $request, DistributionNotifier $notifier): JsonResponse
    {
        abort_unless(
            Schema::hasTable('distribution_draft_assignments'),
            503,
            'Taqsimot rejasi jadvali hali migratsiya qilinmagan.'
        );

        $data = $request->validate([
            'resend' => ['nullable', 'boolean'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'integer', 'min:1', 'max:8'],
            // 'sent' — faqat allaqachon xabar olganlarga qayta yuborish
            // ("Yuborilgan" tabidan). Bo'sh bo'lsa qamrov cheklanmaydi.
            'only' => ['nullable', 'string', 'in:sent'],
        ]);

        $drafts = DistributionDraftAssignment::query()->with('student')->get();

        // Fakultet/kurs filtri — maqsadli guruh bo'yicha, notifyStatus bilan
        // bir xil manbadan (katalog).
        $faculty = preg_replace('/\s+/u', ' ', trim((string) ($data['faculty'] ?? '')));
        $course = $data['course'] ?? null;

        if ($faculty !== '' || $course !== null) {
            $catalog = $this->groupCatalog()->keyBy('group_hemis_id');
            $drafts = $drafts->filter(function (DistributionDraftAssignment $draft) use ($catalog, $faculty, $course) {
                $group = $catalog->get((int) $draft->to_group_hemis_id);

                return ($faculty === '' || ($group['faculty_name'] ?? '') === $faculty)
                    && ($course === null || (int) ($group['course'] ?? 0) === (int) $course);
            })->values();
        }

        // "Yuborilgan" tabidan qayta yuborish — faqat xabar olganlarga.
        if (($data['only'] ?? null) === 'sent') {
            $drafts = $drafts->reject(fn (DistributionDraftAssignment $d) => $d->needsNotification())->values();
        }

        if ($drafts->isEmpty()) {
            return response()->json(['message' => 'Tanlangan filtr bo\'yicha talaba topilmadi.'], 422);
        }

        $resend = (bool) ($data['resend'] ?? false);
        $sent = 0;
        $skippedNoTelegram = 0;
        $alreadySent = 0;
        $failed = 0;

        foreach ($drafts as $draft) {
            if (!$resend && !$draft->needsNotification()) {
                $alreadySent++;
                continue;
            }

            match ($notifier->notify($draft)) {
                'sent' => $sent++,
                'no_telegram' => $skippedNoTelegram++,
                default => $failed++,
            };
        }

        $parts = [$sent . ' ta talabaga xabar yuborildi'];
        if ($alreadySent) {
            $parts[] = $alreadySent . ' tasiga avval yuborilgan';
        }
        if ($skippedNoTelegram) {
            $parts[] = $skippedNoTelegram . ' tasida Telegram ulanmagan';
        }
        if ($failed) {
            $parts[] = $failed . ' tasiga yuborib bo\'lmadi';
        }

        return response()->json([
            'message' => implode(' · ', $parts) . '.',
            'sent' => $sent,
            'already_sent' => $alreadySent,
            'no_telegram' => $skippedNoTelegram,
            'failed' => $failed,
        ]);
    }

    /**
     * Xabar tugmasi uchun holat: nechta talaba xabar kutmoqda.
     */
    public function notifyStatus(): JsonResponse
    {
        if (!Schema::hasTable('distribution_draft_assignments')) {
            return response()->json(['total' => 0, 'pending' => 0, 'no_telegram' => 0, 'students' => []]);
        }

        $drafts = DistributionDraftAssignment::query()
            ->with('student:id,telegram_chat_id,telegram_username')
            ->orderBy('student_name')
            ->get();

        // Fakultet va kurs draftda saqlanmaydi — maqsadli guruh bo'yicha
        // katalogdan olinadi (filtr shular bo'yicha ishlaydi).
        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');

        // Har bir talaba uchun holat: xabar yuborilganmi, Telegram ulanganmi.
        $students = $drafts->map(function (DistributionDraftAssignment $draft) use ($catalog) {
            $hasTelegram = !empty($draft->student?->telegram_chat_id);
            $needs = $draft->needsNotification();
            $group = $catalog->get((int) $draft->to_group_hemis_id);

            return [
                'student_id' => (int) $draft->student_id,
                'full_name' => $draft->student_name,
                'student_id_number' => (string) $draft->student_id_number,
                'from_group_name' => $draft->from_group_name,
                'to_group_name' => $draft->to_group_name,
                'faculty_name' => $group['faculty_name'] ?? '',
                'course' => $group['course'] ?? null,
                'has_telegram' => $hasTelegram,
                'telegram_username' => $draft->student?->telegram_username,
                'notified_at' => optional($draft->notified_at)->format('d.m.Y H:i'),
                'seen_at' => optional($draft->seen_at)->format('d.m.Y H:i'),
                // sent — xabar shu guruh uchun yuborilgan;
                // pending — yuborilishi kerak, Telegram bor;
                // no_telegram — yuborib bo'lmaydi, Telegram ulanmagan.
                'state' => !$needs ? 'sent' : ($hasTelegram ? 'pending' : 'no_telegram'),
            ];
        })->values();

        return response()->json([
            'total' => $students->count(),
            'sent' => $students->where('state', 'sent')->count(),
            'pending' => $students->where('state', 'pending')->count(),
            'no_telegram' => $students->where('state', 'no_telegram')->count(),
            'faculties' => $students->pluck('faculty_name')->filter()->unique()->sort()->values(),
            'courses' => $students->pluck('course')->filter()->unique()->sort()->values(),
            'students' => $students,
        ]);
    }

    /**
     * Son filtri — sahifadagi numberMatches bilan bir xil qoida:
     *   ""       — cheklov yo'q
     *   "9"      — aynan 9        "!=9" — 9 dan boshqa
     *   ">9" "<9" ">=9" "<=9"     — taqqoslash
     *   "5-9"    — oraliq
     * Tushunarsiz yozuv cheklov qo'ymaydi.
     */
    private function numberMatches(?string $raw, $value): bool
    {
        $text = preg_replace('/\s+/u', '', (string) $raw);
        $text = strtr($text, ['≠' => '!=', '≥' => '>=', '≤' => '<=', '<>' => '!=', '=>' => '>=', '=<' => '<=']);
        if ($text === '') {
            return true;
        }

        if (preg_match('/^(\d+)-(\d+)$/', $text, $m)) {
            if ($value === null) {
                return false;
            }
            return $value >= min((int) $m[1], (int) $m[2]) && $value <= max((int) $m[1], (int) $m[2]);
        }

        if (preg_match('/^(!=|>=|<=|>|<|=)?(\d+)$/', $text, $m)) {
            $n = (int) $m[2];
            $op = $m[1] ?: '=';
            if ($value === null) {
                return $op === '!=';
            }

            return match ($op) {
                '!=' => $value !== $n,
                '>' => $value > $n,
                '<' => $value < $n,
                '>=' => $value >= $n,
                '<=' => $value <= $n,
                default => $value === $n,
            };
        }

        return true;
    }

    /** Holat filtri: free — bo'sh joy bor, full — to'la, over — ortiqcha. */
    private function statusMatches(?string $status, $freePlaces): bool
    {
        if (!$status) {
            return true;
        }
        if ($freePlaces === null) {
            return false;
        }

        return match ($status) {
            'free' => $freePlaces > 0,
            'full' => $freePlaces === 0,
            'over' => $freePlaces < 0,
            default => true,
        };
    }

    public function exportStudents(Request $request)
    {
        $filters = $request->validate([
            'faculty' => ['nullable', 'string', 'max:255'],
            'faculties' => ['nullable', 'array', 'max:50'],
            'faculties.*' => ['required', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:32'],
            'search' => ['nullable', 'string', 'max:255'],
            'only_sources' => ['nullable', 'boolean'],
            'side' => ['nullable', 'string', 'in:left,right'],
            'mode' => ['nullable', 'string', 'in:old,new,both'],
            'min_students' => ['nullable', 'string', 'max:32'],
            'min_capacity' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'in:free,full,over'],
        ]);

        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));

        // Bir nechta fakultet (faculties[]) yoki eski bitta (faculty) parametr.
        $faculties = collect($filters['faculties'] ?? [])
            ->when(!empty($filters['faculty']), fn ($list) => $list->push($filters['faculty']))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->values();

        // Nomlar katalogda tozalangan (DistributionCatalog::cleanName), so'rovdan
        // kelgan qiymat ham shu ko'rinishga keltiriladi.
        $specialty = preg_replace('/\s+/u', ' ', trim((string) ($filters['specialty'] ?? '')));

        $groups = $this->groupCatalog()
            ->when($faculties->isNotEmpty(), fn ($rows) => $rows->whereIn('faculty_name', $faculties->all()))
            ->when($specialty !== '', fn ($rows) => $rows->where('specialty_name', $specialty))
            ->when(!empty($filters['course']), fn ($rows) => $rows->where('course', (int) $filters['course']))
            ->when($search !== '', fn ($rows) => $rows->filter(
                fn ($row) => str_contains(mb_strtolower((string) $row['group_name']), $search)
            ))
            // Ekrandagi son va holat filtrlari — Excel ko'rinib turgan
            // ro'yxatning nusxasi bo'lsin.
            ->filter(fn ($row) => $this->numberMatches($filters['min_students'] ?? '', $row['student_count'] ?? null)
                && $this->numberMatches($filters['min_capacity'] ?? '', $row['capacity'] ?? null)
                && $this->statusMatches($filters['status'] ?? '', $row['free_places'] ?? null))
            ->values();

        // Ekrandagi ro'yxat bilan bir xil: taqsimlanadigan (belgilangan) guruhlar
        // faqat "Taqsimlanadigan guruhlar" vkladkasida chiqadi, chap panel va
        // o'ng paneldagi "Barcha guruhlar" ro'yxatidan tushib qoladi.
        $sourcesOnly = !empty($filters['only_sources']);
        $groups = $groups
            ->filter(fn ($row) => $sourcesOnly ? $row['is_source'] : !$row['is_source'])
            ->values();

        $heading = ($filters['side'] ?? 'left') === 'right'
            ? 'Taqsimlanadigan guruhlar'
            : 'Bo\'sh guruhlarni to\'ldirish';

        $scope = collect([
            $faculties->isNotEmpty() ? $faculties->implode(', ') : null,
            $filters['specialty'] ?? null,
            !empty($filters['course']) ? $filters['course'] . '-kurs' : null,
        ])->filter()->implode(' · ');

        if ($scope !== '') {
            $heading .= '   (' . $scope . ')';
        }

        $mode = $filters['mode'] ?? 'old';
        $modes = $mode === 'both' ? ['old', 'new'] : [$mode];

        $fileName = ($sourcesOnly ? 'taqsimlanadigan-guruhlar-' : 'guruh-talabalari-')
            . $mode . '-' . now()->format('Y-m-d-Hi') . '.xlsx';

        return (new DistributionExport($groups, $heading, $modes, $sourcesOnly))->download($fileName);
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
    /**
     * Berilganlardan qaysilari reja bo'yicha allaqachon ko'chirilgan.
     * Qaytadi: talaba id => true (isset uchun).
     */
    private function assignedStudentIds(Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty() || !Schema::hasTable('distribution_draft_assignments')) {
            return collect();
        }

        return DistributionDraftAssignment::query()
            ->whereIn('student_id', $studentIds->map(fn ($id) => (int) $id)->all())
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
    }

    /**
     * Faol ovozlar: student_id => ovoz berilgan guruhning hozirgi nomi
     * (HEMISda qayta nomlangan bo'lsa ham to'g'ri chiqadi).
     */
    private function activeVoteTargets(): Collection
    {
        if (!Schema::hasTable('distribution_votes')) {
            return collect();
        }

        $catalog = $this->groupCatalog()->keyBy('group_hemis_id');

        return DistributionVote::query()
            ->active()
            ->get(['student_id', 'to_group_hemis_id', 'to_group_name'])
            ->mapWithKeys(fn (DistributionVote $vote) => [
                (int) $vote->student_id => ($catalog->get((int) $vote->to_group_hemis_id)['group_name'] ?? null)
                    ?: $vote->to_group_name,
            ]);
    }

    /** Talabalarning faol ovozlarini eski deb belgilaydi. Nechtasi belgilanganini qaytaradi. */
    private function archiveVotes(Collection $studentIds): int
    {
        if ($studentIds->isEmpty() || !Schema::hasTable('distribution_votes') || !DistributionVote::supportsArchive()) {
            return 0;
        }

        return DistributionVote::query()
            ->active()
            ->whereIn('student_id', $studentIds->map(fn ($id) => (int) $id)->unique()->values()->all())
            ->update(['archived_at' => now(), 'archived_by' => Auth::id()]);
    }

    /**
     * Ovozi faol, lekin rejasi yo'q talabalar — reja keyin bekor qilingan
     * (×, "barchasini qaytarish"). Bunday ovoz talabani qayta ovoz berishdan
     * to'sib turardi; ovoz berish qayta ochilganda u eski deb belgilanadi.
     */
    private function archiveStaleVotes(Collection $studentIds): int
    {
        if ($studentIds->isEmpty()) {
            return 0;
        }

        $assigned = $this->assignedStudentIds($studentIds);

        return $this->archiveVotes(
            $studentIds->reject(fn ($id) => $assigned->has((int) $id))->values()
        );
    }

    private function groupCatalog(): Collection
    {
        return $this->catalog->groups();
    }

}

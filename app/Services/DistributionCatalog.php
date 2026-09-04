<?php

namespace App\Services;

use App\Models\DistributionDraftAssignment;
use App\Models\DistributionGroupCapacity;
use App\Models\Curriculum;
use App\Models\DistributionSourceGroup;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Taqsimlash katalogi — guruhlar, sig'imlar va moslik qoidalari.
 *
 * Admin sahifasi va talaba ovoz berish oynasi bitta manbadan foydalanadi,
 * shunda "bo'sh joy" hisobi ikkala tomonda bir xil chiqadi.
 */
class DistributionCatalog
{
    /**
     * Guruhlar katalogi: faqat o'qiyotgan bakalavr talabalar, faol guruhlar.
     * Sig'im va bo'sh joy reja (draft) hisobga olingan holda qaytadi.
     */
    public function groups(): Collection
    {
        $sourceIds = Schema::hasTable('distribution_source_groups')
            ? DistributionSourceGroup::query()->pluck('group_hemis_id')->map(fn ($id) => (int) $id)->flip()
            : collect();

        // Faol guruhlar va ularning ta'lim tili — til faqat `groups` da bor.
        $activeGroups = Group::query()
            ->where('active', true)
            ->get(['group_hemis_id', 'name', 'department_name', 'specialty_name', 'curriculum_hemis_id', 'education_lang_code', 'education_lang_name'])
            ->keyBy(fn ($group) => (int) $group->group_hemis_id);

        $overrides = Schema::hasTable('distribution_group_capacities')
            ? DistributionGroupCapacity::query()->pluck('capacity', 'group_hemis_id')
            : collect();

        // Reja bo'yicha kelgan va ketgan talabalar soni.
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

        $rows = Student::query()
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

        // Hali talabasi yo'q guruhlar (masalan, yangi o'quv yilida ochilgan
        // d26 guruhlari) students dan chiqmaydi — ularni groups jadvalidan
        // qo'shamiz. Kurs guruh nomidagi qabul yilidan hisoblanadi.
        $known = $rows->pluck('group_hemis_id')->flip();

        // HEMIS ba'zan bitta guruhni ikki xil id bilan (eski/yangi yozuv)
        // faol holda saqlaydi — nom bo'yicha dedupe qilamiz: talabali guruh
        // bilan bir xil nomlisi tashlanadi, sintetiklardan eng yangisi qoladi.
        $nameKeyOf = fn ($name, $specialty) => $this->groupNameKey($name)
            . '|' . mb_strtolower(trim((string) $specialty));
        $existingNames = $rows
            ->map(fn ($row) => $nameKeyOf($row['group_name'], $row['specialty_name']))
            ->flip();

        // Bo'sh guruh ikki holatda qo'shiladi:
        //  - joriy qabul yili (1-kurs) guruhi — yangi o'quv yilida ochilgan;
        //  - LMS da hech qachon talabasi bo'lmagan guruh — HEMIS da yangi
        //    yaratilgan (masalan, yuqori kurs uchun ochilgan ingliz guruhi).
        // Talabasi bo'lib, keyin bo'shab qolgan guruh eskirgan hisoblanadi:
        // import chiqib ketgan talabani o'chirmaydi, holatini 60 qiladi,
        // shuning uchun har qanday holatdagi talaba yozuvi "bo'lgan" degani.
        // Har bir o'quv rejaning ta'lim turi. Yangi guruhning rejasi hali
        // import qilinmagan bo'lishi mumkin — bunda guruh chiqaverishi kerak,
        // faqat reja ANIQ bakalavr emasligi ma'lum bo'lsagina tashlanadi.
        $curriculumTypes = Schema::hasTable('curricula')
            ? Curriculum::query()->pluck('education_type_name', 'curricula_hemis_id')
            : collect();

        $unknownIds = $activeGroups->keys()->reject(fn ($id) => $known->has($id))->values();
        $everHadStudents = $unknownIds->isEmpty()
            ? collect()
            : Student::query()
                ->whereIn('group_id', $unknownIds->all())
                ->distinct()
                ->pluck('group_id')
                ->map(fn ($id) => (int) $id)
                ->flip();

        $candidates = [];
        foreach ($activeGroups as $groupId => $active) {
            if ($known->has($groupId)) {
                continue;
            }

            $course = $this->courseFromName((string) $active->name);
            if ($course === null) {
                continue;
            }
            if ($course !== 1 && $everHadStudents->has($groupId)) {
                continue;
            }

            $curriculumType = $active->curriculum_hemis_id
                ? $curriculumTypes->get((int) $active->curriculum_hemis_id)
                : null;
            if ($curriculumType !== null
                && !str_contains(mb_strtolower((string) $curriculumType), 'bakalavr')) {
                continue;
            }

            $nameKey = $nameKeyOf($active->name, $active->specialty_name);
            if ($existingNames->has($nameKey)) {
                continue;
            }
            if (isset($candidates[$nameKey]) && (int) $candidates[$nameKey]->group_hemis_id > (int) $active->group_hemis_id) {
                continue;
            }
            $candidates[$nameKey] = $active;
        }

        foreach ($candidates as $active) {
            $groupId = (int) $active->group_hemis_id;

            $course = $this->courseFromName((string) $active->name);
            $movedIn = (int) $incoming->get($groupId, 0);
            $movedOut = (int) $outgoing->get($groupId, 0);
            $students = max(0, $movedIn - $movedOut);

            $default = DistributionGroupCapacity::defaultFor($course);
            $capacity = $overrides->has($groupId) ? (int) $overrides->get($groupId) : $default;

            $rows->push([
                'group_hemis_id' => $groupId,
                'group_name' => $active->name,
                'faculty_name' => $active->department_name,
                'specialty_name' => $active->specialty_name,
                'level_code' => '',
                'course' => $course,
                'level_name' => $course ? $course . '-kurs' : null,
                'language_code' => $active->education_lang_code ?: null,
                'language_name' => $active->education_lang_name ?: null,
                'lms_student_count' => 0,
                'student_count' => $students,
                'moved_in' => $movedIn,
                'moved_out' => $movedOut,
                'capacity' => $capacity,
                'is_custom_capacity' => $overrides->has($groupId),
                'free_places' => $capacity === null ? null : $capacity - $students,
                'is_source' => $sourceIds->has($groupId),
            ]);
        }

        return $rows
            ->sortBy([
                ['faculty_name', 'asc'],
                ['specialty_name', 'asc'],
                ['course', 'asc'],
                ['group_name', 'asc'],
            ])
            ->values();
    }

    /**
     * Guruh nomidagi qabul yilidan kursni chiqaradi: d1/d26-01(a) -> 26 ->
     * 2026-27 o'quv yilida 1-kurs. O'quv yili sentabrdan boshlanadi.
     */
    /**
     * Guruh nomining solishtirish kaliti.
     *
     * HEMIS bir guruhni ikki xil yozuvda saqlashi mumkin — qabul yili oldidagi
     * fakultet harfi bor ("d1/d25-01(b)") va yo'q ("d1/25-01(b)") ko'rinishda.
     * Ular bitta guruh: nom bo'yicha dedupe ularni bir xil deb ko'rishi kerak,
     * aks holda talabasiz "arvoh" yozuv katalogda alohida guruh bo'lib qoladi
     * va ovoz berish ro'yxatida talabaning o'z guruhi ko'rinib turadi.
     *
     * Kalit: kichik harf, bo'shliq/nuqta/tire olib tashlanadi, "/" dan keyingi
     * harf prefiksi (d25 -> 25) qisqartiriladi.
     */
    public function groupNameKey(?string $name): string
    {
        $key = mb_strtolower(trim((string) $name));
        // "d1/d25-01(b)" -> "d1/25-01(b)"
        $key = preg_replace('~/\s*[a-zа-я]+(?=\d)~u', '/', $key) ?? $key;
        // Ajratgichlar farqi ahamiyatsiz: "d1/25 - 01 (b)" -> "d1/2501b"
        $key = preg_replace('/[\s.\-_()]+/u', '', $key) ?? $key;

        return $key;
    }

    public function courseFromName(string $name): ?int
    {
        if (!preg_match('/(\d{2})\s*-/', $name, $match) && !preg_match('/(\d{2})/', $name, $match)) {
            return null;
        }

        $admissionYear = (int) $match[1];
        $base = (int) date('y');
        if ((int) date('n') < 9) {
            $base--;
        }

        $course = $base - $admissionYear + 1;

        return $course >= 1 && $course <= 8 ? $course : null;
    }

    /**
     * Berilgan guruh talabasini qabul qila oladigan guruhlar: mos yo'nalish,
     * kurs va til, bo'sh joyi bor, taqsimlanadigan guruh emas.
     *
     * $fullMode — registratorning "to'liq guruh" rejimi: sig'im tekshirilmaydi
     * va boshqa tildagi guruh ham ro'yxatga tushadi.
     */
    public function targetsFor(int $groupHemisId, bool $fullMode = false): Collection
    {
        $catalog = $this->groups();
        $source = $catalog->firstWhere('group_hemis_id', $groupHemisId);

        if (!$source) {
            return collect();
        }

        // O'z guruhi id bo'yicha ham, nomi bo'yicha ham chiqarib tashlanadi:
        // HEMIS bir guruhni ikki id bilan saqlagan bo'lsa, id tekshiruvi yolg'iz
        // yetmaydi va talabaga o'z guruhi taklif bo'lib qolardi.
        $ownName = $this->groupNameKey($source['group_name'] ?? null);

        return $catalog
            ->filter(fn ($group) => $group['group_hemis_id'] !== $source['group_hemis_id']
                && $this->groupNameKey($group['group_name'] ?? null) !== $ownName
                && !$group['is_source']
                && ($fullMode || ($group['free_places'] !== null && $group['free_places'] > 0))
                && $this->compatible($source, $group, $fullMode))
            ->values();
    }

    /**
     * Ikki guruh bir-biriga mos keladimi.
     *
     * Fakultet, yo'nalish va kurs har doim bir xil bo'lishi shart — bularda
     * o'quv rejasi boshqa bo'ladi.
     *
     * Ta'lim tili: talaba ovozida bir xil bo'lishi kerak (o'zbek faqat o'zbek
     * guruhiga o'tadi). Registratorning qo'lda ko'chirishida ($allowOtherLang)
     * til farq qilsa ham ruxsat beriladi — UI oldindan ogohlantiradi.
     */
    public function compatible(?array $source, array $target, bool $allowOtherLang = false): bool
    {
        if (!$source) {
            return false;
        }

        if ($this->facultyKey($source) !== $this->facultyKey($target)
            || $source['specialty_name'] !== $target['specialty_name']
            || $source['course'] !== $target['course']) {
            return false;
        }

        return $allowOtherLang
            || $this->languageKey($source) === $this->languageKey($target);
    }

    /** Fakultetni solishtirish kaliti (bo'shliq va katta-kichik harf farqsiz). */
    public function facultyKey(array $group): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) ($group['faculty_name'] ?? '')))) ?? '';
    }

    /** Guruh ingliz tilida o'qiydimi. */
    public function isEnglish(array $group): bool
    {
        return $this->languageKey($group) === 'en';
    }

    /** Ta'lim tilini solishtirish uchun kalit. */
    public function languageKey(array $group): string
    {
        // Til kodi va nomi bitta kalitga keltiriladi: HEMIS bir guruhda kod,
        // boshqasida faqat nom berishi mumkin ("uz" ↔ "O'zbekcha"), ular bir xil
        // til sifatida qaralishi kerak.
        $code = mb_strtolower(trim((string) ($group['language_code'] ?? '')));
        $name = mb_strtolower(trim((string) ($group['language_name'] ?? '')));

        foreach ([
            'uz' => ['uz', 'uzb', 'oz', '11', "o'z", 'ўз'],
            'ru' => ['ru', 'rus', '12', 'рус'],
            'en' => ['en', 'eng', 'en-us', '14'],
        ] as $key => $codes) {
            if ($code !== '' && in_array($code, $codes, true)) {
                return $key;
            }
        }

        foreach ([
            'uz' => ['ozbek', "o'zbek", 'o‘zbek', 'uzbek', 'ўзбек', 'узбек'],
            'ru' => ['rus', 'русск', 'rossiya'],
            'en' => ['ingliz', 'english', 'англ'],
        ] as $key => $needles) {
            foreach ($needles as $needle) {
                if ($name !== '' && str_contains($name, $needle)) {
                    return $key;
                }
            }
        }

        // Til aniqlanmadi. Bunday guruhlarni bir-biriga mos deb hisoblab
        // bo'lmaydi — har biri o'z kaliti bilan ajralib turadi.
        $raw = $code !== '' ? $code : $name;

        return $raw !== '' ? 'x:' . $raw : 'x:' . (string) $group['group_hemis_id'];
    }

    /** HEMIS level_code (11..16 yoki 1..8) ni kurs raqamiga aylantiradi. */
    public function toCourse($raw): ?int
    {
        $number = (int) $raw;

        if ($number >= 11 && $number <= 20) {
            $number -= 10;
        }

        return $number >= 1 && $number <= 8 ? $number : null;
    }
}

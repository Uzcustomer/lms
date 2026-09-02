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
        $nameKeyOf = fn ($name, $specialty) => mb_strtolower(trim((string) $name)) . '|' . mb_strtolower(trim((string) $specialty));
        $existingNames = $rows
            ->map(fn ($row) => $nameKeyOf($row['group_name'], $row['specialty_name']))
            ->flip();

        // Bo'sh guruhlar faqat joriy qabul yili (1-kurs) va bakalavr bo'lsa
        // qo'shiladi — aks holda eskirgan, talabasi allaqachon chiqib ketgan
        // guruhlar ham ro'yxatga kirib qoladi.
        $bachelorCurricula = Schema::hasTable('curricula')
            ? Curriculum::query()
                ->whereRaw('LOWER(education_type_name) LIKE ?', ['%bakalavr%'])
                ->pluck('curricula_hemis_id')
                ->map(fn ($id) => (int) $id)
                ->flip()
            : collect();

        $candidates = [];
        foreach ($activeGroups as $groupId => $active) {
            if ($known->has($groupId)) {
                continue;
            }

            if ($this->courseFromName((string) $active->name) !== 1) {
                continue;
            }

            if ($bachelorCurricula->isNotEmpty()
                && !$bachelorCurricula->has((int) $active->curriculum_hemis_id)) {
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
     */
    public function targetsFor(int $groupHemisId, bool $fullMode = false): Collection
    {
        $catalog = $this->groups();
        $source = $catalog->firstWhere('group_hemis_id', $groupHemisId);

        if (!$source) {
            return collect();
        }

        return $catalog
            ->filter(fn ($group) => $group['group_hemis_id'] !== $source['group_hemis_id']
                && !$group['is_source']
                && ($fullMode || ($group['free_places'] !== null && $group['free_places'] > 0))
                && $this->compatible($source, $group, $fullMode))
            ->values();
    }

    /**
     * Ikki guruh bir-biriga mos keladimi.
     *
     * Yo'nalish va kurs har doim bir xil bo'lishi shart. Ta'lim tili oddiy
     * rejimda bir xil bo'lishi kerak; "to'liq guruh" rejimida esa maqsad
     * ingliz guruhi bo'lsa, o'zbek/rus guruhidan ham o'tish mumkin.
     */
    public function compatible(?array $source, array $target, bool $fullMode = false): bool
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

    /** Guruh ingliz tilida o'qiydimi. */
    public function isEnglish(array $group): bool
    {
        $name = mb_strtolower(trim((string) ($group['language_name'] ?? '')));
        if ($name !== '' && (str_contains($name, 'ingliz') || str_contains($name, 'english') || str_contains($name, 'англ'))) {
            return true;
        }

        $code = mb_strtolower(trim((string) ($group['language_code'] ?? '')));

        return in_array($code, ['en', 'eng', 'en-us', '14'], true);
    }

    /** Ta'lim tilini solishtirish uchun kalit. */
    public function languageKey(array $group): string
    {
        $value = $group['language_code'] ?: ($group['language_name'] ?? '');

        return mb_strtolower(trim((string) $value));
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

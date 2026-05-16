<?php

namespace App\Services\Retake;

use App\Models\Department;
use App\Models\Group;
use App\Models\Specialty;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Qayta o'qish modulida fakultet–yo'nalish bog'liqligini real talabalar
 * ma'lumoti orqali aniqlovchi xizmat.
 *
 * Sabab: HEMIS'dan keladigan `Specialty.department_hemis_id` har bir
 * yo'nalish uchun bitta "egasi-fakultet"ni saqlaydi, lekin amalda bir xil
 * yo'nalishda turli fakultetlardagi talabalar bo'lishi mumkin (masalan,
 * "Davolash ishi" 1-son va 2-son davolash fakultetlarida ham, Pediatriya
 * fakultetida ham bo'lishi mumkin). `Student.department_id` esa har bir
 * talabaning haqiqiy fakultetini saqlaydi — shuning uchun yagona ishonchli
 * manba shu.
 */
class RetakeFacultyResolver
{
    private const TTL = 60; // 1 daqiqa — talabalar/guruhlar ro'yxati tez-tez o'zgarmaydi

    /**
     * Talabalardan kelib chiqadigan (fakultet → [yo'nalish PK lari]) xaritasi.
     * Agar talabalardan ma'lumot kam bo'lsa, Group jadvalidan ham to'ldiriladi.
     *
     * @return array<string, array<int>>  e.g. ['100' => [12, 34], '101' => [12]]
     */
    public static function studentFacultySpecialtyPairs(): array
    {
        return Cache::remember('retake.faculty.specialty_pairs', self::TTL, function () {
            $facultyIds = Department::where('structure_type_code', '11')
                ->pluck('department_hemis_id')
                ->map(fn ($v) => (string) $v)
                ->values()
                ->all();

            if (empty($facultyIds)) {
                return [];
            }

            $specHemisToPk = Specialty::pluck('id', 'specialty_hemis_id')
                ->mapWithKeys(fn ($pk, $hemisId) => [(string) $hemisId => (int) $pk]);

            $map = [];

            // 1) Asosiy manba — Student.department_id (talabaning haqiqiy fakulteti)
            $studentPairs = Student::query()
                ->select('department_id', 'specialty_id')
                ->whereNotNull('department_id')
                ->whereNotNull('specialty_id')
                ->whereIn('department_id', $facultyIds)
                ->distinct()
                ->get();

            foreach ($studentPairs as $row) {
                $deptId = (string) $row->department_id;
                $pk = $specHemisToPk->get((string) $row->specialty_id);
                if (!$pk) {
                    continue;
                }
                $map[$deptId] ??= [];
                $map[$deptId][] = (int) $pk;
            }

            // 2) Qo'shimcha manba — Group jadvali (talabasi yo'q yoki kam guruhlar uchun)
            $groupPairs = Group::query()
                ->select('department_hemis_id', 'specialty_hemis_id')
                ->whereNotNull('department_hemis_id')
                ->whereNotNull('specialty_hemis_id')
                ->whereIn('department_hemis_id', $facultyIds)
                ->distinct()
                ->get();

            foreach ($groupPairs as $row) {
                $deptId = (string) $row->department_hemis_id;
                $pk = $specHemisToPk->get((string) $row->specialty_hemis_id);
                if (!$pk) {
                    continue;
                }
                $map[$deptId] ??= [];
                $map[$deptId][] = (int) $pk;
            }

            // 3) Talaba/guruhlardan kelmagan fakultetlar uchun Specialty'ning
            //    intrinsik department_hemis_id'idan to'ldirib qo'yamiz — shunda
            //    formada hech bo'lmasa nimadir ko'rinadi.
            $specs = Specialty::whereIn('department_hemis_id', $facultyIds)
                ->select('id', 'department_hemis_id')
                ->get();
            foreach ($specs as $sp) {
                $deptId = (string) $sp->department_hemis_id;
                $map[$deptId] ??= [];
                $map[$deptId][] = (int) $sp->id;
            }

            foreach ($map as $dept => $pks) {
                $map[$dept] = array_values(array_unique($pks));
            }

            return $map;
        });
    }

    /**
     * Bitta yo'nalish (specialty_hemis_id) talabalarining fakultetlari ro'yxati.
     *
     * @return array<string>  fakultet hemis ID lari
     */
    public static function facultiesForSpecialty(int $specialtyHemisId, ?string $levelCode = null): array
    {
        $facultyIds = Department::where('structure_type_code', '11')
            ->pluck('department_hemis_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (empty($facultyIds)) {
            return [];
        }

        $query = Student::query()
            ->whereNotNull('department_id')
            ->where('specialty_id', $specialtyHemisId)
            ->whereIn('department_id', $facultyIds);

        if ($levelCode !== null) {
            $query->where('level_code', $levelCode);
        }

        return $query->distinct()
            ->pluck('department_id')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    /**
     * Berilgan oynalar uchun haqiqiy fakultet nomini runtime'da aniqlash.
     * Har bir (specialty_hemis_id, level_code) jufti uchun talabalar eng ko'p
     * bo'lgan fakultetni qaytaradi. Agar oynaning saqlangan
     * department_hemis_id qiymati shu juftga kiruvchi haqiqiy fakultetlardan
     * biri bo'lsa, undan foydalaniladi (admin tanlovi saqlanadi).
     *
     * @param  iterable  $windows  RetakeApplicationWindow ko'rinishidagi yozuvlar
     * @return array<int, string|null>  window_id => fakultet hemis_id
     */
    public static function resolveFacultiesForWindows(iterable $windows): array
    {
        $pairs = [];
        foreach ($windows as $w) {
            if ($w->specialty_id && $w->level_code) {
                $pairs[$w->specialty_id . '|' . $w->level_code] = [
                    'specialty_id' => (int) $w->specialty_id,
                    'level_code' => (string) $w->level_code,
                ];
            }
        }

        if (empty($pairs)) {
            return [];
        }

        $facultyIds = Department::where('structure_type_code', '11')
            ->pluck('department_hemis_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (empty($facultyIds)) {
            return [];
        }

        $specIds = collect($pairs)->pluck('specialty_id')->unique()->values()->all();
        $levelCodes = collect($pairs)->pluck('level_code')->unique()->values()->all();

        // Bitta so'rov bilan barcha (specialty, level, fakultet) bo'yicha hisob
        $studentCounts = Student::query()
            ->select('specialty_id', 'level_code', 'department_id', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('department_id')
            ->whereIn('specialty_id', $specIds)
            ->whereIn('level_code', $levelCodes)
            ->whereIn('department_id', $facultyIds)
            ->groupBy('specialty_id', 'level_code', 'department_id')
            ->get();

        // (specialty|level) => [department_id => count]
        $byPair = [];
        foreach ($studentCounts as $row) {
            $key = $row->specialty_id . '|' . $row->level_code;
            $byPair[$key] ??= [];
            $byPair[$key][(string) $row->department_id] = (int) $row->cnt;
        }

        $result = [];
        foreach ($windows as $w) {
            if (!$w->specialty_id || !$w->level_code) {
                $result[$w->id] = $w->department_hemis_id ?: null;
                continue;
            }
            $key = $w->specialty_id . '|' . $w->level_code;
            $candidates = $byPair[$key] ?? [];

            $stored = (string) ($w->department_hemis_id ?? '');
            if ($stored !== '' && isset($candidates[$stored])) {
                // Saqlangan fakultet haqiqiy talabalar orasida — saqlaymiz
                $result[$w->id] = $stored;
                continue;
            }

            if (!empty($candidates)) {
                arsort($candidates);
                $result[$w->id] = (string) array_key_first($candidates);
                continue;
            }

            // Talaba topilmadi — saqlangan qiymat qoldiramiz
            $result[$w->id] = $stored !== '' ? $stored : null;
        }

        return $result;
    }

    /**
     * Keshini tozalash — talabalar ro'yxati yangilanganda chaqirish.
     */
    public static function flush(): void
    {
        Cache::forget('retake.faculty.specialty_pairs');
    }
}

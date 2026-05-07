<?php

namespace App\Services\Retake;

use App\Models\Department;
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
    private const TTL = 600; // 10 daqiqa — talabalar ro'yxati tez-tez o'zgarmaydi

    /**
     * Talabalardan kelib chiqadigan (fakultet → [yo'nalish PK lari]) xaritasi.
     *
     * @return array<string, array<int>>  e.g. ['100' => [12, 34], '101' => [12]]
     */
    public static function studentFacultySpecialtyPairs(): array
    {
        return Cache::remember('retake.faculty.specialty_pairs', self::TTL, function () {
            $facultyIds = Department::where('structure_type_code', 11)
                ->pluck('department_hemis_id')
                ->map(fn ($v) => (string) $v)
                ->all();

            if (empty($facultyIds)) {
                return [];
            }

            $rawPairs = Student::query()
                ->select('department_id', 'specialty_id')
                ->whereNotNull('department_id')
                ->whereNotNull('specialty_id')
                ->whereIn('department_id', $facultyIds)
                ->distinct()
                ->get();

            $specHemisToPk = Specialty::pluck('id', 'specialty_hemis_id');

            $map = [];
            foreach ($rawPairs as $row) {
                $deptId = (string) $row->department_id;
                $pk = $specHemisToPk->get($row->specialty_id);
                if (!$pk) {
                    continue;
                }
                $map[$deptId] ??= [];
                $map[$deptId][] = (int) $pk;
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
        $facultyIds = Department::where('structure_type_code', 11)
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
     * Keshini tozalash — talabalar ro'yxati yangilanganda chaqirish.
     */
    public static function flush(): void
    {
        Cache::forget('retake.faculty.specialty_pairs');
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "JN 70%" (grades:jn70 yozadi): asosiy imtihonga qatnashmagan talabaning keyingi urinishlari (12-qo'shimcha,
 * 12a, 12b) uchun oshirilgan JN — o'sha urinishlarda o'tish chegarasi 70. Asl JN o'zgarmaydi.
 */
class JnUpgrade
{
    public const LIMIT = 70;

    /** @return array<string, int> talaba HEMIS id → JN 70% */
    public static function map(string $subjectId, string $semesterCode, array $studentHemisIds): array
    {
        if ($studentHemisIds === [] || !Schema::hasTable('jn_upgrades')) {
            return [];
        }

        return DB::table('jn_upgrades')->where('subject_id', $subjectId)->where('semester_code', $semesterCode)
            ->whereIn('student_hemis_id', array_map('strval', $studentHemisIds))
            ->pluck('jn_upgraded', 'student_hemis_id')->map(fn ($v) => (int) $v)->all();
    }
}

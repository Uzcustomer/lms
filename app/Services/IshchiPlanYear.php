<?php

namespace App\Services;

/**
 * Ishchi o'quv rejalarini o'quv yili bo'yicha tanlash.
 *
 * Ishchi reja plan_year = guruh kirgan (qabul) yili. Rejaning kursi:
 *   - level_code bor bo'lsa (11 = 1-kurs ...) — shundan;
 *   - level_code bo'sh bo'lsa (butun ta'lim davri uchun to'liq reja) — fanning
 *     semestridan: 1-2 → 1-kurs, 3-4 → 2-kurs ...
 * O'qitiladigan o'quv yili = plan_year boshi + kurs - 1.
 *
 * Bir kurs uchun level_code li reja ham, to'liq reja ham bo'lsa, level_code li
 * reja ustun (fanlar ikki marta tushmasin).
 */
class IshchiPlanYear
{
    /** Kurs (1..6) SQL ifodasi. */
    public static function courseSql(string $mc = 'mc', string $s = 's'): string
    {
        return "(CASE WHEN {$mc}.level_code IS NULL OR {$mc}.level_code = '' THEN CEIL({$s}.semester / 2)"
            . " WHEN CAST({$mc}.level_code AS UNSIGNED) >= 11 THEN CAST({$mc}.level_code AS UNSIGNED) - 10"
            . " ELSE CAST({$mc}.level_code AS UNSIGNED) END)";
    }

    /** level_code ko'rinishidagi kurs (11..16) — mavjud kod `level_code` ni shunday kutadi. */
    public static function levelSql(string $mc = 'mc', string $s = 's'): string
    {
        return '(' . self::courseSql($mc, $s) . ' + 10)';
    }

    /** So'rovni berilgan o'quv yilida (boshlanish yili) o'tiladigan reja fanlariga cheklaydi. */
    public static function apply($query, int $startYear, string $mc = 'mc', string $s = 's')
    {
        $levelCourse = "(CASE WHEN CAST(m2.level_code AS UNSIGNED) >= 11 THEN CAST(m2.level_code AS UNSIGNED) - 10"
            . " ELSE CAST(m2.level_code AS UNSIGNED) END)";

        return $query
            ->whereRaw("(CAST(SUBSTRING({$mc}.plan_year, 1, 4) AS UNSIGNED) + " . self::courseSql($mc, $s) . " - 1) = ?", [$startYear])
            ->whereRaw("(({$mc}.level_code IS NOT NULL AND {$mc}.level_code <> '') OR NOT EXISTS (
                SELECT 1 FROM manual_curricula m2
                WHERE m2.type = 'ishchi' AND m2.level_code IS NOT NULL AND m2.level_code <> ''
                  AND (CAST(SUBSTRING(m2.plan_year, 1, 4) AS UNSIGNED) + {$levelCourse} - 1) = ?
                  AND {$levelCourse} = CEIL({$s}.semester / 2)
            ))", [$startYear]);
    }
}

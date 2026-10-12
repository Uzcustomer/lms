<?php

namespace App\Services;

use App\Models\Setting;

/**
 * HEMISga kech kiritilgan (superkontrakt, qo'shimcha, oddiy) talabalarning
 * kiritilishigacha bo'lgan darslariga o'qituvchi dars ochish arizasisiz baho
 * qo'yadigan muddat. Sozlamalarda oxirgi sana belgilanadi:
 *   - bo'sh — muddatsiz (har doim ochiq);
 *   - sana o'tgach — bu baholar ham faqat dars ochish arizasi orqali qo'yiladi.
 */
class LateJoinGradeAccess
{
    public const SETTING = 'late_join_grade_until';

    /** Oxirgi sana (Y-m-d) yoki null — muddatsiz */
    public static function until(): ?string
    {
        $value = trim((string) Setting::get(self::SETTING, ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    /** Bugun arizasiz baho qo'yish mumkinmi (oxirgi sana kuni ham kiradi) */
    public static function isOpen(): bool
    {
        $until = self::until();

        return $until === null || now('Asia/Tashkent')->toDateString() <= $until;
    }
}

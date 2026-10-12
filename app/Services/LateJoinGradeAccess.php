<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * HEMISga kech kiritilgan (superkontrakt, qo'shimcha, oddiy) talabalarning
 * kiritilishigacha bo'lgan darslariga o'qituvchi dars ochish arizasisiz baho
 * qo'yadigan muddat. Sozlamalarda ikki qoida:
 *
 *   1. Avtomatik muddat: "boshlanish sanasi"dan keyin kiritilgan talabaga
 *      o'zining HEMISga kiritilgan kunidan N kun (sukut 14) beriladi.
 *   2. Umumiy oxirgi sana: undan oldin kiritilganlar uchun; bo'sh — muddatsiz.
 *
 * Muddat o'tgach bu baholar ham faqat dars ochish arizasi orqali qo'yiladi.
 */
class LateJoinGradeAccess
{
    public const SETTING = 'late_join_grade_until';

    public const AUTO_FROM_SETTING = 'late_join_auto_from';

    public const AUTO_DAYS_SETTING = 'late_join_auto_days';

    public const DEFAULT_AUTO_DAYS = 14;

    /** Umumiy oxirgi sana (Y-m-d) yoki null — muddatsiz */
    public static function until(): ?string
    {
        return self::date(self::SETTING);
    }

    /** Avtomatik muddat shu sanadan (Y-m-d) keyin kiritilganlarga; null — o'chiq */
    public static function autoFrom(): ?string
    {
        return self::date(self::AUTO_FROM_SETTING);
    }

    /** Avtomatik muddat: kiritilgan kundan necha kun */
    public static function autoDays(): int
    {
        $days = (int) Setting::get(self::AUTO_DAYS_SETTING, self::DEFAULT_AUTO_DAYS);

        return $days > 0 ? min($days, 365) : self::DEFAULT_AUTO_DAYS;
    }

    /** Umumiy qoida: bugun arizasiz baho qo'yish mumkinmi (oxirgi sana kuni ham kiradi) */
    public static function isOpen(): bool
    {
        $until = self::until();

        return $until === null || self::today() <= $until;
    }

    /**
     * Shu talaba uchun oxirgi sana: avtomatik qoida bo'yicha (kiritilgan kun
     * + N kun) yoki umumiy oxirgi sana; null — muddatsiz.
     */
    public static function untilFor(?string $joinedAt): ?string
    {
        $join = $joinedAt ? substr((string) $joinedAt, 0, 10) : null;
        $from = self::autoFrom();
        if ($join && $from && $join >= $from) {
            return Carbon::parse($join)->addDays(self::autoDays())->toDateString();
        }

        return self::until();
    }

    /** Shu talabaning kiritilishigacha bo'lgan darslariga bugun arizasiz baho qo'yish mumkinmi */
    public static function isOpenFor(?string $joinedAt): bool
    {
        $until = self::untilFor($joinedAt);

        return $until === null || self::today() <= $until;
    }

    private static function date(string $key): ?string
    {
        $value = trim((string) Setting::get($key, ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private static function today(): string
    {
        return now('Asia/Tashkent')->toDateString();
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * "Ba'zi baho qo'yilmay qolganlarga baho qo'yish" sozlamasi.
 *
 * Sozlamalarda belgilangan kun ichida (dars kunidan keyin) bo'sh qolgan
 * baholarni tanlangan rollar — registrator ofisi va/yoki o'qituvchi —
 * dars ochish so'rovisiz kiritishi mumkin. Rol tanlansa, o'sha roldagi
 * hamma xodim shu huquqqa ega bo'ladi.
 */
class MissedGradeAccess
{
    public const ROLE_REGISTRAR = 'registrator_ofisi';
    public const ROLE_TEACHER = 'oqituvchi';

    /** Dars kunidan keyin necha kun ichida baho qo'yish mumkin (0 — yopiq) */
    public static function days(): int
    {
        return max(0, (int) Setting::get('missed_grade_days', 0));
    }

    /** Baho qo'ya oladigan rollar */
    public static function roles(): array
    {
        $roles = [];
        if (filter_var(Setting::get('missed_grade_by_registrator', false), FILTER_VALIDATE_BOOLEAN)) {
            $roles[] = self::ROLE_REGISTRAR;
        }
        if (filter_var(Setting::get('missed_grade_by_oqituvchi', false), FILTER_VALIDATE_BOOLEAN)) {
            $roles[] = self::ROLE_TEACHER;
        }

        return $roles;
    }

    public static function enabled(): bool
    {
        return self::days() > 0 && self::roles() !== [];
    }

    /** Shu dars kuni hali muddat ichidami (kelajakdagi kunlar hisobga olinmaydi) */
    public static function isDateOpen($date): bool
    {
        if (!self::enabled() || !$date) {
            return false;
        }

        $lessonDate = Carbon::parse($date)->startOfDay();
        $today = Carbon::today('Asia/Tashkent');

        return $lessonDate->lte($today)
            && $lessonDate->gte($today->copy()->subDays(self::days()));
    }

    /** Berilgan sanalardan muddat ichidagilari */
    public static function openDates(array $dates): array
    {
        if (!self::enabled()) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($date) => Carbon::parse($date)->format('Y-m-d'), $dates),
            fn ($date) => self::isDateOpen($date)
        ));
    }

    /** Joriy foydalanuvchining faol roli baho qo'yishga ruxsat beradimi */
    public static function allowsCurrentUser(): bool
    {
        if (!self::enabled()) {
            return false;
        }

        $roles = self::roles();

        if (in_array(self::ROLE_TEACHER, $roles, true) && is_active_oqituvchi()) {
            return true;
        }

        return in_array(self::activeRole(), $roles, true);
    }

    private static function activeRole(): string
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user || !method_exists($user, 'getRoleNames')) {
            return '';
        }

        $userRoles = $user->getRoleNames()->all();
        $active = (string) session('active_role', '');

        if (!in_array($active, $userRoles, true)) {
            $active = $userRoles[0] ?? '';
        }

        return $active;
    }
}

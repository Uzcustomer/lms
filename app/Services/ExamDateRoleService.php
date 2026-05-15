<?php

namespace App\Services;

use App\Enums\ProjectRole;
use App\Models\Setting;

/**
 * YN (Yakuniy Nazorat) sanasini belgilash huquqini boshqaradi.
 *
 * Sozlamalar har bir kurs darajasi (level_code) uchun ushbu sahifaga
 * kirish va tahrirlash huquqiga ega bo'lgan rollar ro'yxatini saqlaydi.
 * Sozlamalar Setting jadvalida `exam_date_role_settings` kalit ostida
 * JSON ko'rinishida saqlanadi:
 *
 * {
 *   "11": ["registrator_ofisi"],
 *   "12": ["registrator_ofisi"],
 *   "13": ["registrator_ofisi"],
 *   "14": ["oquv_bolimi", "oquv_bolimi_boshligi"],
 *   "15": ["oquv_bolimi", "oquv_bolimi_boshligi"],
 *   "16": ["dekan"]
 * }
 */
class ExamDateRoleService
{
    public const SETTING_KEY = 'exam_date_role_settings';

    /** Toggle: when true, the test-centre role may edit/save exam times for
     *  today as well. Default false — test-centre may only touch future-dated
     *  exams; today and earlier are admin-only. Stored as a separate Setting
     *  so admins can flip it on for unusual circumstances (last-minute
     *  schedule changes, etc.). */
    public const SETTING_TC_EDIT_TODAY = 'test_center_can_edit_today';

    /**
     * Whether the test-centre role may currently edit today's exam times.
     */
    public static function testCenterCanEditToday(): bool
    {
        return (bool) \App\Models\Setting::get(self::SETTING_TC_EDIT_TODAY, false);
    }

    /**
     * Persist the test-centre "edit today" toggle. Admin-only caller.
     */
    public static function setTestCenterCanEditToday(bool $value): void
    {
        \App\Models\Setting::set(self::SETTING_TC_EDIT_TODAY, $value ? '1' : '0');
    }

    /**
     * Sahifaga kirish va tahrirlash uchun sozlanishi mumkin bo'lgan rollar.
     *
     * @return array<string, string> [role_value => label]
     */
    public static function configurableRoles(): array
    {
        return [
            ProjectRole::REGISTRAR_OFFICE->value => ProjectRole::REGISTRAR_OFFICE->label(),
            ProjectRole::ACADEMIC_DEPARTMENT->value => ProjectRole::ACADEMIC_DEPARTMENT->label(),
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value => ProjectRole::ACADEMIC_DEPARTMENT_HEAD->label(),
            ProjectRole::DEAN->value => ProjectRole::DEAN->label(),
        ];
    }

    /**
     * Sahifani faqat ko'rish (read-only) huquqiga ega bo'lgan rollar.
     * Ushbu rollarning barchasi barcha kurslarni ko'ra oladi.
     * Sana qo'yish huquqi alohida (canEditAttempt) tekshiriladi.
     *
     * @return array<int, string>
     */
    public static function viewerRoles(): array
    {
        return [
            ProjectRole::REGISTRAR_OFFICE->value,
            ProjectRole::ACADEMIC_DEPARTMENT->value,
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
            ProjectRole::DEAN->value,
        ];
    }

    /**
     * Ushbu sozlamalardan qat'i nazar har doim huquqqa ega bo'lgan admin rollari.
     *
     * @return array<int, string>
     */
    public static function adminRoles(): array
    {
        return [
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
            ProjectRole::JUNIOR_ADMIN->value,
        ];
    }

    /**
     * Default mapping (level_code -> roles[]). Eski xulq-atvorni saqlaydi:
     * 4 va 5-kurs uchun "O'quv bo'limi" rollari.
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultMapping(): array
    {
        $oquv = [
            ProjectRole::ACADEMIC_DEPARTMENT->value,
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ];

        return [
            '11' => $oquv,
            '12' => $oquv,
            '13' => $oquv,
            '14' => $oquv,
            '15' => $oquv,
            '16' => $oquv,
        ];
    }

    /**
     * Saqlangan mapping yoki default.
     *
     * @return array<string, array<int, string>>
     */
    public static function getMapping(): array
    {
        $raw = Setting::get(self::SETTING_KEY);
        if (!$raw) {
            return self::defaultMapping();
        }

        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaultMapping();
        }

        // Normalizatsiya: kalitlar string bo'lsin, qiymatlar massiv bo'lsin
        $normalized = [];
        foreach ($decoded as $level => $roles) {
            $normalized[(string) $level] = is_array($roles)
                ? array_values(array_filter(array_map('strval', $roles)))
                : [];
        }

        // Default level kodlarini ta'minlash
        foreach (self::defaultMapping() as $level => $_) {
            if (!isset($normalized[$level])) {
                $normalized[$level] = [];
            }
        }

        return $normalized;
    }

    /**
     * Mapping'ni saqlash. Faqat configurableRoles ichidagi rollar saqlanadi.
     *
     * @param  array<string, array<int, string>>  $mapping
     */
    public static function setMapping(array $mapping): void
    {
        $allowed = array_keys(self::configurableRoles());
        $clean = [];
        // Default kurs darajalarini boshlang'ich bo'sh massiv bilan to'ldirish
        foreach (array_keys(self::defaultMapping()) as $level) {
            $clean[$level] = [];
        }
        foreach ($mapping as $level => $roles) {
            $level = (string) $level;
            $roles = is_array($roles) ? $roles : [];
            $clean[$level] = array_values(array_unique(array_filter(
                array_map('strval', $roles),
                fn ($r) => in_array($r, $allowed, true)
            )));
        }
        Setting::set(self::SETTING_KEY, json_encode($clean));
    }

    /**
     * Ushbu rol biror level uchun sozlangan bo'lsa true.
     */
    public static function roleHasAnyAccess(?string $role): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        foreach (self::getMapping() as $roles) {
            if (in_array($role, $roles, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Berilgan rol qaysi level kodlariga ruxsatga ega.
     *
     * @return array<int, string>
     */
    public static function levelsForRole(?string $role): array
    {
        if (!$role) {
            return [];
        }
        if (in_array($role, self::adminRoles(), true)) {
            return array_keys(self::getMapping());
        }
        $levels = [];
        foreach (self::getMapping() as $level => $roles) {
            if (in_array($role, $roles, true)) {
                $levels[] = (string) $level;
            }
        }
        return $levels;
    }

    /**
     * Berilgan level uchun rol ruxsatga egami.
     */
    public static function canEditLevel(?string $role, ?string $levelCode): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        if (!$levelCode) {
            return false;
        }
        $mapping = self::getMapping();
        return in_array($role, $mapping[(string) $levelCode] ?? [], true);
    }

    /**
     * YN kunini belgilash sahifasini ko'rish huquqi.
     * Admin, viewerRoles yoki sozlamalarda biror level uchun ruxsatli rol — ko'ra oladi.
     */
    public static function canViewPage(?string $role): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        if (in_array($role, self::viewerRoles(), true)) {
            return true;
        }
        return self::roleHasAnyAccess($role);
    }

    /**
     * 2-urinish va undan keyingi (resit) sanalarini belgilash huquqi.
     * Admin va registrator_ofisi qayta urinish sanalarini qo'yadi; boshqa rollar — yo'q.
     */
    public static function canEditResit(?string $role): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        return $role === ProjectRole::REGISTRAR_OFFICE->value;
    }

    /**
     * Berilgan urinish va kurs darajasi uchun rol sana qo'yishga ruxsatga egami.
     *  - 1-urinish: sozlamalardagi mapping (canEditLevel) bo'yicha.
     *  - 2+ urinish: faqat registrator_ofisi (yoki admin).
     */
    public static function canEditAttempt(?string $role, ?string $levelCode, int $attempt): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        if ($attempt >= 2) {
            return self::canEditResit($role);
        }
        return self::canEditLevel($role, $levelCode);
    }

    /**
     * Foydalanuvchi biror sana belgilashga umuman ruxsatga egami (har qanday urinish/kurs).
     * Store/saqlash endpoint'lariga top-level kirish uchun ishlatiladi.
     */
    public static function canEditAnything(?string $role): bool
    {
        if (!$role) {
            return false;
        }
        if (in_array($role, self::adminRoles(), true)) {
            return true;
        }
        if (self::canEditResit($role)) {
            return true;
        }
        return self::roleHasAnyAccess($role);
    }
}

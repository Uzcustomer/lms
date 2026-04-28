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
}

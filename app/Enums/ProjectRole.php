<?php

namespace App\Enums;

enum ProjectRole: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case JUNIOR_ADMIN = 'kichik_admin';
    case INSPECTOR = 'inspeksiya';
    case VICE_RECTOR = 'oquv_prorektori';
    case REGISTRAR_OFFICE = 'registrator_ofisi';
    case ACADEMIC_DEPARTMENT = 'oquv_bolimi';
    case ACCOUNTANT = 'buxgalteriya';
    case SPIRITUAL_AFFAIRS = 'manaviyat';
    case TUTOR = 'tyutor';
    case DEAN = 'dekan';
    case DEPARTMENT_HEAD = 'kafedra_mudiri';
    case SUBJECT_RESPONSIBLE = 'fan_masuli';
    case TEACHER = 'oqituvchi';
    case STUDENT = 'talaba';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Superadmin',
            self::ADMIN => 'Admin',
            self::JUNIOR_ADMIN => 'Kichik admin',
            self::INSPECTOR => 'Inspeksiya',
            self::VICE_RECTOR => "O'quv prorektori",
            self::REGISTRAR_OFFICE => 'Registrator ofisi',
            self::ACADEMIC_DEPARTMENT => "O'quv bo'limi",
            self::ACCOUNTANT => 'Buxgalteriya',
            self::SPIRITUAL_AFFAIRS => "Ma'naviyat",
            self::TUTOR => 'Tyutor',
            self::DEAN => 'Dekan',
            self::DEPARTMENT_HEAD => 'Kafedra mudiri',
            self::SUBJECT_RESPONSIBLE => "Fan mas'uli",
            self::TEACHER => "O'qituvchi",
            self::STUDENT => 'Talaba',
        };
    }

    /**
     * Rol berish huquqiga ega rollar.
     */
    public static function roleManagers(): array
    {
        return [
            self::SUPERADMIN,
            self::ADMIN,
            self::JUNIOR_ADMIN,
        ];
    }

    /**
     * Berilgan rol boshqa foydalanuvchilarga rol bera oladimi?
     */
    public function canAssignRoles(): bool
    {
        return in_array($this, self::roleManagers());
    }

    /**
     * Talaba rolidan tashqari barcha rollar (xodimlar uchun).
     *
     * @return array<ProjectRole>
     */
    public static function staffRoles(): array
    {
        return array_filter(self::cases(), fn (self $role) => $role !== self::STUDENT);
    }

    /**
     * Barcha rollar ro'yxati.
     *
     * @return array<ProjectRole>
     */
    public static function allRoles(): array
    {
        return self::cases();
    }
}

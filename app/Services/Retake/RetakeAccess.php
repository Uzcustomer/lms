<?php

namespace App\Services\Retake;

use App\Enums\ProjectRole;
use App\Models\RetakeApplication;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Qayta o'qish arizalari uchun rolga asoslangan ruxsat tekshiruv helperi.
 *
 * Loyiha Spatie Permissions emas, faqat Spatie Roles ishlatadi (hasRole),
 * shuning uchun ruxsatlarni alohida permissions sifatida emas, rollar
 * bo'yicha aniqlaymiz va shu yerda markazlashtiramiz.
 */
class RetakeAccess
{
    /**
     * Talaba ariza yubora oladimi (faqat talaba roli)?
     */
    public static function canApply(?Model $actor): bool
    {
        return $actor instanceof Student;
    }

    /**
     * Foydalanuvchi dekan sifatida arizalarni ko'rib chiqa oladimi?
     */
    public static function canApproveAsDean(?Model $actor): bool
    {
        return $actor instanceof Teacher
            && $actor->hasRole(ProjectRole::DEAN->value);
    }

    /**
     * Foydalanuvchi registrator ofisi sifatida ko'rib chiqa oladimi?
     */
    public static function canApproveAsRegistrar(?Model $actor): bool
    {
        if (!self::isStaff($actor)) {
            return false;
        }
        return $actor->hasRole(ProjectRole::REGISTRAR_OFFICE->value)
            || self::isSuperAdmin($actor);
    }

    /**
     * Qabul oynalari/sessiyalarni KO'RISH (faqat o'qish) ruxsati.
     * O'quv bo'limi (to'liq boshqaruv) + Registrator ofisi (faqat ko'rish) +
     * super-admin.
     */
    public static function canViewWindows(?Model $actor): bool
    {
        if (!self::isStaff($actor)) {
            return false;
        }
        return self::canManageAcademicDept($actor)
            || $actor->hasRole(ProjectRole::REGISTRAR_OFFICE->value);
    }

    /**
     * O'quv bo'limi xodimi (yakuniy bosqich + oyna/guruh boshqaruvi)?
     */
    public static function canManageAcademicDept(?Model $actor): bool
    {
        if (!self::isStaff($actor)) {
            return false;
        }
        return $actor->hasAnyRole([
            ProjectRole::ACADEMIC_DEPARTMENT->value,
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ]) || self::isSuperAdmin($actor);
    }

    /**
     * Sozlamalarni boshqarish (kredit narxi, va h.k.).
     * Faqat o'quv bo'limi boshlig'i + super-admin.
     */
    public static function canManageSettings(?Model $actor): bool
    {
        if (!self::isStaff($actor)) {
            return false;
        }
        return $actor->hasAnyRole([
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
            ProjectRole::REGISTRAR_OFFICE->value,
        ]) || self::isSuperAdmin($actor);
    }

    /**
     * Statistika va eksportni ko'rish.
     */
    public static function canViewStatistics(?Model $actor): bool
    {
        return self::canApproveAsRegistrar($actor)
            || self::canManageAcademicDept($actor);
    }

    /**
     * O'qituvchi-fan-talabalar statistikasi.
     * Faqat o'quv bo'limi (akademik dept) + super-admin. Registrator/dekan yo'q.
     */
    public static function canViewTeacherSubjectStats(?Model $actor): bool
    {
        return self::canManageAcademicDept($actor);
    }

    /**
     * Oyna/sessiya sanalarini override qilish (vaqtni o'zgartirish).
     * Super-admin + O'quv bo'limi (xodim va boshlig'i) bo'la oladi.
     */
    public static function canOverride(?Model $actor): bool
    {
        if (!self::isStaff($actor)) {
            return false;
        }
        return self::isSuperAdmin($actor)
            || $actor->hasAnyRole([
                ProjectRole::ACADEMIC_DEPARTMENT->value,
                ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
            ]);
    }

    /**
     * Loyihaning xodimlari teachers yoki users jadvalida bo'lishi mumkin —
     * ikkalasi ham HasRoles trait'iga ega.
     */
    private static function isStaff(?Model $actor): bool
    {
        return $actor instanceof Teacher || $actor instanceof User;
    }

    /**
     * Joriy autentifikatsiya qilingan xodim (Teacher yoki User)ni qaytaradi.
     * Loyihada xodimlar `teacher` yoki `web` guardlardan birida bo'lishi mumkin.
     */
    public static function currentStaff(): ?Model
    {
        if (\Illuminate\Support\Facades\Auth::guard('teacher')->check()) {
            return \Illuminate\Support\Facades\Auth::guard('teacher')->user();
        }
        if (\Illuminate\Support\Facades\Auth::guard('web')->check()) {
            return \Illuminate\Support\Facades\Auth::guard('web')->user();
        }
        return null;
    }

    /**
     * Berilgan dekan ushbu talabaning fakultetiga tegishlimi?
     */
    public static function deanHandlesStudent(Teacher $dean, Student $student): bool
    {
        if ($student->department_id === null) {
            return false;
        }
        $deanFacultyIds = $dean->deanFacultyIds; // department_hemis_id ro'yxati

        return in_array((int) $student->department_id, array_map('intval', $deanFacultyIds), true);
    }

    /**
     * Berilgan dekan ushbu arizani ko'rib chiqa oladimi?
     */
    public static function deanCanReviewApplication(Teacher $dean, RetakeApplication $application): bool
    {
        $student = Student::where('hemis_id', $application->student_hemis_id)->first();
        if (!$student) {
            return false;
        }
        return self::deanHandlesStudent($dean, $student);
    }

    /**
     * Talaba o'z arizasini ko'ra oladimi?
     */
    public static function studentOwnsApplication(Student $student, RetakeApplication $application): bool
    {
        return (int) $student->hemis_id === (int) $application->student_hemis_id;
    }

    private static function isSuperAdmin(Model $actor): bool
    {
        return $actor->hasAnyRole([
            ProjectRole::SUPERADMIN->value,
            ProjectRole::ADMIN->value,
        ]);
    }
}

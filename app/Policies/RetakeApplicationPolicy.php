<?php

namespace App\Policies;

use App\Models\RetakeApplication;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Contracts\Auth\Authenticatable;

class RetakeApplicationPolicy
{
    use HandlesAuthorization;

    /**
     * Ariza ro'yxatini ko'rish — har rol o'z ko'lamida.
     */
    public function viewAny(Authenticatable $user): bool
    {
        if ($user instanceof Student) {
            return true; // o'z arizalarini ko'radi
        }

        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole([
            'dekan', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi',
            'admin', 'superadmin',
        ]);
    }

    /**
     * Yakka arizani ko'rish:
     *  - Talaba: faqat o'ziniki
     *  - Dekan: faqat o'z fakulteti talabasiniki
     *  - Registrator/O'quv bo'limi: hammasi
     *  - Admin/Superadmin: hammasi
     */
    public function view(Authenticatable $user, RetakeApplication $application): bool
    {
        if ($user instanceof Student) {
            return $application->student_id === $user->id;
        }

        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        if ($user->hasRole(['admin', 'superadmin', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi'])) {
            return true;
        }

        if ($user->hasRole('dekan') && $user instanceof Teacher) {
            return $this->matchesDeanFaculty($user, $application);
        }

        return false;
    }

    /**
     * Yangi ariza yuborish — faqat talaba uchun.
     */
    public function create(Authenticatable $user): bool
    {
        return $user instanceof Student;
    }

    public function approveAsDean(Authenticatable $user, RetakeApplication $application): bool
    {
        if (! ($user instanceof Teacher)) {
            return false;
        }
        if (! $user->hasRole('dekan')) {
            return false;
        }
        return $this->matchesDeanFaculty($user, $application);
    }

    public function approveAsRegistrar(Authenticatable $user, RetakeApplication $application): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole('registrator_ofisi');
    }

    public function approveAsAcademicDept(Authenticatable $user, RetakeApplication $application): bool
    {
        if (! method_exists($user, 'hasRole')) {
            return false;
        }
        return $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi']);
    }

    /**
     * Kvitansiya/tasdiqnoma fayllarini yuklab olish:
     *  - Talaba o'z arizasiniki
     *  - Tegishli rollar (dekan o'z fakultetiniki)
     */
    public function downloadFiles(Authenticatable $user, RetakeApplication $application): bool
    {
        return $this->view($user, $application);
    }

    private function matchesDeanFaculty(Teacher $teacher, RetakeApplication $application): bool
    {
        $studentDepartmentId = $application->student?->department_id;
        if ($studentDepartmentId === null) {
            return false;
        }

        $facultyIds = $teacher->dean_faculty_ids ?? [];
        return in_array((int) $studentDepartmentId, array_map('intval', $facultyIds), true);
    }
}

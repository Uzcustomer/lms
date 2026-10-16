<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringDetector;
use App\Models\StaffRegistrationDivision;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;

/**
 * Istisno mas'ulini aniqlash: fakultet dekani, kafedra mudiri, talabaning registratori yoki
 * detektorning paneldan tanlangan standart mas'uli. Bir run ichida natijalar keshlanadi.
 */
class OwnerResolver
{
    public const REGISTRAR_OFFICES = ['back_office' => 'Back ofis', 'front_office' => 'Front ofis'];

    private array $deans = [];
    private array $heads = [];
    private array $registrars = [];
    private array $byHemisId = [];

    /** Xodim HEMIS ID bo'yicha (masalan vedomostda ko'rsatilgan kafedra mudiri). */
    public function teacherByHemisId($hemisId): ?Teacher
    {
        if (empty($hemisId)) {
            return null;
        }

        if (!array_key_exists((string) $hemisId, $this->byHemisId)) {
            $this->byHemisId[(string) $hemisId] = Teacher::where('hemis_id', $hemisId)->orderByDesc('is_active')->first();
        }

        return $this->byHemisId[(string) $hemisId];
    }

    /**
     * Talabaga biriktirilgan registrator: «Registrator bo'linmalari» (staff_registration_divisions) dagi
     * fakultet → yo'nalish → kurs taqsimoti bo'yicha front yoki back ofis xodimi.
     * $office bo'sh bo'lsa yoki biriktirish topilmasa — null (standart mas'ulga o'tadi).
     */
    public function registrarOfStudent(?object $student, ?string $office): ?Teacher
    {
        if ($student === null || empty($student->department_id) || !array_key_exists((string) $office, self::REGISTRAR_OFFICES)) {
            return null;
        }

        $key = implode('|', [$office, $student->department_id, $student->specialty_id ?? '', $student->level_code ?? '']);
        if (!array_key_exists($key, $this->registrars)) {
            $this->registrars[$key] = StaffRegistrationDivision::findForStudent(
                $student->department_id, $student->specialty_id ?? null, $student->level_code ?? null, $office
            )?->teacher;
        }

        return $this->registrars[$key];
    }

    public function deanOfFaculty(?int $facultyHemisId): ?Teacher
    {
        if (!$facultyHemisId) {
            return null;
        }

        if (!array_key_exists($facultyHemisId, $this->deans)) {
            $this->deans[$facultyHemisId] = Teacher::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'dekan'))
                ->whereHas('deanFaculties', fn ($q) => $q->where('departments.department_hemis_id', $facultyHemisId))
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first();
        }

        return $this->deans[$facultyHemisId];
    }

    public function headOfDepartment(?int $departmentHemisId): ?Teacher
    {
        if (!$departmentHemisId) {
            return null;
        }

        if (!array_key_exists($departmentHemisId, $this->heads)) {
            $this->heads[$departmentHemisId] = Teacher::query()
                ->headsOfDepartment($departmentHemisId)
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->first();
        }

        return $this->heads[$departmentHemisId];
    }

    public function defaultOwner(MonitoringDetector $detector): ?Model
    {
        if (!$detector->default_owner_type || !$detector->default_owner_id) {
            return null;
        }

        return $detector->defaultOwner;
    }
}

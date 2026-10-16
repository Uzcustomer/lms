<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringDetector;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Model;

/**
 * Istisno mas'ulini aniqlash: fakultet dekani, kafedra mudiri yoki detektorning
 * paneldan tanlangan standart mas'uli. Bir run ichida natijalar keshlanadi.
 */
class OwnerResolver
{
    private array $deans = [];
    private array $heads = [];

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffRegistrationDivision extends Model
{
    protected $fillable = [
        'teacher_id',
        'division_type',
        'department_hemis_id',
        'specialty_hemis_id',
        'level_code',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    /**
     * Faqat faol (ended_at null) biriktirishlar
     */
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Tarixiy (ended_at bor) biriktirishlar
     */
    public function scopeHistory($query)
    {
        return $query->whereNotNull('ended_at');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function getDivisionLabelAttribute(): string
    {
        return $this->division_type === 'front_office' ? 'Front ofis' : 'Back ofis';
    }

    public function getLevelNameAttribute(): ?string
    {
        if (!$this->level_code) {
            return null;
        }

        return Semester::where('level_code', $this->level_code)
            ->value('level_name');
    }

    /**
     * Talabaning fakultet, yo'nalish, kurs bo'yicha biriktirilgan xodimni topish
     */
    public static function findForStudent($departmentId, $specialtyId, $levelCode, $divisionType)
    {
        // 1. Eng aniq: fakultet + yo'nalish + kurs
        $division = static::active()
            ->where('division_type', $divisionType)
            ->where('department_hemis_id', $departmentId)
            ->where('specialty_hemis_id', $specialtyId)
            ->where('level_code', $levelCode)
            ->first();
        if ($division) return $division;

        // 2. Fakultet + yo'nalish (barcha kurslar)
        $division = static::active()
            ->where('division_type', $divisionType)
            ->where('department_hemis_id', $departmentId)
            ->where('specialty_hemis_id', $specialtyId)
            ->whereNull('level_code')
            ->first();
        if ($division) return $division;

        // 3. Fakultet + kurs (barcha yo'nalishlar)
        $division = static::active()
            ->where('division_type', $divisionType)
            ->where('department_hemis_id', $departmentId)
            ->whereNull('specialty_hemis_id')
            ->where('level_code', $levelCode)
            ->first();
        if ($division) return $division;

        // 4. Faqat fakultet (barcha yo'nalish va kurslar)
        return static::active()
            ->where('division_type', $divisionType)
            ->where('department_hemis_id', $departmentId)
            ->whereNull('specialty_hemis_id')
            ->whereNull('level_code')
            ->first();
    }
}

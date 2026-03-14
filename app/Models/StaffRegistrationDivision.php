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
    ];

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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualCurriculum extends Model
{
    protected $table = 'manual_curricula';

    protected $fillable = [
        'type',
        'status',
        'name',
        'specialty_code',
        'specialty_name',
        'plan_year',
        'curricula_hemis_id',
        'level_code',
        'semester_code',
        'education_type_name',
        'education_period',
        'file_original_name',
        'file_path',
        'notes',
        'created_by',
    ];

    public function subjects()
    {
        return $this->hasMany(ManualCurriculumSubject::class);
    }

    public function hemisCurriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curricula_hemis_id', 'curricula_hemis_id');
    }

    public function typeLabel(): string
    {
        return $this->type === 'namunaviy' ? "Namunaviy o'quv reja" : "Ishchi o'quv reja";
    }

    /** Rejalashtirilgan (HEMIS'ga bog'lanmagan) reja. */
    public function isPlanned(): bool
    {
        return $this->status === 'planned' || ($this->curricula_hemis_id === null && $this->status !== 'active');
    }
}

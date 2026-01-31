<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = [
        'semester_hemis_id',
        'code',
        'name',
        'curriculum_hemis_id',
        'education_year',
        'level_code',
        'level_name',
        'current',
    ];

    protected $casts = [
        'current' => 'boolean',
    ];

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_hemis_id', 'curricula_hemis_id');
    }

    public function curriculumWeeks()
    {
        return $this->hasMany(CurriculumWeek::class, 'semester_hemis_id', 'semester_hemis_id');
    }
}

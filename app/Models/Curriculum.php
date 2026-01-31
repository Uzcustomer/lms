<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Curriculum extends Model
{
    protected $fillable = [
        'curricula_hemis_id',
        'name',
        'specialty_hemis_id',
        'department_hemis_id',
        'education_year_code',
        'education_year_name',
        'current',
        'education_type_code',
        'education_type_name',
        'education_form_code',
        'education_form_name',
        'marking_system_code',
        'marking_system_name',
        'marking_system_minimum_limit',
        'marking_system_gpa_limit',
        'semester_count',
        'education_period',
    ];

    protected $casts = [
        'current' => 'boolean',
        'marking_system_minimum_limit' => 'integer',
        'marking_system_gpa_limit' => 'float',
        'semester_count' => 'integer',
        'education_period' => 'integer',
    ];

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function curriculumSubjects()
    {
        return $this->hasMany(CurriculumSubject::class, 'curricula_hemis_id', 'curricula_hemis_id');
    }

    public function curriculumSubjectsForStudent($student)
    {
        return $this->hasMany(CurriculumSubject::class, 'curricula_hemis_id', 'curricula_hemis_id')
            ->where('department_id', $student->department_id)
            ->where('semester_code',$student->semester_code);
    }


    public function semesters()
    {
        return $this->hasMany(Semester::class, 'curriculum_hemis_id', 'curricula_hemis_id');
    }
}

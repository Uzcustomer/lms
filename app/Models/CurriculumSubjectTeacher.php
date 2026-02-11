<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurriculumSubjectTeacher extends Model
{
    protected $fillable = [
        'hemis_id',
        'semester_id',
        'education_year_id',
        'curriculum_id',
        'department_id',
        'group_id',
        'training_type_id',
        'subject_id',
        'subject_code',
        'subject_name',
        'employee_id',
        'employee_name',
        'training_type_code',
        'training_type_name',
        'curriculum_subject_detail_id',
        'academic_load',
        'active',
        'students_count',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}

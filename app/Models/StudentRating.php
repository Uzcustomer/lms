<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentRating extends Model
{
    protected $fillable = [
        'student_hemis_id',
        'full_name',
        'group_name',
        'department_code',
        'department_name',
        'specialty_code',
        'specialty_name',
        'level_code',
        'semester_code',
        'education_year_code',
        'subjects_count',
        'jn_average',
        'rank',
        'calculated_at',
    ];

    protected $casts = [
        'jn_average' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

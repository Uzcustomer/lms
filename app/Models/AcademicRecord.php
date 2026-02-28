<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'hemis_id',
        'curriculum_id',
        'education_year',
        'semester_id',
        'student_id',
        'subject_id',
        'employee_id',
        'employee_name',
        'semester_name',
        'student_name',
        'subject_name',
        'total_acload',
        'credit',
        'total_point',
        'grade',
        'finish_credit_status',
        'retraining_status',
        'hemis_created_at',
        'hemis_updated_at',
    ];

    protected $casts = [
        'finish_credit_status' => 'boolean',
        'retraining_status' => 'boolean',
        'hemis_created_at' => 'datetime',
        'hemis_updated_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'hemis_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'employee_id', 'hemis_id');
    }
}

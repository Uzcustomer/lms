<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'hemis_id',
        'subject_schedule_id',
        'student_id',
        'student_hemis_id',
        'student_name',
        'employee_id',
        'employee_name',
        'subject_id',
        'subject_name',
        'subject_code',
        'education_year_code',
        'education_year_name',
        'education_year_current',
        'semester_code',
        'semester_name',
        'group_id',
        'group_name',
        'education_lang_code',
        'education_lang_name',
        'training_type_code',
        'training_type_name',
        'lesson_pair_code',
        'lesson_pair_name',
        'lesson_pair_start_time',
        'lesson_pair_end_time',
        'absent_on',
        'absent_off',
        'lesson_date',
        'status',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'hemis_id',
        'subject_schedule_id',
        'subject_id',
        'subject_code',
        'subject_name',
        'employee_id',
        'employee_name',
        'education_year_code',
        'education_year_name',
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
        'lesson_date',
        'load',
    ];

    protected $casts = [
        'lesson_date' => 'datetime',
    ];
}

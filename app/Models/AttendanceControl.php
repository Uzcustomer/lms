<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceControl extends Model
{
    use HasFactory, SoftDeletes;

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
        'is_final',
    ];

    protected $casts = [
        'lesson_date' => 'datetime',
        'is_final' => 'boolean',
    ];
}

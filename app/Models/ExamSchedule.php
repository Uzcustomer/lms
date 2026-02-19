<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_hemis_id',
        'specialty_hemis_id',
        'curriculum_hemis_id',
        'semester_code',
        'group_hemis_id',
        'subject_id',
        'subject_name',
        'lesson_start_date',
        'lesson_end_date',
        'oski_date',
        'oski_na',
        'test_date',
        'test_na',
        'education_year',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'lesson_start_date' => 'date',
        'lesson_end_date' => 'date',
        'oski_date' => 'date',
        'oski_na' => 'boolean',
        'test_date' => 'date',
        'test_na' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_hemis_id', 'group_hemis_id');
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_hemis_id', 'curricula_hemis_id');
    }
}

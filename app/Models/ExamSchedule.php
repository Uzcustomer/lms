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
        'oski_date',
        'oski_na',
        'oski_time',
        'oski_resit_date',
        'oski_resit_time',
        'oski_resit2_date',
        'oski_resit2_time',
        'test_date',
        'test_na',
        'test_time',
        'test_resit_date',
        'test_resit_time',
        'test_resit2_date',
        'test_resit2_time',
        'education_year',
        'created_by',
        'updated_by',
        'oski_moodle_synced_at',
        'oski_moodle_response',
        'oski_moodle_error',
        'test_moodle_synced_at',
        'test_moodle_response',
        'test_moodle_error',
    ];

    protected $casts = [
        'oski_date' => 'date',
        'oski_na' => 'boolean',
        'test_date' => 'date',
        'test_na' => 'boolean',
        'oski_resit_date' => 'date',
        'oski_resit2_date' => 'date',
        'test_resit_date' => 'date',
        'test_resit2_date' => 'date',
        'oski_moodle_synced_at' => 'datetime',
        'test_moodle_synced_at' => 'datetime',
        'oski_moodle_response' => 'array',
        'test_moodle_response' => 'array',
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

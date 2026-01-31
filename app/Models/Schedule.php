<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Schedule extends Model
{
    use HasFactory, SoftDeletes;

    //    protected $table = "schedule_reserves";
    protected $guarded = ['id'];

    protected $fillable = [
        'schedule_hemis_id',
        'subject_id',
        'subject_name',
        'subject_code',
        'semester_code',
        'semester_name',
        'education_year_code',
        'education_year_name',
        'education_year_current',
        'group_id',
        'group_name',
        'education_lang_code',
        'education_lang_name',
        'faculty_id',
        'faculty_name',
        'faculty_code',
        'faculty_structure_type_code',
        'faculty_structure_type_name',
        'department_id',
        'department_name',
        'department_code',
        'department_structure_type_code',
        'department_structure_type_name',
        'auditorium_code',
        'auditorium_name',
        'auditorium_type_code',
        'auditorium_type_name',
        'building_id',
        'building_name',
        'training_type_code',
        'training_type_name',
        'lesson_pair_code',
        'lesson_pair_name',
        'lesson_pair_start_time',
        'lesson_pair_end_time',
        'employee_id',
        'employee_name',
        'week_start_time',
        'week_end_time',
        'lesson_date',
        'week_number',
    ];

    protected $casts = [
        'lesson_date' => 'datetime',
        'week_start_time' => 'datetime',
        'week_end_time' => 'datetime',
    ];

    public function subject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'subject_id', 'curriculum_subject_hemis_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_code', 'code');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_hemis_id');
    }

    public function employee()
    {
        return $this->belongsTo(Teacher::class, 'employee_id', 'hemis_id');
    }
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_hemis_id');
    }
    public function studentGrades()
    {
        return $this->hasMany(StudentGrade::class, 'schedule_hemis_id', 'subject_schedule_id');
    }
}

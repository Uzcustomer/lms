<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OraliqNazorat extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'teacher_hemis_id',
        'teacher_id',
        'teacher_name',
        'teacher_short_name',
        'department_hemis_id',
        'department_id',
        'deportment_name',
        'group_hemis_id',
        'group_id',
        'group_name',
        'semester_name',
        'semester_hemis_id',
        'semester_id',
        'subject_hemis_id',
        'subject_id',
        'subject_name',
        'start_date',
        'deadline',
        'file_path',
        'file_original_name',
        "semester_code",
        'grade_teacher',
        'status'
    ];
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_hemis_id', 'hemis_id');
    }
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_hemis_id', 'group_hemis_id');
    }
    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_hemis_id', 'semester_hemis_id');
    }
    public function subject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'subject_hemis_id', 'curriculum_subject_hemis_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

}
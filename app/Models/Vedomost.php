<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vedomost extends Model
{
    protected $fillable = [
        'user_id',
        'department_hemis_id',
        'department_id',
        'deportment_name',
        'semester_name',
        'semester_code',
        'group_name',
        'subject_name',
        "independent_percent",
        "jb_percent",
        "independent_percent_secend",
        "jb_percent_secend",
        "oski_percent",
        "test_percent",
        "type",
        "shakl",
        "oraliq_percent",
        "file_path"
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
    public function dekan()
    {
        return $this->belongsTo(Teacher::class, 'user_id', 'id');
    }
}
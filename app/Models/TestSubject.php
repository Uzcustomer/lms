<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'faculty_hemis_id',
        'faculty_name',
        'department_hemis_id',
        'department_name',
        'specialty_hemis_id',
        'specialty_name',
        'level_code',
        'level_name',
        'teacher_id',
        'teacher_hemis_id',
        'teacher_name',
        'starts_on',
        'ends_on',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_hemis_id', 'department_hemis_id');
    }

    public function faculty()
    {
        return $this->belongsTo(Department::class, 'faculty_hemis_id', 'department_hemis_id');
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class, 'specialty_hemis_id', 'specialty_hemis_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'test_subject_groups', 'test_subject_id', 'group_id')
            ->withPivot(['group_hemis_id', 'group_name'])
            ->withTimestamps();
    }

    public function groupLinks()
    {
        return $this->hasMany(TestSubjectGroup::class);
    }

    public function lessons()
    {
        return $this->hasMany(TestSubjectLesson::class)->orderBy('lesson_date')->orderBy('topic_order');
    }
}

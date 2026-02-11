<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Qaytnoma extends Model
{
    use LogsActivity;

    protected static string $activityModule = 'qaytnoma';
    protected $fillable = [
        'user_id',
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
        "semester_code",
        'status',
        'file_path',
        'shakl',
        'number'
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
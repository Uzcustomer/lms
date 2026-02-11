<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Independent extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'independent';
    protected $fillable = [
        'user_id',
        'teacher_hemis_id',
        'teacher_name',
        'teacher_short_name',
        'department_hemis_id',
        'deportment_name',
        'group_hemis_id',
        'group_name',
        'semester_name',
        'semester_hemis_id',
        'subject_hemis_id',
        'subject_name',
        'start_date',
        'deadline',
        'file_path',
        'file_original_name',
        "semester_code",
        'grade_teacher',
        'status',
        'schedule_id'
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

    public function submissions()
    {
        return $this->hasMany(IndependentSubmission::class);
    }

    public function submissionByStudent($studentId)
    {
        return $this->submissions()->where('student_id', $studentId)->first();
    }
}
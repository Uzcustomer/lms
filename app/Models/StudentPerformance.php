<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentPerformance extends Model
{
    use HasFactory;


    protected $fillable = [
        'student_id',
        'hemis_id',
        'subject_name',
        'subject_code',
        'semester_code',
        'training_type',
        'teacher_name',
        'grade',
        'lesson_date',
        'reason',
        'deadline',
        'status',
        'retake_score'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeLowGradeOrAbsence($query)
    {
        return $query->where('reason', 'absence')->orWhere('grade', '<', 60);
    }

    public function setDeadlineAttribute($value)
    {
        $this->attributes['deadline'] = now()->addWeek();
    }

    public function isRetakeAllowed()
    {
        return $this->grade < 60;
    }
}

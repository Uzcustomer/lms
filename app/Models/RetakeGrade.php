<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeGrade extends Model
{
    protected $fillable = [
        'retake_group_id',
        'application_id',
        'student_hemis_id',
        'lesson_date',
        'grade',
        'comment',
        'graded_by_user_id',
        'graded_by_name',
        'graded_at',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'grade' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function retakeGroup()
    {
        return $this->belongsTo(RetakeGroup::class, 'retake_group_id');
    }

    public function application()
    {
        return $this->belongsTo(RetakeApplication::class, 'application_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

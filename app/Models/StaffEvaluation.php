<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffEvaluation extends Model
{
    protected $fillable = [
        'teacher_id',
        'student_id',
        'rating',
        'comment',
        'ip_address',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

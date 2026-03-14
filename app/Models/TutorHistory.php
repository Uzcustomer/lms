<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutorHistory extends Model
{
    protected $table = 'tutor_history';

    protected $fillable = [
        'student_id',
        'teacher_id',
        'teacher_name',
        'group_hemis_id',
        'group_name',
        'assigned_at',
        'removed_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}

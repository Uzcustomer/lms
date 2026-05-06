<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeMustaqilSubmission extends Model
{
    public const MAX_FILE_MB = 5;

    protected $fillable = [
        'retake_group_id',
        'application_id',
        'student_hemis_id',
        'file_path',
        'original_filename',
        'student_comment',
        'submitted_at',
        'grade',
        'teacher_comment',
        'graded_by_user_id',
        'graded_by_name',
        'graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'grade' => 'decimal:2',
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

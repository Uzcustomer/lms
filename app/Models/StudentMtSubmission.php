<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentMtSubmission extends Model
{
    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'subject_id',
        'subject_name',
        'semester_code',
        'file_path',
        'file_name',
        'file_size',
        'status',
        'teacher_comment',
    ];
}

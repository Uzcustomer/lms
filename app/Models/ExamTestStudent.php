<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamTestStudent extends Model
{
    protected $fillable = [
        'exam_test_id',
        'student_hemis_id',
        'file_path',
        'file_original_name',
    ];
}
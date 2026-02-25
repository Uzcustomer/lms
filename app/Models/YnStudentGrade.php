<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YnStudentGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'yn_submission_id',
        'student_hemis_id',
        'jn',
        'mt',
    ];

    public function ynSubmission()
    {
        return $this->belongsTo(YnSubmission::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

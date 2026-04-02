<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSubject extends Model
{
    protected $fillable = [
        'student_hemis_id',
        'curriculum_subject_hemis_id',
        'subject_id',
        'semester_id',
        'subject_name',
        'education_year',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }

    public function curriculumSubject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'curriculum_subject_hemis_id', 'curriculum_subject_hemis_id');
    }
}

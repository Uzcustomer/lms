<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurriculumSubject extends Model
{

    use HasFactory;

    protected $fillable = [
        'curriculum_subject_hemis_id',
        'curricula_hemis_id',
        'subject_id',
        'subject_name',
        'subject_code',
        'subject_type_code',
        'subject_type_name',
        'subject_block_code',
        'subject_block_name',
        'semester_code',
        'semester_name',
        'total_acload',
        'credit',
        'in_group',
        'at_semester',
        'subject_details',
        'subject_exam_types',
        'rating_grade_code',
        'rating_grade_name',
        'exam_finish_code',
        'exam_finish_name',
        'department_id',
        'department_name',
    ];

    protected $casts = [
        'total_acload' => 'decimal:2',
        'credit' => 'decimal:2',
        'at_semester' => 'boolean',
        'subject_details' => 'array',
        'subject_exam_types' => 'array',
    ];

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curricula_hemis_id', 'curricula_hemis_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_code', 'code');
    }
}

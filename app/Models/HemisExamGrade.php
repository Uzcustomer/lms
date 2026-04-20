<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HemisExamGrade extends Model
{
    protected $fillable = [
        'hemis_record_id',
        'student_hemis_id',
        'subject_id',
        'semester_code',
        'education_year',
        'exam_type_code',
        'exam_type_name',
        'final_exam_type_code',
        'final_exam_type_name',
        'grade',
        'regrade',
        'exam_date',
        'employee_hemis_id',
        'exam_schedule_id',
        'hemis_updated_at',
    ];

    protected $casts = [
        'grade' => 'integer',
        'regrade' => 'integer',
        'exam_date' => 'datetime',
    ];

    /**
     * Taqqoslash uchun: berilgan talabalar, fan va semestr bo'yicha HEMIS baholarini olish.
     */
    public function scopeForComparison($query, array $studentHemisIds, string $subjectId, string $semesterCode)
    {
        return $query->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode);
    }
}

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
        'hemis_created_at',
        'first_seen_at',
    ];

    protected $casts = [
        'grade' => 'integer',
        'regrade' => 'integer',
        'exam_date' => 'datetime',
        'first_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Monitoring Q-1: baho mark'ga birinchi marta qachon kelganini saqlab qo'yamiz
        // (HEMIS created_at qaytarmasa, kiritilgan sana shundan olinadi).
        static::creating(function (self $grade) {
            $grade->first_seen_at ??= now();
        });
    }

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

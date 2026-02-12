<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HemisQuizResult extends Model
{
    protected $table = 'hemis_quiz_results';

    protected $fillable = [
        'attempt_id',
        'date_start',
        'date_finish',
        'category_path',
        'category_id',
        'category_name',
        'faculty',
        'direction',
        'semester',
        'student_id',
        'student_name',
        'fan_id',
        'fan_name',
        'quiz_type',
        'attempt_name',
        'shakl',
        'attempt_number',
        'grade',
        'old_grade',
        'course_id',
        'course_idnumber',
        'is_valid_format',
        'synced_at',
        'is_active',
    ];

    protected $casts = [
        'date_start' => 'datetime',
        'date_finish' => 'datetime',
        'grade' => 'decimal:2',
        'old_grade' => 'decimal:2',
        'synced_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'hemis_id');
    }
}

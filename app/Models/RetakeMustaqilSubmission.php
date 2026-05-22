<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeMustaqilSubmission extends Model
{
    public const MAX_FILE_MB = 5;

    /** Mustaqil ta'lim uchun eng ko'p urinishlar soni. */
    public const MAX_ATTEMPTS = 3;

    /** O'tish bahosi — shu yoki undan yuqori bo'lsa qayta yuklash shart emas. */
    public const PASS_GRADE = 60;

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
        'attempt_count',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'grade' => 'decimal:2',
        'attempt_count' => 'integer',
    ];

    /** Talaba o'tib bo'lganmi (baho 60+)? */
    public function isPassed(): bool
    {
        return $this->grade !== null && (float) $this->grade >= self::PASS_GRADE;
    }

    /** Urinishlar tugaganmi (3 marta)? */
    public function attemptsExhausted(): bool
    {
        return (int) $this->attempt_count >= self::MAX_ATTEMPTS;
    }

    /** Yana qayta yuklash mumkinmi (o'tmagan + urinish qolgan)? */
    public function canResubmit(): bool
    {
        return !$this->isPassed() && !$this->attemptsExhausted();
    }

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

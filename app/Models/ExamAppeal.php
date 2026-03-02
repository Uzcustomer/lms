<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAppeal extends Model
{
    protected $fillable = [
        'student_id',
        'student_grade_id',
        'subject_name',
        'subject_id',
        'training_type_code',
        'training_type_name',
        'current_grade',
        'employee_name',
        'exam_date',
        'reason',
        'file_path',
        'file_original_name',
        'status',
        'reviewed_by',
        'reviewed_by_name',
        'review_comment',
        'new_grade',
        'reviewed_at',
    ];

    protected $casts = [
        'exam_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING => 'Kutilmoqda',
        self::STATUS_REVIEWING => 'Ko\'rib chiqilmoqda',
        self::STATUS_APPROVED => 'Qabul qilindi',
        self::STATUS_REJECTED => 'Rad etildi',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDING => 'yellow',
        self::STATUS_REVIEWING => 'blue',
        self::STATUS_APPROVED => 'green',
        self::STATUS_REJECTED => 'red',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function studentGrade()
    {
        return $this->belongsTo(StudentGrade::class);
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }
}

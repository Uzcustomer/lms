<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPhoto extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'student_id_number',
        'full_name',
        'group_name',
        'semester_name',
        'uploaded_by',
        'uploaded_by_teacher_id',
        'photo_path',
        'status',
        'reviewed_by_name',
        'reviewed_at',
        'rejection_reason',
        'similarity_score',
        'similarity_status',
        'similarity_checked_at',
        'quality_score',
        'quality_passed',
        'quality_issues',
        'quality_ok',
        'quality_checked_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'similarity_checked_at' => 'datetime',
        'similarity_score' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'quality_passed' => 'boolean',
        'quality_issues' => 'array',
        'quality_ok' => 'array',
        'quality_checked_at' => 'datetime',
    ];

    public function getPhotoUrlAttribute(): string
    {
        return asset($this->photo_path);
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id_number', 'student_id_number');
    }

    public function uploader()
    {
        return $this->belongsTo(Teacher::class, 'uploaded_by_teacher_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

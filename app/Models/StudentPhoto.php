<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentPhoto extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const SOURCE_TEACHER_UPLOAD = 'teacher_upload';
    public const SOURCE_REGISTRATOR_WEBCAM = 'registrator_webcam';

    protected $fillable = [
        'student_id_number',
        'full_name',
        'group_name',
        'semester_name',
        'uploaded_by',
        'uploaded_by_teacher_id',
        'photo_path',
        'source',
        'status',
        'reviewed_by_name',
        'reviewed_at',
        'rejection_reason',
        'similarity_score',
        'similarity_status',
        'similarity_checked_at',
        'similarity_hemis',
        'similarity_mark',
        'captured_by_user_id',
        'quality_score',
        'quality_passed',
        'quality_issues',
        'quality_ok',
        'quality_checked_at',
        'face_embedding',
        'embedding_extracted_at',
        'moodle_synced_at',
        'moodle_sync_status',
        'moodle_sync_error',
        'moodle_file_hash',
        'moodle_response',
        'descriptor_confirmed_at',
        'descriptor_confirmed_notified_at',
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
        'face_embedding' => 'array',
        'embedding_extracted_at' => 'datetime',
        'similarity_hemis' => 'decimal:2',
        'similarity_mark' => 'decimal:2',
        'moodle_synced_at' => 'datetime',
        'moodle_response' => 'array',
        'descriptor_confirmed_at' => 'datetime',
        'descriptor_confirmed_notified_at' => 'datetime',
    ];

    public function isDescriptorConfirmed(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->descriptor_confirmed_at !== null;
    }

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

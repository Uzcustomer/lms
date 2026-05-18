<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetakeGroup extends Model
{
    use SoftDeletes;

    public const STATUS_FORMING = 'forming';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const ASSESSMENT_OSKE = 'oske';
    public const ASSESSMENT_TEST = 'test';
    public const ASSESSMENT_OSKE_TEST = 'oske_test';
    public const ASSESSMENT_SINOV_FAN = 'sinov_fan';

    protected $fillable = [
        'name',
        'subject_id',
        'subject_name',
        'subject_code',
        'semester_id',
        'semester_name',
        'teacher_id',
        'teacher_name',
        'teacher_phones',
        'start_date',
        'end_date',
        'max_students',
        'status',
        'created_by_user_id',
        'created_by_name',
        'assessment_type',
        'oske_date',
        'test_date',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
        'locked_by_name',
        'vedomost_path',
        'vedomost_generated_at',
        'sent_to_test_markazi_at',
        'sent_to_test_markazi_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_students' => 'integer',
        'teacher_phones' => 'array',
        'oske_date' => 'date',
        'test_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'vedomost_generated_at' => 'datetime',
        'sent_to_test_markazi_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Teacher::class, 'created_by_user_id');
    }

    public function applications()
    {
        return $this->hasMany(RetakeApplication::class, 'retake_group_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_FORMING, self::STATUS_SCHEDULED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_FORMING => 'Shakllantirilmoqda',
            self::STATUS_SCHEDULED => 'Tasdiqlangan, kutilmoqda',
            self::STATUS_IN_PROGRESS => 'Boradi',
            self::STATUS_COMPLETED => 'Tugagan',
            default => $this->status,
        };
    }
}

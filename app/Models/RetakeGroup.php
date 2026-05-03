<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeGroup extends Model
{
    public const STATUS_FORMING = 'forming';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'name',
        'subject_id',
        'subject_name',
        'subject_code',
        'semester_id',
        'semester_name',
        'teacher_id',
        'teacher_name',
        'start_date',
        'end_date',
        'max_students',
        'status',
        'created_by_user_id',
        'created_by_name',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'max_students' => 'integer',
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

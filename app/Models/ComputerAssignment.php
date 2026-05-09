<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComputerAssignment extends Model
{
    protected $fillable = [
        'exam_schedule_id',
        'student_id_number',
        'student_hemis_id',
        'yn_type',
        'computer_number',
        'planned_start',
        'planned_end',
        'reveal_at',
        'reveal_notified',
        'approach_notified',
        'ready_notified',
        'is_reserve',
        'moved_from_computer',
        'moved_reason',
        'actual_start',
        'actual_end',
        'status',
        'moodle_attempt_id',
        'history',
    ];

    protected $casts = [
        'planned_start' => 'datetime',
        'planned_end' => 'datetime',
        'reveal_at' => 'datetime',
        'reveal_notified' => 'boolean',
        'approach_notified' => 'boolean',
        'ready_notified' => 'boolean',
        'is_reserve' => 'boolean',
        'moved_from_computer' => 'integer',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'computer_number' => 'integer',
        'history' => 'array',
    ];

    public function isComputerRevealed(?\Carbon\Carbon $now = null): bool
    {
        $now ??= now();
        return $this->reveal_at !== null && $now->greaterThanOrEqualTo($this->reveal_at);
    }

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_MOVED = 'moved';

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }

    public function isOccupiesComputerNow(): bool
    {
        return in_array($this->status, [self::STATUS_IN_PROGRESS], true);
    }
}

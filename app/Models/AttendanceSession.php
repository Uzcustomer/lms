<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'teacher_id', 'teacher_hemis_id', 'subject_id', 'subject_name', 'semester_code',
        'lesson_date', 'lesson_pair_code', 'lesson_pair_name', 'training_type_name',
        'auditorium_code', 'auditorium_name', 'beacon_id',
        'opened_at', 'closes_at', 'closed_at', 'status',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'opened_at' => 'datetime',
        'closes_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function beacon()
    {
        return $this->belongsTo(Beacon::class);
    }

    public function groups()
    {
        return $this->hasMany(AttendanceSessionGroup::class, 'session_id');
    }

    public function confirmations()
    {
        return $this->hasMany(AttendanceConfirmation::class, 'session_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN)->where('closes_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->closes_at->isFuture();
    }

    /** Window elapsed but nobody closed it: settle pending students as absent. */
    public function closeIfExpired(): void
    {
        if ($this->status === self::STATUS_OPEN && $this->closes_at->isPast()) {
            $this->close('system');
        }
    }

    public function close(string $decidedBy = 'teacher'): void
    {
        $this->confirmations()
            ->where('status', AttendanceConfirmation::STATUS_PENDING)
            ->update(['status' => AttendanceConfirmation::STATUS_ABSENT, 'decided_by' => $decidedBy]);

        $this->update(['status' => self::STATUS_CLOSED, 'closed_at' => now()]);
    }

    public function toSummary(): array
    {
        $counts = $this->confirmations()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'id' => $this->id,
            'subject_id' => $this->subject_id,
            'subject_name' => $this->subject_name,
            'lesson_date' => $this->lesson_date->format('Y-m-d'),
            'lesson_pair_code' => $this->lesson_pair_code,
            'lesson_pair_name' => $this->lesson_pair_name,
            'training_type_name' => $this->training_type_name,
            'auditorium_code' => $this->auditorium_code,
            'auditorium_name' => $this->auditorium_name,
            'beacon' => $this->beacon?->toApi(),
            'group_names' => $this->groups->pluck('group_name')->filter()->values(),
            'status' => $this->isOpen() ? self::STATUS_OPEN : self::STATUS_CLOSED,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closes_at' => $this->closes_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'seconds_left' => $this->isOpen() ? max(0, (int) now()->diffInSeconds($this->closes_at, false)) : 0,
            'total' => (int) $counts->sum(),
            'present' => (int) ($counts[AttendanceConfirmation::STATUS_PRESENT] ?? 0),
            'absent' => (int) ($counts[AttendanceConfirmation::STATUS_ABSENT] ?? 0),
            'pending' => (int) ($counts[AttendanceConfirmation::STATUS_PENDING] ?? 0),
        ];
    }
}

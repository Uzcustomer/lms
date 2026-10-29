<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Talabaning qayta topshirish (otrabotka) uchun tanlagan vaqti.
 * id Moodle'ga booking_id bo'lib boradi.
 */
class RetakeBooking extends Model
{
    public const STATUS_BOOKED = 'booked';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_MISSED = 'missed';
    public const STATUS_REJECTED = 'rejected';

    public const SYNC_PENDING = 'pending';
    public const SYNC_OK = 'ok';
    public const SYNC_FAILED = 'failed';

    protected $fillable = [
        'student_hemis_id', 'subject_id', 'lesson_date', 'grade_id',
        'subject_name', 'mavzu', 'reason', 'deadline',
        'slot_start', 'slot_end',
        'status', 'moodle_sync', 'sync_attempts', 'moodle_code', 'moodle_message',
        'synced_at', 'cancelled_at',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'date',
        'slot_start' => 'datetime',
        'slot_end' => 'datetime',
        'synced_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function scopeForDebt($query, int $studentHemisId, int $subjectId, string $lessonDate)
    {
        return $query->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->whereDate('lesson_date', $lessonDate);
    }
}

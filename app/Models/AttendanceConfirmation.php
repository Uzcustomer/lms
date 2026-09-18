<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceConfirmation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';

    protected $fillable = [
        'session_id', 'student_id', 'student_hemis_id', 'status', 'decided_by',
        'beacon_seen', 'rssi', 'notified', 'confirmed_at',
        'face_verified', 'face_similarity', 'face_note', 'face_checked_at',
    ];

    protected $casts = [
        'beacon_seen' => 'boolean',
        'notified' => 'boolean',
        'rssi' => 'integer',
        'confirmed_at' => 'datetime',
        'face_verified' => 'boolean',
        'face_similarity' => 'float',
        'face_checked_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

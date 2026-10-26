<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** HEMIS solishtirishdagi to'siq bo'yicha so'rov (o'qituvchi / kafedra mudiri javobi va hal qilinishi). */
class TimetableBlockTicket extends Model
{
    public const OPEN = 'open';
    public const RESOLVED = 'resolved';
    public const PENDING_REGISTRAR = 'pending_registrar';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'board_id', 'card_id', 'week', 'lesson_date', 'start_time', 'end_time', 'block_type', 'block_text', 'blocker',
        'group_name', 'subject_name', 'kafedra_name', 'status', 'teacher_id', 'head_id', 'recipients', 'resolution',
        'change_request_id', 'created_by_id', 'created_by_name', 'resolved_at',
    ];

    protected $casts = [
        'blocker' => 'array',
        'recipients' => 'array',
        'resolution' => 'array',
        'lesson_date' => 'date:Y-m-d',
        'resolved_at' => 'datetime',
    ];

    public function card()
    {
        return $this->belongsTo(TimetableCard::class, 'card_id');
    }

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }

    /** So'rov hali hal qilinmagan (o'zgartirishga ruxsat shu holatda). */
    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}

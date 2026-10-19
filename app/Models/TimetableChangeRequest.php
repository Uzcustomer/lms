<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableChangeRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'board_id', 'requester_id', 'requester_name', 'kafedra_name', 'route_name', 'target_key',
        'params', 'payload', 'kind', 'subject_name', 'summary', 'details', 'status',
        'reviewer_id', 'reviewer_name', 'review_note', 'error', 'reviewed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'payload' => 'array',
        'details' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }

    public function requester()
    {
        return $this->belongsTo(Teacher::class, 'requester_id');
    }
}

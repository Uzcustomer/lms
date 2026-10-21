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
    /** Tasdiqsiz, darhol qo'llangan o'zgarish (faqat tarix uchun). */
    public const APPLIED = 'applied';

    protected $fillable = [
        'board_id', 'requester_id', 'requester_type', 'requester_name', 'requester_role', 'kafedra_name', 'route_name', 'target_key',
        'params', 'payload', 'kind', 'subject_name', 'summary', 'details', 'status',
        'approval_stage', 'department_hemis_id', 'head_reviewer_id', 'head_reviewer_name', 'head_reviewed_at',
        'reviewer_id', 'reviewer_name', 'review_note', 'error', 'reviewed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'payload' => 'array',
        'details' => 'array',
        'reviewed_at' => 'datetime',
        'head_reviewed_at' => 'datetime',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }

    /** O'zgarishni kiritgan foydalanuvchi: odatda Teacher, admin — User. */
    public function requester()
    {
        return $this->morphTo();
    }

    public function isBy($actor): bool
    {
        return $actor && $this->requester_type === $actor->getMorphClass()
            && (int) $this->requester_id === (int) $actor->getKey();
    }
}

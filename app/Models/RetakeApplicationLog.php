<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetakeApplicationLog extends Model
{
    public const UPDATED_AT = null; // faqat created_at

    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_DEAN_APPROVED = 'dean_approved';
    public const ACTION_DEAN_REJECTED = 'dean_rejected';
    public const ACTION_REGISTRAR_APPROVED = 'registrar_approved';
    public const ACTION_REGISTRAR_REJECTED = 'registrar_rejected';
    public const ACTION_ACADEMIC_APPROVED = 'academic_approved';
    public const ACTION_ACADEMIC_REJECTED = 'academic_rejected';
    public const ACTION_AUTO_CANCELLED_HEMIS = 'auto_cancelled_hemis';
    public const ACTION_GROUP_ASSIGNED = 'group_assigned';
    public const ACTION_STATUS_CHANGED = 'status_changed';

    protected $fillable = [
        'application_id',
        'group_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(RetakeApplication::class, 'application_id');
    }

    public function applicationGroup()
    {
        return $this->belongsTo(RetakeApplicationGroup::class, 'group_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

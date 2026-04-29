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
        'user_type',
        'user_name',
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

    /**
     * Polymorphic-ish user relation: user_type'ga qarab Teacher yoki Student.
     * Bevosita relation o'rniga oddiy resolve metodi.
     */
    public function actor()
    {
        return match ($this->user_type) {
            'teacher' => Teacher::find($this->user_id),
            'student' => Student::find($this->user_id),
            default => null,
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KtrChangeRequest extends Model
{
    protected $fillable = [
        'curriculum_subject_id',
        'requested_by',
        'requested_by_guard',
        'status',
        'draft_week_count',
        'draft_plan_data',
    ];

    protected $casts = [
        'draft_plan_data' => 'array',
    ];

    public function approvals()
    {
        return $this->hasMany(KtrChangeApproval::class, 'change_request_id');
    }

    public function curriculumSubject()
    {
        return $this->belongsTo(CurriculumSubject::class);
    }

    /**
     * Barcha tasdiqlar qabul qilinganmi?
     */
    public function isFullyApproved(): bool
    {
        return $this->approvals()->count() > 0
            && $this->approvals()->where('status', '!=', 'approved')->doesntExist();
    }
}

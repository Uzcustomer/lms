<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndividualScheduleAudit extends Model
{
    protected $fillable = [
        'actor_user_id',
        'actor_guard',
        'actor_name',
        'actor_role',
        'student_hemis_id',
        'student_name',
        'group_hemis_id',
        'subject_id',
        'subject_name',
        'semester_code',
        'attempt',
        'yn_type',
        'action',
        'old_date',
        'old_time',
        'new_date',
        'new_time',
        'note',
        'override_warning',
        'eligibility_snapshot',
    ];

    protected $casts = [
        'old_date' => 'date',
        'new_date' => 'date',
        'override_warning' => 'boolean',
        'eligibility_snapshot' => 'array',
        'attempt' => 'integer',
    ];
}

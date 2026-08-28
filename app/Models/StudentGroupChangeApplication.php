<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGroupChangeApplication extends Model
{
    protected $fillable = [
        'student_id',
        'source_group_id',
        'target_group_id',
        'student_name',
        'student_id_number',
        'faculty_name',
        'specialty_name',
        'course',
        'source_group_name',
        'target_group_name',
        'reason',
        'status',
        'review_note',
        'reviewed_by_id',
        'reviewed_at',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'source_group_id' => 'integer',
        'target_group_id' => 'integer',
        'course' => 'integer',
        'reviewed_by_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function sourceGroup()
    {
        return $this->belongsTo(StudentDistributionGroup::class, 'source_group_id');
    }

    public function targetGroup()
    {
        return $this->belongsTo(StudentDistributionGroup::class, 'target_group_id');
    }
}

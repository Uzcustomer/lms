<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentDistributionAssignment extends Model
{
    protected $fillable = [
        'student_id', 'source_group_id', 'target_group_id',
        'original_group_hemis_id', 'original_group_name',
        'student_name', 'student_id_number', 'assigned_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'source_group_id' => 'integer',
        'target_group_id' => 'integer',
        'assigned_by' => 'integer',
    ];

    public function sourceGroup()
    {
        return $this->belongsTo(StudentDistributionGroup::class, 'source_group_id');
    }

    public function targetGroup()
    {
        return $this->belongsTo(StudentDistributionGroup::class, 'target_group_id');
    }
}

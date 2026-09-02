<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionSourceGroup extends Model
{
    protected $table = 'distribution_source_groups';

    protected $fillable = [
        'group_hemis_id',
        'group_name',
        'faculty_name',
        'specialty_name',
        'level_code',
        'student_count',
        'selected_by',
    ];

    protected $casts = [
        'group_hemis_id' => 'integer',
        'student_count' => 'integer',
        'selected_by' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_hemis_id', 'group_hemis_id');
    }
}

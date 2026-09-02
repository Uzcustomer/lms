<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Talabaning guruh tanlash ovozi. Har talaba bir marta ovoz beradi;
 * registrator tasdiqlagach reja (draft assignment) ga aylanadi.
 */
class DistributionVote extends Model
{
    protected $table = 'distribution_votes';

    protected $fillable = [
        'student_id', 'from_group_hemis_id', 'to_group_hemis_id',
        'student_name', 'student_id_number', 'from_group_name', 'to_group_name',
        'status', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'from_group_hemis_id' => 'integer',
        'to_group_hemis_id' => 'integer',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

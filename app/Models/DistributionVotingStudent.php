<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Alohida talabaga berilgan ovoz ruxsati (modal ichidan tanlab beriladi). */
class DistributionVotingStudent extends Model
{
    protected $table = 'distribution_voting_students';

    protected $fillable = ['student_id', 'group_hemis_id', 'opened_by'];

    protected $casts = [
        'student_id' => 'integer',
        'group_hemis_id' => 'integer',
        'opened_by' => 'integer',
    ];
}

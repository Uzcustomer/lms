<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ovoz berish ochilgan guruh — yozuv bor bo'lsa, guruh talabalari ovoz beradi. */
class DistributionVotingGroup extends Model
{
    protected $table = 'distribution_voting_groups';

    protected $fillable = ['group_hemis_id', 'group_name', 'opened_by'];

    protected $casts = [
        'group_hemis_id' => 'integer',
        'opened_by' => 'integer',
    ];
}

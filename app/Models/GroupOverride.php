<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupOverride extends Model
{
    protected $fillable = [
        'group_hemis_id',
        'group_name',
        'lang',
        'excluded',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'excluded' => 'boolean',
    ];
}

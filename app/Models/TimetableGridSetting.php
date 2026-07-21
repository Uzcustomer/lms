<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableGridSetting extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'days', 'pairs_per_day', 'weeks',
    ];
}

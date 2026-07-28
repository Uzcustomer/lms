<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableGridSetting extends Model
{
    protected $fillable = [
        'board_id', 'faculty_name', 'specialty_name', 'course', 'days', 'pairs_per_day', 'weeks',
    ];
}

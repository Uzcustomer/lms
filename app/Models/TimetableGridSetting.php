<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableGridSetting extends Model
{
    protected $fillable = [
        'board_id', 'faculty_name', 'specialty_name', 'course', 'days', 'pairs_per_day', 'weeks', 'start_date', 'curriculum_hemis_id', 'weekly_breaks',
    ];

    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'curriculum_hemis_id' => 'integer',
        'weekly_breaks' => 'array',
    ];
}

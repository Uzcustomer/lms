<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCapacityOverride extends Model
{
    protected $fillable = [
        'date',
        'work_hours_start',
        'work_hours_end',
        'lunch_start',
        'lunch_end',
        'computer_count',
        'test_duration_minutes',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'computer_count' => 'integer',
        'test_duration_minutes' => 'integer',
    ];
}

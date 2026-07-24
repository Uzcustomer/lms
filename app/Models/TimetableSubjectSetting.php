<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fan bo'yicha jadval rejimi (hafta almashinuvi / sikl).
 * @see database/migrations/2026_07_23_140000_create_timetable_subject_settings.php
 */
class TimetableSubjectSetting extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'subject_name',
        'mode', 'rotation_group', 'occurrences', 'cycle_weeks', 'note',
    ];

    protected $casts = [
        'course'      => 'integer',
        'occurrences' => 'integer',
        'cycle_weeks' => 'integer',
    ];

    public const MODES = ['normal', 'alternate', 'cycle'];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }
}

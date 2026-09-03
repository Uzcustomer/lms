<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCyclePlacement extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'group_name',
        'subject_name', 'start_index',
        'teacher_id', 'teacher_name', 'lesson_time',
        'auditorium_code', 'auditorium_name',
    ];

    protected $casts = [
        'course' => 'integer',
        'start_index' => 'integer',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }
}

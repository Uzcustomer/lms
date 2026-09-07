<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimetableCyclePlacement extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'group_name',
        'subject_name', 'start_index', 'lesson_time',
        'training_type', 'pair', 'lecture_slots',
    ];

    protected $casts = [
        'course' => 'integer',
        'start_index' => 'integer',
        'pair' => 'integer',
        // {"kun_siljishi": [soatlar]} — blok ichida ma'ruza belgilangan kataklar
        'lecture_slots' => 'array',
    ];

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }
}

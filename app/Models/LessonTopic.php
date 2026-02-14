<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonTopic extends Model
{
    protected $fillable = [
        'group_hemis_id',
        'subject_id',
        'semester_code',
        'lesson_date',
        'topic_hemis_id',
        'topic_name',
        'assigned_by_id',
        'assigned_by_guard',
    ];

    protected $casts = [
        'lesson_date' => 'date',
    ];
}

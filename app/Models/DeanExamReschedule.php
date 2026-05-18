<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeanExamReschedule extends Model
{
    protected $fillable = [
        'exam_schedule_id',
        'yn_type',
        'used_date',
        'original_time',
        'new_time',
        'student_count',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'used_date' => 'date',
        'student_count' => 'integer',
    ];

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}

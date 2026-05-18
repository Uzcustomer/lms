<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeanExamReschedule extends Model
{
    protected $fillable = [
        'exam_schedule_id',
        'computer_assignment_id',
        'student_hemis_id',
        'yn_type',
        'used_date',
        'original_start',
        'original_end',
        'original_computer',
        'new_start',
        'new_end',
        'new_computer',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'used_date' => 'date',
        'original_start' => 'datetime',
        'original_end' => 'datetime',
        'new_start' => 'datetime',
        'new_end' => 'datetime',
        'original_computer' => 'integer',
        'new_computer' => 'integer',
    ];

    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function computerAssignment(): BelongsTo
    {
        return $this->belongsTo(ComputerAssignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

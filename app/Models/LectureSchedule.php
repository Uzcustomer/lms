<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LectureSchedule extends Model
{
    protected $fillable = [
        'batch_id',
        'week_day',
        'lesson_pair_code',
        'lesson_pair_name',
        'lesson_pair_start_time',
        'lesson_pair_end_time',
        'group_name',
        'group_id',
        'group_source',
        'subject_name',
        'subject_id',
        'employee_name',
        'employee_id',
        'auditorium_name',
        'floor',
        'building_name',
        'training_type_name',
        'weeks',
        'week_parity',
        'hemis_status',
        'hemis_diff',
        'has_conflict',
        'conflict_details',
    ];

    protected $casts = [
        'hemis_diff' => 'array',
        'conflict_details' => 'array',
        'has_conflict' => 'boolean',
    ];

    public const WEEK_DAYS = [
        1 => 'Dushanba',
        2 => 'Seshanba',
        3 => 'Chorshanba',
        4 => 'Payshanba',
        5 => 'Juma',
        6 => 'Shanba',
    ];

    public const PAIR_TIMES = [
        1 => ['start' => '08:00', 'end' => '09:20'],
        2 => ['start' => '09:30', 'end' => '10:50'],
        3 => ['start' => '11:00', 'end' => '12:20'],
        4 => ['start' => '13:00', 'end' => '14:20'],
        5 => ['start' => '14:30', 'end' => '15:50'],
        6 => ['start' => '16:00', 'end' => '17:20'],
        7 => ['start' => '17:30', 'end' => '18:50'],
    ];

    public function batch()
    {
        return $this->belongsTo(LectureScheduleBatch::class, 'batch_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'group_hemis_id');
    }

    public function employee()
    {
        return $this->belongsTo(Teacher::class, 'employee_id', 'hemis_id');
    }

    public function getWeekDayNameAttribute(): string
    {
        return self::WEEK_DAYS[$this->week_day] ?? '';
    }
}

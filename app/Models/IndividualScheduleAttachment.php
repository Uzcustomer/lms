<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IndividualScheduleAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'exam_schedule_id',
        'student_hemis_id',
        'subject_id',
        'semester_code',
        'original_filename',
        'storage_path',
        'mime_type',
        'size_bytes',
        'uploaded_by_user_id',
        'uploaded_by_guard',
        'uploaded_by_name',
        'note',
    ];

    public function examSchedule()
    {
        return $this->belongsTo(ExamSchedule::class);
    }
}

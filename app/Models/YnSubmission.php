<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YnSubmission extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'yn_submission';

    protected $fillable = [
        'subject_id',
        'semester_code',
        'group_hemis_id',
        'submitted_by',
        'submitted_by_guard',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function submittedBy()
    {
        if ($this->submitted_by_guard === 'teacher') {
            return $this->belongsTo(Teacher::class, 'submitted_by');
        }

        return $this->belongsTo(User::class, 'submitted_by');
    }
}

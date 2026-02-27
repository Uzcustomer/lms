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
        'submitted_at',
        'exam_date',
        'results_fetched',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'exam_date' => 'date',
        'results_fetched' => 'boolean',
    ];

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function studentGrades()
    {
        return $this->hasMany(YnStudentGrade::class);
    }
}

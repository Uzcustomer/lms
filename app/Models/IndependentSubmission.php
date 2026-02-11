<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndependentSubmission extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'independent_submission';

    protected $fillable = [
        'independent_id',
        'student_id',
        'student_hemis_id',
        'file_path',
        'file_original_name',
        'submitted_at',
        'submission_count',
        'viewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function independent()
    {
        return $this->belongsTo(Independent::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class YnConsent extends Model
{
    use HasFactory, LogsActivity;

    protected static string $activityModule = 'yn_consent';

    protected $fillable = [
        'student_hemis_id',
        'subject_id',
        'semester_code',
        'group_hemis_id',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

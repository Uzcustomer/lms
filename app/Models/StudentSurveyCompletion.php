<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSurveyCompletion extends Model
{
    protected $table = 'student_survey_completions';

    protected $fillable = [
        'survey_key',
        'student_hemis_id',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];
}

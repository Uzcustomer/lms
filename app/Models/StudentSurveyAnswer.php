<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSurveyAnswer extends Model
{
    protected $table = 'student_survey_answers';

    protected $fillable = [
        'survey_key',
        'session_token',
        'question_id',
        'answer',
        'answer_multi',
    ];

    protected $casts = [
        'answer_multi' => 'array',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestAnswer extends Model
{
    protected $fillable = [
        'attempt_id',
        'question_id',
        'selected_option_id',
        'answer_text',
        'is_correct',
        'points_earned',
        'answered_at',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'answered_at' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(TestSubjectLessonTestAttempt::class, 'attempt_id');
    }

    public function question()
    {
        return $this->belongsTo(TestSubjectLessonTestQuestion::class, 'question_id');
    }

    public function selectedOption()
    {
        return $this->belongsTo(TestSubjectLessonTestOption::class, 'selected_option_id');
    }
}

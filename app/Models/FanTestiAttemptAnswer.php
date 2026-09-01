<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FanTestiAttemptAnswer extends Model
{
    protected $table = 'fan_testi_attempt_answers';

    protected $fillable = [
        'attempt_id', 'question_index', 'question_type', 'question_prompt',
        'selected_option_index', 'selected_option_text', 'answer_text',
        'correct_answer_text', 'is_correct', 'points_earned', 'points_possible',
        'answered_at',
    ];

    protected $casts = [
        'attempt_id' => 'integer',
        'question_index' => 'integer',
        'selected_option_index' => 'integer',
        'is_correct' => 'boolean',
        'points_earned' => 'integer',
        'points_possible' => 'integer',
        'answered_at' => 'datetime',
    ];

    public function attempt()
    {
        return $this->belongsTo(FanTestiAttempt::class, 'attempt_id');
    }
}

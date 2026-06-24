<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'sort_order',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    public function question()
    {
        return $this->belongsTo(TestSubjectLessonTestQuestion::class, 'question_id');
    }
}

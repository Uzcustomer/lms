<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestQuestion extends Model
{
    protected $fillable = [
        'lesson_test_id',
        'type',
        'prompt',
        'helper_text',
        'correct_answer_text',
        'case_sensitive',
        'points',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function lessonTest()
    {
        return $this->belongsTo(TestSubjectLessonTest::class, 'lesson_test_id');
    }

    public function options()
    {
        return $this->hasMany(TestSubjectLessonTestOption::class, 'question_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}

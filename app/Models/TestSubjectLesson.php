<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLesson extends Model
{
    protected $fillable = [
        'test_subject_id',
        'lesson_date',
        'starts_at',
        'ends_at',
        'topic_order',
        'topic_title',
        'is_active',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function testSubject()
    {
        return $this->belongsTo(TestSubject::class);
    }

    public function lessonTest()
    {
        return $this->hasOne(TestSubjectLessonTest::class, 'test_subject_lesson_id');
    }
}

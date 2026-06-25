<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTest extends Model
{
    protected $fillable = [
        'test_subject_id',
        'test_subject_lesson_id',
        'teacher_id',
        'title',
        'description',
        'duration_minutes',
        'pass_percent',
        'shuffle_questions',
        'show_result_after_submit',
        'is_published',
        'is_open',
        'opened_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'show_result_after_submit' => 'boolean',
        'is_published' => 'boolean',
        'is_open' => 'boolean',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function subject()
    {
        return $this->belongsTo(TestSubject::class, 'test_subject_id');
    }

    public function lesson()
    {
        return $this->belongsTo(TestSubjectLesson::class, 'test_subject_lesson_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->hasMany(TestSubjectLessonTestQuestion::class, 'lesson_test_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attempts()
    {
        return $this->hasMany(TestSubjectLessonTestAttempt::class, 'test_subject_lesson_test_id')
            ->orderByDesc('id');
    }
}

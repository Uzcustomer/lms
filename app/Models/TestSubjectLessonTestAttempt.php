<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestAttempt extends Model
{
    protected $fillable = [
        'test_subject_lesson_test_id',
        'test_subject_id',
        'test_subject_lesson_id',
        'student_id',
        'student_hemis_id',
        'status',
        'started_at',
        'submitted_at',
        'duration_seconds',
        'answers_count',
        'total_points',
        'score',
        'percent',
        'is_passed',
        'question_order',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'is_passed' => 'boolean',
        'question_order' => 'array',
        'score' => 'decimal:2',
        'percent' => 'decimal:2',
    ];

    public function test()
    {
        return $this->belongsTo(TestSubjectLessonTest::class, 'test_subject_lesson_test_id');
    }

    public function subject()
    {
        return $this->belongsTo(TestSubject::class, 'test_subject_id');
    }

    public function lesson()
    {
        return $this->belongsTo(TestSubjectLesson::class, 'test_subject_lesson_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function answers()
    {
        return $this->hasMany(TestSubjectLessonTestAnswer::class, 'attempt_id')
            ->orderBy('question_id');
    }
}

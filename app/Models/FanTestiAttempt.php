<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FanTestiAttempt extends Model
{
    protected $table = 'fan_testi_attempts';

    protected $fillable = [
        'fan_testi_id', 'student_id', 'student_hemis_id',
        'student_name', 'student_id_number', 'group_id', 'group_name',
        'faculty_name', 'specialty_name',
        'status', 'started_at', 'expires_at', 'submitted_at', 'duration_seconds',
        'questions_count', 'answers_count', 'correct_count',
        'total_points', 'score', 'percent', 'is_passed',
        'questions_snapshot', 'ip_address',
    ];

    protected $casts = [
        'fan_testi_id' => 'integer',
        'student_id' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'questions_count' => 'integer',
        'answers_count' => 'integer',
        'correct_count' => 'integer',
        'total_points' => 'integer',
        'score' => 'decimal:2',
        'percent' => 'decimal:2',
        'is_passed' => 'boolean',
        'questions_snapshot' => 'array',
    ];

    public function test()
    {
        return $this->belongsTo(FanTesti::class, 'fan_testi_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function answers()
    {
        return $this->hasMany(FanTestiAttemptAnswer::class, 'attempt_id')->orderBy('question_index');
    }

    public function isFinished(): bool
    {
        return $this->status !== 'in_progress';
    }

    public function secondsLeft(): int
    {
        if (!$this->expires_at) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->expires_at, false));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'submitted' => 'Topshirgan',
            'expired' => 'Vaqt tugagan',
            default => 'Ishlamoqda',
        };
    }
}

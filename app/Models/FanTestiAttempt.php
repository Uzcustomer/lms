<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FanTestiAttempt extends Model
{
    protected $table = 'fan_testi_attempts';

    protected $fillable = [
        'fan_testi_id', 'student_id', 'attempt_no', 'is_retake', 'student_hemis_id',
        'student_name', 'student_id_number', 'group_id', 'group_name',
        'faculty_name', 'specialty_name',
        'status', 'started_at', 'expires_at', 'submitted_at', 'duration_seconds',
        'questions_count', 'answers_count', 'correct_count',
        'total_points', 'score', 'percent', 'is_passed',
        'face_checks', 'face_mismatches', 'away_count',
        'away_streak', 'lock_count', 'locked_at', 'ended_reason', 'face_bypass_by',
        'questions_snapshot', 'ip_address',
        'retake_allowed_at', 'retake_allowed_by',
        'draft_answers', 'draft_saved_at',
        'journal_synced_at', 'journal_sync_note',
    ];

    protected $casts = [
        'fan_testi_id' => 'integer',
        'student_id' => 'integer',
        'attempt_no' => 'integer',
        'is_retake' => 'boolean',
        'started_at' => 'datetime',
        'retake_allowed_at' => 'datetime',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'questions_count' => 'integer',
        'answers_count' => 'integer',
        'correct_count' => 'integer',
        'total_points' => 'integer',
        'score' => 'decimal:2',
        'percent' => 'decimal:2',
        'is_passed' => 'boolean',
        'face_checks' => 'integer',
        'face_mismatches' => 'integer',
        'away_count' => 'integer',
        'away_streak' => 'integer',
        'lock_count' => 'integer',
        'locked_at' => 'datetime',
        'questions_snapshot' => 'array',
        'draft_answers' => 'array',
        'draft_saved_at' => 'datetime',
    ];

    /** Shuncha marta ketma-ket chalg'isa test qulflanadi (ikkinchi marta — yakunlanadi). */
    public const AWAY_LIMIT = 3;

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

    /** Chalg'igani uchun qulflangan va o'qituvchi ochib berishini kutmoqda. */
    public function isLocked(): bool
    {
        return $this->locked_at !== null && !$this->isFinished();
    }

    /** O'qituvchi faqat birinchi qulfni ochib bera oladi. */
    public function canUnlock(): bool
    {
        return $this->isLocked() && (int) $this->lock_count === 1;
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

    /** "1-urinish", "2-urinish" ... (ustun bo'lmasa — 1). */
    public function attemptLabel(): string
    {
        return max(1, (int) ($this->attempt_no ?? 1)) . '-urinish';
    }

    /** O'qituvchi qayta topshirishga ruxsat bergan va u hali ishlatilmagan. */
    public function retakeAllowed(): bool
    {
        return $this->retake_allowed_at !== null && $this->isFinished();
    }

    /** Talabaning shu testdagi oxirgi (eng katta raqamli) urinishi. */
    public static function latestFor(int $testId, int $studentId): ?self
    {
        return static::query()
            ->where('fan_testi_id', $testId)
            ->where('student_id', $studentId)
            ->orderByDesc('attempt_no')
            ->orderByDesc('id')
            ->first();
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

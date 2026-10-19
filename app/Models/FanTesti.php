<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FanTesti extends Model
{
    protected $table = 'fan_testlari';

    protected $fillable = [
        'curriculum_subject_id',
        'name',
        'description',
        'duration_minutes',
        'pass_percent',
        'shuffle_questions',
        'questions_per_attempt',
        'show_result_after_submit',
        'is_active',
        'questions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'questions_per_attempt' => 'integer',
        'show_result_after_submit' => 'boolean',
        'is_active' => 'boolean',
        'questions' => 'array',
    ];

    public function subject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'curriculum_subject_id');
    }

    public function creator()
    {
        return $this->belongsTo(Teacher::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(Teacher::class, 'updated_by');
    }

    public function questionCount(): int
    {
        return count($this->questions ?? []);
    }

    /** Faol savollar soni (talabaga beriladiganlar shulardan tanlanadi). */
    public function activeQuestionCount(): int
    {
        return collect($this->questions ?? [])
            ->filter(fn ($q) => ($q['is_active'] ?? true) !== false)
            ->count();
    }

    /** Bitta urinishda talabaga nechta savol tushadi (chegara va faol savollar soni kichigi). */
    public function questionsPerAttempt(): int
    {
        $active = $this->activeQuestionCount();
        $limit = (int) ($this->questions_per_attempt ?? 0);

        return $limit > 0 ? min($limit, $active) : $active;
    }
}

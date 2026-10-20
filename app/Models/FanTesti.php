<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FanTesti extends Model
{
    protected $table = 'fan_testlari';

    protected $fillable = [
        'curriculum_subject_id',
        'name',
        'topic_number',
        'description',
        'duration_minutes',
        'pass_percent',
        'shuffle_questions',
        'questions_per_attempt',
        'show_result_after_submit',
        'is_active',
        'public_open_at',
        'public_open_by',
        'questions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'questions_per_attempt' => 'integer',
        'topic_number' => 'integer',
        'show_result_after_submit' => 'boolean',
        'is_active' => 'boolean',
        'public_open_at' => 'datetime',
        'questions' => 'array',
    ];

    /**
     * "Umumiy ochish" rejimi: test bitta havola orqali istalgan kompyuterda
     * ochiladi, talaba o'qituvchi ruxsati bilan kiradi. Ustun bo'lmasa
     * (migratsiya qilinmagan) — yopiq.
     */
    public function isPublicOpen(): bool
    {
        return $this->is_active && !empty($this->getAttributes()['public_open_at'] ?? null);
    }

    public function entryRequests()
    {
        return $this->hasMany(FanTestiEntryRequest::class, 'fan_testi_id');
    }

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

    /** "3-mavzu" yoki bo'sh (raqam kiritilmagan). */
    public function topicLabel(): string
    {
        return $this->topic_number ? $this->topic_number . '-mavzu' : '';
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

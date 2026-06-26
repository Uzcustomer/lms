<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestOption extends Model
{
    protected $fillable = [
        'question_id',
        'option_text',
        'option_text_translations',
        'sort_order',
        'is_correct',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'option_text_translations' => 'array',
    ];

    public function question()
    {
        return $this->belongsTo(TestSubjectLessonTestQuestion::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(TestSubjectLessonTestAnswer::class, 'selected_option_id');
    }

    public function textFor(?string $lang = 'uz'): string
    {
        $lang = in_array($lang, ['uz', 'ru', 'en'], true) ? $lang : 'uz';
        $translations = (array) ($this->option_text_translations ?? []);
        $value = trim((string) ($translations[$lang] ?? ''));

        if ($value !== '') {
            return $value;
        }

        if ($lang !== 'uz') {
            $uzValue = trim((string) ($translations['uz'] ?? ''));
            if ($uzValue !== '') {
                return $uzValue;
            }
        }

        return trim((string) $this->option_text);
    }
}

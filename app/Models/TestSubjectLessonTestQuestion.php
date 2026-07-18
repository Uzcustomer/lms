<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestSubjectLessonTestQuestion extends Model
{
    protected $fillable = [
        'lesson_test_id',
        'type',
        'prompt',
        'prompt_translations',
        'image_path',
        'helper_text',
        'helper_text_translations',
        'correct_explanation',
        'correct_explanation_translations',
        'correct_answer_text',
        'correct_answer_translations',
        'case_sensitive',
        'points',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'is_active' => 'boolean',
        'prompt_translations' => 'array',
        'helper_text_translations' => 'array',
        'correct_explanation_translations' => 'array',
        'correct_answer_translations' => 'array',
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

    public function answers()
    {
        return $this->hasMany(TestSubjectLessonTestAnswer::class, 'question_id')
            ->orderByDesc('id');
    }

    public function promptFor(?string $lang = 'uz'): string
    {
        return $this->translationValue('prompt_translations', $lang, $this->prompt) ?? '';
    }

    public function helperTextFor(?string $lang = 'uz'): ?string
    {
        return $this->translationValue('helper_text_translations', $lang, $this->helper_text);
    }

    public function correctAnswerFor(?string $lang = 'uz'): ?string
    {
        return $this->translationValue('correct_answer_translations', $lang, $this->correct_answer_text);
    }

    public function correctExplanationFor(?string $lang = 'uz'): ?string
    {
        return $this->translationValue('correct_explanation_translations', $lang, $this->correct_explanation);
    }

    public function imageUrl(): ?string
    {
        $path = trim((string) ($this->image_path ?? ''));

        if ($path === '') {
            return null;
        }

        return route('test-subject-questions.image', $this);
    }

    private function translationValue(string $field, ?string $lang, ?string $fallback = null): ?string
    {
        $lang = in_array($lang, ['uz', 'ru', 'en'], true) ? $lang : 'uz';
        $translations = (array) ($this->{$field} ?? []);
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

        $fallback = trim((string) ($fallback ?? ''));
        return $fallback !== '' ? $fallback : null;
    }
}

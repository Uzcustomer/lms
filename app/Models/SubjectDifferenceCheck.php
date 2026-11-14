<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fanlar farqi tekshiruvi (bitta talaba, bir yoki bir nechta transkript fayli).
 *
 * transcript = [
 *   'text' => [...]|null,      // pdftotext + qoida bilan o'qilgan qatorlar
 *   'ai' => [...]|null,        // Gemini o'qigan qatorlar
 *   'source' => 'ai'|'text',   // moslashtirishda qaysi biri ishlatiladi
 *   'ai_error' => ?string, 'files' => [...], 'header' => [...],
 * ]
 *
 * levels = [[
 *   'id', 'curriculum_id', 'curriculum_name', 'upto', 'plan' => [...],
 *   'rule' => [key => ['tr', 'score']], 'ai' => [key => ['tr', 'confidence', 'reason']]|null,
 *   'ai_error', 'overrides' => [key => tr|null], 'excluded' => [key, ...], 'created_at',
 * ], ...]
 */
class SubjectDifferenceCheck extends Model
{
    protected $fillable = [
        'student_name',
        'source_institution',
        'files',
        'scale',
        'transcript',
        'levels',
        'status',
        'error',
        'notes',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'files' => 'array',
        'scale' => 'array',
        'transcript' => 'array',
        'levels' => 'array',
    ];

    /** Moslashtirishda ishlatiladigan transkript qatorlari. */
    public function transcriptRows(): array
    {
        $t = $this->transcript ?? [];
        $source = $t['source'] ?? (isset($t['ai']) ? 'ai' : 'text');

        return $t[$source] ?? $t['ai'] ?? $t['text'] ?? [];
    }

    public function transcriptSource(): string
    {
        $t = $this->transcript ?? [];

        return $t['source'] ?? (isset($t['ai']) ? 'ai' : 'text');
    }
}

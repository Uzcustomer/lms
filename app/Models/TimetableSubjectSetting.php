<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fan bo'yicha jadval rejimi (hafta almashinuvi / sikl).
 * Aniqlovchilar (faculty_name, curriculum_hemis_id): umumiy yozuv — ('', 0); fakultet / o'quv reja bo'yicha alohida yozuvda faqat
 * sikl kunlari soni (cycle_days) — qidiruv: (fakultet + o'quv reja) → (fakultet) → (umumiy). Fan rejimi — umumiy yozuvda.
 * @see database/migrations/2026_07_23_140000_create_timetable_subject_settings.php
 * @see database/migrations/2026_11_06_120000_add_scope_to_timetable_subject_settings.php
 */
class TimetableSubjectSetting extends Model
{
    protected $fillable = [
        'board_id', 'specialty_name', 'course', 'subject_name', 'faculty_name', 'curriculum_hemis_id',
        'mode', 'season', 'rotation_group', 'occurrences', 'cycle_weeks', 'cycle_days', 'note',
    ];

    protected $casts = [
        'course'      => 'integer',
        'curriculum_hemis_id' => 'integer',
        'occurrences' => 'integer',
        'cycle_weeks' => 'integer',
        'cycle_days'  => 'integer',
    ];

    public const MODES = ['normal', 'alternate', 'cycle'];
    public const SEASONS = ['kuzgi', 'bahorgi'];

    /** Umumiy yozuvlar (aniqlovchisiz) — fan rejimi, mavsum va boshqa umumiy sozlamalar faqat shulardan. */
    public function scopeGeneral($query)
    {
        // Ustun bor-yo'qligi — jarayon davomida bir marta (har so'rovda information_schema ga murojaat qilinmasin).
        static $scoped = null;
        $scoped ??= \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'faculty_name');
        if (!$scoped) {
            return $query;
        }

        return $query->where('faculty_name', '')->where('curriculum_hemis_id', 0);
    }

    public function isGeneral(): bool
    {
        return (string) ($this->faculty_name ?? '') === '' && (int) ($this->curriculum_hemis_id ?? 0) === 0;
    }

    public function board()
    {
        return $this->belongsTo(TimetableBoard::class, 'board_id');
    }
}

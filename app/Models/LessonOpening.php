<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonOpening extends Model
{
    protected $fillable = [
        'group_hemis_id',
        'subject_id',
        'semester_code',
        'lesson_date',
        'file_path',
        'file_original_name',
        'opened_by_id',
        'opened_by_name',
        'opened_by_guard',
        'deadline',
        'status',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'datetime',
    ];

    /**
     * Berilgan guruh+fan+semestr uchun faol dars ochilishlarini olish
     */
    public static function getActiveOpenings(string $groupHemisId, string $subjectId, string $semesterCode): array
    {
        return static::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('status', 'active')
            ->where('deadline', '>', now())
            ->pluck('lesson_date')
            ->map(fn($d) => $d->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Berilgan guruh+fan+semestr uchun barcha dars ochilishlarini olish (faol va muddati o'tgan)
     */
    public static function getAllOpenings(string $groupHemisId, string $subjectId, string $semesterCode): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->get();
    }

    /**
     * Muddati o'tgan ochilishlarni expired qilish
     */
    public static function expireOverdue(): int
    {
        return static::where('status', 'active')
            ->where('deadline', '<=', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Shu ochilish hali faolmi
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deadline > now();
    }
}

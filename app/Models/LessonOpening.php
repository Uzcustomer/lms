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
        'request_note',
        'reviewed_by_id',
        'reviewed_by_name',
        'reviewed_by_guard',
        'reviewed_at',
        'review_comment',
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'deadline' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Holatlar: pending — prorektor tasdig'ini kutmoqda; active — ochiq,
     * o'qituvchi baho qo'ya oladi; expired — muddati tugagan;
     * rejected — prorektor rad etgan.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    /** Shu so'rov prorektor qarorini kutmoqdami */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

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
        return static::where('status', self::STATUS_ACTIVE)
            ->whereNotNull('deadline')
            ->where('deadline', '<=', now())
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Shu ochilish hali faolmi
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->deadline !== null
            && $this->deadline > now();
    }
}

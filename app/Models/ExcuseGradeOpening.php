<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcuseGradeOpening extends Model
{
    protected $fillable = [
        'absence_excuse_id',
        'absence_excuse_makeup_id',
        'student_hemis_id',
        'subject_id',
        'date_from',
        'date_to',
        'deadline',
        'status',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'deadline' => 'datetime',
    ];

    public function absenceExcuse()
    {
        return $this->belongsTo(AbsenceExcuse::class);
    }

    public function absenceExcuseMakeup()
    {
        return $this->belongsTo(AbsenceExcuseMakeup::class);
    }

    /**
     * Berilgan talaba+fan uchun muayyan sana faol ochilganmi tekshirish
     */
    public static function isDateOpenForStudent(string $studentHemisId, string $subjectId, string $lessonDate): bool
    {
        return static::where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('date_from', '<=', $lessonDate)
            ->where('date_to', '>=', $lessonDate)
            ->where('status', 'active')
            ->where('deadline', '>', now())
            ->exists();
    }

    /**
     * Berilgan talaba+fan uchun faol ochilgan sanalarni olish
     */
    public static function getActiveOpeningsForStudent(string $studentHemisId, string $subjectId): array
    {
        $openings = static::where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->where('deadline', '>', now())
            ->get();

        $dates = [];
        foreach ($openings as $opening) {
            $current = $opening->date_from->copy();
            while ($current->lte($opening->date_to)) {
                $dates[] = $current->format('Y-m-d');
                $current->addDay();
            }
        }

        return array_unique($dates);
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

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deadline > now();
    }
}

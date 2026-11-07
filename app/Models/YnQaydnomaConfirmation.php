<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Amaliy o'qituvchi YN qaydnomani tasdiqlagani (unikal QR — DocumentVerification). */
class YnQaydnomaConfirmation extends Model
{
    protected $fillable = [
        'subject_id', 'semester_code', 'group_hemis_id', 'teacher_id', 'teacher_name',
        'document_verification_id', 'weights', 'confirmed_at',
    ];

    protected $casts = [
        'weights' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function verification()
    {
        return $this->belongsTo(DocumentVerification::class, 'document_verification_id');
    }

    /** Vedomost bo'yicha har o'qituvchining oxirgi tasdig'i. */
    public static function latestFor(string $subjectId, string $semesterCode, string $groupHemisId)
    {
        return static::with('verification')
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId)
            ->orderByDesc('confirmed_at')
            ->get()
            ->unique('teacher_id')
            ->values();
    }
}

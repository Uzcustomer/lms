<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * YN qaydnoma tasdig'i (unikal QR — DocumentVerification): avval amaliy o'qituvchi,
 * keyin fakultet dekani tasdiqlaydi.
 */
class YnQaydnomaConfirmation extends Model
{
    public const ROLE_TEACHER = 'oqituvchi';
    public const ROLE_DEAN = 'dekan';

    protected $fillable = [
        'subject_id', 'semester_code', 'group_hemis_id', 'teacher_id', 'teacher_name', 'role',
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

    public function scopeForVedomost($query, string $subjectId, string $semesterCode, string $groupHemisId)
    {
        return $query->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId);
    }

    /** Vedomost bo'yicha har kishining oxirgi tasdig'i (sukut bo'yicha — o'qituvchilarniki). */
    public static function latestFor(string $subjectId, string $semesterCode, string $groupHemisId, string $role = self::ROLE_TEACHER)
    {
        return static::with('verification')
            ->forVedomost($subjectId, $semesterCode, $groupHemisId)
            ->where('role', $role)
            ->orderByDesc('confirmed_at')
            ->get()
            ->unique('teacher_id')
            ->values();
    }

    public function roleLabel(): string
    {
        return $this->role === self::ROLE_DEAN ? 'Fakultet dekani' : "Amaliyot o'qituvchisi";
    }
}

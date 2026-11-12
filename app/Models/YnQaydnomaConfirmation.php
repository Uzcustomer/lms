<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * YN qaydnoma tasdig'i (unikal QR — DocumentVerification): avval amaliy o'qituvchi(lar),
 * keyin fan kafedrasining mudiri, oxirida fakultet dekani tasdiqlaydi.
 */
class YnQaydnomaConfirmation extends Model
{
    public const ROLE_TEACHER = 'oqituvchi';
    public const ROLE_HEAD = 'kafedra_mudiri';
    public const ROLE_DEAN = 'dekan';

    /** Tasdiqlash tartibi — har biri oldingisidan keyin */
    public const CHAIN = [self::ROLE_TEACHER, self::ROLE_HEAD, self::ROLE_DEAN];

    protected $fillable = [
        'subject_id', 'semester_code', 'group_hemis_id', 'teacher_id', 'teacher_name', 'role',
        'document_verification_id', 'pdf_path', 'weights', 'confirmed_at',
    ];

    protected $casts = [
        'weights' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function verification()
    {
        return $this->belongsTo(DocumentVerification::class, 'document_verification_id');
    }

    /** Qaydnoma bo'yicha: guruh berilsa — uning kichik guruhlari oilasi (a/b/c) bilan, ro'yxat berilsa — aynan shular */
    public function scopeForVedomost($query, string $subjectId, string $semesterCode, string|array $groupHemisIds)
    {
        $groups = is_array($groupHemisIds) ? $groupHemisIds : \App\Services\YnqFamily::members($subjectId, $semesterCode, $groupHemisIds);

        return $query->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('group_hemis_id', array_map('strval', $groups));
    }

    /** Vedomost bo'yicha har kishining oxirgi tasdig'i (sukut bo'yicha — o'qituvchilarniki). */
    public static function latestFor(string $subjectId, string $semesterCode, string|array $groupHemisId, string $role = self::ROLE_TEACHER)
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
        return self::labelFor($this->role);
    }

    public static function labelFor(?string $role): string
    {
        return match ($role) {
            self::ROLE_DEAN => 'Fakultet dekani',
            self::ROLE_HEAD => 'Kafedra mudiri',
            default => "Amaliyot o'qituvchisi",
        };
    }
}

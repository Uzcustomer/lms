<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Dekan YN qaydnomani rad etgani; o'qituvchi qayta tasdiqlaganda yopiladi (resolved_at). */
class YnQaydnomaRejection extends Model
{
    protected $fillable = [
        'subject_id', 'semester_code', 'group_hemis_id', 'form', 'dean_id', 'dean_name', 'teacher_ids', 'reason', 'weights',
        'rejected_at', 'resolved_at',
    ];

    protected $casts = [
        'weights' => 'array',
        'teacher_ids' => 'array',
        'rejected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Qaydnoma bo'yicha: guruh berilsa — uning kichik guruhlari oilasi (a/b/c) bilan, ro'yxat berilsa — aynan
     * shular; shakl (YnqForm) — har shakl alohida vedomost, sukut bo'yicha asosiy 12-shakl
     */
    public function scopeForVedomost($query, string $subjectId, string $semesterCode, string|array $groupHemisIds, ?string $form = null)
    {
        $groups = is_array($groupHemisIds) ? $groupHemisIds : \App\Services\YnqFamily::members($subjectId, $semesterCode, $groupHemisIds);

        return $query->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('group_hemis_id', array_map('strval', $groups))
            ->when(self::hasFormColumn(), fn ($q) => $q->where('form', \App\Services\YnqForm::normalize($form)));
    }

    /** Shakl ustuni bormi (migratsiyadan oldin — hammasi asosiy 12-shakl) */
    public static function hasFormColumn(): bool
    {
        static $has = [];

        return $has[static::class] ??= \Illuminate\Support\Facades\Schema::hasColumn((new static())->getTable(), 'form');
    }

    /** Hali yopilmagan (o'qituvchi qayta tasdiqlamagan) eng so'nggi rad etish */
    public static function openFor(string $subjectId, string $semesterCode, string|array $groupHemisId, ?string $form = null): ?self
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('yn_qaydnoma_rejections')) {
            return null;
        }

        return static::forVedomost($subjectId, $semesterCode, $groupHemisId, $form)
            ->whereNull('resolved_at')
            ->latest('rejected_at')
            ->first();
    }
}

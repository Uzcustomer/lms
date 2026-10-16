<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A-5: to'lov-kontrakt asosida o'qishga tavsiya etilgan abituriyent (mandat ro'yxatidan import).
 */
class MonitoringAdmissionRecommendation extends Model
{
    public const CONTRACT_TYPES = [
        'oddiy' => "To'lov-kontrakt",
        'qoshimcha' => "Qo'shimcha to'lov-kontrakt",
        'tabaqalashtirilgan' => "Tabaqalashtirilgan to'lov-kontrakt",
    ];

    public const MATCH_METHODS = [
        'passport' => 'Pasport',
        'hemis_id' => 'HEMIS ID',
        'id_raqam' => 'Talaba ID raqami',
        'fio' => 'F.I.Sh. (tasdiqlanmagan)',
        'qolda' => "Qo'lda",
    ];

    protected $fillable = [
        'data_import_id',
        'dedupe_key',
        'education_year',
        'applicant_id',
        'full_name',
        'passport',
        'specialty_name',
        'education_form',
        'contract_type',
        'contract_type_raw',
        'recommended_at',
        'student_hemis_id',
        'match_method',
        'match_confirmed',
    ];

    protected $casts = [
        'recommended_at' => 'date',
        'match_confirmed' => 'boolean',
    ];

    public static function makeDedupeKey(string $educationYear, ?string $applicantId, string $fullName, ?string $passport): string
    {
        $who = $applicantId ?: (mb_strtolower(trim($fullName)) . '|' . mb_strtoupper(trim((string) $passport)));

        return sha1($educationYear . '|' . $who);
    }

    /** Oddiy kontraktda yillik summaning 25 %, qo'shimcha/tabaqalashtirilganda birinchi yilning 50 % (VMQ 578). */
    public function isSpecialContract(): bool
    {
        return in_array($this->contract_type, ['qoshimcha', 'tabaqalashtirilgan'], true);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_hemis_id', 'hemis_id');
    }
}

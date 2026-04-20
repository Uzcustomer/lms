<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentContract extends Model
{
    protected $fillable = [
        'student_id',
        'student_hemis_id',
        'student_full_name',
        'group_name',
        'department_name',
        'specialty_name',
        'level_name',
        'contract_type',
        'student_address',
        'student_phone',
        'student_passport',
        'student_bank_account',
        'student_bank_mfo',
        'student_inn',
        'employer_name',
        'employer_address',
        'employer_phone',
        'employer_bank_account',
        'employer_bank_mfo',
        'employer_inn',
        'employer_director_name',
        'employer_director_position',
        'fourth_party_name',
        'fourth_party_address',
        'fourth_party_phone',
        'fourth_party_director_name',
        'specialty_field',
        'status',
        'reject_reason',
        'reviewed_by',
        'reviewed_at',
        'document_path',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_REGISTRAR_REVIEW = 'registrar_review';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const TYPE_3_PARTY = '3_tomonlama';
    const TYPE_4_PARTY = '4_tomonlama';

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Kutilmoqda',
            self::STATUS_REGISTRAR_REVIEW => 'Ko\'rib chiqilmoqda',
            self::STATUS_APPROVED => 'Tasdiqlangan',
            self::STATUS_REJECTED => 'Rad etilgan',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_3_PARTY => '3 tomonlama shartnoma',
            self::TYPE_4_PARTY => '4 tomonlama shartnoma',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->contract_type] ?? $this->contract_type;
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Teacher::class, 'reviewed_by');
    }
}

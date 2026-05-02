<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RetakeApplication extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const REJECTED_BY_DEAN = 'dean';
    public const REJECTED_BY_REGISTRAR = 'registrar';
    public const REJECTED_BY_ACADEMIC_DEPT = 'academic_dept';
    public const REJECTED_BY_SYSTEM_HEMIS = 'system_hemis';
    public const REJECTED_BY_WINDOW_CLOSED = 'window_closed';

    protected $fillable = [
        'group_id',
        'student_hemis_id',
        'subject_id',
        'subject_name',
        'semester_id',
        'semester_name',
        'credit',

        'previous_joriy_grade',
        'previous_mustaqil_grade',
        'has_oske',
        'has_test',
        'has_sinov',

        'dean_status', 'dean_user_id', 'dean_user_name', 'dean_decision_at', 'dean_reason',
        'registrar_status', 'registrar_user_id', 'registrar_user_name', 'registrar_decision_at', 'registrar_reason',
        'academic_dept_status', 'academic_dept_user_id', 'academic_dept_user_name', 'academic_dept_decision_at', 'academic_dept_reason',

        'final_status',
        'rejected_by',
        'retake_group_id',
    ];

    protected $casts = [
        'credit' => 'decimal:2',
        'previous_joriy_grade' => 'decimal:2',
        'previous_mustaqil_grade' => 'decimal:2',
        'has_oske' => 'boolean',
        'has_test' => 'boolean',
        'has_sinov' => 'boolean',
        'dean_decision_at' => 'datetime',
        'registrar_decision_at' => 'datetime',
        'academic_dept_decision_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(RetakeApplicationGroup::class, 'group_id');
    }

    public function retakeGroup()
    {
        return $this->belongsTo(RetakeGroup::class, 'retake_group_id');
    }

    public function deanUser()
    {
        return $this->belongsTo(Teacher::class, 'dean_user_id');
    }

    public function registrarUser()
    {
        return $this->belongsTo(Teacher::class, 'registrar_user_id');
    }

    public function academicDeptUser()
    {
        return $this->belongsTo(Teacher::class, 'academic_dept_user_id');
    }

    public function logs()
    {
        return $this->hasMany(RetakeApplicationLog::class, 'application_id');
    }

    /**
     * Talaba sahifasida ko'rinadigan holat matni.
     */
    public function studentDisplayStatus(): string
    {
        if ($this->final_status === self::STATUS_APPROVED) {
            return 'Tasdiqlangan';
        }

        if ($this->final_status === self::STATUS_REJECTED) {
            $whoLabel = match ($this->rejected_by) {
                self::REJECTED_BY_DEAN => 'Dekan',
                self::REJECTED_BY_REGISTRAR => 'Registrator',
                self::REJECTED_BY_ACADEMIC_DEPT => 'O\'quv bo\'limi',
                self::REJECTED_BY_SYSTEM_HEMIS => 'Tizim (HEMIS)',
                self::REJECTED_BY_WINDOW_CLOSED => "Oyna yopildi (muddat o'tdi)",
                default => 'Tizim',
            };
            return "Rad etilgan: {$whoLabel}";
        }

        // pending — qaysi bosqichda ekanligi
        if ($this->academic_dept_status === self::STATUS_PENDING
            && $this->dean_status === self::STATUS_APPROVED
            && $this->registrar_status === self::STATUS_APPROVED) {
            return 'So\'nggi bosqich — O\'quv bo\'limida kutishda';
        }

        if ($this->dean_status === self::STATUS_APPROVED
            && $this->registrar_status === self::STATUS_PENDING) {
            return 'Registrator ofisi ko\'rib chiqmoqda (dekan tasdiqlagan)';
        }

        if ($this->dean_status === self::STATUS_PENDING
            && $this->registrar_status === self::STATUS_APPROVED) {
            return 'Dekan ko\'rib chiqmoqda (registrator tasdiqlagan)';
        }

        return 'Dekan va Registrator ofisi ko\'rib chiqmoqda';
    }

    /**
     * Sabab matnini olish (rad etilgan bo'lsa).
     */
    public function rejectionReason(): ?string
    {
        return match ($this->rejected_by) {
            self::REJECTED_BY_DEAN => $this->dean_reason,
            self::REJECTED_BY_REGISTRAR => $this->registrar_reason,
            self::REJECTED_BY_ACADEMIC_DEPT => $this->academic_dept_reason,
            default => null,
        };
    }

    public function isPending(): bool
    {
        return $this->final_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->final_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->final_status === self::STATUS_REJECTED;
    }

    /**
     * Dekan va registrator ikkalasi tasdiqlaganmi?
     */
    public function isDualApproved(): bool
    {
        return $this->dean_status === self::STATUS_APPROVED
            && $this->registrar_status === self::STATUS_APPROVED;
    }

    /**
     * Aktiv arizalar (slot tekshirish uchun): pending yoki approved.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('final_status', [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    public function scopeForStudent($query, int $studentHemisId)
    {
        return $query->where('student_hemis_id', $studentHemisId);
    }
}

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
        'oske_score',
        'test_score',
        'oske_graded_at',
        'test_graded_at',
        'joriy_score',
        'joriy_graded_by_name',
        'joriy_graded_at',
        'final_grade_value',
        'final_grade_set_at',

        'dean_status', 'dean_user_id', 'dean_user_name', 'dean_decision_at', 'dean_reason',
        'registrar_status', 'registrar_user_id', 'registrar_user_name', 'registrar_decision_at', 'registrar_reason',
        'academic_dept_status', 'academic_dept_user_id', 'academic_dept_user_name', 'academic_dept_decision_at', 'academic_dept_reason',

        'final_status',
        'rejected_by',
        'retake_group_id',
        'sent_to_test_markazi_at',
        'sent_to_test_markazi_by',
    ];

    protected $casts = [
        'credit' => 'decimal:2',
        'previous_joriy_grade' => 'decimal:2',
        'previous_mustaqil_grade' => 'decimal:2',
        'has_oske' => 'boolean',
        'has_test' => 'boolean',
        'has_sinov' => 'boolean',
        'oske_score' => 'decimal:2',
        'test_score' => 'decimal:2',
        'oske_graded_at' => 'datetime',
        'test_graded_at' => 'datetime',
        'joriy_score' => 'decimal:2',
        'joriy_graded_at' => 'datetime',
        'final_grade_value' => 'decimal:2',
        'final_grade_set_at' => 'datetime',
        'dean_decision_at' => 'datetime',
        'registrar_decision_at' => 'datetime',
        'academic_dept_decision_at' => 'datetime',
        'sent_to_test_markazi_at' => 'datetime',
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

        // O'quv bo'limi oldindan tasdiqlagan, ammo hali guruhga biriktirilmagan
        // (final_status hali pending). Bu yangi 2-bosqichli oqimning oraliq holati.
        if ($this->academic_dept_status === self::STATUS_APPROVED
            && $this->dean_status === self::STATUS_APPROVED
            && $this->registrar_status === self::STATUS_APPROVED) {
            return 'O\'quv bo\'limi tasdiqladi — guruhga biriktirilmoqda';
        }

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
     * O'quv bo'limi "Arizalar" sahifasi uchun umumiy holat yorlig'i.
     *
     * studentDisplayStatus() dan farqli o'laroq bu to'lov bosqichlarini ham
     * qamrab oladi, shuning uchun ariza qaysi bosqichda ("holat") ekanligini
     * bir qarashda ko'rsatadi. ['label' => ..., 'class' => tailwind badge klaslari].
     *
     * @return array{label: string, class: string}
     */
    public function academicStageBadge(): array
    {
        // ── Rad etilgan ──────────────────────────────────
        if ($this->final_status === self::STATUS_REJECTED) {
            $who = match ($this->rejected_by) {
                self::REJECTED_BY_DEAN => 'Dekan',
                self::REJECTED_BY_REGISTRAR => 'Registrator',
                self::REJECTED_BY_ACADEMIC_DEPT => "O'quv bo'limi",
                self::REJECTED_BY_SYSTEM_HEMIS => 'Tizim (HEMIS)',
                self::REJECTED_BY_WINDOW_CLOSED => "Muddat o'tdi",
                default => 'Tizim',
            };

            return ['label' => "Rad etilgan · {$who}", 'class' => 'bg-red-100 text-red-800 border-red-200'];
        }

        // ── Yakuniy tasdiqlangan ─────────────────────────
        if ($this->final_status === self::STATUS_APPROVED) {
            if (!empty($this->retake_group_id)) {
                return ['label' => 'Guruhga biriktirilgan', 'class' => 'bg-emerald-100 text-emerald-800 border-emerald-200'];
            }

            return ['label' => 'Tasdiqlangan', 'class' => 'bg-green-100 text-green-800 border-green-200'];
        }

        // ── Kutilmoqda (pending) — aniq bosqichni topamiz ──

        // Jarayon ketma-ket: avval dekanat, keyin registrator, keyin to'lov,
        // keyin o'quv bo'limi. Qaysidir bosqichda to'xtagan bo'lsa, faqat
        // o'sha joriy bosqich ko'rsatiladi.

        // 1) Dekanat hali tasdiqlamagan
        if ($this->dean_status !== self::STATUS_APPROVED) {
            return ['label' => 'Dekanat tasdig\'i kutilmoqda', 'class' => 'bg-amber-50 text-amber-800 border-amber-200'];
        }

        // 2) Registrator ofisi hali tasdiqlamagan
        if ($this->registrar_status !== self::STATUS_APPROVED) {
            return ['label' => 'Registrator ofisi tasdig\'i kutilmoqda', 'class' => 'bg-amber-50 text-amber-800 border-amber-200'];
        }

        // 3) Dekanat + registrator tasdiqlagan → to'lov bosqichi
        $paymentStatus = $this->group?->payment_verification_status;
        $paymentUploaded = $this->group && $this->group->payment_uploaded_at !== null;

        if (!$paymentUploaded) {
            return ['label' => 'To\'lov yuklanishi kutilmoqda', 'class' => 'bg-orange-50 text-orange-700 border-orange-200'];
        }
        if ($paymentStatus === RetakeApplicationGroup::PAYMENT_VERIFICATION_PENDING) {
            return ['label' => 'To\'lov cheki tasdiqlanishi kutilmoqda', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'];
        }
        if ($paymentStatus === RetakeApplicationGroup::PAYMENT_VERIFICATION_REJECTED) {
            return ['label' => 'To\'lov rad etilgan · qayta yuklash kerak', 'class' => 'bg-red-50 text-red-700 border-red-200'];
        }

        // 4) To'lov tasdiqlangan → o'quv bo'limi bosqichi
        if ($this->academic_dept_status === self::STATUS_APPROVED) {
            return ['label' => 'O\'quv bo\'limi tasdiqladi · guruhga kutilmoqda', 'class' => 'bg-blue-100 text-blue-800 border-blue-200'];
        }

        // O'quv bo'limi tasdig'ini kutmoqda (bu sahifada amal qilinadigan holat)
        return ['label' => 'O\'quv bo\'limi tasdig\'i kutilmoqda', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'];
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

    /**
     * O'quv bo'limi "Arizalar" sahifasi uchun aniq holat bo'yicha filtr.
     * Kalitlar academicStatusOptions() dagi kalitlar bilan mos.
     */
    public function scopeAcademicStatus($query, ?string $status)
    {
        return match ($status) {
            'dean_wait' => $query->where('final_status', self::STATUS_PENDING)
                ->where('dean_status', self::STATUS_PENDING),
            'registrar_wait' => $query->where('final_status', self::STATUS_PENDING)
                ->where('dean_status', self::STATUS_APPROVED)
                ->where('registrar_status', self::STATUS_PENDING),
            'payment_upload_wait' => $query->where('final_status', self::STATUS_PENDING)
                ->where('dean_status', self::STATUS_APPROVED)
                ->where('registrar_status', self::STATUS_APPROVED)
                ->whereHas('group', fn ($g) => $g->whereNull('payment_uploaded_at')),
            'payment_verify_wait' => $query->where('final_status', self::STATUS_PENDING)
                ->where('dean_status', self::STATUS_APPROVED)
                ->where('registrar_status', self::STATUS_APPROVED)
                ->whereHas('group', fn ($g) => $g->whereNotNull('payment_uploaded_at')
                    ->where('payment_verification_status', 'pending')),
            'payment_rejected' => $query->whereHas('group', fn ($g) => $g->where('payment_verification_status', 'rejected')),
            'academic_wait' => $query->where('final_status', self::STATUS_PENDING)
                ->where('dean_status', self::STATUS_APPROVED)
                ->where('registrar_status', self::STATUS_APPROVED)
                ->where('academic_dept_status', self::STATUS_PENDING)
                ->whereHas('group', fn ($g) => $g->where('payment_verification_status', 'approved')),
            'academic_preapproved' => $query->where('final_status', self::STATUS_PENDING)
                ->where('academic_dept_status', self::STATUS_APPROVED)
                ->whereNull('retake_group_id'),
            'grouped' => $query->where('final_status', self::STATUS_APPROVED)
                ->whereNotNull('retake_group_id'),
            'approved' => $query->where('final_status', self::STATUS_APPROVED),
            'rejected' => $query->where('final_status', self::STATUS_REJECTED),
            default => $query,
        };
    }

    /**
     * "Holat" filtri uchun variantlar (jarayon tartibida).
     *
     * @return array<string, string>
     */
    public static function academicStatusOptions(): array
    {
        return [
            'dean_wait' => "Dekanat tasdig'i kutilmoqda",
            'registrar_wait' => "Registrator ofisi tasdig'i kutilmoqda",
            'payment_upload_wait' => "To'lov yuklanishi kutilmoqda",
            'payment_verify_wait' => "To'lov cheki tasdiqlanishi kutilmoqda",
            'payment_rejected' => "To'lov rad etilgan",
            'academic_wait' => "O'quv bo'limi tasdig'i kutilmoqda",
            'academic_preapproved' => "O'quv bo'limi tasdiqladi (guruhga kutilmoqda)",
            'grouped' => "Guruhga biriktirilgan",
            'rejected' => "Rad etilgan",
        ];
    }
}

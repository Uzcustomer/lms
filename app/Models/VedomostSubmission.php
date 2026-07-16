<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VedomostSubmission extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';     // kutilmoqda (topshirilmagan)
    public const STATUS_RECEIVED = 'received';    // tekshirish uchun qabul qilindi
    public const STATUS_REVIEWING = 'reviewing';  // tekshirilmoqda
    public const STATUS_APPROVED = 'approved';    // tasdiqlandi
    public const STATUS_REJECTED = 'rejected';    // rad etildi

    /** Vedomost shakllari. */
    public const FORM_12 = '12';     // 1-urinish (joriy) — har guruh alohida
    public const FORM_12A = '12a';   // 2-urinish (1-qayta topshirish) — barcha guruhlar bitta varaqda
    public const FORM_12B = '12b';   // 3-urinish (2-qayta topshirish) — barcha guruhlar bitta varaqda

    // Qo'shimcha (sababli ma'lumotnoma asosida) shakllar — YN tizimidagi
    // 12-qo'shimcha / 12a-qo'shimcha / 12b-qo'shimcha bilan mos.
    public const FORM_12Q  = '12q';   // 12-qo'shimcha — asosiy urinishning qo'shimchasi (har guruh alohida)
    public const FORM_12AQ = '12aq';  // 12a-qo'shimcha (barcha guruhlar bitta varaqda)
    public const FORM_12BQ = '12bq';  // 12b-qo'shimcha (barcha guruhlar bitta varaqda)
    public const FORM_12AG = '12ag';  // 12a-qo'shimcha (sababli guruh uchun alohida)
    public const FORM_12BG = '12bg';  // 12b-qo'shimcha (sababli guruh uchun alohida)

    /** 12a/12b va ularning qo'shimchalari — umumiy (guruhsiz, hamma guruh bitta varaqda). */
    public const COMBINED_FORMS = [self::FORM_12A, self::FORM_12B, self::FORM_12AQ, self::FORM_12BQ];

    /** Qo'shimcha (sababli) shakllar. */
    public const QOSHIMCHA_FORMS = [self::FORM_12Q, self::FORM_12AQ, self::FORM_12BQ, self::FORM_12AG, self::FORM_12BG];

    /** YN service shakl nomi -> vedomost form_type. */
    public const YN_FORM_MAP = [
        '12-shakl'      => self::FORM_12,
        "12-qo'shimcha" => self::FORM_12Q,
        '12a-shakl'     => self::FORM_12A,
        "12a-qo'shimcha" => self::FORM_12AQ,
        "12a-qo'shimcha (guruh)" => self::FORM_12AG,
        '12b-shakl'     => self::FORM_12B,
        "12b-qo'shimcha" => self::FORM_12BQ,
        "12b-qo'shimcha (guruh)" => self::FORM_12BG,
    ];

    protected $fillable = [
        'education_year',
        'semester_code',
        'group_hemis_id',
        'group_name',
        'curriculum_hemis_id',
        'curriculum_subject_id',
        'subject_id',
        'subject_name',
        'department_hemis_id',
        'department_name',
        'specialty_name',
        'closing_form',
        'form_type',
        'teacher_hemis_id',
        'teacher_name',
        'teacher_phone',
        'fan_masuli_hemis_id',
        'fan_masuli_name',
        'fan_masuli_phone',
        'kafedra_mudiri_hemis_id',
        'kafedra_mudiri_name',
        'kafedra_mudiri_phone',
        'base_type',
        'base_date',
        'deadline',
        'status',
        'pdf_path',
        'excel_path',
        'uploaded_by',
        'uploaded_by_name',
        'uploaded_at',
        'reviewed_by',
        'reviewed_by_name',
        'reviewed_at',
        'rejection_reason',
        'reupload_allowed_at',
        'reupload_allowed_by',
        'reupload_allowed_by_name',
        'ai_check_status',
        'ai_verdict',
        'ai_summary',
        'ai_result',
        'ai_error',
        'ai_checked_at',
        'prorektor_notified_at',
        'warning_stage',
        'warned_at',
    ];

    protected $casts = [
        'base_date' => 'date',
        'deadline' => 'date',
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'reupload_allowed_at' => 'datetime',
        'ai_checked_at' => 'datetime',
        'ai_result' => 'array',
        'prorektor_notified_at' => 'datetime',
        'warned_at' => 'datetime',
    ];

    public function curriculumSubject()
    {
        return $this->belongsTo(CurriculumSubject::class, 'curriculum_subject_id');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_hemis_id', 'group_hemis_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_hemis_id', 'hemis_id');
    }

    public function logs()
    {
        return $this->hasMany(VedomostSubmissionLog::class)->orderByDesc('created_at');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Kutilmoqda',
            self::STATUS_RECEIVED => 'Qabul qilindi',
            self::STATUS_REVIEWING => 'Tekshirilmoqda',
            self::STATUS_APPROVED => 'Tasdiqlandi',
            self::STATUS_REJECTED => 'Rad etildi',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    /**
     * Vedomost shakli yorliqlari.
     */
    public static function formLabels(): array
    {
        return [
            self::FORM_12 => '12-shakl',
            self::FORM_12Q => '12-qo\'shimcha',
            self::FORM_12A => '12a-shakl',
            self::FORM_12AQ => '12a-qo\'shimcha',
            self::FORM_12AG => '12a-qo\'shimcha (guruh)',
            self::FORM_12B => '12b-shakl',
            self::FORM_12BQ => '12b-qo\'shimcha',
            self::FORM_12BG => '12b-qo\'shimcha (guruh)',
        ];
    }

    public static function formLabel(?string $formType): string
    {
        return self::formLabels()[$formType] ?? ($formType ?: '12-shakl');
    }

    /**
     * Qo'lda ochish uchun ruxsat beriladigan shakllar.
     */
    public static function manualOpenableForms(?string $closingForm): array
    {
        $labels = self::formLabels();

        if (in_array($closingForm, ['oski', 'test', 'oski_test'], true)) {
            return $labels;
        }

        return [
            self::FORM_12 => $labels[self::FORM_12],
        ];
    }

    /**
     * 12a/12b — umumiy (guruhsiz, hamma guruh bitta varaqda) shaklmi?
     */
    public function isCombinedForm(): bool
    {
        return in_array($this->form_type, self::COMBINED_FORMS, true);
    }

    /**
     * Rad etilgan vedomostni qayta yuklash mumkinmi?
     * Faqat o'quv prorektori ruxsat bergan (reupload_allowed_at to'ldirilgan) bo'lsa.
     */
    public function reuploadPermitted(): bool
    {
        return $this->status === self::STATUS_REJECTED && $this->reupload_allowed_at !== null;
    }

    /**
     * Deadline o'tib ketgan va hali tasdiqlanmagan bo'lsa — kechikkan.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->deadline || $this->status === self::STATUS_APPROVED) {
            return false;
        }

        return $this->deadline->isPast() && !$this->deadline->isToday();
    }
}

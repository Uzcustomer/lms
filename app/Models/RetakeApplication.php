<?php

namespace App\Models;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetakeApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_group_id',
        'student_id',
        'subject_id',
        'subject_name',
        'semester_id',
        'semester_name',
        'credit',
        'period_id',
        'receipt_path',
        'receipt_original_name',
        'receipt_size',
        'receipt_mime',
        'student_note',
        'generated_doc_path',
        'dean_status',
        'registrar_status',
        'academic_dept_status',
        'dean_reviewed_by',
        'dean_reviewed_by_guard',
        'dean_reviewed_at',
        'dean_rejection_reason',
        'registrar_reviewed_by',
        'registrar_reviewed_by_guard',
        'registrar_reviewed_at',
        'registrar_rejection_reason',
        'academic_dept_reviewed_by',
        'academic_dept_reviewed_by_guard',
        'academic_dept_reviewed_at',
        'academic_dept_rejection_reason',
        'retake_group_id',
        'verification_code',
        'tasdiqnoma_pdf_path',
        'submitted_at',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'semester_id' => 'integer',
        'credit' => 'decimal:2',
        'receipt_size' => 'integer',
        'dean_status' => RetakeReviewStatus::class,
        'registrar_status' => RetakeReviewStatus::class,
        'academic_dept_status' => RetakeAcademicDeptStatus::class,
        'dean_reviewed_at' => 'datetime',
        'registrar_reviewed_at' => 'datetime',
        'academic_dept_reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(RetakeApplicationPeriod::class, 'period_id');
    }

    public function retakeGroup(): BelongsTo
    {
        return $this->belongsTo(RetakeGroup::class, 'retake_group_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RetakeApplicationLog::class, 'application_id')->orderBy('created_at');
    }

    /**
     * Yakuniy holat: birortasi rejected → rejected; uchchalasi approved → approved;
     * boshqa hollarda — kutish bosqichida (pending).
     */
    public function getFinalStatusAttribute(): string
    {
        if ($this->dean_status === RetakeReviewStatus::REJECTED
            || $this->registrar_status === RetakeReviewStatus::REJECTED
            || $this->academic_dept_status === RetakeAcademicDeptStatus::REJECTED) {
            return 'rejected';
        }

        if ($this->dean_status === RetakeReviewStatus::APPROVED
            && $this->registrar_status === RetakeReviewStatus::APPROVED
            && $this->academic_dept_status === RetakeAcademicDeptStatus::APPROVED) {
            return 'approved';
        }

        return 'pending';
    }

    public function getStageDescriptionAttribute(): string
    {
        if ($this->final_status === 'rejected') {
            return $this->getRejectionDescription();
        }

        if ($this->final_status === 'approved') {
            return 'Tasdiqlangan';
        }

        $deanApproved = $this->dean_status === RetakeReviewStatus::APPROVED;
        $registrarApproved = $this->registrar_status === RetakeReviewStatus::APPROVED;

        if ($this->academic_dept_status === RetakeAcademicDeptStatus::PENDING) {
            return "So'nggi bosqich — O'quv bo'limida kutishda";
        }

        if ($deanApproved && ! $registrarApproved) {
            return 'Registrator ofisi ko\'rib chiqmoqda (dekan tasdiqlagan)';
        }

        if ($registrarApproved && ! $deanApproved) {
            return 'Dekan ko\'rib chiqmoqda (registrator tasdiqlagan)';
        }

        return 'Dekan va Registrator ofisi ko\'rib chiqmoqda';
    }

    private function getRejectionDescription(): string
    {
        if ($this->dean_status === RetakeReviewStatus::REJECTED) {
            return "Rad etilgan: Dekan — {$this->dean_rejection_reason}";
        }
        if ($this->registrar_status === RetakeReviewStatus::REJECTED) {
            return "Rad etilgan: Registrator — {$this->registrar_rejection_reason}";
        }
        if ($this->academic_dept_status === RetakeAcademicDeptStatus::REJECTED) {
            return "Rad etilgan: O'quv bo'limi — {$this->academic_dept_rejection_reason}";
        }
        return 'Rad etilgan';
    }

    public function scopePendingDean(Builder $query): Builder
    {
        return $query->where('dean_status', RetakeReviewStatus::PENDING->value);
    }

    public function scopePendingRegistrar(Builder $query): Builder
    {
        return $query->where('registrar_status', RetakeReviewStatus::PENDING->value);
    }

    public function scopePendingAcademicDept(Builder $query): Builder
    {
        return $query->where('academic_dept_status', RetakeAcademicDeptStatus::PENDING->value);
    }

    /**
     * Bitta fanga aktiv ariza bormi (boshqa fanlar bilan birga yuborilgan bo'lsa ham)
     * — yangi yuborishni bloklash uchun.
     */
    public function scopeActiveForStudentSubject(Builder $query, int $studentId, int $subjectId, int $semesterId): Builder
    {
        return $query->where('student_id', $studentId)
            ->where('subject_id', $subjectId)
            ->where('semester_id', $semesterId)
            ->where(function (Builder $q) {
                $q->where('dean_status', '!=', RetakeReviewStatus::REJECTED->value)
                    ->where('registrar_status', '!=', RetakeReviewStatus::REJECTED->value)
                    ->where('academic_dept_status', '!=', RetakeAcademicDeptStatus::REJECTED->value);
            });
    }
}

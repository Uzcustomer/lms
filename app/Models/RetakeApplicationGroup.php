<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RetakeApplicationGroup extends Model
{
    protected $fillable = [
        'group_uuid',
        'student_id',
        'student_hemis_id',
        'window_id',
        'receipt_path',
        'receipt_amount',
        'credit_price_at_time',
        'comment',
        'docx_path',
        'pdf_certificate_path',
        'verification_token',
        'payment_receipt_path',
        'payment_uploaded_at',
    ];

    protected $casts = [
        'receipt_amount' => 'decimal:2',
        'credit_price_at_time' => 'decimal:2',
        'payment_uploaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $group) {
            if (empty($group->group_uuid)) {
                $group->group_uuid = (string) Str::uuid();
            }
        });
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function window()
    {
        return $this->belongsTo(RetakeApplicationWindow::class, 'window_id');
    }

    public function applications()
    {
        return $this->hasMany(RetakeApplication::class, 'group_id');
    }

    public function getTotalCreditsAttribute(): float
    {
        return (float) $this->applications->sum('credit');
    }

    public function getOverallStatusAttribute(): string
    {
        $statuses = $this->applications->pluck('final_status')->unique();

        if ($statuses->count() === 1) {
            return $statuses->first(); // hammasi bir xil holatda
        }
        return 'mixed'; // turli holatlar
    }

    /**
     * Talaba to'lov chekini yuklashi kerakmi?
     * Hech bo'lmaganda bitta ariza dual-approved bo'lib, hali to'lov yuklanmagan bo'lsa.
     */
    public function getRequiresPaymentAttribute(): bool
    {
        if ($this->payment_uploaded_at !== null) {
            return false;
        }

        return $this->applications->contains(function (RetakeApplication $a) {
            return $a->dean_status === RetakeApplication::STATUS_APPROVED
                && $a->registrar_status === RetakeApplication::STATUS_APPROVED
                && $a->academic_dept_status === RetakeApplication::STATUS_PENDING
                && $a->final_status === RetakeApplication::STATUS_PENDING;
        });
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->payment_uploaded_at !== null;
    }
}

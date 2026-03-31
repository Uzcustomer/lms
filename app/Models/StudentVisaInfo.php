<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentVisaInfo extends Model
{
    protected $fillable = [
        'student_id',
        'birth_country',
        'birth_region',
        'birth_city',
        'passport_issued_place',
        'passport_number',
        'passport_issued_date',
        'passport_expiry_date',
        'birth_date',
        'registration_start_date',
        'registration_end_date',
        'visa_number',
        'visa_type',
        'visa_start_date',
        'visa_end_date',
        'visa_entries_count',
        'visa_stay_days',
        'visa_issued_place',
        'visa_issued_date',
        'entry_date',
        'firm',
        'firm_custom',
        'passport_scan_path',
        'visa_scan_path',
        'registration_doc_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'passport_handed_over',
        'passport_handed_at',
        'passport_received_by',
        'agreement_accepted',
    ];

    protected $casts = [
        'passport_issued_date' => 'date',
        'passport_expiry_date' => 'date',
        'birth_date' => 'date',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'visa_start_date' => 'date',
        'visa_end_date' => 'date',
        'visa_issued_date' => 'date',
        'entry_date' => 'date',
        'reviewed_at' => 'datetime',
        'passport_handed_at' => 'datetime',
        'passport_handed_over' => 'boolean',
        'agreement_accepted' => 'boolean',
    ];

    public const FIRM_OPTIONS = [
        'TIT' => 'TIT',
        'Global' => 'Global',
        'Euro Asia' => 'Euro Asia',
        'Independent' => 'Independent (o\'zi kelgan)',
    ];

    public const VISA_TYPES = [
        'A-1' => 'A-1',
        'A-2' => 'A-2',
        'B-1' => 'B-1',
        'B-2' => 'B-2',
        'D-1' => 'D-1',
        'D-2' => 'D-2',
        'E-1' => 'E-1',
        'E-2' => 'E-2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function passportReceiver()
    {
        return $this->belongsTo(User::class, 'passport_received_by');
    }

    public function getFirmDisplayAttribute(): string
    {
        if ($this->firm === 'other') {
            return $this->firm_custom ?? '';
        }

        return self::FIRM_OPTIONS[$this->firm] ?? $this->firm ?? '';
    }

    public function registrationDaysLeft(): ?int
    {
        if (!$this->registration_end_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->registration_end_date, false);
    }

    public function visaDaysLeft(): ?int
    {
        if (!$this->visa_end_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->visa_end_date, false);
    }
}

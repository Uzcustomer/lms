<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentVisaInfoHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'visa_info_id',
        'birth_country',
        'birth_region',
        'birth_city',
        'birth_date',
        'passport_number',
        'passport_issued_place',
        'passport_issued_date',
        'passport_expiry_date',
        'passport_scan_path',
        'registration_start_date',
        'registration_end_date',
        'registration_doc_path',
        'registration_process_status',
        'address_type',
        'current_address',
        'visa_number',
        'visa_type',
        'visa_start_date',
        'visa_end_date',
        'visa_issued_place',
        'visa_issued_date',
        'visa_entries_count',
        'visa_stay_days',
        'visa_scan_path',
        'visa_process_status',
        'entry_date',
        'firm',
        'firm_custom',
        'status',
        'rejection_reason',
        'change_type',
        'changed_fields',
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_role',
        'note',
        'created_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'passport_issued_date' => 'date',
        'passport_expiry_date' => 'date',
        'registration_start_date' => 'date',
        'registration_end_date' => 'date',
        'visa_start_date' => 'date',
        'visa_end_date' => 'date',
        'visa_issued_date' => 'date',
        'entry_date' => 'date',
        'changed_fields' => 'array',
        'created_at' => 'datetime',
    ];

    // change_type ifoda turlari
    public const CHANGE_CREATED = 'created';
    public const CHANGE_UPDATED = 'updated';
    public const CHANGE_APPROVED = 'approved';
    public const CHANGE_REJECTED = 'rejected';
    public const CHANGE_PASSPORT_ACCEPTED = 'passport_accepted';
    public const CHANGE_MARK_REGISTERING = 'mark_registering';
    public const CHANGE_PASSPORT_RETURNED = 'passport_returned';
    public const CHANGE_FIRM_ASSIGNED = 'firm_assigned';
    public const CHANGE_DELETED = 'deleted';

    public const CHANGE_LABELS = [
        self::CHANGE_CREATED => 'Yaratildi',
        self::CHANGE_UPDATED => 'Tahrirlandi',
        self::CHANGE_APPROVED => 'Tasdiqlandi',
        self::CHANGE_REJECTED => 'Rad etildi',
        self::CHANGE_PASSPORT_ACCEPTED => 'Pasport qabul qilindi',
        self::CHANGE_MARK_REGISTERING => 'Jarayonga olindi',
        self::CHANGE_PASSPORT_RETURNED => 'Pasport qaytarildi',
        self::CHANGE_FIRM_ASSIGNED => 'Firma biriktirildi',
        self::CHANGE_DELETED => 'O\'chirildi',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function getChangeLabelAttribute(): string
    {
        return self::CHANGE_LABELS[$this->change_type] ?? $this->change_type;
    }
}

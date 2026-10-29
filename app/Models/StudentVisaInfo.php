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
        'address_type',
        'current_address',
        'registration_process_status',
        'visa_number',
        'visa_type',
        'visa_start_date',
        'visa_end_date',
        'visa_process_status',
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
        'visa_info_deadline',
        'residence_basis',
        'residence_permit_number',
        'residence_permit_pinfl',
        'residence_permit_issued_place',
        'residence_permit_issued_date',
        'residence_permit_expiry_date',
        'residence_permit_scan_path',
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
        'visa_info_deadline' => 'datetime',
        'residence_permit_issued_date' => 'date',
        'residence_permit_expiry_date' => 'date',
    ];

    // O'zbekistonda turish asosi
    public const BASIS_VISA = 'visa';                         // Viza + registratsiya
    public const BASIS_VISA_FREE = 'visa_free';               // Vizasiz, faqat registratsiya
    public const BASIS_RESIDENCE_PERMIT = 'residence_permit'; // Yashash guvohnomasi

    public const BASIS_LABELS = [
        self::BASIS_VISA => 'Viza bilan',
        self::BASIS_VISA_FREE => 'Vizasiz',
        self::BASIS_RESIDENCE_PERMIT => 'Yashash guvohnomasi',
    ];

    /** Standart qiymat — sozlamalarda o'zgartiriladi (VisaSettings::firmOptions) */
    public const FIRM_OPTIONS = [
        'TIT' => 'TIT',
        'Global' => 'Global',
        'Euro Asia' => 'Euro Asia',
        'Independent' => 'Independent (o\'zi kelgan)',
    ];

    /** Yotoqxonani tanlagan talabaning manzili — standart qiymat (VisaSettings::dormitoryAddress) */
    public const DORMITORY_ADDRESS = "Toshkent davlat tibbiyot universiteti Termiz filiali talabalar yotoqxonasi, Termiz shahar, I.Karimov ko'chasi 64-uy";

    /** Standart qiymat — sozlamalarda o'zgartiriladi (VisaSettings::visaTypes) */
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

    // Jarayon holatlari: none -> passport_accepted -> registering -> done
    public const PROCESS_NONE = 'none';                     // Hali boshlanmagan
    public const PROCESS_PASSPORT_ACCEPTED = 'passport_accepted'; // Pasport qabul qilindi
    public const PROCESS_REGISTERING = 'registering';       // Registratsiya qilinmoqda
    public const PROCESS_DONE = 'done';                     // Tugallandi, pasport qaytarildi

    public const VISA_FIELDS = [
        'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date', 'visa_entries_count',
        'visa_stay_days', 'visa_issued_place', 'visa_issued_date', 'visa_scan_path',
    ];

    public const REGISTRATION_FIELDS = [
        'registration_start_date', 'registration_end_date', 'registration_doc_path',
    ];

    public const RESIDENCE_PERMIT_FIELDS = [
        'residence_permit_number', 'residence_permit_pinfl', 'residence_permit_issued_place',
        'residence_permit_issued_date', 'residence_permit_expiry_date', 'residence_permit_scan_path',
    ];

    /**
     * Shu asosga tegishli bo'lmagan maydonlar (null qilinadi), aks holda
     * asos almashganda eski viza/registratsiya muddati bo'yicha ogohlantirish kelaveradi.
     */
    public static function fieldsClearedFor(string $basis): array
    {
        $fields = match ($basis) {
            self::BASIS_VISA_FREE => [...self::VISA_FIELDS, ...self::RESIDENCE_PERMIT_FIELDS],
            self::BASIS_RESIDENCE_PERMIT => [...self::VISA_FIELDS, ...self::REGISTRATION_FIELDS],
            default => self::RESIDENCE_PERMIT_FIELDS,
        };

        return array_fill_keys($fields, null);
    }

    /** Eski yozuvlarda asos bo'sh — ular viza bilan kelgan deb hisoblanadi */
    public function basis(): string
    {
        return $this->residence_basis ?: self::BASIS_VISA;
    }

    public function basisLabel(): string
    {
        return self::BASIS_LABELS[$this->basis()];
    }

    public function needsVisa(): bool
    {
        return $this->basis() === self::BASIS_VISA;
    }

    public function needsRegistration(): bool
    {
        return $this->basis() !== self::BASIS_RESIDENCE_PERMIT;
    }

    public function hasResidencePermit(): bool
    {
        return $this->basis() === self::BASIS_RESIDENCE_PERMIT;
    }

    public function isRegistrationProcessActive(): bool
    {
        return in_array($this->registration_process_status, [self::PROCESS_PASSPORT_ACCEPTED, self::PROCESS_REGISTERING]);
    }

    public function isVisaProcessActive(): bool
    {
        return in_array($this->visa_process_status, [self::PROCESS_PASSPORT_ACCEPTED, self::PROCESS_REGISTERING]);
    }

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

    /** Turar joy turi: yotoqxona yoki talaba kiritgan manzil */
    public function residenceTypeLabel(): string
    {
        return match ($this->address_type) {
            'dormitory' => 'Yotoqxona',
            'other' => 'Boshqa manzil',
            default => '-',
        };
    }

    /** To'liq manzil: yotoqxonaniki doimiy, boshqasi — talaba kiritgani */
    public function residenceAddress(): string
    {
        return match ($this->address_type) {
            'dormitory' => \App\Services\VisaSettings::dormitoryAddress(),
            'other' => trim((string) $this->current_address) ?: '-',
            default => '-',
        };
    }

    public function getFirmDisplayAttribute(): string
    {
        if ($this->firm === 'other') {
            return $this->firm_custom ?? '';
        }

        return \App\Services\VisaSettings::firmOptions()[$this->firm] ?? $this->firm ?? '';
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

    public function residencePermitDaysLeft(): ?int
    {
        if (!$this->residence_permit_expiry_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->residence_permit_expiry_date, false);
    }
}

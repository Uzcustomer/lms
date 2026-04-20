<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'name',
        'type',
        'description',
        'file_path',
        'file_original_name',
        'placeholders',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'placeholders' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Shablon turlari va ularning placeholder'lari
     */
    const TYPES = [
        'absence_excuse' => [
            'label' => 'Sababli ariza farmoyishi',
            'placeholders' => [
                '${student_id}' => 'Talaba ID raqami (student_id_number)',
                '${student_name}' => 'Talaba to\'liq ismi',
                '${student_hemis_id}' => 'HEMIS ID raqami',
                '${group_name}' => 'Guruh nomi',
                '${department_name}' => 'Fakultet/Kafedra nomi',
                '${reason}' => 'Sababli qoldirish sababi',
                '${reason_document}' => 'Talab qilinadigan hujjat nomi',
                '${start_date}' => 'Boshlanish sanasi (01.01.2026)',
                '${end_date}' => 'Tugash sanasi (10.01.2026)',
                '${days_count}' => 'Kunlar soni',
                '${review_date}' => 'Ko\'rib chiqilgan sana (01.01.2026)',
                '${review_date_full}' => 'Sana to\'liq (2026 yil 1-yanvar)',
                '${reviewer_name}' => 'Ko\'rib chiquvchi ismi',
                '${order_number}' => 'Buyruq raqami (08-00001)',
                '${academic_year}' => 'O\'quv yili (2025.2026)',
                '${qr_code}' => 'QR kod rasmi (rasm sifatida)',
                '${verification_url}' => 'Tekshirish URL manzili',
                '${m_num}' => 'Nazorat tartib raqami (1, 2, 3...)',
                '${m_subject}' => 'Fan nomi',
                '${m_type}' => 'Nazorat turi (Joriy nazorat, YN (OSKE), ...)',
                '${m_date}' => 'Qayta topshirish sanasi (01.01.2026 yoki 01.01.2026 — 05.01.2026)',
            ],
        ],
        'student_contract_3' => [
            'label' => 'Ishga joylashish shartnomasi (3 tomonlama)',
            'placeholders' => [
                '${student_name}' => 'Bitiruvchi to\'liq ismi (FAMILIYA ISM OTASINING ISMI)',
                '${student_address}' => 'Bitiruvchi manzili (tuman, MFY, ko\'cha, uy)',
                '${student_phone}' => 'Bitiruvchi telefon raqami',
                '${student_passport}' => 'Bitiruvchi passport seriya va raqami',
                '${student_bank_account}' => 'Bitiruvchi bank hisob raqami',
                '${student_bank_mfo}' => 'Bitiruvchi bank MFO',
                '${student_inn}' => 'Bitiruvchi INN',
                '${group_name}' => 'Guruh nomi',
                '${department_name}' => 'Fakultet nomi',
                '${specialty_name}' => 'Yo\'nalish nomi',
                '${specialty_field}' => 'Mutaxassislik (masalan: Davolash)',
                '${contract_year}' => 'Bitirish yili (2026)',
                '${employer_name}' => 'Ish beruvchi tashkilot nomi',
                '${employer_address}' => 'Ish beruvchi manzili',
                '${employer_phone}' => 'Ish beruvchi telefon',
                '${employer_director_name}' => 'Ish beruvchi rahbar FIO',
                '${employer_director_position}' => 'Ish beruvchi rahbar lavozimi',
                '${employer_bank_account}' => 'Ish beruvchi bank hisob raqami',
                '${employer_bank_mfo}' => 'Ish beruvchi MFO',
                '${employer_inn}' => 'Ish beruvchi INN',
            ],
        ],
        'student_contract_4' => [
            'label' => 'Ishga joylashish shartnomasi (4 tomonlama)',
            'placeholders' => [
                '${student_name}' => 'Bitiruvchi to\'liq ismi (FAMILIYA ISM OTASINING ISMI)',
                '${student_address}' => 'Bitiruvchi manzili (tuman, MFY, ko\'cha, uy)',
                '${student_phone}' => 'Bitiruvchi telefon raqami',
                '${student_passport}' => 'Bitiruvchi passport seriya va raqami',
                '${student_bank_account}' => 'Bitiruvchi bank hisob raqami',
                '${student_bank_mfo}' => 'Bitiruvchi bank MFO',
                '${student_inn}' => 'Bitiruvchi INN',
                '${group_name}' => 'Guruh nomi',
                '${department_name}' => 'Fakultet nomi',
                '${specialty_name}' => 'Yo\'nalish nomi',
                '${specialty_field}' => 'Mutaxassislik (masalan: Davolash)',
                '${contract_year}' => 'Bitirish yili (2026)',
                '${employer_name}' => 'Ish beruvchi tashkilot nomi',
                '${employer_address}' => 'Ish beruvchi manzili',
                '${employer_phone}' => 'Ish beruvchi telefon',
                '${employer_director_name}' => 'Ish beruvchi rahbar FIO',
                '${employer_director_position}' => 'Ish beruvchi rahbar lavozimi',
                '${employer_bank_account}' => 'Ish beruvchi bank hisob raqami',
                '${employer_bank_mfo}' => 'Ish beruvchi MFO',
                '${employer_inn}' => 'Ish beruvchi INN',
                '${fourth_party_name}' => 'Tuman sog\'liqni saqlash bosh boshqarmasi nomi',
                '${fourth_party_address}' => '4-tomon manzili',
                '${fourth_party_phone}' => '4-tomon telefon',
                '${fourth_party_director_name}' => 'Tuman sog\'liqni saqlash bosh boshqarmasi boshlig\'i FIO',
            ],
        ],
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public static function getActiveByType(string $type): ?self
    {
        return static::active()->byType($type)->first();
    }

    public static function typeLabels(): array
    {
        return collect(self::TYPES)->mapWithKeys(fn ($data, $key) => [$key => $data['label']])->toArray();
    }

    public static function getPlaceholdersForType(string $type): array
    {
        return self::TYPES[$type]['placeholders'] ?? [];
    }
}

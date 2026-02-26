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
                '${academic_year}' => 'O\'quv yili (2025.2026)',
                '${qr_code}' => 'QR kod rasmi (rasm sifatida)',
                '${verification_url}' => 'Tekshirish URL manzili',
                // Nazoratlar jadvali (Word jadvalda cloneRow bilan ishlaydi)
                // Bitta fan uchun bir nechta nazorat bo'lsa, bitta qatorda ko'rsatiladi
                '${order_number}' => 'Jadvalda: T/r (1, 2, 3...), Matndа: Buyruq raqami (08-00001)',
                '${subject_name}' => 'Fan nomi',
                '${test_type}' => 'Nazorat turi (JN, YN(Test), YN(OSKE), ...)',
                '${test_date_period}' => 'Qayta topshirish muddati (03.11.2025 dan 07.11.2025 gacha)',
                '${test_single_date}' => 'Qayta topshirish sanasi (08.11.2025)',
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

<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentVisaInfo;
use App\Models\VisaCountryRule;
use Illuminate\Support\Facades\Schema;

/**
 * Xalqaro talabalar bo'limi sozlamalari (admin "Sozlamalar" oynasidan).
 * Qiymat saqlanmagan bo'lsa, oldingi qattiq yozilgan qiymatlar ishlatiladi.
 */
class VisaSettings
{
    /** Ogohlantirish chegaralari (kun): info — eslatma boshlanadi, warning, danger */
    public const THRESHOLD_DEFAULTS = [
        'registration' => ['info' => 7, 'warning' => 5, 'danger' => 3],
        'visa' => ['info' => 30, 'warning' => 20, 'danger' => 15],
        'residence_permit' => ['info' => 30, 'warning' => 20, 'danger' => 15],
    ];

    public const DEFAULT_INFO_DEADLINE_DAYS = 3;

    /** Har bir turish asosi uchun forma ko'rsatadigan hujjatlar */
    public const DOCS_BY_BASIS = [
        StudentVisaInfo::BASIS_VISA => ['passport_scan', 'visa_scan', 'registration_doc'],
        StudentVisaInfo::BASIS_VISA_FREE => ['passport_scan', 'registration_doc'],
        StudentVisaInfo::BASIS_RESIDENCE_PERMIT => ['passport_scan', 'residence_permit_scan'],
    ];

    public const DOC_LABELS = [
        'passport_scan' => 'Pasport skaneri',
        'visa_scan' => 'Viza skaneri',
        'registration_doc' => "Ro'yxatga olish hujjati",
        'residence_permit_scan' => 'Yashash guvohnomasi skaneri',
    ];

    private static array $cache = [];

    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function json(string $key, $default)
    {
        if (!array_key_exists($key, self::$cache)) {
            $raw = Setting::get($key);
            $decoded = $raw !== null ? json_decode($raw, true) : null;
            self::$cache[$key] = is_array($decoded) ? $decoded : $default;
        }
        return self::$cache[$key];
    }

    private static function value(string $key, $default)
    {
        if (!array_key_exists($key, self::$cache)) {
            self::$cache[$key] = Setting::get($key, $default);
        }
        return self::$cache[$key];
    }

    /** @param string $type registration|visa|residence_permit */
    public static function thresholds(string $type): array
    {
        $saved = self::json('intl_thresholds', []);
        return array_merge(self::THRESHOLD_DEFAULTS[$type], array_map('intval', $saved[$type] ?? []));
    }

    public static function threshold(string $type, string $level): int
    {
        return self::thresholds($type)[$level];
    }

    /** Talaba viza ma'lumotini qayta kiritishi uchun beriladigan kunlar */
    public static function infoDeadlineDays(): int
    {
        return max(1, (int) self::value('intl_info_deadline_days', self::DEFAULT_INFO_DEADLINE_DAYS));
    }

    public static function visaTypes(): array
    {
        $types = self::json('intl_visa_types', null);
        if (!$types) {
            return StudentVisaInfo::VISA_TYPES;
        }
        return array_combine($types, $types);
    }

    public static function firmOptions(): array
    {
        $firms = self::json('intl_firm_options', null);
        return $firms ?: StudentVisaInfo::FIRM_OPTIONS;
    }

    public static function dormitoryAddress(): string
    {
        $address = trim((string) self::value('intl_dormitory_address', ''));
        return $address !== '' ? $address : StudentVisaInfo::DORMITORY_ADDRESS;
    }

    /** Shu turish asosida majburiy hujjatlar (input nomlari) */
    public static function requiredDocs(string $basis): array
    {
        $saved = self::json('intl_required_docs', null);
        $applicable = self::DOCS_BY_BASIS[$basis] ?? self::DOCS_BY_BASIS[StudentVisaInfo::BASIS_VISA];
        if (!is_array($saved) || !array_key_exists($basis, $saved)) {
            return $applicable;
        }
        return array_values(array_intersect($applicable, (array) $saved[$basis]));
    }

    /** Ro'yxatda yo'q davlatlar uchun qoida */
    public static function fallbackRule(): array
    {
        return [
            'show_choice' => self::value('intl_choice_default_show', '1') === '1',
            'default_basis' => self::validBasis(self::value('intl_choice_default_basis', StudentVisaInfo::BASIS_VISA)),
        ];
    }

    /**
     * Talabaga tanlov ko'rsatiladimi va ko'rsatilmasa qaysi asos qo'yiladi.
     * Davlat — HEMIS dagi country_name.
     */
    public static function ruleFor(?Student $student): array
    {
        $country = trim((string) ($student?->country_name ?? ''));
        if ($country !== '' && Schema::hasTable('visa_country_rules')) {
            $rule = VisaCountryRule::where('country_name', $country)->first();
            if ($rule) {
                return [
                    'show_choice' => (bool) $rule->show_choice,
                    'default_basis' => self::validBasis($rule->default_basis),
                ];
            }
        }
        return self::fallbackRule();
    }

    public static function validBasis(?string $basis): string
    {
        return array_key_exists((string) $basis, StudentVisaInfo::BASIS_LABELS)
            ? $basis
            : StudentVisaInfo::BASIS_VISA;
    }
}

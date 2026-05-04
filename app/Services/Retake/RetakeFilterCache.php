<?php

namespace App\Services\Retake;

use App\Models\RetakeApplication;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;

/**
 * Retake bo'limidagi sahifalar uchun keshlangan ma'lumot manbalari.
 * Filtr dropdownlari uchun ishlatiladigan ro'yxatlar har sahifa ochilganda
 * to'liq jadval skan qilmasligi uchun keshlanadi.
 */
class RetakeFilterCache
{
    public const TTL_EDUCATION_TYPES = 3600;   // 1 soat — kamdan-kam o'zgaradi
    public const TTL_SUBJECTS = 300;           // 5 daqiqa — yangi ariza qo'shilsa yangilanadi
    public const TTL_SIDEBAR_COUNT = 60;       // 1 daqiqa — sidebar badge

    /**
     * Talaba ta'lim turlari (Bakalavr, Magistr, ...).
     */
    public static function educationTypes()
    {
        return Cache::remember('retake.filter.education_types', self::TTL_EDUCATION_TYPES, function () {
            return Student::query()
                ->select('education_type_code', 'education_type_name')
                ->whereNotNull('education_type_code')
                ->distinct()
                ->orderBy('education_type_name')
                ->get();
        });
    }

    /**
     * Qayta o'qish arizalaridagi unikal fanlar.
     */
    public static function subjects()
    {
        return Cache::remember('retake.filter.subjects', self::TTL_SUBJECTS, function () {
            return RetakeApplication::query()
                ->select('subject_id', 'subject_name')
                ->whereNotNull('subject_id')
                ->orderBy('subject_name')
                ->distinct()
                ->get()
                ->mapWithKeys(fn ($a) => [$a->subject_id => $a->subject_name]);
        });
    }

    /**
     * Yangi ariza yuborilganda chaqiriladi — fanlar ro'yxati keshini tozalaydi.
     */
    public static function flushSubjects(): void
    {
        Cache::forget('retake.filter.subjects');
    }

    /**
     * Sidebar uchun: dekan/registrator + to'lov tasdiqi kutmoqda — soni.
     */
    public static function sidebarPendingCount(string $cacheKey, callable $resolver): int
    {
        return Cache::remember($cacheKey, self::TTL_SIDEBAR_COUNT, $resolver);
    }
}

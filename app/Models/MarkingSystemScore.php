<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarkingSystemScore extends Model
{
    protected $fillable = [
        'marking_system_code',
        'marking_system_name',
        'minimum_limit',
        'gpa_limit',
        'jn_limit',
        'jn_active',
        'mt_limit',
        'mt_active',
        'on_limit',
        'on_active',
        'oski_limit',
        'oski_active',
        'test_limit',
        'test_active',
        'total_limit',
        'total_active',
    ];

    protected $casts = [
        'minimum_limit' => 'integer',
        'gpa_limit' => 'float',
        'jn_limit' => 'integer',
        'jn_active' => 'boolean',
        'mt_limit' => 'integer',
        'mt_active' => 'boolean',
        'on_limit' => 'integer',
        'on_active' => 'boolean',
        'oski_limit' => 'integer',
        'oski_active' => 'boolean',
        'test_limit' => 'integer',
        'test_active' => 'boolean',
        'total_limit' => 'integer',
        'total_active' => 'boolean',
    ];

    /** settings jadvalidagi kalit: LMS bo'yicha minimal chegara (bo'sh — HEMIS qiymati) */
    public const SETTING_MINIMUM_LIMIT = 'grade_minimum_limit';

    private static ?int $lmsMinimumLimit = null;
    private static bool $lmsMinimumLimitLoaded = false;

    /**
     * Minimal chegara (past baho → otrabotka, MT qulfi, JN va hisobotlar): admin sozlamasida
     * belgilangan bo'lsa — barcha baholash tizimlari uchun shu, aks holda HEMIS qiymati.
     * HEMIS sinxronizatsiyasi faqat asl ustunni yangilaydi — sozlamaga tegmaydi.
     */
    public function getMinimumLimitAttribute($value): int
    {
        return self::lmsMinimumLimit() ?? (int) $value;
    }

    /** HEMIS dan kelgan asl qiymat (sozlamadan qat'i nazar) */
    public function hemisMinimumLimit(): int
    {
        return (int) $this->getRawOriginal('minimum_limit', $this->attributes['minimum_limit'] ?? 60);
    }

    public static function lmsMinimumLimit(): ?int
    {
        if (!self::$lmsMinimumLimitLoaded) {
            $value = Setting::get(self::SETTING_MINIMUM_LIMIT);
            self::$lmsMinimumLimit = is_numeric($value) ? (int) $value : null;
            self::$lmsMinimumLimitLoaded = true;
        }

        return self::$lmsMinimumLimit;
    }

    /**
     * Kunlik baho uchun past baho chegarasi (otrabotka huquqi, "asl baho chegaradan past
     * bo'lsa otrabotka bahosi ustun" qoidasi): sozlama, bo'lmasa avvalgidek 60.
     * OSKI/test va yakuniy ball (V<60) bunga kirmaydi.
     */
    public static function lowGradeLimit(): int
    {
        return self::lmsMinimumLimit() ?? 60;
    }

    public static function forgetLmsMinimumLimit(): void
    {
        self::$lmsMinimumLimitLoaded = false;
    }

    public function curricula()
    {
        return $this->hasMany(Curriculum::class, 'marking_system_code', 'marking_system_code');
    }

    /**
     * Get MarkingSystemScore by student's hemis_id.
     * Caches results to avoid repeated queries within same request.
     */
    public static function getByStudentHemisId($studentHemisId): self
    {
        static $cache = [];

        if (isset($cache[$studentHemisId])) {
            return $cache[$studentHemisId];
        }

        $student = Student::where('hemis_id', $studentHemisId)->first();
        $markingSystemCode = optional(optional($student)->curriculum)->marking_system_code;

        $score = $markingSystemCode
            ? static::where('marking_system_code', $markingSystemCode)->first()
            : null;

        $cache[$studentHemisId] = $score ?? static::getDefault();
        return $cache[$studentHemisId];
    }

    /**
     * Get default marking system score (fallback when no record found).
     */
    public static function getDefault(): self
    {
        $default = new static;
        $default->minimum_limit = 60;
        $default->gpa_limit = 2.0;
        $default->jn_limit = 60;
        $default->jn_active = true;
        $default->mt_limit = 60;
        $default->mt_active = true;
        $default->on_limit = 60;
        $default->on_active = false;
        $default->oski_limit = 60;
        $default->oski_active = true;
        $default->test_limit = 60;
        $default->test_active = true;
        $default->total_limit = 60;
        $default->total_active = true;
        return $default;
    }

    /**
     * YN ga ruxsat uchun JN va MT chegarasi — barcha talabalar uchun, xalqaro ta'lim
     * fakulteti (PIMU) ham (avval PIMU 60 da qolardi).
     */
    public const YN_JN_MT_LIMIT = 70;

    /** PIMU talabalari uchun vedomost hisobidagi JN/MT o'tish chegarasi — endi boshqalar bilan bir xil. */
    public const INTERNATIONAL_VEDOMOST_PASS = self::YN_JN_MT_LIMIT;

    /**
     * YN ga ruxsat (YN jadval, qaytnoma, vedomost) uchun chegaralar:
     * getByStudentHemisId() + barcha talabalar uchun JN va MT 70.
     */
    public static function forYnAdmission($studentHemisId): self
    {
        $score = static::getByStudentHemisId($studentHemisId);

        $raiseJn = $score->jn_active && $score->jn_limit < self::YN_JN_MT_LIMIT;
        $raiseMt = $score->mt_active && $score->mt_limit < self::YN_JN_MT_LIMIT;
        if (!$raiseJn && !$raiseMt) {
            return $score;
        }

        // getByStudentHemisId keshidagi obyekt boshqa talabalar bilan umumiy — nusxasini o'zgartiramiz
        $score = clone $score;
        if ($raiseJn) {
            $score->jn_limit = self::YN_JN_MT_LIMIT;
        }
        if ($raiseMt) {
            $score->mt_limit = self::YN_JN_MT_LIMIT;
        }

        return $score;
    }

    /**
     * Vedomost hisobidagi JN/MT o'tish chegarasi: barcha talabalar (PIMU ham) uchun 70.
     */
    public static function vedomostJnMtPass($studentHemisId): int
    {
        return self::YN_JN_MT_LIMIT;
    }

    /**
     * Get the effective limit for a given type (returns 0 if inactive).
     */
    public function effectiveLimit(string $type): int
    {
        $activeKey = $type . '_active';
        $limitKey = $type . '_limit';
        return $this->$activeKey ? $this->$limitKey : 0;
    }
}

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
     * YN ga ruxsat uchun JN chegarasi — xalqaro ta'lim fakulteti (PIMU) dan tashqari
     * barcha talabalar uchun. PIMU talabalari avvalgidek sozlamadagi chegarada qoladi.
     * Ular uchala baholash tizimida ham bor, shuning uchun qoida sozlamada emas,
     * fakultet bo'yicha.
     */
    public const YN_JN_LIMIT = 70;

    /** PIMU talabalari uchun vedomost hisobidagi JN o'tish chegarasi (avvalgidek). */
    public const INTERNATIONAL_VEDOMOST_JN_PASS = 60;

    /**
     * YN ga ruxsat (YN jadval, qaytnoma, vedomost) uchun chegaralar:
     * getByStudentHemisId() + PIMU dan tashqari talabalar uchun JN 70.
     */
    public static function forYnAdmission($studentHemisId): self
    {
        $score = static::getByStudentHemisId($studentHemisId);

        if (!$score->jn_active
            || $score->jn_limit >= self::YN_JN_LIMIT
            || static::isInternationalStudent($studentHemisId)) {
            return $score;
        }

        // getByStudentHemisId keshidagi obyekt boshqa talabalar bilan umumiy — nusxasini o'zgartiramiz
        $score = clone $score;
        $score->jn_limit = self::YN_JN_LIMIT;

        return $score;
    }

    /**
     * Vedomost hisobidagi JN o'tish chegarasi: PIMU uchun 60 (avvalgidek), qolganlarga 70.
     */
    public static function vedomostJnPass($studentHemisId): int
    {
        return static::isInternationalStudent($studentHemisId)
            ? self::INTERNATIONAL_VEDOMOST_JN_PASS
            : self::YN_JN_LIMIT;
    }

    public static function isInternationalStudent($studentHemisId): bool
    {
        static $cache = [];

        return $cache[$studentHemisId] ??= (bool) Student::where('hemis_id', $studentHemisId)->first()?->isInternationalFaculty();
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

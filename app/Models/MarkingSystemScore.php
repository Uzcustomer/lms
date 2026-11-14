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
     * Chegara oshirilgan sana (settings: grade_minimum_limit_from, sukut — 26.10.2026): YN imtihonlari
     * shu sanadan oldin tugagan fanlar uchun eski chegara (OLD_LIMIT) qoladi.
     */
    public const SETTING_LIMIT_FROM = 'grade_minimum_limit_from';
    public const DEFAULT_LIMIT_FROM = '2026-10-26';
    public const OLD_LIMIT = 60;

    /** Joriy fan (guruh × fan × semestr) konteksti: chegara oshirilishidan oldin tugagan bo'lsa — OLD_LIMIT */
    private static ?int $subjectLimit = null;

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
        if (self::$subjectLimit !== null) {
            return self::$subjectLimit;
        }
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

    /**
     * Fan YN imtihonlari (guruh jadvalidagi OSKI / test sanalari, "yo'q" deb belgilanganlari hisobga
     * olinmaydi) chegara oshirilgan sanadan oldin tugaganmi. Jadvali yo'q — yo'q (yangi chegara).
     */
    public static function finishedBeforeLimitChange(string $groupHemisId, string $subjectId, string $semesterCode): bool
    {
        static $cache = [];
        $key = $groupHemisId . '|' . $subjectId . '|' . $semesterCode;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $from = (string) (Setting::get(self::SETTING_LIMIT_FROM) ?: self::DEFAULT_LIMIT_FROM);
        $exam = \Illuminate\Support\Facades\Schema::hasTable('exam_schedules')
            ? \Illuminate\Support\Facades\DB::table('exam_schedules')->whereNull('student_hemis_id')
                ->where('group_hemis_id', $groupHemisId)->where('subject_id', $subjectId)->where('semester_code', $semesterCode)
                ->first(['oski_date', 'test_date', 'oski_na', 'test_na'])
            : null;
        $dates = $exam ? array_filter([$exam->oski_na ? null : $exam->oski_date, $exam->test_na ? null : $exam->test_date]) : [];

        return $cache[$key] = $dates !== [] && substr((string) max($dates), 0, 10) < $from;
    }

    /**
     * Shu so'rov (hisob) davomida chegara — shu fan bo'yicha: chegara oshirilishidan oldin tugagan bo'lsa
     * eski chegara (60), aks holda sozlamadagi. Jurnal, qaydnoma, YN — bitta guruh × fan bilan ishlaydi.
     */
    public static function useSubject(string $groupHemisId, string $subjectId, string $semesterCode): void
    {
        self::$subjectLimit = self::finishedBeforeLimitChange($groupHemisId, $subjectId, $semesterCode) ? self::OLD_LIMIT : null;
    }

    /** $fn ni shu fan chegarasi bilan bajaradi (avvalgi kontekst tiklanadi) */
    public static function withSubject(string $groupHemisId, string $subjectId, string $semesterCode, callable $fn): mixed
    {
        $previous = self::$subjectLimit;
        self::useSubject($groupHemisId, $subjectId, $semesterCode);
        try {
            return $fn();
        } finally {
            self::$subjectLimit = $previous;
        }
    }

    public static function forgetSubject(): void
    {
        self::$subjectLimit = null;
    }

    /** Joriy kontekst chegarasi (null — sozlamadagi) — vaqtincha almashtirib, keyin tiklash uchun */
    public static function subjectLimit(): ?int
    {
        return self::$subjectLimit;
    }

    public static function setSubjectLimit(?int $limit): void
    {
        self::$subjectLimit = $limit;
    }

    /** $fn ni berilgan chegara bilan bajaradi ("JN 70%" — keyingi urinishlar 70 da), keyin tiklanadi */
    public static function withLimit(int $limit, callable $fn): mixed
    {
        $previous = self::$subjectLimit;
        self::$subjectLimit = $limit;
        try {
            return $fn();
        } finally {
            self::$subjectLimit = $previous;
        }
    }

    /** YN ga ruxsat / vedomost JN-MT chegarasi: hammaga 70, chegara oshirilishidan oldin tugagan fanda — 60 */
    public static function ynJnMtLimit(): int
    {
        return self::$subjectLimit ?? self::YN_JN_MT_LIMIT;
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
        $limit = self::lowGradeLimit();
        $default = new static;
        $default->minimum_limit = 60;
        $default->gpa_limit = 2.0;
        $default->jn_limit = $limit;
        $default->jn_active = true;
        $default->mt_limit = $limit;
        $default->mt_active = true;
        $default->on_limit = $limit;
        $default->on_active = false;
        $default->oski_limit = $limit;
        $default->oski_active = true;
        $default->test_limit = $limit;
        $default->test_active = true;
        $default->total_limit = $limit;
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

        $limit = self::ynJnMtLimit();
        $raiseJn = $score->jn_active && $score->jn_limit < $limit;
        $raiseMt = $score->mt_active && $score->mt_limit < $limit;
        if (!$raiseJn && !$raiseMt) {
            return $score;
        }

        // getByStudentHemisId keshidagi obyekt boshqa talabalar bilan umumiy — nusxasini o'zgartiramiz
        $score = clone $score;
        if ($raiseJn) {
            $score->jn_limit = $limit;
        }
        if ($raiseMt) {
            $score->mt_limit = $limit;
        }

        return $score;
    }

    /**
     * Vedomost hisobidagi JN/MT o'tish chegarasi: barcha talabalar (PIMU ham) uchun 70.
     */
    public static function vedomostJnMtPass($studentHemisId): int
    {
        return self::ynJnMtLimit();
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

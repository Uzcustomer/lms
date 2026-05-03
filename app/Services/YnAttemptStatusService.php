<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN urinishlar bo'yicha talaba bosqichini hisoblovchi markaziy xizmat.
 *
 * Bosqichlar:
 *  - asosiy_passed         : 12-shakl da V≥60 bilan o'tgan
 *  - qoshimcha_passed      : 12-qo'shimcha (sababli) da V≥60 bilan o'tgan
 *  - in_12a                : V<60 va JN/MT to'g'ri — 12a OSKI/Test topshiradi
 *  - in_12a_pullik         : V<60 lekin JN<60 yoki MT<60 — 12a ga tushadi (ro'yxat uchun) lekin baholar o'zgarmaydi (pullik)
 *  - 12a_passed            : 12a da V≥60
 *  - 12a_qoshimcha_passed  : 12a-qo'shimcha (sababli) da V≥60
 *  - in_12b                : 12a dan ham yiqildi
 *  - in_12b_pullik         : JN/MT past, 12b ga ham ro'yxat uchun
 *  - 12b_passed            : 12b da V≥60
 *  - 12b_qoshimcha_passed  : 12b-qo'shimcha da V≥60
 *  - failed_pullik         : barcha urinishlar tugadi, V<60 — pullik qayta o'qish
 *
 * Eslatma: hisoblash exportYnQaydnoma'dagi V hisoblash mantiqi bilan ekvivalent
 * bo'lishi kerak. Bu yerda baho hisoblash o'rniga, exportYnQaydnoma o'zining
 * jb/mt/other gradeMap'larini berib, ularning V≥60 bo'lishini tekshiramiz.
 */
class YnAttemptStatusService
{
    public const STAGE_ASOSIY_PASSED         = 'asosiy_passed';
    public const STAGE_QOSHIMCHA_PASSED      = 'qoshimcha_passed';
    public const STAGE_IN_12A                = 'in_12a';
    public const STAGE_IN_12A_PULLIK         = 'in_12a_pullik';
    public const STAGE_12A_PASSED            = '12a_passed';
    public const STAGE_12A_QOSHIMCHA_PASSED  = '12a_qoshimcha_passed';
    public const STAGE_IN_12B                = 'in_12b';
    public const STAGE_IN_12B_PULLIK         = 'in_12b_pullik';
    public const STAGE_12B_PASSED            = '12b_passed';
    public const STAGE_12B_QOSHIMCHA_PASSED  = '12b_qoshimcha_passed';
    public const STAGE_FAILED_PULLIK         = 'failed_pullik';

    public const FORM_12             = '12-shakl';
    public const FORM_12_QOSHIMCHA   = '12-qo\'shimcha';
    public const FORM_12A            = '12a-shakl';
    public const FORM_12A_QOSHIMCHA  = '12a-qo\'shimcha';
    public const FORM_12B            = '12b-shakl';
    public const FORM_12B_QOSHIMCHA  = '12b-qo\'shimcha';

    /**
     * Talabaning joriy bosqichini aniqlaydi.
     *
     * Kirish: har bir urinish uchun (V, jn, mt, oski, test) qiymatlari.
     * - $main:       asosiy urinish, sababli retakesiz
     * - $qoshimcha:  asosiy + sababli retake bilan
     * - $a:          12a (asosiy JN/MT + 12a OSKI/Test, sababli retakesiz)
     * - $aQoshimcha: 12a + sababli retake
     * - $b:          12b (asosiy JN/MT + 12b OSKI/Test)
     * - $bQoshimcha: 12b + sababli retake
     *
     * Har biri ['v' => int|string, 'jn' => int, 'mt' => int, 'oski' => int|null, 'test' => int|null]
     *
     * Qaytaradi: ['stage' => STAGE_*, 'reason' => string]
     */
    public static function determineStage(
        array $main,
        ?array $qoshimcha = null,
        ?array $a = null,
        ?array $aQoshimcha = null,
        ?array $b = null,
        ?array $bQoshimcha = null
    ): array {
        $passes = fn(?array $row) => $row !== null && self::isPassing($row);
        $jnMtOk = fn(?array $row) => $row !== null
            && (int)($row['jn'] ?? 0) >= 60
            && (int)($row['mt'] ?? 0) >= 60;

        // 1. Asosiy urinishda o'tgan?
        if ($passes($main)) {
            return ['stage' => self::STAGE_ASOSIY_PASSED, 'reason' => '12-shakl da V≥60'];
        }

        // 2. Sababli orqali asosiy urinishda o'tgan?
        if ($passes($qoshimcha)) {
            return ['stage' => self::STAGE_QOSHIMCHA_PASSED, 'reason' => '12-qo\'shimcha da V≥60'];
        }

        // JN/MT past bo'lsa va sababli orqali tuzatilmagan bo'lsa — pullik kategoriya.
        // Bunday talaba 12a/12b ga aktiv kira olmaydi, lekin ro'yxatda turadi.
        $latestJnMtSource = $qoshimcha ?? $main;
        $jnMtFailed = !$jnMtOk($latestJnMtSource);

        // 3. 12a urinishida o'tgan?
        if ($passes($a)) {
            if ($jnMtFailed) {
                // JN/MT past lekin 12a OSKI/Test bilan V≥60 — bu xodisa bo'lmaydi
                // chunki JN/MT past bo'lsa 12a OSKI/Test ham aslida o'zgarmasligi kerak.
                // Lekin himoya uchun.
                return ['stage' => self::STAGE_IN_12A_PULLIK, 'reason' => 'JN/MT past — pullik'];
            }
            return ['stage' => self::STAGE_12A_PASSED, 'reason' => '12a da V≥60'];
        }

        // 4. 12a-qo'shimcha (sababli) orqali o'tgan?
        if ($passes($aQoshimcha)) {
            return ['stage' => self::STAGE_12A_QOSHIMCHA_PASSED, 'reason' => '12a-qo\'shimcha da V≥60'];
        }

        // 5. 12b da o'tgan?
        if ($passes($b)) {
            if ($jnMtFailed) {
                return ['stage' => self::STAGE_IN_12B_PULLIK, 'reason' => 'JN/MT past — pullik'];
            }
            return ['stage' => self::STAGE_12B_PASSED, 'reason' => '12b da V≥60'];
        }

        // 6. 12b-qo'shimcha?
        if ($passes($bQoshimcha)) {
            return ['stage' => self::STAGE_12B_QOSHIMCHA_PASSED, 'reason' => '12b-qo\'shimcha da V≥60'];
        }

        // O'tmagan: qaysi bosqichda turibdi?
        if ($b !== null || $bQoshimcha !== null) {
            return [
                'stage' => $jnMtFailed ? self::STAGE_IN_12B_PULLIK : self::STAGE_IN_12B,
                'reason' => $jnMtFailed ? 'JN/MT past — pullik' : '12b ga tushgan',
            ];
        }
        if ($a !== null || $aQoshimcha !== null) {
            return [
                'stage' => $jnMtFailed ? self::STAGE_IN_12A_PULLIK : self::STAGE_IN_12A,
                'reason' => $jnMtFailed ? 'JN/MT past — pullik' : '12a ga tushgan',
            ];
        }

        // Hali hech qaysi keyingi urinishga o'tmagan — asosiy yiqilgan
        return [
            'stage' => $jnMtFailed ? self::STAGE_IN_12A_PULLIK : self::STAGE_IN_12A,
            'reason' => 'Asosiy urinishda V<60',
        ];
    }

    /**
     * V≥60 — o'tgan deb hisoblanadi. V mumkin bo'lgan qiymatlar:
     *  - musbat son ≥ 60: o'tgan
     *  - son < 60: yiqilgan
     *  - -1 (kelmadi), -2 (qo'yilmadi), -3 (davomat ≥25%), '': yiqilgan
     */
    public static function isPassing(array $row): bool
    {
        $v = $row['v'] ?? null;
        if ($v === null || $v === '') return false;
        if (!is_numeric($v)) return false;
        return ((float)$v) >= 60;
    }

    /**
     * Berilgan bosqich qaysi shakl ro'yxatlarida ko'rinadi:
     *  - stage'ga qarab talaba qaysi sheetlarda ko'rinishini qaytaradi
     */
    public static function formsForStage(string $stage): array
    {
        // 12-shakl: HAMMASI ko'rinadi (asosiy YN snapshoti)
        // 12-qo'shimcha: sababli orqali tuzatilganlar
        // 12a-shakl: asosiy/qo'shimchadan o'tmaganlar (yiqilgan + pullik kategoriya)
        // 12a-qo'shimcha: 12a da sababli orqali tuzatilganlar
        // 12b-shakl: 12a/12a-qo'shimchadan o'tmaganlar
        // 12b-qo'shimcha: 12b da sababli orqali tuzatilganlar
        switch ($stage) {
            case self::STAGE_ASOSIY_PASSED:
                return [self::FORM_12];

            case self::STAGE_QOSHIMCHA_PASSED:
                return [self::FORM_12, self::FORM_12_QOSHIMCHA];

            case self::STAGE_IN_12A:
            case self::STAGE_IN_12A_PULLIK:
                return [self::FORM_12, self::FORM_12A];

            case self::STAGE_12A_PASSED:
                return [self::FORM_12, self::FORM_12A];

            case self::STAGE_12A_QOSHIMCHA_PASSED:
                return [self::FORM_12, self::FORM_12A, self::FORM_12A_QOSHIMCHA];

            case self::STAGE_IN_12B:
            case self::STAGE_IN_12B_PULLIK:
                return [self::FORM_12, self::FORM_12A, self::FORM_12B];

            case self::STAGE_12B_PASSED:
                return [self::FORM_12, self::FORM_12A, self::FORM_12B];

            case self::STAGE_12B_QOSHIMCHA_PASSED:
                return [self::FORM_12, self::FORM_12A, self::FORM_12B, self::FORM_12B_QOSHIMCHA];

            case self::STAGE_FAILED_PULLIK:
                // Hamma urinishlar tugadi — hammasida ro'yxatda turadi
                return [self::FORM_12, self::FORM_12A, self::FORM_12B];
        }

        return [self::FORM_12];
    }

    /**
     * Qaysi formlar uchun "qo'shimcha" sheet hosil qilish kerakligini aniqlaydi
     * — guruh bo'yicha umumiy: bironta talabada sababli retake mavjud bo'lsa.
     *
     * @param array<int,array> $stages Har talaba uchun ['stage' => ...]
     * @return array<string,bool> ['12-qo\'shimcha' => true, '12a-qo\'shimcha' => false, ...]
     */
    public static function activeFormsInGroup(array $stages): array
    {
        $forms = [
            self::FORM_12             => true, // 12-shakl har doim chiqadi
            self::FORM_12_QOSHIMCHA   => false,
            self::FORM_12A            => false,
            self::FORM_12A_QOSHIMCHA  => false,
            self::FORM_12B            => false,
            self::FORM_12B_QOSHIMCHA  => false,
        ];

        foreach ($stages as $st) {
            $stage = $st['stage'] ?? null;
            if (!$stage) continue;

            foreach (self::formsForStage($stage) as $f) {
                $forms[$f] = true;
            }
        }

        return $forms;
    }

    /**
     * Yaqinda dars/imtihonga kelmagan talabalar — yakuniy ogohlantirish uchun.
     * Sozlamalardagi spravka_deadline_days ichida absent_off bo'lgan yozuvlarni
     * topadi va menejerga ro'yxat sifatida qaytaradi.
     *
     * @return array<int,array{student_hemis_id:string, full_name:?string, lesson_date:string, lesson_pair_name:?string, training_type_name:?string, subject_name:?string}>
     */
    public static function findRecentAbsenteesForFinalizationWarning(
        string $subjectId,
        string $semesterCode,
        string $groupHemisId,
        int $windowDays
    ): array {
        $cutoff = now()->subDays(max($windowDays, 0))->startOfDay();

        $rows = DB::table('attendances as a')
            ->leftJoin('students as s', 's.hemis_id', '=', 'a.student_hemis_id')
            ->where('a.subject_id', $subjectId)
            ->where('a.semester_code', $semesterCode)
            ->where('a.group_id', $groupHemisId)
            ->where('a.lesson_date', '>=', $cutoff)
            ->where(function ($q) {
                $q->where('a.absent_on', '>', 0)
                  ->orWhere('a.absent_off', '>', 0);
            })
            ->orderBy('a.lesson_date', 'desc')
            ->select([
                'a.student_hemis_id',
                's.full_name',
                'a.lesson_date',
                'a.lesson_pair_name',
                'a.training_type_name',
                'a.subject_name',
            ])
            ->limit(200)
            ->get();

        return $rows->map(fn($r) => (array) $r)->all();
    }
}

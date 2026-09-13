<?php

namespace App\Support;

/**
 * Klinik fanlar — nomidagi kalit so'zlardan aniqlash (qo'lda belgilanmagan bo'lsa).
 *
 * Ikki joyda ishlatiladi: amaliy guruh o'lchamining sukut qiymati (~10 kishi) va
 * dars jadvalidagi "umumiy karta" — klinik fanning ma'ruzasi va amaliyoti bitta
 * kartada yaratiladi (o'quv bo'limi katta jadvalni tuzadi, kafedra ichkarida
 * ma'ruza/amaliyga ajratadi). Qo'lda belgilash: subject_kafedra_overrides.is_clinical.
 */
final class ClinicalSubjects
{
    public const KEYWORDS = [
        'klinik', 'kasallik', 'terapiya', 'xirurgiya', 'jarrohlik', 'pediatriya', 'akusher',
        'ginekolog', 'nevrolog', 'kardiolog', 'onkolog', 'urolog', 'endokrin', 'dermato',
        'psixiatr', 'stomatolog', 'ftiziatr', 'reanimatsiya', 'anesteziolog', 'yuqumli',
    ];

    /** $normalized — normSubject() bilan kichik harfga keltirilgan, belgilarsiz nom. */
    public static function matches(string $normalized): bool
    {
        foreach (self::KEYWORDS as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }

        return false;
    }
}

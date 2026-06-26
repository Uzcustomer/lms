<?php

namespace App\Services\Retake;

use App\Models\RetakeWindowSession;

/**
 * Qayta o'qish sessiyasi kodi — o'quv yili + fasl bo'yicha yagona aniqlovchi.
 *
 * Kanonik kod formati:  {YYYY}-{YYYY}-{fasl}     masalan  2025-2026-yozgi
 * Moodle quiz nomidagi suffiks:  Qayta-o'qish-{kod}
 *   masalan  YN test (rus)_Umumiy xirurgiya_6-sem_DAV-2_D_Qayta-o'qish-2025-2026-yozgi
 *
 * Bu kod ikki joyda bir xil bo'lishi shart:
 *   1) Moodle quiz nomi oxiridagi token (diagnostikaga `attempt_name`/`shakl` bo'lib keladi)
 *   2) LMS dagi `retake_window_sessions` (qayta o'qish to'lqini)
 * Shunda qishki sessiya natijasi yozgi jurnalga oqib o'tmaydi.
 */
class RetakeSessionCode
{
    /** Yagona ruxsat etilgan fasllar. */
    public const FASLLAR = ['kuzgi', 'qishki', 'yozgi'];

    /** Quiz nomidagi qayta o'qish marker matni (kanonik ko'rinish). */
    public const MARKER = "Qayta-o'qish";

    /**
     * Istalgan erkin matnni (sessiya nomi yoki quiz tokeni) kanonik
     * `YYYY-YYYY-fasl` kodiga keltiradi. Topa olmasa — null.
     *
     * Yil oralig'i `-` yoki `/` bilan ajratilgan bo'lishi mumkin, fasl
     * yildan oldin yoki keyin kelishi mumkin — har ikkala tartib qo'llanadi.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return null;
        }

        $fasl = null;
        foreach (self::FASLLAR as $f) {
            if (mb_strpos($s, $f) !== false) {
                $fasl = $f;
                break;
            }
        }
        if ($fasl === null) {
            return null;
        }

        if (!preg_match('/(\d{4})\s*[-\/]\s*(\d{4})/u', $s, $m)) {
            return null;
        }

        return $m[1] . '-' . $m[2] . '-' . $fasl;
    }

    /**
     * Sessiyaning kanonik kodi — avval `code` ustuni, bo'lmasa `name` dan.
     */
    public static function fromSession(?RetakeWindowSession $session): ?string
    {
        if ($session === null) {
            return null;
        }
        return self::normalize($session->code) ?? self::normalize($session->name);
    }

    /**
     * Moodle quiz natijasidan (attempt_name yoki shakl) sessiya kodini ajratadi.
     * Faqat qayta o'qish quizlari mos keladi (yil oralig'i + fasl bo'lsa).
     */
    public static function fromQuizName(?string $attemptName, ?string $shakl = null): ?string
    {
        return self::normalize($shakl) ?? self::normalize($attemptName);
    }

    /**
     * Quiz nomi oxiriga qo'yiladigan suffiks: "Qayta-o'qish-2025-2026-yozgi".
     */
    public static function quizSuffix(string $code): string
    {
        return self::MARKER . '-' . $code;
    }

    /**
     * Matnda qayta o'qish markeri ("Qayta-o'qish") bormi — yil/fasl tokeni
     * bo'lmasa ham (eski quizlar). Apostrof variantlariga bardoshli.
     */
    public static function hasRetakeMarker(?string $s): bool
    {
        if ($s === null || $s === '') {
            return false;
        }
        return (bool) preg_match("/qayta[-_\\s]*o['’‘ʻʼ`´]?\\s*qish/iu", $s);
    }

    /**
     * Berilgan quiz natijasi qayta o'qishga tegishlimi? Sessiya tokeni
     * (yil+fasl) bo'lmasa ham, "Qayta-o'qish" markeri bo'lsa — true.
     */
    public static function isRetakeQuiz(?string $attemptName, ?string $shakl = null): bool
    {
        return self::fromQuizName($attemptName, $shakl) !== null
            || self::hasRetakeMarker($shakl)
            || self::hasRetakeMarker($attemptName);
    }
}

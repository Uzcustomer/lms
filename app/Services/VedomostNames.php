<?php

namespace App\Services;

/**
 * Ism va fan nomlarini solishtirish yordamchilari (Python "vedomost_avto"
 * dasturidagi uz/namekey/fankey/name_sim/same_name/fan_score bilan bir xil g'oya).
 */
class VedomostNames
{
    private const CYR = [
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'J',
        'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
        'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'X', 'Ц' => 'TS',
        'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => "'", 'Ы' => 'I', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU',
        'Я' => 'YA', 'Ғ' => "G'", 'Қ' => 'Q', 'Ҳ' => 'H', 'Ў' => "O'", 'І' => 'I',
    ];

    /** Bo'shliq, apostrof va tirnoq turlarini bir xillashtiradi. */
    public static function uz($s): string
    {
        $s = str_replace("\u{00A0}", ' ', (string) $s);
        $s = preg_replace("/[`‘’ʻʼʹ´′]/u", "'", $s);
        $s = preg_replace('/[“”]/u', '"', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    public static function latin($s): string
    {
        $s = mb_strtoupper(self::uz($s));

        return strtr($s, self::CYR);
    }

    /** Ism kaliti: katta harf, faqat A-Z, apostrof va bo'shliq. */
    public static function nameKey($s): string
    {
        $s = preg_replace("/[^A-Z' ]/", '', self::latin($s));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** O'G'LI/QIZI va apostrof hisobga olinmaydigan kalit. */
    private static function simKey($s): string
    {
        $parts = array_filter(explode(' ', str_replace("'", '', self::nameKey($s))), fn ($t) => $t !== ''
            && !preg_match('/^(OGLI|UGLI|OGL|QIZI|KIZI|QIZ)$/', $t));

        return implode(' ', $parts);
    }

    /** F.I.Sh o'xshashligi 0..1. */
    public static function similarity($a, $b): float
    {
        $x = self::simKey($a);
        $y = self::simKey($b);
        if ($x === '' || $y === '') {
            return 0.0;
        }
        if ($x === $y) {
            return 1.0;
        }

        return 1 - levenshtein($x, $y) / max(strlen($x), strlen($y));
    }

    /**
     * O'qituvchi ismlari: familiya + bosh harflar ("Sh.T. Maxmaraimov" = "MAHMARAIMOV SH. T.").
     */
    public static function sameName($a, $b): bool
    {
        [$x, $ia] = self::nameParts($a);
        [$y, $ib] = self::nameParts($b);
        if ($x === '' || $y === '') {
            return false;
        }
        similar_text($x, $y, $pct);
        $close = $x === $y || str_starts_with($x, $y) || str_starts_with($y, $x) || $pct >= 86;
        if (!$close) {
            return false;
        }

        return !($ia && $ib && $ia[0] !== $ib[0]);
    }

    /** @return array{0: string, 1: array} [familiya, bosh harflar] */
    private static function nameParts($s): array
    {
        $t = str_replace("'", '', self::nameKey(preg_replace('/[.,]/', ' ', (string) $s)));
        $ws = array_values(array_filter(explode(' ', $t), fn ($w) => $w !== ''));
        if (!$ws) {
            return ['', []];
        }
        $big = array_values(array_filter($ws, fn ($w) => strlen($w) > 2)) ?: $ws;
        // "Familiya Ism Sharif" — familiya birinchi; "A.X Berdiyev" — yagona uzun so'z.
        $sur = $big[0];
        $ini = [];
        foreach ($ws as $w) {
            if ($w !== $sur) {
                $ini[] = $w[0];
            }
        }

        return [strtr($sur, ['X' => 'H', 'Q' => 'K', 'W' => 'V', 'Y' => 'I', 'J' => 'I']), $ini];
    }

    /** Fan nomi kaliti: kichik harf, o'/g' → o/g, faqat a-z0-9. */
    public static function subjectKey($s): string
    {
        $s = mb_strtolower(self::latin($s));
        $s = preg_replace('/[.,]/', ' ', $s);
        $s = str_replace(["o'", "g'"], ['o', 'g'], $s);
        $s = preg_replace('/[^a-z0-9 ]/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Fan nomlari o'xshashligi 0..1. */
    public static function subjectScore($a, $b): float
    {
        $x = self::subjectKey($a);
        $y = self::subjectKey($b);
        if ($x === '' || $y === '') {
            return 0.0;
        }
        if ($x === $y) {
            return 1.0;
        }
        $r = fn ($u, $v) => 1 - levenshtein($u, $v) / max(strlen($u), strlen($v));
        $best = $r($x, $y);
        if (str_contains($y, $x) || str_contains($x, $y)) {
            $best = max($best, 0.95);
        }

        return $best;
    }
}

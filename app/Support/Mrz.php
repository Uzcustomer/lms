<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * ICAO 9303 mashinada o'qiladigan zona (MRZ) — hujjat kartochkalarida
 * ko'rsatish uchun. Hisob standart bo'yicha (7-3-1 nazorat raqami),
 * lekin bu faqat ko'rinish: haqiqiy hujjat o'rnini bosmaydi.
 */
class Mrz
{
    /** Pasport (TD3): 2 qator × 44 */
    public static function passport(string $surname, string $given, ?string $country, ?string $number,
        ?CarbonInterface $birth, ?string $sex, ?CarbonInterface $expiry): array
    {
        $country = self::country($country);
        $num = self::field($number, 9);
        $line1 = self::pad('P<' . $country . self::names($surname, $given), 44);
        $body = $num . self::check($num) . $country . self::date($birth) . self::check(self::date($birth))
            . self::sex($sex) . self::date($expiry) . self::check(self::date($expiry));
        $personal = str_repeat('<', 14);
        $line2 = $body . $personal . self::check($personal);
        $composite = substr($line2, 0, 10) . substr($line2, 13, 7) . substr($line2, 21, 22);

        return [$line1, $line2 . self::check($composite)];
    }

    /** Viza (MRV-A): 2 qator × 44, beruvchi davlat — O'zbekiston */
    public static function visa(string $surname, string $given, ?string $nationality, ?string $passport,
        ?CarbonInterface $birth, ?string $sex, ?CarbonInterface $expiry, string $optional = ''): array
    {
        $num = self::field($passport, 9);
        $line1 = self::pad('V<UZB' . self::names($surname, $given), 44);
        // Ixtiyoriy qism: O'zbekiston vizasida viza turi va safarlar soni ("A1<3")
        $line2 = $num . self::check($num) . self::country($nationality) . self::date($birth) . self::check(self::date($birth))
            . self::sex($sex) . self::date($expiry) . self::check(self::date($expiry)) . self::clean($optional);

        return [$line1, self::pad($line2, 44)];
    }

    /** ID-karta (TD1): 3 qator × 30, beruvchi davlat — O'zbekiston */
    public static function idCard(string $surname, string $given, ?string $nationality, ?string $number, ?string $personal,
        ?CarbonInterface $birth, ?string $sex, ?CarbonInterface $expiry): array
    {
        $num = self::field($number, 9);
        $line1 = self::pad('I<UZB' . $num . self::check($num) . self::field($personal, 15), 30);
        $line2 = self::date($birth) . self::check(self::date($birth)) . self::sex($sex) . self::date($expiry)
            . self::check(self::date($expiry)) . self::country($nationality) . str_repeat('<', 11);
        $composite = substr($line1, 5, 25) . substr($line2, 0, 7) . substr($line2, 8, 7) . substr($line2, 18, 11);
        $line2 .= self::check($composite);
        $line3 = self::pad(self::names($surname, $given), 30);

        return [$line1, $line2, $line3];
    }

    /** "XOLBOYEV SALMONBEK BOBOMURODOVICH" => [familiya, ism, otasining ismi] */
    public static function splitName(?string $fullName): array
    {
        $parts = preg_split('/\s+/', trim((string) $fullName)) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? '', implode(' ', array_slice($parts, 2))];
    }

    public static function check(string $value): string
    {
        $weights = [7, 3, 1];
        $sum = 0;
        foreach (str_split($value) as $i => $ch) {
            $v = ctype_digit($ch) ? (int) $ch : (ctype_upper($ch) ? ord($ch) - 55 : 0);
            $sum += $v * $weights[$i % 3];
        }

        return (string) ($sum % 10);
    }

    private static function names(string $surname, string $given): string
    {
        return self::clean($surname) . '<<' . self::clean($given);
    }

    /** Lotin harflari va raqamlardan boshqasi '<' ga aylanadi */
    private static function clean(?string $s): string
    {
        $s = strtr((string) $s, ['ʻ' => '', 'ʼ' => '', "'" => '', '‘' => '', '’' => '', '`' => '']);
        $s = mb_strtoupper(\Illuminate\Support\Str::ascii($s));

        return trim(preg_replace('/[^A-Z0-9]+/', '<', $s) ?? '', '<');
    }

    private static function field(?string $s, int $len): string
    {
        return self::pad(substr(self::clean($s), 0, $len), $len);
    }

    private static function country(?string $iso3): string
    {
        return self::pad($iso3 ?: '', 3);
    }

    private static function date(?CarbonInterface $d): string
    {
        return $d ? $d->format('ymd') : '<<<<<<';
    }

    private static function sex(?string $sex): string
    {
        $s = mb_strtolower((string) $sex);

        return match (true) {
            str_starts_with($s, 'erk'), str_starts_with($s, 'm') => 'M',
            str_starts_with($s, 'ayol'), str_starts_with($s, 'f'), str_starts_with($s, 'w') => 'F',
            default => '<',
        };
    }

    private static function pad(string $s, int $len): string
    {
        return substr(str_pad($s, $len, '<'), 0, $len);
    }
}

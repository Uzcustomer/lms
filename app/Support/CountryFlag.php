<?php

namespace App\Support;

/**
 * HEMIS davlat nomi (o'zbekcha, ba'zan inglizcha) yoki kodidan bayroq rasmi.
 * Bayroqlar: public/images/flags/{iso}.svg (flag-icons, MIT).
 */
class CountryFlag
{
    /** Normallashtirilgan nom => ISO 3166-1 alpha-2 */
    private const NAMES = [
        // O'zbekcha (HEMIS)
        'afgoniston' => 'af', 'hindiston' => 'in', 'pokiston' => 'pk', 'bangladesh' => 'bd',
        'nepal' => 'np', 'shrilanka' => 'lk', 'butan' => 'bt', 'maldiv' => 'mv', 'maldivorollari' => 'mv',
        'tojikiston' => 'tj', 'qozogiston' => 'kz', 'qirgiziston' => 'kg', 'turkmaniston' => 'tm',
        'ozbekiston' => 'uz', 'rossiya' => 'ru', 'xitoy' => 'cn', 'turkiya' => 'tr', 'eron' => 'ir',
        'iroq' => 'iq', 'ozarbayjon' => 'az', 'armaniston' => 'am', 'gruziya' => 'ge', 'gurjiston' => 'ge',
        'ukraina' => 'ua', 'belarus' => 'by', 'belorussiya' => 'by', 'moldova' => 'md', 'misr' => 'eg',
        'nigeriya' => 'ng', 'gana' => 'gh', 'keniya' => 'ke', 'iordaniya' => 'jo', 'suriya' => 'sy',
        'livan' => 'lb', 'saudiyaarabistoni' => 'sa', 'birlashganarabamirliklari' => 'ae', 'baa' => 'ae',
        'yaman' => 'ye', 'malayziya' => 'my', 'indoneziya' => 'id', 'koreya' => 'kr', 'janubiykoreya' => 'kr',
        'yaponiya' => 'jp', 'mongoliya' => 'mn', 'aqsh' => 'us', 'amerikaqoshmashtatlari' => 'us',
        'buyukbritaniya' => 'gb', 'germaniya' => 'de', 'fransiya' => 'fr', 'italiya' => 'it',
        'ispaniya' => 'es', 'polsha' => 'pl', 'latviya' => 'lv', 'litva' => 'lt', 'estoniya' => 'ee',
        'sudan' => 'sd', 'somali' => 'so', 'efiopiya' => 'et', 'tanzaniya' => 'tz', 'uganda' => 'ug',
        'zambiya' => 'zm', 'zimbabve' => 'zw', 'kamerun' => 'cm', 'marokash' => 'ma', 'jazoir' => 'dz',
        'tunis' => 'tn', 'liviya' => 'ly', 'quvayt' => 'kw', 'qatar' => 'qa', 'ummon' => 'om',
        'bahrayn' => 'bh', 'falastin' => 'ps', 'isroil' => 'il', 'tailand' => 'th', 'vyetnam' => 'vn',
        'filippin' => 'ph', 'myanma' => 'mm', 'kambodja' => 'kh', 'laos' => 'la', 'kanada' => 'ca',
        'braziliya' => 'br',
        // Inglizcha (formadagi tug'ilgan davlat ro'yxati)
        'afghanistan' => 'af', 'india' => 'in', 'pakistan' => 'pk', 'srilanka' => 'lk', 'bhutan' => 'bt',
        'maldives' => 'mv', 'tajikistan' => 'tj', 'kazakhstan' => 'kz', 'kyrgyzstan' => 'kg',
        'turkmenistan' => 'tm', 'uzbekistan' => 'uz', 'russia' => 'ru', 'china' => 'cn', 'turkey' => 'tr',
        'iran' => 'ir', 'iraq' => 'iq', 'azerbaijan' => 'az', 'armenia' => 'am', 'georgia' => 'ge',
        'ukraine' => 'ua', 'egypt' => 'eg', 'nigeria' => 'ng', 'ghana' => 'gh', 'kenya' => 'ke',
        'jordan' => 'jo', 'syria' => 'sy', 'lebanon' => 'lb', 'saudiarabia' => 'sa', 'uae' => 'ae',
        'yemen' => 'ye', 'malaysia' => 'my', 'indonesia' => 'id', 'korea' => 'kr', 'japan' => 'jp',
        'mongolia' => 'mn', 'unitedstates' => 'us', 'unitedkingdom' => 'gb', 'germany' => 'de',
        'france' => 'fr', 'italy' => 'it', 'spain' => 'es', 'poland' => 'pl', 'latvia' => 'lv',
        'lithuania' => 'lt', 'estonia' => 'ee', 'somalia' => 'so', 'ethiopia' => 'et', 'tanzania' => 'tz',
        'zambia' => 'zm', 'cameroon' => 'cm', 'morocco' => 'ma', 'algeria' => 'dz', 'tunisia' => 'tn',
        'libya' => 'ly', 'kuwait' => 'kw', 'oman' => 'om', 'bahrain' => 'bh', 'palestine' => 'ps',
        'israel' => 'il', 'thailand' => 'th', 'vietnam' => 'vn', 'philippines' => 'ph', 'myanmar' => 'mm',
        'cambodia' => 'kh', 'canada' => 'ca', 'brazil' => 'br',
    ];

    /** ISO alpha-2 => alpha-3 (MRZ uchun) */
    private const ISO3 = [
        'af' => 'AFG', 'in' => 'IND', 'pk' => 'PAK', 'bd' => 'BGD', 'np' => 'NPL', 'lk' => 'LKA', 'bt' => 'BTN',
        'mv' => 'MDV', 'tj' => 'TJK', 'kz' => 'KAZ', 'kg' => 'KGZ', 'tm' => 'TKM', 'uz' => 'UZB', 'ru' => 'RUS',
        'cn' => 'CHN', 'tr' => 'TUR', 'ir' => 'IRN', 'iq' => 'IRQ', 'az' => 'AZE', 'am' => 'ARM', 'ge' => 'GEO',
        'ua' => 'UKR', 'by' => 'BLR', 'md' => 'MDA', 'eg' => 'EGY', 'ng' => 'NGA', 'gh' => 'GHA', 'ke' => 'KEN',
        'jo' => 'JOR', 'sy' => 'SYR', 'lb' => 'LBN', 'sa' => 'SAU', 'ae' => 'ARE', 'ye' => 'YEM', 'my' => 'MYS',
        'id' => 'IDN', 'kr' => 'KOR', 'jp' => 'JPN', 'mn' => 'MNG', 'us' => 'USA', 'gb' => 'GBR', 'de' => 'DEU',
        'fr' => 'FRA', 'it' => 'ITA', 'es' => 'ESP', 'pl' => 'POL', 'lv' => 'LVA', 'lt' => 'LTU', 'ee' => 'EST',
        'sd' => 'SDN', 'so' => 'SOM', 'et' => 'ETH', 'tz' => 'TZA', 'ug' => 'UGA', 'zm' => 'ZMB', 'zw' => 'ZWE',
        'cm' => 'CMR', 'ma' => 'MAR', 'dz' => 'DZA', 'tn' => 'TUN', 'ly' => 'LBY', 'kw' => 'KWT', 'qa' => 'QAT',
        'om' => 'OMN', 'bh' => 'BHR', 'ps' => 'PSE', 'il' => 'ISR', 'th' => 'THA', 'vn' => 'VNM', 'ph' => 'PHL',
        'mm' => 'MMR', 'kh' => 'KHM', 'la' => 'LAO', 'ca' => 'CAN', 'br' => 'BRA',
    ];

    public static function iso3(?string $name, ?string $code = null): ?string
    {
        $iso = self::code($name, $code);

        return $iso ? (self::ISO3[$iso] ?? null) : null;
    }

    public static function code(?string $name, ?string $code = null): ?string
    {
        $code = strtolower(trim((string) $code));
        if (preg_match('/^[a-z]{2}$/', $code) && self::exists($code)) {
            return $code;
        }

        $key = self::normalize((string) $name);
        if ($key === '') {
            return null;
        }
        $iso = self::NAMES[$key] ?? null;

        return $iso && self::exists($iso) ? $iso : null;
    }

    public static function url(?string $name, ?string $code = null): ?string
    {
        $iso = self::code($name, $code);

        return $iso ? asset("images/flags/{$iso}.svg") : null;
    }

    /** "Afg'oniston Islom Respublikasi" -> "afgoniston" */
    private static function normalize(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = str_replace(["'", '`', 'ʻ', 'ʼ', '‘', '’', '-'], '', $s);
        $s = preg_replace('/\b(respublikasi|republic of|federatsiyasi|islom|davlati|the)\b/u', '', $s);

        return preg_replace('/[^a-z]/', '', $s) ?? '';
    }

    private static function exists(string $iso): bool
    {
        return is_file(public_path("images/flags/{$iso}.svg"));
    }
}

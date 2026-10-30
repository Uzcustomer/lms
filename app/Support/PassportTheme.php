<?php

namespace App\Support;

/**
 * Talaba pasporti kartochkasining davlatga xos ko'rinishi: muqova rangi,
 * ma'lumotlar sahifasi foni, himoya to'ri ranglari va davlat nomi o'z tilida.
 * Ranglar haqiqiy pasportlarga yaqin tanlangan; gerb va rasmiy belgilar yo'q.
 */
class PassportTheme
{
    private const THEMES = [
        'tj' => [
            'native' => 'ҶУМҲУРИИ ТОҶИКИСТОН', 'en' => 'REPUBLIC OF TAJIKISTAN',
            'word' => 'ШИНОСНОМА', 'cover' => '#7a1426', 'ink' => '#7a1426',
            'paper' => ['#fbf3ee', '#f5e8e4', '#eef1e8'],
            'g1' => 'rgba(185, 28, 28, .08)', 'g2' => 'rgba(4, 120, 87, .09)',
        ],
        'ru' => [
            'native' => 'РОССИЙСКАЯ ФЕДЕРАЦИЯ', 'en' => 'RUSSIAN FEDERATION',
            'word' => 'ПАСПОРТ', 'cover' => '#6b1a2a', 'ink' => '#6b1a2a',
            'paper' => ['#fbf2f3', '#f3e6ec', '#ece9f4'],
            'g1' => 'rgba(29, 78, 216, .07)', 'g2' => 'rgba(190, 18, 60, .08)',
        ],
        'kz' => [
            'native' => 'ҚАЗАҚСТАН РЕСПУБЛИКАСЫ', 'en' => 'REPUBLIC OF KAZAKHSTAN',
            'word' => 'ПАСПОРТ', 'cover' => '#1d5fa8', 'ink' => '#0b4f8a',
            'paper' => ['#eef7fc', '#f1f6ee', '#f8f3dc'],
            'g1' => 'rgba(2, 132, 199, .09)', 'g2' => 'rgba(202, 138, 4, .09)',
        ],
        'in' => [
            'native' => 'भारत गणराज्य', 'en' => 'REPUBLIC OF INDIA',
            'word' => 'पासपोर्ट', 'cover' => '#1b2a5c', 'ink' => '#1b2a5c',
            'paper' => ['#f7f1f6', '#f1eff8', '#eef4f1'],
            'g1' => 'rgba(234, 88, 12, .08)', 'g2' => 'rgba(22, 101, 52, .07)',
        ],
        'af' => [
            'native' => 'افغانستان', 'en' => 'AFGHANISTAN', 'rtl' => true,
            'word' => 'پاسپورت', 'cover' => '#1f2a44', 'ink' => '#1f2a44',
            'paper' => ['#f1f5f1', '#ecf1f2', '#f3efe9'],
            'g1' => 'rgba(15, 23, 42, .07)', 'g2' => 'rgba(21, 128, 61, .09)',
        ],
        'tr' => [
            'native' => 'TÜRKİYE CUMHURİYETİ', 'en' => 'REPUBLIC OF TÜRKİYE',
            'word' => 'PASAPORT', 'cover' => '#7f1d2d', 'ink' => '#8b1e2d',
            'paper' => ['#fcf4f1', '#f6ebe8', '#f1ecef'],
            'g1' => 'rgba(220, 38, 38, .08)', 'g2' => 'rgba(220, 38, 38, .05)',
        ],
        'uz' => [
            'native' => "O'ZBEKISTON RESPUBLIKASI", 'en' => 'REPUBLIC OF UZBEKISTAN',
            'word' => 'PASPORT', 'cover' => '#13315c', 'ink' => '#12456b',
            'paper' => ['#eef6f7', '#eef4ec', '#f1f2f8'],
            'g1' => 'rgba(14, 116, 144, .08)', 'g2' => 'rgba(22, 163, 74, .08)',
        ],
    ];

    private const DEFAULT = [
        'native' => null, 'en' => null, 'word' => 'PASSPORT', 'cover' => '#7f1d1d', 'ink' => '#7f1d1d',
        'paper' => ['#fbf7ef', '#f6efe3', '#f3ece4'],
        'g1' => 'rgba(190, 18, 60, .07)', 'g2' => 'rgba(13, 148, 136, .08)',
    ];

    /** @return array{native: ?string, en: ?string, word: string, cover: string, ink: string, paper: string[], g1: string, g2: string, rtl?: bool} */
    public static function for(?string $iso): array
    {
        return self::THEMES[strtolower((string) $iso)] ?? self::DEFAULT;
    }
}

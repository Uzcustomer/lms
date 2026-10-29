<?php

namespace App\Services;

use App\Models\MarkingSystemScore;
use App\Models\Setting;

/**
 * Vedomost AI tekshiruvi sozlamalari (AI sozlamalari sahifasidan boshqariladi).
 *
 * Bitta JSON sifatida settings jadvalida saqlanadi. Kalitlar bu yerda EMAS —
 * ular vedomost_ai_keys jadvalida shifrlangan holda turadi.
 */
class VedomostAiSettings
{
    private const SETTING_KEY = 'vedomost_ai_settings';

    /** Maydon => [standart qiymat, turi]. */
    private const FIELDS = [
        'model'          => ['gemini-2.5-flash', 'string'],
        'fallback_model' => ['gemini-2.5-flash-lite', 'string?'],
        'timeout'        => [120, 'int'],
        'max_retries'    => [2, 'int'],
        'thinking_budget' => [null, 'int?'],
        'bands'          => [1, 'int'],
        'auto_check'     => [false, 'bool'],
        'allow_free_keys' => [true, 'bool'],
        'daily_limit'    => [null, 'int?'],
        'price_input_per_m'  => [null, 'float?'],
        'price_output_per_m' => [null, 'float?'],
        // Tekshiruv qoidalari
        'jn_mt_pass'               => [MarkingSystemScore::YN_JN_MT_LIMIT, 'int'],
        'jn_mt_pass_international' => [MarkingSystemScore::INTERNATIONAL_VEDOMOST_PASS, 'int'],
        'tolerance'                => [0.05, 'float'],
    ];

    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = json_decode((string) Setting::get(self::SETTING_KEY, '{}'), true);
        $stored = is_array($stored) ? $stored : [];

        $out = [];
        foreach (self::FIELDS as $name => [$default, $type]) {
            $out[$name] = array_key_exists($name, $stored)
                ? $this->cast($stored[$name], $type, $default)
                : $default;
        }

        return $this->cache = $out;
    }

    public function get(string $name)
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * Faqat ma'lum maydonlarni saqlaydi; noma'lumlari e'tiborsiz qoldiriladi.
     */
    public function save(array $values): void
    {
        $current = $this->all();
        foreach (self::FIELDS as $name => [$default, $type]) {
            if (array_key_exists($name, $values)) {
                $current[$name] = $this->cast($values[$name], $type, $default);
            }
        }

        Setting::set(self::SETTING_KEY, json_encode($current, JSON_UNESCAPED_UNICODE));
        $this->cache = $current;
    }

    public static function defaults(): array
    {
        return array_map(fn ($f) => $f[0], self::FIELDS);
    }

    private function cast($value, string $type, $default)
    {
        $nullable = str_ends_with($type, '?');
        if ($nullable && ($value === null || $value === '')) {
            return null;
        }

        return match (rtrim($type, '?')) {
            'int' => is_numeric($value) ? (int) $value : $default,
            'float' => is_numeric($value) ? (float) $value : $default,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => trim((string) $value) !== '' ? trim((string) $value) : $default,
        };
    }
}

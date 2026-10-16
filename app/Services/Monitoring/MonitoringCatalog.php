<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringDetector;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * docs/detectors.yaml katalogini monitoring_detectors jadvaliga yozadi (TZ 3-bo'lim).
 *
 * Yangi detektor — yaml'dagi hamma qiymat bilan yaratiladi. Mavjud detektorda
 * faqat matnli maydonlar yangilanadi; paneldan o'zgartirilgan `params`, `enabled`,
 * `warning_mode`, `due_workdays` va standart mas'ul saqlanadi. Yaml'ga yangi parametr
 * kaliti qo'shilsa, u mavjud qiymatlarga tegmasdan qo'shiladi. $resetParams — hammasini
 * yaml'dagi holatga qaytaradi.
 */
class MonitoringCatalog
{
    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function sync(?string $path = null, bool $resetParams = false): array
    {
        $path ??= config('monitoring.catalog_path');
        if (!is_file($path)) {
            throw new RuntimeException("Detektorlar katalogi topilmadi: {$path}");
        }

        $entries = Yaml::parseFile($path)['detectors'] ?? [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($entries, $resetParams, &$created, &$updated) {
            foreach ($entries as $entry) {
                $code = trim((string) ($entry['code'] ?? ''));
                if ($code === '') {
                    continue;
                }

                $status = (string) ($entry['status'] ?? '');
                $text = [
                    'group_name' => (string) ($entry['group'] ?? 'BOSHQA'),
                    'title' => mb_substr((string) ($entry['title'] ?? self::titleFromRule((string) ($entry['rule'] ?? $code))), 0, 500),
                    'rule_text' => (string) ($entry['rule'] ?? ''),
                    'legal_basis' => $entry['legal_basis'] ?? null,
                    'detection_text' => $entry['detection'] ?? null,
                    'catalog_status' => $status ?: null,
                    'pilot' => (bool) ($entry['pilot'] ?? false),
                    'default_owner_role' => $entry['default_owner_role'] ?? null,
                ];
                $settings = [
                    'params' => (array) ($entry['params'] ?? []),
                    'enabled' => (bool) ($entry['enabled'] ?? false),
                    // TZ 5-bo'lim: ⚠ — normasi tekshirilishi kerak, faqat ogohlantirish rejimida.
                    // yaml'da warning_mode: true — xavfsiz ishga tushirish (staging yo'q): natija avval
                    // panelda tekshiriladi, mas'ullarga yuborish administrator o'chirgach boshlanadi.
                    'warning_mode' => (bool) ($entry['warning_mode'] ?? str_contains($status, '⚠')),
                    'due_workdays' => (int) ($entry['due_workdays'] ?? config('monitoring.default_due_workdays', 3)),
                    'severity' => (string) ($entry['severity'] ?? (str_contains($status, '🔴') ? 'yuqori' : 'orta')),
                ];

                $detector = MonitoringDetector::where('code', $code)->first();
                if ($detector === null) {
                    MonitoringDetector::create(array_merge(['code' => $code], $text, $settings));
                    $created++;

                    continue;
                }

                $detector->fill($text);
                if ($resetParams) {
                    $detector->fill($settings);
                } else {
                    // Yaml'dagi yangi kalitlar qo'shiladi, paneldagi qiymatlar ustun (tartib saqlanadi).
                    $params = (array) ($detector->params ?? []);
                    foreach ($settings['params'] as $key => $value) {
                        if (!array_key_exists($key, $params)) {
                            $params[$key] = $value;
                        }
                    }
                    $detector->params = $params;
                }
                if ($detector->isDirty()) {
                    $detector->save();
                    $updated++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated, 'total' => count($entries)];
    }

    /** Qoida matnining birinchi gapi (yaml'da `title` bo'lmasa). */
    public static function titleFromRule(string $rule): string
    {
        $first = rtrim(preg_split('/(?<=[.;])\s/u', trim($rule), 2)[0] ?? $rule, ' .;,');

        return mb_strlen($first) > 160 ? mb_substr($first, 0, 157) . '…' : $first;
    }
}

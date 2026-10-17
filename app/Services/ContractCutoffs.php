<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Kontrakt to'lov muddatlari (Sozlamalar).
 *
 *  - contract_cutoffs — umumiy jadval: [{deadline, percent}, ...]. YN oldi qaydnoma shuni ishlatadi (o'zgarmagan).
 *  - contract_cutoffs_by_course — kurs kesimidagi jadvallar: {"1": [...], ..., "6": [...], "super": [...],
 *    "super_types": [HEMIS kontrakt turi kodlari]}. Bo'sh kurs — umumiy jadval. Superkontrakt jadvali
 *    tanlangan kontrakt turlariga kursidan qat'iy nazar qo'llanadi. Monitoring (F-6) shuni o'qiydi.
 */
class ContractCutoffs
{
    public const COURSES = [1, 2, 3, 4, 5, 6];

    public const DEFAULT_GENERAL = [
        ['deadline' => '2025-10-01', 'percent' => 25],
        ['deadline' => '2026-01-01', 'percent' => 50],
        ['deadline' => '2026-03-01', 'percent' => 75],
        ['deadline' => '2026-05-01', 'percent' => 100],
    ];

    public static function general(): array
    {
        return self::normalize(json_decode((string) Setting::get('contract_cutoffs', json_encode(self::DEFAULT_GENERAL)), true));
    }

    /** @return array{courses: array<int, array>, super: array, super_types: string[]} */
    public static function byCourse(): array
    {
        $raw = json_decode((string) Setting::get('contract_cutoffs_by_course', '{}'), true) ?: [];
        $courses = [];
        foreach (self::COURSES as $course) {
            $courses[$course] = self::normalize($raw[(string) $course] ?? []);
        }

        return [
            'courses' => $courses,
            'super' => self::normalize($raw['super'] ?? []),
            'super_types' => array_values(array_map('strval', (array) ($raw['super_types'] ?? []))),
        ];
    }

    public static function saveByCourse(array $courses, array $super, array $superTypes): void
    {
        $data = [];
        foreach (self::COURSES as $course) {
            $data[(string) $course] = self::normalize($courses[$course] ?? []);
        }
        $data['super'] = self::normalize($super);
        $data['super_types'] = array_values(array_unique(array_filter(array_map('strval', $superTypes), fn ($v) => $v !== '')));

        Setting::set('contract_cutoffs_by_course', json_encode($data));
    }

    /**
     * Talabaga qo'llanadigan jadval: superkontrakt (turi tanlangan bo'lsa) → kurs jadvali → umumiy.
     *
     * @return array{label: string, cutoffs: array}
     */
    public static function scheduleFor(?int $course, ?string $contractTypeCode): array
    {
        $byCourse = self::byCourse();
        if ($contractTypeCode !== null && $byCourse['super'] !== [] && in_array($contractTypeCode, $byCourse['super_types'], true)) {
            return ['label' => 'Superkontrakt', 'cutoffs' => $byCourse['super']];
        }
        if ($course !== null && ($byCourse['courses'][$course] ?? []) !== []) {
            return ['label' => "{$course}-kurs", 'cutoffs' => $byCourse['courses'][$course]];
        }

        return ['label' => 'Umumiy jadval', 'cutoffs' => self::general()];
    }

    /**
     * Berilgan sanaga qadar o'tgan eng oxirgi bosqich: shu bosqich foizi to'langan bo'lishi kerak.
     * $from dan oldingi (eski o'quv yilidan qolgan) sanalar hisobga olinmaydi.
     *
     * @return array{deadline: string, percent: int}|null
     */
    public static function passedStage(array $cutoffs, Carbon $today, ?Carbon $from = null): ?array
    {
        $stage = null;
        foreach ($cutoffs as $cutoff) {
            $deadline = Carbon::parse($cutoff['deadline'])->endOfDay();
            if ($from !== null && $deadline->lt($from)) {
                continue;
            }
            if ($deadline->lt($today)) {
                $stage = $cutoff;
            }
        }

        return $stage;
    }

    private static function normalize($cutoffs): array
    {
        return collect(is_array($cutoffs) ? $cutoffs : [])
            ->filter(fn ($c) => is_array($c) && !empty($c['deadline']) && is_numeric($c['percent'] ?? null))
            ->map(fn ($c) => ['deadline' => Carbon::parse($c['deadline'])->toDateString(), 'percent' => max(1, min(100, (int) $c['percent']))])
            ->sortBy('deadline')
            ->values()
            ->all();
    }
}

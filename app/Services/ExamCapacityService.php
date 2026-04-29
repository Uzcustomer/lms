<?php

namespace App\Services;

use App\Models\ExamCapacityOverride;
use App\Models\ExamSchedule;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Test markazi sig'imi va vaqt-ust-ust tushish (overlap) tekshirish.
 *
 * Sozlamalar (Setting jadvalida `exam_capacity_settings` kalit ostida):
 *   - computer_count:        kompyuter soni
 *   - test_duration_minutes: bitta testning davomiyligi (daqiqa)
 *   - work_hours_start:      ish vaqti boshlanishi (HH:MM)
 *   - work_hours_end:        ish vaqti tugashi (HH:MM)
 */
class ExamCapacityService
{
    public const SETTING_KEY = 'exam_capacity_settings';

    public static function defaults(): array
    {
        return [
            'computer_count' => 60,
            'test_duration_minutes' => 15,
            'work_hours_start' => '09:00',
            'work_hours_end' => '17:00',
            'lunch_start' => '13:00',
            'lunch_end' => '14:00',
        ];
    }

    public static function getSettings(): array
    {
        $raw = Setting::get(self::SETTING_KEY);
        if (!$raw) {
            return self::defaults();
        }
        $decoded = is_array($raw) ? $raw : json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::defaults();
        }
        return array_merge(self::defaults(), $decoded);
    }

    /**
     * Berilgan kun uchun sozlamalar — agar shu kunga override bo'lsa, default ustiga
     * faqat to'ldirilgan maydonlarni qo'shadi.
     */
    public static function getSettingsForDate(?string $date): array
    {
        $settings = self::getSettings();
        if (!$date) {
            return $settings;
        }
        try {
            $normalizedDate = Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $settings;
        }

        $override = ExamCapacityOverride::where('date', $normalizedDate)->first();
        if (!$override) {
            return $settings;
        }

        // Faqat to'ldirilgan (null bo'lmagan) maydonlarni override qiladi
        $overrides = [
            'work_hours_start' => $override->work_hours_start ? substr($override->work_hours_start, 0, 5) : null,
            'work_hours_end' => $override->work_hours_end ? substr($override->work_hours_end, 0, 5) : null,
            'lunch_start' => $override->lunch_start ? substr($override->lunch_start, 0, 5) : null,
            'lunch_end' => $override->lunch_end ? substr($override->lunch_end, 0, 5) : null,
            'computer_count' => $override->computer_count,
            'test_duration_minutes' => $override->test_duration_minutes,
        ];

        foreach ($overrides as $key => $value) {
            if ($value !== null && $value !== '') {
                $settings[$key] = $value;
            }
        }

        // Agar tushlik faqat bittasi to'ldirilsa — tushlik o'chiriladi
        if (!empty($overrides['lunch_start']) xor !empty($overrides['lunch_end'])) {
            // Bittasini override qilgan bo'lsa, ikkalasini ham yangilash kerak
            // — bunda mantiqsiz holatdan qochish uchun originalni saqlaymiz
        }

        return $settings;
    }

    public static function setSettings(array $data): void
    {
        $defaults = self::defaults();
        $clean = [
            'computer_count' => max(1, (int) ($data['computer_count'] ?? $defaults['computer_count'])),
            'test_duration_minutes' => max(1, (int) ($data['test_duration_minutes'] ?? $defaults['test_duration_minutes'])),
            'work_hours_start' => self::normalizeTime($data['work_hours_start'] ?? $defaults['work_hours_start']),
            'work_hours_end' => self::normalizeTime($data['work_hours_end'] ?? $defaults['work_hours_end']),
            'lunch_start' => self::normalizeTimeOrNull($data['lunch_start'] ?? null),
            'lunch_end' => self::normalizeTimeOrNull($data['lunch_end'] ?? null),
        ];
        // Tushlik vaqti faqat ikkalasi to'g'ri bo'lsa va end > start bo'lsa hisobga olinadi
        if ($clean['lunch_start'] && $clean['lunch_end']) {
            $ls = Carbon::createFromFormat('H:i', $clean['lunch_start']);
            $le = Carbon::createFromFormat('H:i', $clean['lunch_end']);
            if ($le->lte($ls)) {
                $clean['lunch_start'] = null;
                $clean['lunch_end'] = null;
            }
        } else {
            $clean['lunch_start'] = null;
            $clean['lunch_end'] = null;
        }
        Setting::set(self::SETTING_KEY, json_encode($clean));
    }

    private static function normalizeTime(string $time): string
    {
        try {
            return Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('H:i');
        } catch (\Throwable $e) {
            return '09:00';
        }
    }

    private static function normalizeTimeOrNull($time): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }
        try {
            return Carbon::createFromFormat('H:i', substr((string) $time, 0, 5))->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Tushlik vaqti aktiv bo'lsa uning daqiqada davomiyligi.
     */
    public static function lunchMinutes(array $settings = null): int
    {
        $s = $settings ?? self::getSettings();
        if (empty($s['lunch_start']) || empty($s['lunch_end'])) {
            return 0;
        }
        $ls = Carbon::createFromFormat('H:i', $s['lunch_start']);
        $le = Carbon::createFromFormat('H:i', $s['lunch_end']);
        return max(0, $le->diffInMinutes($ls, false));
    }

    /**
     * Bitta kunning maksimal sig'imi (talaba-test soni).
     * = ((ish_vaqti - tushlik) / davomiylik) * kompyuter_soni
     */
    public static function dailyCapacity(?array $settings = null): int
    {
        $s = $settings ?? self::getSettings();
        $start = Carbon::createFromFormat('H:i', $s['work_hours_start']);
        $end = Carbon::createFromFormat('H:i', $s['work_hours_end']);
        $minutes = max(0, $end->diffInMinutes($start, false));
        $minutes -= self::lunchMinutes($s);
        if ($minutes <= 0) {
            return 0;
        }
        $slots = (int) floor($minutes / max(1, $s['test_duration_minutes']));
        return $slots * (int) $s['computer_count'];
    }

    public static function dailyCapacityForDate(?string $date): int
    {
        return self::dailyCapacity(self::getSettingsForDate($date));
    }

    /**
     * Berilgan sana+vaqt+davomiylik oralig'i tushlik vaqti bilan ustma-ust tushsa true.
     */
    public static function overlapsLunch(string $date, string $startTime, int $duration, array $settings = null): bool
    {
        $s = $settings ?? self::getSettings();
        if (empty($s['lunch_start']) || empty($s['lunch_end'])) {
            return false;
        }
        $start = Carbon::parse($date . ' ' . substr($startTime, 0, 5));
        $end = $start->copy()->addMinutes($duration);
        $lStart = Carbon::parse($date . ' ' . $s['lunch_start']);
        $lEnd = Carbon::parse($date . ' ' . $s['lunch_end']);
        return $start->lt($lEnd) && $end->gt($lStart);
    }

    /**
     * Berilgan guruhdagi talabalar soni (faol talabalar).
     */
    public static function groupStudentCount(string $groupHemisId): int
    {
        return (int) Student::where('group_id', $groupHemisId)
            ->where('student_status_code', 11)
            ->count();
    }

    /**
     * Berilgan kun uchun belgilangan barcha YN (OSKI/Test) lar bo'yicha guruhlardagi
     * talabalar yig'indisini hisoblash. $exclude — joriy yozuvni hisobdan chiqarib tashlash uchun
     * (group_hemis_id, subject_id, semester_code, yn_type) kombinatsiyasi.
     *
     * @param  string  $date          Y-m-d formatda
     * @param  string  $ynType        'oski' yoki 'test'
     * @param  array   $exclude       ['group_hemis_id'=>..., 'subject_id'=>..., 'semester_code'=>..., 'yn_type'=>...]
     */
    public static function totalStudentsOnDate(string $date, ?array $exclude = null): int
    {
        $query = ExamSchedule::query()
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereDate('oski_date', $date)->where('oski_na', false);
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('test_date', $date)->where('test_na', false);
                });
            })
            ->get();

        $groupCounts = self::collectGroupCounts($query->pluck('group_hemis_id')->unique()->all());

        $total = 0;
        foreach ($query as $row) {
            $oskiDateMatch = $row->oski_date && $row->oski_date->format('Y-m-d') === $date && !$row->oski_na;
            $testDateMatch = $row->test_date && $row->test_date->format('Y-m-d') === $date && !$row->test_na;

            if ($oskiDateMatch) {
                if (!self::isExcluded($row, 'oski', $exclude)) {
                    $total += $groupCounts[$row->group_hemis_id] ?? 0;
                }
            }
            if ($testDateMatch) {
                if (!self::isExcluded($row, 'test', $exclude)) {
                    $total += $groupCounts[$row->group_hemis_id] ?? 0;
                }
            }
        }

        return $total;
    }

    /**
     * Vaqt-ust-ust tushish: ($date, $time) da $duration daqiqa davomida boshqa
     * belgilangan YN lar bilan bir vaqtda ishlaydigan talabalar yig'indisi.
     */
    public static function concurrentStudentsForSlot(string $date, string $time, ?array $exclude = null): int
    {
        $duration = (int) self::getSettingsForDate($date)['test_duration_minutes'];
        $start = Carbon::parse($date . ' ' . substr($time, 0, 5));
        $end = $start->copy()->addMinutes($duration);

        $rows = ExamSchedule::query()
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereDate('oski_date', $date)
                       ->where('oski_na', false)
                       ->whereNotNull('oski_time');
                })->orWhere(function ($q2) use ($date) {
                    $q2->whereDate('test_date', $date)
                       ->where('test_na', false)
                       ->whereNotNull('test_time');
                });
            })
            ->get();

        $groupCounts = self::collectGroupCounts($rows->pluck('group_hemis_id')->unique()->all());

        $total = 0;
        foreach ($rows as $row) {
            // OSKI
            if ($row->oski_date && $row->oski_date->format('Y-m-d') === $date
                && !$row->oski_na && $row->oski_time
                && !self::isExcluded($row, 'oski', $exclude)) {
                $rowStart = Carbon::parse($date . ' ' . substr((string) $row->oski_time, 0, 5));
                $rowEnd = $rowStart->copy()->addMinutes($duration);
                if ($rowStart->lt($end) && $rowEnd->gt($start)) {
                    $total += $groupCounts[$row->group_hemis_id] ?? 0;
                }
            }
            // Test
            if ($row->test_date && $row->test_date->format('Y-m-d') === $date
                && !$row->test_na && $row->test_time
                && !self::isExcluded($row, 'test', $exclude)) {
                $rowStart = Carbon::parse($date . ' ' . substr((string) $row->test_time, 0, 5));
                $rowEnd = $rowStart->copy()->addMinutes($duration);
                if ($rowStart->lt($end) && $rowEnd->gt($start)) {
                    $total += $groupCounts[$row->group_hemis_id] ?? 0;
                }
            }
        }

        return $total;
    }

    private static function collectGroupCounts(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }
        return Student::whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
            ->selectRaw('group_id, COUNT(*) as cnt')
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id')
            ->toArray();
    }

    private static function isExcluded($row, string $ynType, ?array $exclude): bool
    {
        if (!$exclude) {
            return false;
        }
        return ($exclude['group_hemis_id'] ?? null) === $row->group_hemis_id
            && (string) ($exclude['subject_id'] ?? null) === (string) $row->subject_id
            && (string) ($exclude['semester_code'] ?? null) === (string) $row->semester_code
            && strtolower((string) ($exclude['yn_type'] ?? null)) === $ynType;
    }
}

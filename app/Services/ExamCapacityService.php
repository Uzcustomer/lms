<?php

namespace App\Services;

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

    public static function setSettings(array $data): void
    {
        $defaults = self::defaults();
        $clean = [
            'computer_count' => max(1, (int) ($data['computer_count'] ?? $defaults['computer_count'])),
            'test_duration_minutes' => max(1, (int) ($data['test_duration_minutes'] ?? $defaults['test_duration_minutes'])),
            'work_hours_start' => self::normalizeTime($data['work_hours_start'] ?? $defaults['work_hours_start']),
            'work_hours_end' => self::normalizeTime($data['work_hours_end'] ?? $defaults['work_hours_end']),
        ];
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

    /**
     * Bitta kunning maksimal sig'imi (talaba-test soni).
     * = (ish_vaqti_minut / davomiylik) * kompyuter_soni
     */
    public static function dailyCapacity(): int
    {
        $s = self::getSettings();
        $start = Carbon::createFromFormat('H:i', $s['work_hours_start']);
        $end = Carbon::createFromFormat('H:i', $s['work_hours_end']);
        $minutes = max(0, $end->diffInMinutes($start, false));
        if ($minutes === 0) {
            return 0;
        }
        $slots = (int) floor($minutes / max(1, $s['test_duration_minutes']));
        return $slots * (int) $s['computer_count'];
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
        $duration = (int) self::getSettings()['test_duration_minutes'];
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

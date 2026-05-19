<?php

namespace App\Services;

use App\Models\ExamCapacityOverride;
use App\Models\ExamSchedule;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
            'reserve_count' => 5,
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
            'reserve_count' => max(0, (int) ($data['reserve_count'] ?? $defaults['reserve_count'])),
            'test_duration_minutes' => max(1, (int) ($data['test_duration_minutes'] ?? $defaults['test_duration_minutes'])),
            'work_hours_start' => self::normalizeTime($data['work_hours_start'] ?? $defaults['work_hours_start']),
            'work_hours_end' => self::normalizeTime($data['work_hours_end'] ?? $defaults['work_hours_end']),
            'lunch_start' => self::normalizeTimeOrNull($data['lunch_start'] ?? null),
            'lunch_end' => self::normalizeTimeOrNull($data['lunch_end'] ?? null),
        ];
        // reserve_count computer_count'dan oshmasin
        if ($clean['reserve_count'] >= $clean['computer_count']) {
            $clean['reserve_count'] = max(0, $clean['computer_count'] - 1);
        }
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
        return max(0, $ls->diffInMinutes($le, false));
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
        $minutes = max(0, $start->diffInMinutes($end, false));
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
     *
     * 2/3-urinish (resit) qatorlari uchun butun guruh emas, faqat haqiqatda
     * imtihon topshiradigan talabalar (yiqilganlar) hisoblanadi —
     * bandlikKursatkichi dashboard'idagi mantiq bilan bir xil.
     */
    public static function concurrentStudentsForSlot(string $date, string $time, ?array $exclude = null): int
    {
        $duration = (int) self::getSettingsForDate($date)['test_duration_minutes'];
        $start = Carbon::parse($date . ' ' . substr($time, 0, 5));
        $end = $start->copy()->addMinutes($duration);

        $slots = self::attemptSlots();

        $rows = ExamSchedule::query()
            ->where(function ($q) use ($date, $slots) {
                foreach ($slots as $slot) {
                    $q->orWhere(function ($q2) use ($date, $slot) {
                        $q2->whereDate($slot['date_col'], $date)
                           ->whereNotNull($slot['time_col']);
                        if ($slot['check_na']) {
                            $q2->where($slot['yn'] . '_na', false);
                        }
                    });
                }
            })
            ->get();

        // Shu sanada uchraydigan (row, slot) juftliklarini yig'amiz — per-student
        // offset hisobi butun kun bo'yicha qilinadi, vaqtdan qat'iy nazar.
        $entriesOnDate = [];
        $groupIds = [];
        $resitTriples = [];
        foreach ($rows as $row) {
            foreach ($slots as $slot) {
                $rowDate = $row->{$slot['date_col']};
                $rowTime = $row->{$slot['time_col']};
                if (!$rowDate || $rowDate->format('Y-m-d') !== $date || !$rowTime) {
                    continue;
                }
                if ($slot['check_na'] && $row->{$slot['yn'] . '_na'}) {
                    continue;
                }
                $entriesOnDate[] = ['row' => $row, 'slot' => $slot];
                $groupIds[$row->group_hemis_id] = true;
                if ($slot['attempt'] >= 2 && empty($row->student_hemis_id)
                    && $row->subject_id && $row->semester_code) {
                    $k = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code;
                    $resitTriples[$k] = [
                        'group_hemis_id' => $row->group_hemis_id,
                        'subject_id' => $row->subject_id,
                        'semester_code' => $row->semester_code,
                    ];
                }
            }
        }

        $groupCounts = self::collectGroupCounts(array_keys($groupIds));
        $resitMap = self::resitEligibleCounts(array_values($resitTriples));

        // Individual (per-student) vaqti belgilangan qatorlar soni —
        // sanadan qat'iy nazar, butun (group, subject, sem, yn, attempt)
        // kombinatsiyasi bo'yicha. Per-student qatorda vaqt yo'q bo'lsa,
        // talaba guruh vaqtiga bo'ysunadi va guruh hisobidan ayrilmaydi.
        $combinedTriples = $resitTriples;
        foreach ($entriesOnDate as $entry) {
            $r = $entry['row'];
            if (empty($r->student_hemis_id) && $r->subject_id && $r->semester_code) {
                $k = $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                $combinedTriples[$k] = [
                    'group_hemis_id' => $r->group_hemis_id,
                    'subject_id' => $r->subject_id,
                    'semester_code' => $r->semester_code,
                ];
            }
        }
        $perStudentOffsets = self::perStudentWithTimeCounts(array_values($combinedTriples));

        $total = 0;
        foreach ($entriesOnDate as $entry) {
            $row = $entry['row'];
            $slot = $entry['slot'];
            if (self::isExcluded($row, $slot['yn'], $exclude, $slot['attempt'])) {
                continue;
            }
            $rowTime = $row->{$slot['time_col']};
            $rowStart = Carbon::parse($date . ' ' . substr((string) $rowTime, 0, 5));
            $rowEnd = $rowStart->copy()->addMinutes($duration);
            if (!($rowStart->lt($end) && $rowEnd->gt($start))) {
                continue;
            }
            $total += self::effectiveCountForRow(
                $row, $slot['yn'], $slot['attempt'], $groupCounts, $resitMap, $perStudentOffsets
            );
        }

        return $total;
    }

    /**
     * Bitta ExamSchedule qatori uchun joriy vaqtda haqiqiy talabalar soni.
     * Per-student override → 1, guruh qatori → guruh sig'imi MINUS individual
     * vaqti belgilangan per-student qatorlar (vaqtsiz per-student qatorlar
     * guruh ichida qoladi).
     */
    public static function effectiveStudentCountForSchedule(ExamSchedule $schedule, int $attempt, string $ynType = 'test'): int
    {
        if (!empty($schedule->student_hemis_id)) {
            return 1;
        }
        $base = $attempt <= 1
            ? self::groupStudentCount($schedule->group_hemis_id)
            : 0;
        if ($attempt > 1) {
            if (!$schedule->subject_id || !$schedule->semester_code) {
                return 0;
            }
            $map = self::resitEligibleCounts([[
                'group_hemis_id' => $schedule->group_hemis_id,
                'subject_id' => $schedule->subject_id,
                'semester_code' => $schedule->semester_code,
            ]]);
            $key = $schedule->group_hemis_id . '|' . $schedule->subject_id . '|' . $schedule->semester_code;
            $base = (int) ($map[$key] ?? 0);
        }
        if ($base <= 0 || !$schedule->subject_id || !$schedule->semester_code) {
            return max(0, $base);
        }
        $offsetMap = self::perStudentWithTimeCounts([[
            'group_hemis_id' => $schedule->group_hemis_id,
            'subject_id' => $schedule->subject_id,
            'semester_code' => $schedule->semester_code,
        ]]);
        $offsetKey = $schedule->group_hemis_id . '|' . $schedule->subject_id . '|' . $schedule->semester_code
            . '|' . strtolower($ynType) . '|' . $attempt;
        return max(0, $base - (int) ($offsetMap[$offsetKey] ?? 0));
    }

    /**
     * Berilgan (group, subject, semester) bo'yicha 2/3-urinishga loyiq
     * (yiqilgan) talabalarning hemis_id ro'yxatini qaytaradi —
     * resitEligibleCounts bilan bir xil mantiq, faqat ID'lar.
     *
     * @return array<int,string>  student_hemis_id ro'yxati
     */
    public static function resitEligibleStudentIds(string $groupHemisId, $subjectId, $semesterCode): array
    {
        if (!$groupHemisId || !$subjectId || !$semesterCode) {
            return [];
        }
        try {
            $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');
            $query = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->where('st.group_id', $groupHemisId)
                ->where('st.student_status_code', 11)
                ->where('sg.subject_id', $subjectId)
                ->where('sg.semester_code', $semesterCode)
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereNull('sg.deleted_at');
            if ($hasAttemptCol) {
                $query->where(function ($w) {
                    $w->where('sg.attempt', '>=', 2)
                      ->orWhere(function ($x) {
                          $x->where(function ($y) { $y->where('sg.attempt', 1)->orWhereNull('sg.attempt'); })
                            ->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                      });
                });
            } else {
                $query->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
            }
            return $query->distinct()->pluck('sg.student_hemis_id')->map(fn($v) => (string) $v)->all();
        } catch (\Throwable $e) {
            Log::warning('ExamCapacityService::resitEligibleStudentIds failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Berilgan (group, subject, semester) triples bo'yicha 2/3-urinishga
     * loyiq talabalar (yiqilganlar) sonini batch DB so'rovi bilan hisoblaydi.
     *
     * @param  array<int,array{group_hemis_id:string, subject_id:int|string, semester_code:int|string}>  $triples
     * @return array<string,int>  Kalit: "{group_hemis_id}|{subject_id}|{semester_code}"
     */
    public static function resitEligibleCounts(array $triples): array
    {
        if (empty($triples)) {
            return [];
        }
        $groupIds = array_values(array_unique(array_column($triples, 'group_hemis_id')));
        $subjectIds = array_values(array_unique(array_column($triples, 'subject_id')));
        $semCodes = array_values(array_unique(array_column($triples, 'semester_code')));
        if (!$groupIds || !$subjectIds || !$semCodes) {
            return [];
        }

        $allowed = [];
        foreach ($triples as $t) {
            $allowed[$t['group_hemis_id'] . '|' . $t['subject_id'] . '|' . $t['semester_code']] = true;
        }

        try {
            $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');
            $query = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereIn('st.group_id', $groupIds)
                ->where('st.student_status_code', 11)
                ->whereIn('sg.subject_id', $subjectIds)
                ->whereIn('sg.semester_code', $semCodes)
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereNull('sg.deleted_at');
            if ($hasAttemptCol) {
                $query->where(function ($w) {
                    $w->where('sg.attempt', '>=', 2)
                      ->orWhere(function ($x) {
                          $x->where(function ($y) { $y->where('sg.attempt', 1)->orWhereNull('sg.attempt'); })
                            ->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
                      });
                });
            } else {
                $query->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
            }
            $rows = $query
                ->select('st.group_id', 'sg.subject_id', 'sg.semester_code',
                    DB::raw('COUNT(DISTINCT sg.student_hemis_id) as cnt'))
                ->groupBy('st.group_id', 'sg.subject_id', 'sg.semester_code')
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $k = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (isset($allowed[$k])) {
                    $map[$k] = (int) $r->cnt;
                }
            }
            return $map;
        } catch (\Throwable $e) {
            Log::warning('ExamCapacityService::resitEligibleCounts failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Berilgan (group, subject, semester) triples bo'yicha har bir (yn, attempt)
     * uchun individual vaqti belgilangan per-student exam_schedule qatorlar sonini
     * qaytaradi (sanadan qat'iy nazar). Per-student qatorda vaqt yo'q bo'lsa, u
     * guruh vaqtiga bo'ysunadi va guruh hisobidan ayrilmaydi.
     *
     * @param  array<int,array{group_hemis_id:string, subject_id:int|string, semester_code:int|string}>  $triples
     * @return array<string,int>  Kalit: "{group}|{subject}|{sem}|{yn}|{attempt}"
     */
    public static function perStudentWithTimeCounts(array $triples): array
    {
        if (empty($triples)) {
            return [];
        }
        $groupIds = array_values(array_unique(array_column($triples, 'group_hemis_id')));
        $subjectIds = array_values(array_unique(array_column($triples, 'subject_id')));
        $semCodes = array_values(array_unique(array_column($triples, 'semester_code')));
        if (!$groupIds || !$subjectIds || !$semCodes) {
            return [];
        }

        $allowed = [];
        foreach ($triples as $t) {
            $allowed[$t['group_hemis_id'] . '|' . $t['subject_id'] . '|' . $t['semester_code']] = true;
        }

        try {
            $rows = ExamSchedule::whereNotNull('student_hemis_id')
                ->whereIn('group_hemis_id', $groupIds)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semCodes)
                ->selectRaw('group_hemis_id, subject_id, semester_code,
                    SUM(CASE WHEN oski_time IS NOT NULL THEN 1 ELSE 0 END) as oski_a1,
                    SUM(CASE WHEN oski_resit_time IS NOT NULL THEN 1 ELSE 0 END) as oski_a2,
                    SUM(CASE WHEN oski_resit2_time IS NOT NULL THEN 1 ELSE 0 END) as oski_a3,
                    SUM(CASE WHEN test_time IS NOT NULL THEN 1 ELSE 0 END) as test_a1,
                    SUM(CASE WHEN test_resit_time IS NOT NULL THEN 1 ELSE 0 END) as test_a2,
                    SUM(CASE WHEN test_resit2_time IS NOT NULL THEN 1 ELSE 0 END) as test_a3')
                ->groupBy('group_hemis_id', 'subject_id', 'semester_code')
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $base = $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
                if (!isset($allowed[$base])) {
                    continue;
                }
                $map[$base . '|oski|1'] = (int) $r->oski_a1;
                $map[$base . '|oski|2'] = (int) $r->oski_a2;
                $map[$base . '|oski|3'] = (int) $r->oski_a3;
                $map[$base . '|test|1'] = (int) $r->test_a1;
                $map[$base . '|test|2'] = (int) $r->test_a2;
                $map[$base . '|test|3'] = (int) $r->test_a3;
            }
            return $map;
        } catch (\Throwable $e) {
            Log::warning('ExamCapacityService::perStudentWithTimeCounts failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Har bir YN turi va urinish bo'yicha tegishli sana/vaqt ustunlari.
     *
     * @return array<int,array{yn:string, attempt:int, date_col:string, time_col:string, check_na:bool}>
     */
    private static function attemptSlots(): array
    {
        return [
            ['yn' => 'oski', 'attempt' => 1, 'date_col' => 'oski_date',         'time_col' => 'oski_time',         'check_na' => true],
            ['yn' => 'oski', 'attempt' => 2, 'date_col' => 'oski_resit_date',   'time_col' => 'oski_resit_time',   'check_na' => false],
            ['yn' => 'oski', 'attempt' => 3, 'date_col' => 'oski_resit2_date',  'time_col' => 'oski_resit2_time',  'check_na' => false],
            ['yn' => 'test', 'attempt' => 1, 'date_col' => 'test_date',         'time_col' => 'test_time',         'check_na' => true],
            ['yn' => 'test', 'attempt' => 2, 'date_col' => 'test_resit_date',   'time_col' => 'test_resit_time',   'check_na' => false],
            ['yn' => 'test', 'attempt' => 3, 'date_col' => 'test_resit2_date',  'time_col' => 'test_resit2_time',  'check_na' => false],
        ];
    }

    private static function effectiveCountForRow(
        ExamSchedule $row,
        string $ynType,
        int $attempt,
        array $groupCounts,
        array $resitMap,
        array $perStudentOffsets
    ): int {
        if (!empty($row->student_hemis_id)) {
            return 1;
        }
        $base = $attempt <= 1
            ? (int) ($groupCounts[$row->group_hemis_id] ?? 0)
            : (int) ($resitMap[$row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code] ?? 0);
        if ($base <= 0) {
            return 0;
        }
        $offsetKey = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code
            . '|' . strtolower($ynType) . '|' . $attempt;
        return max(0, $base - (int) ($perStudentOffsets[$offsetKey] ?? 0));
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

    private static function isExcluded($row, string $ynType, ?array $exclude, int $attempt = 1): bool
    {
        if (!$exclude) {
            return false;
        }
        $matchesKey = ($exclude['group_hemis_id'] ?? null) === $row->group_hemis_id
            && (string) ($exclude['subject_id'] ?? null) === (string) $row->subject_id
            && (string) ($exclude['semester_code'] ?? null) === (string) $row->semester_code
            && strtolower((string) ($exclude['yn_type'] ?? null)) === $ynType;
        if (!$matchesKey) {
            return false;
        }
        // Eski chaqiruvlar `attempt` bermaydi — ular faqat 1-urinish (asosiy) yozuvini istisno qiladi
        $excludeAttempt = (int) ($exclude['attempt'] ?? 1);
        return $excludeAttempt === $attempt;
    }
}

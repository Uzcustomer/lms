<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Services\Monitoring\Contracts\Detector;
use App\Services\Monitoring\DetectorResult;
use App\Services\Monitoring\Finding;
use App\Services\Monitoring\OwnerResolver;
use App\Support\WorkdayCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Q-1. Yakuniy nazorat baholari nazoratdan keyin 3 (ish) kun ichida HEMISga kiritiladi;
 * haqqoniylik uchun kafedra mudiri javobgar. (Baholash tartibi 38)
 *
 * Trigger: exam_schedules (guruh bo'yicha) — YN sanasi = OSKI va test sanalarining kechrog'i
 *          (vedomost base_date mantig'i bilan bir xil), muddat = sana + days.
 * Kutilgan harakat: guruhning faol talabalari uchun hemis_exam_grades da shu fan va semestr
 *          bo'yicha YN bahosi (exam_type_code = yn_exam_type_code) bor.
 * Bitta istisno — bitta guruh × fan × nazorat sanasi. Mas'ul: fan kafedrasi mudiri.
 */
class Q1FinalGradesEntry implements Detector
{
    public function __construct(private OwnerResolver $owners)
    {
    }

    public function run(array $params, MonitoringDetector $detector): DetectorResult
    {
        $days = max(0, (int) ($params['days'] ?? 3));
        $workdays = ($params['day_type'] ?? 'workdays') !== 'calendar';
        $ynCode = (string) ($params['yn_exam_type_code'] ?? '13');
        $keepLate = ($params['late_completion'] ?? 'auto_close') === 'keep_open';
        $lookback = max(1, (int) ($params['lookback_days'] ?? 60));

        $now = now();
        $from = $now->copy()->startOfDay()->subDays($lookback);

        $schedules = DB::table('exam_schedules')
            ->whereNull('student_hemis_id')
            ->where(fn ($q) => $q->where('oski_date', '>=', $from)->orWhere('test_date', '>=', $from))
            ->get(['group_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'oski_date', 'oski_na', 'test_date', 'test_na']);

        $due = [];
        foreach ($schedules as $s) {
            $examDate = self::examDate($s);
            if ($examDate === null || $examDate->lt($from)) {
                continue;
            }
            $deadline = self::deadline($examDate, $days, $workdays);
            if ($now->lte($deadline)) {
                continue; // muddat hali tugamagan
            }
            $due[] = ['row' => $s, 'date' => $examDate, 'deadline' => $deadline];
        }

        $result = new DetectorResult(array_values(array_unique(array_map(fn ($d) => $d['date']->toDateString(), $due))));
        if ($due === []) {
            return $result;
        }

        $groups = DB::table('groups')
            ->whereIn('group_hemis_id', array_unique(array_map(fn ($d) => $d['row']->group_hemis_id, $due)))
            ->get(['group_hemis_id', 'name', 'department_hemis_id', 'curriculum_hemis_id'])
            ->keyBy('group_hemis_id');

        $studentsByGroup = DB::table('students')
            ->whereIn('group_id', $groups->keys()->all())
            ->where('student_status_code', D2UnexcusedAbsence::STUDYING)
            ->get(['hemis_id', 'group_id'])
            ->groupBy('group_id')
            ->map(fn (Collection $c) => $c->pluck('hemis_id')->map(fn ($v) => (string) $v)->all());

        foreach ($due as $item) {
            $s = $item['row'];
            $group = $groups->get($s->group_hemis_id);
            $studentIds = $studentsByGroup->get($s->group_hemis_id, []);
            if ($studentIds === []) {
                continue;
            }

            $entries = $this->entryDates($studentIds, (string) $s->subject_id, (string) $s->semester_code, $ynCode);
            $missing = array_values(array_diff($studentIds, array_keys($entries)));
            $late = array_keys(array_filter($entries, fn ($at) => $at !== null && $at->gt($item['deadline'])));

            if ($missing === [] && (!$keepLate || $late === [])) {
                continue;
            }

            $kafedraId = $this->kafedraOf($group, (string) $s->subject_id, (string) $s->semester_code);
            $groupName = $group->name ?? (string) $s->group_hemis_id;

            $result->add(new Finding(
                fingerprint: sprintf('Q-1:%s:%s:%s:%s', $s->group_hemis_id, $s->subject_id, $s->semester_code, $item['date']->format('Ymd')),
                subjectType: 'qaydnoma',
                subjectLabel: "{$groupName} — {$s->subject_name} (YN {$item['date']->format('d.m.Y')})",
                subjectRef: (string) $s->group_hemis_id,
                details: [
                    'guruh' => $groupName,
                    'fan' => $s->subject_name,
                    'semestr' => $s->semester_code,
                    'nazorat_sanasi' => $item['date']->toDateString(),
                    'muddat' => $item['deadline']->toDateTimeString(),
                    'talabalar' => count($studentIds),
                    'kiritilmagan' => count($missing),
                    'kechikib_kiritilgan' => count($late),
                ],
                scopeKey: $item['date']->toDateString(),
                facultyHemisId: $group?->department_hemis_id ? (int) $group->department_hemis_id : null,
                departmentHemisId: $kafedraId,
                owner: $this->owners->headOfDepartment($kafedraId),
            ));
        }

        return $result;
    }

    /** YN sanasi — belgilangan OSKI va test sanalarining kechrog'i. */
    public static function examDate(object $s): ?Carbon
    {
        $dates = [];
        if (!$s->oski_na && $s->oski_date) {
            $dates[] = Carbon::parse($s->oski_date)->startOfDay();
        }
        if (!$s->test_na && $s->test_date) {
            $dates[] = Carbon::parse($s->test_date)->startOfDay();
        }

        return $dates === [] ? null : max($dates);
    }

    public static function deadline(CarbonInterface $examDate, int $days, bool $workdays): Carbon
    {
        return $workdays
            ? WorkdayCalculator::addWorkdays($examDate, $days)->endOfDay()
            : Carbon::instance($examDate)->copy()->addDays($days)->endOfDay();
    }

    /**
     * Talaba → YN bahosi HEMIS'ga kiritilgan eng erta vaqt (HEMIS created_at, bo'lmasa birinchi sinxron).
     *
     * @return array<string, ?Carbon>
     */
    private function entryDates(array $studentIds, string $subjectId, string $semesterCode, string $ynCode): array
    {
        $rows = DB::table('hemis_exam_grades')
            ->whereIn('student_hemis_id', $studentIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('exam_type_code', $ynCode)
            ->get(['student_hemis_id', 'hemis_created_at', 'first_seen_at', 'created_at']);

        $result = [];
        foreach ($rows as $r) {
            $at = $r->hemis_created_at
                ? Carbon::createFromTimestamp((int) $r->hemis_created_at)
                : (($r->first_seen_at ?? $r->created_at) ? Carbon::parse($r->first_seen_at ?? $r->created_at) : null);
            $key = (string) $r->student_hemis_id;
            if (!array_key_exists($key, $result) || ($at !== null && ($result[$key] === null || $at->lt($result[$key])))) {
                $result[$key] = $at;
            }
        }

        return $result;
    }

    private function kafedraOf(?object $group, string $subjectId, string $semesterCode): ?int
    {
        if ($group === null) {
            return null;
        }

        $id = DB::table('curriculum_subjects')
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->value('department_id');

        return $id ? (int) $id : null;
    }
}

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
 * Qayta topshirish (include_resits; buyurtmachi qarori 16.10.2026): 2-urinish (12a) — oski/test_resit_date,
 *          3-urinish (12b) — *_resit2_date. Tekshiriladiganlar — mark'da shu urinish bo'yicha OSKI/test
 *          bahosi bor talabalar (student_grades.attempt); kutilgan — HEMIS'da nazorat sanasi qayta topshirish
 *          kunidan kech bo'lmagan YN yozuvi (HEMIS urinish kodlariga bog'lanmaydi).
 * Bitta istisno — bitta guruh × fan × nazorat sanasi (× urinish). Mas'ul: fan kafedrasi mudiri.
 */
class Q1FinalGradesEntry implements Detector
{
    /** mark'dagi YN komponent baholari: 101 — OSKI, 102 — test, 103 — eski quiz (turi quiz_type'dan). */
    private const YN_COMPONENT_TYPES = ['101', '102', '103'];

    /** 103 yozuvi faqat shu quiz turlarida OSKI/YN test hisoblanadi (AcademicScheduleController bilan bir xil). */
    private const YN_QUIZ_TYPES = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)', 'YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

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
        $includeResits = (bool) ($params['include_resits'] ?? true);

        $now = now();
        $from = $now->copy()->startOfDay()->subDays($lookback);
        $dateColumns = $includeResits
            ? ['oski_date', 'test_date', 'oski_resit_date', 'test_resit_date', 'oski_resit2_date', 'test_resit2_date']
            : ['oski_date', 'test_date'];

        $schedules = DB::table('exam_schedules')
            ->whereNull('student_hemis_id')
            ->where(function ($q) use ($dateColumns, $from) {
                foreach ($dateColumns as $column) {
                    $q->orWhere($column, '>=', $from);
                }
            })
            ->get(array_merge(['group_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'oski_na', 'test_na'], $dateColumns));

        $due = [];
        foreach ($schedules as $s) {
            foreach (self::attemptDates($s, $includeResits) as $attempt => $examDate) {
                if ($examDate->lt($from)) {
                    continue;
                }
                $deadline = self::deadline($examDate, $days, $workdays);
                if ($now->lte($deadline)) {
                    continue; // muddat hali tugamagan
                }
                $due[] = ['row' => $s, 'attempt' => $attempt, 'date' => $examDate, 'deadline' => $deadline];
            }
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
            $attempt = $item['attempt'];
            $group = $groups->get($s->group_hemis_id);
            $studentIds = $studentsByGroup->get($s->group_hemis_id, []);
            if ($attempt > 1) {
                // Qayta topshirishda faqat shu urinishda qatnashganlar (mark'da OSKI/test bahosi bor).
                $studentIds = $this->resitParticipants($studentIds, (string) $s->subject_id, (string) $s->semester_code, $attempt);
            }
            if ($studentIds === []) {
                continue;
            }

            $entries = $this->entryDates($studentIds, (string) $s->subject_id, (string) $s->semester_code, $ynCode, $attempt > 1 ? $item['date'] : null);
            $missing = array_values(array_diff($studentIds, array_keys($entries)));
            $late = array_keys(array_filter($entries, fn ($at) => $at !== null && $at->gt($item['deadline'])));

            if ($missing === [] && (!$keepLate || $late === [])) {
                continue;
            }

            $kafedraId = $this->kafedraOf($group, (string) $s->subject_id, (string) $s->semester_code);
            $groupName = $group->name ?? (string) $s->group_hemis_id;
            $attemptLabel = $attempt > 1 ? ', qayta topshirish ' . ($attempt - 1) : '';

            $result->add(new Finding(
                fingerprint: sprintf('Q-1:%s:%s:%s:%s', $s->group_hemis_id, $s->subject_id, $s->semester_code, $item['date']->format('Ymd'))
                    . ($attempt > 1 ? ":u{$attempt}" : ''),
                subjectType: 'qaydnoma',
                subjectLabel: "{$groupName} — {$s->subject_name} (YN {$item['date']->format('d.m.Y')}{$attemptLabel})",
                subjectRef: (string) $s->group_hemis_id,
                details: [
                    'guruh' => $groupName,
                    'fan' => $s->subject_name,
                    'semestr' => $s->semester_code,
                    'urinish' => $attempt === 1 ? 'asosiy' : ($attempt - 1) . '-qayta topshirish',
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

    /**
     * Urinish → nazorat sanasi: 1 — asosiy (OSKI va test sanalarining kechrog'i),
     * 2 — 1-qayta topshirish, 3 — 2-qayta topshirish.
     *
     * @return array<int, Carbon>
     */
    public static function attemptDates(object $s, bool $includeResits): array
    {
        $result = [];
        if ($main = self::examDate($s)) {
            $result[1] = $main;
        }
        if ($includeResits) {
            foreach ([2 => ['oski_resit_date', 'test_resit_date'], 3 => ['oski_resit2_date', 'test_resit2_date']] as $attempt => $columns) {
                $dates = array_filter(array_map(fn ($c) => !empty($s->{$c}) ? Carbon::parse($s->{$c})->startOfDay() : null, $columns));
                if ($dates !== []) {
                    $result[$attempt] = max($dates);
                }
            }
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
    private function entryDates(array $studentIds, string $subjectId, string $semesterCode, string $ynCode, ?Carbon $minExamDate = null): array
    {
        $rows = DB::table('hemis_exam_grades')
            ->whereIn('student_hemis_id', $studentIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('exam_type_code', $ynCode)
            ->get(['student_hemis_id', 'exam_date', 'hemis_created_at', 'first_seen_at', 'created_at']);

        $result = [];
        foreach ($rows as $r) {
            $at = $r->hemis_created_at
                ? Carbon::createFromTimestamp((int) $r->hemis_created_at)
                : (($r->first_seen_at ?? $r->created_at) ? Carbon::parse($r->first_seen_at ?? $r->created_at) : null);
            if ($minExamDate !== null) {
                // Qayta topshirish bahosi: HEMIS'dagi nazorat sanasi (bo'lmasa kiritilgan vaqti) qayta topshirish kunidan oldin emas.
                $examAt = $r->exam_date ? Carbon::parse($r->exam_date)->startOfDay() : $at?->copy()->startOfDay();
                if ($examAt === null || $examAt->lt($minExamDate)) {
                    continue;
                }
            }
            $key = (string) $r->student_hemis_id;
            if (!array_key_exists($key, $result) || ($at !== null && ($result[$key] === null || $at->lt($result[$key])))) {
                $result[$key] = $at;
            }
        }

        return $result;
    }

    /** Qayta topshirishda qatnashganlar: mark'da shu urinish bo'yicha OSKI/test bahosi bor talabalar. */
    private function resitParticipants(array $studentIds, string $subjectId, string $semesterCode, int $attempt): array
    {
        $rows = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('training_type_code', self::YN_COMPONENT_TYPES)
            ->where('attempt', $attempt)
            ->whereNull('deleted_at')
            ->get(['student_hemis_id', 'training_type_code', 'quiz_result_id']);

        // Eski 103 yozuvlari: faqat quiz turi OSKI yoki YN test bo'lsa (boshqa quizlar qayta topshirish emas).
        $quizIds = $rows->where('training_type_code', '103')->pluck('quiz_result_id')->filter()->unique()->values()->all();
        $quizTypes = $quizIds === [] ? [] : DB::table('hemis_quiz_results')->whereIn('id', $quizIds)->pluck('quiz_type', 'id')->all();

        return $rows
            ->filter(fn ($r) => (string) $r->training_type_code !== '103'
                || ($r->quiz_result_id && in_array($quizTypes[$r->quiz_result_id] ?? null, self::YN_QUIZ_TYPES, true)))
            ->pluck('student_hemis_id')
            ->map(fn ($v) => (string) $v)
            ->unique()
            ->values()
            ->all();
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

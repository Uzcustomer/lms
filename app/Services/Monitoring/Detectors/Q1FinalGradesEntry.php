<?php

namespace App\Services\Monitoring\Detectors;

use App\Models\MonitoringDetector;
use App\Models\VedomostSubmission;
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
 * check_against = vedomost (standart; buyurtmachi qarori 16.10.2026 — HEMIS'ga yakuniy natijalar semestr oxirida
 *          markazlashtirib kiritiladi): mark'dagi vedomost topshirish jarayoni (vedomost_submissions) — YN yoki
 *          oxirgi dars sanasi + 3 ish kuni (jarayonning o'zi hisoblagan deadline) ichida vedomost yuklanganmi.
 *          Mas'ul — vedomostdagi kafedra mudiri.
 * check_against = hemis — quyidagi HEMIS tekshiruvi.
 *
 * Trigger: exam_schedules (guruh bo'yicha) — YN sanasi = OSKI va test sanalarining kechrog'i
 *          (vedomost base_date mantig'i bilan bir xil), muddat = sana + days.
 * Kutilgan harakat: guruhning faol talabalari uchun hemis_exam_grades da shu fan va semestr
 *          bo'yicha YN bahosi (exam_type_code = yn_exam_type_code) bor.
 * Qayta topshirish (include_resits; buyurtmachi qarori 16.10.2026): 2-urinish (12a) — oski/test_resit_date,
 *          3-urinish (12b) — *_resit2_date. Tekshiriladiganlar — mark'da shu urinish bo'yicha OSKI/test
 *          bahosi bor talabalar (student_grades.attempt); kutilgan — HEMIS'da nazorat sanasi qayta topshirish
 *          kunidan kech bo'lmagan YN yozuvi (HEMIS urinish kodlariga bog'lanmaydi).
 * Individual sanalar (exam_schedules.student_hemis_id; IndividualExamScheduleController): talaba qatorida belgilangan
 *          urinish sanasi guruh sanasi o'rniga olinadi — talaba o'z sanasi bo'yicha tekshiriladi.
 * Bitta istisno — bitta guruh × fan × nazorat sanasi (× urinish). Mas'ul: fan kafedrasi mudiri.
 */
class Q1FinalGradesEntry implements Detector
{
    /** mark'dagi YN komponent baholari: 101 — OSKI, 102 — test, 103 — eski quiz (turi quiz_type'dan). */
    private const YN_COMPONENT_TYPES = ['101', '102', '103'];

    /** Urinish → shu urinishning OSKI va test sanasi ustunlari. */
    private const ATTEMPT_COLUMNS = [
        1 => ['oski_date', 'test_date'],
        2 => ['oski_resit_date', 'test_resit_date'],
        3 => ['oski_resit2_date', 'test_resit2_date'],
    ];

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

        if (($params['check_against'] ?? 'vedomost') !== 'hemis') {
            return $this->vedomostFindings($lookback, $keepLate);
        }

        $now = now();
        $from = $now->copy()->startOfDay()->subDays($lookback);
        $dateColumns = $includeResits
            ? ['oski_date', 'test_date', 'oski_resit_date', 'test_resit_date', 'oski_resit2_date', 'test_resit2_date']
            : ['oski_date', 'test_date'];

        $columns = array_merge(['group_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'oski_na', 'test_na'], $dateColumns);
        $recent = function ($q) use ($dateColumns, $from) {
            foreach ($dateColumns as $column) {
                $q->orWhere($column, '>=', $from);
            }
        };

        $units = [];
        $schedules = DB::table('exam_schedules')->whereNull('student_hemis_id')->where($recent)->get($columns);
        foreach ($schedules as $s) {
            foreach (self::attemptDates($s, $includeResits) as $attempt => $examDate) {
                $this->addUnit($units, $s, $attempt, $examDate);
            }
        }

        // Individual sana belgilangan talaba shu urinishda guruh birligidan chiqariladi va o'z sanasi bo'yicha tekshiriladi.
        $overridden = [];
        foreach ($this->individualDates($recent, $schedules, $columns, $includeResits) as $o) {
            $overridden[implode('|', [$o['student'], $o['row']->subject_id, $o['row']->semester_code, $o['attempt']])] = true;
            $this->addUnit($units, $o['row'], $o['attempt'], $o['date'], $o['student']);
        }

        $due = [];
        foreach ($units as $unit) {
            if ($unit['date']->lt($from)) {
                continue;
            }
            $unit['deadline'] = self::deadline($unit['date'], $days, $workdays);
            if ($now->lte($unit['deadline'])) {
                continue; // muddat hali tugamagan
            }
            $due[] = $unit;
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
            $studentIds = $item['group_wide']
                ? array_filter($studentsByGroup->get($s->group_hemis_id, []), fn ($id) => !isset($overridden[implode('|', [$id, $s->subject_id, $s->semester_code, $attempt])]))
                : [];
            $studentIds = array_values(array_unique(array_merge($studentIds, $item['students'])));
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
     * Vedomost rejimi: muddati (deadline) o'tgan, lekin vedomost yuklanmagan (yoki keep_open bo'lsa — kechikib
     * yuklangan) qatorlar. Tekshirilgan davr — oxirgi lookback kunlari: shu davrdagi boshqa Q-1 istisnolari
     * (jumladan HEMIS rejimidan qolganlari) bartaraf bo'lgan deb yopiladi.
     */
    private function vedomostFindings(int $lookback, bool $keepLate): DetectorResult
    {
        $now = now();
        $from = $now->copy()->startOfDay()->subDays($lookback);

        $scopes = [];
        for ($day = $from->copy(); $day->lte($now); $day->addDay()) {
            $scopes[] = $day->toDateString();
        }
        $result = new DetectorResult($scopes);

        $rows = DB::table('vedomost_submissions')
            ->whereNotNull('base_date')
            ->whereNotNull('deadline')
            ->where('base_date', '>=', $from->toDateString())
            ->where('deadline', '<', $now->toDateString())
            ->get(['id', 'group_hemis_id', 'group_name', 'subject_name', 'semester_code', 'form_type', 'base_type', 'base_date',
                'deadline', 'status', 'uploaded_at', 'department_hemis_id', 'kafedra_mudiri_hemis_id', 'kafedra_mudiri_name']);
        if ($rows->isEmpty()) {
            return $result;
        }

        $faculties = DB::table('groups')
            ->whereIn('group_hemis_id', $rows->pluck('group_hemis_id')->unique()->all())
            ->pluck('department_hemis_id', 'group_hemis_id');
        $statuses = VedomostSubmission::statusLabels();

        foreach ($rows as $row) {
            $deadline = Carbon::parse($row->deadline)->endOfDay();
            $uploadedAt = $row->uploaded_at ? Carbon::parse($row->uploaded_at) : null;
            if ($uploadedAt !== null && (!$keepLate || $uploadedAt->lte($deadline))) {
                continue;
            }

            $baseDate = Carbon::parse($row->base_date);
            $baseLabel = ($row->base_type === 'lesson' ? 'oxirgi dars ' : 'YN ') . $baseDate->format('d.m.Y');
            $faculty = $faculties[$row->group_hemis_id] ?? null;

            $result->add(new Finding(
                fingerprint: "Q-1:v:{$row->id}",
                subjectType: 'qaydnoma',
                subjectLabel: "{$row->group_name} — {$row->subject_name} (vedomost {$row->form_type}-shakl, {$baseLabel})",
                subjectRef: (string) $row->group_hemis_id,
                details: [
                    'guruh' => $row->group_name,
                    'fan' => $row->subject_name,
                    'semestr' => $row->semester_code,
                    'shakl' => $row->form_type . '-shakl',
                    'nazorat_sanasi' => $baseDate->toDateString(),
                    'muddat' => $deadline->toDateTimeString(),
                    'vedomost_holati' => $statuses[$row->status] ?? $row->status,
                    'yuklangan' => $uploadedAt?->toDateTimeString() ?? 'yuklanmagan',
                    'kafedra_mudiri' => $row->kafedra_mudiri_name,
                    'kutilgan_harakat' => "Imzolangan vedomost muddatida mark'ka yuklanishi",
                ],
                scopeKey: $baseDate->toDateString(),
                facultyHemisId: $faculty ? (int) $faculty : null,
                departmentHemisId: $row->department_hemis_id ? (int) $row->department_hemis_id : null,
                owner: $this->owners->teacherByHemisId($row->kafedra_mudiri_hemis_id)
                    ?? $this->owners->headOfDepartment($row->department_hemis_id ? (int) $row->department_hemis_id : null),
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
            foreach ([2, 3] as $attempt) {
                $dates = array_filter(array_map(fn ($c) => !empty($s->{$c}) ? Carbon::parse($s->{$c})->startOfDay() : null, self::ATTEMPT_COLUMNS[$attempt]));
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

    /**
     * Birlik — guruh × fan × semestr × urinish × nazorat sanasi. $student berilmasa — guruhning barcha talabalari,
     * berilsa — individual sanasi shu kunga to'g'ri kelgan talaba qo'shiladi.
     */
    private function addUnit(array &$units, object $row, int $attempt, Carbon $date, ?string $student = null): void
    {
        $key = implode('|', [$row->group_hemis_id, $row->subject_id, $row->semester_code, $attempt, $date->format('Ymd')]);
        $units[$key] ??= ['row' => $row, 'attempt' => $attempt, 'date' => $date, 'group_wide' => false, 'students' => []];
        if ($student === null) {
            $units[$key]['row'] = $row;
            $units[$key]['group_wide'] = true;
        } else {
            $units[$key]['students'][] = $student;
        }
    }

    /**
     * Individual sanalar: o'qiyotgan talabaning alohida qatorida belgilangan ustun guruh qatoridagi qiymat o'rniga olinadi,
     * belgilanmagani guruhnikidan (ExamCapacityService bilan bir xil). Faqat talaba qatorida sanasi bor urinishlar qaytadi.
     * Tekshiriladigan guruh × fanlarning individual qatorlari sanasi lookback'dan eski bo'lsa ham olinadi: talaba guruh
     * sanasi bo'yicha tekshirilmasligi kerak.
     *
     * @return list<array{row: object, student: string, attempt: int, date: Carbon}>
     */
    private function individualDates(\Closure $recent, Collection $schedules, array $columns, bool $includeResits): array
    {
        $rows = DB::table('exam_schedules')
            ->whereNotNull('student_hemis_id')
            ->where(function ($q) use ($recent, $schedules) {
                $q->where($recent);
                if ($schedules->isNotEmpty()) {
                    $q->orWhere(fn ($w) => $w
                        ->whereIn('group_hemis_id', $schedules->pluck('group_hemis_id')->unique()->all())
                        ->whereIn('subject_id', $schedules->pluck('subject_id')->unique()->all()));
                }
            })
            ->get(array_merge($columns, ['student_hemis_id']));
        if ($rows->isEmpty()) {
            return [];
        }

        $active = DB::table('students')
            ->whereIn('hemis_id', $rows->pluck('student_hemis_id')->unique()->all())
            ->where('student_status_code', D2UnexcusedAbsence::STUDYING)
            ->pluck('hemis_id')
            ->mapWithKeys(fn ($v) => [(string) $v => true]);

        $groupRows = DB::table('exam_schedules')
            ->whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $rows->pluck('group_hemis_id')->unique()->all())
            ->whereIn('subject_id', $rows->pluck('subject_id')->unique()->all())
            ->get($columns)
            ->keyBy(fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code);

        $result = [];
        foreach ($rows as $row) {
            $student = (string) $row->student_hemis_id;
            if (!$active->has($student)) {
                continue;
            }
            $groupRow = $groupRows->get($row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code);
            $merged = clone $row;
            foreach (array_merge(...self::ATTEMPT_COLUMNS) as $column) {
                $merged->{$column} = $row->{$column} ?? $groupRow?->{$column} ?? null;
            }
            $merged->oski_na = $row->oski_date ? 0 : ($groupRow->oski_na ?? 0);
            $merged->test_na = $row->test_date ? 0 : ($groupRow->test_na ?? 0);

            foreach (self::attemptDates($merged, $includeResits) as $attempt => $date) {
                if (array_filter(self::ATTEMPT_COLUMNS[$attempt], fn ($c) => !empty($row->{$c})) !== []) {
                    $result[] = ['row' => $merged, 'student' => $student, 'attempt' => $attempt, 'date' => $date];
                }
            }
        }

        return $result;
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

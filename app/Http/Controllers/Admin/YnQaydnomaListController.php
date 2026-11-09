<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YnQaydnomaConfirmation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Vedomostlar": YN (OSKI / test) imtihonlari tugagan fanlar bo'yicha qaydnoma kimlar tomonidan
 * topshirilgani (amaliy o'qituvchi tasdiqlagani) va dekan tasdiqlagani. Dekan — faqat o'z
 * fakulteti guruhlari; admin/superadmin/registrator — hammasi (fakultet filtri bilan), dekan
 * tasdiqlaganlari PDF da yuklab olinadi (Vedomost tekshirish).
 */
class YnQaydnomaListController extends Controller
{
    private const PER_PAGE = 30;

    public const STATUS_NONE = 'none';         // o'qituvchi hali tasdiqlamagan
    public const STATUS_TEACHER = 'teacher';   // o'qituvchi tasdiqlagan, dekan kutilmoqda
    public const STATUS_DEAN = 'dean';         // dekan ham tasdiqlagan

    public function index(Request $request)
    {
        $isDean = is_active_dekan();
        $current = $request->input('current', '1') === '1';
        $rows = $this->finishedVedomosts($isDean ? JournalController::deanFacultyHemisIds() : null, $current);

        // Filtr variantlari — faqat shu foydalanuvchiga tegishli qatorlardan
        $options = [
            'faculties' => $rows->pluck('faculty', 'faculty_id')->filter()->sort(),
            'levels' => $rows->pluck('level_name', 'level_code')->filter()->sortKeys(),
            'specialties' => $rows->pluck('specialty', 'specialty_id')->filter()->sort(),
            'groups' => $rows->pluck('group', 'group_hemis_id')->filter()->sort(),
        ];

        $f = $request->only(['faculty', 'level', 'specialty', 'group', 'q', 'status']);
        $filtered = $rows
            ->when(!$isDean && !empty($f['faculty']), fn ($c) => $c->where('faculty_id', (string) $f['faculty']))
            ->when(!empty($f['level']), fn ($c) => $c->where('level_code', (string) $f['level']))
            ->when(!empty($f['specialty']), fn ($c) => $c->where('specialty_id', (string) $f['specialty']))
            ->when(!empty($f['group']), fn ($c) => $c->where('group_hemis_id', (string) $f['group']))
            ->when(trim($f['q'] ?? '') !== '', function ($c) use ($f) {
                $q = mb_strtolower(trim($f['q']));

                return $c->filter(fn ($r) => str_contains(mb_strtolower($r->group . ' ' . $r->subject . ' '
                    . $r->teachers->pluck('teacher_name')->implode(' ') . ' ' . ($r->dean->teacher_name ?? '')), $q));
            });
        $counts = [
            'all' => $filtered->count(),
            self::STATUS_NONE => $filtered->where('status', self::STATUS_NONE)->count(),
            self::STATUS_TEACHER => $filtered->where('status', self::STATUS_TEACHER)->count(),
            self::STATUS_DEAN => $filtered->where('status', self::STATUS_DEAN)->count(),
        ];
        if (in_array($f['status'] ?? '', [self::STATUS_NONE, self::STATUS_TEACHER, self::STATUS_DEAN], true)) {
            $filtered = $filtered->where('status', $f['status']);
        }
        $filtered = $filtered->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = $filtered->forPage($page, self::PER_PAGE)->values();
        $this->attachPendingTeachers($items);
        $paginator = new LengthAwarePaginator($items, $filtered->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('admin.yn-qaydnomalar.index', [
            'rows' => $paginator, 'counts' => $counts, 'options' => $options, 'filters' => $f + ['current' => $current ? '1' : '0'],
            'isDean' => $isDean,
            // PDF — Vedomost tekshirish yo'li (registrator, admin); dekanga emas
            'canPdf' => !$isDean,
        ]);
    }

    /**
     * YN imtihonlari tugagan guruh × fan lar (yopilish shakli talab qilgan OSKI / test sanasi
     * bugungacha o'tgan) va o'qituvchi topshirgan barcha qaydnomalar (imtihoni tugamagan bo'lsa
     * ham), tasdiq holati bilan; eng so'nggisi birinchi.
     */
    public function finishedVedomosts(?array $facultyHemisIds, bool $currentSemester = true): Collection
    {
        $today = Carbon::today()->toDateString();

        $exams = DB::table('exam_schedules as es')
            ->join('groups as g', 'g.group_hemis_id', '=', 'es.group_hemis_id')
            ->leftJoin('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'g.curriculum_hemis_id')->on('s.code', '=', 'es.semester_code');
            })
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
                  ->on('cs.subject_id', '=', 'es.subject_id')
                  ->on('cs.semester_code', '=', 'es.semester_code');
            })
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->leftJoin('departments as dep', 'dep.department_hemis_id', '=', 'g.department_hemis_id')
            ->whereNull('es.student_hemis_id')
            ->when($facultyHemisIds !== null, fn ($q) => $q->whereIn('g.department_hemis_id', $facultyHemisIds ?: [-1]))
            ->when($currentSemester, fn ($q) => $q->where('s.current', true))
            ->where(fn ($q) => $q->where('es.oski_date', '<=', $today)->orWhere('es.test_date', '<=', $today))
            ->select('es.group_hemis_id', 'es.subject_id', 'es.semester_code', 'es.oski_date', 'es.test_date', 'es.oski_na', 'es.test_na',
                'es.subject_name as exam_subject_name', 'cs.subject_name', 'cs.semester_name', 'cs.closing_form',
                'g.name as group_name', 'g.department_hemis_id', 'g.specialty_hemis_id', 'dep.name as faculty_name',
                'sp.name as specialty_name', 's.level_code', 's.level_name')
            ->get()
            ->unique(fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code)
            ->filter(fn ($r) => self::examsFinished($r, $today));

        $key = fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
        $exams = $exams->keyBy($key);

        // Tasdiqlar — fakultet guruhlari bo'yicha (imtihon jadvalidagi guruhlar bilan cheklanmaydi)
        $facultyGroups = $facultyHemisIds === null ? null
            : DB::table('groups')->whereIn('department_hemis_id', $facultyHemisIds ?: [-1])->pluck('group_hemis_id')->map(fn ($v) => (string) $v);
        $confirmations = YnQaydnomaConfirmation::with('verification')
            ->when($facultyGroups !== null, fn ($q) => $q->whereIn('group_hemis_id', $facultyGroups->all() ?: ['-']))
            ->orderByDesc('confirmed_at')
            ->get()
            ->groupBy($key);

        // O'qituvchi topshirgan qaydnoma — imtihon hali tugamagan / jadvalda yo'q bo'lsa ham ro'yxatda
        $extraKeys = $confirmations->keys()->diff($exams->keys());
        if ($extraKeys->isNotEmpty()) {
            $exams = $exams->union($this->entriesForConfirmations($extraKeys, $confirmations, $currentSemester));
        }

        return $exams->map(function ($r) use ($confirmations, $key) {
            $all = $confirmations->get($key($r), collect());
            $teachers = $all->where('role', YnQaydnomaConfirmation::ROLE_TEACHER)->unique('teacher_id')->values();
            $dean = $teachers->isNotEmpty() ? $all->firstWhere('role', YnQaydnomaConfirmation::ROLE_DEAN) : null;
            $w = $teachers->first()->weights ?? [];

            return (object) [
                'group_hemis_id' => (string) $r->group_hemis_id,
                'group' => $r->group_name,
                'faculty_id' => (string) $r->department_hemis_id,
                'faculty' => $r->faculty_name ?? '',
                'specialty_id' => (string) $r->specialty_hemis_id,
                'specialty' => $r->specialty_name ?? '',
                'level_code' => (string) $r->level_code,
                'level_name' => $r->level_name ?? '',
                'subject_id' => (string) $r->subject_id,
                'subject' => $r->subject_name ?? $r->exam_subject_name,
                'semester_code' => (string) $r->semester_code,
                'semester' => $r->semester_name ?? $r->semester_code,
                'oski_date' => $r->oski_na ? null : $r->oski_date,
                'test_date' => $r->test_na ? null : $r->test_date,
                'finished_at' => max((string) $r->oski_date, (string) $r->test_date, (string) ($all->first()?->confirmed_at)),
                'teachers' => $teachers,
                'dean' => $dean,
                'pending_teachers' => collect(),
                'status' => $dean ? self::STATUS_DEAN : ($teachers->isNotEmpty() ? self::STATUS_TEACHER : self::STATUS_NONE),
                'params' => $teachers->isEmpty() ? null : [
                    'subject_id' => (string) $r->subject_id, 'semester_code' => (string) $r->semester_code, 'group_hemis_id' => (string) $r->group_hemis_id,
                    'weight_jn' => $w['jn'] ?? 30, 'weight_mt' => $w['mt'] ?? 10, 'weight_on' => $w['on'] ?? 0,
                    'weight_oski' => $w['oski'] ?? 0, 'weight_test' => $w['test'] ?? 60,
                ],
            ];
        })->sortByDesc('finished_at')->values();
    }

    /**
     * Tasdiqlangan, lekin imtihoni tugaganlar ro'yxatida yo'q qaydnomalar uchun qator ma'lumoti
     * (guruh, semestr, fan nomlari — jadvallardan, bo'lmasa tasdiq yozuvidan). Joriy semestr
     * filtri semestr yozuvi bo'lsagina qo'llanadi.
     */
    private function entriesForConfirmations(Collection $keys, Collection $confirmations, bool $currentSemester): Collection
    {
        $first = $keys->mapWithKeys(fn ($k) => [$k => $confirmations[$k]->first()]);
        $groups = DB::table('groups as g')
            ->leftJoin('departments as dep', 'dep.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->whereIn('g.group_hemis_id', $first->pluck('group_hemis_id')->unique()->values())
            ->get(['g.group_hemis_id', 'g.name', 'g.curriculum_hemis_id', 'g.department_hemis_id', 'g.specialty_hemis_id', 'dep.name as faculty_name', 'sp.name as specialty_name'])
            ->keyBy(fn ($g) => (string) $g->group_hemis_id);
        $curricula = $groups->pluck('curriculum_hemis_id')->filter()->unique()->values();
        $semesters = DB::table('semesters')->whereIn('curriculum_hemis_id', $curricula)
            ->get(['curriculum_hemis_id', 'code', 'current', 'level_code', 'level_name'])
            ->keyBy(fn ($s) => $s->curriculum_hemis_id . '|' . $s->code);
        $subjects = DB::table('curriculum_subjects')->whereIn('curricula_hemis_id', $curricula)
            ->whereIn('subject_id', $first->pluck('subject_id')->unique()->values())
            ->get(['curricula_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'semester_name'])
            ->keyBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code);
        $exams = DB::table('exam_schedules')->whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $first->pluck('group_hemis_id')->unique()->values())
            ->whereIn('subject_id', $first->pluck('subject_id')->unique()->values())
            ->get(['group_hemis_id', 'subject_id', 'semester_code', 'oski_date', 'test_date', 'oski_na', 'test_na'])
            ->keyBy(fn ($e) => $e->group_hemis_id . '|' . $e->subject_id . '|' . $e->semester_code);

        return $first->map(function ($c, $k) use ($groups, $semesters, $subjects, $exams) {
            $g = $groups->get((string) $c->group_hemis_id);
            if (!$g) {
                return null;
            }
            $sem = $semesters->get($g->curriculum_hemis_id . '|' . $c->semester_code);
            $subject = $subjects->get($g->curriculum_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code);
            $exam = $exams->get($k);

            return (object) [
                'group_hemis_id' => $c->group_hemis_id, 'subject_id' => $c->subject_id, 'semester_code' => $c->semester_code,
                'oski_date' => $exam->oski_date ?? null, 'test_date' => $exam->test_date ?? null,
                'oski_na' => $exam->oski_na ?? false, 'test_na' => $exam->test_na ?? false,
                'exam_subject_name' => $c->verification->subject_name ?? ('Fan #' . $c->subject_id),
                'subject_name' => $subject->subject_name ?? null,
                'semester_name' => $subject->semester_name ?? ($c->verification->semester_name ?? null),
                'group_name' => $g->name, 'department_hemis_id' => $g->department_hemis_id, 'specialty_hemis_id' => $g->specialty_hemis_id,
                'faculty_name' => $g->faculty_name, 'specialty_name' => $g->specialty_name,
                'level_code' => $sem->level_code ?? '', 'level_name' => $sem->level_name ?? '',
                'current' => $sem ? (bool) $sem->current : true,
            ];
        })->filter(fn ($r) => $r && (!$currentSemester || $r->current));
    }

    /**
     * Yopilish shakli talab qilgan imtihonlar bugungacha o'tganmi: OSKI → OSKI sanasi, test → test,
     * OSKI+test → ikkalasi; shakl belgilanmagan bo'lsa — jadvaldagi sanalarning barchasi.
     */
    public static function examsFinished(object $r, string $today): bool
    {
        $oski = $r->oski_na ? null : $r->oski_date;
        $test = $r->test_na ? null : $r->test_date;
        $required = match ($r->closing_form) {
            'oski' => ['oski' => $oski],
            'test' => ['test' => $test],
            'oski_test' => ['oski' => $oski, 'test' => $test],
            default => array_filter(['oski' => $oski, 'test' => $test]),
        };

        return $required !== [] && collect($required)->every(fn ($date) => $date && substr((string) $date, 0, 10) <= $today);
    }

    /** Topshirmaganlar uchun — shu guruhda fandan amaliy mashg'ulot o'tgan o'qituvchilar (dars jadvali) */
    private function attachPendingTeachers(Collection $items): void
    {
        $pending = $items->where('status', self::STATUS_NONE);
        if ($pending->isEmpty()) {
            return;
        }
        $names = DB::table('schedules')
            ->whereNull('deleted_at')
            ->whereIn('group_id', $pending->pluck('group_hemis_id')->unique()->values())
            ->whereIn('subject_id', $pending->pluck('subject_id')->unique()->values())
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103])
            ->whereNotNull('employee_name')
            ->select('group_id', 'subject_id', 'employee_name')
            ->distinct()
            ->get()
            ->groupBy(fn ($s) => $s->group_id . '|' . $s->subject_id);
        foreach ($pending as $row) {
            $row->pending_teachers = $names->get($row->group_hemis_id . '|' . $row->subject_id, collect())->pluck('employee_name')->unique()->values();
        }
    }
}

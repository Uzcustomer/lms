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
     * bugungacha o'tgan), tasdiq holati bilan; eng so'nggi imtihon birinchi.
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

        $confirmations = YnQaydnomaConfirmation::with('verification')
            ->whereIn('group_hemis_id', $exams->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->unique()->values())
            ->orderByDesc('confirmed_at')
            ->get()
            ->groupBy(fn ($c) => $c->group_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code);

        return $exams->map(function ($r) use ($confirmations) {
            $all = $confirmations->get($r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code, collect());
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
                'finished_at' => max((string) $r->oski_date, (string) $r->test_date),
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

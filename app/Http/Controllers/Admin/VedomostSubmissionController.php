<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VedomostSubmissionExport;
use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Setting;
use App\Models\VedomostSubmission;
use App\Models\VedomostSubmissionLog;
use App\Services\VedomostMergeService;
use App\Services\VedomostSubmissionNotifier;
use App\Services\VedomostSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VedomostSubmissionController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

    /** Ko'rish (index/show/export/file) — admin + o'quv prorektori. */
    private const VIEW_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'oquv_prorektori'];

    /** Rad etilgan vedomostni qayta yuklashga ruxsat bera oladigan rollar. */
    private const REUPLOAD_PERMIT_ROLES = ['superadmin', 'oquv_prorektori'];

    /** Telegram/bildirishnoma toggle'ini boshqara oladigan rollar (admin). */
    private const NOTIFY_TOGGLE_ROLES = ['superadmin', 'admin', 'kichik_admin'];

    /** Saralash mumkin bo'lgan ustunlar. */
    private const SORTABLE = [
        'group' => 'vs.group_name',
        'subject' => 'vs.subject_name',
        'department' => 'vs.department_name',
        'teacher' => 'vs.teacher_name',
        'closing_form' => 'vs.closing_form',
        'semester' => 'vs.semester_code',
        'base_date' => 'vs.base_date',
        'deadline' => 'vs.deadline',
        'status' => 'vs.status',
    ];

    public function __construct(
        private VedomostSubmissionService $service,
        private VedomostSubmissionNotifier $notifier,
        private VedomostMergeService $merge,
        private \App\Services\VedomostRejectionInbox $rejectionInbox
    ) {
    }

    /**
     * Jamlangan qatorlarni saralash (DB emas, PHP darajasida — birlashtirishdan keyin).
     */
    private function sortAggregated(Collection $rows, Request $request): Collection
    {
        $sort = $request->get('sort');
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        $map = [
            'group' => 'group_name', 'subject' => 'subject_name',
            'department' => 'department_name', 'teacher' => 'teacher_name',
            'closing_form' => 'closing_form', 'semester' => 'semester_code',
            'base_date' => 'base_date', 'deadline' => 'deadline', 'status' => 'status',
        ];

        if (isset($map[$sort])) {
            $col = $map[$sort];
            $sorted = $rows->sortBy(fn($r) => (string) ($r->{$col} ?? ''), SORT_NATURAL | SORT_FLAG_CASE, $dir === 'desc');
            return $sorted->values();
        }

        // Default: muddat (bo'shlar oxirida), keyin guruh, fan.
        return $rows->sortBy(fn($r) => [
            $r->deadline === null ? 1 : 0,
            (string) $r->deadline,
            (string) $r->group_name,
            (string) $r->subject_name,
        ])->values();
    }

    private function checkAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        $activeRole = session('active_role', '');
        if (!in_array($activeRole, self::ALLOWED_ROLES, true)) {
            abort(403, "Vedomost hisobotini faqat admin va registrator ofisi ko'ra oladi.");
        }
    }

    /**
     * Ko'rish huquqi — admin/registrator + o'quv prorektori (faqat o'qish va
     * "qayta yuklashga ruxsat" amali uchun).
     */
    private function checkViewAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::VIEW_ROLES, true)) {
            abort(403, "Bu sahifani ko'rish huquqi yo'q.");
        }
    }

    /**
     * Rad etilgan vedomostni qayta yuklashga ruxsat berish huquqi — o'quv prorektori.
     */
    private function checkReuploadPermitAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::REUPLOAD_PERMIT_ROLES, true)) {
            abort(403, "Qayta yuklashga ruxsatni faqat o'quv prorektori bera oladi.");
        }
    }

    private function resolveSelectedEducationType(Request $request, $educationTypes)
    {
        if ($request->has('education_type')) {
            return $request->get('education_type');
        }

        return $educationTypes
            ->first(fn($t) => str_contains(mb_strtolower($t->education_type_name ?? ''), 'bakalavr'))
            ?->education_type_code;
    }

    /**
     * Filtrlangan so'rov (hisobot va Excel eksport bitta mantiqdan foydalanadi).
     */
    private function filteredQuery(Request $request)
    {
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();
        $selectedEducationType = $this->resolveSelectedEducationType($request, $educationTypes);

        $query = DB::table('vedomost_submissions as vs')
            // MUHIM: faqat FAOL guruhlar — vedomost faol guruh uchun olinadi.
            ->join('groups as g', function ($join) {
                $join->on('g.group_hemis_id', '=', 'vs.group_hemis_id')
                    ->where('g.active', true);
            })
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'vs.curriculum_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'vs.curriculum_hemis_id')
                    ->on('s.code', '=', 'vs.semester_code');
            })
            ->select('vs.*', 'f.name as faculty_name', 's.level_name', 's.level_code');

        if ($selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
        }
        if ($request->filled('specialty')) {
            $query->where('vs.specialty_name', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('vs.semester_code', $request->semester_code);
        }
        if ($request->filled('subject_name')) {
            $query->where('vs.subject_name', 'like', '%' . $request->subject_name . '%');
        }
        if ($request->filled('closing_form_filter')) {
            $query->where('vs.closing_form', $request->closing_form_filter);
        }
        if ($request->filled('form_type')) {
            $query->where('vs.form_type', $request->form_type);
        }
        if ($request->filled('status')) {
            $query->where('vs.status', $request->status);
        }
        if ($request->boolean('overdue')) {
            $query->whereNotNull('vs.deadline')
                ->whereDate('vs.deadline', '<', now()->toDateString())
                ->where('vs.status', '!=', VedomostSubmission::STATUS_APPROVED);
        }

        // Saralash
        $sort = $request->get('sort');
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        if (isset(self::SORTABLE[$sort])) {
            $query->orderBy(self::SORTABLE[$sort], $dir);
        } else {
            $query->orderByRaw('vs.deadline IS NULL')
                ->orderBy('vs.deadline')
                ->orderBy('vs.group_name')
                ->orderBy('vs.subject_name');
        }

        return [$query, $educationTypes, $selectedEducationType];
    }

    public function index(Request $request)
    {
        $this->checkViewAccess();

        [$query, $educationTypes, $selectedEducationType] = $this->filteredQuery($request);

        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Yo'nalishlar — nom bo'yicha distinct (bir xil nomlilar bitta ko'rinadi).
        // Tanlangan ta'lim turi/fakultetga qarab cheklanadi.
        $specialtyQuery = DB::table('vedomost_submissions as vs')
            ->join('groups as g', function ($join) {
                $join->on('g.group_hemis_id', '=', 'vs.group_hemis_id')
                    ->where('g.active', true);
            })
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'vs.curriculum_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->whereNotNull('vs.specialty_name')
            ->where('vs.specialty_name', '!=', '');
        if ($selectedEducationType) {
            $specialtyQuery->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $specialtyQuery->where('f.id', $request->faculty);
        }
        $specialties = $specialtyQuery->distinct()
            ->orderBy('vs.specialty_name')
            ->pluck('vs.specialty_name');

        $closingForms = [
            'oski' => 'Faqat OSKI',
            'test' => 'Faqat Test',
            'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ',
            'sinov' => 'Sinov (test)',
        ];
        $formLabels = VedomostSubmission::formLabels();

        // Filtrlangan yozuvlarni o'zak guruh × o'zak fan bo'yicha jamlaymiz —
        // guruhcha (a/b/c) va fan variant harflari kesilib, bitta vedomost qatori bo'ladi.
        $aggregated = $this->sortAggregated($this->merge->aggregate($query->get()), $request);

        // Status statistikasi — jamlangan (o'zak) vedomostlar bo'yicha, jadval bilan mos.
        $stats = $aggregated->groupBy('status')->map->count()->all();

        $perPage = (int) $request->get('per_page', 50);
        $page = max(1, (int) $request->get('page', 1));
        $submissions = new LengthAwarePaginator(
            $aggregated->forPage($page, $perPage)->values(),
            $aggregated->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $notifyEnabled = VedomostSubmissionNotifier::enabled();
        $canToggleNotify = in_array(session('active_role', ''), self::NOTIFY_TOGGLE_ROLES, true);
        $syncProgress = Cache::get('vedomost_submission_sync_progress', ['status' => 'idle']);

        return view('admin.vedomost-submission.index', compact(
            'submissions',
            'faculties',
            'specialties',
            'educationTypes',
            'selectedEducationType',
            'closingForms',
            'formLabels',
            'stats',
            'notifyEnabled',
            'canToggleNotify',
            'syncProgress'
        ));
    }

    /** Davr ichidagi harakat turlari (audit jurnali action -> yorliq). */
    private const REPORT_ACTIONS = [
        'upload'          => 'Topshirildi',
        'review'          => 'Tekshirishga olindi',
        'approve'         => 'Tasdiqlandi',
        'reject'          => 'Rad etildi',
        'reupload_permit' => 'Qayta ruxsat',
    ];

    /**
     * Svodnaya hisobot — tanlangan kesimlar (fakultet/kurs/kafedra/fan/guruh)
     * bo'yicha IERARXIK qatorlar. Sana oralig'isiz — shakl (12/12a/12b) × status.
     * Sana oralig'i berilsa — "Davri boshiga / Davri ichida / Davri oxiriga"
     * (qoldiq–harakat–qoldiq) ko'rinishi. Har ustun bo'yicha sort qilinadi.
     */
    public function report(Request $request)
    {
        $this->checkViewAccess();

        [$query] = $this->filteredQuery($request);

        // Mavjud kesimlar: yorliq + jamlangan qatordagi maydon nomi.
        $dimensions = [
            'faculty'    => ['label' => 'Fakultet', 'field' => 'faculty_name'],
            'level'      => ['label' => 'Kurs',     'field' => 'level_name'],
            'department' => ['label' => 'Kafedra',  'field' => 'department_name'],
            'subject'    => ['label' => 'Fan',      'field' => 'subject_name'],
            'group'      => ['label' => 'Guruh',    'field' => 'group_name'],
        ];

        // Tanlangan kesimlar — tartibli ro'yxat (dims=department,subject -> Kafedra > Fan).
        $selectedDims = collect(explode(',', (string) $request->get('dims', 'faculty')))
            ->map(fn($d) => trim($d))
            ->filter(fn($d) => isset($dimensions[$d]))
            ->unique()->values()->all();
        if (empty($selectedDims)) {
            $selectedDims = ['faculty'];
        }
        $availableDims = array_values(array_diff(array_keys($dimensions), $selectedDims));

        $statuses = VedomostSubmission::statusLabels();  // pending..rejected
        $statusColor = [
            'pending'   => ['#475569', '#f1f5f9'],
            'received'  => ['#1d4ed8', '#dbeafe'],
            'reviewing' => ['#b45309', '#fef3c7'],
            'approved'  => ['#166534', '#dcfce7'],
            'rejected'  => ['#b91c1c', '#fee2e2'],
        ];

        // Index bilan bir xil — o'zak guruh × o'zak fan bo'yicha jamlangan qatorlar.
        $aggregated = $this->merge->aggregate($query->get());

        // Sana oralig'i (ikkalasi ham berilsa — davr rejimi).
        [$from, $to] = $this->reportDateRange($request);
        $dateMode = $from && $to;

        // Ustun bo'limlari ($sections) va har varaq uchun ustun hissasi ($contribById).
        if ($dateMode) {
            [$sections, $contribById] = $this->reportDateSections($aggregated, $from, $to, $statuses, $statusColor);
            $showGrand = false;
        } else {
            [$sections, $contribById] = $this->reportFormSections($aggregated, $statuses, $statusColor);
            $showGrand = true;
        }

        // Ierarxik daraxt + har bir ustun bo'yicha umumiy "Jami" (tfoot).
        $dimFields = array_map(fn($d) => $dimensions[$d]['field'], $selectedDims);
        $tree = $this->buildReportTree($aggregated, $dimFields, $contribById);

        $totalMetrics = [];
        foreach ($contribById as $contrib) {
            foreach ($contrib as $k => $c) {
                $totalMetrics[$k] = ($totalMetrics[$k] ?? 0) + $c;
            }
        }

        // Sort: label | "{section}|{col}" | "{section}|__total" | __grand
        $sortCol = $request->get('rsort', 'label');
        $sortDir = $request->get('rdir') === 'desc' ? 'desc' : 'asc';
        $sectionKeys = array_map(fn($s) => $s['key'], $sections);
        $this->sortReportNodes($tree, $sortCol, $sortDir, $sectionKeys);

        $rows = [];
        $this->flattenReportTree($tree, 0, $rows);

        return view('admin.vedomost-submission.report', compact(
            'rows', 'dimensions', 'selectedDims', 'availableDims',
            'sections', 'totalMetrics', 'showGrand', 'sortCol', 'sortDir', 'from', 'to'
        ));
    }

    /** Hisobot sana oralig'i: [Carbon|null start, Carbon|null end]. */
    private function reportDateRange(Request $request): array
    {
        $parse = function ($v) {
            $v = trim((string) $v);
            if ($v === '') {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($v);
            } catch (\Throwable $e) {
                return null;
            }
        };
        $from = $parse($request->get('from'));
        $to   = $parse($request->get('to'));

        return [$from?->startOfDay(), $to?->endOfDay()];
    }

    /**
     * Sana oralig'isiz rejim — shakl (12/12a/12b) × status bo'limlari.
     * @return array{0: array, 1: array}  [$sections, $contribById]
     */
    private function reportFormSections(iterable $aggregated, array $statuses, array $statusColor): array
    {
        $forms = VedomostSubmission::formLabels();
        $formTint = [
            '12' => '#eef2ff', '12q' => '#e0e7ff',
            '12a' => '#ecfeff', '12aq' => '#fef3c7', '12ag' => '#fde68a',
            '12b' => '#fef2f2', '12bq' => '#fae8ff', '12bg' => '#fed7aa',
        ];

        $sections = [];
        foreach ($forms as $fkey => $flabel) {
            $cols = [];
            foreach ($statuses as $st => $slabel) {
                $cols[] = ['key' => "$fkey|$st", 'label' => $slabel, 'color' => $statusColor[$st] ?? null];
            }
            $sections[] = ['key' => $fkey, 'label' => $flabel, 'cols' => $cols, 'tint' => $formTint[$fkey] ?? '#f8fafc'];
        }

        $contribById = [];
        foreach ($aggregated as $v) {
            $form = isset($forms[$v->form_type]) ? $v->form_type : VedomostSubmission::FORM_12;
            $contribById[$v->id] = ["$form|$v->status" => 1];
        }

        return [$sections, $contribById];
    }

    /**
     * Sana rejimi — "Davri boshiga / Davri ichida / Davri oxiriga" bo'limlari.
     * Har varaqning holati audit jurnalidan (rep yozuv loglari) tiklanadi.
     * @return array{0: array, 1: array}  [$sections, $contribById]
     */
    private function reportDateSections(iterable $aggregated, \Carbon\Carbon $from, \Carbon\Carbon $to, array $statuses, array $statusColor): array
    {
        $aggregated = collect($aggregated);
        $repIds = $aggregated->pluck('id')->all();

        $logsById = VedomostSubmissionLog::whereIn('vedomost_submission_id', $repIds)
            ->orderBy('created_at')->orderBy('id')
            ->get(['vedomost_submission_id', 'action', 'from_status', 'to_status', 'created_at'])
            ->groupBy('vedomost_submission_id');

        $contribById = [];
        foreach ($aggregated as $v) {
            $logs = $logsById->get($v->id) ?? collect();
            $initial = $logs->isNotEmpty() ? ($logs->first()->from_status ?: VedomostSubmission::STATUS_PENDING) : VedomostSubmission::STATUS_PENDING;
            $created = $v->created_at ? \Carbon\Carbon::parse($v->created_at) : null;

            $contrib = [];

            // Davri boshiga — davr boshlanishidan oldingi holat (varaq o'shanda mavjud bo'lsa).
            if ($created === null || $created < $from) {
                $st = $this->statusAt($logs, $from, false, $initial);
                $contrib["open|$st"] = ($contrib["open|$st"] ?? 0) + 1;
            }

            // Davri ichida — oraliqda qilingan harakatlar (loglar).
            foreach ($logs as $l) {
                $at = \Carbon\Carbon::parse($l->created_at);
                if ($at >= $from && $at <= $to && isset(self::REPORT_ACTIONS[$l->action])) {
                    $key = "period|{$l->action}";
                    $contrib[$key] = ($contrib[$key] ?? 0) + 1;
                }
            }

            // Davri oxiriga — davr oxiridagi holat (varaq o'shanda mavjud bo'lsa).
            if ($created === null || $created <= $to) {
                $st = $this->statusAt($logs, $to, true, $initial);
                $contrib["close|$st"] = ($contrib["close|$st"] ?? 0) + 1;
            }

            $contribById[$v->id] = $contrib;
        }

        $statusCols = function (string $prefix) use ($statuses, $statusColor) {
            $cols = [];
            foreach ($statuses as $st => $slabel) {
                $cols[] = ['key' => "$prefix|$st", 'label' => $slabel, 'color' => $statusColor[$st] ?? null];
            }
            return $cols;
        };
        $actionCols = [];
        $actionColor = [
            'upload' => ['#1d4ed8', '#dbeafe'], 'review' => ['#b45309', '#fef3c7'],
            'approve' => ['#166534', '#dcfce7'], 'reject' => ['#b91c1c', '#fee2e2'],
            'reupload_permit' => ['#475569', '#f1f5f9'],
        ];
        foreach (self::REPORT_ACTIONS as $act => $label) {
            $actionCols[] = ['key' => "period|$act", 'label' => $label, 'color' => $actionColor[$act] ?? null];
        }

        $sections = [
            ['key' => 'open',   'label' => 'Davri boshiga',            'cols' => $statusCols('open'),  'tint' => '#eef2ff'],
            ['key' => 'period', 'label' => 'Davri ichida (harakatlar)', 'cols' => $actionCols,          'tint' => '#ecfeff'],
            ['key' => 'close',  'label' => 'Davri oxiriga',            'cols' => $statusCols('close'), 'tint' => '#fef2f2'],
        ];

        return [$sections, $contribById];
    }

    /**
     * Saralangan loglar (created_at asc) bo'yicha berilgan kesim vaqtidagi status.
     * @param  bool  $inclusive  cutoff vaqtning o'zi kiritiladimi (<=) yoki yo'q (<).
     */
    private function statusAt(Collection $logs, \Carbon\Carbon $cutoff, bool $inclusive, string $initial): string
    {
        $status = $initial;
        foreach ($logs as $l) {
            $at = \Carbon\Carbon::parse($l->created_at);
            $within = $inclusive ? $at <= $cutoff : $at < $cutoff;
            if (!$within) {
                break;
            }
            if ($l->to_status) {
                $status = $l->to_status;
            }
        }
        return $status;
    }

    /**
     * Jamlangan qatorlardan ierarxik daraxt quradi. Har tugun avlodlari
     * hissasi yig'indisi bo'lgan metrikani (metrics[ustun_kaliti] => son) saqlaydi.
     *
     * @param  array  $dimFields    tartibli kesim maydonlari (qolgan darajalar)
     * @param  array  $contribById  vedomost id => [ustun_kaliti => son]
     */
    private function buildReportTree(iterable $rows, array $dimFields, array $contribById): array
    {
        if (empty($dimFields)) {
            return [];
        }
        $field = $dimFields[0];
        $rest  = array_slice($dimFields, 1);

        $groups = collect($rows)->groupBy(
            fn($v) => trim((string) ($v->{$field} ?? '')) ?: '— (aniqlanmagan)'
        );

        $nodes = [];
        foreach ($groups as $label => $groupRows) {
            $metrics = [];
            foreach ($groupRows as $v) {
                foreach (($contribById[$v->id] ?? []) as $k => $c) {
                    $metrics[$k] = ($metrics[$k] ?? 0) + $c;
                }
            }
            $nodes[] = [
                'label'    => (string) $label,
                'metrics'  => $metrics,
                'children' => $this->buildReportTree($groupRows, $rest, $contribById),
            ];
        }

        return $nodes;
    }

    /**
     * Tanlangan ustun bo'yicha har bir darajadagi "aka-uka" tugunlarni saralaydi.
     */
    private function sortReportNodes(array &$nodes, string $sortCol, string $sortDir, array $sectionKeys): void
    {
        if (empty($nodes)) {
            return;
        }
        $factor = $sortDir === 'desc' ? -1 : 1;

        $metric = function (array $node) use ($sortCol, $sectionKeys) {
            if ($sortCol === '__grand') {
                return array_sum($node['metrics']);
            }
            if (str_contains($sortCol, '|')) {
                [$sec, $col] = explode('|', $sortCol, 2);
                if ($col === '__total') {
                    $sum = 0;
                    foreach ($node['metrics'] as $k => $c) {
                        if (str_starts_with($k, "$sec|")) {
                            $sum += $c;
                        }
                    }
                    return $sum;
                }
                return $node['metrics'][$sortCol] ?? 0;
            }
            return 0;
        };

        usort($nodes, function ($a, $b) use ($sortCol, $factor, $metric) {
            if ($sortCol === 'label') {
                return strnatcasecmp($a['label'], $b['label']) * $factor;
            }
            $cmp = $metric($a) <=> $metric($b);
            return ($cmp !== 0 ? $cmp * $factor : strnatcasecmp($a['label'], $b['label']));
        });

        foreach ($nodes as &$node) {
            $this->sortReportNodes($node['children'], $sortCol, $sortDir, $sectionKeys);
        }
    }

    /**
     * Daraxtni depth bilan tekis qatorlar massiviga yoyadi.
     */
    private function flattenReportTree(array $nodes, int $depth, array &$out): void
    {
        foreach ($nodes as $node) {
            $out[] = [
                'label'        => $node['label'],
                'depth'        => $depth,
                'metrics'      => $node['metrics'],
                'has_children' => !empty($node['children']),
            ];
            $this->flattenReportTree($node['children'], $depth + 1, $out);
        }
    }

    public function export(Request $request)
    {
        $this->checkViewAccess();

        [$query] = $this->filteredQuery($request);

        $closingForms = [
            'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
        ];
        $statusLabels = VedomostSubmission::statusLabels();
        $today = now()->toDateString();

        // Index bilan bir xil — o'zak guruh × o'zak fan bo'yicha jamlangan qatorlar.
        $aggregated = $this->sortAggregated($this->merge->aggregate($query->get()), $request);

        $rows = [];
        $i = 1;
        foreach ($aggregated as $v) {
            $overdue = $v->deadline && $v->deadline < $today && $v->status !== VedomostSubmission::STATUS_APPROVED;
            $rows[] = [
                $i++,
                $v->faculty_name,
                $v->group_name,
                VedomostSubmission::formLabel($v->form_type ?? null),
                $v->specialty_name,
                $v->subject_name,
                $v->department_name,
                $v->teacher_name,
                $v->teacher_phone,
                $v->fan_masuli_name,
                $v->fan_masuli_phone,
                $v->kafedra_mudiri_name,
                $v->kafedra_mudiri_phone,
                $closingForms[$v->closing_form] ?? $v->closing_form,
                $v->base_date ? date('d.m.Y', strtotime($v->base_date)) : '',
                $v->deadline ? date('d.m.Y', strtotime($v->deadline)) : '',
                $overdue ? 'Ha' : '',
                $statusLabels[$v->status] ?? $v->status,
            ];
        }

        $fileName = 'vedomost-topshirish-' . now()->format('Y-m-d_His') . '.xlsx';

        return (new VedomostSubmissionExport($rows))->download($fileName);
    }

    /**
     * Joriy semestr bo'yicha vedomost yozuvlarini generatsiya/yangilash.
     */
    public function sync(Request $request)
    {
        $this->checkAccess();

        if (Cache::has('vedomost_submission_sync_lock')) {
            return redirect()
                ->route('admin.vedomost-submission.index', $request->query())
                ->with('error', "Joriy semester bo'yicha yangilash allaqachon ishlamoqda.");
        }

        Cache::put('vedomost_submission_sync_progress', [
            'status' => 'queued',
            'message' => "Joriy semester bo'yicha yangilash navbatga qo'yildi.",
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        \App\Jobs\SyncVedomostSubmissionsJob::dispatch();

        return redirect()
            ->route('admin.vedomost-submission.index', $request->query())
            ->with('success', "Joriy semester bo'yicha yangilash fon rejimida boshlandi.");
    }

    public function syncProgress(): JsonResponse
    {
        $this->checkViewAccess();

        return response()->json(
            Cache::get('vedomost_submission_sync_progress', ['status' => 'idle'])
        );
    }

    /**
     * Telegram/bildirishnoma yuborishni yoqish/o'chirish (faqat admin).
     */
    public function toggleNotify(Request $request)
    {
        $this->checkAccess();
        if (!in_array(session('active_role', ''), self::NOTIFY_TOGGLE_ROLES, true)) {
            abort(403, 'Bu sozlamani faqat admin o\'zgartira oladi.');
        }

        $enabled = $request->boolean('enabled');
        Setting::set('vedomost_notify_enabled', $enabled ? '1' : '0');

        return redirect()
            ->route('admin.vedomost-submission.index', $request->except(['enabled', '_token']))
            ->with('success', $enabled
                ? 'Telegram/bildirishnoma yuborish YOQILDI.'
                : "Telegram/bildirishnoma yuborish O'CHIRILDI (test rejimi).");
    }

    public function show($id)
    {
        $this->checkViewAccess();
        $submission = VedomostSubmission::with('logs')->findOrFail($id);
        $aiConfigured = \App\Services\VedomostAiChecker::isConfigured();

        // Birlashtirilgan (o'zak guruh × o'zak fan) ko'rinish — guruhchalar va
        // ularning o'qituvchilari jamlangan holda ko'rsatish uchun.
        $merged = $this->merge->aggregate($this->merge->siblingsOf($submission))->first();

        // O'quv prorektori rad etilgan vedomostni ochsa — inboxda "o'qilgan" bo'ladi.
        if ($submission->status === VedomostSubmission::STATUS_REJECTED
            && in_array(session('active_role', ''), self::REUPLOAD_PERMIT_ROLES, true)) {
            $this->rejectionInbox->markRead($submission, auth()->user());
        }

        return view('admin.vedomost-submission.show', compact('submission', 'aiConfigured', 'merged'));
    }

    /**
     * Yuklangan vedomostni Claude API orqali tizim ma'lumotiga solishtirib tekshirish.
     * Faqat tavsiya — yakuniy qarorni registrator qiladi. Async (Job).
     */
    public function aiCheck($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!\App\Services\VedomostAiChecker::isConfigured()) {
            return back()->with('error', "AI tekshiruv sozlanmagan (ANTHROPIC_API_KEY yo'q).");
        }
        if (!$v->pdf_path) {
            return back()->with('error', 'Avval skaner (PDF) yuklang.');
        }
        if ($v->ai_check_status === 'running' || $v->ai_check_status === 'queued') {
            return back()->with('error', 'AI tekshiruv allaqachon ishlamoqda.');
        }

        $v->update(['ai_check_status' => 'queued', 'ai_error' => null]);
        \App\Jobs\CheckVedomostSubmissionWithAi::dispatch($v->id);

        return back()->with('success', 'AI tekshiruv boshlandi. Natija bir necha daqiqada tayyor bo\'ladi (sahifani yangilash shart emas).');
    }

    /**
     * AI tekshiruv holatini JSON qaytaradi (show sahifasidagi jonli progress uchun).
     */
    public function aiStatus($id)
    {
        $this->checkViewAccess();
        $v = VedomostSubmission::findOrFail($id);

        return response()->json([
            'status' => $v->ai_check_status,
            'verdict' => $v->ai_verdict,
            'error' => $v->ai_error,
        ]);
    }

    /**
     * Skaner qilingan vedomostni yuklash (PDF majburiy, Excel ixtiyoriy). Status -> qabul qilindi.
     */
    public function uploadFiles(Request $request, $id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        // Pending (hali yuklanmagan) yoki received (tekshirishga olinmagan) — erkin
        // yuklash/almashtirish mumkin. Rad etilgan (rejected) bo'lsa — faqat o'quv
        // prorektori "qayta yuklashga ruxsat" bergan bo'lsa. Tekshirilmoqda yoki
        // tasdiqlangan bo'lsa — umuman mumkin emas.
        if ($v->status === VedomostSubmission::STATUS_REJECTED) {
            if (!$v->reupload_allowed_at) {
                return back()->with('error', "Rad etilgan vedomostni qayta yuklash uchun avval o'quv prorektori ruxsat berishi kerak.");
            }
        } elseif (!in_array($v->status, [
            VedomostSubmission::STATUS_PENDING,
            VedomostSubmission::STATUS_RECEIVED,
        ], true)) {
            return back()->with('error', "Bu vedomost tekshirishga olingan yoki tasdiqlangan — faylni almashtirib bo'lmaydi.");
        }

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'excel' => 'nullable|file|mimes:xlsx,xls|max:20480',
        ], [
            'pdf.required' => 'Skaner qilingan PDF faylni yuklang.',
            'pdf.mimes' => 'PDF fayl PDF formatida bo\'lishi kerak.',
            'excel.mimes' => 'Excel fayl .xlsx yoki .xls formatida bo\'lishi kerak.',
        ]);

        // Bitta o'zak guruh × o'zak fan = bitta vedomost. Yuklash shu guruhdagi
        // barcha guruhcha/variant yozuvlariga birdek qo'llanadi.
        $siblings = $this->merge->siblingsOf($v);

        $dir = "vedomost-submissions/{$v->id}";
        // Eski PDF fayllar (qayta yuklash) — barcha guruhchalardagilarni o'chiramiz.
        foreach ($siblings->pluck('pdf_path')->filter()->unique() as $old) {
            if (Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $update = [
            'pdf_path' => $request->file('pdf')->store($dir, 'public'),
            'uploaded_by' => auth()->id(),
            'uploaded_by_name' => $this->userName(),
            'uploaded_at' => now(),
            'status' => VedomostSubmission::STATUS_RECEIVED,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
            // Qayta yuklash ruxsati bir martalik — yuklab bo'lingach iste'mol qilinadi.
            'reupload_allowed_at' => null,
            'reupload_allowed_by' => null,
            'reupload_allowed_by_name' => null,
            // Fayl almashtirilsa eski AI tekshiruv natijasi eskiradi — tozalaymiz.
            // (ai_check_status NOT NULL, default 'none' — null EMAS!)
            'ai_check_status' => 'none',
            'ai_verdict' => null,
            'ai_summary' => null,
            'ai_result' => null,
            'ai_error' => null,
            'ai_checked_at' => null,
        ];

        // Excel ixtiyoriy — yuklansa, barcha guruhchalardagi eskisini almashtiramiz.
        if ($request->hasFile('excel')) {
            foreach ($siblings->pluck('excel_path')->filter()->unique() as $old) {
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $update['excel_path'] = $request->file('excel')->store($dir, 'public');
        }

        $from = $v->status;
        foreach ($siblings as $sib) {
            $sib->update($update);
        }
        $v->refresh();

        $this->log($v, 'upload', $from, $v->status);

        // Received -> received (faqat fayl almashtirish) bo'lsa, qayta xabar bermaymiz.
        $isReplace = $from === VedomostSubmission::STATUS_RECEIVED;
        if (!$isReplace) {
            $this->notifier->notifyStatusChange($v);
        }

        return back()->with('success', $isReplace
            ? 'Fayl almashtirildi (vedomost hali tekshirishga olinmagan).'
            : 'Vedomost yuklandi va tekshirishga qabul qilindi.');
    }

    public function review($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if ($v->status !== VedomostSubmission::STATUS_RECEIVED) {
            return back()->with('error', "Faqat qabul qilingan vedomostni tekshirishga olish mumkin.");
        }

        $from = $v->status;
        foreach ($this->merge->siblingsOf($v) as $sib) {
            $sib->update(['status' => VedomostSubmission::STATUS_REVIEWING]);
        }
        $v->refresh();
        $this->log($v, 'review', $from, $v->status);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost tekshirishga olindi.');
    }

    public function approve($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!in_array($v->status, [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING], true)) {
            return back()->with('error', "Bu vedomostni tasdiqlab bo'lmaydi.");
        }

        $from = $v->status;
        $approval = [
            'status' => VedomostSubmission::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_by_name' => $this->userName(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ];
        foreach ($this->merge->siblingsOf($v) as $sib) {
            $sib->update($approval);
        }
        $v->refresh();
        $this->log($v, 'approve', $from, $v->status);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost tasdiqlandi.');
    }

    public function reject(Request $request, $id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!in_array($v->status, [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING], true)) {
            return back()->with('error', "Bu vedomostni rad etib bo'lmaydi.");
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:1000',
        ], [
            'rejection_reason.required' => 'Rad etish sababini (xatolarni) kiriting.',
        ]);

        $from = $v->status;
        $rejection = [
            'status' => VedomostSubmission::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_by_name' => $this->userName(),
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ];
        foreach ($this->merge->siblingsOf($v) as $sib) {
            $sib->update($rejection);
        }
        $v->refresh();
        $this->log($v, 'reject', $from, $v->status, $request->rejection_reason);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost rad etildi va tegishli shaxslarga xabar yuborildi.');
    }

    /**
     * Rad etilgan vedomostni qayta yuklashga ruxsat berish (faqat o'quv prorektori).
     * Ruxsat berilgach o'qituvchi/admin faylni qayta yuklay oladi; yuklab bo'lingach
     * ruxsat bir martalik bo'lib iste'mol qilinadi.
     */
    public function allowReupload($id)
    {
        $this->checkReuploadPermitAccess();
        $v = VedomostSubmission::findOrFail($id);

        if ($v->status !== VedomostSubmission::STATUS_REJECTED) {
            return back()->with('error', "Faqat rad etilgan vedomostga qayta yuklash ruxsatini berish mumkin.");
        }
        if ($v->reupload_allowed_at) {
            return back()->with('error', 'Bu vedomostga qayta yuklash ruxsati allaqachon berilgan.');
        }

        $permit = [
            'reupload_allowed_at' => now(),
            'reupload_allowed_by' => auth()->id(),
            'reupload_allowed_by_name' => $this->userName(),
        ];
        foreach ($this->merge->siblingsOf($v) as $sib) {
            $sib->update($permit);
        }
        $v->refresh();
        $this->log($v, 'reupload_permit', $v->status, $v->status);

        return back()->with('success', "Qayta yuklashga ruxsat berildi. Endi vedomostni qayta yuklash mumkin.");
    }

    public function downloadFile($id, $type)
    {
        $this->checkViewAccess();
        $v = VedomostSubmission::findOrFail($id);

        $path = $type === 'excel' ? $v->excel_path : $v->pdf_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Fayl topilmadi.');

        return Storage::disk('public')->download($path);
    }

    private function userName(): string
    {
        $u = auth()->user();
        return $u->name ?? $u->full_name ?? $u->short_name ?? 'Foydalanuvchi';
    }

    private function log(VedomostSubmission $v, string $action, ?string $from, ?string $to, ?string $note = null): void
    {
        VedomostSubmissionLog::create([
            'vedomost_submission_id' => $v->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'user_id' => auth()->id(),
            'user_name' => $this->userName(),
        ]);
    }
}

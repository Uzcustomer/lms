<?php

namespace App\Http\Controllers\Admin;

use App\Exports\KafedraSubjectReportExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Services\KafedraSubjectReport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Fanlar bo'yicha hisobot (kafedra mudiri): kafedra fanlari → fan guruhlari → guruh talabalari baholari.
 * Kafedra mudiri faqat o'z kafedrasini ko'radi; admin rollari kafedrani tanlaydi.
 */
class KafedraSubjectReportController extends Controller
{
    private const ADMIN_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

    private function role(): string
    {
        return (string) session('active_role', '');
    }

    /** Ko'rilayotgan kafedra (department_hemis_id) va kafedralar ro'yxati (admin uchun). */
    private function context(Request $request): array
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $role = $this->role();
        if ($role === 'kafedra_mudiri') {
            $deptId = method_exists($user, 'headDepartmentHemisId') ? $user->headDepartmentHemisId() : ($user->department_hemis_id ?? null);
            abort_unless($deptId, 403, 'Kafedrangiz aniqlanmadi. Profilingizda kafedra mudiri kafedrasini tekshiring.');

            return [(int) $deptId, collect()];
        }
        abort_unless(in_array($role, self::ADMIN_ROLES, true), 403, 'Bu hisobot kafedra mudiri uchun.');
        $kafedras = Department::where('structure_type_code', '12')->where('active', true)->orderBy('name')->get(['department_hemis_id', 'name']);
        $deptId = (int) $request->input('kafedra') ?: (int) optional($kafedras->first())->department_hemis_id;

        return [$deptId, $kafedras];
    }

    private function period(Request $request): array
    {
        $ok = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;

        return [$ok($request->input('from')), $ok($request->input('to'))];
    }

    private function findUnit(int $deptId, Request $request): object
    {
        $key = (string) $request->input('unit');
        $unit = KafedraSubjectReport::units($deptId)->firstWhere('key', $key);
        abort_unless($unit, 404, 'Fan topilmadi (kafedrangizga tegishli emas yoki joriy semestrda yo\'q).');

        return $unit;
    }

    /** Tanlangan "fakultet / yo'nalish · kurs" (scope=fakultet¦yo'nalish¦kurs) — [fakultet, yo'nalish, kurs] yoki null. */
    private function scope(Request $request): ?array
    {
        $parts = explode('¦', (string) $request->input('scope'));

        return count($parts) === 3 && $parts[0] !== '' ? $parts : null;
    }

    /**
     * "Fakultet / yo'nalish · kurs" tanlov ro'yxati (ikki yengil so'rov):
     * [scopes (kalit => label), fan birligi => guruhlari, guruh => talabalar soni].
     */
    private function scopeList($units): array
    {
        $curricula = $units->flatMap(fn ($u) => array_keys($u->curricula))->unique()->values()->all();
        $allGroups = KafedraSubjectReport::plainGroups($curricula);
        $counts = KafedraSubjectReport::studentCounts($allGroups->pluck('group_hemis_id')->all());
        $byCurriculum = $allGroups->groupBy('curriculum');

        $scopes = [];
        $unitGroups = [];
        foreach ($units as $u) {
            $gs = collect(array_keys($u->curricula))->flatMap(fn ($c) => $byCurriculum->get($c, collect()))
                ->filter(fn ($g) => ($counts[$g->group_hemis_id] ?? 0) > 0)->unique('group_hemis_id')->values();
            $unitGroups[$u->key] = $gs;
            foreach ($gs as $g) {
                $k = KafedraSubjectReport::scopeKey($g->faculty, $u->specialty_name, $u->level_code);
                $scopes[$k] ??= ['label' => ($g->faculty ?: '—') . ' / ' . mb_strtolower($u->specialty_name) . ' · ' . $u->level_name,
                    'sort' => [$g->faculty, $u->level_code, $u->specialty_name]];
            }
        }
        uasort($scopes, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return [$scopes, $unitGroups, $counts];
    }

    /**
     * 1-bosqich: kafedra fanlari. Sahifa ochilganda baholar hisoblanmaydi (tez) — faqat fan,
     * guruh va talaba soni; JN va taqsimot "fakultet / yo'nalish · kurs" tanlanganda hisoblanadi.
     */
    public function index(Request $request)
    {
        [$deptId, $kafedras] = $this->context($request);
        [$from, $to] = $this->period($request);
        $units = KafedraSubjectReport::units($deptId);
        [$scopes, $unitGroups, $counts] = $this->scopeList($units);

        $scope = $this->scope($request);
        if ($scope && !isset($scopes[implode('¦', $scope)])) {
            $scope = null;
        }

        $rows = [];
        foreach ($units as $u) {
            $gs = $unitGroups[$u->key];
            if ($scope) {
                if ($u->specialty_name !== $scope[1] || (string) $u->level_code !== (string) $scope[2]) {
                    continue;
                }
                $gs = $gs->where('faculty', $scope[0])->values();
                if ($gs->isEmpty()) {
                    continue;
                }
            }
            $rows[] = ['unit' => $u, 'groupList' => $gs, 'groups' => $gs->count(),
                'students' => $gs->sum(fn ($g) => $counts[$g->group_hemis_id] ?? 0),
                'avg' => null, 'graded' => null, 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0];
        }

        // Baholar — faqat tanlangan qism uchun
        if ($scope && $rows) {
            $groupIds = collect($rows)->flatMap(fn ($r) => $r['groupList']->pluck('group_hemis_id'))->unique()->values()->all();
            $studentGroup = \Illuminate\Support\Facades\DB::table('students')->whereIn('group_id', $groupIds)
                ->where(fn ($q) => $q->whereNull('student_status_code')->orWhere('student_status_code', '!=', '60'))
                ->pluck('group_id', 'hemis_id')->all();
            $grades = KafedraSubjectReport::grades(array_map('intval', array_keys($studentGroup)),
                collect($rows)->pluck('unit.subject_id')->unique()->all(), collect($rows)->pluck('unit.semester_code')->unique()->all(), $from, $to);
            foreach ($rows as &$r) {
                $ids = $r['groupList']->pluck('group_hemis_id')->flip();
                $jns = [];
                foreach ($studentGroup as $sid => $gid) {
                    if (isset($ids[$gid])) {
                        $jns[] = $grades[(int) $sid][$r['unit']->subject_id]['jn'] ?? null;
                    }
                }
                $r = array_merge($r, KafedraSubjectReport::stats($jns));
            }
            unset($r);
        }

        return view('admin.kafedra-report.index', [
            'mode' => 'subjects', 'rows' => $rows, 'kafedras' => $kafedras, 'deptId' => $deptId,
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
            'scopes' => $scopes, 'scope' => $scope ? implode('¦', $scope) : null,
        ]);
    }

    /** 2-bosqich: fanni o'qiyotgan guruhlar. */
    public function subject(Request $request)
    {
        [$deptId, $kafedras] = $this->context($request);
        [$from, $to] = $this->period($request);
        $unit = $this->findUnit($deptId, $request);
        $groups = KafedraSubjectReport::groups($unit, $this->scope($request)[0] ?? null);
        $ids = $groups->flatMap(fn ($g) => $g->students->pluck('hemis_id'))->map(fn ($v) => (int) $v)->all();
        $grades = KafedraSubjectReport::grades($ids, [$unit->subject_id], [$unit->semester_code], $from, $to);

        $rows = [];
        foreach ($groups as $g) {
            $sids = $g->students->pluck('hemis_id')->map(fn ($v) => (int) $v);
            $jns = $sids->map(fn ($id) => $grades[$id][$unit->subject_id]['jn'] ?? null)->all();
            $rows[] = ['group' => $g, 'students' => $sids->count(),
                'teacher' => KafedraSubjectReport::teachers($grades, $unit->subject_id, $sids)] + KafedraSubjectReport::stats($jns);
        }

        return view('admin.kafedra-report.index', [
            'mode' => 'groups', 'unit' => $unit, 'rows' => $rows, 'kafedras' => $kafedras, 'deptId' => $deptId,
            'scopes' => $this->scopeList(KafedraSubjectReport::units($deptId))[0],
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
            'scope' => $this->scope($request) ? implode('¦', $this->scope($request)) : null,
        ]);
    }

    /** 3-bosqich: guruh talabalari × sanalar (jurnal kabi, faqat ko'rish). */
    public function group(Request $request)
    {
        [$deptId, $kafedras] = $this->context($request);
        [$from, $to] = $this->period($request);
        $unit = $this->findUnit($deptId, $request);
        $group = KafedraSubjectReport::groups($unit)->firstWhere('group_hemis_id', (int) $request->input('group'));
        abort_unless($group, 404, 'Guruh topilmadi.');
        $ids = $group->students->pluck('hemis_id')->map(fn ($v) => (int) $v)->all();
        $grades = KafedraSubjectReport::grades($ids, [$unit->subject_id], [$unit->semester_code], $from, $to);

        $dates = [];
        $types = [];
        foreach ($ids as $id) {
            foreach (array_keys($grades[$id][$unit->subject_id]['days'] ?? []) as $d) {
                $dates[$d] = true;
            }
            foreach (array_keys($grades[$id][$unit->subject_id]['other'] ?? []) as $t) {
                $types[$t] = true;
            }
        }
        ksort($dates);
        $otherTypes = array_values(array_filter(['MT', 'ON', 'OSKI', 'Test', 'Quiz'], fn ($t) => $t !== 'Quiz' || isset($types[$t])));

        return view('admin.kafedra-report.index', [
            'mode' => 'students', 'unit' => $unit, 'group' => $group, 'grades' => $grades, 'dates' => array_keys($dates),
            'scopes' => $this->scopeList(KafedraSubjectReport::units($deptId))[0],
            'otherTypes' => $otherTypes, 'kafedras' => $kafedras, 'deptId' => $deptId,
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
            'teacher' => KafedraSubjectReport::teachers($grades, $unit->subject_id, $ids),
            'scope' => $this->scope($request) ? implode('¦', $this->scope($request)) : null,
            'stats' => KafedraSubjectReport::stats(array_map(fn ($id) => $grades[$id][$unit->subject_id]['jn'] ?? null, $ids)),
        ]);
    }

    /**
     * Excel — tanlangan "fakultet / yo'nalish · kurs" ning barcha fanlari: har fan alohida varaqda,
     * varaq ichida guruhlar alohida bloklarda (talabalar kunlik baholari bilan).
     */
    public function exportScope(Request $request)
    {
        @ini_set('memory_limit', '768M');
        @set_time_limit(300);
        [$deptId] = $this->context($request);
        [$from, $to] = $this->period($request);
        $scope = $this->scope($request);
        abort_unless($scope, 422, "Avval fakultet / yo'nalish · kursni tanlang.");

        $units = KafedraSubjectReport::units($deptId)
            ->filter(fn ($u) => $u->specialty_name === $scope[1] && (string) $u->level_code === (string) $scope[2])->values();
        $items = [];
        $ids = [];
        foreach ($units as $u) {
            $groups = KafedraSubjectReport::groups($u, $scope[0]);
            if ($groups->isEmpty()) {
                continue;
            }
            $items[] = ['unit' => $u, 'groups' => $groups];
            foreach ($groups as $g) {
                foreach ($g->students as $s) {
                    $ids[(int) $s->hemis_id] = true;
                }
            }
        }
        $grades = KafedraSubjectReport::grades(array_keys($ids), collect($items)->pluck('unit.subject_id')->unique()->all(),
            collect($items)->pluck('unit.semester_code')->unique()->all(), $from, $to);
        $label = $scope[0] . ' / ' . mb_strtolower($scope[1]) . ' · ' . ($units->first()->level_name ?? $scope[2]);
        $file = preg_replace('/[^\pL\pN]+/u', '_', $scope[0] . '_' . $scope[1] . '_' . ($units->first()->level_name ?? $scope[2]));

        return Excel::download(new \App\Exports\KafedraScopeReportExport($items, $grades, $label, $from, $to),
            mb_substr($file, 0, 80) . '_' . now('Asia/Tashkent')->format('Y-m-d') . '.xlsx');
    }

    /** Excel: fan bo'yicha (har guruh alohida varaq) yoki bitta guruh. */
    public function export(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        [$deptId] = $this->context($request);
        [$from, $to] = $this->period($request);
        $unit = $this->findUnit($deptId, $request);
        $groups = KafedraSubjectReport::groups($unit, $this->scope($request)[0] ?? null);
        if ($request->filled('group')) {
            $groups = $groups->where('group_hemis_id', (int) $request->input('group'))->values();
        }
        $ids = $groups->flatMap(fn ($g) => $g->students->pluck('hemis_id'))->map(fn ($v) => (int) $v)->all();
        $grades = KafedraSubjectReport::grades($ids, [$unit->subject_id], [$unit->semester_code], $from, $to);
        $name = preg_replace('/[^\pL\pN]+/u', '_', $unit->subject_name);

        return Excel::download(new KafedraSubjectReportExport($unit, $groups, $grades, $from, $to),
            mb_substr($name, 0, 60) . '_' . now('Asia/Tashkent')->format('Y-m-d') . '.xlsx');
    }
}

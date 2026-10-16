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

    /** 1-bosqich: kafedra fanlari va ko'rsatkichlari. */
    public function index(Request $request)
    {
        [$deptId, $kafedras] = $this->context($request);
        [$from, $to] = $this->period($request);
        $units = KafedraSubjectReport::units($deptId);

        // Barcha fanlar uchun guruh/talabalar va baholar bitta so'rovda
        $unitGroups = [];
        $studentIds = [];
        foreach ($units as $u) {
            $unitGroups[$u->key] = KafedraSubjectReport::groups($u);
            foreach ($unitGroups[$u->key] as $g) {
                foreach ($g->students as $s) {
                    $studentIds[(int) $s->hemis_id] = true;
                }
            }
        }
        $grades = KafedraSubjectReport::grades(array_keys($studentIds), $units->pluck('subject_id')->unique()->all(),
            $units->pluck('semester_code')->unique()->all(), $from, $to);

        $rows = [];
        foreach ($units as $u) {
            $jns = [];
            $students = 0;
            foreach ($unitGroups[$u->key] as $g) {
                foreach ($g->students as $s) {
                    $students++;
                    $jns[] = $grades[(int) $s->hemis_id][$u->subject_id]['jn'] ?? null;
                }
            }
            $rows[] = ['unit' => $u, 'groups' => $unitGroups[$u->key]->count(), 'students' => $students] + KafedraSubjectReport::stats($jns);
        }

        return view('admin.kafedra-report.index', [
            'mode' => 'subjects', 'rows' => $rows, 'kafedras' => $kafedras, 'deptId' => $deptId,
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
        ]);
    }

    /** 2-bosqich: fanni o'qiyotgan guruhlar. */
    public function subject(Request $request)
    {
        [$deptId, $kafedras] = $this->context($request);
        [$from, $to] = $this->period($request);
        $unit = $this->findUnit($deptId, $request);
        $groups = KafedraSubjectReport::groups($unit);
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
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
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
            'otherTypes' => $otherTypes, 'kafedras' => $kafedras, 'deptId' => $deptId,
            'deptName' => Department::where('department_hemis_id', $deptId)->value('name'), 'from' => $from, 'to' => $to,
            'teacher' => KafedraSubjectReport::teachers($grades, $unit->subject_id, $ids),
            'stats' => KafedraSubjectReport::stats(array_map(fn ($id) => $grades[$id][$unit->subject_id]['jn'] ?? null, $ids)),
        ]);
    }

    /** Excel: fan bo'yicha (har guruh alohida varaq) yoki bitta guruh. */
    public function export(Request $request)
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);
        [$deptId] = $this->context($request);
        [$from, $to] = $this->period($request);
        $unit = $this->findUnit($deptId, $request);
        $groups = KafedraSubjectReport::groups($unit);
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

<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Concerns\ComputesStudentDebts;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RetakeApplication;
use App\Models\Specialty;
use App\Services\Retake\RetakeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Qayta o'qish arizasi hisoboti" — fan + ariza semestri kesimida:
 *   - Ariza berib tasdiqlanib guruhga qo'yilganlar (O'tgan/Yiqilgan/Imtihon topshirmagan/Jami)
 *   - Joriy semestrdan qarzdor arizachilar (O'tgan/Yiqilgan/Imtihon topshirmagan/Jami)
 *   - Tasdiqlanish jarayonida (pending)
 *   - Qayta o'qishga ariza bermagan qarzdorlar
 *   - Joriy semestrdan ariza bermagan qarzdorlar
 *
 * Qarzdorlar olami `computeDebtorResults` (academic_records asosida o'tgan
 * semestrlar) orqali aniqlanadi — qarzdorlar hisoboti bilan bir xil mantiq.
 * Imtihon natijasi (O'tgan/Yiqilgan/Imtihon topshirmagan) yopilish shakliga
 * qarab OSKE/TEST/Sinov(test) baholaridan hisoblanadi.
 */
class RetakeApplicationReportController extends Controller
{
    use ComputesStudentDebts;

    public function index(Request $request)
    {
        $this->authorizeAccess();

        return view('teacher.academic-dept.retake-application-report', [
            'educationTypes' => \App\Services\Retake\RetakeFilterCache::educationTypes(),
            'subjects' => \App\Services\Retake\RetakeFilterCache::subjects(),
        ]);
    }

    public function data(Request $request)
    {
        $this->authorizeAccess();

        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $students = $this->studentQuery($request)->get();
        if ($students->isEmpty()) {
            return response()->json(['rows' => [], 'totals' => $this->emptyTotals()]);
        }

        $semNum = fn ($name) => preg_match('/(\d+)/', (string) $name, $m) ? (int) $m[1] : null;

        // Talabaning joriy semestri (raqam) — joriy/o'tgan ajratish uchun.
        $curSem = [];
        foreach ($students as $s) {
            $curSem[(string) $s->hemis_id] = $semNum($s->semester_name ?: $s->semester_code);
        }
        $hemisIds = $students->pluck('hemis_id')->map(fn ($v) => (string) $v)->all();

        $rows = [];
        $rowFor = function ($sid, $sn, $subjName, $semName) use (&$rows) {
            $key = $sid . '|' . $sn;
            if (!isset($rows[$key])) {
                $rows[$key] = $this->newRow($subjName, $semName, $sn);
            }
            return $key;
        };

        // 1) Arizalar — "ariza bergan" ustunlar (A/B/C). To'g'ridan-to'g'ri
        //    arizalardan (imtihondan o'tib qarzdorlar olamidan chiqqanlar ham
        //    "O'tgan" sifatida sanaladi).
        $apps = RetakeApplication::query()
            ->whereIn('student_hemis_id', $hemisIds)
            ->with('retakeGroup')
            ->get();

        $appliedKeys = []; // hid|sid|sn => true (shu fan+semestrdan ariza bergan)
        foreach ($apps as $a) {
            $hid = (string) $a->student_hemis_id;
            $sid = (string) $a->subject_id;
            $sn = $semNum($a->semester_name ?: $a->semester_id);
            if ($sn === null) {
                continue;
            }
            $key = $rowFor($sid, $sn, $a->subject_name, $a->semester_name);
            $appliedKeys[$hid . '|' . $sid . '|' . $sn] = true;
            $isCurrent = ($curSem[$hid] ?? null) === $sn;

            if ($a->final_status === RetakeApplication::STATUS_PENDING) {
                $rows[$key]['in_process']++;
                continue;
            }
            if ($a->final_status !== RetakeApplication::STATUS_APPROVED) {
                continue; // rad etilgan — ustunlarga kirmaydi
            }
            $res = $this->examResult($a);
            $bucket = $isCurrent ? 'current' : 'approved';
            $rows[$key][$bucket][$res]++;
            $rows[$key][$bucket]['jami']++;
        }

        // 2) Qarzdorlar olami (o'tgan semestrlar) — "ariza bermaganlar" (D).
        $debtorResults = $this->computeDebtorResults($students, 1, false, []);
        foreach ($debtorResults as $r) {
            $hid = (string) $r['hemis_id'];
            foreach ($r['debts'] as $d) {
                $sid = (string) $d['subject_id'];
                $sn = $semNum($d['semester_name'] ?: $d['semester_code']);
                if ($sn === null) {
                    continue;
                }
                $key = $rowFor($sid, $sn, $d['subject_name'], $d['semester_name']);
                if (!isset($appliedKeys[$hid . '|' . $sid . '|' . $sn])) {
                    $rows[$key]['not_applied']++;
                    if (($curSem[$hid] ?? null) === $sn) {
                        $rows[$key]['current_not_applied']++;
                    }
                }
            }
        }

        if (empty($rows)) {
            return response()->json(['rows' => [], 'totals' => $this->emptyTotals()]);
        }

        // Tartiblash: semestr → fan.
        $list = array_values($rows);
        usort($list, fn ($a, $b) => [$a['semester_code'], $a['subject_name']] <=> [$b['semester_code'], $b['subject_name']]);

        $totals = $this->emptyTotals();
        $out = [];
        $i = 0;
        foreach ($list as $row) {
            $row['tr'] = ++$i;
            $out[] = $row;
            foreach (['approved', 'current'] as $g) {
                foreach (['pass', 'fail', 'none', 'jami'] as $k) {
                    $totals[$g][$k] += $row[$g][$k];
                }
            }
            $totals['in_process'] += $row['in_process'];
            $totals['not_applied'] += $row['not_applied'];
            $totals['current_not_applied'] += $row['current_not_applied'];
        }

        return response()->json(['rows' => $out, 'totals' => $totals]);
    }

    private function newRow(string $subjectName, ?string $semesterName, int $semesterCode): array
    {
        return [
            'subject_name' => $subjectName,
            'semester_name' => $semesterName ?: ($semesterCode . '-semestr'),
            'semester_code' => $semesterCode,
            'approved' => ['pass' => 0, 'fail' => 0, 'none' => 0, 'jami' => 0],
            'current' => ['pass' => 0, 'fail' => 0, 'none' => 0, 'jami' => 0],
            'in_process' => 0,
            'not_applied' => 0,
            'current_not_applied' => 0,
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'approved' => ['pass' => 0, 'fail' => 0, 'none' => 0, 'jami' => 0],
            'current' => ['pass' => 0, 'fail' => 0, 'none' => 0, 'jami' => 0],
            'in_process' => 0,
            'not_applied' => 0,
            'current_not_applied' => 0,
        ];
    }

    /**
     * Imtihon natijasi: 'pass' (o'tgan), 'fail' (yiqilgan), 'none' (topshirmagan).
     * Yopilish shakliga qarab OSKE/TEST/Sinov(test) baholaridan.
     */
    private function examResult(RetakeApplication $app): string
    {
        $at = $app->retakeGroup?->assessment_type;
        $oske = $app->oske_score !== null ? (float) $app->oske_score : null;
        $test = $app->test_score !== null ? (float) $app->test_score : null;

        if ($at === 'oske') {
            return $oske === null ? 'none' : ($oske >= 60 ? 'pass' : 'fail');
        }
        if ($at === 'test' || $at === 'sinov' || $at === 'sinov_fan') {
            return $test === null ? 'none' : ($test >= 60 ? 'pass' : 'fail');
        }
        if ($at === 'oske_test') {
            if ($oske === null || $test === null) {
                return 'none';
            }
            return ($oske < 60 || $test < 60) ? 'fail' : 'pass';
        }
        // Noma'lum shakl — mavjud bahodan.
        $s = $test ?? $oske;
        return $s === null ? 'none' : ($s >= 60 ? 'pass' : 'fail');
    }

    private function studentQuery(Request $request)
    {
        $q = DB::table('students as s')
            ->whereNotNull('s.curriculum_id')
            ->select('s.hemis_id', 's.full_name', 's.student_id_number', 's.department_name',
                's.specialty_name', 's.level_name', 's.semester_name', 's.semester_code',
                's.group_name', 's.group_id', 's.curriculum_id', 's.image');

        // _retake_filters maydon nomlari bilan bir xil (retake-journal kabi).
        if ($request->filled('education_type')) $q->where('s.education_type_code', $request->education_type);
        if ($request->filled('department')) $q->where('s.department_id', $request->department);
        if ($request->filled('specialty')) $q->where('s.specialty_id', $request->specialty);
        if ($request->filled('level_code')) $q->where('s.level_code', $request->level_code);
        if ($request->filled('semester_code')) $q->where('s.semester_code', $request->semester_code);
        if ($request->filled('group')) $q->where('s.group_id', $request->group);

        return $q;
    }

    private function authorizeAccess(): void
    {
        if (!RetakeAccess::canViewStatistics(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda hisobotni ko\'rish ruxsati yo\'q');
        }
    }
}

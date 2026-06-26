<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Concerns\ComputesStudentDebts;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Services\Retake\RetakeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "Qayta o'qish arizasi hisoboti" — fan + ariza semestri kesimida cross-tab.
 * Qarzdorlar olami ComputesStudentDebts (academic_records) orqali; imtihon
 * natijasi yopilish shakliga qarab OSKE/TEST/Sinov(test) baholaridan.
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

        $r = $this->compute($request);

        return response()->json(['rows' => $r['rows'], 'totals' => $r['totals'], 'current_computed' => $r['current_computed'] ?? true]);
    }

    public function export(Request $request)
    {
        $this->authorizeAccess();
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $r = $this->compute($request);

        $spreadsheet = new Spreadsheet();
        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $r['rows'], $r['totals']);
        $this->buildDetailSheet($spreadsheet->createSheet(), $r['detail']);
        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'qayta_oqish_arizasi_hisoboti_' . now()->format('Ymd_His') . '.xlsx';
        $dir = storage_path('app/public/retake-reports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $fileName;
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Asosiy hisob — summary qatorlar, jami, va tafsilot (talaba darajasi).
     *
     * @return array{rows:array, totals:array, detail:array}
     */
    private function compute(Request $request): array
    {
        $students = $this->studentQuery($request)->get();
        if ($students->isEmpty()) {
            return ['rows' => [], 'totals' => $this->emptyTotals(), 'detail' => []];
        }

        $semNum = fn ($name) => preg_match('/(\d+)/', (string) $name, $m) ? (int) $m[1] : null;

        $stu = [];
        $curSem = [];
        foreach ($students as $s) {
            $hid = (string) $s->hemis_id;
            $stu[$hid] = $s;
            $curSem[$hid] = $semNum($s->semester_name ?: $s->semester_code);
        }
        $hemisIds = array_keys($stu);

        $rows = [];
        $detail = [];
        $rowFor = function ($sid, $sn, $subjName, $semName) use (&$rows) {
            $key = $sid . '|' . $sn;
            if (!isset($rows[$key])) {
                $rows[$key] = $this->newRow($subjName, $semName, $sn);
            }
            return $key;
        };
        $addDetail = function ($hid, $sn, $subjName, $category, $oske = null, $test = null) use (&$detail, $stu) {
            $s = $stu[$hid] ?? null;
            if (!$s) return;
            $detail[] = [
                'full_name' => $s->full_name ?? '-',
                'faculty' => $s->department_name ?? '-',
                'direction' => $s->specialty_name ?? '-',
                'kurs' => $s->level_name ?? '-',
                'semester' => $s->semester_name ?? '-',
                'ariza_semestr' => $sn . '-semestr',
                'group' => $s->group_name ?? '-',
                'subject' => $subjName,
                'category' => $category,
                'oske' => $oske !== null ? (float) $oske : null,
                'test' => $test !== null ? (float) $test : null,
            ];
        };

        // 1) Arizalar — A/B/C ustunlar.
        $apps = RetakeApplication::query()
            ->whereIn('student_hemis_id', $hemisIds)
            ->with('retakeGroup')
            ->get();

        $appliedKeys = [];
        $resLabel = ['pass' => "O'tgan", 'fail' => 'Yiqilgan', 'none' => 'Imtihon topshirmagan'];
        foreach ($apps as $a) {
            $hid = (string) $a->student_hemis_id;
            $sid = (string) $a->subject_id;
            $sn = $semNum($a->semester_name ?: $a->semester_id);
            if ($sn === null) continue;
            $key = $rowFor($sid, $sn, $a->subject_name, $a->semester_name);
            $appliedKeys[$hid . '|' . $sid . '|' . $sn] = true;
            $isCurrent = ($curSem[$hid] ?? null) === $sn;

            if ($a->final_status === RetakeApplication::STATUS_PENDING) {
                $rows[$key]['in_process']++;
                $addDetail($hid, $sn, $a->subject_name, 'Tasdiqlanish jarayonida');
                continue;
            }
            if ($a->final_status !== RetakeApplication::STATUS_APPROVED) {
                continue;
            }
            $res = $this->examResult($a);
            $bucket = $isCurrent ? 'current' : 'approved';
            $rows[$key][$bucket][$res]++;
            $rows[$key][$bucket]['jami']++;
            $prefix = $isCurrent ? 'Joriy semestr' : 'Tasdiqlangan';
            $addDetail($hid, $sn, $a->subject_name, $prefix . ' — ' . $resLabel[$res], $a->oske_score, $a->test_score);
        }

        // 2) Qarzdorlar olami — O'TGAN semestr (academic_records). Barcha talabalar
        //    uchun yengil va ishonchli → "Qayta o'qishga ariza bermaganlar" (D, o'tgan).
        $debtorResults = $this->computeDebtorResults($students, 1, false, []);
        foreach ($debtorResults as $dr) {
            $hid = (string) $dr['hemis_id'];
            foreach ($dr['debts'] as $d) {
                $sid = (string) $d['subject_id'];
                $sn = $semNum($d['semester_name'] ?: $d['semester_code']);
                if ($sn === null) continue;
                $key = $rowFor($sid, $sn, $d['subject_name'], $d['semester_name']);
                if (!isset($appliedKeys[$hid . '|' . $sid . '|' . $sn])) {
                    $rows[$key]['not_applied']++;
                    $addDetail($hid, $sn, $d['subject_name'], 'Ariza bermagan');
                }
            }
        }

        // 3) JORIY semestr qarzlari (jurnal xavflari) — "Joriy semestrdan ariza
        //    bermagan qarzdorlar" (E) + umumiy D. Bu hisob OG'IR (davomat/baholar),
        //    shuning uchun faqat filtr qo'llanganda yoki talaba soni cheklangan
        //    bo'lganda hisoblanadi (aks holda butun universitet bo'yicha timeout).
        $studentSemCodes = [];
        foreach ($students as $s) {
            if ($s->semester_code !== null) {
                $studentSemCodes[(string) $s->hemis_id] = (string) $s->semester_code;
            }
        }
        $hasFilter = collect(['education_type', 'department', 'specialty', 'level_code', 'semester_code', 'group'])
            ->contains(fn ($k) => filled($request->input($k)));
        $currentRisksComputed = false;

        if ($hasFilter || count($hemisIds) <= 2000) {
            $currentRisksMap = [];
            try {
                $currentRisksMap = $this->getCurrentSemesterRisks($hemisIds, $studentSemCodes);
                $currentRisksComputed = true;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[RetakeAppReport] joriy semestr xavflari: ' . $e->getMessage());
            }
            foreach ($currentRisksMap as $hid => $risks) {
                $hid = (string) $hid;
                $sn = $curSem[$hid] ?? null;
                if ($sn === null) continue;
                foreach ($risks as $cr) {
                    $sid = (string) ($cr['subject_id'] ?? '');
                    if ($sid === '') continue;
                    if (isset($appliedKeys[$hid . '|' . $sid . '|' . $sn])) {
                        continue; // ariza bergan — B guruhda hisoblangan
                    }
                    $key = $rowFor($sid, $sn, $cr['subject_name'] ?? '—', $sn . '-semestr');
                    $rows[$key]['not_applied']++;
                    $rows[$key]['current_not_applied']++;
                    $addDetail($hid, $sn, $cr['subject_name'] ?? '—', 'Joriy semestrdan ariza bermagan');
                }
            }
        }

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

        return ['rows' => $out, 'totals' => $totals, 'detail' => $detail, 'current_computed' => $currentRisksComputed];
    }

    private function buildSummarySheet($sheet, array $rows, array $totals): void
    {
        $sheet->setTitle('Hisobot');
        // Sarlavhalar (ikki qator, birlashtirilgan).
        $sheet->setCellValue('A1', 'T/R');
        $sheet->setCellValue('B1', 'Fan');
        $sheet->setCellValue('C1', 'Ariza semestri');
        $sheet->setCellValue('D1', 'Ariza berib tasdiqlanib guruhga qo\'yilganlar');
        $sheet->setCellValue('H1', 'Joriy semestrdan qarzdorlar ariza berganlar');
        $sheet->setCellValue('L1', 'Ariza berib, tasdiqlanish jarayonida');
        $sheet->setCellValue('M1', 'Qayta o\'qishga ariza bermaganlar');
        $sheet->setCellValue('N1', 'Joriy semestrdan ariza bermagan qarzdorlar');
        foreach (['A', 'B', 'C', 'L', 'M', 'N'] as $col) {
            $sheet->mergeCells($col . '1:' . $col . '2');
        }
        $sheet->mergeCells('D1:G1');
        $sheet->mergeCells('H1:K1');
        $sub = ['D' => "O'tgan", 'E' => 'Yiqilgan', 'F' => 'Imtihon topshirmagan', 'G' => 'Jami',
                'H' => "O'tgan", 'I' => 'Yiqilgan', 'J' => 'Imtihon topshirmagan', 'K' => 'Jami'];
        foreach ($sub as $col => $label) {
            $sheet->setCellValue($col . '2', $label);
        }

        $row = 3;
        foreach ($rows as $r) {
            $sheet->fromArray([
                $r['tr'], $r['subject_name'], $r['semester_name'],
                $r['approved']['pass'], $r['approved']['fail'], $r['approved']['none'], $r['approved']['jami'],
                $r['current']['pass'], $r['current']['fail'], $r['current']['none'], $r['current']['jami'],
                $r['in_process'], $r['not_applied'], $r['current_not_applied'],
            ], null, 'A' . $row, true);
            $row++;
        }
        // JAMI
        $sheet->setCellValue('A' . $row, 'JAMI');
        $sheet->mergeCells('A' . $row . ':C' . $row);
        $sheet->fromArray([
            $totals['approved']['pass'], $totals['approved']['fail'], $totals['approved']['none'], $totals['approved']['jami'],
            $totals['current']['pass'], $totals['current']['fail'], $totals['current']['none'], $totals['current']['jami'],
            $totals['in_process'], $totals['not_applied'], $totals['current_not_applied'],
        ], null, 'D' . $row, true);

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:N2')->getFont()->setBold(true);
        $sheet->getStyle('A1:N2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:N2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CFE0F3');
        $sheet->getStyle('A' . $row . ':N' . $row)->getFont()->setBold(true);
    }

    private function buildDetailSheet($sheet, array $detail): void
    {
        $sheet->setTitle('Tafsilot');
        $headings = ['F.I.Sh', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Ariza semestri', 'Guruh', 'Fan', 'Holat', 'OSKE', 'TEST'];
        $sheet->fromArray($headings, null, 'A1', true);
        $row = 2;
        foreach ($detail as $d) {
            $sheet->fromArray([
                $d['full_name'], $d['faculty'], $d['direction'], $d['kurs'], $d['semester'],
                $d['ariza_semestr'], $d['group'], $d['subject'], $d['category'],
                $d['oske'], $d['test'],
            ], null, 'A' . $row, true);
            $row++;
        }
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CFE0F3');
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

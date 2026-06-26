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

    /**
     * Progressiv (bo'lakli) hisob — timeout'siz.
     *
     * Birinchi so'rov (token yo'q): yengil bazani (A/B/C ustunlar + o'tgan
     * semestr qarzlari D) darhol qaytaradi va guruh-to'plamlari ro'yxatini
     * keshga yozadi. Keyingi so'rovlar (token bilan) JORIY semestr qarzlarini
     * (E ustun — og'ir jurnal mantig'i) guruh-to'plami bo'yicha bosqichma-bosqich
     * hisoblab keshdagi natijaga qo'shadi. Front-end "done" bo'lguncha so'raydi.
     */
    public function data(Request $request)
    {
        $this->authorizeAccess();
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);

        $sig = $this->filterSignature($request);
        $token = (string) $request->input('token', '');

        if ($token === '') {
            // Yaqinda tugagan natija keshda bormi? — darhol qaytaramiz.
            $done = \Illuminate\Support\Facades\Cache::get("rar:result:{$sig}");
            if ($done) {
                return response()->json(['status' => 'done', 'progress' => 1] + $done);
            }

            // Yangi run: yengil baza.
            $state = $this->computeBase($request);
            $state['sig'] = $sig;
            $total = count($state['batches']);

            if ($total === 0) {
                $fin = $this->finalize($state);
                \Illuminate\Support\Facades\Cache::put("rar:result:{$sig}", $fin, now()->addMinutes(10));
                return response()->json(['status' => 'done', 'progress' => 1] + $fin);
            }

            $token = \Illuminate\Support\Str::random(20);
            \Illuminate\Support\Facades\Cache::put("rar:state:{$token}", $state, now()->addMinutes(20));

            return response()->json([
                'status' => 'running',
                'token' => $token,
                'progress' => 0,
                'batches' => $total,
            ] + $this->finalize($state));
        }

        // Davom etayotgan run: keyingi guruh-to'plam(lar)ini hisoblaymiz.
        $state = \Illuminate\Support\Facades\Cache::get("rar:state:{$token}");
        if (!$state) {
            return response()->json(['status' => 'expired']);
        }

        $i = (int) ($state['batchIndex'] ?? 0);
        $total = count($state['batches']);
        $deadline = microtime(true) + 45; // so'rov budjeti — web timeout'dan past
        while ($i < $total && microtime(true) < $deadline) {
            $this->processBatch($state, $i);
            $i++;
            $state['batchIndex'] = $i;
        }

        if ($i >= $total) {
            $fin = $this->finalize($state);
            \Illuminate\Support\Facades\Cache::put("rar:result:{$state['sig']}", $fin, now()->addMinutes(10));
            \Illuminate\Support\Facades\Cache::forget("rar:state:{$token}");
            return response()->json(['status' => 'done', 'progress' => 1] + $fin);
        }

        \Illuminate\Support\Facades\Cache::put("rar:state:{$token}", $state, now()->addMinutes(20));

        return response()->json([
            'status' => 'running',
            'token' => $token,
            'progress' => $i / max(1, $total),
            'batches' => $total,
        ] + $this->finalize($state));
    }

    private function filterSignature(Request $request): string
    {
        $keys = ['education_type', 'department', 'specialty', 'level_code', 'semester_code', 'group'];
        $parts = [];
        foreach ($keys as $k) {
            $parts[$k] = (string) $request->input($k, '');
        }

        return md5(json_encode($parts));
    }

    /**
     * Excel eksport — data() bilan AYNAN bir xil bo'lakli (progressiv) mantiq,
     * lekin tafsilot (talaba-darajasi) ham to'planadi va oxirida xlsx fayl
     * yoziladi. Front-end "done" bo'lguncha so'raydi, so'ng download'ga o'tadi.
     */
    public function exportPrepare(Request $request)
    {
        $this->authorizeAccess();
        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);

        $sig = $this->filterSignature($request);
        $token = (string) $request->input('token', '');

        if ($token === '') {
            $done = \Illuminate\Support\Facades\Cache::get("rar:exp:result:{$sig}");
            if ($done && is_file(storage_path('app/public/retake-reports/' . $done['file']))) {
                return response()->json(['status' => 'done', 'progress' => 1, 'download' => $this->exportDownloadUrl($done['file'])]);
            }

            $state = $this->computeBase($request, true);
            $state['sig'] = $sig;
            $total = count($state['batches']);

            if ($total === 0) {
                $file = $this->buildExportFile($state);
                \Illuminate\Support\Facades\Cache::put("rar:exp:result:{$sig}", ['file' => $file], now()->addMinutes(30));
                return response()->json(['status' => 'done', 'progress' => 1, 'download' => $this->exportDownloadUrl($file)]);
            }

            $token = \Illuminate\Support\Str::random(20);
            \Illuminate\Support\Facades\Cache::put("rar:exp:state:{$token}", $state, now()->addMinutes(20));
            return response()->json(['status' => 'running', 'token' => $token, 'progress' => 0, 'batches' => $total]);
        }

        $state = \Illuminate\Support\Facades\Cache::get("rar:exp:state:{$token}");
        if (!$state) {
            return response()->json(['status' => 'expired']);
        }

        $i = (int) ($state['batchIndex'] ?? 0);
        $total = count($state['batches']);
        $deadline = microtime(true) + 45;
        while ($i < $total && microtime(true) < $deadline) {
            $this->processBatch($state, $i);
            $i++;
            $state['batchIndex'] = $i;
        }

        if ($i >= $total) {
            $file = $this->buildExportFile($state);
            \Illuminate\Support\Facades\Cache::put("rar:exp:result:{$state['sig']}", ['file' => $file], now()->addMinutes(30));
            \Illuminate\Support\Facades\Cache::forget("rar:exp:state:{$token}");
            return response()->json(['status' => 'done', 'progress' => 1, 'download' => $this->exportDownloadUrl($file)]);
        }

        \Illuminate\Support\Facades\Cache::put("rar:exp:state:{$token}", $state, now()->addMinutes(20));
        return response()->json(['status' => 'running', 'token' => $token, 'progress' => $i / max(1, $total), 'batches' => $total]);
    }

    /** Tayyor xlsx faylni yuklab beradi. */
    public function exportDownload(Request $request)
    {
        $this->authorizeAccess();
        $file = basename((string) $request->input('file', ''));
        if (!preg_match('/^qayta_oqish_arizasi_hisoboti_[\w]+\.xlsx$/', $file)) {
            abort(404);
        }
        $path = storage_path('app/public/retake-reports/' . $file);
        if (!is_file($path)) {
            abort(404);
        }

        return response()->download($path, 'qayta_oqish_arizasi_hisoboti.xlsx');
    }

    private function exportDownloadUrl(string $file): string
    {
        return route('admin.retake-application-report.export-download', ['file' => $file]);
    }

    /** state (rows + detail) dan xlsx fayl yozadi, fayl nomini qaytaradi. */
    private function buildExportFile(array $state): string
    {
        $fin = $this->finalize($state);

        $spreadsheet = new Spreadsheet();
        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $fin['rows'], $fin['totals']);
        $this->buildDetailSheet($spreadsheet->createSheet(), $state['detail'] ?? []);
        $spreadsheet->setActiveSheetIndex(0);

        $dir = storage_path('app/public/retake-reports');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // Eski fayllarni tozalash (1 soatdan eski).
        foreach (glob($dir . '/*.xlsx') ?: [] as $old) {
            if (@filemtime($old) < time() - 3600) {
                @unlink($old);
            }
        }

        $fileName = 'qayta_oqish_arizasi_hisoboti_' . now()->format('Ymd_His') . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6)) . '.xlsx';
        $path = $dir . '/' . $fileName;
        \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);
        $spreadsheet->disconnectWorksheets();

        return $fileName;
    }

    /**
     * Yengil baza: A/B/C ustunlar (arizalar) + o'tgan semestr qarzlari (D).
     * Hamda JORIY semestr (E) ni bo'lakli hisoblash uchun zarur holatni
     * (guruh-to'plamlari, semestr xaritalari, ariza kalitlari) tayyorlaydi.
     *
     * @param  bool  $wantDetail  true bo'lsa — talaba-darajasidagi tafsilot
     *                            state['detail'] ga to'planadi (eksport uchun).
     */
    private function computeBase(Request $request, bool $wantDetail = false): array
    {
        $students = $this->studentQuery($request)->get();

        $state = [
            'rows' => [],
            'appliedKeys' => [],
            'curSem' => [],
            'semCodes' => [],
            'batches' => [],
            'batchIndex' => 0,
            'wantDetail' => $wantDetail,
            'detail' => [],
            'stuMeta' => [],
        ];

        if ($students->isEmpty()) {
            return $state;
        }

        $semNum = fn ($name) => preg_match('/(\d+)/', (string) $name, $m) ? (int) $m[1] : null;

        $byGroup = [];
        foreach ($students as $s) {
            $hid = (string) $s->hemis_id;
            $state['curSem'][$hid] = $semNum($s->semester_name ?: $s->semester_code);
            if ($s->semester_code !== null) {
                $state['semCodes'][$hid] = (string) $s->semester_code;
            }
            $byGroup[(string) ($s->group_id ?? '')][] = $hid;
            if ($wantDetail) {
                // Tafsilot uchun yengil meta — keshda saqlanadigan holatni
                // og'irlashtirmaslik uchun faqat zarur maydonlar.
                $state['stuMeta'][$hid] = [
                    'full_name' => $s->full_name ?? '-',
                    'faculty' => $s->department_name ?? '-',
                    'direction' => $s->specialty_name ?? '-',
                    'kurs' => $s->level_name ?? '-',
                    'semester' => $s->semester_name ?? '-',
                    'group' => $s->group_name ?? '-',
                ];
            }
        }
        $hemisIds = array_keys($state['curSem']);

        $rowFor = function ($sid, $sn, $subjName, $semName) use (&$state) {
            $key = $sid . '|' . $sn;
            if (!isset($state['rows'][$key])) {
                $state['rows'][$key] = $this->newRow($subjName, $semName, $sn);
            }
            return $key;
        };
        $addDetail = function ($hid, $sn, $subjName, $category, $oske = null, $test = null) use (&$state) {
            if (!$state['wantDetail']) return;
            $m = $state['stuMeta'][$hid] ?? null;
            if (!$m) return;
            $state['detail'][] = $m + [
                'ariza_semestr' => $sn . '-semestr',
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

        $resLabel = ['pass' => "O'tgan", 'fail' => 'Yiqilgan', 'none' => 'Imtihon topshirmagan'];
        foreach ($apps as $a) {
            $hid = (string) $a->student_hemis_id;
            $sid = (string) $a->subject_id;
            $sn = $semNum($a->semester_name ?: $a->semester_id);
            if ($sn === null) continue;
            $key = $rowFor($sid, $sn, $a->subject_name, $a->semester_name);
            $state['appliedKeys'][$hid . '|' . $sid . '|' . $sn] = true;
            $isCurrent = ($state['curSem'][$hid] ?? null) === $sn;

            if ($a->final_status === RetakeApplication::STATUS_PENDING) {
                $state['rows'][$key]['in_process']++;
                $addDetail($hid, $sn, $a->subject_name, 'Tasdiqlanish jarayonida');
                continue;
            }
            if ($a->final_status !== RetakeApplication::STATUS_APPROVED) {
                continue;
            }
            $res = $this->examResult($a);
            $bucket = $isCurrent ? 'current' : 'approved';
            $state['rows'][$key][$bucket][$res]++;
            $state['rows'][$key][$bucket]['jami']++;
            $prefix = $isCurrent ? 'Joriy semestr' : 'Tasdiqlangan';
            $addDetail($hid, $sn, $a->subject_name, $prefix . ' — ' . $resLabel[$res], $a->oske_score, $a->test_score);
        }

        // 2) Qarzdorlar olami — O'TGAN semestr (academic_records). Yengil va ishonchli
        //    → "Qayta o'qishga ariza bermaganlar" (D, o'tgan).
        $debtorResults = $this->computeDebtorResults($students, 1, false, []);
        foreach ($debtorResults as $dr) {
            $hid = (string) $dr['hemis_id'];
            foreach ($dr['debts'] as $d) {
                $sid = (string) $d['subject_id'];
                $sn = $semNum($d['semester_name'] ?: $d['semester_code']);
                if ($sn === null) continue;
                $key = $rowFor($sid, $sn, $d['subject_name'], $d['semester_name']);
                if (!isset($state['appliedKeys'][$hid . '|' . $sid . '|' . $sn])) {
                    $state['rows'][$key]['not_applied']++;
                    $addDetail($hid, $sn, $d['subject_name'], 'Ariza bermagan');
                }
            }
        }

        // 3) JORIY semestr (E) uchun guruh-to'plamlari. Har bir guruh BUTUNLIGICHA
        //    bitta to'plamda bo'ladi (JN jurnal hisobini guruhga bir marta yuritish
        //    uchun); to'plamlar ~400 talabagacha to'planadi.
        $batch = [];
        $cnt = 0;
        foreach ($byGroup as $ids) {
            if ($cnt > 0 && $cnt + count($ids) > 400) {
                $state['batches'][] = $batch;
                $batch = [];
                $cnt = 0;
            }
            foreach ($ids as $id) {
                $batch[] = $id;
            }
            $cnt += count($ids);
            if ($cnt >= 400) {
                $state['batches'][] = $batch;
                $batch = [];
                $cnt = 0;
            }
        }
        if (!empty($batch)) {
            $state['batches'][] = $batch;
        }

        return $state;
    }

    /**
     * Bitta guruh-to'plami uchun JORIY semestr qarzlarini (E ustun) hisoblab
     * state'dagi qatorlarga qo'shadi. $state — keshdan kelgan/qaytadigan holat.
     */
    private function processBatch(array &$state, int $i): void
    {
        $hemisChunk = $state['batches'][$i] ?? [];
        if (empty($hemisChunk)) {
            return;
        }

        $semCodesSub = [];
        foreach ($hemisChunk as $hid) {
            if (isset($state['semCodes'][$hid])) {
                $semCodesSub[$hid] = $state['semCodes'][$hid];
            }
        }

        try {
            $risks = $this->getCurrentSemesterRisks($hemisChunk, $semCodesSub);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[RetakeAppReport] joriy semestr xavflari (to\'plam): ' . $e->getMessage());
            return;
        }

        $wantDetail = !empty($state['wantDetail']);
        foreach ($risks as $hid => $rs) {
            $hid = (string) $hid;
            $sn = $state['curSem'][$hid] ?? null;
            if ($sn === null) continue;
            foreach ($rs as $cr) {
                $sid = (string) ($cr['subject_id'] ?? '');
                if ($sid === '') continue;
                if (isset($state['appliedKeys'][$hid . '|' . $sid . '|' . $sn])) {
                    continue; // ariza bergan — B guruhda hisoblangan
                }
                $key = $sid . '|' . $sn;
                if (!isset($state['rows'][$key])) {
                    $state['rows'][$key] = $this->newRow($cr['subject_name'] ?? '—', $sn . '-semestr', $sn);
                }
                $state['rows'][$key]['not_applied']++;
                $state['rows'][$key]['current_not_applied']++;

                if ($wantDetail && isset($state['stuMeta'][$hid])) {
                    $state['detail'][] = $state['stuMeta'][$hid] + [
                        'ariza_semestr' => $sn . '-semestr',
                        'subject' => $cr['subject_name'] ?? '—',
                        'category' => 'Joriy semestrdan ariza bermagan',
                        'oske' => null,
                        'test' => null,
                    ];
                }
            }
        }
    }

    /** state'dagi qatorlardan saralangan ro'yxat + jami yig'indini quradi. */
    private function finalize(array $state): array
    {
        $list = array_values($state['rows']);
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

        return ['rows' => $out, 'totals' => $totals];
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

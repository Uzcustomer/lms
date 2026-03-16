<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color as SpreadsheetColor;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class VedomostTekshirishController extends Controller
{
    private array $allowedRoles = [
        'superadmin', 'admin', 'kichik_admin',
        'registrator_ofisi', 'oquv_bolimi', 'oquv_prorektori',
    ];

    public function index()
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);
        return view('admin.vedomost_tekshirish.index');
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        $request->validate([
            'group_ids'    => 'required|array|min:1',
            'group_ids.*'  => 'required',
            'subject_id'   => 'required|string',
            'semester_code' => 'required|string',
            'weight_jn'    => 'nullable|integer|min:0|max:100',
            'weight_mt'    => 'nullable|integer|min:0|max:100',
            'weight_on'    => 'nullable|integer|min:0|max:100',
            'weight_oski'  => 'nullable|integer|min:0|max:100',
            'weight_test'  => 'nullable|integer|min:0|max:100',
        ]);

        $groupIds = $request->input('group_ids');
        $subjectId     = $request->input('subject_id');
        $semesterCode  = $request->input('semester_code');
        $wJn   = (int) ($request->weight_jn   ?? 50);
        $wMt   = (int) ($request->weight_mt   ?? 20);
        $wOn   = (int) ($request->weight_on   ?? 0);
        $wOski = (int) ($request->weight_oski ?? 0);
        $wTest = (int) ($request->weight_test ?? 30);
        $shaklId = (int) ($request->shakl ?? 1);

        $shakllar = config('app.shakllar', []);
        $shaklName = '12-shakl';
        foreach ($shakllar as $sh) {
            if (($sh['id'] ?? 0) === $shaklId) {
                $shaklName = $sh['name'];
                break;
            }
        }

        // --- Spreadsheet ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tekshirish');

        // --- Header ---
        $headers = [
            'A' => '#',
            'B' => 'FIO',
            'C' => 'ID raqami',
            'D' => 'JN',
            'E' => 'JN ball',
            'F' => '',
            'G' => 'MT',
            'H' => 'MT ball',
            'I' => '',
            'J' => 'ON',
            'K' => 'ON ball',
            'L' => '',
            'M' => 'JN+MT',
            'N' => 'JN+MT %',
            'O' => '',
            'P' => 'OSKI',
            'Q' => 'OSKI ball',
            'R' => '',
            'S' => 'Test',
            'T' => 'Test ball',
            'U' => '',
            'V' => 'YN natija',
            'W' => 'ECTS',
            'X' => 'Amaliyot o\'q.',
            'Y' => 'Baho',
            'Z' => 'Talaba HEMIS ID',
            'AA' => 'Fan ID',
            'AB' => 'Fan nomi',
            'AC' => 'Qaydnoma turi',
            'AD' => 'O\'quv yili',
            'AE' => 'Semestr',
            'AF' => 'Guruh HEMIS ID',
            'AG' => 'Guruh nomi',
            'AH' => 'Soat',
            'AI' => 'Kredit',
            'AJ' => 'Ma\'ruzachi',
            'AK' => 'Divisor',
            'AL' => 'Davomat %',
            'AM' => 'JN (asl)',
            'AY' => 'Zanjir',
        ];

        $sheet->getRowDimension(1)->setRowHeight(30);
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9E1F2'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

        // Ustun kengliklarini belgilash
        $colWidths = [
            'A' => 4, 'B' => 30, 'C' => 14, 'D' => 6, 'E' => 7,
            'F' => 2, 'G' => 6, 'H' => 7, 'I' => 2, 'J' => 6,
            'K' => 7, 'L' => 2, 'M' => 7, 'N' => 7, 'O' => 2,
            'P' => 6, 'Q' => 7, 'R' => 2, 'S' => 6, 'T' => 7,
            'U' => 2, 'V' => 7, 'W' => 6, 'X' => 20, 'Y' => 10,
            'Z' => 14, 'AA' => 10, 'AB' => 28, 'AC' => 14,
            'AD' => 14, 'AE' => 8, 'AF' => 12, 'AG' => 16,
            'AH' => 6, 'AI' => 6, 'AJ' => 22, 'AK' => 7,
            'AL' => 8, 'AM' => 8, 'AY' => 25,
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // --- Effective grade helper ---
        $getEffectiveGrade = function ($row) {
            if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
                return (float) $row->grade;
            }
            if ($row->status === 'pending') return null;
            if ($row->reason === 'absent' && $row->grade === null) {
                return $row->retake_grade !== null ? (float) $row->retake_grade : null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            if ($row->status === 'recorded') return $row->grade !== null ? (float) $row->grade : null;
            if ($row->status === 'closed')   return $row->grade !== null ? (float) $row->grade : null;
            if ($row->retake_grade !== null)  return (float) $row->retake_grade;
            return null;
        };

        $roundHalfUp = fn($v) => (int) floor((float) $v + 0.5);

        // --- Ma'lumot yig'ish ---
        $dataRow = 2;

        foreach ($groupIds as $groupId) {
            $group = is_numeric($groupId)
                ? Group::find($groupId)
                : Group::where('group_hemis_id', $groupId)->first();
            if (!$group) continue;
            $groupHemisId = $group->group_hemis_id;

            $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
            $educationYearCode = $curriculum?->education_year_code;

            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();

            if (!$subject) continue;

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            $students = Student::where('group_id', $groupHemisId)->orderBy('full_name')->get();
            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            if (empty($studentHemisIds)) continue;

            // Education year (schedules dan)
            $scheduleYear = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->value('education_year_code');
            if ($scheduleYear) $educationYearCode = $scheduleYear;

            // --- JN schedule ---
            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
            $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];

            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($educationYearCode, fn($q) => $q->where('education_year_code', $educationYearCode))
                ->whereNotIn('training_type_name', $excludedNames)
                ->whereNotIn('training_type_code', $excludedCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')->orderBy('lesson_pair_code')
                ->get();

            $jbColumns = $jbScheduleRows->map(fn($s) => [
                'date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                'pair' => $s->lesson_pair_code,
            ])->unique(fn($i) => $i['date'] . '_' . $i['pair'])->values();

            $jbDatePairSet = [];
            $jbPairsPerDay = [];
            foreach ($jbColumns as $col) {
                $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
                $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $cutoff = Carbon::now('Asia/Tashkent')->endOfDay();
            $jbDatesForAvg = $jbColumns->pluck('date')->unique()->sort()->filter(
                fn($d) => Carbon::parse($d, 'Asia/Tashkent')->startOfDay()->lte($cutoff)
            )->values()->toArray();
            $jbDatesForAvgLookup = array_flip($jbDatesForAvg);
            $totalJbDays = count($jbDatesForAvg);

            // --- MT schedule ---
            $mtScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($educationYearCode, fn($q) => $q->where('education_year_code', $educationYearCode))
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->get();

            $mtColumns = $mtScheduleRows->map(fn($s) => [
                'date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                'pair' => $s->lesson_pair_code,
            ])->unique(fn($i) => $i['date'] . '_' . $i['pair'])->values();

            $mtDatePairSet = [];
            $mtPairsPerDay = [];
            foreach ($mtColumns as $col) {
                $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
                $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
            }
            $mtDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();
            $totalMtDays = count($mtDates);

            $minScheduleDate = collect()
                ->merge($jbColumns->pluck('date'))
                ->merge($mtColumns->pluck('date'))
                ->min();

            // --- JN va MT baholar ---
            $allGradesRaw = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->when($educationYearCode, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $educationYearCode)
                        ->orWhere(fn($q3) => $q3->whereNull('education_year_code')
                            ->when($minScheduleDate, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate)));
                }))
                ->when(!$educationYearCode && $minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $jbGrades = [];
            $mtGradesMap = [];
            foreach ($allGradesRaw as $g) {
                $eff = $getEffectiveGrade($g);
                if ($eff === null) continue;
                $date = Carbon::parse($g->lesson_date)->format('Y-m-d');
                $key  = $date . '_' . $g->lesson_pair_code;
                if (isset($jbDatePairSet[$key])) {
                    $jbGrades[$g->student_hemis_id][$date][$g->lesson_pair_code] = $eff;
                }
                if (isset($mtDatePairSet[$key])) {
                    $mtGradesMap[$g->student_hemis_id][$date][$g->lesson_pair_code] = $eff;
                }
            }

            // Manual MT
            $manualMt = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->whereNotNull('grade')
                ->select('student_hemis_id', 'grade')
                ->get()->keyBy('student_hemis_id');

            // JN/MT hisoblash
            $jnGrades = [];
            $mtGrades = [];
            foreach ($studentHemisIds as $hId) {
                // JN
                $dailySum = 0;
                $studentDayGrades = $jbGrades[$hId] ?? [];
                foreach ($jbDatesForAvg as $date) {
                    $dayGrades = $studentDayGrades[$date] ?? [];
                    $pairs = $jbPairsPerDay[$date] ?? 1;
                    $dailySum += $roundHalfUp(array_sum($dayGrades) / $pairs);
                }
                $jnGrades[$hId] = $totalJbDays > 0 ? $roundHalfUp($dailySum / $totalJbDays) : 0;

                // MT (scheduled)
                $mtDailySum = 0;
                $studentMtGrades = $mtGradesMap[$hId] ?? [];
                foreach ($mtDates as $date) {
                    $dayGrades = $studentMtGrades[$date] ?? [];
                    $pairs = $mtPairsPerDay[$date] ?? 1;
                    $mtDailySum += $roundHalfUp(array_sum($dayGrades) / $pairs);
                }
                $mtGrades[$hId] = $totalMtDays > 0 ? $roundHalfUp($mtDailySum / $totalMtDays) : 0;

                // Manual MT ustun turadi
                if (isset($manualMt[$hId])) {
                    $mtGrades[$hId] = $roundHalfUp((float) $manualMt[$hId]->grade);
                }
            }

            // --- ON, OSKI, Test baholar ---
            $otherRaw = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [100, 101, 102, 103])
                ->when($educationYearCode, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $educationYearCode)
                        ->orWhere(fn($q3) => $q3->whereNull('education_year_code')
                            ->when($minScheduleDate, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate)));
                }))
                ->when(!$educationYearCode && $minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'training_type_code', 'grade', 'retake_grade', 'status', 'reason', 'quiz_result_id')
                ->get();

            $otherGrouped = [];
            foreach ($otherRaw as $g) {
                $eff = $getEffectiveGrade($g);
                if ($eff === null) continue;
                $typeCode = $g->training_type_code;
                if ($typeCode == 103 && $g->quiz_result_id) {
                    $qt = DB::table('hemis_quiz_results')->where('id', $g->quiz_result_id)->value('quiz_type');
                    if (in_array($qt, ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'])) $typeCode = 101;
                    elseif (in_array($qt, ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'])) $typeCode = 102;
                }
                $otherGrouped[$g->student_hemis_id][$typeCode][] = $eff;
            }
            $gradesByType = [100 => [], 101 => [], 102 => []];
            foreach ($otherGrouped as $sId => $types) {
                foreach ([100, 101, 102] as $tc) {
                    if (!empty($types[$tc])) {
                        $gradesByType[$tc][$sId] = array_sum($types[$tc]) / count($types[$tc]);
                    }
                }
            }

            // --- O'qituvchilar ---
            $lectureTeacher   = $this->getTopTeacher($groupHemisId, $subjectId, $semesterCode, [11]);
            $practiceTeacher  = $this->getTopTeacher($groupHemisId, $subjectId, $semesterCode, [12, 13, 14, 18]);

            // --- Davomat ---
            $davomatByStudent = [];
            $totalHours = (int) ($subject->total_acload ?? 0);
            foreach ($students as $stu) {
                $absent = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->where('student_hemis_id', $stu->hemis_id)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->where('reason', 'absent')
                    ->count();
                $davomatByStudent[$stu->hemis_id] = $totalHours > 0
                    ? round($absent / $totalHours * 100, 2)
                    : 0.0;
            }

            // JN divisor (PHP script mantig'i: talabalar soniga qarab)
            $totalStudents = count($studentHemisIds);
            $threshold = max(3, (int) ($totalStudents * 0.3));
            $dateCounts = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [99, 100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->selectRaw('DATE(lesson_date) as ld, COUNT(DISTINCT student_hemis_id) as cnt')
                ->groupBy('ld')
                ->get();
            $validDates = $dateCounts->filter(fn($r) => $r->cnt >= $threshold)->count();
            $divisor = max(1, $validDates);

            // --- Qatorlarni yozish ---
            $rowIndex = 1;
            foreach ($students as $stu) {
                $hId = $stu->hemis_id;

                $jnOrig  = $jnGrades[$hId] ?? 0;
                $mt      = $mtGrades[$hId] ?? 0;
                $on      = round($gradesByType[100][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $oski    = round($gradesByType[101][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $test    = round($gradesByType[102][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $dav     = $davomatByStudent[$hId] ?? 0;

                // Davomat >= 25% bo'lsa JN = 0
                $jn = $dav >= 25 ? 0 : $jnOrig;

                // Balllar
                $jnBall   = $jn >= 60   ? $roundHalfUp($jn * $wJn / 100)     : 0;
                $mtBall   = $mt >= 60   ? $roundHalfUp($mt * $wMt / 100)     : 0;
                $onBall   = $on >= 60   ? $roundHalfUp($on * $wOn / 100)     : 0;
                $oskiBall = $oski >= 60 ? $roundHalfUp($oski * $wOski / 100) : 0;
                $testBall = $test >= 60 ? $roundHalfUp($test * $wTest / 100) : 0;

                $sumBall = $jnBall + $mtBall + $onBall;
                $maxSum  = $wJn + $wMt + $wOn;

                // YN natija
                $yn = $this->calcYn($jn, $mt, $on, $oski, $test, $wJn, $wMt, $wOn, $wOski, $wTest, $dav);

                // ECTS
                $ects = $this->toEcts($yn);

                // Baho
                $baho = $this->toBaho($yn);

                // Zanjir
                $zanjir = $this->checkZanjir($jn, $mt, $on, $oski, $test, $wJn, $wMt, $wOn, $wOski, $wTest);

                $r = $dataRow;

                // Hujayralarga yozish
                $sheet->setCellValue("A{$r}", $rowIndex);
                $sheet->setCellValue("B{$r}", $stu->full_name);
                $sheet->setCellValueExplicit("C{$r}", (string) $stu->student_id_number, DataType::TYPE_STRING);
                $sheet->setCellValue("D{$r}", $jn);
                $sheet->setCellValue("E{$r}", $jnBall);
                $sheet->setCellValue("G{$r}", $mt);
                $sheet->setCellValue("H{$r}", $mtBall);
                $sheet->setCellValue("J{$r}", $on);
                $sheet->setCellValue("K{$r}", $onBall);
                $sheet->setCellValue("M{$r}", $sumBall);
                if ($maxSum > 0) {
                    $sheet->setCellValue("N{$r}", $sumBall / $maxSum);
                    $sheet->getStyle("N{$r}")->getNumberFormat()->setFormatCode('0%');
                }
                $sheet->setCellValue("P{$r}", $oski);
                $sheet->setCellValue("Q{$r}", $oskiBall);
                $sheet->setCellValue("S{$r}", $test);
                $sheet->setCellValue("T{$r}", $testBall);
                $sheet->setCellValue("V{$r}", $yn === '' ? '' : $yn);
                $sheet->setCellValue("W{$r}", $ects);
                $sheet->setCellValue("X{$r}", $practiceTeacher);
                $sheet->setCellValue("Y{$r}", $baho);
                $sheet->setCellValue("Z{$r}", (string) $hId);
                $sheet->setCellValue("AA{$r}", $subjectId);
                $sheet->setCellValue("AB{$r}", $subject->subject_name ?? '');
                $sheet->setCellValue("AC{$r}", $shaklName);
                $sheet->setCellValue("AD{$r}", $curriculum?->education_year_name ?? '');
                $sheet->setCellValue("AE{$r}", $semester?->name ?? $semesterCode);
                $sheet->setCellValue("AF{$r}", (string) $groupHemisId);
                $sheet->setCellValue("AG{$r}", $group->name ?? '');
                $sheet->setCellValue("AH{$r}", $totalHours);
                $sheet->setCellValue("AI{$r}", (int) ($subject->credit ?? 0));
                $sheet->setCellValue("AJ{$r}", $lectureTeacher);
                $sheet->setCellValue("AK{$r}", $divisor);
                $sheet->setCellValue("AL{$r}", $dav / 100);
                $sheet->getStyle("AL{$r}")->getNumberFormat()->setFormatCode('0.00%');
                if ($dav >= 25 && $jnOrig > 0) {
                    $sheet->setCellValue("AM{$r}", $jnOrig);
                }
                $sheet->setCellValue("AY{$r}", $zanjir);

                // Rang berish
                $this->applyRowStyle($sheet, $r, $yn, $dav);

                $dataRow++;
                $rowIndex++;
            }
        }

        // Freeze pane
        $sheet->freezePane('D2');

        // Fayl yuborish
        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        if (method_exists($writer, 'setPreCalculateFormulas')) {
            $writer->setPreCalculateFormulas(false);
        }

        $filename = 'vedomost_tekshirish_' . now()->format('d.m.Y_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // --- Helpers ---

    private function getTopTeacher(string $groupHemisId, string $subjectId, string $semesterCode, array $typeCodes): string
    {
        $row = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereIn('training_type_code', $typeCodes)
            ->whereNotNull('employee_name')
            ->selectRaw('employee_name, COUNT(*) as cnt')
            ->groupBy('employee_name')
            ->orderByDesc('cnt')
            ->first();

        return $row?->employee_name ?? '';
    }

    private function calcYn(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest, float $dav): string|int
    {
        // Bo'sh talaba
        if ($jn === 0 && $mt === 0) return '';

        // Davomat >= 25% → qo'yilmadi (-2)
        if ($dav >= 25) return -2;

        // Imtihonga kirmaganlar
        $oskiMissing = $wOski > 0 && $oski == 0;
        $testMissing = $wTest > 0 && $test == 0;
        if ($oskiMissing || $testMissing) {
            // JN+MT >= 60% dan o'tganlar uchun -1
            $sumBall = ($jn >= 60 ? $jn * $wJn / 100 : 0) + ($mt >= 60 ? $mt * $wMt / 100 : 0) + ($on >= 60 ? $on * $wOn / 100 : 0);
            $maxPre = $wJn + $wMt + $wOn;
            if ($maxPre > 0 && $sumBall / $maxPre >= 0.6) {
                return -1;
            }
        }

        // Har qanday komponent < 60 → 0
        if (($wJn > 0 && $jn < 60) || ($wMt > 0 && $mt < 60) || ($wOn > 0 && $on < 60) || ($wOski > 0 && $oski < 60) || ($wTest > 0 && $test < 60)) {
            return 0;
        }

        // Yakuniy ball
        $sum = 0;
        if ($wJn > 0)   $sum += $jn * $wJn / 100;
        if ($wMt > 0)   $sum += $mt * $wMt / 100;
        if ($wOn > 0)   $sum += $on * $wOn / 100;
        if ($wOski > 0) $sum += $oski * $wOski / 100;
        if ($wTest > 0) $sum += $test * $wTest / 100;

        return (int) floor($sum + 0.5);
    }

    private function toEcts(string|int $yn): string
    {
        if (!is_numeric($yn) || $yn < 0) return '';
        $yn = (int) $yn;
        if ($yn >= 90) return 'A';
        if ($yn >= 85) return 'B+';
        if ($yn >= 70) return 'B';
        if ($yn >= 60) return 'C';
        return 'F';
    }

    private function toBaho(string|int $yn): string
    {
        if ($yn === '' || $yn === -2) return "qo'yilmadi";
        if ($yn === -1) return 'kelmadi';
        if (!is_numeric($yn)) return '';
        $yn = (int) $yn;
        if ($yn >= 90) return "a\u{02BC}lo";
        if ($yn >= 70) return 'yaxshi';
        if ($yn >= 60) return "o\u{02BB}rta";
        return 'qon-siz';
    }

    private function checkZanjir(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest): string
    {
        $warnings = [];
        $jnPass   = $wJn   === 0 || $jn   >= 60;
        $mtPass   = $wMt   === 0 || $mt   >= 60;
        $onPass   = $wOn   === 0 || $on   >= 60;
        $oskiPass = $wOski === 0 || $oski >= 60;

        if ($oski > 0) {
            if (!$jnPass && $wJn > 0) $warnings[] = 'JN✖→OSKI';
            if (!$mtPass && $wMt > 0) $warnings[] = 'MT✖→OSKI';
            if (!$onPass && $wOn > 0) $warnings[] = 'ON✖→OSKI';
        }
        if ($test > 0) {
            if (!$jnPass && $wJn > 0) $warnings[] = 'JN✖→Test';
            if (!$mtPass && $wMt > 0) $warnings[] = 'MT✖→Test';
            if (!$onPass && $wOn > 0) $warnings[] = 'ON✖→Test';
            if (!$oskiPass && $wOski > 0) $warnings[] = 'OSKI✖→Test';
        }
        return implode(', ', $warnings);
    }

    private function applyRowStyle($sheet, int $row, string|int $yn, float $dav): void
    {
        $range = "A{$row}:AY{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['size' => 9],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD0D0D0'],
                ],
            ],
        ]);

        // Rang
        if ($yn === -2 || $yn === 0) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFC7CE');
        } elseif ($yn === -1) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFEB9C');
        } elseif ($dav >= 25) {
            $sheet->getStyle("A{$row}:AY{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FFFF0000');
        }
    }
}

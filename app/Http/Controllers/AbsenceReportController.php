<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsenceReportController extends Controller
{
    private const THRESHOLD_HOURS = 74;

    public function index(Request $request)
    {
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            $selectedEducationType = $educationTypes
                ->first(fn($type) => str_contains(mb_strtolower($type->education_type_name ?? ''), 'bakalavr'))
                ?->education_type_code;
        }

        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        return view('admin.absence_report.index', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'studentStatuses'
        ));
    }

    public function data(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1. Talabalar ro'yxatini olish (filtrlar bilan)
        $studentQuery = DB::table('students as s')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->select('s.hemis_id', 's.full_name', 's.group_id', 's.group_name',
                's.department_name', 's.specialty_name', 's.level_name',
                's.semester_name', 's.student_status_name');

        if ($request->filled('student_status')) {
            $studentQuery->where('s.student_status_code', $request->student_status);
        }
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $studentQuery->where('s.department_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty')) {
            $studentQuery->where('s.specialty_id', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $studentQuery->where('s.level_code', $request->level_code);
        }
        if ($request->filled('group')) {
            $studentQuery->where('s.group_id', $request->group);
        }

        $selectedEducationType = $request->get('education_type');
        if ($selectedEducationType) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $selectedEducationType)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $studentQuery->whereIn('s.group_id', $groupIds);
        }

        // Joriy semestr filtri: faqat joriy semestrdagi guruhlar
        if ($request->get('current_semester', '1') == '1') {
            $studentQuery->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('semesters as sem')
                    ->whereColumn('sem.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->where('sem.current', true);
            });
        }

        $students = $studentQuery->get();

        if ($students->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $studentMap = $students->keyBy('hemis_id');
        $hemisIds = $students->pluck('hemis_id')->toArray();

        // 2. student_grades dan barcha absent yozuvlarni olish
        $gradesQuery = DB::table('student_grades')
            ->whereIn('student_hemis_id', $hemisIds)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->where('reason', 'absent')
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'subject_name',
                'semester_code', 'status', 'lesson_date');

        if ($request->get('current_semester', '1') == '1') {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->unique()
                ->toArray();
            if (!empty($currentSemesterCodes)) {
                $gradesQuery->whereIn('semester_code', $currentSemesterCodes);
            }
        }

        $grades = $gradesQuery->get();

        if ($grades->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 3. Har bir talaba uchun soatlarni hisoblash
        $studentData = [];

        foreach ($grades as $g) {
            $sid = $g->student_hemis_id;

            if (!isset($studentData[$sid])) {
                $studentData[$sid] = [
                    'total_absent' => 0,
                    'unexcused_absent' => 0,
                    'excused_absent' => 0,
                    'subjects' => [],
                ];
            }

            $studentData[$sid]['total_absent'] += 2;

            if ($g->status === 'retake') {
                $studentData[$sid]['excused_absent'] += 2;
            } else {
                $studentData[$sid]['unexcused_absent'] += 2;
            }

            // Fan bo'yicha guruhlash
            $subKey = $g->subject_id;
            if (!isset($studentData[$sid]['subjects'][$subKey])) {
                $studentData[$sid]['subjects'][$subKey] = [
                    'name' => $g->subject_name,
                    'hours' => 0,
                ];
            }
            $studentData[$sid]['subjects'][$subKey]['hours'] += 2;
        }

        // 4. 74 soat chegarasidan o'tganlarni filtrlash
        $threshold = (int) ($request->get('threshold', self::THRESHOLD_HOURS));
        $results = [];

        foreach ($studentData as $sid => $data) {
            if ($data['total_absent'] < $threshold) {
                continue;
            }

            $st = $studentMap[$sid] ?? null;
            if (!$st) continue;

            // Fanlar ro'yxati (ko'p soatdan kamga tartiblash)
            $subjects = $data['subjects'];
            uasort($subjects, fn($a, $b) => $b['hours'] <=> $a['hours']);
            $subjectList = array_map(
                fn($s) => $s['name'] . ' (' . $s['hours'] . ' soat)',
                $subjects
            );

            $results[] = [
                'full_name' => $st->full_name,
                'department_name' => $st->department_name ?? '-',
                'specialty_name' => $st->specialty_name ?? '-',
                'level_name' => $st->level_name ?? '-',
                'semester_name' => $st->semester_name ?? '-',
                'group_name' => $st->group_name ?? '-',
                'student_status_name' => $st->student_status_name ?? '-',
                'total_absent' => $data['total_absent'],
                'unexcused_absent' => $data['unexcused_absent'],
                'excused_absent' => $data['excused_absent'],
                'threshold_percent' => round(($data['total_absent'] / self::THRESHOLD_HOURS) * 100),
                'subjects_detail' => implode('; ', $subjectList),
                'subjects_count' => count($subjects),
            ];
        }

        // Saralash
        $sortColumn = $request->get('sort', 'total_absent');
        $sortDirection = $request->get('direction', 'desc');

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportExcel($results);
        }

        // Sahifalash
        $page = (int) $request->get('page', 1);
        $perPage = (int) $request->get('per_page', 50);
        $total = count($results);
        $offset = ($page - 1) * $perPage;
        $pageData = array_slice($results, $offset, $perPage);

        foreach ($pageData as $i => &$item) {
            $item['row_num'] = $offset + $i + 1;
        }
        unset($item);

        return response()->json([
            'data' => $pageData,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ]);
    }

    private function exportExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('74 soat hisobot');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr',
            'Guruh', 'Holat', 'Jami qoldirilgan', 'Sababsiz', 'Sababli',
            '74 soatdan %', 'Fanlar soni', 'Fanlar tafsiloti'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:N1')->applyFromArray($headerStyle);

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['semester_name']);
            $sheet->setCellValue([7, $row], $r['group_name']);
            $sheet->setCellValue([8, $row], $r['student_status_name']);
            $sheet->setCellValue([9, $row], $r['total_absent']);
            $sheet->setCellValue([10, $row], $r['unexcused_absent']);
            $sheet->setCellValue([11, $row], $r['excused_absent']);
            $sheet->setCellValue([12, $row], $r['threshold_percent'] . '%');
            $sheet->setCellValue([13, $row], $r['subjects_count']);
            $sheet->setCellValue([14, $row], $r['subjects_detail']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 15, 14, 16, 14, 14, 14, 12, 60];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:N{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = '74_soat_hisobot_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), '74h_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

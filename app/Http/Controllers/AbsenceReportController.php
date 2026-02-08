<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsenceReportController extends Controller
{
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
        // 1. attendances jadvalidan talabalar bo'yicha jami soatlarni hisoblash
        $query = DB::table('attendances as a')
            ->join('students as s', 's.hemis_id', '=', 'a.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('g.department_active', true)
            ->where('g.active', true);

        // Filtrlar
        if ($request->filled('student_status')) {
            $query->where('s.student_status_code', $request->student_status);
        }
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $query->where('s.department_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty')) {
            $query->where('s.specialty_id', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->filled('group')) {
            $query->where('s.group_id', $request->group);
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
            $query->whereIn('s.group_id', $groupIds);
        }

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('semesters as sem')
                    ->whereColumn('sem.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->where('sem.current', true);
            });

            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->unique()
                ->toArray();
            if (!empty($currentSemesterCodes)) {
                $query->whereIn('a.semester_code', $currentSemesterCodes);
            }
        }

        // 2. Talaba bo'yicha guruhlash: sababsiz, sababli, jami soat, jami kun
        $rows = $query
            ->select(
                'a.student_hemis_id',
                's.full_name',
                's.department_name',
                's.specialty_name',
                's.level_name',
                's.group_name',
                DB::raw('SUM(a.absent_off) as unexcused_hours'),
                DB::raw('SUM(a.absent_on) as excused_hours'),
                DB::raw('SUM(a.absent_on + a.absent_off) as total_hours'),
                DB::raw('COUNT(DISTINCT DATE(a.lesson_date)) as total_days')
            )
            ->groupBy('a.student_hemis_id', 's.full_name', 's.department_name',
                's.specialty_name', 's.level_name', 's.group_name')
            ->get();

        // 3. Statusga qarab filtrlash (minimal chegara: 30 soat sababsiz yoki 15 kun)
        $results = [];

        foreach ($rows as $r) {
            $status = $this->getStatus((int) $r->unexcused_hours, (int) $r->total_days);
            if (!$status) continue;

            $results[] = [
                'full_name' => $r->full_name,
                'department_name' => $r->department_name ?? '-',
                'specialty_name' => $r->specialty_name ?? '-',
                'level_name' => $r->level_name ?? '-',
                'group_name' => $r->group_name ?? '-',
                'unexcused_hours' => (int) $r->unexcused_hours,
                'excused_hours' => (int) $r->excused_hours,
                'total_hours' => (int) $r->total_hours,
                'total_days' => (int) $r->total_days,
                'status' => $status,
            ];
        }

        // 4. Saralash
        $sortColumn = $request->get('sort', 'total_hours');
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

        // 5. Sahifalash
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

    /**
     * Status aniqlash:
     * yellow  - 30-45 soat sababsiz YOKI 15-20 kun
     * orange  - 45-60 soat sababsiz YOKI 20-25 kun
     * red     - 60-74 soat sababsiz YOKI 25-30 kun
     * critical - 74+ soat sababsiz YOKI 30+ kun
     */
    private function getStatus(int $unexcusedHours, int $totalDays): ?string
    {
        if ($unexcusedHours >= 74 || $totalDays >= 30) return 'critical';
        if ($unexcusedHours >= 60 || $totalDays >= 25) return 'red';
        if ($unexcusedHours >= 45 || $totalDays >= 20) return 'orange';
        if ($unexcusedHours >= 30 || $totalDays >= 15) return 'yellow';
        return null;
    }

    private function exportExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('74 soat hisobot');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh',
            'Sababsiz (soat)', 'Sababli (soat)', 'Jami qoldirilgan soat',
            'Jami qoldirilgan kun', 'Status'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        $statusLabels = [
            'yellow' => 'Ogohlantirish (30-45 soat / 15-20 kun)',
            'orange' => 'Xavfli (45-60 soat / 20-25 kun)',
            'red' => 'Jiddiy (60-74 soat / 25-30 kun)',
            'critical' => 'Chegara (74+ soat / 30+ kun)',
        ];

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['group_name']);
            $sheet->setCellValue([7, $row], $r['unexcused_hours']);
            $sheet->setCellValue([8, $row], $r['excused_hours']);
            $sheet->setCellValue([9, $row], $r['total_hours']);
            $sheet->setCellValue([10, $row], $r['total_days']);
            $sheet->setCellValue([11, $row], $statusLabels[$r['status']] ?? '-');

            // Status rangini qo'yish
            $colors = ['yellow' => 'FFF3CD', 'orange' => 'FFE0B2', 'red' => 'FFCDD2', 'critical' => 'D32F2F'];
            $fontColors = ['yellow' => '856404', 'orange' => 'E65100', 'red' => 'C62828', 'critical' => 'FFFFFF'];
            if (isset($colors[$r['status']])) {
                $sheet->getStyle("K{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$r['status']]]],
                    'font' => ['bold' => true, 'color' => ['rgb' => $fontColors[$r['status']]]],
                ]);
            }
        }

        $widths = [5, 30, 25, 30, 8, 15, 16, 16, 20, 20, 35];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:K{$lastRow}")->applyFromArray([
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

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
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

        $kafedraQuery = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->whereNotNull('cs.department_id')
            ->whereNotNull('cs.department_name');

        if ($selectedEducationType) {
            $kafedraQuery->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $kafedraQuery->where('f.id', $request->faculty);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        return view('admin.absence_report.index', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras'
        ));
    }

    public function data(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        // 1-QADAM: Davomat ma'lumotlarini olish (absent_on = 1 bo'lganlar)
        $query = DB::table('attendances as a')
            ->where('a.absent_on', 1)
            ->whereNotIn('a.training_type_code', $excludedCodes);

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $query->join('groups as gr', 'gr.group_hemis_id', '=', 'a.group_id')
                ->join('semesters as sem', function ($join) {
                    $join->on('sem.code', '=', 'a.semester_code')
                        ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
                })
                ->where('sem.current', true);
        }

        // Sana oralig'i filtri
        if ($dateFrom) {
            $query->where('a.lesson_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('a.lesson_date', '<=', $dateTo);
        }

        // Talaba filtrlari
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $query->where(function ($q) use ($faculty) {
                    $q->whereExists(function ($sub) use ($faculty) {
                        $sub->select(DB::raw(1))
                            ->from('students as st')
                            ->whereColumn('st.hemis_id', 'a.student_hemis_id')
                            ->where('st.department_id', $faculty->department_hemis_id);
                    });
                });
            }
        }

        if ($request->filled('education_type')) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $request->education_type)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $query->whereIn('a.group_id', $groupIds);
        }

        if ($request->filled('specialty')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('students as st')
                    ->whereColumn('st.hemis_id', 'a.student_hemis_id')
                    ->where('st.specialty_id', $request->specialty);
            });
        }

        if ($request->filled('level_code')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('students as st')
                    ->whereColumn('st.hemis_id', 'a.student_hemis_id')
                    ->where('st.level_code', $request->level_code);
            });
        }

        if ($request->filled('semester_code')) {
            $query->where('a.semester_code', $request->semester_code);
        }

        if ($request->filled('group')) {
            $query->where('a.group_id', $request->group);
        }

        if ($request->filled('department')) {
            $allowedSubjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
            $query->whereIn('a.subject_id', $allowedSubjectIds);
        }

        if ($request->filled('subject')) {
            $query->where('a.subject_id', $request->subject);
        }

        // 2-QADAM: Talaba bo'yicha aggregatsiya - har bir davomat = 2 soat (1 juftlik)
        $absences = $query->select(
            'a.student_hemis_id',
            DB::raw('SUM(CASE WHEN a.absent_off = 1 THEN 2 ELSE 0 END) as sababli_hours'),
            DB::raw('SUM(CASE WHEN a.absent_off = 0 THEN 2 ELSE 0 END) as sababsiz_hours'),
            DB::raw('COUNT(*) * 2 as total_hours'),
            DB::raw('COUNT(DISTINCT DATE(a.lesson_date)) as total_days')
        )
            ->groupBy('a.student_hemis_id')
            ->get();

        if ($absences->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 3-QADAM: Talaba ma'lumotlarini biriktirish
        $hemisIds = $absences->pluck('student_hemis_id')->unique()->toArray();
        $studentInfo = DB::table('students')
            ->whereIn('hemis_id', $hemisIds)
            ->select('hemis_id', 'full_name', 'department_name', 'specialty_name', 'level_name', 'semester_name', 'group_name')
            ->get()
            ->keyBy('hemis_id');

        $results = [];
        foreach ($absences as $a) {
            $st = $studentInfo[$a->student_hemis_id] ?? null;
            $results[] = [
                'full_name' => $st->full_name ?? 'Noma\'lum',
                'department_name' => $st->department_name ?? '-',
                'specialty_name' => $st->specialty_name ?? '-',
                'level_name' => $st->level_name ?? '-',
                'semester_name' => $st->semester_name ?? '-',
                'sababli_hours' => (int) $a->sababli_hours,
                'sababsiz_hours' => (int) $a->sababsiz_hours,
                'total_hours' => (int) $a->total_hours,
                'total_days' => (int) $a->total_days,
            ];
        }

        // Saralash (default: sababsiz_hours desc)
        $sortColumn = $request->get('sort', 'sababsiz_hours');
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

        // Pagination
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
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
            'current_page' => (int) $page,
            'last_page' => ceil($total / $perPage),
        ]);
    }

    private function exportExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('74 soat dars qoldirish');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Sababli (soat)', 'Sababsiz (soat)', 'Jami (soat)', 'Jami (kun)'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['semester_name']);
            $sheet->setCellValue([7, $row], $r['sababli_hours']);
            $sheet->setCellValue([8, $row], $r['sababsiz_hours']);
            $sheet->setCellValue([9, $row], $r['total_hours']);
            $sheet->setCellValue([10, $row], $r['total_days']);

            // Zona ranglari Excel uchun
            $fillColor = null;
            if ($r['sababsiz_hours'] > 60 || $r['total_days'] > 25) {
                $fillColor = 'FECACA'; // qizil
            } elseif ($r['sababsiz_hours'] >= 30 || $r['total_days'] >= 15) {
                $fillColor = 'FBCFE8'; // pink
            }

            if ($fillColor) {
                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
                ]);
            }
        }

        $widths = [5, 30, 25, 30, 8, 10, 14, 14, 14, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = '74_soat_dars_qoldirish_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'abs_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

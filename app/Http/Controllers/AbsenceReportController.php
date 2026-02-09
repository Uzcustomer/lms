<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Setting;
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

        // O'quv yillari ro'yxati
        $educationYears = DB::table('attendances')
            ->select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderByDesc('education_year_code')
            ->get();

        // Joriy o'quv yili (semesters jadvalidan)
        $currentEducationYear = DB::table('semesters')
            ->where('current', true)
            ->value('education_year');

        return view('admin.absence_report.index', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'studentStatuses',
            'educationYears',
            'currentEducationYear'
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

        // Semestr filtri
        if ($request->filled('semester')) {
            $query->where('a.semester_code', $request->semester);
        }

        // O'quv yili filtri
        if ($request->filled('education_year')) {
            $query->where('a.education_year_code', $request->education_year);
        }

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('semesters as sem')
                    ->whereColumn('sem.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->whereColumn('sem.code', 'a.semester_code')
                    ->where('sem.current', true);
            });
        }

        // 2. Talaba bo'yicha guruhlash: sababsiz, sababli, jami soat, jami kun
        $rows = $query
            ->select(
                'a.student_hemis_id',
                's.full_name',
                's.department_name',
                's.specialty_name',
                's.level_name',
                'a.semester_name',
                's.group_name',
                DB::raw('SUM(a.absent_off) as unexcused_hours'),
                DB::raw('SUM(a.absent_on) as excused_hours'),
                DB::raw('SUM(a.absent_on + a.absent_off) as total_hours'),
                DB::raw('COUNT(DISTINCT DATE(a.lesson_date)) as total_days')
            )
            ->groupBy('a.student_hemis_id', 's.full_name', 's.department_name',
                's.specialty_name', 's.level_name', 'a.semester_name', 's.group_name')
            ->get();

        // 3. Statusga qarab filtrlash (minimal chegara: 30 soat sababsiz yoki 15 kun)
        $results = [];

        foreach ($rows as $r) {
            $status = $this->getStatus((int) $r->unexcused_hours, (int) $r->total_days);
            if (!$status) continue;

            $results[] = [
                'student_hemis_id' => $r->student_hemis_id,
                'full_name' => $r->full_name,
                'department_name' => $r->department_name ?? '-',
                'specialty_name' => $r->specialty_name ?? '-',
                'level_name' => $r->level_name ?? '-',
                'semester_name' => $r->semester_name ?? '-',
                'group_name' => $r->group_name ?? '-',
                'unexcused_hours' => (int) $r->unexcused_hours,
                'excused_hours' => (int) $r->excused_hours,
                'total_hours' => (int) $r->total_hours,
                'total_days' => (int) $r->total_days,
                'status' => $status,
            ];
        }

        // 3.5. 74+ soat talabalar uchun spravka status va qatnashish sanasi
        $spravkaDeadlineDays = (int) Setting::get('spravka_deadline_days', 10);
        $today = date('Y-m-d');
        $reportDate = date('d.m.Y');

        $criticalStudentIds = [];
        foreach ($results as $item) {
            if ($item['unexcused_hours'] >= 74) {
                $criticalStudentIds[] = $item['student_hemis_id'];
            }
        }

        $thresholdData = [];
        if (!empty($criticalStudentIds)) {
            $attQuery = DB::table('attendances as a2')
                ->join('students as s2', 's2.hemis_id', '=', 'a2.student_hemis_id')
                ->join('groups as g2', 'g2.group_hemis_id', '=', 's2.group_id')
                ->whereIn('a2.student_hemis_id', $criticalStudentIds)
                ->where('a2.absent_off', '>', 0)
                ->select('a2.student_hemis_id', 'a2.lesson_date', 'a2.absent_off');

            if ($request->filled('semester')) {
                $attQuery->where('a2.semester_code', $request->semester);
            }
            if ($request->filled('education_year')) {
                $attQuery->where('a2.education_year_code', $request->education_year);
            }
            if ($request->get('current_semester', '1') == '1') {
                $attQuery->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('semesters as sem2')
                        ->whereColumn('sem2.curriculum_hemis_id', 'g2.curriculum_hemis_id')
                        ->whereColumn('sem2.code', 'a2.semester_code')
                        ->where('sem2.current', true);
                });
            }

            $attQuery->orderBy('a2.lesson_date')->orderBy('a2.lesson_pair_start_time');
            $allRecords = $attQuery->get()->groupBy('student_hemis_id');

            foreach ($criticalStudentIds as $studentId) {
                $records = $allRecords->get($studentId, collect());
                $cumulative = 0;
                $thresholdDate = null;
                $firstAfterDate = null;

                foreach ($records as $rec) {
                    $recDate = date('Y-m-d', strtotime($rec->lesson_date));
                    if (!$thresholdDate) {
                        $cumulative += (int) $rec->absent_off;
                        if ($cumulative >= 74) {
                            $thresholdDate = $recDate;
                        }
                    } elseif ($recDate > $thresholdDate && !$firstAfterDate) {
                        $firstAfterDate = $recDate;
                        break;
                    }
                }

                $thresholdData[$studentId] = [
                    'threshold_date' => $thresholdDate,
                    'first_after' => $firstAfterDate,
                ];
            }
        }

        foreach ($results as &$item) {
            $item['report_date'] = $reportDate;

            if ($item['unexcused_hours'] >= 74 && isset($thresholdData[$item['student_hemis_id']])) {
                $td = $thresholdData[$item['student_hemis_id']];
                $item['attendance_after_74'] = $td['first_after']
                    ? date('d.m.Y', strtotime($td['first_after']))
                    : '-';

                if ($td['threshold_date']) {
                    $deadlineDate = date('Y-m-d', strtotime($td['threshold_date'] . " +{$spravkaDeadlineDays} days"));
                    $item['status'] = ($today > $deadlineDate) ? 'late' : 'has_time';
                }
            } else {
                $item['attendance_after_74'] = '-';
            }
        }
        unset($item);

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
     * Talabaning batafsil davomat ma'lumotlari
     */
    public function detail(Request $request)
    {
        $hemisId = $request->get('hemis_id');
        if (!$hemisId) {
            return response()->json(['data' => []]);
        }

        $student = DB::table('students')
            ->where('hemis_id', $hemisId)
            ->select('full_name', 'department_name', 'specialty_name',
                'level_name', 'semester_name', 'group_name')
            ->first();

        $query = DB::table('attendances')
            ->where('student_hemis_id', $hemisId)
            ->select('subject_name', 'lesson_date', 'lesson_pair_name',
                'lesson_pair_start_time', 'lesson_pair_end_time',
                'absent_on', 'absent_off', 'semester_name')
            ->orderBy('lesson_date', 'desc')
            ->orderBy('lesson_pair_start_time');

        // O'quv yili filtri
        if ($request->filled('education_year')) {
            $query->where('education_year_code', $request->education_year);
        }

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $curriculumId = DB::table('students as s')
                ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
                ->where('s.hemis_id', $hemisId)
                ->value('g.curriculum_hemis_id');

            if ($curriculumId) {
                $currentCode = DB::table('semesters')
                    ->where('curriculum_hemis_id', $curriculumId)
                    ->where('current', true)
                    ->value('code');
                if ($currentCode) {
                    $query->where('semester_code', $currentCode);
                }
            }
        }

        $rows = $query->get()->map(function ($r) {
            $lessonDate = $r->lesson_date ? date('d.m.Y', strtotime($r->lesson_date)) : '-';
            $pairTime = $r->lesson_pair_start_time . ' - ' . $r->lesson_pair_end_time;
            $type = ((int) $r->absent_on > 0 && (int) $r->absent_off == 0) ? 'Sababli' : 'Sababsiz';
            $hours = max((int) $r->absent_on, (int) $r->absent_off);

            return [
                'subject_name' => $r->subject_name,
                'lesson_date' => $lessonDate,
                'pair_name' => $r->lesson_pair_name,
                'pair_time' => $pairTime,
                'type' => $type,
                'hours' => $hours,
            ];
        });

        return response()->json([
            'student' => $student,
            'data' => $rows,
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

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh',
            'Sababsiz (soat)', 'Sababli (soat)', 'Jami qoldirilgan soat',
            'Jami qoldirilgan kun', '74 soat keyin qatnashgan', 'Hisobot sanasi', 'Status'];

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

        $statusLabels = [
            'yellow' => 'Ogohlantirish (30-45 soat / 15-20 kun)',
            'orange' => 'Xavfli (45-60 soat / 20-25 kun)',
            'red' => 'Jiddiy (60-74 soat / 25-30 kun)',
            'critical' => 'Chegara (74+ soat / 30+ kun)',
            'late' => 'Kechikkan',
            'has_time' => 'Spravka topshirishga muddati bor',
        ];

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['semester_name']);
            $sheet->setCellValue([7, $row], $r['group_name']);
            $sheet->setCellValue([8, $row], $r['unexcused_hours']);
            $sheet->setCellValue([9, $row], $r['excused_hours']);
            $sheet->setCellValue([10, $row], $r['total_hours']);
            $sheet->setCellValue([11, $row], $r['total_days']);
            $sheet->setCellValue([12, $row], $r['attendance_after_74'] ?? '-');
            $sheet->setCellValue([13, $row], $r['report_date'] ?? '-');
            $sheet->setCellValue([14, $row], $statusLabels[$r['status']] ?? '-');

            // Status rangini qo'yish
            $colors = ['yellow' => 'FFF3CD', 'orange' => 'FFE0B2', 'red' => 'FFCDD2', 'critical' => 'D32F2F', 'late' => 'D32F2F', 'has_time' => '16A34A'];
            $fontColors = ['yellow' => '856404', 'orange' => 'E65100', 'red' => 'C62828', 'critical' => 'FFFFFF', 'late' => 'FFFFFF', 'has_time' => 'FFFFFF'];
            if (isset($colors[$r['status']])) {
                $sheet->getStyle("N{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[$r['status']]]],
                    'font' => ['bold' => true, 'color' => ['rgb' => $fontColors[$r['status']]]],
                ]);
            }
        }

        $widths = [5, 30, 25, 30, 8, 12, 15, 16, 16, 20, 20, 22, 18, 35];
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

<?php

namespace App\Jobs;

use App\Models\Curriculum;
use App\Models\Department;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportLessonAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 daqiqa

    private array $filters;
    private string $exportKey;

    public function __construct(array $filters, string $exportKey)
    {
        $this->filters = $filters;
        $this->exportKey = $exportKey;
    }

    public function handle(): void
    {
        try {
            $this->updateProgress('Ma\'lumotlar olinmoqda...', 10);

            $results = $this->fetchData();

            $this->updateProgress('Excel fayl yaratilmoqda...', 60);

            $filePath = $this->generateExcel($results);

            Log::info("[ExportLessonAssignmentJob] Tayyor", [
                'export_key' => $this->exportKey,
                'file_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
                'results_count' => count($results),
            ]);

            $this->updateProgress('Tayyor', 100, 'done', $filePath);

        } catch (\Throwable $e) {
            Log::error("[ExportLessonAssignmentJob] Xato: {$e->getMessage()}", [
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);

            $this->updateProgress('Xato: ' . mb_substr($e->getMessage(), 0, 120), 0, 'failed');
        }
    }

    private function fetchData(): array
    {
        $filters = $this->filters;

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        if (($filters['current_semester'] ?? '1') == '1') {
            $scheduleQuery->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            });
        }

        if (!empty($filters['education_type'])) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $filters['education_type'])
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $scheduleQuery->whereIn('sch.group_id', $groupIds);
        }

        if (!empty($filters['faculty'])) {
            $faculty = Department::find($filters['faculty']);
            if ($faculty) {
                $scheduleQuery->where('sch.faculty_id', $faculty->department_hemis_id);
            }
        }

        if (!empty($filters['specialty'])) {
            $scheduleQuery->where('g.specialty_hemis_id', $filters['specialty']);
        }

        if (!empty($filters['level_code'])) {
            $scheduleQuery->where('sem.level_code', $filters['level_code']);
        }

        if (!empty($filters['semester_code'])) {
            $scheduleQuery->where('sch.semester_code', $filters['semester_code']);
        }

        if (!empty($filters['department'])) {
            $scheduleQuery->where('sch.department_id', $filters['department']);
        }

        if (!empty($filters['subject'])) {
            $scheduleQuery->where('sch.subject_id', $filters['subject']);
        }

        if (!empty($filters['group'])) {
            $scheduleQuery->where('sch.group_id', $filters['group']);
        }

        if (!empty($filters['date_from'])) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$filters['date_from']]);
        }

        if (!empty($filters['date_to'])) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$filters['date_to']]);
        }

        $schedules = $scheduleQuery->select(
            'sch.schedule_hemis_id',
            'sch.employee_id',
            'sch.employee_name',
            'sch.faculty_name',
            'g.specialty_name',
            'sem.level_name',
            'sch.semester_code',
            'sch.semester_name',
            'sch.department_name',
            'sch.subject_id',
            'sch.subject_name',
            'sch.group_id',
            'sch.group_name',
            'sch.training_type_code',
            'sch.training_type_name',
            'sch.lesson_pair_code',
            'sch.lesson_pair_start_time',
            'sch.lesson_pair_end_time',
            'g.id as group_db_id',
            DB::raw('DATE(sch.lesson_date) as lesson_date_str')
        )->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $this->updateProgress('Davomat va baholar tekshirilmoqda...', 30);

        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $minDate = $schedules->min('lesson_date_str');
        $maxDate = $schedules->max('lesson_date_str');

        $attendanceByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->pluck('subject_schedule_id')
            ->flip();

        $attendanceByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        $processedCountByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->select('subject_schedule_id', DB::raw('COUNT(DISTINCT student_hemis_id) as cnt'))
            ->groupBy('subject_schedule_id')
            ->pluck('cnt', 'subject_schedule_id');

        $processedCountByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->whereNotIn('sg.training_type_code', [100, 101, 102, 103])
            ->select(DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.lesson_pair_code) as gk"), DB::raw('COUNT(DISTINCT sg.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.lesson_pair_code)"))
            ->pluck('cnt', 'gk');

        $groupIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();

        $currentEducationYear = DB::table('semesters')
            ->where('current', true)
            ->orderByDesc('education_year')
            ->value('education_year');

        $subjectStudentCounts = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('ss.subject_id', $subjectIds)
            ->where('st.student_status_code', 11)
            ->where(function ($q) use ($currentEducationYear) {
                $q->where('ss.education_year', $currentEducationYear)
                  ->orWhereNull('ss.education_year');
            })
            ->select(DB::raw("CONCAT(st.group_id, '|', ss.subject_id) as gs_key"), DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', ss.subject_id)"))
            ->pluck('cnt', 'gs_key');

        $groupStudentCounts = DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        $this->updateProgress('Ma\'lumotlar guruhlanmoqda...', 50);

        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $gradeKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->lesson_pair_code;

            $hasAtt = isset($attendanceByScheduleId[$sch->schedule_hemis_id])
                   || isset($attendanceByKey[$attKey]);

            $skipGradeCheck = in_array($sch->training_type_code, $gradeExcludedTypes);
            $gsKey = $sch->group_id . '|' . $sch->subject_id;
            $totalStudents = $subjectStudentCounts[$gsKey] ?? ($groupStudentCounts[$sch->group_id] ?? 0);

            if ($skipGradeCheck) {
                $hasGrade = null;
                $missingGradeCount = 0;
            } else {
                $processedBySchedule = $processedCountByScheduleId[$sch->schedule_hemis_id] ?? 0;
                $processedByComposite = $processedCountByKey[$gradeKey] ?? 0;
                $processedCount = max($processedBySchedule, $processedByComposite);

                if ($processedCount >= $totalStudents) {
                    $hasGrade = true;
                    $missingGradeCount = 0;
                } elseif ($processedCount > 0) {
                    $hasGrade = false;
                    $missingGradeCount = $totalStudents - $processedCount;
                } else {
                    $hasGrade = false;
                    $missingGradeCount = $totalStudents;
                }
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_name' => $sch->employee_name,
                    'faculty_name' => $sch->faculty_name,
                    'specialty_name' => $sch->specialty_name,
                    'level_name' => $sch->level_name,
                    'semester_name' => $sch->semester_name,
                    'department_name' => $sch->department_name,
                    'subject_name' => $sch->subject_name,
                    'group_name' => $sch->group_name,
                    'training_type' => $sch->training_type_name,
                    'lesson_pair_time' => $pairTime,
                    'lesson_date' => $sch->lesson_date_str,
                    'student_count' => $totalStudents,
                    'has_attendance' => $hasAtt,
                    'has_grades' => $hasGrade,
                    'missing_grade_count' => $missingGradeCount,
                ];
            } else {
                if ($hasAtt && !$grouped[$key]['has_attendance']) {
                    $grouped[$key]['has_attendance'] = true;
                }
                if ($hasGrade === true && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['has_grades'] = true;
                    $grouped[$key]['missing_grade_count'] = 0;
                } elseif ($hasGrade === false && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['missing_grade_count'] = min($grouped[$key]['missing_grade_count'], $missingGradeCount);
                }
            }
        }

        $results = array_values($grouped);

        // Holat filtri
        if (!empty($filters['status_filter'])) {
            $statusFilter = $filters['status_filter'];
            $results = array_values(array_filter($results, function ($r) use ($statusFilter) {
                return match ($statusFilter) {
                    'any_missing' => !$r['has_attendance'] || $r['has_grades'] === false,
                    'attendance_missing' => !$r['has_attendance'],
                    'grade_missing' => $r['has_grades'] === false,
                    'both_missing' => !$r['has_attendance'] && $r['has_grades'] === false,
                    'all_done' => $r['has_attendance'] && $r['has_grades'] !== false,
                    default => true,
                };
            }));
        }

        // Saralash
        $sortColumn = $filters['sort'] ?? 'lesson_date';
        $sortDirection = $filters['direction'] ?? 'desc';

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        return $results;
    }

    private function generateExcel(array $data): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dars belgilash');

        $headers = ['#', 'Xodim FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Kafedra', 'Fan', 'Guruh', "Mashg'ulot turi", 'Juftlik vaqti', 'Talaba soni', 'Davomat', 'Baho', 'Dars sanasi'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:O1')->applyFromArray($headerStyle);

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['employee_name']);
            $sheet->setCellValue([3, $row], $r['faculty_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['semester_name']);
            $sheet->setCellValue([7, $row], $r['department_name']);
            $sheet->setCellValue([8, $row], $r['subject_name']);
            $sheet->setCellValue([9, $row], $r['group_name']);
            $sheet->setCellValue([10, $row], $r['training_type'] ?? '');
            $sheet->setCellValue([11, $row], $r['lesson_pair_time'] ?? '');
            $sheet->setCellValue([12, $row], $r['student_count']);
            $sheet->setCellValue([13, $row], $r['has_attendance'] ? 'Ha' : "Yo'q");
            $sheet->setCellValue([14, $row], $r['has_grades'] === null ? '-' : ($r['has_grades'] ? 'Ha' : "Yo'q (" . ($r['missing_grade_count'] ?? 0) . ")"));
            $sheet->setCellValue([15, $row], $r['lesson_date']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 25, 35, 15, 16, 13, 12, 10, 10, 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:O{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Dars_belgilash_' . date('Y-m-d_H-i') . '.xlsx';

        // exports papkasini yaratish
        $exportDir = storage_path('app/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $fullPath = $exportDir . '/' . $fileName;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($fullPath);
        $spreadsheet->disconnectWorksheets();

        // Web server o'qishi uchun ruxsat berish
        chmod($fullPath, 0644);

        return $fullPath;
    }

    private function updateProgress(string $message, int $percent, string $status = 'running', ?string $filePath = null): void
    {
        $data = [
            'status' => $status,
            'message' => $message,
            'percent' => $percent,
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($filePath) {
            $data['file_path'] = $filePath;
        }

        Cache::put($this->exportKey, $data, 1800); // 30 daqiqa saqlanadi
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[ExportLessonAssignmentJob] Failed: {$exception->getMessage()}", [
            'trace' => mb_substr($exception->getTraceAsString(), 0, 500),
        ]);

        Cache::put($this->exportKey, [
            'status' => 'failed',
            'message' => mb_substr($exception->getMessage(), 0, 120),
            'percent' => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);
    }
}

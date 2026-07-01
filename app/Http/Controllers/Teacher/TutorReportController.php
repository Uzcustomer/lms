<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ComputesStudentDebts;
use App\Models\ContractList;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Specialty;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TutorReportController extends Controller
{
    use ComputesStudentDebts;

    private function getTutorGroupIds()
    {
        $teacher = auth()->guard('teacher')->user();

        $tutorGroupIds = $teacher->groups()->where('active', true)->pluck('group_hemis_id')->toArray();

        $nazoratchiGroupIds = $teacher->nazoratchiGroups()->where('active', true)->pluck('group_hemis_id')->toArray();

        $scheduleGroupIds = DB::table('schedules')
            ->where('employee_id', $teacher->hemis_id)
            ->where('education_year_current', true)
            ->whereNotNull('lesson_date')
            ->pluck('group_id')
            ->unique()
            ->toArray();

        return array_values(array_unique(array_merge($tutorGroupIds, $nazoratchiGroupIds, $scheduleGroupIds)));
    }

    private function getTutorGroups()
    {
        $groupIds = $this->getTutorGroupIds();
        if (empty($groupIds)) {
            return collect();
        }
        return \App\Models\Group::whereIn('group_hemis_id', $groupIds)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    private function getFilteredGroupIds(Request $request)
    {
        $groupIds = $this->getTutorGroupIds();
        if (empty($groupIds)) {
            return [];
        }
        if ($request->filled('group')) {
            $selected = $request->group;
            if (in_array($selected, $groupIds)) {
                return [$selected];
            }
        }
        return $groupIds;
    }

    /**
     * Admin ReportController bilan bir xil baho hisoblash mantiqi
     */
    private function getEffectiveGradeForJn($row): ?float
    {
        if (($row->status ?? null) === 'pending') {
            return null;
        }

        if (($row->reason ?? null) === 'absent' && $row->grade === null) {
            return $row->retake_grade !== null ? (float) $row->retake_grade : null;
        }

        if (($row->status ?? null) === 'closed' && ($row->reason ?? null) === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
            return null;
        }

        if (($row->status ?? null) === 'recorded') {
            return $row->grade !== null ? (float) $row->grade : null;
        }

        if (($row->status ?? null) === 'closed') {
            return $row->grade !== null ? (float) $row->grade : null;
        }

        if ($row->retake_grade !== null) {
            return (float) $row->retake_grade;
        }

        return null;
    }

    /**
     * JN o'zlashtirish hisoboti — admin uslubida (filterlar + AJAX table)
     */
    public function jnReport(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = null;

        $facultyHemisIds = DB::table('groups')
            ->whereIn('group_hemis_id', $tutorGroupIds)
            ->pluck('department_hemis_id')
            ->unique();
        $faculties = Department::whereIn('department_hemis_id', $facultyHemisIds)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $dekanFacultyIds = [];

        return view('teacher.reports.jn', compact(
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'dekanFacultyIds'
        ));
    }

    /**
     * AJAX: JN hisobot ma'lumotlarini hisoblash (admin mantiqi, faqat tutor guruhlari)
     */
    public function jnReportData(Request $request)
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);

        try {
            $tutorGroupIds = $this->getTutorGroupIds();
            if (empty($tutorGroupIds)) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
            $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

            $dateFrom = $request->filled('date_from') ? $request->date_from : null;
            $dateTo = $request->filled('date_to') ? $request->date_to : null;

            // 1-QADAM: Schedule yozuvlari
            $scheduleQuery = DB::table('schedules as sch')
                ->whereIn('sch.group_id', $tutorGroupIds)
                ->whereNotIn('sch.training_type_name', $excludedNames)
                ->whereNotNull('sch.lesson_date')
                ->select('sch.group_id', 'sch.subject_id', 'sch.semester_code', 'sch.lesson_date', 'sch.lesson_pair_code');

            foreach ($excludedSubjectPatterns as $pattern) {
                $scheduleQuery->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
            }

            $needsJoin = $request->get('current_semester', '1') == '1' || $request->filled('level_code');
            if ($needsJoin) {
                $scheduleQuery
                    ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
                    ->join('semesters as sem', function ($join) {
                        $join->on('sem.code', '=', 'sch.semester_code')
                            ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
                    });
                if ($request->get('current_semester', '1') == '1') {
                    $scheduleQuery->where('sem.current', true);
                }
                if ($request->filled('level_code')) {
                    $scheduleQuery->where('sem.level_code', $request->level_code);
                }
            }

            if ($request->filled('semester_code')) {
                $scheduleQuery->where('sch.semester_code', $request->semester_code);
            }

            if ($request->filled('subject')) {
                $scheduleQuery->where('sch.subject_id', $request->subject);
            }

            if ($request->filled('group')) {
                $groupHemisId = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
                if ($groupHemisId && in_array($groupHemisId, $tutorGroupIds)) {
                    $scheduleQuery->where('sch.group_id', $groupHemisId);
                }
            }

            if ($dateFrom) $scheduleQuery->where('sch.lesson_date', '>=', $dateFrom);
            if ($dateTo) $scheduleQuery->where('sch.lesson_date', '<=', $dateTo);

            $columns = [];
            $minDates = [];
            $scheduleGroupIds = [];
            $scheduleSubjectIds = [];
            $scheduleSemesterCodes = [];
            $hasAny = false;

            foreach ($scheduleQuery->cursor() as $row) {
                $hasAny = true;
                $dateKey = substr($row->lesson_date, 0, 10);
                $comboKey = $row->group_id . '|' . $row->subject_id . '|' . $row->semester_code;
                $datePairKey = $dateKey . '_' . $row->lesson_pair_code;

                $scheduleGroupIds[$row->group_id] = true;
                $scheduleSubjectIds[$row->subject_id] = true;
                $scheduleSemesterCodes[$row->semester_code] = true;

                $columns[$comboKey][$datePairKey] = true;

                if (!isset($minDates[$comboKey]) || $dateKey < $minDates[$comboKey]) {
                    $minDates[$comboKey] = $dateKey;
                }
            }

            if (!$hasAny) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            // 2-QADAM: Talabalar
            $studentQuery = DB::table('students as s')
                ->whereIn('s.group_id', $tutorGroupIds)
                ->select('s.hemis_id', 's.group_id');

            if ($request->filled('student_name')) {
                $studentQuery->where('s.full_name', 'like', '%' . $request->student_name . '%');
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
                $groupHemisIdForStudents = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
                if ($groupHemisIdForStudents && in_array($groupHemisIdForStudents, $tutorGroupIds)) {
                    $studentQuery->where('s.group_id', $groupHemisIdForStudents);
                }
            }

            if ($request->filled('education_type')) {
                $eduGroupIds = DB::table('groups')
                    ->whereIn('curriculum_hemis_id',
                        Curriculum::where('education_type_code', $request->education_type)
                            ->pluck('curricula_hemis_id')
                    )
                    ->pluck('group_hemis_id')
                    ->toArray();
                $studentQuery->whereIn('s.group_id', $eduGroupIds);
            }

            $allowedSubjectIds = null;
            if ($request->filled('department')) {
                $allowedSubjectIds = DB::table('curriculum_subjects')
                    ->where('department_id', $request->department)
                    ->pluck('subject_id')
                    ->unique()
                    ->toArray();
            }

            $scheduleGroupIdsList = array_keys($scheduleGroupIds);
            $studentQuery->whereIn('s.group_id', $scheduleGroupIdsList);
            $students = $studentQuery->get();

            if ($students->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            $studentGroupMap = [];
            foreach ($students as $st) {
                $studentGroupMap[$st->hemis_id] = $st->group_id;
            }
            $studentHemisIds = array_keys($studentGroupMap);

            $validSubjectIds = array_keys($scheduleSubjectIds);
            $validSemesterCodes = array_keys($scheduleSemesterCodes);

            if ($allowedSubjectIds !== null) {
                $validSubjectIds = array_intersect($validSubjectIds, $allowedSubjectIds);
                if (empty($validSubjectIds)) {
                    return response()->json(['data' => [], 'total' => 0]);
                }
            }

            // 3-QADAM: Baholar
            $gradesQuery = DB::table('student_grades')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('subject_id', $validSubjectIds)
                ->whereIn('semester_code', $validSemesterCodes)
                ->whereNotIn('training_type_name', $excludedNames)
                ->where(function ($q) {
                    $q->whereNotNull('grade')->orWhereNotNull('retake_grade');
                })
                ->whereNotNull('lesson_date')
                ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'grade', 'lesson_date', 'lesson_pair_code', 'retake_grade', 'status', 'reason');

            if ($dateFrom) $gradesQuery->where('lesson_date', '>=', $dateFrom);
            if ($dateTo) $gradesQuery->where('lesson_date', '<=', $dateTo);

            $cutoffDate = $dateTo ?? Carbon::now('Asia/Tashkent')->format('Y-m-d');
            $gradesByDay = [];
            $studentSubjects = [];

            foreach ($gradesQuery->cursor() as $g) {
                $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
                if (!$groupId) continue;

                $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
                $minDate = $minDates[$comboKey] ?? null;
                if (!$minDate) continue;

                $dateKey = substr($g->lesson_date, 0, 10);
                if ($dateKey < $minDate) continue;

                $datePairKey = $dateKey . '_' . $g->lesson_pair_code;
                $columns[$comboKey][$datePairKey] = true;

                $gradeKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $dateKey;
                $effectiveGrade = $this->getEffectiveGradeForJn($g);
                if ($effectiveGrade !== null) {
                    $gradesByDay[$gradeKey][$g->lesson_pair_code] = $effectiveGrade;
                }

                $ssKey = $g->student_hemis_id . '|' . $g->subject_id;
                if (!isset($studentSubjects[$ssKey])) {
                    $studentSubjects[$ssKey] = [
                        'student_hemis_id' => $g->student_hemis_id,
                        'subject_id' => $g->subject_id,
                        'subject_name' => $g->subject_name,
                        'combo_key' => $comboKey,
                    ];
                }
            }

            $pairsPerDay = [];
            $lessonDates = [];

            foreach ($columns as $comboKey => $datePairs) {
                $datesSet = [];
                foreach ($datePairs as $datePair => $v) {
                    $dateKey = substr($datePair, 0, 10);
                    $ppdKey = $comboKey . '|' . $dateKey;
                    $pairsPerDay[$ppdKey] = ($pairsPerDay[$ppdKey] ?? 0) + 1;
                    $datesSet[$dateKey] = true;
                }
                $dates = array_keys($datesSet);
                sort($dates);
                $lessonDates[$comboKey] = $dates;
            }

            $results = [];
            foreach ($studentSubjects as $ssKey => $info) {
                $comboKey = $info['combo_key'];
                $comboLessonDates = $lessonDates[$comboKey] ?? [];

                $dailySum = 0;
                $daysForAverage = 0;

                foreach ($comboLessonDates as $dateKey) {
                    if ($dateKey > $cutoffDate) continue;

                    $gradeKey = $info['student_hemis_id'] . '|' . $info['subject_id'] . '|' . $dateKey;
                    $dayGrades = $gradesByDay[$gradeKey] ?? [];

                    if (empty($dayGrades)) continue;

                    $gradeSum = array_sum($dayGrades);
                    $dailyAvg = round($gradeSum / count($dayGrades), 0, PHP_ROUND_HALF_UP);

                    $dailySum += $dailyAvg;
                    $daysForAverage++;
                }

                $jnAverage = $daysForAverage > 0
                    ? round($dailySum / $daysForAverage, 0, PHP_ROUND_HALF_UP)
                    : 0;

                $comboParts = explode('|', $comboKey);
                $results[$ssKey] = [
                    'student_hemis_id' => $info['student_hemis_id'],
                    'subject_id' => $info['subject_id'],
                    'subject_name' => $info['subject_name'],
                    'avg_grade' => $jnAverage,
                    'grades_count' => $daysForAverage,
                    'group_id' => $comboParts[0] ?? '',
                    'semester_code' => $comboParts[2] ?? '',
                ];
            }

            $hemisIds = array_unique(array_column($results, 'student_hemis_id'));
            $studentInfo = [];
            if (!empty($hemisIds)) {
                $studentInfo = DB::table('students')
                    ->whereIn('hemis_id', $hemisIds)
                    ->select('hemis_id', 'full_name', 'department_name', 'specialty_name', 'level_name', 'semester_name', 'group_name')
                    ->get()
                    ->keyBy('hemis_id');
            }

            $groupHemisIdsResult = array_unique(array_column($results, 'group_id'));
            $groupIdMap = [];
            if (!empty($groupHemisIdsResult)) {
                $groupIdMap = DB::table('groups')
                    ->whereIn('group_hemis_id', $groupHemisIdsResult)
                    ->pluck('id', 'group_hemis_id')
                    ->toArray();
            }

            $finalResults = [];
            foreach ($results as $r) {
                $mappedGroupId = $groupIdMap[$r['group_id']] ?? null;
                if ($mappedGroupId === null) continue;
                $st = $studentInfo[$r['student_hemis_id']] ?? null;
                $finalResults[] = [
                    'full_name' => $st->full_name ?? 'Noma\'lum',
                    'department_name' => $st->department_name ?? '-',
                    'specialty_name' => $st->specialty_name ?? '-',
                    'level_name' => $st->level_name ?? '-',
                    'semester_name' => $st->semester_name ?? '-',
                    'group_name' => $st->group_name ?? '-',
                    'subject_name' => $r['subject_name'],
                    'avg_grade' => $r['avg_grade'],
                    'grades_count' => $r['grades_count'],
                    'group_id' => $mappedGroupId,
                    'subject_id' => $r['subject_id'],
                    'semester_code' => $r['semester_code'],
                    'minimum_limit' => MarkingSystemScore::getByStudentHemisId($r['student_hemis_id'])->minimum_limit,
                ];
            }

            $sortColumn = $request->get('sort', 'avg_grade');
            $sortDirection = $request->get('direction', 'desc');

            usort($finalResults, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';
                $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            if ($request->get('export') === 'excel') {
                return $this->exportJnExcel($finalResults);
            }

            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);
            $total = count($finalResults);
            $offset = ($page - 1) * $perPage;
            $pageData = array_slice($finalResults, $offset, $perPage);

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
        } catch (\Throwable $e) {
            Log::error('Tutor JN Report error: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    private function exportJnExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('JN hisobot');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh', 'Fan', "O'rtacha baho", 'Darslar soni'];
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
            $sheet->setCellValue([7, $row], $r['group_name']);
            $sheet->setCellValue([8, $row], $r['subject_name']);
            $sheet->setCellValue([9, $row], $r['avg_grade']);
            $sheet->setCellValue([10, $row], $r['grades_count']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 15, 35, 14, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'JN_hisobot_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'jn_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Cascading dropdown: Yo'nalishlar (faqat tutor guruhlaridagi)
     */
    public function jnGetSpecialties(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();
        if (empty($tutorGroupIds)) return response()->json([]);

        $query = DB::table('groups as g')
            ->join('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->whereIn('g.group_hemis_id', $tutorGroupIds);

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('g.department_hemis_id', $faculty->department_hemis_id);
            }
        }

        return $query->select('sp.specialty_hemis_id', 'sp.name')
            ->groupBy('sp.specialty_hemis_id', 'sp.name')
            ->orderBy('sp.name')
            ->get()
            ->pluck('name', 'specialty_hemis_id');
    }

    /**
     * Cascading dropdown: Kurslar (faqat tutor guruhlaridagi)
     */
    public function jnGetLevelCodes(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();
        if (empty($tutorGroupIds)) return response()->json([]);

        $curriculumHemisIds = DB::table('groups')
            ->whereIn('group_hemis_id', $tutorGroupIds)
            ->pluck('curriculum_hemis_id')
            ->unique();

        $query = Semester::whereIn('curriculum_hemis_id', $curriculumHemisIds);

        return $query->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get()
            ->pluck('level_name', 'level_code');
    }

    /**
     * Cascading dropdown: Semestrlar (faqat tutor guruhlaridagi)
     */
    public function jnGetSemesters(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();
        if (empty($tutorGroupIds)) return response()->json([]);

        $curriculumHemisIds = DB::table('groups')
            ->whereIn('group_hemis_id', $tutorGroupIds)
            ->pluck('curriculum_hemis_id')
            ->unique();

        $query = Semester::whereIn('curriculum_hemis_id', $curriculumHemisIds);

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        return $query->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->get()
            ->pluck('name', 'code');
    }

    /**
     * Cascading dropdown: Fanlar (faqat tutor guruhlaridagi)
     */
    public function jnGetSubjects(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();
        if (empty($tutorGroupIds)) return response()->json([]);

        $query = DB::table('curriculum_subjects as cs')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->whereIn('g.group_hemis_id', $tutorGroupIds);

        if ($request->filled('group_id')) {
            $selectedGroup = Group::find($request->group_id);
            if ($selectedGroup) {
                $query->where('g.group_hemis_id', $selectedGroup->group_hemis_id);
            }
        }
        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('g.department_hemis_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty_id')) {
            $query->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->get('current_semester') == '1') {
            $query->where('s.current', true);
        }

        $excludedPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];
        foreach ($excludedPatterns as $pattern) {
            $query->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        return $query->select('cs.subject_id', 'cs.subject_name')
            ->groupBy('cs.subject_id', 'cs.subject_name')
            ->orderBy('cs.subject_name')
            ->get()
            ->pluck('subject_name', 'subject_id');
    }

    /**
     * Cascading dropdown: Guruhlar (faqat tutor guruhlari)
     */
    public function jnGetGroups(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();
        if (empty($tutorGroupIds)) return response()->json([]);

        $query = Group::whereIn('group_hemis_id', $tutorGroupIds)->where('active', true);

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty_id')) {
            $query->where('specialty_hemis_id', $request->specialty_id);
        }

        if ($request->filled('subject_id') || $request->filled('semester_code') || $request->filled('level_code') || $request->get('current_semester') == '1') {
            $allowedHemisIds = DB::table('curriculum_subjects as cs')
                ->join('groups as g', 'g.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                ->join('semesters as s', function ($join) {
                    $join->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->whereIn('g.group_hemis_id', $tutorGroupIds);

            if ($request->filled('subject_id')) {
                $allowedHemisIds->where('cs.subject_id', $request->subject_id);
            }
            if ($request->filled('semester_code')) {
                $allowedHemisIds->where('cs.semester_code', $request->semester_code);
            }
            if ($request->filled('level_code')) {
                $allowedHemisIds->where('s.level_code', $request->level_code);
            }
            if ($request->get('current_semester') == '1') {
                $allowedHemisIds->where('s.current', true);
            }

            $allowedHemisIds = $allowedHemisIds->pluck('g.group_hemis_id')->unique()->toArray();
            $query->whereIn('group_hemis_id', $allowedHemisIds);
        }

        return $query->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');
    }

    /**
     * 74 soat dars qoldirish hisoboti
     */
    public function absenceReport74(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);

        $query = DB::table('attendances as a')
            ->join('students as s', 's.hemis_id', '=', 'a.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->whereIn('s.group_id', $groupIds)
            ->where('g.active', true);

        // Joriy semestr
        $query->whereExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('semesters as sem')
                ->whereColumn('sem.curriculum_hemis_id', 'g.curriculum_hemis_id')
                ->whereColumn('sem.code', 'a.semester_code')
                ->where('sem.current', true);
        });

        $rows = $query
            ->select(
                'a.student_hemis_id',
                's.full_name',
                's.student_id_number',
                's.group_name',
                's.image',
                DB::raw('SUM(a.absent_off) as unexcused_hours'),
                DB::raw('SUM(a.absent_on) as excused_hours'),
                DB::raw('SUM(a.absent_on + a.absent_off) as total_hours'),
                DB::raw('COUNT(DISTINCT DATE(a.lesson_date)) as total_days')
            )
            ->groupBy('a.student_hemis_id', 's.full_name', 's.student_id_number', 's.group_name', 's.image')
            ->having('total_hours', '>=', 74)
            ->orderByDesc('total_hours')
            ->get();

        return view('teacher.reports.absence-74', compact('tutorGroups', 'rows'));
    }

    /**
     * 25% sababsiz hisoboti (admin mantiqi: student_grades reason='absent')
     */
    public function absenceReport25(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);
        $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

        // Schedule kombinatsiyalarini olish (joriy semestr)
        $scheduleCombos = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_name', $excludedNames)
            ->whereNotNull('sch.lesson_date')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at');

        foreach ($excludedSubjectPatterns as $pattern) {
            $scheduleCombos->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        $scheduleCombos = $scheduleCombos
            ->select('sch.group_id', 'sch.subject_id', 'sch.subject_name', 'sch.semester_code')
            ->groupBy('sch.group_id', 'sch.subject_id', 'sch.subject_name', 'sch.semester_code')
            ->get();

        if ($scheduleCombos->isEmpty()) {
            return view('teacher.reports.absence-25', compact('tutorGroups'))->with('results', []);
        }

        $scheduleGroupIds = $scheduleCombos->pluck('group_id')->unique()->toArray();
        $validSubjectIds = $scheduleCombos->pluck('subject_id')->unique()->toArray();
        $validSemesterCodes = $scheduleCombos->pluck('semester_code')->unique()->toArray();

        // Auditoriya soatlarini curriculum_subjects dan olish (admin mantiqi)
        $groupsData = DB::table('groups')
            ->whereIn('group_hemis_id', $scheduleGroupIds)
            ->select('group_hemis_id', 'curriculum_hemis_id')
            ->get();

        $groupCurriculumMap = [];
        foreach ($groupsData as $g) {
            $groupCurriculumMap[$g->group_hemis_id] = $g->curriculum_hemis_id;
        }

        $comboKeys = [];
        foreach ($scheduleCombos as $row) {
            $comboKeys[$row->group_id . '|' . $row->subject_id . '|' . $row->semester_code] = [
                'group_id' => $row->group_id,
                'subject_id' => $row->subject_id,
                'semester_code' => $row->semester_code,
                'subject_name' => $row->subject_name,
            ];
        }

        $curriculumSubjects = CurriculumSubject::whereIn('curricula_hemis_id', array_unique(array_values($groupCurriculumMap)))
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->get()
            ->keyBy(function ($item) {
                return $item->curricula_hemis_id . '|' . $item->subject_id . '|' . $item->semester_code;
            });

        $nonAuditoriumCodes = ['17'];
        $auditoryHours = [];
        foreach ($comboKeys as $comboKey => $combo) {
            $currHemisId = $groupCurriculumMap[$combo['group_id']] ?? null;
            if (!$currHemisId) continue;

            $csKey = $currHemisId . '|' . $combo['subject_id'] . '|' . $combo['semester_code'];
            $cs = $curriculumSubjects[$csKey] ?? null;
            if (!$cs) continue;

            $hours = 0;
            if (is_array($cs->subject_details)) {
                foreach ($cs->subject_details as $detail) {
                    $trainingCode = (string) ($detail['trainingType']['code'] ?? '');
                    if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                        $hours += (float) ($detail['academic_load'] ?? 0);
                    }
                }
            }
            if ($hours <= 0) {
                $hours = (float) ($cs->total_acload ?? 0);
            }

            $auditoryHours[$comboKey] = $hours;
        }

        // Talabalar
        $students = Student::whereIn('group_id', $scheduleGroupIds)->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();

        if (empty($studentHemisIds)) {
            return view('teacher.reports.absence-25', compact('tutorGroups'))->with('results', []);
        }

        $studentGroupMap = [];
        foreach ($students as $st) {
            $studentGroupMap[$st->hemis_id] = $st->group_id;
        }

        // student_grades dan davomatni olish (admin mantiqi: reason='absent')
        $minScheduleDate = DB::table('schedules')
            ->where('education_year_current', true)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->min('lesson_date');

        $studentSubjectData = [];
        foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
            $gradesChunk = DB::table('student_grades')
                ->whereIn('student_hemis_id', $hemisChunk)
                ->whereIn('subject_id', $validSubjectIds)
                ->whereIn('semester_code', $validSemesterCodes)
                ->whereNotIn('training_type_name', $excludedNames)
                ->whereNotNull('lesson_date')
                ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'grade', 'lesson_date', 'reason', 'status')
                ->get();

            foreach ($gradesChunk as $g) {
                $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
                if (!$groupId) continue;

                $ssKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code;
                $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;

                if (!isset($studentSubjectData[$ssKey])) {
                    $studentSubjectData[$ssKey] = [
                        'student_hemis_id' => $g->student_hemis_id,
                        'subject_id' => $g->subject_id,
                        'subject_name' => $g->subject_name,
                        'semester_code' => $g->semester_code,
                        'combo_key' => $comboKey,
                        'group_id' => $groupId,
                        'total_absent_hours' => 0,
                        'unexcused_absent_hours' => 0,
                    ];
                }

                if ($g->reason === 'absent') {
                    $studentSubjectData[$ssKey]['total_absent_hours'] += 2;
                    if ($g->status !== 'retake') {
                        $studentSubjectData[$ssKey]['unexcused_absent_hours'] += 2;
                    }
                }
            }
            unset($gradesChunk);
        }

        // Natijalarni hisoblash
        $now = Carbon::now('Asia/Tashkent');
        $spravkaDays = (int) Setting::get('spravka_deadline_days', 10);

        $results = [];
        foreach ($studentSubjectData as $data) {
            $comboKey = $data['combo_key'];
            $totalAuditoryHours = $auditoryHours[$comboKey] ?? 0;

            if ($totalAuditoryHours <= 0) continue;

            $unexcusedPercent = round(($data['unexcused_absent_hours'] / $totalAuditoryHours) * 100);

            if ($unexcusedPercent < 25) continue;

            $studentInfo = $students->firstWhere('hemis_id', $data['student_hemis_id']);

            $results[] = [
                'full_name' => $studentInfo->full_name ?? 'Noma\'lum',
                'student_id' => $studentInfo->student_id_number ?? '-',
                'group_name' => $studentInfo->group_name ?? '-',
                'image' => $studentInfo->image ?? null,
                'subject_name' => $data['subject_name'],
                'unexcused_hours' => $data['unexcused_absent_hours'],
                'total_absent_hours' => $data['total_absent_hours'],
                'auditory_hours' => $totalAuditoryHours,
                'percentage' => $unexcusedPercent,
            ];
        }

        usort($results, fn($a, $b) => $b['percentage'] <=> $a['percentage']);

        return view('teacher.reports.absence-25', compact('tutorGroups', 'results'));
    }

    /**
     * Qarzdorlar hisoboti — admin uslubida (filterlar + AJAX jadval), faqat
     * tyutorning o'z guruhlari kesimida. Sahifa faqat filtr UI ni chizadi;
     * ma'lumotlar debtorsReportData orqali keladi.
     */
    public function debtorsReport(Request $request)
    {
        $tutorGroupIds = $this->getTutorGroupIds();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = null;

        // Faqat tyutorga tegishli fakultetlar
        $facultyHemisIds = DB::table('groups')
            ->whereIn('group_hemis_id', $tutorGroupIds ?: [0])
            ->pluck('department_hemis_id')
            ->unique();
        $faculties = Department::whereIn('department_hemis_id', $facultyHemisIds)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Faqat tyutor guruhlaridagi talabalar bo'yicha holat/toifa ro'yxatlari
        $studentStatuses = DB::table('students')
            ->whereIn('group_id', $tutorGroupIds ?: [0])
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        $studentTypes = DB::table('students')
            ->whereIn('group_id', $tutorGroupIds ?: [0])
            ->select('student_type_code', 'student_type_name')
            ->whereNotNull('student_type_code')
            ->groupBy('student_type_code', 'student_type_name')
            ->orderBy('student_type_name')
            ->get();

        return view('teacher.reports.debtors', compact(
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'studentStatuses',
            'studentTypes'
        ));
    }

    /**
     * AJAX: qarzdorlar hisobot ma'lumotlari — admin ReportController::debtorsReportData
     * bilan aynan bir xil natija, faqat tyutorning o'z guruhlari bilan cheklangan.
     */
    public function debtorsReportData(Request $request)
    {
        try {
            @ini_set('memory_limit', '1024M');
            @set_time_limit(180);

            $tutorGroupIds = $this->getTutorGroupIds();
            if (empty($tutorGroupIds)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            // Bo'sh yoki "Barchasi" (0) tanlansa — barcha qarzdorlarni (>=1 fan) ko'rsatamiz
            $minDebtRaw = $request->get('min_debt_count', 4);
            $minDebtCount = ($minDebtRaw === '' || $minDebtRaw === null) ? 1 : (int) $minDebtRaw;
            if ($minDebtCount < 1) {
                $minDebtCount = 1;
            }
            $showCurrentSemester = $request->get('current_semester', '0') == '1';

            // Talabalar ro'yxati — faqat tyutor guruhlari + admin bilan bir xil filtrlar
            $studentQuery = DB::table('students as s')
                ->whereIn('s.group_id', $tutorGroupIds)
                ->whereNotNull('s.curriculum_id')
                ->select('s.hemis_id', 's.full_name', 's.student_id_number',
                    's.department_name', 's.specialty_name', 's.level_name',
                    's.semester_name', 's.semester_code', 's.group_name',
                    's.group_id', 's.curriculum_id', 's.image',
                    's.student_type_code', 's.student_type_name');

            // Guruh filtri — faqat tyutorning o'z guruhi tanlanishi mumkin
            if ($request->filled('group')) {
                $group = \App\Models\Group::find($request->group);
                if ($group && in_array($group->group_hemis_id, $tutorGroupIds)) {
                    $studentQuery->where('s.group_id', $group->group_hemis_id);
                }
            }
            if ($request->filled('student_status')) {
                $studentQuery->where('s.student_status_code', $request->student_status);
            }
            if ($request->filled('student_name')) {
                $studentQuery->where('s.full_name', 'like', '%' . $request->student_name . '%');
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
            if ($request->filled('education_type')) {
                $studentQuery->where('s.education_type_code', $request->education_type);
            }
            if ($request->filled('student_type')) {
                $studentQuery->where('s.student_type_code', $request->student_type);
            }

            $students = $studentQuery->get();
            if ($students->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $studentSemCodeMap = $students->pluck('semester_code', 'hemis_id')
                ->map(fn($v) => (string) $v)->filter()->toArray();

            // Joriy semestr xavflari (jurnaldan) — xato bo'lsa asosiy ro'yxat ko'rsatiladi
            try {
                $currentRisksMap = $this->getCurrentSemesterRisks($studentHemisIds, $studentSemCodeMap);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Tyutor joriy semestr xavflari xatolik: ' . $e->getMessage());
                $currentRisksMap = [];
            }

            // O'tgan semestr qarzlari — admin bilan aynan bir xil mantiq (trait)
            $finalResults = $this->computeDebtorResults($students, $minDebtCount, $showCurrentSemester, $currentRisksMap);

            if (empty($finalResults)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            // Saralash
            $sortColumn = $request->get('sort', 'debt_count');
            $sortDirection = $request->get('direction', 'desc');
            usort($finalResults, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';
                $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            // Sahifalash
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 50);
            $total = count($finalResults);
            $offset = ($page - 1) * $perPage;
            $pageData = array_slice($finalResults, $offset, $perPage);

            foreach ($pageData as $i => &$item) {
                $item['row_num'] = $offset + $i + 1;
            }
            unset($item);

            return response()->json([
                'data' => $pageData,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Tyutor debtors report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Joriy semestr bo'yicha xavf ostidagi talabalarni student_grades dan hisoblash.
     * Qaytaradi: [hemis_id => [['subject_name'=>..., 'reasons'=>[...]], ...]]
     */
    private function getCurrentSemesterRisks(array $studentHemisIds, array $studentSemCodesMap = []): array
    {
        if (empty($studentHemisIds)) return [];

        if (!empty($studentSemCodesMap)) {
            $currentSemesterCodes = array_values(array_unique(array_map('strval', $studentSemCodesMap)));
        } else {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->toArray();
        }

        if (empty($currentSemesterCodes)) return [];

        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

        // Barcha joriy semestr student_grades yozuvlari
        $grades = collect();
        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            $chunk_grades = DB::table('student_grades')
                ->whereIn('student_hemis_id', $chunk)
                ->whereIn('semester_code', $currentSemesterCodes)
                ->whereNull('deleted_at')
                ->select(
                    'student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'training_type_code', 'grade', 'retake_grade', 'status', 'reason',
                    $hasAttemptCol ? 'attempt' : DB::raw('1 as attempt'),
                    'lesson_date', 'education_year_code'
                )
                ->get();
            $grades = $grades->merge($chunk_grades);
        }

        if ($grades->isEmpty()) return [];

        // Biriktirilganlik (enrollment) — student_subjects + JORIY O'QUV YILI bo'yicha.
        // Tiklangan talaba bir semestrni 2 marta o'qishi mumkin; faqat eng so'nggi
        // (joriy) o'quv yili biriktirilgan fanlar hozir o'qilayotgan fanlardir.
        $curYear = [];
        $hasEnrollment = [];
        $ssRowsAll = collect();
        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            $ssRows = DB::table('student_subjects')
                ->whereIn('student_hemis_id', $chunk)
                ->whereIn('semester_id', $currentSemesterCodes)
                ->whereNotNull('subject_id')
                ->select('student_hemis_id', 'semester_id', 'subject_id', 'education_year')
                ->get();
            $ssRowsAll = $ssRowsAll->merge($ssRows);
            foreach ($ssRows as $sr) {
                $hid = $sr->student_hemis_id;
                $hasEnrollment[$hid] = true;
                $y = (string) $sr->education_year;
                if ($y !== '' && (!isset($curYear[$hid]) || $y > $curYear[$hid])) {
                    $curYear[$hid] = $y;
                }
            }
        }
        $enrolledCur = [];
        foreach ($ssRowsAll as $sr) {
            $hid = $sr->student_hemis_id;
            $cy = $curYear[$hid] ?? null;
            if ($cy === null || (string) $sr->education_year === '' || (string) $sr->education_year === $cy) {
                $enrolledCur[$hid][$sr->subject_id] = true;
            }
        }

        // Baholarni joriy o'quv yili bo'yicha tozalash (eski yil baholari chiqarib tashlanadi).
        $grades = $grades->filter(function ($g) use ($curYear) {
            $cy = $curYear[$g->student_hemis_id] ?? null;
            if ($cy === null) return true;
            $gy = (string) ($g->education_year_code ?? '');
            if ($gy === '') return true;
            return $gy === $cy;
        });

        if ($grades->isEmpty()) return [];

        // Sababli absent oralilqlari (AbsenceExcuse — sana oralig'i bo'yicha, fan emas)
        $hasExcuseTable = \Illuminate\Support\Facades\Schema::hasTable('absence_excuses');
        $excuseRanges = [];
        if ($hasExcuseTable) {
            $excused = DB::table('absence_excuses')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('status', 'approved')
                ->select('student_hemis_id', 'start_date', 'end_date')
                ->get();
            foreach ($excused as $e) {
                $excuseRanges[$e->student_hemis_id][] = [
                    'start' => substr((string) $e->start_date, 0, 10),
                    'end' => substr((string) $e->end_date, 0, 10),
                ];
            }
        }

        // --- Davomat (jurnal mantig'i): attendances.absent_off soatlari / auditoriya soati ---
        // Jurnal show sahifasi davomatni dars SONIDAN emas, balki QOLDIRILGAN SOATLARdan
        // hisoblaydi: SUM(absent_off) / auditoriumHours * 100. MT/ON/OSKI/Test (99,100,101,102)
        // turlari va tasdiqlangan sababli ariza sanalari chiqarib tashlanadi.
        $subjectIdsForAtt = $grades->pluck('subject_id')->filter()->unique()->values()->all();

        // 1) Har talaba+fan+semestr uchun sababsiz qoldirilgan soat.
        //    Tasdiqlangan sababli ariza sanalari SQL korrelyatsiyalangan subso'rov
        //    o'rniga PHP tomonda ($excuseRanges) filtrlanadi — DATE() funksiyasi bilan
        //    indekssiz korrelyatsiyali subso'rov katta talaba to'plamida sahifani
        //    juda sekinlashtirib (timeout) yuboradi.
        $absentHours = [];
        if (!empty($subjectIdsForAtt)) {
            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $attRows = DB::table('attendances')
                    ->whereIn('student_hemis_id', $chunk)
                    ->whereIn('semester_code', $currentSemesterCodes)
                    ->whereIn('subject_id', $subjectIdsForAtt)
                    ->whereNotIn('training_type_code', [99, 100, 101, 102])
                    ->where('absent_off', '>', 0)
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'education_year_code', 'lesson_date', 'absent_off')
                    ->get();
                foreach ($attRows as $ar) {
                    // Joriy o'quv yili filtri (tiklangan talaba uchun eski yil qoldirishlarini chiqarib tashlash)
                    $cy = $curYear[$ar->student_hemis_id] ?? null;
                    $ey = (string) ($ar->education_year_code ?? '');
                    if ($cy !== null && $ey !== '' && $ey !== $cy) continue;
                    // Tasdiqlangan sababli ariza oralig'iga tushsa — sababli, hisobga olinmaydi
                    if ($hasExcuseTable && $this->isDateExcused($ar->student_hemis_id, $ar->lesson_date, $excuseRanges)) {
                        continue;
                    }
                    $k = $ar->student_hemis_id . '|' . $ar->subject_id . '|' . $ar->semester_code;
                    $absentHours[$k] = ($absentHours[$k] ?? 0) + (float) $ar->absent_off;
                }
            }
        }

        // 2) Talaba -> o'quv reja (curricula) xaritasi
        $stuCurricula = DB::table('students')
            ->whereIn('students.hemis_id', $studentHemisIds)
            ->leftJoin('groups', 'students.group_id', '=', 'groups.group_hemis_id')
            ->select('students.hemis_id', 'groups.curriculum_hemis_id')
            ->pluck('curriculum_hemis_id', 'students.hemis_id')
            ->toArray();

        // Talaba -> guruh (group_hemis_id) xaritasi — JN ni jurnal mantig'ida hisoblash uchun
        $stuGroup = DB::table('students')
            ->whereIn('hemis_id', $studentHemisIds)
            ->pluck('group_id', 'hemis_id')
            ->toArray();
        $jnGroupCache = [];

        // Sinov fanlari uchun "Sinov (test)" bahosi student_grades'dagi xom
        // urinish yozuvidan emas, sinov_test_grades'dagi qulflangan/override
        // qiymatdan olinadi — bu jurnalda ko'rsatilgan yakuniy natija.
        $sinovGradeMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('sinov_test_grades')) {
            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $sinovRows = DB::table('sinov_test_grades')
                    ->whereIn('student_hemis_id', $chunk)
                    ->get(['student_hemis_id', 'subject_id', 'semester_code', 'default_grade', 'override_grade']);
                foreach ($sinovRows as $sr) {
                    $val = $sr->override_grade !== null ? (float) $sr->override_grade : ((float) $sr->default_grade);
                    $sinovGradeMap[$sr->student_hemis_id . '|' . $sr->subject_id . '|' . $sr->semester_code] = $val;
                }
            }
        }

        // 3) Auditoriya soatlari: [curricula|subject|sem] va fallback [subject|sem]
        $auditMap = [];
        $auditAnyMap = [];
        if (!empty($subjectIdsForAtt)) {
            $csRows = \App\Models\CurriculumSubject::whereIn('semester_code', $currentSemesterCodes)
                ->whereIn('subject_id', $subjectIdsForAtt)
                ->orderByDesc('is_active')
                ->get(['subject_id', 'semester_code', 'curricula_hemis_id', 'total_acload', 'subject_details']);
            foreach ($csRows as $cs) {
                $h = 0.0;
                if (is_array($cs->subject_details)) {
                    foreach ($cs->subject_details as $d) {
                        $code = (string) (($d['trainingType'] ?? [])['code'] ?? '');
                        if ($code !== '' && $code !== '17') {
                            $h += (float) ($d['academic_load'] ?? 0);
                        }
                    }
                }
                if ($h <= 0) $h = (float) ($cs->total_acload ?? 0);
                $kc = $cs->curricula_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code;
                if (!isset($auditMap[$kc])) $auditMap[$kc] = $h;
                $ka = $cs->subject_id . '|' . $cs->semester_code;
                if (!isset($auditAnyMap[$ka])) $auditAnyMap[$ka] = $h;
            }
        }

        // Talaba+fan bo'yicha guruhlash
        $grouped = $grades->groupBy(fn($g) => $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code);

        $risks = [];

        foreach ($grouped as $key => $rows) {
            [$hemisId, $subjectId, $semCode] = explode('|', $key, 3);

            // Talabaning o'z semester_code si bilan mos kelmasa — bu fan joriy semestr emas
            if (!empty($studentSemCodesMap)) {
                $studentSemCode = $studentSemCodesMap[$hemisId] ?? null;
                if ($studentSemCode !== null && (string) $semCode !== (string) $studentSemCode) {
                    continue;
                }
            }

            // Biriktirilganlik tekshiruvi: bu fan joriy o'quv yili biriktirilganlar
            // ro'yxatida bo'lmasa — talaba hozir o'qimaydi (tiklangan, eski yil fani),
            // xavf hisoblanmaydi.
            if (($hasEnrollment[$hemisId] ?? false) && !isset($enrolledCur[$hemisId][$subjectId])) {
                continue;
            }

            $subjectName = $rows->first()->subject_name ?? 'Fan';
            $reasons = [];

            // 1. Sinov fanlari uchun jurnaldagi "Sinov (test)" ustuni sinov_test_grades'dan
            // (qulflangan/override qiymat, odatda JN o'rtachasi) keladi — bu joriy
            // haqiqiy natija. student_grades'dagi xom urinish yozuvlari sinov
            // mexanizmidan oldingi eskirgan ma'lumot bo'lishi mumkin.
            $sinovKey = $hemisId . '|' . $subjectId . '|' . $semCode;
            if (isset($sinovGradeMap[$sinovKey])) {
                if ($sinovGradeMap[$sinovKey] < 60) {
                    $reasons[] = '1-urinish: V<60';
                }
            } else {
                // OSKI/Test (imtihon urinishlari) — faqat ENG OXIRGI (sana bo'yicha) yozuvga qaraladi.
                // "attempt" raqami qayta sinxronlashda barqaror bo'lmasligi mumkun (masalan,
                // tuzatilgan yakuniy baho eski attempt raqami bilan qayta yozilishi mumkin),
                // shuning uchun lesson_date bo'yicha eng so'nggi yozuv joriy holat deb olinadi.
                $examRows = $rows->whereIn('training_type_code', [101, 102]);
                if ($examRows->isNotEmpty()) {
                    $latestRow = $examRows->sortByDesc(fn($r) => (string) ($r->lesson_date ?? ''))->first();
                    $lastBaho = null;
                    $lastAttempt = 1;
                    if ($latestRow) {
                        $lastBaho = $latestRow->retake_grade !== null ? (float)$latestRow->retake_grade : ($latestRow->grade !== null ? (float)$latestRow->grade : null);
                        $lastAttempt = (int) $latestRow->attempt;
                    }
                    if ($lastBaho !== null && $lastBaho < 60) {
                        if ($lastAttempt <= 1) $reasons[] = '1-urinish: V<60';
                        elseif ($lastAttempt === 2) $reasons[] = '2-urinish: V<60';
                        else $reasons[] = 'Akademik qarzdor (3 urinish tugadi)';
                    }
                }
            }

            // 2. MT (training_type_code = 99)
            $mtRows = $rows->where('training_type_code', 99);
            if ($mtRows->isNotEmpty()) {
                $mtGrade = null;
                foreach ($mtRows as $r) {
                    $val = $r->grade !== null ? (float)$r->grade : null;
                    if ($val !== null && ($mtGrade === null || $val > $mtGrade)) {
                        $mtGrade = $val;
                    }
                }
                if ($mtGrade !== null && $mtGrade < 60) {
                    $reasons[] = 'MT<60';
                }
            }

            // 3. JN (kunlik baholar) — jurnaldagi AYNAN bir xil hisob:
            // computeJnAveragesForGroup (schedules rejasi bo'yicha kunlik o'rtacha,
            // yo'qolgan juftlar 0, maxraj = rejalashtirilgan dars kunlari soni).
            $jnHasGrades = $rows->contains(fn($r) =>
                !in_array((int)$r->training_type_code, [11, 99, 100, 101, 102, 103])
                && $r->lesson_date !== null
                && $r->reason !== 'absent'
                && $r->grade !== null
            );
            $gidForJn = $stuGroup[$hemisId] ?? null;
            if ($jnHasGrades && $gidForJn) {
                $jnCacheKey = $gidForJn . '|' . $subjectId . '|' . $semCode;
                if (!array_key_exists($jnCacheKey, $jnGroupCache)) {
                    try {
                        $jnGroupCache[$jnCacheKey] = \App\Http\Controllers\Admin\JournalController::computeJnAveragesForGroup(
                            (string) $subjectId, (string) $semCode, (string) $gidForJn
                        );
                    } catch (\Throwable $e) {
                        $jnGroupCache[$jnCacheKey] = [];
                    }
                }
                $jnVal = $jnGroupCache[$jnCacheKey][$hemisId] ?? null;
                if ($jnVal !== null && $jnVal > 0 && $jnVal < 60) {
                    $reasons[] = 'JN<60 (' . $jnVal . ')';
                }
            }

            // 4. Sababsiz davomat >= 25% (jurnal mantig'i: qoldirilgan soat / auditoriya soati)
            $absH = $absentHours[$hemisId . '|' . $subjectId . '|' . $semCode] ?? 0;
            if ($absH > 0) {
                $cur = $stuCurricula[$hemisId] ?? null;
                $audH = ($cur !== null) ? ($auditMap[$cur . '|' . $subjectId . '|' . $semCode] ?? 0) : 0;
                if ($audH <= 0) {
                    $audH = $auditAnyMap[$subjectId . '|' . $semCode] ?? 0;
                }
                if ($audH > 0) {
                    $pct = round(($absH / $audH) * 100, 2);
                    if ($pct >= 25) {
                        $pctLabel = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');
                        $reasons[] = "Davomat≥25% ({$pctLabel}%)";
                    }
                }
            }

            if (!empty($reasons)) {
                $risks[$hemisId][] = [
                    'subject_name' => $subjectName,
                    'reasons' => $reasons,
                ];
            }
        }

        // Individual grafik bo'yicha belgilangan urinishlar: sana o'tib ketgan lekin
        // student_grades da mos yozuv yo'q — "o'tmagan" hisoblanadi.
        // exam_schedules.student_hemis_id = talaba → shaxsiy resit sana belgilangan.
        // attempt=2: test_resit_date / oski_resit_date
        // attempt=3: test_resit2_date / oski_resit2_date
        if (\Illuminate\Support\Facades\Schema::hasTable('exam_schedules')) {
            $today = now()->format('Y-m-d');
            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $esRows = DB::table('exam_schedules')
                    ->whereIn('student_hemis_id', $chunk)
                    ->whereIn('semester_code', $currentSemesterCodes)
                    ->select(
                        'student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                        'test_resit_date', 'oski_resit_date',
                        'test_resit2_date', 'oski_resit2_date'
                    )
                    ->get();

                // Mavjud student_grades imtihon yozuvlari: [hemis_id|subject_id|semester_code|attempt|type]
                $existingKeys = [];
                foreach ($grades as $g) {
                    if (!in_array((int)$g->training_type_code, [101, 102])) continue;
                    $att = (int) ($g->attempt ?? 1);
                    $existingKeys[$g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code . '|' . $att] = true;
                }

                foreach ($esRows as $es) {
                    $hid = $es->student_hemis_id;
                    $subId = $es->subject_id;
                    $semCode = $es->semester_code;
                    $subName = $es->subject_name ?? 'Fan';

                    // Semester filter per student
                    if (!empty($studentSemCodesMap)) {
                        $stu_sem = $studentSemCodesMap[$hid] ?? null;
                        if ($stu_sem !== null && (string)$semCode !== (string)$stu_sem) continue;
                    }

                    // attempt=2: test yoki oski resit sanasi o'tib ketgan va grade yo'q
                    foreach (['test_resit_date' => 102, 'oski_resit_date' => 101] as $col => $ttCode) {
                        $date = $es->$col ?? null;
                        if ($date === null) continue;
                        $dateStr = substr((string)$date, 0, 10);
                        if ($dateStr > $today) continue;
                        $key2 = $hid . '|' . $subId . '|' . $semCode . '|2';
                        if (!isset($existingKeys[$key2])) {
                            // 2-urinish sanasi o'tib ketgan, lekin baholanmagan
                            $alreadyAdded = false;
                            foreach ($risks[$hid] ?? [] as $r) {
                                if ($r['subject_name'] === $subName) { $alreadyAdded = true; break; }
                            }
                            if (!$alreadyAdded) {
                                $risks[$hid][] = [
                                    'subject_name' => $subName,
                                    'reasons' => ['2-urinish: baholanmagan (grafik o\'tib ketdi)'],
                                ];
                            } else {
                                foreach ($risks[$hid] as &$r) {
                                    if ($r['subject_name'] === $subName) {
                                        if (!in_array('2-urinish: baholanmagan (grafik o\'tib ketdi)', $r['reasons'])) {
                                            $r['reasons'][] = '2-urinish: baholanmagan (grafik o\'tib ketdi)';
                                        }
                                        break;
                                    }
                                }
                                unset($r);
                            }
                        }
                    }

                    // attempt=3: resit2 sanasi o'tib ketgan va grade yo'q
                    foreach (['test_resit2_date' => 102, 'oski_resit2_date' => 101] as $col => $ttCode) {
                        $date = $es->$col ?? null;
                        if ($date === null) continue;
                        $dateStr = substr((string)$date, 0, 10);
                        if ($dateStr > $today) continue;
                        $key3 = $hid . '|' . $subId . '|' . $semCode . '|3';
                        if (!isset($existingKeys[$key3])) {
                            // 3-urinish sanasi o'tib ketgan, lekin baholanmagan → akademik qarzdor
                            $alreadyAdded = false;
                            $reason3 = 'Akademik qarzdor (3-urinish baholanmagan)';
                            foreach ($risks[$hid] ?? [] as $r) {
                                if ($r['subject_name'] === $subName) { $alreadyAdded = true; break; }
                            }
                            if (!$alreadyAdded) {
                                $risks[$hid][] = [
                                    'subject_name' => $subName,
                                    'reasons' => [$reason3],
                                ];
                            } else {
                                foreach ($risks[$hid] as &$r) {
                                    if ($r['subject_name'] === $subName) {
                                        // Agar "2-urinish: V<60" yoki shunga o'xshash sabab bo'lsa — 3-urinish ustunlik qiladi
                                        if (!in_array($reason3, $r['reasons'])) {
                                            $r['reasons'][] = $reason3;
                                        }
                                        break;
                                    }
                                }
                                unset($r);
                            }
                        }
                    }
                }
            }
        }

        return $risks;
    }

    /**
     * Berilgan sana talaba uchun tasdiqlangan sababli ariza oralig'iga tushadimi?
     *
     * @param  mixed  $hemisId      Talaba hemis_id.
     * @param  mixed  $lessonDate   Dars sanasi (Y-m-d yoki datetime).
     * @param  array  $excuseRanges [hemis_id => [['start'=>Y-m-d, 'end'=>Y-m-d], ...]].
     */
    private function isDateExcused($hemisId, $lessonDate, array $excuseRanges): bool
    {
        $ranges = $excuseRanges[$hemisId] ?? null;
        if (empty($ranges) || $lessonDate === null) {
            return false;
        }
        $d = substr((string) $lessonDate, 0, 10);
        if ($d === '') {
            return false;
        }
        foreach ($ranges as $range) {
            if ($d >= $range['start'] && $d <= $range['end']) {
                return true;
            }
        }
        return false;
    }

    /**
     * 5 ga da'vogar talabalar hisoboti (admin mantiqi bilan)
     */
    public function topStudentsReport(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);
        $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];
        $scoreLimit = (int) $request->get('score_limit', 90);

        // Fanlar ro'yxatini olish
        $scheduleSubjects = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_name', $excludedNames)
            ->whereNotNull('sch.lesson_date')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true);

        foreach ($excludedSubjectPatterns as $pattern) {
            $scheduleSubjects->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        $scheduleSubjects = $scheduleSubjects
            ->select('sch.group_id', 'sch.subject_id', 'sch.subject_name')
            ->groupBy('sch.group_id', 'sch.subject_id', 'sch.subject_name')
            ->get();

        $students = Student::whereIn('group_id', $groupIds)->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();

        if (empty($studentHemisIds) || $scheduleSubjects->isEmpty()) {
            return view('teacher.reports.top-students', compact('tutorGroups', 'scoreLimit'))->with('results', []);
        }

        $validSubjectIds = $scheduleSubjects->pluck('subject_id')->unique()->toArray();

        // Baholarni olish (admin mantiqi: status, reason, retake_grade)
        $grades = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereNotIn('training_type_name', $excludedNames)
            ->select('student_hemis_id', 'subject_id', 'grade', 'retake_grade', 'status', 'reason', 'lesson_date')
            ->get();

        $gradeMap = [];
        foreach ($grades as $g) {
            $effectiveGrade = $this->getEffectiveGradeForJn($g);
            if ($effectiveGrade === null) continue;

            $key = $g->student_hemis_id . '|' . $g->subject_id;
            $date = substr($g->lesson_date, 0, 10);
            $gradeMap[$key][$date][] = $effectiveGrade;
        }

        // Guruh nomi map
        $groupNameMap = DB::table('groups')
            ->whereIn('group_hemis_id', $groupIds)
            ->pluck('name', 'group_hemis_id')
            ->toArray();

        $results = [];
        foreach ($students as $student) {
            $groupSubjects = $scheduleSubjects->where('group_id', $student->group_id);
            if ($groupSubjects->isEmpty()) continue;

            $allAbove = true;
            $subjectAvgs = [];
            $hasAnyGrade = false;

            foreach ($groupSubjects as $sub) {
                $key = $student->hemis_id . '|' . $sub->subject_id;
                $dailyGrades = $gradeMap[$key] ?? [];

                if (empty($dailyGrades)) {
                    $allAbove = false;
                    continue;
                }

                $hasAnyGrade = true;
                $dailyAvgs = [];
                foreach ($dailyGrades as $dayGrades) {
                    $dailyAvgs[] = round(array_sum($dayGrades) / count($dayGrades), 0, PHP_ROUND_HALF_UP);
                }
                $avg = round(array_sum($dailyAvgs) / count($dailyAvgs), 0, PHP_ROUND_HALF_UP);
                $subjectAvgs[] = ['name' => $sub->subject_name, 'avg' => $avg];

                if ($avg < $scoreLimit) {
                    $allAbove = false;
                }
            }

            if ($allAbove && $hasAnyGrade) {
                $overallAvg = count($subjectAvgs) > 0
                    ? round(collect($subjectAvgs)->avg('avg'), 1)
                    : 0;

                $results[] = [
                    'full_name' => $student->full_name,
                    'student_id' => $student->student_id_number,
                    'group_name' => $groupNameMap[$student->group_id] ?? $student->group_name,
                    'image' => $student->image,
                    'overall_avg' => $overallAvg,
                    'subjects' => $subjectAvgs,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['overall_avg'] <=> $a['overall_avg']);

        return view('teacher.reports.top-students', compact('tutorGroups', 'results', 'scoreLimit'));
    }

    /**
     * Baho qo'ymaganlar hisoboti
     */
    public function unratedReport(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);
        $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

        // Schedule yozuvlarini olish (o'tgan kunlar uchun)
        $schedules = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_name', $excludedNames)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->whereRaw('DATE(sch.lesson_date) < CURDATE()');

        foreach ($excludedSubjectPatterns as $pattern) {
            $schedules->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        $schedules = $schedules
            ->select('sch.group_id', 'sch.subject_id', 'sch.subject_name',
                'sch.employee_id', 'sch.employee_name',
                DB::raw('DATE(sch.lesson_date) as lesson_date'),
                'sch.lesson_pair_code', 'gr.name as group_name')
            ->get();

        if ($schedules->isEmpty()) {
            return view('teacher.reports.unrated', compact('tutorGroups'))->with('results', []);
        }

        $subjectIds = $schedules->pluck('subject_id')->unique()->toArray();
        $scheduleGroupIds = $schedules->pluck('group_id')->unique()->toArray();

        $students = Student::whereIn('group_id', $scheduleGroupIds)->get();
        $studentMap = $students->groupBy('group_id');

        $existingGrades = DB::table('student_grades')
            ->whereIn('student_hemis_id', $students->pluck('hemis_id')->toArray())
            ->whereIn('subject_id', $subjectIds)
            ->whereNotIn('training_type_name', $excludedNames)
            ->select('student_hemis_id', 'subject_id', DB::raw('DATE(lesson_date) as lesson_date'))
            ->distinct()
            ->get();

        $gradeExists = [];
        foreach ($existingGrades as $eg) {
            $gradeExists[$eg->student_hemis_id . '|' . $eg->subject_id . '|' . $eg->lesson_date] = true;
        }

        $unrated = [];
        $scheduleCombos = $schedules->groupBy(function ($item) {
            return $item->group_id . '|' . $item->subject_id . '|' . $item->lesson_date;
        });

        foreach ($scheduleCombos as $comboKey => $combo) {
            $first = $combo->first();
            $groupStudents = $studentMap[$first->group_id] ?? collect();

            $noGradeCount = 0;
            $totalStudents = $groupStudents->count();

            foreach ($groupStudents as $student) {
                $gKey = $student->hemis_id . '|' . $first->subject_id . '|' . $first->lesson_date;
                if (!isset($gradeExists[$gKey])) {
                    $noGradeCount++;
                }
            }

            if ($noGradeCount > 0 && $totalStudents > 0 && $noGradeCount == $totalStudents) {
                $unrated[] = [
                    'group_name' => $first->group_name,
                    'subject_name' => $first->subject_name,
                    'employee_name' => $first->employee_name ?? '-',
                    'lesson_date' => $first->lesson_date,
                    'no_grade_count' => $noGradeCount,
                    'total_students' => $totalStudents,
                ];
            }
        }

        usort($unrated, function ($a, $b) {
            $cmp = strcmp($b['lesson_date'], $a['lesson_date']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['group_name'], $b['group_name']);
        });

        return view('teacher.reports.unrated', compact('tutorGroups'))->with('results', $unrated);
    }

    /**
     * Kontraktlar hisoboti
     */
    public function contractsReport(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);

        $students = Student::whereIn('group_id', $groupIds)->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();

        $contracts = ContractList::whereIn('student_hemis_id', $studentHemisIds)
            ->orderBy('full_name')
            ->get();

        $studentMap = $students->keyBy('hemis_id');
        foreach ($contracts as $contract) {
            $contract->student_data = $studentMap[$contract->student_hemis_id] ?? null;
        }

        return view('teacher.reports.contracts', compact('tutorGroups', 'contracts'));
    }
}

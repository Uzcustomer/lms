<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
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
     * 4+ qarzdorlar hisoboti
     */
    public function debtorsReport(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);

        $students = DB::table('students as s')
            ->whereIn('s.group_id', $groupIds)
            ->whereNotNull('s.curriculum_id')
            ->select('s.hemis_id', 's.full_name', 's.student_id_number',
                's.group_name', 's.semester_code', 's.curriculum_id', 's.image')
            ->get();

        if ($students->isEmpty()) {
            return view('teacher.reports.debtors', compact('tutorGroups'))->with('results', []);
        }

        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        $curriculumIds = $students->pluck('curriculum_id')->unique()->filter()->values()->toArray();

        // Curriculum subjects
        $currSubjects = DB::table('curriculum_subjects')
            ->whereIn('curricula_hemis_id', $curriculumIds)
            ->where('is_active', true)
            ->where('subject_code', 'not like', '%/%')
            ->select('curricula_hemis_id', 'semester_code', 'subject_id', 'subject_name', 'credit')
            ->distinct()
            ->get();

        // Academic records
        $arRecordsLookup = [];
        foreach (array_chunk($studentHemisIds, 1000) as $chunk) {
            $arRecords = DB::table('academic_records')
                ->whereIn('student_id', $chunk)
                ->select('student_id', 'subject_id', 'semester_id', 'grade', 'retraining_status')
                ->get();

            foreach ($arRecords as $ar) {
                $key = $ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id;
                if (!isset($arRecordsLookup[$key]) || (float) ($ar->grade ?? 0) > (float) ($arRecordsLookup[$key]->grade ?? 0)) {
                    $arRecordsLookup[$key] = $ar;
                }
            }
        }

        $results = [];
        foreach ($students as $st) {
            if (!$st->curriculum_id) continue;

            $subjects = $currSubjects->where('curricula_hemis_id', $st->curriculum_id);
            $debts = [];

            foreach ($subjects as $sub) {
                if ((int) $sub->semester_code >= (int) $st->semester_code) continue;

                $arKey = $st->hemis_id . '|' . $sub->subject_id . '|' . $sub->semester_code;
                $ar = $arRecordsLookup[$arKey] ?? null;

                $isDebt = !$ar
                    || $ar->grade === null
                    || (float) ($ar->grade ?? 0) == 0
                    || (float) ($ar->grade ?? 0) == 2
                    || $ar->retraining_status;

                if ($isDebt) {
                    $debts[] = [
                        'subject_name' => $sub->subject_name,
                        'semester_code' => $sub->semester_code,
                        'credit' => $sub->credit,
                    ];
                }
            }

            if (count($debts) >= 4) {
                $results[] = [
                    'full_name' => $st->full_name,
                    'student_id' => $st->student_id_number,
                    'group_name' => $st->group_name,
                    'image' => $st->image,
                    'debt_count' => count($debts),
                    'debts' => $debts,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['debt_count'] <=> $a['debt_count']);

        return view('teacher.reports.debtors', compact('tutorGroups', 'results'));
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

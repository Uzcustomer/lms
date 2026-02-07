<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Sahifa yuklanishi - faqat filtrlar ko'rsatiladi (ma'lumot so'ralmaydi)
     */
    public function jnReport(Request $request)
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

        return view('admin.reports.jn', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras'
        ));
    }

    /**
     * AJAX: JN hisobot ma'lumotlarini hisoblash
     * Jurnal bilan bir xil formula: kunlik o'rtachalar (half-round-up) → yakuniy o'rtacha (half-round-up)
     */
    public function jnReportData(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Barcha schedule yozuvlarini olish (pairs_per_day hisoblash uchun)
        $scheduleQuery = DB::table('schedules as sch')
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->select('sch.group_id', 'sch.subject_id', 'sch.semester_code', 'sch.lesson_date', 'sch.lesson_pair_code');

        if ($request->get('current_semester', '1') == '1') {
            $scheduleQuery
                ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
                ->join('semesters as sem', function ($join) {
                    $join->on('sem.code', '=', 'sch.semester_code')
                        ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
                })
                ->where('sem.current', true);
        }

        if ($request->filled('semester_code')) {
            $scheduleQuery->where('sch.semester_code', $request->semester_code);
        }

        if ($request->filled('subject')) {
            $scheduleQuery->where('sch.subject_id', $request->subject);
        }

        $scheduleRows = $scheduleQuery->get();

        if ($scheduleRows->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // Schedule ma'lumotlarini tayyorlash: columns, minDates
        $columns = [];    // [combo_key][date_pair] = true
        $minDates = [];   // [combo_key] = eng kichik sana

        foreach ($scheduleRows as $row) {
            $dateKey = substr($row->lesson_date, 0, 10);
            $comboKey = $row->group_id . '|' . $row->subject_id . '|' . $row->semester_code;
            $datePairKey = $dateKey . '_' . $row->lesson_pair_code;

            $columns[$comboKey][$datePairKey] = true;

            if (!isset($minDates[$comboKey]) || $dateKey < $minDates[$comboKey]) {
                $minDates[$comboKey] = $dateKey;
            }
        }

        // 2-QADAM: Talabalar ro'yxatini tayyorlash (filtrlar bilan)
        $studentQuery = DB::table('students as s')->select('s.hemis_id', 's.group_id');

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

        // Kafedra filtri
        $allowedSubjectIds = null;
        if ($request->filled('department')) {
            $allowedSubjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
        }

        $scheduleGroupIds = $scheduleRows->pluck('group_id')->unique()->toArray();
        $studentQuery->whereIn('s.group_id', $scheduleGroupIds);
        $students = $studentQuery->get();

        if ($students->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $studentGroupMap = [];
        foreach ($students as $st) {
            $studentGroupMap[$st->hemis_id] = $st->group_id;
        }
        $studentHemisIds = array_keys($studentGroupMap);

        $validSubjectIds = $scheduleRows->pluck('subject_id')->unique()->toArray();
        $validSemesterCodes = $scheduleRows->pluck('semester_code')->unique()->toArray();

        if ($allowedSubjectIds !== null) {
            $validSubjectIds = array_intersect($validSubjectIds, $allowedSubjectIds);
            if (empty($validSubjectIds)) {
                return response()->json(['data' => [], 'total' => 0]);
            }
        }

        // 3-QADAM: student_grades dan baholarni olish (lesson_pair_code bilan)
        $gradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'grade', 'lesson_date', 'lesson_pair_code')
            ->get();

        // 4-QADAM: Jurnal formulasi bo'yicha hisoblash
        // a) Baho date_pair larini columns ga birlashtirish (jurnal kabi fallback)
        // b) Baholarni kun bo'yicha guruhlash
        $cutoffDate = Carbon::now('Asia/Tashkent')->subDay()->startOfDay()->format('Y-m-d');

        $gradesByDay = [];      // [student|subject|date] => [grade1, ...]
        $studentSubjects = [];  // [student|subject] => info

        foreach ($gradesRaw as $g) {
            $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
            if (!$groupId) continue;

            $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
            $minDate = $minDates[$comboKey] ?? null;
            if (!$minDate) continue;

            $dateKey = substr($g->lesson_date, 0, 10);
            if ($dateKey < $minDate) continue;

            // Baho date_pair ni columns ga birlashtirish (jurnal fallback logikasi)
            $datePairKey = $dateKey . '_' . $g->lesson_pair_code;
            $columns[$comboKey][$datePairKey] = true;

            // Baholarni kun bo'yicha guruhlash
            $gradeKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $dateKey;
            $gradesByDay[$gradeKey][] = $g->grade;

            // Student-subject ma'lumotlarini saqlash
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

        // pairs_per_day va lesson_dates ni yakuniy columns dan hisoblash
        $pairsPerDay = [];   // [combo_key|date] => juftliklar soni
        $lessonDates = [];   // [combo_key] => [date1, date2, ...]

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

        // 5-QADAM: Kunlik o'rtachalar → yakuniy o'rtacha (jurnal formulasi)
        // Har bir kun: round(gradeSum / pairsInDay, 0, PHP_ROUND_HALF_UP)
        // Yakuniy: round(dailySum / totalDays, 0, PHP_ROUND_HALF_UP)
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

                $ppdKey = $comboKey . '|' . $dateKey;
                $pairsInDay = $pairsPerDay[$ppdKey] ?? 1;

                $gradeSum = array_sum($dayGrades);
                $dailyAvg = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);

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

        // Talaba ma'lumotlarini biriktirish
        $hemisIds = array_unique(array_column($results, 'student_hemis_id'));
        $studentInfo = [];
        if (!empty($hemisIds)) {
            $studentInfo = DB::table('students')
                ->whereIn('hemis_id', $hemisIds)
                ->select('hemis_id', 'full_name', 'department_name', 'specialty_name', 'level_name', 'semester_name', 'group_name')
                ->get()
                ->keyBy('hemis_id');
        }

        // group_hemis_id → groups.id map (jurnal URL uchun)
        $groupHemisIds = array_unique(array_column($results, 'group_id'));
        $groupIdMap = [];
        if (!empty($groupHemisIds)) {
            $groupIdMap = DB::table('groups')
                ->whereIn('group_hemis_id', $groupHemisIds)
                ->pluck('id', 'group_hemis_id')
                ->toArray();
        }

        $finalResults = [];
        foreach ($results as $r) {
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
                'group_id' => $groupIdMap[$r['group_id']] ?? $r['group_id'],
                'subject_id' => $r['subject_id'],
                'semester_code' => $r['semester_code'],
            ];
        }

        // Saralash
        $sortColumn = $request->get('sort', 'avg_grade');
        $sortDirection = $request->get('direction', 'desc');

        usort($finalResults, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportExcel($finalResults);
        }

        // Pagination
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $total = count($finalResults);
        $offset = ($page - 1) * $perPage;
        $pageData = array_slice($finalResults, $offset, $perPage);

        // Tartib raqamlarini qo'shish
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

    /**
     * Dars belgilash hisoboti sahifasi
     */
    public function lessonAssignment(Request $request)
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

        return view('admin.reports.lesson-assignment', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras'
        ));
    }

    /**
     * AJAX: Dars belgilash hisobot ma'lumotlarini hisoblash
     */
    public function lessonAssignmentData(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Jadvallardan ma'lumot olish
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $scheduleQuery->where('sem.current', true);
        }

        // Filtrlar
        if ($request->filled('education_type')) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $request->education_type)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $scheduleQuery->whereIn('sch.group_id', $groupIds);
        }

        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $scheduleQuery->where('sch.faculty_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('specialty')) {
            $scheduleQuery->where('g.specialty_hemis_id', $request->specialty);
        }

        if ($request->filled('level_code')) {
            $scheduleQuery->where('sem.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $scheduleQuery->where('sch.semester_code', $request->semester_code);
        }

        if ($request->filled('department')) {
            $scheduleQuery->where('sch.department_id', $request->department);
        }

        if ($request->filled('subject')) {
            $scheduleQuery->where('sch.subject_id', $request->subject);
        }

        if ($request->filled('group')) {
            $scheduleQuery->where('sch.group_id', $request->group);
        }

        if ($request->filled('date_from')) {
            $scheduleQuery->where('sch.lesson_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $scheduleQuery->where('sch.lesson_date', '<=', $request->date_to);
        }

        // Dars jadvalini olish (guruh bo'yicha aggregatsiya)
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
            DB::raw('DATE(sch.lesson_date) as lesson_date_str')
        )->get();

        if ($schedules->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 2-QADAM: schedule_hemis_id lar bo'yicha davomat va baho mavjudligini tekshirish
        $allScheduleIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();

        // Chunk bo'yicha davomat mavjudligini tekshirish
        $attendanceExists = collect();
        foreach (array_chunk($allScheduleIds, 5000) as $chunk) {
            $result = DB::table('attendances')
                ->whereIn('subject_schedule_id', $chunk)
                ->select('subject_schedule_id')
                ->distinct()
                ->pluck('subject_schedule_id');
            $attendanceExists = $attendanceExists->merge($result);
        }
        $attendanceSet = $attendanceExists->flip();

        // Chunk bo'yicha baho mavjudligini tekshirish
        $gradeExists = collect();
        foreach (array_chunk($allScheduleIds, 5000) as $chunk) {
            $result = DB::table('student_grades')
                ->whereIn('subject_schedule_id', $chunk)
                ->whereNotNull('grade')
                ->where('grade', '>', 0)
                ->select('subject_schedule_id')
                ->distinct()
                ->pluck('subject_schedule_id');
            $gradeExists = $gradeExists->merge($result);
        }
        $gradeSet = $gradeExists->flip();

        // 3-QADAM: Talaba sonini guruh bo'yicha hisoblash
        $groupIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11) // Faol talabalar
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // 4-QADAM: Ma'lumotlarni guruhlash (employee + group + subject + date)
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str;

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_id' => $sch->employee_id,
                    'employee_name' => $sch->employee_name,
                    'faculty_name' => $sch->faculty_name,
                    'specialty_name' => $sch->specialty_name,
                    'level_name' => $sch->level_name,
                    'semester_name' => $sch->semester_name,
                    'department_name' => $sch->department_name,
                    'subject_name' => $sch->subject_name,
                    'group_id' => $sch->group_id,
                    'group_name' => $sch->group_name,
                    'lesson_date' => $sch->lesson_date_str,
                    'student_count' => $studentCounts[$sch->group_id] ?? 0,
                    'has_attendance' => false,
                    'has_grades' => false,
                    'schedule_ids' => [],
                ];
            }

            $grouped[$key]['schedule_ids'][] = $sch->schedule_hemis_id;

            if (isset($attendanceSet[$sch->schedule_hemis_id])) {
                $grouped[$key]['has_attendance'] = true;
            }
            if (isset($gradeSet[$sch->schedule_hemis_id])) {
                $grouped[$key]['has_grades'] = true;
            }
        }

        $results = array_values($grouped);

        // Holat filtri (birlashtirilgan)
        if ($request->filled('status_filter')) {
            $results = array_values(array_filter($results, function ($r) use ($request) {
                return match ($request->status_filter) {
                    'any_missing' => !$r['has_attendance'] || !$r['has_grades'],
                    'attendance_missing' => !$r['has_attendance'],
                    'grade_missing' => !$r['has_grades'],
                    'both_missing' => !$r['has_attendance'] && !$r['has_grades'],
                    'all_done' => $r['has_attendance'] && $r['has_grades'],
                    default => true,
                };
            }));
        }

        // Saralash
        $sortColumn = $request->get('sort', 'lesson_date');
        $sortDirection = $request->get('direction', 'desc');

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportLessonAssignmentExcel($results);
        }

        // Pagination
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 50);
        $total = count($results);
        $offset = ($page - 1) * $perPage;
        $pageData = array_slice($results, $offset, $perPage);

        foreach ($pageData as $i => &$item) {
            $item['row_num'] = $offset + $i + 1;
            unset($item['schedule_ids']);
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

    /**
     * Dars belgilash Excel export
     */
    private function exportLessonAssignmentExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Dars belgilash');

        $headers = ['#', 'Xodim FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Kafedra', 'Fan', 'Guruh', 'Talaba soni', 'Davomat', 'Baho', 'Dars sanasi'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:M1')->applyFromArray($headerStyle);

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
            $sheet->setCellValue([10, $row], $r['student_count']);
            $sheet->setCellValue([11, $row], $r['has_attendance'] ? 'Ha' : "Yo'q");
            $sheet->setCellValue([12, $row], $r['has_grades'] ? 'Ha' : "Yo'q");
            $sheet->setCellValue([13, $row], $r['lesson_date']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 25, 35, 15, 12, 10, 10, 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:M{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Dars_belgilash_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'la_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Excel fayl yaratish va yuklash
     */
    private function exportExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('JN hisobot');

        // Sarlavhalar
        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh', 'Fan', "O'rtacha baho", 'Darslar soni'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        // Sarlavha stili
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Ma'lumotlar
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

        // Ustun kengliklarini sozlash
        $widths = [5, 30, 25, 30, 8, 10, 15, 35, 14, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        // Ma'lumotlar uchun border
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
}

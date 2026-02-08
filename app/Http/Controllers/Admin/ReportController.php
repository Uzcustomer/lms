<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
use App\Models\Setting;
use App\Models\CurriculumSubject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        // Sana oralig'i filtri
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

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

        // Sana oralig'i bo'yicha dars jadvalini filtrlash
        if ($dateFrom) {
            $scheduleQuery->where('sch.lesson_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $scheduleQuery->where('sch.lesson_date', '<=', $dateTo);
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
        $gradesQuery = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'grade', 'lesson_date', 'lesson_pair_code');

        // Sana oralig'i bo'yicha baholarni filtrlash
        if ($dateFrom) {
            $gradesQuery->where('lesson_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $gradesQuery->where('lesson_date', '<=', $dateTo);
        }

        $gradesRaw = $gradesQuery->get();

        // 4-QADAM: Jurnal formulasi bo'yicha hisoblash
        // a) Baho date_pair larini columns ga birlashtirish (jurnal kabi fallback)
        // b) Baholarni kun bo'yicha guruhlash
        $cutoffDate = $dateTo ?? Carbon::now('Asia/Tashkent')->subDay()->startOfDay()->format('Y-m-d');

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
            $mappedGroupId = $groupIdMap[$r['group_id']] ?? null;
            if ($mappedGroupId === null) {
                continue;
            }
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
     * Dars jadval mosligi hisoboti sahifasi
     */
    public function scheduleReport(Request $request)
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

        $trainingTypes = DB::table('schedules')
            ->select('training_type_code', 'training_type_name')
            ->whereNotNull('training_type_code')
            ->whereNull('deleted_at')
            ->groupBy('training_type_code', 'training_type_name')
            ->orderBy('training_type_name')
            ->get();

        return view('admin.reports.schedule-report', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'trainingTypes'
        ));
    }

    /**
     * AJAX: Dars jadval mosligi hisobot ma'lumotlarini hisoblash
     * Har bir fan+guruh+dars_turi uchun ajratilgan soat vs jadvalda qo'yilgan soatni solishtiradi
     */
    public function scheduleReportData(Request $request)
    {
        try {
        // 1-QADAM: O'quv rejadagi fanlarni olish (curriculum_subjects) - subject_details bilan
        $csQuery = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);

        // Joriy semestr filtri
        if ($request->get('current_semester', '1') == '1') {
            $csQuery->where('s.current', true);
        }

        // Filtrlar
        if ($request->filled('education_type')) {
            $csQuery->where('c.education_type_code', $request->education_type);
        }

        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $csQuery->where('c.department_hemis_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('specialty')) {
            $csQuery->where('g.specialty_hemis_id', $request->specialty);
        }

        if ($request->filled('level_code')) {
            $csQuery->where('s.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $csQuery->where('cs.semester_code', $request->semester_code);
        }

        if ($request->filled('department')) {
            $csQuery->where('cs.department_id', $request->department);
        }

        if ($request->filled('subject')) {
            $csQuery->where('cs.subject_id', $request->subject);
        }

        if ($request->filled('group')) {
            $csQuery->where('g.group_hemis_id', $request->group);
        }

        $curriculumSubjects = $csQuery->select(
            'cs.subject_id',
            'cs.subject_name',
            'cs.semester_code',
            'cs.subject_details',
            'g.group_hemis_id',
            'g.name as group_name',
            'f.name as faculty_name',
            'g.specialty_name',
            's.level_name',
            's.name as semester_name'
        )->get();

        if ($curriculumSubjects->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 2-QADAM: Jadvaldan darslar sonini dars turi bo'yicha hisoblash
        // Akademik soat = (lesson_pair_end_time - lesson_pair_start_time) / 40 daqiqa
        $groupIds = $curriculumSubjects->pluck('group_hemis_id')->unique()->toArray();
        $subjectIds = $curriculumSubjects->pluck('subject_id')->unique()->toArray();
        $semesterCodes = $curriculumSubjects->pluck('semester_code')->unique()->toArray();

        $scheduleQuery = DB::table('schedules as sch')
            ->whereIn('sch.group_id', $groupIds)
            ->whereIn('sch.subject_id', $subjectIds)
            ->whereIn('sch.semester_code', $semesterCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        if ($request->filled('date_from')) {
            $scheduleQuery->where('sch.lesson_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $scheduleQuery->where('sch.lesson_date', '<=', $request->date_to);
        }

        $scheduleRows = $scheduleQuery
            ->select(
                'sch.group_id',
                'sch.subject_id',
                'sch.semester_code',
                'sch.training_type_code',
                'sch.lesson_pair_start_time',
                'sch.lesson_pair_end_time'
            )
            ->get();

        // Har bir jadval qatori uchun akademik soatni hisoblash va yig'ish
        $scheduleMap = [];
        foreach ($scheduleRows as $row) {
            $key = $row->group_id . '|' . $row->subject_id . '|' . $row->semester_code . '|' . $row->training_type_code;
            $start = strtotime($row->lesson_pair_start_time);
            $end = strtotime($row->lesson_pair_end_time);
            $durationMinutes = ($end - $start) / 60;
            // 1 akademik soat = 40 daqiqa (80 min = 2 soat, 40-45 min = 1 soat)
            $academicHours = max(1, round($durationMinutes / 40));
            $scheduleMap[$key] = ($scheduleMap[$key] ?? 0) + $academicHours;
        }

        // 3-QADAM: subject_details JSON dan dars turlari bo'yicha ajratilgan soatlarni olish
        // Har bir fan+guruh+dars_turi uchun alohida qator hosil qilish
        $trainingTypeFilter = $request->has('training_types') ? (array) $request->training_types : [];
        $results = [];
        foreach ($curriculumSubjects as $cs) {
            $details = $cs->subject_details;
            if (is_string($details)) {
                $details = json_decode($details, true);
            }
            if (!is_array($details) || empty($details)) {
                continue;
            }

            foreach ($details as $detail) {
                $trainingTypeCode = (string) ($detail['trainingType']['code'] ?? '');
                $trainingTypeName = $detail['trainingType']['name'] ?? '-';
                $plannedHours = (int) ($detail['academic_load'] ?? 0);

                if ($trainingTypeCode === '') {
                    continue;
                }

                // Dars turi filtri
                if (!empty($trainingTypeFilter) && !in_array($trainingTypeCode, $trainingTypeFilter)) {
                    continue;
                }

                $schedKey = $cs->group_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code . '|' . $trainingTypeCode;
                $scheduledHours = $scheduleMap[$schedKey] ?? 0;
                $farq = $plannedHours - $scheduledHours;

                $results[] = [
                    'faculty_name' => $cs->faculty_name ?? '-',
                    'specialty_name' => $cs->specialty_name ?? '-',
                    'level_name' => $cs->level_name ?? '-',
                    'semester_name' => $cs->semester_name ?? '-',
                    'subject_name' => $cs->subject_name ?? '-',
                    'group_name' => $cs->group_name ?? '-',
                    'training_type' => $trainingTypeName,
                    'planned_hours' => $plannedHours,
                    'scheduled_hours' => $scheduledHours,
                    'farq' => $farq,
                ];
            }
        }

        if (empty($results)) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // Saralash (standart: farq bo'yicha kamayish tartibida)
        $sortColumn = $request->get('sort', 'farq');
        $sortDirection = $request->get('direction', 'desc');

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportScheduleReportExcel($results);
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
        } catch (\Throwable $e) {
            \Log::error('Schedule report error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Dars jadval mosligi Excel export
     */
    private function exportScheduleReportExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadval mosligi');

        $headers = ['#', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Fan', 'Guruh', 'Dars turi', 'Ajratilgan soat', 'Jadvalda qo\'yilgan soat', 'Farq'];
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

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['faculty_name']);
            $sheet->setCellValue([3, $row], $r['specialty_name']);
            $sheet->setCellValue([4, $row], $r['level_name']);
            $sheet->setCellValue([5, $row], $r['semester_name']);
            $sheet->setCellValue([6, $row], $r['subject_name']);
            $sheet->setCellValue([7, $row], $r['group_name']);
            $sheet->setCellValue([8, $row], $r['training_type']);
            $sheet->setCellValue([9, $row], $r['planned_hours']);
            $sheet->setCellValue([10, $row], $r['scheduled_hours']);
            $sheet->setCellValue([11, $row], $r['farq']);
        }

        $widths = [5, 25, 30, 8, 10, 35, 15, 20, 16, 22, 10];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:K{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Jadval_mosligi_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'sr_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * 25% sababsiz davomat hisoboti - filtrlar sahifasi
     */
    public function absenceReport(Request $request)
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

        // Talaba holatlari
        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        return view('admin.reports.absence', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'studentStatuses'
        ));
    }

    /**
     * AJAX: 25% sababsiz davomat hisobot ma'lumotlarini hisoblash
     */
    public function absenceReportData(Request $request)
    {
        try {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Schedule dan unique (group, subject, semester) kombinatsiyalarini olish
        $scheduleQuery = DB::table('schedules as sch')
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->select('sch.group_id', 'sch.subject_id', 'sch.semester_code')
            ->distinct();

        $isCurrentSemester = $request->get('current_semester', '1') == '1';
        if ($isCurrentSemester) {
            $scheduleQuery
                ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
                ->join('semesters as sem', function ($join) {
                    $join->on('sem.code', '=', 'sch.semester_code')
                        ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
                })
                ->where('sem.current', true)
                ->where('sch.education_year_current', true);
        }

        if ($request->filled('semester_code')) {
            $scheduleQuery->where('sch.semester_code', $request->semester_code);
        }
        if ($request->filled('subject')) {
            $scheduleQuery->where('sch.subject_id', $request->subject);
        }

        $scheduleCombos = $scheduleQuery->get();

        if ($scheduleCombos->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $scheduleGroupIds = $scheduleCombos->pluck('group_id')->unique()->toArray();
        $validSubjectIds = $scheduleCombos->pluck('subject_id')->unique()->toArray();
        $validSemesterCodes = $scheduleCombos->pluck('semester_code')->unique()->toArray();

        // Auditoriya soatlarini hisoblash: curriculum_subjects.subject_details dan
        // (Jurnal details bilan bir xil logika)
        $groupCurriculumMap = DB::table('groups')
            ->whereIn('group_hemis_id', $scheduleGroupIds)
            ->pluck('curriculum_hemis_id', 'group_hemis_id')
            ->toArray();

        $comboKeys = [];
        foreach ($scheduleCombos as $row) {
            $comboKeys[$row->group_id . '|' . $row->subject_id . '|' . $row->semester_code] = [
                'group_id' => $row->group_id,
                'subject_id' => $row->subject_id,
                'semester_code' => $row->semester_code,
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

        // 2-QADAM: Talabalar ro'yxatini tayyorlash
        $studentQuery = DB::table('students as s')
            ->select('s.hemis_id', 's.group_id', 's.student_status_code', 's.student_status_name');

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

        $allowedSubjectIds = null;
        if ($request->filled('department')) {
            $allowedSubjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
        }

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

        $filteredSubjectIds = $validSubjectIds;
        if ($allowedSubjectIds !== null) {
            $filteredSubjectIds = array_intersect($validSubjectIds, $allowedSubjectIds);
            if (empty($filteredSubjectIds)) {
                return response()->json(['data' => [], 'total' => 0]);
            }
        }

        // 3-QADAM: student_grades dan ma'lumotlarni olish
        // Joriy o'quv yili bo'lsa, o'tgan yilgi ma'lumotlar tushmasligi uchun
        // joriy o'quv yilining eng kichik dars sanasidan filtrlash
        $minScheduleDate = null;
        if ($isCurrentSemester) {
            $minScheduleDate = DB::table('schedules')
                ->where('education_year_current', true)
                ->whereNotNull('lesson_date')
                ->min('lesson_date');
        }

        $gradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $filteredSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('lesson_date')
            ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                'grade', 'lesson_date', 'lesson_pair_code', 'reason', 'status', 'deadline')
            ->get();

        // 4-QADAM: Har bir talaba/fan uchun davomat ma'lumotlarini hisoblash
        $studentSubjectData = [];
        $now = Carbon::now('Asia/Tashkent');
        $spravkaDays = (int) Setting::get('spravka_deadline_days', 10);

        foreach ($gradesRaw as $g) {
            $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
            if (!$groupId) continue;

            $ssKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code;
            $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
            $dateKey = substr($g->lesson_date, 0, 10);

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
                    'unexcused_absent_dates' => [],
                    'attendance_dates' => [],
                ];
            }

            if ($g->reason === 'absent') {
                $studentSubjectData[$ssKey]['total_absent_hours'] += 2;

                if ($g->status !== 'retake') {
                    $studentSubjectData[$ssKey]['unexcused_absent_hours'] += 2;
                    $studentSubjectData[$ssKey]['unexcused_absent_dates'][] = $dateKey;
                }
            } else {
                if ($g->grade !== null && $g->grade > 0) {
                    $studentSubjectData[$ssKey]['attendance_dates'][$dateKey] = true;
                }
            }
        }

        // 5-QADAM: Barcha talabalarni filtrlash
        $results = [];
        foreach ($studentSubjectData as $ssKey => $data) {
            $comboKey = $data['combo_key'];
            $totalAuditoryHours = $auditoryHours[$comboKey] ?? 0;

            if ($totalAuditoryHours <= 0) continue;

            $unexcusedPercent = round(($data['unexcused_absent_hours'] / $totalAuditoryHours) * 100);

            // Spravka muddati: oxirgi sababsiz dars kunidan boshlab hisoblash
            $absentDates = $data['unexcused_absent_dates'];
            sort($absentDates);
            $spravkaStatus = '-';
            if (!empty($absentDates)) {
                $latestAbsentDate = Carbon::parse(end($absentDates));
                $daysSinceAbsent = $latestAbsentDate->diffInDays($now);
                $spravkaStatus = $daysSinceAbsent <= $spravkaDays ? 'Muddat bor' : 'Kechikkan';
            }

            // 25% chegarasiga yetgan sanani aniqlash
            $firstAttendanceAfter25 = null;
            $cumulativeHours = 0;
            $thresholdDate = null;

            foreach ($absentDates as $aDate) {
                $cumulativeHours += 2;
                if (($cumulativeHours / $totalAuditoryHours) * 100 >= 25 && !$thresholdDate) {
                    $thresholdDate = $aDate;
                    break;
                }
            }

            if ($thresholdDate) {
                $gradeDates = array_keys($data['attendance_dates']);
                sort($gradeDates);
                foreach ($gradeDates as $gDate) {
                    if ($gDate > $thresholdDate) {
                        $firstAttendanceAfter25 = Carbon::parse($gDate)->format('d.m.Y');
                        break;
                    }
                }
            }

            $comboParts = explode('|', $comboKey);
            $results[] = [
                'student_hemis_id' => $data['student_hemis_id'],
                'subject_id' => $data['subject_id'],
                'subject_name' => $data['subject_name'],
                'semester_code' => $data['semester_code'],
                'group_id' => $comboParts[0] ?? '',
                'total_absent_hours' => $data['total_absent_hours'],
                'unexcused_absent_hours' => $data['unexcused_absent_hours'],
                'auditory_hours' => $totalAuditoryHours,
                'unexcused_percent' => $unexcusedPercent,
                'spravka_status' => $spravkaStatus,
                'first_attendance_after_25' => $firstAttendanceAfter25,
                'report_date' => $now->format('d.m.Y'),
            ];
        }

        // Talaba ma'lumotlarini biriktirish
        $hemisIds = array_unique(array_column($results, 'student_hemis_id'));
        $studentInfo = [];
        if (!empty($hemisIds)) {
            $studentInfo = DB::table('students')
                ->whereIn('hemis_id', $hemisIds)
                ->select('hemis_id', 'full_name', 'department_name', 'specialty_name',
                    'level_name', 'semester_name', 'group_name')
                ->get()
                ->keyBy('hemis_id');
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
                'unexcused_absent_hours' => $r['unexcused_absent_hours'],
                'total_absent_hours' => $r['total_absent_hours'],
                'auditory_hours' => $r['auditory_hours'],
                'unexcused_percent' => $r['unexcused_percent'],
                'spravka_status' => $r['spravka_status'],
                'first_attendance_after_25' => $r['first_attendance_after_25'],
                'report_date' => $r['report_date'],
                'group_id' => $r['group_id'],
                'subject_id' => $r['subject_id'],
                'semester_code' => $r['semester_code'],
            ];
        }

        // Saralash
        $sortColumn = $request->get('sort', 'unexcused_absent_hours');
        $sortDirection = $request->get('direction', 'desc');

        usort($finalResults, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        if ($request->get('export') === 'excel') {
            return $this->exportAbsenceExcel($finalResults);
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
            \Log::error('Absence report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Excel fayl yaratish va yuklash (25% sababsiz hisobot)
     */
    private function exportAbsenceExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('25% sababsiz hisobot');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh', 'Fan',
            'Sababsiz qoldirilgan soat', 'Jami qoldirilgan soat', 'Auditoriya soati', 'Sababsiz %',
            'Spravka muddati', '25% dan keyin darsga chiqqan sana', 'Hisobot sanasi'];
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
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['semester_name']);
            $sheet->setCellValue([7, $row], $r['group_name']);
            $sheet->setCellValue([8, $row], $r['subject_name']);
            $sheet->setCellValue([9, $row], $r['unexcused_absent_hours']);
            $sheet->setCellValue([10, $row], $r['total_absent_hours']);
            $sheet->setCellValue([11, $row], $r['auditory_hours']);
            $sheet->setCellValue([12, $row], $r['unexcused_percent'] . '%');
            $sheet->setCellValue([13, $row], $r['spravka_status']);
            $sheet->setCellValue([14, $row], $r['first_attendance_after_25'] ?? '-');
            $sheet->setCellValue([15, $row], $r['report_date']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 15, 35, 20, 20, 16, 12, 16, 24, 16];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:O{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = '25_sababsiz_hisobot_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'abs_');

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

    /**
     * Yuklama vs Juftlik hisoboti sahifasi
     */
    public function loadVsPairReport(Request $request)
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

        return view('admin.reports.load-vs-pair', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras'
        ));
    }

    /**
     * AJAX: Yuklama vs Juftlik hisobot ma'lumotlarini hisoblash
     * attendance_controls jadvalidan foydalanadi (HEMIS API dan sinxronlangan)
     * Juftlik soat = lesson_pair vaqtidan hisoblangan akademik soat: round((end-start)/40)
     * Yuklama soat = load maydoni (API dan kelgan)
     * Farq = juftlik_soat - yuklama_soat
     */
    public function loadVsPairReportData(Request $request)
    {
        try {
            $cl = 'utf8mb4_unicode_ci';

            $query = DB::table('attendance_controls as ac')
                ->leftJoin('groups as g', function ($join) use ($cl) {
                    $join->whereRaw("g.group_hemis_id COLLATE $cl = ac.group_id COLLATE $cl");
                })
                ->leftJoin('curricula as c', function ($join) use ($cl) {
                    $join->whereRaw("g.curriculum_hemis_id COLLATE $cl = c.curricula_hemis_id COLLATE $cl");
                })
                ->leftJoin('semesters as s', function ($join) use ($cl) {
                    $join->whereRaw("s.curriculum_hemis_id COLLATE $cl = c.curricula_hemis_id COLLATE $cl")
                        ->whereRaw("s.code COLLATE $cl = ac.semester_code COLLATE $cl");
                })
                ->leftJoin('departments as f', function ($join) use ($cl) {
                    $join->whereRaw("f.department_hemis_id COLLATE $cl = c.department_hemis_id COLLATE $cl")
                        ->where('f.structure_type_code', 11);
                })
                ->leftJoin('schedules as sch', function ($join) use ($cl) {
                    $join->whereRaw("sch.schedule_hemis_id COLLATE $cl = ac.subject_schedule_id COLLATE $cl");
                });

            // Filtrlar
            if ($request->filled('education_type')) {
                $query->where('c.education_type_code', $request->education_type);
            }
            if ($request->filled('faculty')) {
                $faculty = Department::find($request->faculty);
                if ($faculty) {
                    $query->where('c.department_hemis_id', $faculty->department_hemis_id);
                }
            }
            if ($request->filled('specialty')) {
                $query->where('g.specialty_hemis_id', $request->specialty);
            }
            if ($request->filled('level_code')) {
                $query->where('s.level_code', $request->level_code);
            }
            if ($request->filled('semester_code')) {
                $query->where('ac.semester_code', $request->semester_code);
            }
            if ($request->filled('department')) {
                $query->where('sch.department_id', $request->department);
            }
            if ($request->filled('subject')) {
                $query->where('ac.subject_id', $request->subject);
            }
            if ($request->filled('group')) {
                $query->where('ac.group_id', $request->group);
            }
            if ($request->filled('date_from')) {
                $query->where('ac.lesson_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->where('ac.lesson_date', '<=', $request->date_to . ' 23:59:59');
            }

            $rows = $query->select(
                'ac.employee_id',
                'ac.employee_name',
                'ac.subject_name',
                'ac.group_name',
                'ac.semester_name',
                'ac.lesson_pair_name',
                'ac.lesson_pair_start_time',
                'ac.lesson_pair_end_time',
                'ac.load',
                'ac.lesson_date',
                'ac.training_type_name',
                'g.id as group_local_id',
                'ac.subject_id',
                'ac.semester_code',
                'f.name as faculty_name',
                'g.specialty_name',
                's.level_name',
                'sch.department_name'
            )->get();

            if ($rows->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            // Juftlik soat va yuklama soatni solishtirish
            $results = [];
            foreach ($rows as $row) {
                $start = strtotime($row->lesson_pair_start_time);
                $end = strtotime($row->lesson_pair_end_time);
                $minutes = ($end && $start && $end > $start) ? ($end - $start) / 60 : 0;
                $pairHours = ($minutes > 0) ? max(1, round($minutes / 40)) : 0;
                $loadHours = (int) $row->load;
                $farq = $pairHours - $loadHours;

                if ($farq == 0) {
                    continue;
                }

                $pairName = $row->lesson_pair_name . ' (' . $row->lesson_pair_start_time . '-' . $row->lesson_pair_end_time . ')';
                $lessonDateStr = $row->lesson_date ? date('d.m.Y', strtotime($row->lesson_date)) : '-';

                $results[] = [
                    'employee_name' => $row->employee_name ?? '-',
                    'faculty_name' => $row->faculty_name ?? '-',
                    'specialty_name' => $row->specialty_name ?? '-',
                    'level_name' => $row->level_name ?? '-',
                    'semester_name' => $row->semester_name ?? '-',
                    'department_name' => $row->department_name ?? '-',
                    'subject_name' => $row->subject_name ?? '-',
                    'group_name' => $row->group_name ?? '-',
                    'pair_name' => $pairName,
                    'pair_hours' => $pairHours,
                    'load_hours' => $loadHours,
                    'farq' => $farq,
                    'lesson_date' => $lessonDateStr,
                    'group_id' => $row->group_local_id ?? '',
                    'subject_id' => $row->subject_id ?? '',
                    'semester_code' => $row->semester_code ?? '',
                ];
            }

            if (empty($results)) {
                return response()->json(['data' => [], 'total' => 0]);
            }

            // Saralash (standart: farq bo'yicha kamayish tartibida)
            $sortColumn = $request->get('sort', 'farq');
            $sortDirection = $request->get('direction', 'desc');

            usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';
                $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            // Excel export
            if ($request->get('export') === 'excel') {
                return $this->exportLoadVsPairExcel($results);
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
        } catch (\Throwable $e) {
            \Log::error('Load vs Pair report error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Yuklama vs Juftlik Excel export
     */
    private function exportLoadVsPairExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Yuklama vs Juftlik');

        $headers = ['#', 'Xodim FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Kafedra', 'Fan', 'Guruh', 'Juftlik', 'Juftlik soat', 'Yuklama soat', 'Farq'];
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
            $sheet->setCellValue([10, $row], $r['pair_name']);
            $sheet->setCellValue([11, $row], $r['pair_hours']);
            $sheet->setCellValue([12, $row], $r['load_hours']);
            $sheet->setCellValue([13, $row], $r['farq']);
        }

        $widths = [5, 30, 25, 30, 8, 10, 25, 35, 15, 25, 14, 14, 10];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:M{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Yuklama_vs_Juftlik_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'lvp_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function debtorsReport()
    {
        return view('admin.reports.debtors');
    }

    /**
     * Sababli check hisoboti sahifasi
     * Onlayn sababli qilishga ariza yozganlar bilan HEMISda davomat sababli qilinganini tekshirish
     */
    public function sababliCheckReport(Request $request)
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

        return view('admin.reports.sababli-check', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType'
        ));
    }

    /**
     * AJAX: Sababli check hisobot ma'lumotlarini hisoblash
     * student_grades da reason='absent' + status='retake' bo'lganlarni
     * attendances da absent_on > 0 ekanligini tekshiradi
     */
    public function sababliCheckData(Request $request)
    {
        // 1-QADAM: student_grades dan sababli qilingan (reason='absent', status='retake') yozuvlarni olish
        $gradesQuery = DB::table('student_grades as sg')
            ->join('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->where('sg.reason', 'absent')
            ->where('sg.status', 'retake');

        // Filtrlar
        if ($request->filled('education_type')) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $request->education_type)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $gradesQuery->whereIn('s.group_id', $groupIds);
        }
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $gradesQuery->where('s.department_id', $faculty->department_hemis_id);
            }
        }
        if ($request->filled('specialty')) {
            $gradesQuery->where('s.specialty_id', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $gradesQuery->where('s.level_code', $request->level_code);
        }
        if ($request->filled('group')) {
            $gradesQuery->where('s.group_id', $request->group);
        }

        // Joriy semestr
        if ($request->get('current_semester', '1') == '1') {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->unique()
                ->toArray();
            if (!empty($currentSemesterCodes)) {
                $gradesQuery->whereIn('sg.semester_code', $currentSemesterCodes);
            }
        }

        $gradesRows = $gradesQuery->select(
            'sg.student_hemis_id',
            's.full_name',
            's.department_name',
            's.specialty_name',
            's.level_name',
            's.group_name',
            's.semester_name',
            'sg.subject_id',
            'sg.subject_name',
            'sg.lesson_date',
            'sg.lesson_pair_code',
            'sg.semester_code'
        )->get();

        if ($gradesRows->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 2-QADAM: attendances dan tegishli yozuvlarni olish
        $studentHemisIds = $gradesRows->pluck('student_hemis_id')->unique()->toArray();

        $attendanceRows = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->select('student_hemis_id', 'subject_id', 'lesson_date', 'lesson_pair_code', 'absent_on', 'absent_off')
            ->get();

        // attendances ni kalit bo'yicha indekslash: student_hemis_id|subject_id|lesson_date|lesson_pair_code
        $attendanceMap = [];
        foreach ($attendanceRows as $att) {
            $dateStr = substr($att->lesson_date, 0, 10);
            $key = $att->student_hemis_id . '|' . $att->subject_id . '|' . $dateStr . '|' . $att->lesson_pair_code;
            $attendanceMap[$key] = $att;
        }

        // 3-QADAM: Solishtirish
        $results = [];
        foreach ($gradesRows as $gr) {
            $dateStr = substr($gr->lesson_date, 0, 10);
            $key = $gr->student_hemis_id . '|' . $gr->subject_id . '|' . $dateStr . '|' . $gr->lesson_pair_code;

            $att = $attendanceMap[$key] ?? null;

            $hemisSababli = false;
            $hemisStatus = 'Davomat topilmadi';

            if ($att) {
                if ((int) $att->absent_on > 0 && (int) $att->absent_off == 0) {
                    $hemisSababli = true;
                    $hemisStatus = 'Sababli';
                } elseif ((int) $att->absent_off > 0 && (int) $att->absent_on == 0) {
                    $hemisStatus = 'Sababsiz';
                } elseif ((int) $att->absent_on > 0 && (int) $att->absent_off > 0) {
                    $hemisSababli = true;
                    $hemisStatus = 'Aralash';
                } else {
                    $hemisStatus = 'Noaniq';
                }
            }

            $match = $hemisSababli ? 'match' : 'mismatch';

            $results[] = [
                'student_hemis_id' => $gr->student_hemis_id,
                'full_name' => $gr->full_name,
                'department_name' => $gr->department_name ?? '-',
                'specialty_name' => $gr->specialty_name ?? '-',
                'level_name' => $gr->level_name ?? '-',
                'group_name' => $gr->group_name ?? '-',
                'subject_name' => $gr->subject_name ?? '-',
                'lesson_date' => $dateStr ? date('d.m.Y', strtotime($dateStr)) : '-',
                'lms_status' => 'Sababli (retake)',
                'hemis_status' => $hemisStatus,
                'match' => $match,
            ];
        }

        // Filtrlash: faqat nomuvofiqlarni ko'rsatish
        if ($request->get('filter_status') === 'mismatch') {
            $results = array_values(array_filter($results, fn($r) => $r['match'] === 'mismatch'));
        } elseif ($request->get('filter_status') === 'match') {
            $results = array_values(array_filter($results, fn($r) => $r['match'] === 'match'));
        }

        // Saralash
        $sortColumn = $request->get('sort', 'full_name');
        $sortDirection = $request->get('direction', 'asc');

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportSababliCheckExcel($results);
        }

        // Statistika
        $totalCount = count($results);
        $matchCount = count(array_filter($results, fn($r) => $r['match'] === 'match'));
        $mismatchCount = $totalCount - $matchCount;

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
            'last_page' => (int) ceil($total / max($perPage, 1)),
            'match_count' => $matchCount,
            'mismatch_count' => $mismatchCount,
        ]);
    }

    /**
     * Sababli check Excel export
     */
    private function exportSababliCheckExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sababli check');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Fan', 'Sana', 'LMS holati', 'HEMIS holati', 'Natija'];
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

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['group_name']);
            $sheet->setCellValue([7, $row], $r['subject_name']);
            $sheet->setCellValue([8, $row], $r['lesson_date']);
            $sheet->setCellValue([9, $row], $r['lms_status']);
            $sheet->setCellValue([10, $row], $r['hemis_status']);
            $sheet->setCellValue([11, $row], $r['match'] === 'match' ? 'Mos' : 'Mos emas');

            // Natija rangini qo'yish
            if ($r['match'] === 'match') {
                $sheet->getStyle("K{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                ]);
            } else {
                $sheet->getStyle("K{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
                ]);
            }
        }

        $widths = [5, 30, 25, 30, 8, 15, 35, 12, 18, 18, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:K{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Sababli_check_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'sc_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

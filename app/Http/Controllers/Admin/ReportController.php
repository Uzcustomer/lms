<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use App\Models\Setting;
use App\Models\CurriculumSubject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Services\ScheduleImportService;

class ReportController extends Controller
{
    /**
     * Sahifa yuklanishi - faqat filtrlar ko'rsatiladi (ma'lumot so'ralmaydi)
     */
    public function jnReport(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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
        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        } elseif ($request->filled('faculty')) {
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
            'kafedras',
            'dekanFacultyIds'
        ));
    }

    /**
     * AJAX: JN hisobot ma'lumotlarini hisoblash
     * Jurnal bilan bir xil formula: kunlik o'rtachalar (half-round-up) → yakuniy o'rtacha (half-round-up)
     */
    public function jnReportData(Request $request)
    {
        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

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
            ->where(function ($q) {
                $q->whereNotNull('grade')->orWhereNotNull('retake_grade');
            })
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'grade', 'lesson_date', 'lesson_pair_code', 'retake_grade', 'status', 'reason');

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

            // Baholarni kun bo'yicha guruhlash (pair_code bo'yicha — jurnal kabi deduplikatsiya)
            $gradeKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $dateKey;
            $effectiveGrade = $this->getEffectiveGradeForJn($g);
            if ($effectiveGrade !== null) {
                $gradesByDay[$gradeKey][$g->lesson_pair_code] = $effectiveGrade;
            }

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
                'minimum_limit' => MarkingSystemScore::getByStudentHemisId($r['student_hemis_id'])->minimum_limit,
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
     * JN hisobot uchun samarali (effective) bahoni aniqlash.
     * Retake holatida retake_grade, aks holda grade ishlatiladi.
     * Jurnal bilan bir xil mantiq (JournalController va StudentGradeService ga mos).
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

        // recorded/closed → faqat grade ishlatiladi (jurnal mantiqiga mos)
        if (($row->status ?? null) === 'recorded') {
            return $row->grade !== null ? (float) $row->grade : null;
        }

        if (($row->status ?? null) === 'closed') {
            return $row->grade !== null ? (float) $row->grade : null;
        }

        // Boshqa statuslar (retake va h.k.) → retake_grade ishlatiladi
        if ($row->retake_grade !== null) {
            return (float) $row->retake_grade;
        }

        return null;
    }

    /**
     * Dars belgilash hisoboti sahifasi
     */
    public function lessonAssignment(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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
        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        } elseif ($request->filled('faculty')) {
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
            'kafedras',
            'dekanFacultyIds'
        ));
    }

    /**
     * Dars jadvali yangilash — HEMIS dan tanlangan sana oralig'idagi jadvallarni sinxronlash
     */
    public function syncSchedulesForReport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $from = Carbon::parse($request->date_from)->startOfDay();
        $to = Carbon::parse($request->date_to)->endOfDay();

        if ($from->diffInDays($to) > 31) {
            return response()->json([
                'success' => false,
                'message' => 'Sana oralig\'i 31 kundan oshmasligi kerak.',
            ], 422);
        }

        $syncKey = 'report_sync_' . auth()->id();

        // Allaqachon ishlab turgan sync bormi?
        $existing = \Illuminate\Support\Facades\Cache::get($syncKey);
        if ($existing && $existing['status'] === 'running') {
            return response()->json([
                'success' => true,
                'sync_key' => $syncKey,
                'message' => 'Sinxronlash allaqachon jarayonda.',
            ]);
        }

        // Darhol "boshlandi" holatini yozish
        \Illuminate\Support\Facades\Cache::put($syncKey, [
            'status' => 'running',
            'message' => 'Navbatga qo\'shildi...',
            'current' => 0,
            'total' => 0,
            'percent' => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 600);

        \App\Jobs\SyncReportDataJob::dispatch(
            $request->date_from,
            $request->date_to,
            $syncKey
        );

        return response()->json([
            'success' => true,
            'sync_key' => $syncKey,
            'message' => 'Sinxronlash boshlandi.',
        ]);
    }

    public function syncSchedulesStatus(Request $request)
    {
        $syncKey = 'report_sync_' . auth()->id();
        $data = \Illuminate\Support\Facades\Cache::get($syncKey);

        if (!$data) {
            return response()->json(['status' => 'none']);
        }

        return response()->json($data);
    }

    /**
     * AJAX: Dars belgilash hisobot ma'lumotlarini hisoblash
     */
    /**
     * Dars belgilash hisoboti diagnostikasi — HEMIS bilan solishtirish
     */
    public function lessonAssignmentDiagnostic(Request $request)
    {
        $date = $request->get('date', now()->subDay()->format('Y-m-d'));
        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1. Barcha schedulelar (shu sanadagi)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            })
            ->whereRaw('DATE(sch.lesson_date) = ?', [$date])
            ->select(
                'sch.schedule_hemis_id',
                'sch.employee_id',
                'sch.employee_name',
                'sch.subject_id',
                'sch.subject_name',
                'sch.group_id',
                'sch.group_name',
                'sch.training_type_code',
                'sch.training_type_name',
                'sch.lesson_date',
                'sch.lesson_pair_code'
            )
            ->orderBy('sch.employee_name')
            ->get();

        $scheduleIds = $schedules->pluck('schedule_hemis_id')->toArray();

        // 2. attendance_controls da bor schedule IDlar
        $acRecords = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleIds)
            ->select('subject_schedule_id', 'load', 'employee_name', 'group_name', 'subject_name', 'lesson_date')
            ->get()
            ->keyBy('subject_schedule_id');

        // 3. student_grades da bor schedule IDlar
        $gradeRecords = DB::table('student_grades')
            ->whereIn('subject_schedule_id', $scheduleIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->select('subject_schedule_id')
            ->distinct()
            ->pluck('subject_schedule_id')
            ->flip();

        // 4. Natijalarni tayyorlash
        $rows = [];
        foreach ($schedules as $sch) {
            $hasAC = isset($acRecords[$sch->schedule_hemis_id]);
            $acLoad = $hasAC ? $acRecords[$sch->schedule_hemis_id]->load : null;
            // Ma'ruza va boshqa maxsus turlarga baho talab qilinmaydi
            $skipGradeCheck = in_array($sch->training_type_code, $gradeExcludedTypes);
            $hasGrade = $skipGradeCheck ? null : isset($gradeRecords[$sch->schedule_hemis_id]);

            $rows[] = [
                'schedule_hemis_id' => $sch->schedule_hemis_id,
                'employee_name' => $sch->employee_name,
                'subject_name' => $sch->subject_name,
                'group_name' => $sch->group_name,
                'training_type' => $sch->training_type_code . ' (' . $sch->training_type_name . ')',
                'lesson_pair' => $sch->lesson_pair_code,
                'lesson_date' => $sch->lesson_date,
                'ac_exists' => $hasAC ? 'HA' : 'YO\'Q',
                'ac_load' => $acLoad,
                'has_grade' => $hasGrade === null ? '-' : ($hasGrade ? 'HA' : 'YO\'Q'),
            ];
        }

        // 5. Statistika
        $total = count($rows);
        $withAC = collect($rows)->where('ac_exists', 'HA')->count();
        $withoutAC = $total - $withAC;
        $acLoaded = collect($rows)->where('ac_load', '>', 0)->count();
        $acNotLoaded = collect($rows)->where('ac_exists', 'HA')->where('ac_load', 0)->count();

        // 6. Guruhlangan (employee+group+subject+date) holat
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r['employee_name'] . '|' . $r['group_name'] . '|' . $r['subject_name'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_name' => $r['employee_name'],
                    'subject_name' => $r['subject_name'],
                    'group_name' => $r['group_name'],
                    'schedule_count' => 0,
                    'ac_count' => 0,
                    'no_ac_count' => 0,
                    'loaded_count' => 0,
                    'grade_count' => 0,
                    'lms_says_attendance' => false,
                    'lms_says_grade' => false,
                    'hemis_monitors' => false,
                ];
            }
            $grouped[$key]['schedule_count']++;
            if ($r['ac_exists'] === 'HA') {
                $grouped[$key]['ac_count']++;
                $grouped[$key]['hemis_monitors'] = true;
                if ($r['ac_load'] > 0) {
                    $grouped[$key]['loaded_count']++;
                    $grouped[$key]['lms_says_attendance'] = true;
                }
            } else {
                $grouped[$key]['no_ac_count']++;
            }
            if ($r['has_grade'] === 'HA') {
                $grouped[$key]['grade_count']++;
                $grouped[$key]['lms_says_grade'] = true;
            }
        }

        // LMS hisobotida "belgilanmagan" bo'lib, HEMIS da monitoring yozuvi yo'qlar
        $falsePositives = collect($grouped)->filter(function ($g) {
            return !$g['hemis_monitors'] && (!$g['lms_says_attendance'] || !$g['lms_says_grade']);
        })->values();

        return response()->json([
            'date' => $date,
            'summary' => [
                'jami_schedulelar' => $total,
                'attendance_controls_da_bor' => $withAC,
                'attendance_controls_da_YOQ' => $withoutAC,
                'ac_load_belgilangan' => $acLoaded,
                'ac_load_belgilanMAGAN' => $acNotLoaded,
            ],
            'guruhlangan' => array_values($grouped),
            'false_positives_hemis_da_yoq' => $falsePositives,
            'batafsil_har_bir_schedule' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function lessonAssignmentData(Request $request)
    {
        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        // Bu turlarga faqat davomat tekshiriladi, baho tekshirilmaydi (ma'ruza va h.k.)
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Jadvallardan ma'lumot olish
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        // Joriy semestr filtri (LEFT JOIN bo'lgani uchun semester topilmagan yozuvlarni ham qo'shamiz)
        if ($request->get('current_semester', '1') == '1') {
            $scheduleQuery->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            });
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
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$request->date_from]);
        }

        if ($request->filled('date_to')) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$request->date_to]);
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
            'sch.training_type_code',
            'sch.training_type_name',
            'sch.lesson_pair_code',
            'sch.lesson_pair_start_time',
            'sch.lesson_pair_end_time',
            'g.id as group_db_id',
            DB::raw('DATE(sch.lesson_date) as lesson_date_str')
        )->get();

        if ($schedules->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 2-QADAM: Davomat va baho mavjudligini tekshirish
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $minDate = $schedules->min('lesson_date_str');
        $maxDate = $schedules->max('lesson_date_str');

        // Davomat (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        // Bu HEMIS dagi davomat nazoratini jadval bilan aniq bog'laydi
        $attendanceByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->pluck('subject_schedule_id')
            ->flip();

        // Davomat (2-usul): atribut kalitlari orqali tekshirish (zaxira)
        // subject_schedule_id bo'lmagan yozuvlar uchun
        $attendanceByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        // Baho (1-usul): subject_schedule_id orqali to'g'ridan-to'g'ri tekshirish
        $gradeByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->pluck('subject_schedule_id')
            ->unique()
            ->flip();

        // Baho (2-usul): employee + group (student orqali) + subject + date + training_type + lesson_pair
        $gradeByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.employee_id', $employeeIds)
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(sg.employee_id, '|', st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.training_type_code, '|', sg.lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        // 3-QADAM: Talaba sonini guruh bo'yicha hisoblash
        $groupIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $studentCounts = DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11) // Faol talabalar
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        // 4-QADAM: Ma'lumotlarni guruhlash (employee + group + subject + date + mashg'ulot turi + juftlik)
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            // Davomat va baho tekshirish uchun kalitlar
            $attKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                    . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;
            $gradeKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            // Davomat: schedule_hemis_id orqali yoki atribut kaliti orqali tekshirish
            $hasAtt = isset($attendanceByScheduleId[$sch->schedule_hemis_id])
                   || isset($attendanceByKey[$attKey]);

            // Baho: schedule_hemis_id orqali yoki atribut kaliti (group_id bilan) orqali
            $skipGradeCheck = in_array($sch->training_type_code, $gradeExcludedTypes);
            $hasGrade = $skipGradeCheck ? null : (
                isset($gradeByScheduleId[$sch->schedule_hemis_id])
                || isset($gradeByKey[$gradeKey])
            );

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
                    'subject_id' => $sch->subject_id,
                    'group_id' => $sch->group_id,
                    'group_db_id' => $sch->group_db_id,
                    'group_name' => $sch->group_name,
                    'training_type' => $sch->training_type_name,
                    'lesson_pair_time' => $pairTime,
                    'semester_code' => $sch->semester_code,
                    'lesson_date' => $sch->lesson_date_str,
                    'student_count' => $studentCounts[$sch->group_id] ?? 0,
                    'has_attendance' => $hasAtt,
                    'has_grades' => $hasGrade,
                ];
            } else {
                // Dublikat schedule (bir xil kalit, boshqa schedule_hemis_id) —
                // agar biror dublikatda davomat/baho topilsa, "Ha" deb belgilash
                if ($hasAtt && !$grouped[$key]['has_attendance']) {
                    $grouped[$key]['has_attendance'] = true;
                }
                if ($hasGrade === true && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['has_grades'] = true;
                }
            }
        }

        $results = array_values($grouped);

        // Holat filtri (birlashtirilgan)
        if ($request->filled('status_filter')) {
            $results = array_values(array_filter($results, function ($r) use ($request) {
                return match ($request->status_filter) {
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
            $sheet->setCellValue([14, $row], $r['has_grades'] === null ? '-' : ($r['has_grades'] ? 'Ha' : "Yo'q"));
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
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$request->date_from]);
        }
        if ($request->filled('date_to')) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$request->date_to]);
        }
        if ($request->filled('auditorium')) {
            $scheduleQuery->where('sch.auditorium_code', $request->auditorium);
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
     * AJAX: Auditoriyalar ro'yxatini dropdown uchun qaytarish
     */
    public function getAuditoriums(Request $request)
    {
        $query = \App\Models\Auditorium::where('active', true)
            ->orderBy('name');

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('auditorium_type_code')) {
            $query->where('auditorium_type_code', $request->auditorium_type_code);
        }

        return $query->select('code', 'name')
            ->get()
            ->pluck('name', 'code');
    }

    /**
     * AJAX: HEMIS API dan auditoriyalarni sinxronlashtirish
     */
    public function syncAuditoriums()
    {
        try {
            $token = config('services.hemis.token');
            $page = 1;
            $pageSize = 40;
            $totalImported = 0;

            do {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(30)
                    ->get("https://student.ttatf.uz/rest/v1/data/auditorium-list?limit=$pageSize&page=$page");

                if (!$response->successful()) {
                    return response()->json(['error' => 'HEMIS API dan ma\'lumot olishda xatolik'], 500);
                }

                $data = $response->json()['data'];
                $items = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                foreach ($items as $item) {
                    \App\Models\Auditorium::updateOrCreate(
                        ['code' => $item['code']],
                        [
                            'name' => $item['name'],
                            'volume' => $item['volume'] ?? 0,
                            'active' => $item['active'] ?? true,
                            'building_id' => $item['building']['id'] ?? null,
                            'building_name' => $item['building']['name'] ?? null,
                            'auditorium_type_code' => $item['auditoriumType']['code'] ?? null,
                            'auditorium_type_name' => $item['auditoriumType']['name'] ?? null,
                        ]
                    );
                    $totalImported++;
                }

                $page++;
            } while ($page <= $totalPages);

            return response()->json([
                'success' => true,
                'message' => "Jami {$totalImported} ta auditoriya sinxronlashtirildi",
                'total' => $totalImported,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Auditorium sync error: ' . $e->getMessage());
            return response()->json(['error' => 'Sinxronlashtirishda xatolik: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Auditoriyalar ro'yxati sahifasi
     */
    public function auditoriumList()
    {
        return view('admin.reports.auditorium-list');
    }

    /**
     * AJAX: Auditoriyalar ro'yxati ma'lumotlarini qaytarish
     */
    public function auditoriumListData(Request $request)
    {
        try {
            $query = \App\Models\Auditorium::query();

            if ($request->filled('building_id')) {
                $query->where('building_id', $request->building_id);
            }
            if ($request->filled('auditorium_type_code')) {
                $query->where('auditorium_type_code', $request->auditorium_type_code);
            }
            if ($request->filled('status')) {
                $query->where('active', $request->status == '1');
            }

            // Saralash
            $sortColumn = $request->get('sort', 'name');
            $allowedSorts = ['code', 'name', 'volume', 'building_name', 'auditorium_type_name', 'active'];
            if (!in_array($sortColumn, $allowedSorts)) {
                $sortColumn = 'name';
            }
            $sortDirection = $request->get('direction', 'asc') === 'desc' ? 'desc' : 'asc';
            $query->orderBy($sortColumn, $sortDirection);

            $total = $query->count();

            // Excel export
            if ($request->get('export') === 'excel') {
                $data = $query->get();
                return $this->exportAuditoriumListExcel($data);
            }

            // Pagination
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 50);
            $offset = ($page - 1) * $perPage;

            $items = $query->skip($offset)->take($perPage)->get();

            $pageData = [];
            foreach ($items as $i => $item) {
                $pageData[] = [
                    'row_num' => $offset + $i + 1,
                    'code' => $item->code,
                    'name' => $item->name,
                    'volume' => $item->volume,
                    'active' => $item->active,
                    'building_id' => $item->building_id,
                    'building_name' => $item->building_name,
                    'auditorium_type_code' => $item->auditorium_type_code,
                    'auditorium_type_name' => $item->auditorium_type_name,
                ];
            }

            return response()->json([
                'data' => $pageData,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Auditorium list error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: Binolar ro'yxatini dropdown uchun qaytarish
     */
    public function getAuditoriumBuildings()
    {
        return \App\Models\Auditorium::whereNotNull('building_id')
            ->whereNotNull('building_name')
            ->select('building_id', 'building_name')
            ->distinct()
            ->orderBy('building_name')
            ->get()
            ->pluck('building_name', 'building_id');
    }

    /**
     * AJAX: Auditoriya turlarini dropdown uchun qaytarish
     */
    public function getAuditoriumTypes()
    {
        return \App\Models\Auditorium::whereNotNull('auditorium_type_code')
            ->whereNotNull('auditorium_type_name')
            ->select('auditorium_type_code', 'auditorium_type_name')
            ->distinct()
            ->orderBy('auditorium_type_name')
            ->get()
            ->pluck('auditorium_type_name', 'auditorium_type_code');
    }

    /**
     * Auditoriyalar ro'yxati Excel export
     */
    private function exportAuditoriumListExcel($data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoriyalar');

        $headers = ['#', 'Kod', 'Nomi', "Sig'imi", 'Bino', 'Turi', 'Holat'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r->code);
            $sheet->setCellValue([3, $row], $r->name);
            $sheet->setCellValue([4, $row], $r->volume);
            $sheet->setCellValue([5, $row], $r->building_name);
            $sheet->setCellValue([6, $row], $r->auditorium_type_name);
            $sheet->setCellValue([7, $row], $r->active ? 'Faol' : 'Nofaol');
        }

        $widths = [5, 15, 30, 10, 25, 25, 10];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:G{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Auditoriyalar_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'aud_');

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
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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
        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        } elseif ($request->filled('faculty')) {
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
            'studentStatuses',
            'dekanFacultyIds'
        ));
    }

    /**
     * AJAX: 25% sababsiz davomat hisobot ma'lumotlarini hisoblash
     */
    public function absenceReportData(Request $request)
    {
        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        try {
        ini_set('memory_limit', '512M');
        set_time_limit(120);

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
        $groupsData = DB::table('groups')
            ->whereIn('group_hemis_id', $scheduleGroupIds)
            ->select('id', 'group_hemis_id', 'curriculum_hemis_id')
            ->get();

        $groupCurriculumMap = [];
        $groupDbIdMap = [];
        foreach ($groupsData as $g) {
            $groupCurriculumMap[$g->group_hemis_id] = $g->curriculum_hemis_id;
            $groupDbIdMap[$g->group_hemis_id] = $g->id;
        }

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
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->min('lesson_date');
        }

        // 4-QADAM: Har bir talaba/fan uchun davomat ma'lumotlarini hisoblash
        $studentSubjectData = [];
        $studentAllAttendanceDates = []; // Talabaning ISTALGAN fandan darsga chiqqan kunlari
        $now = Carbon::now('Asia/Tashkent');
        $spravkaDays = (int) Setting::get('spravka_deadline_days', 10);

        // student_grades ni chunk qilib olish (katta ma'lumot uchun)
        foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
            $gradesChunk = DB::table('student_grades')
                ->whereIn('student_hemis_id', $hemisChunk)
                ->whereIn('subject_id', $filteredSubjectIds)
                ->whereIn('semester_code', $validSemesterCodes)
                ->whereNotIn('training_type_code', $excludedCodes)
                ->whereNotNull('lesson_date')
                ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'grade', 'lesson_date', 'lesson_pair_code', 'reason', 'status', 'deadline')
                ->get();

            foreach ($gradesChunk as $g) {
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
                        // Istalgan fandan grade > 0 bo'lsa, talabaning umumiy davomatiga qo'shish
                        $studentAllAttendanceDates[$g->student_hemis_id][$dateKey] = true;
                    }
                }
            }
            unset($gradesChunk);
        }

        // attendance_controls dan istalgan fandan dars o'tilgan kunlarni olish (load > 0)
        $groupStudentsMap = [];
        foreach ($studentGroupMap as $studentHemisId => $groupId) {
            $groupStudentsMap[$groupId][] = $studentHemisId;
        }

        $allGroupHemisIds = array_unique(array_values($studentGroupMap));
        foreach (array_chunk($allGroupHemisIds, 1000) as $groupChunk) {
            $acRecords = DB::table('attendance_controls')
                ->whereNull('deleted_at')
                ->whereIn('group_id', $groupChunk)
                ->where('load', '>', 0)
                ->whereNotNull('lesson_date')
                ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('group_id', DB::raw('DATE(lesson_date) as lesson_date'))
                ->distinct()
                ->get();

            foreach ($acRecords as $ac) {
                $dateKey = $ac->lesson_date;
                $studentsInGroup = $groupStudentsMap[$ac->group_id] ?? [];
                foreach ($studentsInGroup as $studentHemisId) {
                    $studentAllAttendanceDates[$studentHemisId][$dateKey] = true;
                }
            }
            unset($acRecords);
        }
        unset($groupStudentsMap);

        // 5-QADAM: Foiz chegarasi bo'yicha filtrlash
        $minPercent = (int) $request->get('min_percent', 15);
        $results = [];
        foreach ($studentSubjectData as $ssKey => $data) {
            $comboKey = $data['combo_key'];
            $totalAuditoryHours = $auditoryHours[$comboKey] ?? 0;

            if ($totalAuditoryHours <= 0) continue;

            $unexcusedPercent = round(($data['unexcused_absent_hours'] / $totalAuditoryHours) * 100);

            if ($unexcusedPercent < $minPercent) continue;

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
                // Istalgan fandan darsga chiqqan kunlar (student_grades grade>0 YOKI attendance_controls load>0)
                $allDates = array_keys($studentAllAttendanceDates[$data['student_hemis_id']] ?? []);
                sort($allDates);
                foreach ($allDates as $gDate) {
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
                'group_db_id' => $groupDbIdMap[$r['group_id']] ?? null,
                'subject_id' => $r['subject_id'],
                'semester_code' => $r['semester_code'],
            ];
        }

        // Saralash
        $sortColumn = $request->get('sort', 'unexcused_percent');
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
                ->whereNull('ac.deleted_at')
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

    /**
     * 4 va undan ortiq qarzdorlar hisoboti sahifasi
     */
    public function debtorsReport(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if ($dekanFacultyId) {
            $facultyQuery->where('id', $dekanFacultyId);
        }

        $faculties = $facultyQuery->get();

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
        if ($dekanFacultyId) {
            $kafedraQuery->where('f.id', $dekanFacultyId);
        } elseif ($request->filled('faculty')) {
            $kafedraQuery->where('f.id', $request->faculty);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        return view('admin.reports.debtors', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'studentStatuses',
            'dekanFacultyId'
        ));
    }

    /**
     * AJAX: 4 va undan ortiq qarzdorlar hisobot ma'lumotlarini hisoblash
     */
    public function debtorsReportData(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);
            $minDebtCount = (int) $request->get('min_debt_count', 4);

            // 1-QADAM: Schedule dan unique (group, subject, semester) olish
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

            if ($request->filled('education_type')) {
                $educationGroupIds = DB::table('groups')
                    ->whereIn('curriculum_hemis_id',
                        Curriculum::where('education_type_code', $request->education_type)
                            ->pluck('curricula_hemis_id')
                    )
                    ->pluck('group_hemis_id')
                    ->toArray();
                $scheduleQuery->whereIn('sch.group_id', $educationGroupIds);
            }
            if ($request->filled('semester_code')) {
                $scheduleQuery->where('sch.semester_code', $request->semester_code);
            }
            if ($request->filled('group')) {
                $scheduleQuery->where('sch.group_id', $request->group);
            }
            if ($request->filled('subject')) {
                $scheduleQuery->where('sch.subject_id', $request->subject);
            }
            if ($request->filled('department')) {
                $deptSubjectIds = DB::table('curriculum_subjects')
                    ->where('department_id', $request->department)
                    ->pluck('subject_id')
                    ->unique()
                    ->values()
                    ->toArray();
                if (!empty($deptSubjectIds)) {
                    $scheduleQuery->whereIn('sch.subject_id', $deptSubjectIds);
                }
            }

            $scheduleCombos = $scheduleQuery->get();
            if ($scheduleCombos->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $scheduleGroupIds = $scheduleCombos->pluck('group_id')->unique()->toArray();
            $validSubjectIds = $scheduleCombos->pluck('subject_id')->unique()->toArray();
            $validSemesterCodes = $scheduleCombos->pluck('semester_code')->unique()->toArray();

            // Darslar soni - har bir guruh uchun necha kun dars o'tilgan
            $lessonDaysQuery = DB::table('schedules as sch2')
                ->whereNotIn('sch2.training_type_code', $excludedCodes)
                ->whereNotNull('sch2.lesson_date')
                ->whereIn('sch2.group_id', $scheduleGroupIds);

            if ($isCurrentSemester) {
                $lessonDaysQuery
                    ->join('groups as gr2', 'gr2.group_hemis_id', '=', 'sch2.group_id')
                    ->join('semesters as sem2', function ($join) {
                        $join->on('sem2.code', '=', 'sch2.semester_code')
                            ->on('sem2.curriculum_hemis_id', '=', 'gr2.curriculum_hemis_id');
                    })
                    ->where('sem2.current', true)
                    ->where('sch2.education_year_current', true);
            }

            if ($request->filled('semester_code')) {
                $lessonDaysQuery->where('sch2.semester_code', $request->semester_code);
            }

            $lessonDayCounts = $lessonDaysQuery
                ->select('sch2.group_id', DB::raw('COUNT(DISTINCT sch2.lesson_date) as days_count'))
                ->groupBy('sch2.group_id')
                ->pluck('days_count', 'sch2.group_id')
                ->toArray();

            // Auditoriya soatlarini hisoblash
            $groupsData = DB::table('groups')
                ->whereIn('group_hemis_id', $scheduleGroupIds)
                ->select('id', 'group_hemis_id', 'curriculum_hemis_id')
                ->get();

            $groupCurriculumMap = [];
            $groupDbIdMap = [];
            foreach ($groupsData as $g) {
                $groupCurriculumMap[$g->group_hemis_id] = $g->curriculum_hemis_id;
                $groupDbIdMap[$g->group_hemis_id] = $g->id;
            }

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

            // 2-QADAM: Talabalar ro'yxati
            $studentQuery = DB::table('students as s')
                ->select('s.hemis_id', 's.group_id', 's.level_code', 's.student_status_code');

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
            if ($request->filled('education_type')) {
                $studentQuery->where('s.education_type_code', $request->education_type);
            }

            $students = $studentQuery->whereIn('s.group_id', $scheduleGroupIds)->get();
            if ($students->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $studentGroupMap = $students->pluck('group_id', 'hemis_id')->toArray();
            $studentLevelMap = $students->pluck('level_code', 'hemis_id')->toArray();

            // Deadline ma'lumotlarini olish
            $deadlines = Deadline::all()->keyBy('level_code');

            // Vedomost ma'lumotlarini olish (oski_percent, test_percent, oraliq_percent)
            $vedomosts = DB::table('vedomosts')
                ->whereIn('semester_code', $validSemesterCodes)
                ->select('group_name', 'subject_name', 'semester_code',
                    'oski_percent', 'test_percent', 'oraliq_percent',
                    'jb_percent', 'independent_percent', 'shakl')
                ->get();

            $vedomostMap = [];
            foreach ($vedomosts as $v) {
                $vKey = $v->group_name . '|' . $v->subject_name . '|' . $v->semester_code;
                if (!isset($vedomostMap[$vKey]) || $v->shakl > ($vedomostMap[$vKey]->shakl ?? 0)) {
                    $vedomostMap[$vKey] = $v;
                }
            }

            // Group name map
            $groupNameMap = DB::table('groups')
                ->whereIn('group_hemis_id', $scheduleGroupIds)
                ->pluck('name', 'group_hemis_id')
                ->toArray();

            // Subject name map
            $subjectNameMap = [];

            // 3-QADAM: student_grades dan baholarni hisoblash
            $studentSubjectData = [];

            foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
                $gradesChunk = DB::table('student_grades')
                    ->whereIn('student_hemis_id', $hemisChunk)
                    ->whereIn('subject_id', $validSubjectIds)
                    ->whereIn('semester_code', $validSemesterCodes)
                    ->whereNotNull('lesson_date')
                    ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                        'training_type_code', 'grade', 'lesson_date', 'reason', 'status',
                        'oski_id', 'test_id')
                    ->get();

                foreach ($gradesChunk as $g) {
                    $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
                    if (!$groupId) continue;

                    $ssKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code;

                    if (!isset($studentSubjectData[$ssKey])) {
                        $subjectNameMap[$g->subject_id] = $g->subject_name;
                        $studentSubjectData[$ssKey] = [
                            'student_hemis_id' => $g->student_hemis_id,
                            'subject_id' => $g->subject_id,
                            'subject_name' => $g->subject_name,
                            'semester_code' => $g->semester_code,
                            'group_id' => $groupId,
                            'jb_grades' => [],
                            'mt_grades' => [],
                            'on_grades' => [],
                            'oski_grades' => [],
                            'test_grades' => [],
                            'total_absent_hours' => 0,
                            'unexcused_absent_hours' => 0,
                        ];
                    }

                    $code = (int) $g->training_type_code;
                    $dateKey = substr($g->lesson_date, 0, 10);

                    // Davomatni hisoblash (auditoriya darslari - excludedCodes dan tashqari)
                    if (!in_array($code, $excludedCodes)) {
                        if ($g->reason === 'absent') {
                            $studentSubjectData[$ssKey]['total_absent_hours'] += 2;
                            if ($g->status !== 'retake') {
                                $studentSubjectData[$ssKey]['unexcused_absent_hours'] += 2;
                            }
                        }
                    }

                    // Baholarni yig'ish (training_type_code bo'yicha)
                    if ($g->grade !== null && $g->grade > 0) {
                        if (!in_array($code, $excludedCodes)) {
                            // JB (auditoriya darslari)
                            $studentSubjectData[$ssKey]['jb_grades'][$dateKey][] = (float) $g->grade;
                        } elseif ($code === 99) {
                            $studentSubjectData[$ssKey]['mt_grades'][$dateKey][] = (float) $g->grade;
                        } elseif ($code === 100) {
                            $studentSubjectData[$ssKey]['on_grades'][$dateKey][] = (float) $g->grade;
                        } elseif ($code === 101) {
                            $studentSubjectData[$ssKey]['oski_grades'][$dateKey][] = (float) $g->grade;
                        } elseif ($code === 102) {
                            $studentSubjectData[$ssKey]['test_grades'][$dateKey][] = (float) $g->grade;
                        }
                    }
                }
                unset($gradesChunk);
            }

            // 4-QADAM: Har bir talaba/fan uchun JN va yiqilish sabablarini hisoblash
            $allStudentResults = [];

            foreach ($studentSubjectData as $ssKey => $data) {
                $comboKey = $data['group_id'] . '|' . $data['subject_id'] . '|' . $data['semester_code'];
                $totalAuditoryHours = $auditoryHours[$comboKey] ?? 0;

                $markingScore = MarkingSystemScore::getByStudentHemisId($data['student_hemis_id']);
                $joriyMin = $markingScore->effectiveLimit('jn');
                $mtMin = $markingScore->effectiveLimit('mt');

                $groupName = $groupNameMap[$data['group_id']] ?? '';
                $vKey = $groupName . '|' . $data['subject_name'] . '|' . $data['semester_code'];
                $vedomost = $vedomostMap[$vKey] ?? null;

                $hasOski = $vedomost && $vedomost->oski_percent > 0;
                $hasTest = $vedomost && $vedomost->test_percent > 0;
                $hasOn = $vedomost && ($vedomost->oraliq_percent ?? 0) > 0;

                // O'rtacha baholarni hisoblash
                $jbAvg = $this->calcDailyAverage($data['jb_grades']);
                $mtAvg = $this->calcDailyAverage($data['mt_grades']);
                $onAvg = $this->calcDailyAverage($data['on_grades']);
                $oskiAvg = $this->calcDailyAverage($data['oski_grades']);
                $testAvg = $this->calcDailyAverage($data['test_grades']);

                // JN o'rtachasi hisoblash
                $jnAvg = 0;
                if ($vedomost) {
                    $jbPct = $vedomost->jb_percent ?? 0;
                    $mtPct = $vedomost->independent_percent ?? 0;
                    $onPct = $vedomost->oraliq_percent ?? 0;
                    $totalPct = $jbPct + $mtPct + $onPct;
                    if ($totalPct > 0) {
                        $jnRaw = round($jbAvg) * $jbPct / 100 + round($mtAvg) * $mtPct / 100 + ($hasOn ? round($onAvg) * $onPct / 100 : 0);
                        $jnAvg = round($jnRaw / $totalPct * 100);
                    }
                } else {
                    $jnAvg = round($jbAvg);
                }

                // Davomatdan sababsiz %
                $absencePercent = $totalAuditoryHours > 0
                    ? round(($data['unexcused_absent_hours'] / $totalAuditoryHours) * 100)
                    : 0;

                // Yiqilish sabablarini aniqlash
                $reasons = [];

                if ($jnAvg < $joriyMin) {
                    $reasons[] = 'JN% < ' . $joriyMin . ' (' . $jnAvg . ')';
                }
                if (round($mtAvg) < $mtMin) {
                    $reasons[] = 'MT < ' . $mtMin . ' (' . round($mtAvg) . ')';
                }
                $onMin = $markingScore->effectiveLimit('on');
                if ($hasOn && round($onAvg) < $onMin) {
                    $reasons[] = 'ON < ' . $onMin . ' (' . round($onAvg) . ')';
                }
                $oskiMin = $markingScore->effectiveLimit('oski');
                if ($hasOski && round($oskiAvg) < $oskiMin) {
                    $reasons[] = 'OSKI < ' . $oskiMin . ' (' . round($oskiAvg) . ')';
                }
                $testMin = $markingScore->effectiveLimit('test');
                if ($hasTest && round($testAvg) < $testMin) {
                    $reasons[] = 'Test < ' . $testMin . ' (' . round($testAvg) . ')';
                }
                if ($absencePercent > 25) {
                    $reasons[] = 'Davomat > 25% (' . $absencePercent . '%)';
                }

                $hemis = $data['student_hemis_id'];
                if (!isset($allStudentResults[$hemis])) {
                    $allStudentResults[$hemis] = [];
                }

                $allStudentResults[$hemis][] = [
                    'subject_id' => $data['subject_id'],
                    'subject_name' => $data['subject_name'],
                    'semester_code' => $data['semester_code'],
                    'group_id' => $groupDbIdMap[$data['group_id']] ?? $data['group_id'],
                    'jb' => round($jbAvg),
                    'mt' => round($mtAvg),
                    'on' => $hasOn ? round($onAvg) : null,
                    'oski' => $hasOski ? round($oskiAvg) : null,
                    'test' => $hasTest ? round($testAvg) : null,
                    'jn_percent' => $jnAvg,
                    'absence_percent' => $absencePercent,
                    'auditory_hours' => $totalAuditoryHours,
                    'unexcused_hours' => $data['unexcused_absent_hours'],
                    'reasons' => $reasons,
                    'minimum_limit' => $markingScore->minimum_limit,
                ];
            }

            // 4.5-QADAM: Dublikat fanlarni filtrlash
            // "Umumiy xirurgiya (a)", "(b)", "(c)" kabi fanlardan faqat eng yuqori JN baholi variantni saqlash
            $studentDebts = [];
            foreach ($allStudentResults as $hemisId => $results) {
                $grouped = [];
                foreach ($results as $r) {
                    $baseName = preg_replace('/\s*\([a-zA-Zа-яА-Яa-zа-я]\)\s*$/u', '', $r['subject_name']);
                    $grouped[$baseName][] = $r;
                }

                foreach ($grouped as $variants) {
                    if (count($variants) > 1) {
                        // Eng yuqori JN baholi variantni tanlash
                        usort($variants, fn($a, $b) => $b['jn_percent'] <=> $a['jn_percent']);
                    }
                    $best = $variants[0];
                    if (!empty($best['reasons'])) {
                        if (!isset($studentDebts[$hemisId])) {
                            $studentDebts[$hemisId] = [];
                        }
                        $studentDebts[$hemisId][] = $best;
                    }
                }
            }
            unset($allStudentResults);

            // 5-QADAM: minDebtCount bo'yicha filtrlash
            $studentDebts = array_filter($studentDebts, fn($debts) => count($debts) >= $minDebtCount);

            if (empty($studentDebts)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            // Talaba ma'lumotlarini biriktirish
            $hemisIds = array_keys($studentDebts);
            $studentInfo = DB::table('students')
                ->whereIn('hemis_id', $hemisIds)
                ->select('hemis_id', 'full_name', 'student_id_number', 'department_name',
                    'specialty_name', 'level_name', 'semester_name', 'group_name', 'group_id')
                ->get()
                ->keyBy('hemis_id');

            $finalResults = [];
            foreach ($studentDebts as $hemisId => $debts) {
                $st = $studentInfo[$hemisId] ?? null;
                if (!$st) continue;

                $finalResults[] = [
                    'hemis_id' => $hemisId,
                    'full_name' => $st->full_name ?? 'Noma\'lum',
                    'student_id_number' => $st->student_id_number ?? '-',
                    'department_name' => $st->department_name ?? '-',
                    'specialty_name' => $st->specialty_name ?? '-',
                    'level_name' => $st->level_name ?? '-',
                    'semester_name' => $st->semester_name ?? '-',
                    'group_name' => $st->group_name ?? '-',
                    'group_id' => $st->group_id ?? '',
                    'debt_count' => count($debts),
                    'lesson_days' => $lessonDayCounts[$st->group_id] ?? 0,
                    'debts' => $debts,
                ];
            }

            // Saralash (default: qarzdorlik soni kamayib borish tartibida)
            $sortColumn = $request->get('sort', 'debt_count');
            $sortDirection = $request->get('direction', 'desc');

            usort($finalResults, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = $a[$sortColumn] ?? '';
                $valB = $b[$sortColumn] ?? '';
                $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            // Excel eksport
            if ($request->get('export') === 'summary') {
                return $this->exportDebtorsSummaryExcel($finalResults);
            }
            if ($request->get('export') === 'full') {
                return $this->exportDebtorsFullExcel($finalResults);
            }

            // Sahifalash
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
            \Log::error('Debtors report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Kunlik o'rtacha hisoblash (har bir kun uchun o'rtacha -> umumiy o'rtacha)
     */
    private function calcDailyAverage(array $dayGrades): float
    {
        if (empty($dayGrades)) return 0;
        $dayAverages = [];
        foreach ($dayGrades as $dateKey => $grades) {
            $dayAverages[] = round(array_sum($grades) / count($grades));
        }
        return count($dayAverages) > 0 ? array_sum($dayAverages) / count($dayAverages) : 0;
    }

    /**
     * Qarzdorlar hisoboti - Excel (qisqacha: talaba guruhlangan)
     */
    private function exportDebtorsSummaryExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qarzdorlar hisoboti');

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh',
            'Qarzdor fanlar soni', 'Darslar soni (kun)'];
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
            $sheet->setCellValueExplicit([3, $row], $r['student_id_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([4, $row], $r['department_name']);
            $sheet->setCellValue([5, $row], $r['specialty_name']);
            $sheet->setCellValue([6, $row], $r['level_name']);
            $sheet->setCellValue([7, $row], $r['semester_name']);
            $sheet->setCellValue([8, $row], $r['group_name']);
            $sheet->setCellValue([9, $row], $r['debt_count']);
            $sheet->setCellValue([10, $row], $r['lesson_days']);
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 12, 60];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'qarzdorlar_hisobot_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'dbt_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Qarzdorlar hisoboti - Excel (to'liq: har bir fan alohida qator)
     */
    private function exportDebtorsFullExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Qarzdorlar toliq');

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh',
            'Fan', 'JB', 'MT', 'ON', 'JN%', 'OSKI', 'Test', 'Davomat %', 'Sababsiz soat', 'Auditoriya soati', 'Sabab'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:S1')->applyFromArray($headerStyle);

        $rowNum = 2;
        $idx = 1;
        foreach ($data as $student) {
            foreach ($student['debts'] as $debt) {
                $sheet->setCellValue([1, $rowNum], $idx);
                $sheet->setCellValue([2, $rowNum], $student['full_name']);
                $sheet->setCellValueExplicit([3, $rowNum], $student['student_id_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue([4, $rowNum], $student['department_name']);
                $sheet->setCellValue([5, $rowNum], $student['specialty_name']);
                $sheet->setCellValue([6, $rowNum], $student['level_name']);
                $sheet->setCellValue([7, $rowNum], $student['semester_name']);
                $sheet->setCellValue([8, $rowNum], $student['group_name']);
                $sheet->setCellValue([9, $rowNum], $debt['subject_name']);
                $sheet->setCellValue([10, $rowNum], $debt['jb']);
                $sheet->setCellValue([11, $rowNum], $debt['mt']);
                $sheet->setCellValue([12, $rowNum], $debt['on'] ?? '-');
                $sheet->setCellValue([13, $rowNum], $debt['jn_percent']);
                $sheet->setCellValue([14, $rowNum], $debt['oski'] ?? '-');
                $sheet->setCellValue([15, $rowNum], $debt['test'] ?? '-');
                $sheet->setCellValue([16, $rowNum], $debt['absence_percent'] . '%');
                $sheet->setCellValue([17, $rowNum], $debt['unexcused_hours']);
                $sheet->setCellValue([18, $rowNum], $debt['auditory_hours']);
                $sheet->setCellValue([19, $rowNum], implode('; ', $debt['reasons']));

                // Qizil fon yiqilgan ballarga
                $redFill = [
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
                ];
                $minLimit = $debt['minimum_limit'] ?? 60;
                if ($debt['jn_percent'] < $minLimit) $sheet->getStyle("M{$rowNum}")->applyFromArray($redFill);
                if ($debt['mt'] < $minLimit) $sheet->getStyle("K{$rowNum}")->applyFromArray($redFill);
                if ($debt['on'] !== null && $debt['on'] < $minLimit) $sheet->getStyle("L{$rowNum}")->applyFromArray($redFill);
                if ($debt['oski'] !== null && $debt['oski'] < $minLimit) $sheet->getStyle("N{$rowNum}")->applyFromArray($redFill);
                if ($debt['test'] !== null && $debt['test'] < $minLimit) $sheet->getStyle("O{$rowNum}")->applyFromArray($redFill);
                if ($debt['absence_percent'] > 25) $sheet->getStyle("P{$rowNum}")->applyFromArray($redFill);

                $rowNum++;
                $idx++;
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 35, 8, 8, 8, 8, 8, 8, 12, 12, 12, 40];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = $rowNum - 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:S{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'qarzdorlar_toliq_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'dbt_full_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
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

    /**
     * 5 ga da'vogar talabalar sahifasi
     */
    public function topStudents(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if ($dekanFacultyId) {
            $facultyQuery->where('id', $dekanFacultyId);
        }

        $faculties = $facultyQuery->get();

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
        if ($dekanFacultyId) {
            $kafedraQuery->where('f.id', $dekanFacultyId);
        } elseif ($request->filled('faculty')) {
            $kafedraQuery->where('f.id', $request->faculty);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        return view('admin.reports.top-students', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'studentStatuses',
            'dekanFacultyId'
        ));
    }

    /**
     * AJAX: 5 ga da'vogar talabalar hisobot ma'lumotlarini hisoblash
     * Har bir talaba uchun barcha fanlar bo'yicha kunlik baholarni ko'rsatadi
     * < 90 (yoki tanlangan chegaradan past) bo'lgan kunlarni ajratib ko'rsatadi
     */
    public function topStudentsData(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);
            $scoreLimit = (int) $request->get('score_limit', 90);

            // 1-QADAM: Schedule dan unique (group, subject, semester) olish
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

            if ($request->filled('education_type')) {
                $educationGroupIds = DB::table('groups')
                    ->whereIn('curriculum_hemis_id',
                        Curriculum::where('education_type_code', $request->education_type)
                            ->pluck('curricula_hemis_id')
                    )
                    ->pluck('group_hemis_id')
                    ->toArray();
                $scheduleQuery->whereIn('sch.group_id', $educationGroupIds);
            }
            if ($request->filled('semester_code')) {
                $scheduleQuery->where('sch.semester_code', $request->semester_code);
            }
            if ($request->filled('group')) {
                $scheduleQuery->where('sch.group_id', $request->group);
            }
            if ($request->filled('subject')) {
                $scheduleQuery->where('sch.subject_id', $request->subject);
            }
            if ($request->filled('department')) {
                $deptSubjectIds = DB::table('curriculum_subjects')
                    ->where('department_id', $request->department)
                    ->pluck('subject_id')
                    ->unique()
                    ->values()
                    ->toArray();
                if (!empty($deptSubjectIds)) {
                    $scheduleQuery->whereIn('sch.subject_id', $deptSubjectIds);
                }
            }

            $scheduleCombos = $scheduleQuery->get();
            if ($scheduleCombos->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $scheduleGroupIds = $scheduleCombos->pluck('group_id')->unique()->toArray();
            $validSubjectIds = $scheduleCombos->pluck('subject_id')->unique()->toArray();
            $validSemesterCodes = $scheduleCombos->pluck('semester_code')->unique()->toArray();

            // Vedomost ma'lumotlarini olish
            $groupNameMap = DB::table('groups')
                ->whereIn('group_hemis_id', $scheduleGroupIds)
                ->pluck('name', 'group_hemis_id')
                ->toArray();

            $groupsData = DB::table('groups')
                ->whereIn('group_hemis_id', $scheduleGroupIds)
                ->select('id', 'group_hemis_id', 'curriculum_hemis_id')
                ->get();

            $groupCurriculumMap = [];
            $groupDbIdMap = [];
            foreach ($groupsData as $g) {
                $groupCurriculumMap[$g->group_hemis_id] = $g->curriculum_hemis_id;
                $groupDbIdMap[$g->group_hemis_id] = $g->id;
            }

            $vedomosts = DB::table('vedomosts')
                ->whereIn('semester_code', $validSemesterCodes)
                ->select('group_name', 'subject_name', 'semester_code',
                    'oski_percent', 'test_percent', 'oraliq_percent',
                    'jb_percent', 'independent_percent', 'shakl')
                ->get();

            $vedomostMap = [];
            foreach ($vedomosts as $v) {
                $vKey = $v->group_name . '|' . $v->subject_name . '|' . $v->semester_code;
                if (!isset($vedomostMap[$vKey]) || $v->shakl > ($vedomostMap[$vKey]->shakl ?? 0)) {
                    $vedomostMap[$vKey] = $v;
                }
            }

            // 2-QADAM: Faqat "5 ga da'vogar" ro'yxatidagi talabalar
            $studentQuery = DB::table('students as s')
                ->select('s.hemis_id', 's.group_id', 's.level_code', 's.student_status_code')
                ->where('s.is_five_candidate', true);

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
            if ($request->filled('education_type')) {
                $studentQuery->where('s.education_type_code', $request->education_type);
            }

            $students = $studentQuery->whereIn('s.group_id', $scheduleGroupIds)->get();
            if ($students->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $studentGroupMap = $students->pluck('group_id', 'hemis_id')->toArray();

            // 3-QADAM: student_grades dan baholarni olish
            // Joriy semestr bo'lsa, faqat joriy semestr dars sanalaridan filtrlash
            $minScheduleDate = null;
            if ($isCurrentSemester) {
                $minScheduleDate = DB::table('schedules as sch2')
                    ->join('groups as gr2', 'gr2.group_hemis_id', '=', 'sch2.group_id')
                    ->join('semesters as sem2', function ($join) {
                        $join->on('sem2.code', '=', 'sch2.semester_code')
                            ->on('sem2.curriculum_hemis_id', '=', 'gr2.curriculum_hemis_id');
                    })
                    ->where('sem2.current', true)
                    ->where('sch2.education_year_current', true)
                    ->whereNull('sch2.deleted_at')
                    ->whereNotNull('sch2.lesson_date')
                    ->min('sch2.lesson_date');
            }

            // Har bir talaba, fan, kun, dars turi bo'yicha
            $allGrades = [];

            foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
                $gradesChunk = DB::table('student_grades')
                    ->whereIn('student_hemis_id', $hemisChunk)
                    ->whereIn('subject_id', $validSubjectIds)
                    ->whereIn('semester_code', $validSemesterCodes)
                    ->whereNotNull('lesson_date')
                    ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                    ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                        'training_type_code', 'grade', 'lesson_date', 'reason')
                    ->get();

                foreach ($gradesChunk as $g) {
                    $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
                    if (!$groupId) continue;

                    $code = (int) $g->training_type_code;
                    $dateKey = substr($g->lesson_date, 0, 10);

                    // Dars turini aniqlash
                    if (!in_array($code, $excludedCodes)) {
                        $lessonType = 'JN';
                    } elseif ($code === 99) {
                        $lessonType = 'MT';
                    } elseif ($code === 100) {
                        $lessonType = 'ON';
                    } elseif ($code === 101) {
                        $lessonType = 'OSKI';
                    } elseif ($code === 102) {
                        $lessonType = 'Test';
                    } else {
                        continue;
                    }

                    $studentKey = $g->student_hemis_id;
                    $subjectKey = $g->subject_id . '|' . $g->semester_code;

                    if (!isset($allGrades[$studentKey])) {
                        $allGrades[$studentKey] = [];
                    }
                    if (!isset($allGrades[$studentKey][$subjectKey])) {
                        $allGrades[$studentKey][$subjectKey] = [
                            'subject_id' => $g->subject_id,
                            'subject_name' => $g->subject_name,
                            'semester_code' => $g->semester_code,
                            'group_id' => $groupId,
                            'days' => [],
                        ];
                    }

                    $dayTypeKey = $dateKey . '|' . $lessonType;
                    if (!isset($allGrades[$studentKey][$subjectKey]['days'][$dayTypeKey])) {
                        $allGrades[$studentKey][$subjectKey]['days'][$dayTypeKey] = [
                            'date' => $dateKey,
                            'lesson_type' => $lessonType,
                            'grades' => [],
                            'absent' => false,
                        ];
                    }

                    if ($g->reason === 'absent') {
                        $allGrades[$studentKey][$subjectKey]['days'][$dayTypeKey]['absent'] = true;
                    }
                    if ($g->grade !== null && $g->grade > 0) {
                        $allGrades[$studentKey][$subjectKey]['days'][$dayTypeKey]['grades'][] = (float) $g->grade;
                    }
                }
                unset($gradesChunk);
            }

            // 4-QADAM: Har bir talaba uchun < scoreLimit bo'lgan kunlarni hisoblash
            $studentResults = [];

            foreach ($allGrades as $hemisId => $subjects) {
                $lowDays = [];
                $totalDays = 0;
                $lowDayCount = 0;

                foreach ($subjects as $subjectKey => $subjectData) {
                    $groupName = $groupNameMap[$subjectData['group_id']] ?? '';
                    $vKey = $groupName . '|' . $subjectData['subject_name'] . '|' . $subjectData['semester_code'];
                    $vedomost = $vedomostMap[$vKey] ?? null;

                    foreach ($subjectData['days'] as $dayTypeKey => $dayData) {
                        $totalDays++;
                        $dayAvg = 0;

                        if (!empty($dayData['grades'])) {
                            $dayAvg = round(array_sum($dayData['grades']) / count($dayData['grades']));
                        }

                        if ($dayData['absent']) {
                            $dayAvg = 0;
                        }

                        if ($dayAvg < $scoreLimit) {
                            $lowDayCount++;
                            $lowDays[] = [
                                'subject_name' => $subjectData['subject_name'],
                                'subject_id' => $subjectData['subject_id'],
                                'semester_code' => $subjectData['semester_code'],
                                'group_id' => $subjectData['group_id'],
                                'lesson_type' => $dayData['lesson_type'],
                                'date' => $dayData['date'],
                                'grade' => $dayAvg,
                                'absent' => $dayData['absent'],
                            ];
                        }
                    }
                }

                if ($lowDayCount > 0) {
                    // Kunlarni sana bo'yicha saralash
                    usort($lowDays, function ($a, $b) {
                        return strcmp($a['date'], $b['date']);
                    });

                    $studentResults[$hemisId] = [
                        'low_days' => $lowDays,
                        'low_day_count' => $lowDayCount,
                        'total_days' => $totalDays,
                    ];
                }
            }
            unset($allGrades);

            if (empty($studentResults)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            // Talaba ma'lumotlarini biriktirish
            $hemisIds = array_keys($studentResults);
            $studentInfo = DB::table('students')
                ->whereIn('hemis_id', $hemisIds)
                ->select('hemis_id', 'full_name', 'student_id_number', 'department_name',
                    'specialty_name', 'level_name', 'semester_name', 'group_name', 'group_id')
                ->get()
                ->keyBy('hemis_id');

            // Har bir kun alohida qator bo'lsin (flat format)
            $finalResults = [];
            foreach ($studentResults as $hemisId => $result) {
                $st = $studentInfo[$hemisId] ?? null;
                if (!$st) continue;

                foreach ($result['low_days'] as $day) {
                    $finalResults[] = [
                        'hemis_id' => $hemisId,
                        'full_name' => $st->full_name ?? 'Noma\'lum',
                        'student_id_number' => $st->student_id_number ?? '-',
                        'department_name' => $st->department_name ?? '-',
                        'specialty_name' => $st->specialty_name ?? '-',
                        'level_name' => $st->level_name ?? '-',
                        'semester_name' => $st->semester_name ?? '-',
                        'group_name' => $st->group_name ?? '-',
                        'group_id' => $groupDbIdMap[$st->group_id] ?? '',
                        'subject_name' => $day['subject_name'],
                        'subject_id' => $day['subject_id'],
                        'semester_code' => $day['semester_code'],
                        'grade' => $day['grade'],
                        'lesson_date' => \Carbon\Carbon::parse($day['date'])->format('d.m.Y'),
                        'lesson_date_raw' => $day['date'],
                        'lesson_type' => $day['lesson_type'],
                        'absent' => $day['absent'],
                    ];
                }
            }

            // Saralash
            $sortColumn = $request->get('sort', 'lesson_date');
            $sortDirection = $request->get('direction', 'desc');
            // lesson_date bo'yicha saralashda raw (yyyy-mm-dd) qiymatdan foydalanamiz
            $actualSortColumn = $sortColumn === 'lesson_date' ? 'lesson_date_raw' : $sortColumn;

            usort($finalResults, function ($a, $b) use ($actualSortColumn, $sortDirection) {
                $valA = $a[$actualSortColumn] ?? '';
                $valB = $b[$actualSortColumn] ?? '';
                $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            // Excel eksport
            if ($request->get('export') === 'summary') {
                return $this->exportTopStudentsSummaryExcel($finalResults, $scoreLimit);
            }
            if ($request->get('export') === 'full') {
                return $this->exportTopStudentsFullExcel($finalResults, $scoreLimit);
            }

            // Sahifalash
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
            \Log::error('Top students report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * 5 ga da'vogar - Excel (qisqacha)
     */
    private function exportTopStudentsSummaryExcel(array $data, int $scoreLimit)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('5 ga davogar');

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh',
            'Fan', 'Joriy bahosi', 'Dars sanasi'];
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

        $redFill = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
        ];

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValueExplicit([3, $row], $r['student_id_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([4, $row], $r['department_name']);
            $sheet->setCellValue([5, $row], $r['specialty_name']);
            $sheet->setCellValue([6, $row], $r['level_name']);
            $sheet->setCellValue([7, $row], $r['semester_name']);
            $sheet->setCellValue([8, $row], $r['group_name']);
            $sheet->setCellValue([9, $row], $r['subject_name']);
            $sheet->setCellValue([10, $row], $r['grade']);
            $sheet->setCellValue([11, $row], $r['lesson_date']);

            if ($r['grade'] < $scoreLimit) {
                $sheet->getStyle("J{$row}")->applyFromArray($redFill);
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 25, 12, 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:K{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = '5_ga_davogar_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'top_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * 5 ga da'vogar - Excel (to'liq)
     */
    private function exportTopStudentsFullExcel(array $data, int $scoreLimit)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('5 ga davogar toliq');

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh',
            'Fan', 'Dars turi', 'Joriy bahosi', 'Dars sanasi', 'Holat'];
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

        $redFill = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
        ];

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValueExplicit([3, $row], $r['student_id_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue([4, $row], $r['department_name']);
            $sheet->setCellValue([5, $row], $r['specialty_name']);
            $sheet->setCellValue([6, $row], $r['level_name']);
            $sheet->setCellValue([7, $row], $r['semester_name']);
            $sheet->setCellValue([8, $row], $r['group_name']);
            $sheet->setCellValue([9, $row], $r['subject_name']);
            $sheet->setCellValue([10, $row], $r['lesson_type']);
            $sheet->setCellValue([11, $row], $r['grade']);
            $sheet->setCellValue([12, $row], $r['lesson_date']);
            $sheet->setCellValue([13, $row], $r['absent'] ? 'Sababsiz' : ($r['grade'] < $scoreLimit ? "< {$scoreLimit}" : ''));

            if ($r['grade'] < $scoreLimit) {
                $sheet->getStyle("K{$row}")->applyFromArray($redFill);
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 25, 10, 12, 14, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:M{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = '5_ga_davogar_toliq_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'topf_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function usersWithoutRatings(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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
        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        return view('admin.reports.users-without-ratings', compact(
            'faculties', 'educationTypes', 'selectedEducationType', 'kafedras', 'dekanFacultyIds'
        ));
    }

    public function usersWithoutRatingsData(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Jadvallardan ma'lumot olish
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $gradeExcludedTypes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) < CURDATE()');

        if ($request->get('current_semester', '1') == '1') {
            $scheduleQuery->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            });
        }

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
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$request->date_from]);
        }

        if ($request->filled('date_to')) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$request->date_to]);
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
            return response()->json(['data' => [], 'total' => 0]);
        }

        // 2-QADAM: Baho mavjudligini tekshirish
        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $minDate = $schedules->min('lesson_date_str');
        $maxDate = $schedules->max('lesson_date_str');

        // Baho (1-usul): subject_schedule_id orqali
        $gradeByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->pluck('subject_schedule_id')
            ->unique()
            ->flip();

        // Baho (2-usul): atribut kaliti orqali
        $gradeByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.employee_id', $employeeIds)
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereRaw('DATE(sg.lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(sg.employee_id, '|', st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.training_type_code, '|', sg.lesson_pair_code) as gk"))
            ->pluck('gk')
            ->flip();

        // Dars ochilganlarni tekshirish
        $openingsByKey = DB::table('lesson_openings')
            ->where('status', 'active')
            ->where('deadline', '>', now())
            ->get()
            ->keyBy(function ($item) {
                return $item->group_hemis_id . '|' . $item->subject_id . '|' . $item->semester_code . '|' . \Carbon\Carbon::parse($item->lesson_date)->format('Y-m-d');
            });

        // 3-QADAM: Faqat baho qo'yilmaganlarni filtrlash
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $gradeKey = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $hasGrade = isset($gradeByScheduleId[$sch->schedule_hemis_id])
                || isset($gradeByKey[$gradeKey]);

            // Faqat baho qo'yilmaganlarni olamiz
            if ($hasGrade) {
                continue;
            }

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            // Dars ochilganmi tekshirish
            $openingKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code . '|' . $sch->lesson_date_str;
            $hasOpening = isset($openingsByKey[$openingKey]);

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'employee_id' => $sch->employee_id,
                    'employee_name' => $sch->employee_name,
                    'faculty_name' => $sch->faculty_name,
                    'specialty_name' => $sch->specialty_name,
                    'level_name' => $sch->level_name,
                    'semester_name' => $sch->semester_name,
                    'semester_code' => $sch->semester_code,
                    'department_name' => $sch->department_name,
                    'subject_name' => $sch->subject_name,
                    'subject_id' => $sch->subject_id,
                    'group_id' => $sch->group_id,
                    'group_db_id' => $sch->group_db_id,
                    'group_name' => $sch->group_name,
                    'training_type' => $sch->training_type_name,
                    'lesson_pair_time' => $pairTime,
                    'lesson_date' => $sch->lesson_date_str,
                    'has_opening' => $hasOpening,
                ];
            }
        }

        $results = array_values($grouped);

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
            return $this->exportUsersWithoutRatingsExcel($results);
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

    private function exportUsersWithoutRatingsExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Baho qo\'ymaganlar');

        $headers = ['#', 'O\'qituvchi FISH', 'Fakultet', 'Kafedra', 'Fan', 'Guruh', 'Mashg\'ulot turi', 'Juftlik vaqti', 'Dars sanasi', 'Dars ochilgan'];
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
            $sheet->setCellValue([2, $row], $r['employee_name']);
            $sheet->setCellValue([3, $row], $r['faculty_name']);
            $sheet->setCellValue([4, $row], $r['department_name']);
            $sheet->setCellValue([5, $row], $r['subject_name']);
            $sheet->setCellValue([6, $row], $r['group_name']);
            $sheet->setCellValue([7, $row], $r['training_type']);
            $sheet->setCellValue([8, $row], $r['lesson_pair_time']);
            $sheet->setCellValue([9, $row], $r['lesson_date']);
            $sheet->setCellValue([10, $row], $r['has_opening'] ? 'Ha' : 'Yo\'q');
        }

        $widths = [5, 30, 25, 25, 35, 15, 18, 14, 14, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow >= 2) {
            $sheet->getStyle("A2:J{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            ]);
        }

        $fileName = 'Baho_qoymaganlar_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'baho_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}

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
use App\Services\TelegramService;
use App\Models\Teacher;

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
        @ini_set('memory_limit', '1024M');
        @set_time_limit(180);
        try {
        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test", "Klinik mashg'ulot", "Klinik mashgulot"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

        // Sana oralig'i filtri
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        // JN (= JB) training turlari: Ma'ruza/MT/ON/OSKI/Test/Quiz kodlari chiqarib tashlanadi.
        // Jurnal bilan bir xil: whereNotIn('training_type_code', [11, 99, 100, 101, 102, 103]).
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // 1-QADAM: Barcha schedule yozuvlarini olish (pairs_per_day hisoblash uchun)
        $scheduleQuery = DB::table('schedules as sch')
            ->whereNull('sch.deleted_at')
            ->whereNotIn('sch.training_type_name', $excludedNames)
            ->whereNotIn('sch.training_type_code', $excludedTrainingCodes)
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
                $scheduleQuery->where('sch.education_year_current', true);
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
            // group param — Group.id, convert to group_hemis_id
            $groupHemisId = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
            if ($groupHemisId) {
                $scheduleQuery->where('sch.group_id', $groupHemisId);
            }
        }

        // Sana oralig'i bo'yicha dars jadvalini filtrlash
        if ($dateFrom) {
            $scheduleQuery->where('sch.lesson_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $scheduleQuery->where('sch.lesson_date', '<=', $dateTo);
        }

        // Schedule ma'lumotlarini tayyorlash: columns, minDates (memory-efficient cursor)
        $columns = [];    // [combo_key][date_pair] = true
        $minDates = [];   // [combo_key] = eng kichik sana
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
            $groupHemisIdForStudents = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
            if ($groupHemisIdForStudents) {
                $studentQuery->where('s.group_id', $groupHemisIdForStudents);
            }
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

        // 3-QADAM: student_grades dan baholarni olish (lesson_pair_code bilan).
        // ON/OSKI/Test/Quiz kodlari (100, 101, 102, 103) chiqarib tashlanadi — jurnaldagi JB filtriga mos.
        // Ma'ruza (11) va MT (99) baho sifatida kelishi mumkin, ammo ular keyinroq
        // schedule'dagi (sana+juftlik) to'plamiga mos kelmagani uchun avtomatik chetlashtiriladi.
        $gradesQuery = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
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

        // 4-QADAM: Jurnal formulasi bo'yicha hisoblash.
        // Jurnal yondashuvi: columns — faqat schedule'dan. Baholar schedule'dagi
        // (sana+juftlik) to'plamiga mos kelsa hisobga olinadi (JournalController.php:635).
        $cutoffDate = $dateTo ?? Carbon::now('Asia/Tashkent')->format('Y-m-d');

        // pairs_per_day va lesson_dates ni schedule columns dan hisoblash
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

        $gradesByDay = [];      // [student|subject|date] => [pair_code => grade]
        $studentSubjects = [];  // [student|subject] => info

        foreach ($gradesQuery->cursor() as $g) {
            $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
            if (!$groupId) continue;

            $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
            $minDate = $minDates[$comboKey] ?? null;
            if (!$minDate) continue;

            $dateKey = substr($g->lesson_date, 0, 10);
            if ($dateKey < $minDate) continue;

            // Baho faqat schedule'dagi (sana+juftlik) juftligiga mos kelsa qabul qilinadi.
            // Bu jurnal bilan bir xil xulq-atvor — MT/Ma'ruza/boshqa training type grade'lari
            // JN to'plamiga kirmaydi, noma'lum juftlik kodlari ham e'tiborga olinmaydi.
            $datePairKey = $dateKey . '_' . $g->lesson_pair_code;
            if (!isset($columns[$comboKey][$datePairKey])) {
                continue;
            }

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
        } catch (\Throwable $e) {
            \Log::error('JN Report error: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
            return response()->json(['message' => $e->getMessage()], 500);
        }
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
        $date = $request->get('date', now()->format('Y-m-d'));
        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test", "Klinik mashg'ulot", "Klinik mashgulot"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

        // 1. Barcha schedulelar (shu sanadagi)
        $schedules = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotIn('sch.training_type_name', $gradeExcludedNames)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->where(function ($q) {
                $q->where('sem.current', true)
                  ->orWhereNull('sem.id');
            })
            ->whereRaw('DATE(sch.lesson_date) = ?', [$date]);

        foreach ($excludedSubjectPatterns as $pattern) {
            $schedules->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        $schedules = $schedules->select(
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

        // 3. student_grades da bor schedule IDlar (baho, retake, yoki NB — barchasi hisobga olinadi)
        $gradeRecords = DB::table('student_grades')
            ->whereIn('subject_schedule_id', $scheduleIds)
            ->whereNull('deleted_at')
            ->select('subject_schedule_id')
            ->distinct()
            ->pluck('subject_schedule_id')
            ->flip();

        // 4. Natijalarni tayyorlash
        $rows = [];
        foreach ($schedules as $sch) {
            $hasAC = isset($acRecords[$sch->schedule_hemis_id]);
            $acLoad = $hasAC ? $acRecords[$sch->schedule_hemis_id]->load : null;
            // Faqat amaliy mashg'ulotlar uchun baho tekshiriladi
            $skipGradeCheck = in_array($sch->training_type_name, $gradeExcludedNames);
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
        if ($request->get('export') === 'excel') {
            return $this->startLessonAssignmentExport($request);
        }

        $calcKey = 'report_calc_' . auth()->id();

        $existing = \Illuminate\Support\Facades\Cache::get($calcKey);
        if ($existing && ($existing['status'] ?? '') === 'running') {
            return response()->json(['queued' => true, 'message' => 'Allaqachon hisoblanmoqda.']);
        }

        $filters = $request->all();
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && empty($filters['faculty'])) {
            $filters['dekan_faculty_ids'] = $dekanFacultyIds;
        }

        \Illuminate\Support\Facades\Cache::put($calcKey, [
            'status' => 'running',
            'message' => 'Hisoblanmoqda...',
        ], 600);

        \App\Jobs\CalculateLessonAssignmentJob::dispatch($filters, $calcKey);

        return response()->json(['queued' => true, 'message' => 'Hisoblash boshlandi.']);
    }

    public function lessonAssignmentCalcStatus()
    {
        $calcKey = 'report_calc_' . auth()->id();
        $data = \Illuminate\Support\Facades\Cache::get($calcKey);

        if (!$data) {
            return response()->json(['status' => 'none']);
        }

        if (($data['status'] ?? '') === 'done') {
            return response()->json(['status' => 'done']);
        }

        return response()->json($data);
    }

    public function lessonAssignmentCalcResults(Request $request)
    {
        $calcKey = 'report_calc_' . auth()->id();
        $data = \Illuminate\Support\Facades\Cache::get($calcKey);

        if (!$data || ($data['status'] ?? '') !== 'done') {
            return response()->json(['data' => [], 'total' => 0]);
        }

        $results = $data['results'] ?? [];

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

        $sortColumn = $request->get('sort', 'lesson_date');
        $sortDirection = $request->get('direction', 'desc');
        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            $cmp = is_numeric($valA) ? ($valA <=> $valB) : strcasecmp($valA, $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

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

    private function lessonAssignmentDataInner(Request $request)
    {
        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test", "Klinik mashg'ulot", "Klinik mashgulot"];
        $excludedSubjectPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];

        // 1-QADAM: Jadvallardan ma'lumot olish
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->where('sch.training_type_code', '!=', 11)
            ->whereRaw("sch.subject_name NOT LIKE '%amaliyoti'")
            ->whereNotIn('sch.training_type_name', $gradeExcludedNames)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        foreach ($excludedSubjectPatterns as $pattern) {
            $scheduleQuery->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

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
            ->whereIn('subject_id', $subjectIds)
            ->whereRaw('DATE(lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')
            ->flip();

        // Baho (1-usul): subject_schedule_id bo'yicha yozuvi bor talabalar soni
        // Baho HAM, NB HAM hisobga olinadi — o'qituvchi shu talaba uchun ish qilgan
        // Faqat hech qanday yozuv yo'q talaba = "baho qo'yilmagan"
        $processedCountByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->select('subject_schedule_id', DB::raw('COUNT(DISTINCT student_hemis_id) as cnt'))
            ->groupBy('subject_schedule_id')
            ->pluck('cnt', 'subject_schedule_id');

        // Baho (2-usul): group + subject + date + lesson_pair bo'yicha yozuvi bor talabalar soni
        $processedCountByKey = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereIn('sg.subject_id', $subjectIds)
            ->whereRaw('DATE(sg.lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->whereNotIn('sg.training_type_code', [100, 101, 102, 103])
            ->select(DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.lesson_pair_code) as gk"), DB::raw('COUNT(DISTINCT sg.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', sg.subject_id, '|', DATE(sg.lesson_date), '|', sg.lesson_pair_code)"))
            ->pluck('cnt', 'gk');

        // 3-QADAM: Fanga biriktirilgan faol talabalar sonini hisoblash
        // student_subjects jadvalidan — HEMIS da fanga biriktirilgan talabalarni olish
        // Chetlashtirilgan talabalar (student_status_code != 11) hisobga olinmaydi
        // Joriy semestrga tegishli biriktirishlarni aniqlash (guruh+fan+semestr kaliti bilan)
        $groupIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $semesterCodes = $schedules->pluck('semester_code')->unique()->values()->toArray();

        $subjectStudentCounts = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->whereIn('st.group_id', $groupIds)
            ->whereIn('ss.subject_id', $subjectIds)
            ->whereIn('ss.semester_id', $semesterCodes)
            ->where('st.student_status_code', 11) // Faqat faol talabalar
            ->select(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id) as gs_key"), DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id)"))
            ->pluck('cnt', 'gs_key');

        // Zaxira: agar student_subjects da ma'lumot bo'lmasa, guruh bo'yicha hisoblash
        $groupStudentCounts = DB::table('students')
            ->whereIn('group_id', $groupIds)
            ->where('student_status_code', 11)
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
            $gradeKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                      . '|' . $sch->lesson_pair_code;

            // Davomat: schedule_hemis_id orqali yoki atribut kaliti orqali tekshirish
            $hasAtt = isset($attendanceByScheduleId[$sch->schedule_hemis_id])
                   || isset($attendanceByKey[$attKey]);

            // Baho: faqat amaliy mashg'ulotlar uchun tekshiriladi
            $skipGradeCheck = in_array($sch->training_type_name, $gradeExcludedNames);
            // Fanga biriktirilgan talabalar soni (student_subjects — semestr bo'yicha), topilmasa guruh soni
            $gsKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code;
            $totalStudents = $subjectStudentCounts[$gsKey] ?? ($groupStudentCounts[$sch->group_id] ?? 0);

            if ($skipGradeCheck) {
                $hasGrade = null;
                $missingGradeCount = 0;
            } else {
                // Yozuvi bor talabalar soni (baho HAM, NB HAM — barchasi "o'qituvchi ishini bajargan")
                // 1-usul: schedule_hemis_id orqali
                $processedBySchedule = $processedCountByScheduleId[$sch->schedule_hemis_id] ?? 0;
                // 2-usul: kalit orqali
                $processedByComposite = $processedCountByKey[$gradeKey] ?? 0;
                // Eng ko'p topilgan usulni tanlash
                $processedCount = max($processedBySchedule, $processedByComposite);

                if ($processedCount >= $totalStudents) {
                    // Barcha faol talabalar uchun yozuv bor (baho yoki NB)
                    $hasGrade = true;
                    $missingGradeCount = 0;
                } elseif ($processedCount > 0) {
                    // Ba'zi talabalar uchun yozuv yo'q
                    $hasGrade = false;
                    $missingGradeCount = $totalStudents - $processedCount;
                } else {
                    // Hech qanday yozuv yo'q — o'qituvchi umuman ishlamagan
                    $hasGrade = false;
                    $missingGradeCount = $totalStudents;
                }
            }

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
                    'student_count' => $totalStudents,
                    'has_attendance' => $hasAtt,
                    'has_grades' => $hasGrade,
                    'missing_grade_count' => $missingGradeCount,
                ];
            } else {
                // Dublikat schedule (bir xil kalit, boshqa schedule_hemis_id) —
                // agar biror dublikatda davomat/baho topilsa, eng yaxshi natijani olish
                if ($hasAtt && !$grouped[$key]['has_attendance']) {
                    $grouped[$key]['has_attendance'] = true;
                }
                if ($hasGrade === true && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['has_grades'] = true;
                    $grouped[$key]['missing_grade_count'] = 0;
                } elseif ($hasGrade === false && $grouped[$key]['has_grades'] === false) {
                    // Eng kam missing sonini olish (eng ko'p baho topilgan usul)
                    $grouped[$key]['missing_grade_count'] = min($grouped[$key]['missing_grade_count'], $missingGradeCount);
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
        $temp = tempnam(sys_get_temp_dir(), 'la_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Excel eksportni background job sifatida boshlash
     */
    private function startLessonAssignmentExport(Request $request)
    {
        $exportKey = 'lesson_assignment_export_' . auth()->id() . '_' . md5(json_encode($request->all()));

        // Agar allaqachon ishlab turgan export bo'lsa, uning statusini qaytarish
        $existing = \Illuminate\Support\Facades\Cache::get($exportKey);
        if ($existing && $existing['status'] === 'running') {
            return response()->json([
                'export_key' => $exportKey,
                'status' => 'running',
                'message' => $existing['message'] ?? 'Ishlanmoqda...',
                'percent' => $existing['percent'] ?? 0,
            ]);
        }

        $filters = $request->only([
            'education_type', 'faculty', 'specialty', 'level_code', 'semester_code',
            'department', 'subject', 'group', 'date_from', 'date_to',
            'current_semester', 'status_filter', 'sort', 'direction',
        ]);

        // Dekan uchun fakultet majburiy filtr
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && empty($filters['faculty'])) {
            $filters['faculty'] = $dekanFacultyIds[0];
        }

        \Illuminate\Support\Facades\Cache::put($exportKey, [
            'status' => 'running',
            'message' => 'Navbatga qo\'shilmoqda...',
            'percent' => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);

        \App\Jobs\ExportLessonAssignmentJob::dispatch($filters, $exportKey);

        return response()->json([
            'export_key' => $exportKey,
            'status' => 'running',
            'message' => 'Eksport boshlandi',
        ]);
    }

    /**
     * Excel eksport statusini tekshirish
     */
    public function lessonAssignmentExportStatus(Request $request)
    {
        $exportKey = $request->get('export_key');
        if (!$exportKey) {
            return response()->json(['status' => 'error', 'message' => 'Export key topilmadi'], 400);
        }

        $data = \Illuminate\Support\Facades\Cache::get($exportKey);
        if (!$data) {
            return response()->json(['status' => 'error', 'message' => 'Eksport topilmadi yoki muddati tugagan']);
        }

        return response()->json($data);
    }

    /**
     * Tayyor Excel faylni yuklab olish
     */
    public function lessonAssignmentExportDownload(Request $request)
    {
        $exportKey = $request->get('export_key');
        if (!$exportKey) {
            return response()->json(['error' => 'Export key topilmadi'], 400);
        }

        $data = \Illuminate\Support\Facades\Cache::get($exportKey);
        if (!$data || $data['status'] !== 'done') {
            return response()->json(['error' => 'Fayl topilmadi yoki hali tayyor emas'], 404);
        }

        // Yangi usul: cache dan kontentni olish
        if (!empty($data['file_content'])) {
            $content = base64_decode($data['file_content']);
            $fileName = $data['file_name'] ?? 'Dars_belgilash.xlsx';

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => strlen($content),
            ]);
        }

        // Eski usul (orqaga moslik): disk dan faylni olish
        $filePath = $data['file_path'] ?? '';
        if (!$filePath || !file_exists($filePath)) {
            return response()->json(['error' => 'Fayl serverda topilmadi'], 404);
        }

        return response()->download($filePath, basename($filePath), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Dars soati belgilash hisoboti sahifasi.
     * Jadvalga qo'yilgan soat bilan o'qituvchi HEMIS da belgilagan soat taqqoslanadi.
     */
    public function lessonHours(Request $request)
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

        return view('admin.reports.lesson-hours', compact(
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'dekanFacultyIds'
        ));
    }

    /**
     * Dars soati belgilash AJAX ma'lumot endpointi.
     * Har bir dars juftligi uchun jadval soati (doimo 2 akademik soat) va
     * o'qituvchi HEMIS da belgilagan soat (attendance_controls.load)ni qaytaradi.
     */
    public function lessonHoursData(Request $request)
    {
        try {
            return $this->lessonHoursDataInner($request);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('[lessonHoursData] ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'params' => $request->all(),
            ]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
                'file' => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    private function lessonHoursDataInner(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);

        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

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
            return response()->json([
                'data' => [],
                'total' => 0,
                'summary' => ['scheduled_total' => 0, 'hemis_total' => 0, 'diff_total' => 0],
            ]);
        }

        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $minDate = $schedules->min('lesson_date_str');
        $maxDate = $schedules->max('lesson_date_str');

        // HEMIS da o'qituvchi belgilagan soat — subject_schedule_id orqali
        $loadByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->select('subject_schedule_id', DB::raw('MAX(`load`) as load_hours'))
            ->groupBy('subject_schedule_id')
            ->pluck('load_hours', 'subject_schedule_id');

        // Atribut kaliti orqali (zaxira — subject_schedule_id bog'lanmagan yozuvlar uchun)
        $loadByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereRaw('DATE(lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->where('load', '>', 0)
            ->select(
                DB::raw("CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"),
                DB::raw('MAX(`load`) as load_hours')
            )
            ->groupBy(DB::raw("CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code)"))
            ->pluck('load_hours', 'ck');

        // Juftlik davomidan akademik soatni hisoblash.
        // HEMIS da faqat 1 soatlik (≈40 daq) yoki 2 soatlik (≈80 daq) darslar bo'ladi.
        $pairHours = function ($start, $end): int {
            if (!$start || !$end) return 2;
            $startTs = strtotime((string) $start);
            $endTs = strtotime((string) $end);
            if ($startTs === false || $endTs === false) return 2;
            $minutes = ($endTs - $startTs) / 60;
            if ($minutes <= 0) return 2;
            // ≤ 60 daq → 1 akademik soat, aks holda 2 akademik soat.
            return $minutes <= 60 ? 1 : 2;
        };

        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            $attKey = $key;
            $loadBySch = (int) ($loadByScheduleId[$sch->schedule_hemis_id] ?? 0);
            $loadByK = (int) ($loadByKey[$attKey] ?? 0);
            $hemisHours = max($loadBySch, $loadByK);

            $scheduledHours = $pairHours($sch->lesson_pair_start_time, $sch->lesson_pair_end_time);

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
                    'subject_id' => $sch->subject_id,
                    'subject_name' => $sch->subject_name,
                    'group_id' => $sch->group_id,
                    'group_db_id' => $sch->group_db_id,
                    'group_name' => $sch->group_name,
                    'training_type' => $sch->training_type_name,
                    'lesson_pair_time' => $pairTime,
                    'lesson_date' => $sch->lesson_date_str,
                    'scheduled_hours' => $scheduledHours,
                    'hemis_hours' => $hemisHours,
                    'hours_diff' => $scheduledHours - $hemisHours,
                    'hours_match' => $hemisHours === $scheduledHours,
                ];
            } elseif ($hemisHours > $grouped[$key]['hemis_hours']) {
                $grouped[$key]['hemis_hours'] = $hemisHours;
                $grouped[$key]['hours_diff'] = $grouped[$key]['scheduled_hours'] - $hemisHours;
                $grouped[$key]['hours_match'] = $hemisHours === $grouped[$key]['scheduled_hours'];
            }
        }

        $results = array_values($grouped);

        // Status filtri: barchasi | only mismatches | only matches | not marked
        if ($request->filled('status_filter')) {
            $results = array_values(array_filter($results, function ($r) use ($request) {
                return match ($request->status_filter) {
                    'mismatch' => !$r['hours_match'],
                    'match' => $r['hours_match'],
                    'not_marked' => $r['hemis_hours'] === 0,
                    'partial' => $r['hemis_hours'] > 0 && $r['hemis_hours'] < $r['scheduled_hours'],
                    'over_marked' => $r['hemis_hours'] > $r['scheduled_hours'],
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

        // Xulosa jamlari (filtrlangan natijalar bo'yicha)
        $summary = [
            'scheduled_total' => array_sum(array_column($results, 'scheduled_hours')),
            'hemis_total' => array_sum(array_column($results, 'hemis_hours')),
        ];
        $summary['diff_total'] = $summary['scheduled_total'] - $summary['hemis_total'];

        // Pagination
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
            'summary' => $summary,
        ]);
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
            ->where('g.active', true)
            ->where(function ($q) {
                $q->where('cs.is_active', true)->orWhereNull('cs.is_active');
            });

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
            // Yo'nalish nomi bo'yicha filtrlanadi (bir xil nom ostida bir nechta specialty_hemis_id bo'lishi mumkin).
            // Eski formatlar (hemis_id) uchun ham moslashadi.
            $val = $request->specialty;
            if (is_numeric($val)) {
                $specialtyName = \App\Models\Specialty::where('specialty_hemis_id', $val)->value('name');
                $csQuery->where('g.specialty_name', $specialtyName ?: $val);
            } else {
                $csQuery->where('g.specialty_name', $val);
            }
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
            $csQuery->where('g.id', $request->group);
        }

        $curriculumSubjects = $csQuery->select(
            'cs.id as cs_id',
            'cs.subject_id',
            'cs.subject_name',
            'cs.semester_code',
            'cs.subject_details',
            'g.id as group_id',
            'g.group_hemis_id',
            'g.name as group_name',
            'f.name as faculty_name',
            'g.specialty_name',
            's.level_name',
            's.name as semester_name',
            's.semester_hemis_id'
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

        // KTR rejalaridagi soatlar (barcha haftalar yig'indisi tt_code bo'yicha)
        $ktrMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('ktr_plans')) {
            $csIds = $curriculumSubjects->pluck('cs_id')->unique()->toArray();
            $ktrPlans = DB::table('ktr_plans')
                ->whereIn('curriculum_subject_id', $csIds)
                ->select('curriculum_subject_id', 'plan_data')
                ->get();

            foreach ($ktrPlans as $plan) {
                $planData = is_string($plan->plan_data) ? json_decode($plan->plan_data, true) : $plan->plan_data;
                if (!is_array($planData)) continue;
                $hoursData = $planData['hours'] ?? $planData;
                if (!is_array($hoursData)) continue;

                $byCode = [];
                foreach ($hoursData as $weekData) {
                    if (!is_array($weekData)) continue;
                    foreach ($weekData as $code => $hours) {
                        $byCode[(string) $code] = ($byCode[(string) $code] ?? 0) + (int) $hours;
                    }
                }
                $ktrMap[$plan->curriculum_subject_id] = $byCode;
            }
        }

        // 3-QADAM: Har bir fan+guruh uchun dars turlari bo'yicha yig'indini hisoblash
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

            $ktrByCode = $ktrMap[$cs->cs_id] ?? null;
            $ktrExists = $ktrByCode !== null;

            $totalPlanned = 0;
            $totalScheduled = 0;
            $totalKtr = 0;
            $hasAnyType = false;

            foreach ($details as $detail) {
                $trainingTypeCode = (string) ($detail['trainingType']['code'] ?? '');
                $plannedHours = (int) ($detail['academic_load'] ?? 0);
                if ($trainingTypeCode === '') {
                    continue;
                }
                if (!empty($trainingTypeFilter) && !in_array($trainingTypeCode, $trainingTypeFilter)) {
                    continue;
                }
                $hasAnyType = true;

                $schedKey = $cs->group_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code . '|' . $trainingTypeCode;
                $totalPlanned += $plannedHours;
                $totalScheduled += (int) ($scheduleMap[$schedKey] ?? 0);
                if ($ktrExists) {
                    $totalKtr += (int) ($ktrByCode[$trainingTypeCode] ?? 0);
                }
            }

            if (!$hasAnyType) {
                continue;
            }

            $farq = $totalPlanned - $totalScheduled;
            $ktrFarq = $ktrExists ? ($totalKtr - $totalScheduled) : null;

            $results[] = [
                'cs_id' => (int) $cs->cs_id,
                'group_id' => (int) ($cs->group_id ?? 0),
                'faculty_name' => $cs->faculty_name ?? '-',
                'specialty_name' => $cs->specialty_name ?? '-',
                'level_name' => $cs->level_name ?? '-',
                'semester_name' => $cs->semester_name ?? '-',
                'subject_name' => $cs->subject_name ?? '-',
                'group_name' => $cs->group_name ?? '-',
                'planned_hours' => $totalPlanned,
                'scheduled_hours' => $totalScheduled,
                'ktr_hours' => $ktrExists ? $totalKtr : null,
                'ktr_exists' => $ktrExists,
                'farq' => $farq,
                'ktr_farq' => $ktrFarq,
            ];
        }

        if (empty($results)) {
            return response()->json(['data' => [], 'total' => 0, 'column_values' => []]);
        }

        // Saralash (standart: farq bo'yicha kamayish tartibida)
        $sortColumn = $request->get('sort', 'farq');
        $sortDirection = $request->get('direction', 'desc');

        usort($results, function ($a, $b) use ($sortColumn, $sortDirection) {
            $valA = $a[$sortColumn] ?? '';
            $valB = $b[$sortColumn] ?? '';
            if ($valA === null) $valA = PHP_INT_MIN;
            if ($valB === null) $valB = PHP_INT_MIN;
            $cmp = is_numeric($valA) && is_numeric($valB) ? ($valA <=> $valB) : strcasecmp((string) $valA, (string) $valB);
            return $sortDirection === 'desc' ? -$cmp : $cmp;
        });

        // Filtrlanadigan ustunlar va ulardagi mavjud (noyob) qiymatlar (filtrgacha)
        $filterableCols = ['faculty_name', 'specialty_name', 'level_name', 'semester_name', 'subject_name', 'group_name', 'planned_hours', 'scheduled_hours', 'ktr_hours', 'farq', 'ktr_farq'];
        $columnValues = [];
        foreach ($filterableCols as $col) {
            $vals = [];
            foreach ($results as $row) {
                $v = $row[$col] ?? null;
                if ($v === null) $v = '';
                $vals[(string) $v] = true;
            }
            $columnValues[$col] = array_keys($vals);
        }

        // Ustun filtrlari (frontend dropdown'larda tanlangan qiymatlar)
        $colFilters = $request->input('col_filters', []);
        if (is_array($colFilters) && !empty($colFilters)) {
            $results = array_values(array_filter($results, function ($row) use ($colFilters) {
                foreach ($colFilters as $col => $allowed) {
                    if (!is_array($allowed) || empty($allowed)) {
                        return false; // bo'sh ro'yxat - hech qanday qator ko'rinmasin
                    }
                    $v = $row[$col] ?? '';
                    if ($v === null) $v = '';
                    if (!in_array((string) $v, $allowed, true)) {
                        return false;
                    }
                }
                return true;
            }));
        }

        // Excel export (umumiy) - yig'ilgan ma'lumot asosida
        if ($request->get('export') === 'excel') {
            return $this->exportScheduleReportSummaryExcel($results);
        }
        // Excel export (batafsil) - faqat filtrlangan (cs_id) lar uchun
        if ($request->get('export') === 'excel_lessons') {
            $allowedCsIds = array_flip(array_map(fn($r) => (int) $r['cs_id'], $results));
            $filteredCs = $curriculumSubjects->filter(fn($cs) => isset($allowedCsIds[(int) $cs->cs_id]));
            return $this->exportScheduleReportLessonsExcel($filteredCs, $request);
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
            'column_values' => $columnValues,
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
     * AJAX: Bitta fan+guruh uchun haftalik HEMIS vs KTR soatlarini qaytarish.
     * Modalda ko'rsatiladigan batafsil ma'lumot.
     */
    public function scheduleKtrCompareDetail(Request $request, $csId)
    {
        try {
            $cs = DB::table('curriculum_subjects as cs')
                ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
                ->leftJoin('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                ->join('semesters as s', function ($join) {
                    $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->where('cs.id', $csId);

            if ($request->filled('group')) {
                $cs->where('g.id', $request->group);
            }

            $cs = $cs->select(
                'cs.id as cs_id',
                'cs.subject_id',
                'cs.subject_name',
                'cs.semester_code',
                'cs.subject_details',
                'g.group_hemis_id',
                'g.name as group_name',
                's.semester_hemis_id'
            )->first();

            if (!$cs) {
                return response()->json(['error' => 'Fan topilmadi'], 404);
            }

            // Mustaqil ta'lim turlarini aniqlovchi yordamchi
            $isMustaqil = function ($name) {
                $normalized = preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower((string) $name));
                return str_contains($normalized, 'mustaqil');
            };

            // Fan dars turlari (subject_details dan) - mustaqil ta'limdan tashqari
            $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
            $trainingTypes = [];
            if (is_array($details)) {
                foreach ($details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $name = $d['trainingType']['name'] ?? '';
                    if ($code === '' || $isMustaqil($name)) {
                        continue;
                    }
                    $trainingTypes[$code] = [
                        'name' => $name,
                        'planned_hours' => (int) ($d['academic_load'] ?? 0),
                    ];
                }
            }

            // Semestr haftalarini olish va ketma-ket indeks xaritasini yaratish
            $weekIndexMap = [];
            $weekStartByIdx = []; // weekIdx => 'YYYY-MM-DD' (hafta boshlanish sanasi)
            $weekRanges = []; // [{idx, start, end}] - sanaga qarab hafta topish uchun
            if ($cs->semester_hemis_id) {
                $weeks = DB::table('curriculum_weeks')
                    ->where('semester_hemis_id', $cs->semester_hemis_id)
                    ->orderBy('start_date')
                    ->select('curriculum_week_hemis_id', 'start_date', 'end_date')
                    ->get();
                foreach ($weeks->values() as $i => $w) {
                    $idx = $i + 1;
                    $weekIndexMap[(string) $w->curriculum_week_hemis_id] = $idx;
                    $weekStartByIdx[$idx] = substr((string) $w->start_date, 0, 10);
                    $weekRanges[] = [
                        'idx' => $idx,
                        'start' => substr((string) $w->start_date, 0, 10),
                        'end' => substr((string) $w->end_date, 0, 10),
                    ];
                }
            }

            // Yordamchi: sana berilsa, shu sanaga mos hafta indeksini topadi (yoki sintetik yaratadi)
            $resolveWeek = function ($lessonDate, $fallbackWeekNumber) use ($weekIndexMap, $weekRanges, &$weekStartByIdx) {
                $idx = $weekIndexMap[(string) $fallbackWeekNumber] ?? null;
                if ($idx !== null) return $idx;
                $d = substr((string) $lessonDate, 0, 10);
                if ($d === '') return null;
                foreach ($weekRanges as $r) {
                    if ($d >= $r['start'] && $d <= $r['end']) return $r['idx'];
                }
                // Shu sana uchun sintetik indeks allaqachon bormi?
                foreach ($weekStartByIdx as $wIdx => $startDate) {
                    if ($startDate === $d) return $wIdx;
                }
                // Yangi sintetik hafta
                $maxIdx = empty($weekStartByIdx) ? 0 : max(array_keys($weekStartByIdx));
                $newIdx = $maxIdx + 1;
                $weekStartByIdx[$newIdx] = $d;
                return $newIdx;
            };

            // HEMIS jadvaldan dars soatlarini hafta+dars_turi bo'yicha yig'ish
            $scheduleQuery = DB::table('schedules as sch')
                ->where('sch.subject_id', $cs->subject_id)
                ->where('sch.semester_code', $cs->semester_code)
                ->whereNotNull('sch.lesson_date')
                ->whereNull('sch.deleted_at');

            if ($cs->group_hemis_id) {
                $scheduleQuery->where('sch.group_id', $cs->group_hemis_id);
            }
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
                    'sch.schedule_hemis_id',
                    'sch.training_type_code',
                    'sch.training_type_name',
                    'sch.week_number',
                    'sch.lesson_date',
                    'sch.lesson_pair_start_time',
                    'sch.lesson_pair_end_time'
                )
                ->get();

            // O'qituvchi belgilagan soatlar (attendance_controls.load) - subject_schedule_id bo'yicha
            $markedByScheduleId = [];
            $acRows = collect();
            if (\Illuminate\Support\Facades\Schema::hasTable('attendance_controls')) {
                $scheduleHemisIds = $scheduleRows->pluck('schedule_hemis_id')->filter()->unique()->toArray();
                if (!empty($scheduleHemisIds)) {
                    $markedByScheduleId = DB::table('attendance_controls')
                        ->whereNull('deleted_at')
                        ->whereIn('subject_schedule_id', $scheduleHemisIds)
                        ->select('subject_schedule_id', DB::raw('SUM(`load`) as marked_load'))
                        ->groupBy('subject_schedule_id')
                        ->pluck('marked_load', 'subject_schedule_id')
                        ->toArray();
                }

                // attendance_controls dan ALOHIDA darslar ham chiqishi kerak
                // (agar schedules da yo'q bo'lsa: o'chirilgan yoki sinxron qilinmagan)
                // Jurnal kabi deleted_at filtri qo'yilmaydi
                $acQuery = DB::table('attendance_controls')
                    ->where('subject_id', $cs->subject_id)
                    ->where('semester_code', $cs->semester_code)
                    ->whereNotNull('lesson_date');
                if ($cs->group_hemis_id) {
                    $acQuery->where('group_id', $cs->group_hemis_id);
                }
                if ($request->filled('date_from')) {
                    $acQuery->whereRaw('DATE(lesson_date) >= ?', [$request->date_from]);
                }
                if ($request->filled('date_to')) {
                    $acQuery->whereRaw('DATE(lesson_date) <= ?', [$request->date_to]);
                }
                $acRows = $acQuery
                    ->select(
                        'training_type_code',
                        'training_type_name',
                        'lesson_date',
                        'lesson_pair_start_time',
                        'lesson_pair_end_time',
                        'load'
                    )
                    ->get();
            }

            // hemisWeeks[weekIdx][tt_code] = hours (haftalik yig'indi)
            // hemisLessonsRaw[tt_code] = [{week, date, start, hours}, ...] - bir kundagi soatlar jamlanadi
            $hemisWeeks = [];
            $hemisLessonsRaw = [];
            $dayAcc = []; // code|YYYY-MM-DD => index in hemisLessonsRaw[code]
            foreach ($scheduleRows as $row) {
                $code = (string) $row->training_type_code;
                if ($isMustaqil($row->training_type_name ?? $code)) {
                    continue;
                }
                $weekIdx = $resolveWeek($row->lesson_date, $row->week_number);
                if ($weekIdx === null) {
                    continue;
                }
                $start = strtotime($row->lesson_pair_start_time);
                $end = strtotime($row->lesson_pair_end_time);
                $hours = max(1, round((($end - $start) / 60) / 40));
                $hemisWeeks[$weekIdx][$code] = ($hemisWeeks[$weekIdx][$code] ?? 0) + $hours;
                $marked = (int) ($markedByScheduleId[$row->schedule_hemis_id] ?? 0);

                $dateStr = '';
                if (!empty($row->lesson_date)) {
                    $dateStr = substr((string) $row->lesson_date, 0, 10);
                }
                $dayKey = $code . '|' . $dateStr;

                if (isset($dayAcc[$dayKey])) {
                    $idx = $dayAcc[$dayKey];
                    $hemisLessonsRaw[$code][$idx]['hours'] += $hours;
                    $hemisLessonsRaw[$code][$idx]['marked'] += $marked;
                    if (strcmp($row->lesson_pair_start_time, $hemisLessonsRaw[$code][$idx]['start']) < 0) {
                        $hemisLessonsRaw[$code][$idx]['start'] = $row->lesson_pair_start_time;
                    }
                } else {
                    $hemisLessonsRaw[$code][] = [
                        'week' => $weekIdx,
                        'date' => $row->lesson_date,
                        'start' => $row->lesson_pair_start_time,
                        'hours' => $hours,
                        'marked' => $marked,
                    ];
                    $dayAcc[$dayKey] = count($hemisLessonsRaw[$code]) - 1;
                }

                if (!isset($trainingTypes[$code])) {
                    $trainingTypes[$code] = [
                        'name' => $row->training_type_name ?? $code,
                        'planned_hours' => 0,
                    ];
                }
            }

            // attendance_controls dan qo'shimcha darslarni qo'shish (schedules da yo'q bo'lsa)
            foreach ($acRows as $ac) {
                $code = (string) $ac->training_type_code;
                if ($isMustaqil($ac->training_type_name ?? $code)) {
                    continue;
                }
                $dateStr = substr((string) $ac->lesson_date, 0, 10);
                if (!$dateStr) continue;

                $dayKey = $code . '|' . $dateStr;
                if (isset($dayAcc[$dayKey])) {
                    // Schedule'da bor - AC dan faqat marked qiymatini yangilaymiz (agar hali belgilanmagan bo'lsa)
                    $idx = $dayAcc[$dayKey];
                    if (($hemisLessonsRaw[$code][$idx]['marked'] ?? 0) === 0) {
                        $hemisLessonsRaw[$code][$idx]['marked'] = (int) $ac->load;
                    }
                    continue;
                }
                // Schedule'da yo'q - AC dan yangi dars sifatida qo'shamiz
                $weekIdx = $resolveWeek($ac->lesson_date, null);
                if ($weekIdx === null) continue;
                $hours = max(1, (int) $ac->load);

                $hemisLessonsRaw[$code][] = [
                    'week' => $weekIdx,
                    'date' => $ac->lesson_date,
                    'start' => $ac->lesson_pair_start_time,
                    'hours' => $hours,
                    'marked' => (int) $ac->load,
                ];
                $dayAcc[$dayKey] = count($hemisLessonsRaw[$code]) - 1;

                if (!isset($trainingTypes[$code])) {
                    $trainingTypes[$code] = [
                        'name' => $ac->training_type_name ?? $code,
                        'planned_hours' => 0,
                    ];
                }
            }

            // KTR rejasidan soatlarni olish
            $ktrWeeks = [];
            $weekCount = 0;
            $ktrExists = false;
            if (\Illuminate\Support\Facades\Schema::hasTable('ktr_plans')) {
                $plan = DB::table('ktr_plans')->where('curriculum_subject_id', $cs->cs_id)->first();
                if ($plan) {
                    $ktrExists = true;
                    $weekCount = (int) $plan->week_count;
                    $planData = is_string($plan->plan_data) ? json_decode($plan->plan_data, true) : $plan->plan_data;
                    if (is_array($planData)) {
                        $hoursData = $planData['hours'] ?? $planData;
                        if (is_array($hoursData)) {
                            foreach ($hoursData as $w => $weekData) {
                                if (!is_array($weekData)) continue;
                                $wIdx = (int) $w;
                                foreach ($weekData as $code => $hours) {
                                    $codeStr = (string) $code;
                                    // KTR yangi training turini qo'shmasin (mustaqil bo'lsa) - subject_details da borligini tekshiramiz
                                    if (!isset($trainingTypes[$codeStr])) {
                                        if ($isMustaqil($codeStr)) {
                                            continue;
                                        }
                                        $trainingTypes[$codeStr] = [
                                            'name' => $codeStr,
                                            'planned_hours' => 0,
                                        ];
                                    }
                                    $ktrWeeks[$wIdx][$codeStr] = (int) $hours;
                                }
                            }
                        }
                    }
                }
            }

            // Dars turlari tartibini belgilash
            $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];
            $normalize = function ($str) {
                return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
            };
            uksort($trainingTypes, function ($a, $b) use ($trainingTypes, $typeOrder, $normalize) {
                $nameA = $normalize($trainingTypes[$a]['name']);
                $nameB = $normalize($trainingTypes[$b]['name']);
                $posA = count($typeOrder);
                $posB = count($typeOrder);
                foreach ($typeOrder as $i => $keyword) {
                    if ($posA === count($typeOrder) && str_contains($nameA, $keyword)) $posA = $i;
                    if ($posB === count($typeOrder) && str_contains($nameB, $keyword)) $posB = $i;
                }
                return $posA <=> $posB;
            });

            // Har bir dars turi bo'yicha HEMIS darslarini sana bo'yicha tartibga solish
            $hemisLessonsByType = [];
            foreach ($trainingTypes as $code => $info) {
                $list = $hemisLessonsRaw[$code] ?? [];
                usort($list, function ($a, $b) {
                    $ad = substr((string) ($a['date'] ?? ''), 0, 10);
                    $bd = substr((string) ($b['date'] ?? ''), 0, 10);
                    $cmp = strcmp($ad, $bd);
                    if ($cmp !== 0) return $cmp;
                    return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
                });
                $hemisLessonsByType[$code] = $list;
            }

            // Sana orqali hafta indeksini aniqlash (KTR soatini taqsimlash uchun)
            $resolveWeekFromDate = function ($dateStr) use ($weekRanges, &$weekStartByIdx) {
                if (!$dateStr) return null;
                foreach ($weekRanges as $r) {
                    if ($dateStr >= $r['start'] && $dateStr <= $r['end']) return $r['idx'];
                }
                foreach ($weekStartByIdx as $wIdx => $startDate) {
                    if ($startDate === $dateStr) return $wIdx;
                }
                return null;
            };

            // Har bir dars turi uchun: haftadagi HEMIS darslar soni (KTR soatini taqsimlash uchun)
            $lessonsPerWeekByType = [];
            foreach ($hemisLessonsByType as $code => $list) {
                foreach ($list as $lesson) {
                    $w = $lesson['week'] ?? $resolveWeekFromDate(substr((string) $lesson['date'], 0, 10));
                    if ($w === null) continue;
                    $lessonsPerWeekByType[$code][$w] = ($lessonsPerWeekByType[$code][$w] ?? 0) + 1;
                }
            }

            // Har bir hafta uchun: shu haftada HEMIS jadvalda dars qo'yilgan barcha sanalar
            // (har qanday dars turi bo'yicha). Orphan KTR soatini bu sanalarga ulash uchun ishlatiladi.
            $weekToHemisDates = [];
            foreach ($hemisLessonsByType as $codeAny => $listAny) {
                foreach ($listAny as $lessonAny) {
                    $dAny = substr((string) ($lessonAny['date'] ?? ''), 0, 10);
                    if ($dAny === '') continue;
                    $wAny = $lessonAny['week'] ?? $resolveWeekFromDate($dAny);
                    if ($wAny === null) continue;
                    $weekToHemisDates[$wAny][$dAny] = true;
                }
            }

            // Har bir dars turi uchun darslar ro'yxati (sana bo'yicha, HEMIS darslari + KTR-only haftalar)
            $lessonsByType = [];
            foreach ($trainingTypes as $code => $info) {
                $list = [];
                $hemisDatesSet = [];
                $hemisWeeksSet = [];

                // HEMIS darslari
                foreach ($hemisLessonsByType[$code] ?? [] as $lesson) {
                    $dateStr = substr((string) $lesson['date'], 0, 10);
                    $w = $lesson['week'] ?? $resolveWeekFromDate($dateStr);
                    $ktrWeekHours = ($ktrExists && $w !== null) ? (int) ($ktrWeeks[$w][$code] ?? 0) : 0;
                    $cnt = max(1, $lessonsPerWeekByType[$code][$w] ?? 1);
                    $ktrPerLesson = $ktrWeekHours / $cnt;

                    $list[] = [
                        'date' => $dateStr,
                        'hemis' => (int) $lesson['hours'],
                        'ktr' => $ktrPerLesson,
                        'marked' => (int) ($lesson['marked'] ?? 0),
                    ];
                    $hemisDatesSet[$dateStr] = true;
                    if ($w !== null) $hemisWeeksSet[$w] = true;
                }

                // KTR rejada bor, lekin HEMIS'da shu turdagi dars qo'yilmagan haftalar.
                // Avval shu haftada boshqa dars turi sanasiga ulanadi (yangi qator yaratilmaydi).
                // Agar haftada hech qaysi turda dars yo'q bo'lsa — haftaning boshlanish sanasi bilan
                // alohida "KTR rejada" qatori chiqariladi.
                if ($ktrExists) {
                    foreach ($ktrWeeks as $w => $wd) {
                        if (empty($wd[$code])) continue;
                        if (isset($hemisWeeksSet[$w])) continue;

                        $borrowedDate = null;
                        foreach (array_keys($weekToHemisDates[$w] ?? []) as $candDate) {
                            if (!isset($hemisDatesSet[$candDate])) {
                                $borrowedDate = $candDate;
                                break;
                            }
                        }

                        if ($borrowedDate !== null) {
                            $list[] = [
                                'date' => $borrowedDate,
                                'hemis' => 0,
                                'ktr' => (int) $wd[$code],
                                'marked' => 0,
                            ];
                            $hemisDatesSet[$borrowedDate] = true;
                        } else {
                            $weekDate = $weekStartByIdx[$w] ?? '';
                            if ($weekDate === '' || isset($hemisDatesSet[$weekDate])) continue;
                            $list[] = [
                                'date' => $weekDate,
                                'hemis' => 0,
                                'ktr' => (int) $wd[$code],
                                'marked' => 0,
                                'ktr_only' => true,
                            ];
                            $hemisDatesSet[$weekDate] = true;
                        }
                    }
                }

                // Sana bo'yicha saralash
                usort($list, function ($a, $b) {
                    $ad = $a['date'] ?: '9999-12-31';
                    $bd = $b['date'] ?: '9999-12-31';
                    return strcmp($ad, $bd);
                });

                $lessonsByType[$code] = $list;
            }

            // Har bir dars turi uchun sana -> lesson xaritasi
            $byTypeByDate = [];
            $allDates = [];
            foreach ($lessonsByType as $code => $list) {
                foreach ($list as $l) {
                    $d = $l['date'] ?? '';
                    if ($d === '') continue;
                    $byTypeByDate[$code][$d] = $l;
                    $allDates[$d] = true;
                }
            }
            ksort($allDates);
            $uniqueDates = array_keys($allDates);
            $maxLessons = count($uniqueDates);
            if ($maxLessons <= 0) $maxLessons = 1;

            // Darslar ro'yxatini tuzish - har bir noyob sana = bitta qator
            $lessonsList = [];
            foreach ($uniqueDates as $k => $date) {
                $rowData = [
                    'lesson' => $k + 1,
                    'date' => date('d.m.Y', strtotime($date)),
                    'cells' => [],
                    'ktr_only' => true,
                ];
                $rowHasHemis = false;
                foreach ($trainingTypes as $code => $info) {
                    $l = $byTypeByDate[$code][$date] ?? null;
                    $hemisH = $l ? (int) $l['hemis'] : 0;
                    $markedH = $l ? (int) ($l['marked'] ?? 0) : 0;
                    if ($ktrExists) {
                        $kRaw = $l ? (float) $l['ktr'] : 0;
                        $ktrH = (abs($kRaw - round($kRaw)) < 0.01) ? (int) round($kRaw) : round($kRaw, 1);
                    } else {
                        $ktrH = null;
                    }
                    if ($l && empty($l['ktr_only'])) {
                        $rowHasHemis = true;
                    }
                    $rowData['cells'][$code] = [
                        'hemis' => $hemisH,
                        'ktr' => $ktrH,
                        'marked' => $markedH,
                        'diff' => $ktrExists ? (round($ktrH - $hemisH, 1) + 0) : null,
                    ];
                }
                if ($rowHasHemis) $rowData['ktr_only'] = false;
                $lessonsList[] = $rowData;
            }

            return response()->json([
                'subject_name' => $cs->subject_name,
                'group_name' => $cs->group_name,
                'ktr_exists' => $ktrExists,
                'week_count' => $weekCount,
                'total_lessons' => $maxLessons,
                'training_types' => $trainingTypes,
                'lessons' => $lessonsList,
                'totals' => $this->computeModalTotals($trainingTypes, $hemisLessonsByType, $ktrWeeks, $ktrExists),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Schedule-KTR compare detail error: ' . $e->getMessage(), [
                'cs_id' => $csId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function computeModalTotals(array $trainingTypes, array $hemisLessonsByType, array $ktrWeeks, bool $ktrExists): array
    {
        $totals = [];
        foreach ($trainingTypes as $code => $info) {
            $totalHemis = 0;
            $totalMarked = 0;
            foreach ($hemisLessonsByType[$code] ?? [] as $l) {
                $totalHemis += (int) $l['hours'];
                $totalMarked += (int) ($l['marked'] ?? 0);
            }
            $totalKtr = 0;
            if ($ktrExists) {
                foreach ($ktrWeeks as $w => $wd) {
                    $totalKtr += (int) ($wd[$code] ?? 0);
                }
            }
            $totals[$code] = [
                'hemis' => $totalHemis,
                'ktr' => $ktrExists ? $totalKtr : null,
                'marked' => $totalMarked,
                'diff' => $ktrExists ? ($totalKtr - $totalHemis) : null,
            ];
        }
        return $totals;
    }

    /**
     * Dars jadval mosligi Excel export - umumiy (yig'ilgan) ko'rinish
     * Har bir fan+guruh bitta qator: Ajratilgan, HEMIS, KTR, Farqlar.
     */
    private function exportScheduleReportSummaryExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Jadval mosligi');

        $headers = ['#', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Fan', 'Guruh', 'Ajratilgan soat', "Jadvalda qo'yilgan soat", 'KTR soati', 'Farq (ajrat.)', 'Farq (KTR)'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E293B']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

        foreach ($data as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue([1, $row], $i + 1);
            $sheet->setCellValue([2, $row], $r['faculty_name'] ?? '-');
            $sheet->setCellValue([3, $row], $r['specialty_name'] ?? '-');
            $sheet->setCellValue([4, $row], $r['level_name'] ?? '-');
            $sheet->setCellValue([5, $row], $r['semester_name'] ?? '-');
            $sheet->setCellValue([6, $row], $r['subject_name'] ?? '-');
            $sheet->setCellValue([7, $row], $r['group_name'] ?? '-');
            $sheet->setCellValue([8, $row], $r['planned_hours']);
            $sheet->setCellValue([9, $row], $r['scheduled_hours']);
            $sheet->setCellValue([10, $row], !empty($r['ktr_exists']) ? $r['ktr_hours'] : 'KTR yo\'q');
            $sheet->setCellValue([11, $row], $r['farq']);
            $sheet->setCellValue([12, $row], !empty($r['ktr_exists']) ? $r['ktr_farq'] : '-');
        }

        $widths = [5, 22, 28, 8, 10, 32, 14, 14, 20, 12, 12, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:L{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("H2:L{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }
        $sheet->freezePane('A2');

        $fileName = 'Jadval_mosligi_umumiy_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'sr_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Dars jadval mosligi Excel export - darslar bo'yicha batafsil ko'rinish
     * Har bir HEMIS darsi alohida qator bo'ladi (N-dars), KTR soati haftadagi darslar bo'yicha taqsimlanadi.
     */
    private function exportScheduleReportLessonsExcel($curriculumSubjects, Request $request)
    {
        try {
        @set_time_limit(300);
        @ini_set('memory_limit', '1G');
        $isMustaqil = function ($name) {
            $normalized = preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower((string) $name));
            return str_contains($normalized, 'mustaqil');
        };
        $normalize = function ($str) {
            return preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower((string) $str));
        };
        $typeOrder = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar'];
        $trainingTypeFilter = $request->has('training_types') ? (array) $request->training_types : [];

        // Semestrlarning haftalari (tartib raqami, boshlanish va tugash sanalari)
        $semIds = $curriculumSubjects->pluck('semester_hemis_id')->filter()->unique()->toArray();
        $weekIndexMap = [];
        $weekStartMap = []; // [$semId][$weekIdx] = 'YYYY-MM-DD'
        $weekRangesMap = []; // [$semId] = [{idx, start, end}, ...]
        if (!empty($semIds)) {
            $weeks = DB::table('curriculum_weeks')
                ->whereIn('semester_hemis_id', $semIds)
                ->orderBy('start_date')
                ->select('curriculum_week_hemis_id', 'semester_hemis_id', 'start_date', 'end_date')
                ->get()
                ->groupBy('semester_hemis_id');
            foreach ($weeks as $sId => $rows) {
                foreach ($rows->values() as $i => $w) {
                    $idx = $i + 1;
                    $weekIndexMap[(string) $sId][(string) $w->curriculum_week_hemis_id] = $idx;
                    $weekStartMap[(string) $sId][$idx] = substr((string) $w->start_date, 0, 10);
                    $weekRangesMap[(string) $sId][] = [
                        'idx' => $idx,
                        'start' => substr((string) $w->start_date, 0, 10),
                        'end' => substr((string) $w->end_date, 0, 10),
                    ];
                }
            }
        }

        // Jadval satrlarini barcha fan+guruh uchun bir so'rovda olish
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
        $scheduleRows = $scheduleQuery->select(
            'sch.schedule_hemis_id',
            'sch.group_id', 'sch.subject_id', 'sch.semester_code',
            'sch.training_type_code', 'sch.training_type_name',
            'sch.week_number', 'sch.lesson_date',
            'sch.lesson_pair_start_time', 'sch.lesson_pair_end_time'
        )->get();

        $schedBySubject = [];
        foreach ($scheduleRows as $row) {
            $key = $row->group_id . '|' . $row->subject_id . '|' . $row->semester_code;
            $schedBySubject[$key][] = $row;
        }

        // O'qituvchi belgilagan soatlar (attendance_controls)
        $markedByScheduleId = [];
        $acBySubject = []; // [group|subject|semester] => [AC qatorlari]
        if (\Illuminate\Support\Facades\Schema::hasTable('attendance_controls')) {
            $scheduleHemisIds = $scheduleRows->pluck('schedule_hemis_id')->filter()->unique()->toArray();
            if (!empty($scheduleHemisIds)) {
                $markedByScheduleId = DB::table('attendance_controls')
                    ->whereNull('deleted_at')
                    ->whereIn('subject_schedule_id', $scheduleHemisIds)
                    ->select('subject_schedule_id', DB::raw('SUM(`load`) as marked_load'))
                    ->groupBy('subject_schedule_id')
                    ->pluck('marked_load', 'subject_schedule_id')
                    ->toArray();
            }

            // attendance_controls dan alohida darslar (schedules da bo'lmaganlar) - jurnal kabi deleted_at qo'llanmaydi
            $acAll = DB::table('attendance_controls')
                ->whereIn('group_id', $groupIds)
                ->whereIn('subject_id', $subjectIds)
                ->whereIn('semester_code', $semesterCodes)
                ->whereNotNull('lesson_date');
            if ($request->filled('date_from')) {
                $acAll->whereRaw('DATE(lesson_date) >= ?', [$request->date_from]);
            }
            if ($request->filled('date_to')) {
                $acAll->whereRaw('DATE(lesson_date) <= ?', [$request->date_to]);
            }
            $acAllRows = $acAll->select(
                'group_id', 'subject_id', 'semester_code',
                'training_type_code', 'training_type_name',
                'lesson_date', 'lesson_pair_start_time', 'lesson_pair_end_time', 'load'
            )->get();
            foreach ($acAllRows as $ac) {
                $key = $ac->group_id . '|' . $ac->subject_id . '|' . $ac->semester_code;
                $acBySubject[$key][] = $ac;
            }
        }

        // KTR rejalarini cs_id bo'yicha olish
        $ktrPlans = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('ktr_plans')) {
            $csIds = $curriculumSubjects->pluck('cs_id')->unique()->toArray();
            $plans = DB::table('ktr_plans')
                ->whereIn('curriculum_subject_id', $csIds)
                ->select('curriculum_subject_id', 'week_count', 'plan_data')
                ->get();
            foreach ($plans as $p) {
                $ktrPlans[$p->curriculum_subject_id] = $p;
            }
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Batafsil mosligi');

        // 1-bosqich: har bir (cs) uchun barcha dars turlari ma'lumotini tayyorlash
        // $blocks[csIdx] = ['cs'=>..., 'training_types'=>['code'=>['name','hemis'=>[],'ktr'=>[],'dates'=>[]]], 'ktr_exists'=>bool]
        $blocks = [];
        $globalTrainingTypes = []; // code => name (barcha cs lar bo'ylab birlashtirilgan)

        foreach ($curriculumSubjects as $cs) {
            $details = is_string($cs->subject_details) ? json_decode($cs->subject_details, true) : $cs->subject_details;
            $trainingTypes = [];
            if (is_array($details)) {
                foreach ($details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $name = $d['trainingType']['name'] ?? '';
                    if ($code === '' || $isMustaqil($name)) continue;
                    if (!empty($trainingTypeFilter) && !in_array($code, $trainingTypeFilter)) continue;
                    $trainingTypes[$code] = $name;
                }
            }

            $semId = (string) ($cs->semester_hemis_id ?? '');
            $wMap = $weekIndexMap[$semId] ?? [];
            $wRanges = $weekRangesMap[$semId] ?? [];
            $weekStartByIdx = $weekStartMap[$semId] ?? [];

            // week_number yoki lesson_date orqali hafta indeksini topadi
            $resolveWeek = function ($lessonDate, $fallbackWeekNumber) use ($wMap, $wRanges, &$weekStartByIdx) {
                $idx = $wMap[(string) $fallbackWeekNumber] ?? null;
                if ($idx !== null) return $idx;
                $d = substr((string) $lessonDate, 0, 10);
                if ($d === '') return null;
                foreach ($wRanges as $r) {
                    if ($d >= $r['start'] && $d <= $r['end']) return $r['idx'];
                }
                foreach ($weekStartByIdx as $wIdx => $startDate) {
                    if ($startDate === $d) return $wIdx;
                }
                $maxIdx = empty($weekStartByIdx) ? 0 : max(array_keys($weekStartByIdx));
                $newIdx = $maxIdx + 1;
                $weekStartByIdx[$newIdx] = $d;
                return $newIdx;
            };

            // HEMIS darslarini dars turi bo'yicha (bir kundagi soatlar bitta darsga jamlanadi)
            $hemisLessonsRaw = [];
            $dayAcc = [];
            $key = $cs->group_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code;
            foreach ($schedBySubject[$key] ?? [] as $r) {
                $code = (string) $r->training_type_code;
                $name = $r->training_type_name ?? $code;
                if ($isMustaqil($name)) continue;
                if (!empty($trainingTypeFilter) && !in_array($code, $trainingTypeFilter)) continue;
                $wIdx = $resolveWeek($r->lesson_date, $r->week_number);
                if ($wIdx === null) continue;
                $start = strtotime($r->lesson_pair_start_time);
                $end = strtotime($r->lesson_pair_end_time);
                $hours = max(1, round((($end - $start) / 60) / 40));
                $marked = (int) ($markedByScheduleId[$r->schedule_hemis_id] ?? 0);

                $dateStr = '';
                if (!empty($r->lesson_date)) {
                    $dateStr = substr((string) $r->lesson_date, 0, 10);
                }
                $dayKey = $code . '|' . $dateStr;

                if (isset($dayAcc[$dayKey])) {
                    $idx = $dayAcc[$dayKey];
                    $hemisLessonsRaw[$code][$idx]['hours'] += $hours;
                    $hemisLessonsRaw[$code][$idx]['marked'] += $marked;
                    if (strcmp($r->lesson_pair_start_time, $hemisLessonsRaw[$code][$idx]['start']) < 0) {
                        $hemisLessonsRaw[$code][$idx]['start'] = $r->lesson_pair_start_time;
                    }
                } else {
                    $hemisLessonsRaw[$code][] = [
                        'week' => $wIdx,
                        'date' => $r->lesson_date,
                        'start' => $r->lesson_pair_start_time,
                        'hours' => $hours,
                        'marked' => $marked,
                    ];
                    $dayAcc[$dayKey] = count($hemisLessonsRaw[$code]) - 1;
                }
                if (!isset($trainingTypes[$code])) {
                    $trainingTypes[$code] = $name;
                }
            }

            // attendance_controls dan qo'shimcha darslarni qo'shish
            $acKey = $cs->group_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code;
            foreach ($acBySubject[$acKey] ?? [] as $ac) {
                $code = (string) $ac->training_type_code;
                $name = $ac->training_type_name ?? $code;
                if ($isMustaqil($name)) continue;
                if (!empty($trainingTypeFilter) && !in_array($code, $trainingTypeFilter)) continue;
                $dateStr = substr((string) $ac->lesson_date, 0, 10);
                if (!$dateStr) continue;

                $dayKey = $code . '|' . $dateStr;
                if (isset($dayAcc[$dayKey])) {
                    $idx = $dayAcc[$dayKey];
                    if (($hemisLessonsRaw[$code][$idx]['marked'] ?? 0) === 0) {
                        $hemisLessonsRaw[$code][$idx]['marked'] = (int) $ac->load;
                    }
                    continue;
                }
                $wIdx = $resolveWeek($ac->lesson_date, null);
                if ($wIdx === null) continue;
                $hours = max(1, (int) $ac->load);

                $hemisLessonsRaw[$code][] = [
                    'week' => $wIdx,
                    'date' => $ac->lesson_date,
                    'start' => $ac->lesson_pair_start_time,
                    'hours' => $hours,
                    'marked' => (int) $ac->load,
                ];
                $dayAcc[$dayKey] = count($hemisLessonsRaw[$code]) - 1;
                if (!isset($trainingTypes[$code])) {
                    $trainingTypes[$code] = $name;
                }
            }

            foreach ($hemisLessonsRaw as $code => &$list) {
                usort($list, function ($a, $b) {
                    $ad = substr((string) ($a['date'] ?? ''), 0, 10);
                    $bd = substr((string) ($b['date'] ?? ''), 0, 10);
                    $cmp = strcmp($ad, $bd);
                    if ($cmp !== 0) return $cmp;
                    return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
                });
            }
            unset($list);

            // KTR rejasi
            $ktrWeeks = [];
            $ktrExists = false;
            if (isset($ktrPlans[$cs->cs_id])) {
                $plan = $ktrPlans[$cs->cs_id];
                $ktrExists = true;
                $planData = is_string($plan->plan_data) ? json_decode($plan->plan_data, true) : $plan->plan_data;
                if (is_array($planData)) {
                    $hoursData = $planData['hours'] ?? $planData;
                    if (is_array($hoursData)) {
                        foreach ($hoursData as $w => $weekData) {
                            if (!is_array($weekData)) continue;
                            $wIdx = (int) $w;
                            foreach ($weekData as $code => $hours) {
                                $codeStr = (string) $code;
                                if ($isMustaqil($codeStr)) continue;
                                if (!empty($trainingTypeFilter) && !in_array($codeStr, $trainingTypeFilter)) continue;
                                $ktrWeeks[$wIdx][$codeStr] = (int) $hours;
                                if (!isset($trainingTypes[$codeStr])) {
                                    $trainingTypes[$codeStr] = $codeStr;
                                }
                            }
                        }
                    }
                }
            }

            if (empty($trainingTypes)) continue;

            // HEMIS darslarini dars turi bo'yicha sana bo'yicha saralash
            $hemisLessonsByType = [];
            foreach ($trainingTypes as $code => $name) {
                $list = $hemisLessonsRaw[$code] ?? [];
                usort($list, function ($a, $b) {
                    $ad = substr((string) ($a['date'] ?? ''), 0, 10);
                    $bd = substr((string) ($b['date'] ?? ''), 0, 10);
                    $cmp = strcmp($ad, $bd);
                    if ($cmp !== 0) return $cmp;
                    return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
                });
                $hemisLessonsByType[$code] = $list;
            }

            // Sana orqali hafta indeksini aniqlash
            $resolveWeekFromDate = function ($dateStr) use ($wRanges, &$weekStartByIdx) {
                if (!$dateStr) return null;
                foreach ($wRanges as $r) {
                    if ($dateStr >= $r['start'] && $dateStr <= $r['end']) return $r['idx'];
                }
                foreach ($weekStartByIdx as $wIdx => $startDate) {
                    if ($startDate === $dateStr) return $wIdx;
                }
                return null;
            };

            // Har bir dars turi uchun: haftadagi HEMIS darslar soni
            $lessonsPerWeekByType = [];
            foreach ($hemisLessonsByType as $code => $list) {
                foreach ($list as $lesson) {
                    $w = $lesson['week'] ?? $resolveWeekFromDate(substr((string) $lesson['date'], 0, 10));
                    if ($w === null) continue;
                    $lessonsPerWeekByType[$code][$w] = ($lessonsPerWeekByType[$code][$w] ?? 0) + 1;
                }
            }

            // Har bir dars turi uchun darslar massivlarini (sana bo'yicha tartibda) qurib chiqamiz
            $ttData = [];
            foreach ($trainingTypes as $code => $name) {
                $hemisVals = [];
                $ktrVals = [];
                $markedVals = [];
                $dates = [];
                $hemisWeeksSet = [];

                // HEMIS darslari
                foreach ($hemisLessonsByType[$code] ?? [] as $lesson) {
                    $dateStr = substr((string) $lesson['date'], 0, 10);
                    $w = $lesson['week'] ?? $resolveWeekFromDate($dateStr);
                    $ktrWeekHours = ($ktrExists && $w !== null) ? (int) ($ktrWeeks[$w][$code] ?? 0) : 0;
                    $cnt = max(1, $lessonsPerWeekByType[$code][$w] ?? 1);
                    $ktrPerLesson = $ktrWeekHours / $cnt;

                    $hemisVals[] = (int) $lesson['hours'];
                    $markedVals[] = (int) ($lesson['marked'] ?? 0);
                    $dates[] = $dateStr;
                    if ($ktrExists) {
                        $ktrVals[] = (abs($ktrPerLesson - round($ktrPerLesson)) < 0.01) ? (int) round($ktrPerLesson) : round($ktrPerLesson, 1);
                    }
                    if ($w !== null) $hemisWeeksSet[$w] = true;
                }

                // HEMIS yo'q, lekin KTR soati bor haftalar uchun ALOHIDA qator yaratilmaydi
                // KTR'ning to'liq jami'si Jami satrida ko'rinadi

                // Jami uchun alohida KTR jami (barcha haftalar bo'yicha)
                $totalKtrForCode = 0;
                if ($ktrExists) {
                    foreach ($ktrWeeks as $w => $wd) {
                        $totalKtrForCode += (int) ($wd[$code] ?? 0);
                    }
                }

                if (empty($hemisVals) && empty($ktrVals) && $totalKtrForCode === 0) continue;
                $ttData[$code] = ['name' => $name, 'hemis' => $hemisVals, 'ktr' => $ktrVals, 'marked' => $markedVals, 'dates' => $dates, 'total_ktr' => $totalKtrForCode];
                if (!isset($globalTrainingTypes[$code])) {
                    $globalTrainingTypes[$code] = $name;
                }
            }

            if (empty($ttData)) continue;

            $blocks[] = ['cs' => $cs, 'training_types' => $ttData, 'ktr_exists' => $ktrExists];
        }

        // Global dars turlarini standart tartibda saralash
        uksort($globalTrainingTypes, function ($a, $b) use ($globalTrainingTypes, $typeOrder, $normalize) {
            $nameA = $normalize($globalTrainingTypes[$a]);
            $nameB = $normalize($globalTrainingTypes[$b]);
            $posA = count($typeOrder);
            $posB = count($typeOrder);
            foreach ($typeOrder as $i => $kw) {
                if ($posA === count($typeOrder) && str_contains($nameA, $kw)) $posA = $i;
                if ($posB === count($typeOrder) && str_contains($nameB, $kw)) $posB = $i;
            }
            return $posA <=> $posB;
        });

        $globalTtCodes = array_keys($globalTrainingTypes);
        $ttCount = count($globalTtCodes);
        if ($ttCount === 0) {
            // Bo'sh natija - sarlavha bilan qaytarish
            $sheet->setCellValue('A1', "Ma'lumot topilmadi");
            $fileName = 'Jadval_mosligi_batafsil_' . date('Y-m-d_H-i') . '.xlsx';
            $temp = tempnam(sys_get_temp_dir(), 'sr_');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($temp);
            $spreadsheet->disconnectWorksheets();
            return response()->download($temp, $fileName)->deleteFileAfterSend(true);
        }

        // 2-bosqich: Excel sarlavha (2 qatorli)
        // Static ustunlar: # | Fakultet | Yo'nalish | Kurs | Semestr | Fan | Guruh | Dars (sana)
        $staticHeaders = ['#', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Fan', 'Guruh', 'Dars (sana)'];
        $staticCols = count($staticHeaders); // 8

        foreach ($staticHeaders as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
            $sheet->mergeCells([$col + 1, 1, $col + 1, 2]);
        }

        // Dars turi guruhli sarlavhalar (HEMIS / KTR / Belgi / Farq)
        $col = $staticCols + 1;
        foreach ($globalTtCodes as $code) {
            $name = $globalTrainingTypes[$code];
            $sheet->setCellValue([$col, 1], $name);
            $sheet->mergeCells([$col, 1, $col + 3, 1]);
            $sheet->setCellValue([$col, 2], 'HEMIS');
            $sheet->setCellValue([$col + 1, 2], 'KTR');
            $sheet->setCellValue([$col + 2, 2], 'Belgi');
            $sheet->setCellValue([$col + 3, 2], 'Farq');
            $col += 4;
        }
        // Jami guruhi
        $sheet->setCellValue([$col, 1], 'Jami');
        $sheet->mergeCells([$col, 1, $col + 3, 1]);
        $sheet->setCellValue([$col, 2], 'HEMIS');
        $sheet->setCellValue([$col + 1, 2], 'KTR');
        $sheet->setCellValue([$col + 2, 2], 'Belgi');
        $sheet->setCellValue([$col + 3, 2], 'Farq');

        $totalCols = $staticCols + ($ttCount + 1) * 4;
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E293B']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle("A1:{$lastColLetter}2")->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $excelRow = 3;
        $num = 1;

        // Sana formatlovchi
        $fmtDate = function ($d) {
            if (empty($d)) return '';
            $ts = strtotime($d);
            return $ts ? date('d.m.Y', $ts) : '';
        };

        // 3-bosqich: ma'lumotni yozish (har bir block uchun: maxLessons darslari uchun qatorlar)
        foreach ($blocks as $block) {
            $cs = $block['cs'];
            $ttData = $block['training_types'];
            $ktrExists = $block['ktr_exists'];

            // Har bir dars turi uchun sana -> indeks xaritasi
            $byTypeByDate = [];
            $allDates = [];
            foreach ($ttData as $code => $td) {
                foreach ($td['dates'] ?? [] as $i => $d) {
                    if (empty($d)) continue;
                    $byTypeByDate[$code][$d] = $i;
                    $allDates[$d] = true;
                }
            }
            ksort($allDates);
            $uniqueDates = array_keys($allDates);
            $maxK = count($uniqueDates);
            if ($maxK === 0) continue;

            $blockStartRow = $excelRow;

            foreach ($uniqueDates as $k => $rowDateRaw) {
                $rowDate = $fmtDate($rowDateRaw);
                $darsLabel = ($k + 1) . '-dars' . ($rowDate ? ' (' . $rowDate . ')' : '');

                // Static cells
                $sheet->setCellValue([1, $excelRow], $num++);
                $sheet->setCellValue([2, $excelRow], $cs->faculty_name ?? '-');
                $sheet->setCellValue([3, $excelRow], $cs->specialty_name ?? '-');
                $sheet->setCellValue([4, $excelRow], $cs->level_name ?? '-');
                $sheet->setCellValue([5, $excelRow], $cs->semester_name ?? '-');
                $sheet->setCellValue([6, $excelRow], $cs->subject_name ?? '-');
                $sheet->setCellValue([7, $excelRow], $cs->group_name ?? '-');
                $sheet->setCellValue([8, $excelRow], $darsLabel);

                // Dars turi cells (HEMIS / KTR / Belgi / Farq)
                $col = $staticCols + 1;
                $rowHemisSum = 0;
                $rowKtrSum = 0;
                $rowMarkedSum = 0;
                $rowFarqSum = 0;
                foreach ($globalTtCodes as $code) {
                    $td = $ttData[$code] ?? null;
                    $tdIdx = $byTypeByDate[$code][$rowDateRaw] ?? null;
                    $h = ($td && $tdIdx !== null && isset($td['hemis'][$tdIdx])) ? $td['hemis'][$tdIdx] : '';
                    $kt = ($td && $tdIdx !== null && isset($td['ktr'][$tdIdx])) ? $td['ktr'][$tdIdx] : '';
                    $mk = ($td && $tdIdx !== null && isset($td['marked'][$tdIdx])) ? $td['marked'][$tdIdx] : '';
                    $sheet->setCellValue([$col, $excelRow], $h);
                    if ($ktrExists) {
                        $sheet->setCellValue([$col + 1, $excelRow], $kt);
                    } else {
                        $sheet->setCellValue([$col + 1, $excelRow], '-');
                    }
                    $sheet->setCellValue([$col + 2, $excelRow], $mk);
                    if (is_numeric($mk) && $mk > 0) {
                        $sheet->getStyle([$col + 2, $excelRow, $col + 2, $excelRow])->applyFromArray([
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BBF7D0']],
                            'font' => ['bold' => true, 'color' => ['rgb' => '14532D']],
                        ]);
                        $rowMarkedSum += $mk;
                    }
                    if ($ktrExists) {
                        if ($h !== '' || $kt !== '') {
                            $diff = (is_numeric($kt) ? $kt : 0) - (is_numeric($h) ? $h : 0);
                            $diff = (abs($diff - round($diff)) < 0.01) ? (int) round($diff) : round($diff, 1);
                            $sheet->setCellValue([$col + 3, $excelRow], $diff);
                            if ($diff !== 0) {
                                $color = $diff > 0 ? 'FEF3C7' : 'FEE2E2';
                                $sheet->getStyle([$col + 3, $excelRow, $col + 3, $excelRow])->applyFromArray([
                                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                                ]);
                            }
                            $rowFarqSum += $diff;
                        }
                        if (is_numeric($kt)) $rowKtrSum += $kt;
                    } else {
                        $sheet->setCellValue([$col + 3, $excelRow], '-');
                    }
                    if (is_numeric($h)) $rowHemisSum += $h;
                    $col += 4;
                }

                // Jami ustunlari
                $sheet->setCellValue([$col, $excelRow], $rowHemisSum);
                if ($ktrExists) {
                    $sheet->setCellValue([$col + 1, $excelRow], $rowKtrSum);
                } else {
                    $sheet->setCellValue([$col + 1, $excelRow], '-');
                }
                $sheet->setCellValue([$col + 2, $excelRow], $rowMarkedSum);
                if ($ktrExists) {
                    $sheet->setCellValue([$col + 3, $excelRow], $rowFarqSum);
                    if ($rowFarqSum !== 0) {
                        $color = $rowFarqSum > 0 ? 'FEF3C7' : 'FEE2E2';
                        $sheet->getStyle([$col + 3, $excelRow, $col + 3, $excelRow])->applyFromArray([
                            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                        ]);
                    }
                } else {
                    $sheet->setCellValue([$col + 3, $excelRow], '-');
                }

                $excelRow++;
            }

            // Jami satri (ushbu cs uchun barcha darslar yig'indisi)
            $sheet->setCellValue([1, $excelRow], $num++);
            $sheet->setCellValue([2, $excelRow], $cs->faculty_name ?? '-');
            $sheet->setCellValue([3, $excelRow], $cs->specialty_name ?? '-');
            $sheet->setCellValue([4, $excelRow], $cs->level_name ?? '-');
            $sheet->setCellValue([5, $excelRow], $cs->semester_name ?? '-');
            $sheet->setCellValue([6, $excelRow], $cs->subject_name ?? '-');
            $sheet->setCellValue([7, $excelRow], $cs->group_name ?? '-');
            $sheet->setCellValue([8, $excelRow], 'Jami');
            $col = $staticCols + 1;
            $totalHemis = 0; $totalKtr = 0; $totalMarked = 0; $totalFarq = 0;
            foreach ($globalTtCodes as $code) {
                $td = $ttData[$code] ?? null;
                $h = $td ? array_sum($td['hemis']) : 0;
                // KTR jami: total_ktr (to'liq yig'indi) ishlatiladi
                $kt = ($td && $ktrExists) ? (int) ($td['total_ktr'] ?? array_sum($td['ktr'])) : 0;
                $mk = $td ? array_sum($td['marked'] ?? []) : 0;
                $f = $ktrExists ? round($kt - $h, 1) : 0;
                $sheet->setCellValue([$col, $excelRow], $h);
                if ($ktrExists) {
                    $sheet->setCellValue([$col + 1, $excelRow], $kt);
                } else {
                    $sheet->setCellValue([$col + 1, $excelRow], '-');
                }
                $sheet->setCellValue([$col + 2, $excelRow], $mk);
                if ($ktrExists) {
                    $sheet->setCellValue([$col + 3, $excelRow], $f);
                } else {
                    $sheet->setCellValue([$col + 3, $excelRow], '-');
                }
                $totalHemis += $h;
                $totalKtr += $kt;
                $totalMarked += $mk;
                $totalFarq += $f;
                $col += 4;
            }
            $sheet->setCellValue([$col, $excelRow], $totalHemis);
            if ($ktrExists) {
                $sheet->setCellValue([$col + 1, $excelRow], $totalKtr);
            } else {
                $sheet->setCellValue([$col + 1, $excelRow], '-');
            }
            $sheet->setCellValue([$col + 2, $excelRow], $totalMarked);
            if ($ktrExists) {
                $sheet->setCellValue([$col + 3, $excelRow], $totalFarq);
            } else {
                $sheet->setCellValue([$col + 3, $excelRow], '-');
            }
            $sheet->getStyle("A{$excelRow}:{$lastColLetter}{$excelRow}")->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                'font' => ['bold' => true],
            ]);
            $excelRow++;

            // Block ostiga chegarasi
            $sheet->getStyle("A" . ($excelRow - 1) . ":{$lastColLetter}" . ($excelRow - 1))->applyFromArray([
                'borders' => ['bottom' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['rgb' => '94A3B8']]],
            ]);
        }

        // Ustun kengliklari
        $widths = [5, 22, 28, 8, 10, 30, 14, 22];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }
        for ($c = $staticCols + 1; $c <= $totalCols; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(8);
        }

        $lastRow = $excelRow - 1;
        if ($lastRow > 2) {
            $sheet->getStyle("A3:{$lastColLetter}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A3:A{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $firstNumColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($staticCols + 1);
            $sheet->getStyle("{$firstNumColLetter}3:{$lastColLetter}{$lastRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        }

        // Sarlavhalar va birinchi ustunlarni muzlatish
        $freezeColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($staticCols + 1);
        $sheet->freezePane("{$freezeColLetter}3");

        $fileName = 'Jadval_mosligi_batafsil_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'sr_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            \Log::error('Lessons Excel export error: ' . $e->getMessage(), [
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

        // Fatal errorlarni (OOM / max_execution_time) ham ushlab, foydalanuvchiga
        // JSON javob qaytarish uchun shutdown funksiya ro'yxatdan o'tkaziladi.
        // Try/catch fatal xatolarni ushlay olmaydi — shu sababli bu kerak.
        $fatalCaught = false;
        register_shutdown_function(function () use (&$fatalCaught) {
            $err = error_get_last();
            if (!$err || $fatalCaught) return;
            $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE];
            if (!in_array($err['type'], $fatalTypes, true)) return;

            \Log::error('Absence report fatal error', $err);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode([
                'error' => 'Server xatosi: ' . ($err['message'] ?? 'fatal') . ' (' . basename($err['file'] ?? '') . ':' . ($err['line'] ?? '?') . ')',
            ]);
        });

        try {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: Schedule dan unique (group, subject, semester) kombinatsiyalarini olish
        // MUHIM: fakultet/ta'lim turi/kurs/guruh filtrlari shu yerda qo'llaniladi,
        // aks holda butun universitetning schedule yozuvlari yuklanib, timeout/OOM
        // sodir bo'lishi mumkin.
        $scheduleQuery = DB::table('schedules as sch')
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
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

        // Fakultet filtri: schedules.faculty_id = departments.department_hemis_id
        if ($request->filled('faculty')) {
            $facultyForSchedule = Department::find($request->faculty);
            if ($facultyForSchedule) {
                $scheduleQuery->where('sch.faculty_id', $facultyForSchedule->department_hemis_id);
            }
        }

        // Ta'lim turi filtri: faqat shu ta'lim turiga tegishli guruhlar
        if ($request->filled('education_type')) {
            $eduGroupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $request->education_type)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $scheduleQuery->whereIn('sch.group_id', $eduGroupIds);
        }

        // Guruh filtri: to'g'ridan-to'g'ri schedule da filtrlash
        if ($request->filled('group')) {
            $scheduleQuery->where('sch.group_id', $request->group);
        }

        // Nazoratchi: faqat biriktirilgan guruhlar
        if (is_active_nazoratchi()) {
            $nazoratchiHemisIds = get_nazoratchi_group_hemis_ids();
            if (empty($nazoratchiHemisIds)) {
                $fatalCaught = true;
                return response()->json(['data' => [], 'total' => 0]);
            }
            $scheduleQuery->whereIn('sch.group_id', $nazoratchiHemisIds);
        }

        // Kafedra filtri: schedules.department_id bo'yicha filter
        if ($request->filled('department')) {
            $scheduleQuery->where('sch.department_id', $request->department);
        }

        $scheduleCombos = $scheduleQuery->get();

        if ($scheduleCombos->isEmpty()) {
            $fatalCaught = true;
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
            $groupHemisIdForStudents = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
            if ($groupHemisIdForStudents) {
                $studentQuery->where('s.group_id', $groupHemisIdForStudents);
            }
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
            $fatalCaught = true;
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
                $fatalCaught = true;
                return response()->json(['data' => [], 'total' => 0]);
            }
        }

        // 3-QADAM: attendances dan davomat ma'lumotlarini olish
        // MUHIM: avval bu yerda student_grades ishlatilardi, lekin u jadvalda bir
        // dars uchun bir nechta yozuv bo'lishi mumkin (HEMIS qayta import, retake,
        // status o'zgarishlari) — bu sababsiz soat 4-5 baravargacha shishib ketardi
        // (jurnalda 23% bo'lsa, hisobotda 437% chiqishi mumkin edi).
        // attendances jadvali esa har bir (talaba, fan, sana, juftlik, training_type)
        // uchun bitta yagona yozuv saqlaydi va jurnal ham o'shani ishlatadi.
        $minScheduleDate = null;
        if ($isCurrentSemester) {
            $minScheduleDate = DB::table('schedules')
                ->where('education_year_current', true)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->min('lesson_date');
        }

        // Auditoriya bo'lmagan training_type kodlari (JournalController::show
        // dagi $excludedAttendanceCodes bilan bir xil): 99=MT, 100=ON,
        // 101=Oski, 102=Test. Bu jurnal "Dav %" hisoblash bilan moslashish
        // uchun kerak — aks holda foiz farq qilib qoladi.
        $nonAuditoryAttendanceCodes = [99, 100, 101, 102];

        // 4-QADAM: Har bir talaba/fan uchun davomat ma'lumotlarini hisoblash
        $studentSubjectData = [];
        $studentAllAttendanceDates = []; // Talabaning ISTALGAN fandan darsga chiqqan kunlari
        $now = Carbon::now('Asia/Tashkent');
        $spravkaDays = (int) Setting::get('spravka_deadline_days', 10);

        // attendances ni chunk qilib olish (katta ma'lumot uchun)
        foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
            $attendanceChunk = DB::table('attendances')
                ->whereIn('student_hemis_id', $hemisChunk)
                ->whereIn('subject_id', $filteredSubjectIds)
                ->whereIn('semester_code', $validSemesterCodes)
                ->whereNotIn('training_type_code', $nonAuditoryAttendanceCodes)
                ->whereNotNull('lesson_date')
                ->when($minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'lesson_date', 'absent_on', 'absent_off')
                ->get();

            foreach ($attendanceChunk as $a) {
                $groupId = $studentGroupMap[$a->student_hemis_id] ?? null;
                if (!$groupId) continue;

                $absentOn  = (int) $a->absent_on;   // sababli (excused)
                $absentOff = (int) $a->absent_off;  // sababsiz (unexcused)

                $ssKey = $a->student_hemis_id . '|' . $a->subject_id . '|' . $a->semester_code;
                $comboKey = $groupId . '|' . $a->subject_id . '|' . $a->semester_code;
                $dateKey = substr($a->lesson_date, 0, 10);

                if (!isset($studentSubjectData[$ssKey])) {
                    $studentSubjectData[$ssKey] = [
                        'student_hemis_id' => $a->student_hemis_id,
                        'subject_id' => $a->subject_id,
                        'subject_name' => $a->subject_name,
                        'semester_code' => $a->semester_code,
                        'combo_key' => $comboKey,
                        'group_id' => $groupId,
                        'total_absent_hours' => 0,
                        'unexcused_absent_hours' => 0,
                        'unexcused_absent_dates' => [],
                        'attendance_dates' => [],
                    ];
                }

                if ($absentOn > 0 || $absentOff > 0) {
                    $studentSubjectData[$ssKey]['total_absent_hours'] += $absentOn + $absentOff;
                    if ($absentOff > 0) {
                        $studentSubjectData[$ssKey]['unexcused_absent_hours'] += $absentOff;
                        // Saqlash uchun [sana => shu kungi sababsiz soat] (25% chegarasi
                        // sanasini aniqlashda kerak)
                        $studentSubjectData[$ssKey]['unexcused_absent_dates'][$dateKey] =
                            ($studentSubjectData[$ssKey]['unexcused_absent_dates'][$dateKey] ?? 0) + $absentOff;
                    }
                } else {
                    // Talaba shu darsda bo'lgan
                    $studentSubjectData[$ssKey]['attendance_dates'][$dateKey] = true;
                    $studentAllAttendanceDates[$a->student_hemis_id][$dateKey] = true;
                }
            }
            unset($attendanceChunk);
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

            // unexcused_absent_dates: [sana => shu kungi sababsiz soat]
            $absentDateHours = $data['unexcused_absent_dates'];
            ksort($absentDateHours);
            $absentDates = array_keys($absentDateHours);

            // Spravka muddati: oxirgi sababsiz dars kunidan boshlab hisoblash
            $spravkaStatus = '-';
            if (!empty($absentDates)) {
                $latestAbsentDate = Carbon::parse(end($absentDates));
                $daysSinceAbsent = $latestAbsentDate->diffInDays($now);
                $spravkaStatus = $daysSinceAbsent <= $spravkaDays ? 'Muddat bor' : 'Kechikkan';
            }

            // 25% chegarasiga yetgan sanani aniqlash (kunma-kun jamlash)
            $firstAttendanceAfter25 = null;
            $cumulativeHours = 0;
            $thresholdDate = null;

            foreach ($absentDateHours as $aDate => $hoursThatDay) {
                $cumulativeHours += $hoursThatDay;
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
            $fatalCaught = true;
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

        $fatalCaught = true;
        return response()->json([
            'data' => $pageData,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => (int) $page,
            'last_page' => ceil($total / $perPage),
        ]);
        } catch (\Throwable $e) {
            $fatalCaught = true;
            \Log::error('Absence report error: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ], 500);
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
        if (is_active_nazoratchi()) {
            abort(403, 'Bu hisobotga ruxsatingiz yo\'q.');
        }

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
        if (is_active_nazoratchi()) {
            abort(403, 'Bu hisobotga ruxsatingiz yo\'q.');
        }

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

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if ($dekanFacultyId) {
            $facultyQuery->where('id', $dekanFacultyId);
        }

        $faculties = $facultyQuery->get();

        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        $studentTypes = DB::table('students')
            ->select('student_type_code', 'student_type_name')
            ->whereNotNull('student_type_code')
            ->groupBy('student_type_code', 'student_type_name')
            ->orderBy('student_type_name')
            ->get();

        return view('admin.reports.debtors', compact(
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'studentStatuses',
            'studentTypes',
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

            // Bo'sh yoki "Barchasi" (0) tanlansa — barcha qarzdorlarni (>=1 fan) ko'rsatamiz
            $minDebtRaw = $request->get('min_debt_count', 4);
            $minDebtCount = ($minDebtRaw === '' || $minDebtRaw === null) ? 1 : (int) $minDebtRaw;
            if ($minDebtCount < 1) {
                $minDebtCount = 1;
            }
            $showCurrentSemester = $request->get('current_semester', '0') == '1';

            // 1-QADAM: Talabalar ro'yxatini filtrlar bo'yicha olish
            $studentQuery = DB::table('students as s')
                ->whereNotNull('s.curriculum_id')
                ->select('s.hemis_id', 's.full_name', 's.student_id_number',
                    's.department_name', 's.specialty_name', 's.level_name',
                    's.semester_name', 's.semester_code', 's.group_name',
                    's.group_id', 's.curriculum_id',
                    's.student_type_code', 's.student_type_name');

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
            if ($request->filled('group')) {
                $group = \App\Models\Group::find($request->group);
                if ($group) {
                    $studentQuery->where('s.group_id', $group->group_hemis_id);
                }
            }
            if ($request->filled('education_type')) {
                $studentQuery->where('s.education_type_code', $request->education_type);
            }
            if ($request->filled('student_type')) {
                $studentQuery->where('s.student_type_code', $request->student_type);
            }

            // Nazoratchi: faqat biriktirilgan guruhlar
            if (is_active_nazoratchi()) {
                $nazoratchiHemisIds = get_nazoratchi_group_hemis_ids();
                if (empty($nazoratchiHemisIds)) {
                    return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
                }
                $studentQuery->whereIn('s.group_id', $nazoratchiHemisIds);
            }

            $students = $studentQuery->get();
            if ($students->isEmpty()) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $studentMap = $students->keyBy('hemis_id');

            // 2-QADAM: Academic records'ni bir martagina yuklab olamiz —
            //  • mavjudligini tekshirish (qarz/yopiq aniqlash uchun)
            //  • har semestrdagi tarixiy curriculum_id (transferdan oldingi yo'lni topish uchun)
            $arRecords = [];
            foreach (array_chunk($studentHemisIds, 1000) as $chunk) {
                $arRecords = array_merge($arRecords, DB::table('academic_records')
                    ->whereIn('student_id', $chunk)
                    ->select('student_id', 'subject_id', 'subject_name', 'credit', 'total_acload', 'total_point', 'grade', 'finish_credit_status', 'retraining_status', 'semester_id', 'curriculum_id')
                    ->get()
                    ->all());
            }

            $arExistsLookup = [];
            $arLegacyExistsLookup = [];
            $studentSemCurrCounts = [];
            // [hemis_id][semester_code] => curriculum_id (talaba shu semestrda qaysi rejada o'qigan)
            $studentSemCurr = [];
            foreach ($arRecords as $ar) {
                if ($ar->curriculum_id) {
                    $arExistsLookup[$ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id . '|' . $ar->curriculum_id] = true;
                    $studentSemCurrCounts[$ar->student_id][$ar->semester_id][$ar->curriculum_id]
                        = ($studentSemCurrCounts[$ar->student_id][$ar->semester_id][$ar->curriculum_id] ?? 0) + 1;
                } else {
                    // Legacy yozuvlar uchun fallback — eski ma'lumotlarda curriculum_id bo'lmasligi mumkin.
                    $arLegacyExistsLookup[$ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id] = true;
                }
            }
            foreach ($studentSemCurrCounts as $studentId => $semesterRows) {
                $studentCurriculumId = $studentMap[$studentId]->curriculum_id ?? null;
                foreach ($semesterRows as $semesterCode => $curriculumCounts) {
                    $pickedCurriculumId = $this->pickSemesterCurriculumId($curriculumCounts, $studentCurriculumId);
                    if ($pickedCurriculumId) {
                        $studentSemCurr[$studentId][$semesterCode] = $pickedCurriculumId;
                    }
                }
            }

            $studentArBySemSubject = [];
            foreach ($arRecords as $ar) {
                $semesterCode = (string) $ar->semester_id;
                $pickedCurriculumId = $studentSemCurr[$ar->student_id][$semesterCode] ?? null;

                if ($ar->curriculum_id && $pickedCurriculumId !== null && (string) $ar->curriculum_id !== (string) $pickedCurriculumId) {
                    continue;
                }

                $studentArBySemSubject[$ar->student_id][$semesterCode][(string) $ar->subject_id] = $ar;
            }

            unset($arRecords);

            // 3-QADAM: Talabalarning kerakli (curriculum_id, semester_code) juftliklarini yig'amiz.
            //  • O'tgan semestrlar uchun: academic_records'dagi tarixiy curriculum_id
            //  • Joriy semester uchun: students.curriculum_id (mavjud mantiq)
            $curriculumPairs = [];   // ['curr_id|sem' => true]
            foreach ($students as $st) {
                $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
                // O'tgan semestrlar — academic_records'dan
                foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                    $curriculumPairs[$currId . '|' . $semCode] = true;
                }
                // Joriy semester — talabaning hozirgi rejasi (joriy guruh)
                if ($st->curriculum_id && $studentSemCode) {
                    $curriculumPairs[$st->curriculum_id . '|' . $studentSemCode] = true;
                }
            }

            // 4-QADAM: curriculum_subjects'ni shu juftliklar uchun ommaviy yuklash
            $allCurriculumIds = collect($curriculumPairs)->keys()
                ->map(fn ($k) => explode('|', $k)[0])->unique()->values()->all();
            $allSemCodes = collect($curriculumPairs)->keys()
                ->map(fn ($k) => explode('|', $k)[1])->unique()->values()->all();

            $currSubjectsQuery = DB::table('curriculum_subjects as cs')
                ->whereIn('cs.curricula_hemis_id', $allCurriculumIds ?: [0])
                ->whereIn('cs.semester_code', $allSemCodes ?: [0])
                ->where('cs.is_active', 1)
                ->where(function ($q) {
                    // in_group bo'sh yoki NULL bo'lganlar — guruhli fanlar e'tiborga olinmaydi
                    $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
                })
                ->select(
                    'cs.curricula_hemis_id', 'cs.curriculum_subject_hemis_id',
                    'cs.semester_code', 'cs.semester_name',
                    'cs.subject_id', 'cs.subject_name', 'cs.subject_type_code',
                    'cs.credit', 'cs.total_acload'
                )
                ->distinct();

            $excludedPatterns = config('app.excluded_rating_subject_patterns', []);
            foreach ($excludedPatterns as $pattern) {
                $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
            }

            $currSubjects = $currSubjectsQuery->get();

            // (curr_id|sem) bo'yicha guruhlash — tez kirish uchun
            $subjectsByPair = $currSubjects->groupBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->semester_code);

            // 5-QADAM: Tanlov fanlar (subject_type_code=12) uchun talaba haqiqatda
            // qaysi fanni tanlaganini student_subjects'dan olamiz.
            $tanlovCsHemisIds = $currSubjects
                ->where('subject_type_code', '12')
                ->pluck('curriculum_subject_hemis_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $tanlovPicksMap = [];
            if (!empty($tanlovCsHemisIds)) {
                $tanlovPicks = DB::table('student_subjects')
                    ->whereIn('student_hemis_id', $studentHemisIds)
                    ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                    ->select('student_hemis_id', 'curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                    ->get();
                foreach ($tanlovPicks as $tp) {
                    $tanlovPicksMap[$tp->student_hemis_id . '|' . $tp->curriculum_subject_hemis_id] = [
                        'subject_id'   => $tp->subject_id,
                        'subject_name' => $tp->subject_name,
                    ];
                }
            }

            // 5b-QADAM: student_subjects'dan har talaba+semestr uchun biriktirilgan
            // fanlarni yuklash. Bu "Tiklangan" va boshqa alohida toifadagi talabalar
            // uchun qaysi fanlarga haqiqatan biriktirilganini aniqlaydi.
            // JournalController kabi: student_subjects bor bo'lsa — faqat o'sha fanlar,
            // yo'q bo'lsa — curriculum_subjects (barcha talabalar uchun umumiy).
            //
            // $studentSubjectsMap[hemis_id|semester_code] = [subject_id => true, ...]
            // $studentSubjectsHasSemester[hemis_id|semester_code] = bool (biriktirishlar bor yoki yo'q)
            $studentSubjectsMap = [];
            $studentSubjectsHasSemester = [];
            {
                $allPastSemCodes = [];
                foreach ($studentHemisIds as $hId) {
                    foreach ($studentSemCurr[$hId] ?? [] as $sc => $cId) {
                        $allPastSemCodes[$sc] = true;
                    }
                }
                $allPastSemCodes = array_keys($allPastSemCodes);

                if (!empty($allPastSemCodes)) {
                    $ssRows = DB::table('student_subjects')
                        ->whereIn('student_hemis_id', $studentHemisIds)
                        ->whereIn('semester_id', $allPastSemCodes)
                        ->whereNotNull('subject_id')
                        ->select('student_hemis_id', 'semester_id', 'subject_id')
                        ->get();
                    foreach ($ssRows as $sr) {
                        $k = $sr->student_hemis_id . '|' . (string)$sr->semester_id;
                        $studentSubjectsMap[$k][$sr->subject_id] = true;
                        $studentSubjectsHasSemester[$k] = true;
                    }
                }
            }

            // 6-QADAM: Har bir talaba uchun qarzdorlikni hisoblash
            $finalResults = [];

            // Joriy semestr journal-based xavflarini OLDINDAN hisoblaymiz —
            // shunda o'tgan semestrda qarzi bo'lmasa ham, joriy semestrda
            // xavf ostidagi talaba (masalan davomat>=25%) ro'yxatga tushadi.
            // Talabalarning o'z semester kodlari ishlatiladi (semesters.current
            // noto'g'ri bo'lishi mumkin), har talabaning semester_code va hemis_id
            // map sifatida uzatiladi.
            $studentSemCodeMap = $students->pluck('semester_code', 'hemis_id')->filter()->toArray();
            $currentRisksMap = $this->getCurrentSemesterRisksForReport($studentHemisIds, $studentSemCodeMap);

            foreach ($students as $st) {
                if (!$st->curriculum_id) continue;

                $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
                $debts = [];

                // Talabaga tegishli (sem, curriculum) juftliklari ro'yxati
                $studentPairs = []; // [sem_code => curriculum_id]
                if ($showCurrentSemester) {
                    // Faqat joriy semestr — joriy curriculum_id
                    if ($studentSemCode) {
                        $studentPairs[$studentSemCode] = $st->curriculum_id;
                    }
                } else {
                    // Joriy semestrdan oldingi (joriy DAHIL EMAS) — har sem uchun tarixiy curriculum
                    foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                        if (!$studentSemCode || (int) $semCode < $studentSemCode) {
                            $studentPairs[(int) $semCode] = $currId;
                        }
                    }
                }

                foreach ($studentPairs as $semCode => $currId) {
                    $subjectsForSem = $subjectsByPair->get($currId . '|' . $semCode, collect());
                    $subjectsForSem = $this->filterSubjectsByGroupSuffix($subjectsForSem, $st->group_name ?? '');

                    $semesterGrades = $this->buildSemesterAcademicGradeRows(
                        $st->hemis_id,
                        (string) $semCode,
                        $subjectsForSem->values(),
                        collect($studentArBySemSubject[$st->hemis_id][(string) $semCode] ?? []),
                        $tanlovPicksMap
                    );

                    foreach ($semesterGrades as $gradeRow) {
                        if (!$gradeRow->is_debt) {
                            continue;
                        }

                        $debts[] = [
                            'subject_id'    => $gradeRow->subject_id,
                            'subject_name'  => $gradeRow->subject_name,
                            'semester_code' => $gradeRow->semester_code,
                            'semester_name' => $gradeRow->semester_name,
                            'credit'        => $gradeRow->credit,
                            'total_acload'  => $gradeRow->total_acload,
                            'status'        => 'Qarzdor',
                        ];
                    }
                }

                $debtCount = count($debts);
                $currentRisks = $currentRisksMap[$st->hemis_id] ?? [];
                // O'tgan semestr qarzi yetarli BO'LMASA ham, joriy semestrda
                // xavf bo'lsa talabani ko'rsatamiz.
                if ($debtCount < $minDebtCount && empty($currentRisks)) continue;

                // Semestr bo'yicha tartiblash
                usort($debts, fn($a, $b) => $a['semester_code'] <=> $b['semester_code']);

                $finalResults[] = [
                    'hemis_id'          => $st->hemis_id,
                    'full_name'         => $st->full_name ?? 'Noma\'lum',
                    'student_id_number' => $st->student_id_number ?? '-',
                    'department_name'   => $st->department_name ?? '-',
                    'specialty_name'    => $st->specialty_name ?? '-',
                    'level_name'        => $st->level_name ?? '-',
                    'semester_name'     => $st->semester_name ?? '-',
                    'group_name'        => $st->group_name ?? '-',
                    'group_id'          => $st->group_id ?? '',
                    'student_type_name' => $st->student_type_name ?? null,
                    'debt_count'        => $debtCount,
                    'debt_count_curr'   => $debtCount,
                    'debt_count_ss'     => null,
                    'debt_status'       => '',
                    'lesson_days'       => 0,
                    'debts'             => $debts,
                    'current_risks'     => $currentRisks,
                    'current_risk_count' => count($currentRisks),
                ];
            }

            if (empty($finalResults)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
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
     * "Qayta o'qishga ariza topshirmaganlar" hisoboti sahifasi.
     *
     * Talabaning o'quv rejasidagi (tugagan semestrlar) fanlarini academic_records
     * bilan FAN NOMI + KREDIT bo'yicha (aynan — apostrof va kredit ham muhim)
     * solishtiradi:
     *  - Yetmayotgan (bahosi yo'q) fanlar — har biri yoniga qayta o'qishga ariza
     *    bergan/bermaganlik holati yoziladi.
     *  - Ortiqcha (rejada yo'q, academic_records'da bor) fanlar alohida.
     *  - Joriy semestr — "4≥qarzdorlar" mantig'i bo'yicha xavf (potensial yiqilganlar).
     */
    public function retakeNotAppliedReport(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();

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

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if ($dekanFacultyId) {
            $facultyQuery->where('id', $dekanFacultyId);
        }

        $faculties = $facultyQuery->get();

        $studentStatuses = DB::table('students')
            ->select('student_status_code', 'student_status_name')
            ->whereNotNull('student_status_code')
            ->groupBy('student_status_code', 'student_status_name')
            ->orderBy('student_status_name')
            ->get();

        $studentTypes = DB::table('students')
            ->select('student_type_code', 'student_type_name')
            ->whereNotNull('student_type_code')
            ->groupBy('student_type_code', 'student_type_name')
            ->orderBy('student_type_name')
            ->get();

        return view('admin.reports.retake-not-applied', compact(
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'studentStatuses',
            'studentTypes',
            'dekanFacultyId'
        ));
    }

    /**
     * Academic records (HEMIS) importi holati — "Qarzdorlar" sahifasidagi
     * qo'lda yangilash tugmasi va progress paneli uchun. Import jarayoni
     * ImportAcademicRecordsJob orqali fon rejimida ketadi; bu yerdan faqat
     * kesh o'qiladi (behuda qayta yangilamaslik uchun oxirgi yangilangan vaqt ham).
     */
    public function academicRecordsSyncProgress(): \Illuminate\Http\JsonResponse
    {
        $progress = \Illuminate\Support\Facades\Cache::get('academic_import_progress') ?: ['status' => 'idle'];

        // Oxirgi yangilangan vaqt: aniq qiymat — import tugaganda yozilgan kesh.
        // Hali bironta import tugamagan bo'lsa (kesh bo'sh), academic_records
        // jadvalidagi eng so'nggi yozuv vaqtiga (MAX(updated_at)) qaytamiz.
        $lastSynced = \Illuminate\Support\Facades\Cache::get('academic_records_last_synced_at')
            ?? \Illuminate\Support\Facades\Cache::remember(
                'academic_records_last_synced_fallback',
                120,
                fn () => DB::table('academic_records')->max('updated_at')
            );
        $progress['last_synced_at'] = $lastSynced;

        return response()->json($progress);
    }

    /**
     * Academic records (HEMIS) importini qo'lda ishga tushirish (fon rejimida).
     */
    public function startAcademicRecordsSync(): \Illuminate\Http\JsonResponse
    {
        if (\Illuminate\Support\Facades\Cache::get('academic_import_lock')) {
            return response()->json([
                'status' => 'locked',
                'message' => 'Import allaqachon ketayapti. Tugashini kuting.',
            ], 409);
        }

        \Illuminate\Support\Facades\Cache::put('academic_import_progress', [
            'status' => 'queued',
            'percent' => 0,
            'started_at' => now()->toDateTimeString(),
        ], 3600);

        try {
            $userName = optional(auth()->user())->name ?? 'Foydalanuvchi';
            \App\Services\ActivityLogService::log('import', 'academic_record', 'Qarzdorlar sahifasidan academic_records sinxronizatsiyasi boshlandi');
            app(\App\Services\TelegramService::class)->notify("👤 {$userName} tomonidan Qarzdorlar sahifasidan academic_records sinxronizatsiyasi boshlandi");
        } catch (\Throwable $e) {
            // Log/telegram xatosi importni to'xtatmasin.
            \Log::warning('startAcademicRecordsSync notify failed: ' . $e->getMessage());
        }

        \App\Jobs\ImportAcademicRecordsJob::dispatch();

        return response()->json(['status' => 'queued']);
    }

    /**
     * AJAX: Qarzdorlar — talabalarning academic_records yozuvlari (har fan alohida qator).
     *
     * Har bir qator = bitta academic_records yozuvi. "Faqat qarzdorlar" toggle yoqilganda
     * faqat qarz yozuvlar chiqadi. Fan o'zlashtirilgan (qarz emas) hisoblanadi, agar kredit
     * olingan bo'lsa (finish_credit_status = 1, "O'tdi" pass/fail) YOKI o'tish bahosi bo'lsa
     * (baho >= 3). Toggle o'chirilsa talabaning barcha academic_records fanlari ko'rsatiladi.
     */
    public function retakeNotAppliedReportData(Request $request)
    {
        return $this->retakeNotAppliedReportDataCurriculumBased($request);

        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            // Toggle: "only_debtors" (yangi nom). Eski "only_not_applied" ham qabul qilinadi.
            $onlyDebtors = $request->get('only_debtors', $request->get('only_not_applied', '1')) == '1';

            // Asosiy so'rov: academic_records + students (har yozuv = bir qator).
            $query = DB::table('academic_records as ar')
                ->join('students as s', 's.hemis_id', '=', 'ar.student_id')
                ->whereNotNull('s.curriculum_id')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('curriculum_subjects as cs')
                        ->whereColumn('cs.curricula_hemis_id', 'ar.curriculum_id')
                        ->whereColumn('cs.semester_code', 'ar.semester_id')
                        ->whereColumn('cs.subject_id', 'ar.subject_id')
                        ->where('cs.is_active', 1)
                        ->where(function ($w) {
                            $w->whereNull('cs.in_group')->orWhere('cs.in_group', '');
                        });
                });

            // ── Talaba filtrlari ─────────────────────────────────────────
            if ($request->filled('student_status')) {
                $query->where('s.student_status_code', $request->student_status);
            }
            if ($request->filled('student_name')) {
                $query->where('s.full_name', 'like', '%' . $request->student_name . '%');
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
                $group = \App\Models\Group::find($request->group);
                if ($group) {
                    $query->where('s.group_id', $group->group_hemis_id);
                }
            }
            if ($request->filled('education_type')) {
                $query->where('s.education_type_code', $request->education_type);
            }
            if ($request->filled('student_type')) {
                $query->where('s.student_type_code', $request->student_type);
            }
            // Semestr filtri — bu yerda YOZUV semestriga (academic_records.semester_id) qo'llanadi.
            if ($request->filled('semester_code')) {
                $query->where('ar.semester_id', $request->semester_code);
            }

            if (is_active_nazoratchi()) {
                $nazoratchiHemisIds = get_nazoratchi_group_hemis_ids();
                if (empty($nazoratchiHemisIds)) {
                    return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
                }
                $query->whereIn('s.group_id', $nazoratchiHemisIds);
            }

            // ── Qarzdorlik filtri ────────────────────────────────────────
            //  Fan O'ZLASHTIRILGAN (qarz EMAS) hisoblanadi, agar QUYIDAGILARDAN biri bo'lsa:
            //    (a) finish_credit_status = 1  — kredit olingan "O'tdi" pass/fail fan
            //        (baho 0.00 bo'lsa ham, masalan "Odam anatomiyasi"), YOKI
            //    (b) o'tish bahosi bor — baho numerik va >= 3 (masalan 3.00, 4.00),
            //        yoki matnli o'tish bahosi (numerik bo'lmagan).
            //  Qarzdor = ikkalasi ham yo'q: kredit olinmagan VA (baho bo'sh yoki numerik < 3).
            if ($onlyDebtors) {
                $query
                    ->where(function ($q) {
                        $q->where('ar.finish_credit_status', '!=', 1)
                            ->orWhereNull('ar.finish_credit_status');
                    })
                    ->where(function ($q) {
                        $q->whereNull('ar.grade')
                            ->orWhere('ar.grade', '=', '')
                            ->orWhereRaw("(ar.grade REGEXP '^[0-9]+([.][0-9]+)?\$' AND CAST(ar.grade AS DECIMAL(10,2)) < 3)");
                    });
            }

            $total = (clone $query)->count();
            $perPage = (int) $request->get('per_page', 50);
            if ($perPage < 1) {
                $perPage = 50;
            }

            if ($total === 0) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'last_page' => 1]);
            }

            // ── Saralash ─────────────────────────────────────────────────
            $sortMap = [
                'full_name'       => 's.full_name',
                'department_name' => 's.department_name',
                'specialty_name'  => 's.specialty_name',
                'level_name'      => 's.level_name',
                'group_name'      => 's.group_name',
                'subject_name'    => 'ar.subject_name',
                'semester_name'   => 'ar.semester_id',
                'total_point'     => 'ar.total_point',
                'grade'           => 'ar.grade',
            ];
            $sortColumn = $sortMap[$request->get('sort')] ?? 's.full_name';
            $sortDirection = strtolower($request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

            $page = max(1, (int) $request->get('page', 1));
            $offset = ($page - 1) * $perPage;

            $rows = (clone $query)
                ->select(
                    'ar.id as ar_id', 'ar.student_id', 'ar.subject_id', 'ar.subject_name',
                    'ar.semester_id', 'ar.semester_name as ar_semester_name', 'ar.credit',
                    'ar.total_acload', 'ar.total_point', 'ar.grade', 'ar.retraining_status',
                    'ar.finish_credit_status', 'ar.curriculum_id as ar_curriculum_id',
                    's.full_name', 's.student_id_number', 's.department_name', 's.specialty_name',
                    's.level_name', 's.group_name'
                )
                ->orderBy($sortColumn, $sortDirection)
                ->orderBy('s.full_name', 'asc')
                ->orderBy('ar.semester_id', 'asc')
                ->orderBy('ar.id', 'asc')
                ->offset($offset)->limit($perPage)
                ->get();

            // ── Boyitish (faqat joriy sahifa qatorlari uchun) ────────────
            $curriculumIds = $rows->pluck('ar_curriculum_id')->filter()->unique()->values()->all();
            $subjectIds    = $rows->pluck('subject_id')->filter()->unique()->values()->all();
            $semIds        = $rows->pluck('semester_id')->filter()->unique()->values()->all();
            $studentIds    = $rows->pluck('student_id')->filter()->unique()->values()->all();

            // 1) Yopilish shakli — curriculum_subjects.closing_form
            $closingFormMap = [];
            if (!empty($curriculumIds) && !empty($subjectIds)) {
                $cf = DB::table('curriculum_subjects')
                    ->whereIn('curricula_hemis_id', $curriculumIds)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereIn('semester_code', $semIds)
                    ->select('curricula_hemis_id', 'subject_id', 'semester_code', 'closing_form')
                    ->get();
                foreach ($cf as $c) {
                    $k = $c->curricula_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code;
                    if (!isset($closingFormMap[$k]) && $c->closing_form) {
                        $closingFormMap[$k] = $c->closing_form;
                    }
                }
            }

            // 2) Qayta o'qishga ariza holati — retake_applications (+ to'lov holati)
            $retakeMap = [];
            if (!empty($studentIds) && !empty($subjectIds)) {
                $apps = \App\Models\RetakeApplication::query()
                    ->whereIn('student_hemis_id', $studentIds)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereIn('final_status', [
                        \App\Models\RetakeApplication::STATUS_PENDING,
                        \App\Models\RetakeApplication::STATUS_APPROVED,
                    ])
                    ->get(['group_id', 'student_hemis_id', 'subject_id', 'semester_id',
                        'final_status', 'dean_status', 'registrar_status', 'retake_group_id']);

                $groupIds = $apps->pluck('group_id')->filter()->unique()->values()->all();
                $groups = collect();
                if (!empty($groupIds)) {
                    $groups = \App\Models\RetakeApplicationGroup::whereIn('id', $groupIds)
                        ->get(['id', 'payment_uploaded_at', 'payment_verification_status'])
                        ->keyBy('id');
                }

                // Holatlar ustunligi (kuchliroq holat kuchsizini almashtiradi).
                $priority = [
                    'Ariza bermagan'      => 0,
                    "Ko'rib chiqilmoqda"  => 1,
                    "To'lovini qilmagan"  => 2,
                    "To'lov tekshirilmoqda" => 3,
                    "To'lov tasdiqlandi"  => 4,
                    'Guruhga tasdiqlangan' => 5,
                ];
                foreach ($apps as $app) {
                    $k = $app->student_hemis_id . '|' . $app->subject_id . '|' . $app->semester_id;
                    $label = $this->retakeApplicationStatusLabel($app, $groups->get($app->group_id));
                    if (!isset($retakeMap[$k]) || ($priority[$label] ?? 0) > ($priority[$retakeMap[$k]] ?? 0)) {
                        $retakeMap[$k] = $label;
                    }
                }
            }

            $cfLabels = [
                'oski'      => 'Faqat OSKI',
                'test'      => 'Faqat Test',
                'oski_test' => 'OSKI + Test',
                'normativ'  => 'Normativ',
                'sinov'     => 'Sinov',
                'none'      => "Yakuniy nazorat yo'q",
            ];

            $data = [];
            $rowNum = $offset;
            foreach ($rows as $r) {
                $rowNum++;

                $cfKey  = $r->ar_curriculum_id . '|' . $r->subject_id . '|' . $r->semester_id;
                $cfCode = $closingFormMap[$cfKey] ?? null;

                $rk = $r->student_id . '|' . $r->subject_id . '|' . $r->semester_id;
                $retakeStatus = $retakeMap[$rk] ?? 'Ariza bermagan';

                $study = $this->academicRecordStudyStatus($r);

                $data[] = [
                    'row_num'           => $rowNum,
                    'hemis_id'          => $r->student_id,
                    'full_name'         => $r->full_name ?? '-',
                    'student_id_number' => $r->student_id_number ?? '-',
                    'department_name'   => $r->department_name ?? '-',
                    'specialty_name'    => $r->specialty_name ?? '-',
                    'level_name'        => $r->level_name ?? '-',
                    'group_name'        => $r->group_name ?? '-',
                    'subject_name'      => $r->subject_name ?? '-',
                    'closing_form'      => $cfCode ? ($cfLabels[$cfCode] ?? $cfCode) : '-',
                    'semester_code'     => $r->semester_id,
                    'semester_name'     => $r->ar_semester_name ?: ($r->semester_id ? $r->semester_id . '-semestr' : '-'),
                    'total_acload'      => $r->total_acload,
                    'credit'            => $r->credit,
                    'total_point'       => ($r->total_point === null || $r->total_point === '') ? null : $r->total_point,
                    'grade'             => ($r->grade === null || $r->grade === '') ? null : $r->grade,
                    'retake_status'     => $retakeStatus,
                    'study_status'      => $study['label'],
                    'study_status_code' => $study['code'],
                    'is_debt'           => $study['code'] !== 'passed',
                ];
            }

            return response()->json([
                'data'         => $data,
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Retake-not-applied report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * calc_key prefiksi uchun foydalanuvchi identifikatori — admin (web) yoki
     * teacher guard. Boshqa foydalanuvchining hisob natijasini o'qishni bloklaydi.
     */
    private function retakeNotAppliedCalcUserToken(): string
    {
        if (auth()->check()) {
            return 'u' . auth()->id();
        }
        if (auth('teacher')->check()) {
            return 't' . auth('teacher')->id();
        }
        return 'g0';
    }

    /**
     * Hisobot filtrlari (Request → massiv). Sessiyaga bog'liq kontekst (dekan
     * fakulteti chaqiruvchi tomonda request'ga merge qilinadi, nazoratchi
     * guruhlari shu yerda) request vaqtida hal qilinadi — massiv fon jobiga
     * o'zgarishsiz uzatiladi.
     */
    private function buildRetakeNotAppliedFilters(Request $request): array
    {
        $filters = [
            'only_debtors' => $request->get('only_debtors', $request->get('only_not_applied', '1')) == '1' ? '1' : '0',
            'semester_code' => (string) ($request->get('semester_code') ?? ''),
            // Academic-record qarzida joriy semestrni ham hisoblash (default: o'chiq).
            'include_current_semester' => $request->get('include_current_semester', '0') == '1' ? '1' : '0',
            'student_status' => (string) ($request->get('student_status') ?? ''),
            'student_name' => (string) ($request->get('student_name') ?? ''),
            'faculty' => (string) ($request->get('faculty') ?? ''),
            'specialty' => (string) ($request->get('specialty') ?? ''),
            'level_code' => (string) ($request->get('level_code') ?? ''),
            'group' => (string) ($request->get('group') ?? ''),
            'education_type' => (string) ($request->get('education_type') ?? ''),
            'student_type' => (string) ($request->get('student_type') ?? ''),
        ];

        if (is_active_nazoratchi()) {
            $filters['nazoratchi_group_ids'] = get_nazoratchi_group_hemis_ids();
        }

        return $filters;
    }

    /**
     * Hisobot qatorlarini to'liq (sahifalanmagan) hisoblaydi. Request/sessiyaga
     * bog'liq emas — fon jobidan (ComputeRetakeNotAppliedReportJob) ham, sinxron
     * yo'ldan ham chaqiriladi. $progress(percent, message) — jarayon holati.
     */
    public function computeRetakeNotAppliedRows(array $filters, ?callable $progress = null): array
    {
            $tick = static function (float $percent, string $message) use ($progress) {
                if ($progress) {
                    $progress(min(99, round($percent, 1)), $message);
                }
            };

            $onlyDebtors = ($filters['only_debtors'] ?? '1') == '1';
            $semesterFilter = $filters['semester_code'] ?? '';
            // Toggle: academic_records qarzida joriy semestrni ham tekshirish.
            // O'chiq (default) — faqat tugallangan (o'tgan) semestrlar.
            $includeCurrentSemester = ($filters['include_current_semester'] ?? '0') == '1';

            $tick(2, 'Talabalar yuklanmoqda...');

            $studentQuery = DB::table('students as s')
                ->whereNotNull('s.curriculum_id')
                ->select(
                    's.hemis_id',
                    's.full_name',
                    's.student_id_number',
                    's.department_name',
                    's.specialty_name',
                    's.level_name',
                    's.level_code',
                    's.semester_name',
                    's.semester_code',
                    's.group_name',
                    's.group_id',
                    's.curriculum_id',
                    's.student_type_code',
                    's.student_type_name'
                );

            if (!empty($filters['student_status'])) {
                $studentQuery->where('s.student_status_code', $filters['student_status']);
            }
            if (!empty($filters['student_name'])) {
                $studentQuery->where('s.full_name', 'like', '%' . $filters['student_name'] . '%');
            }
            if (!empty($filters['faculty'])) {
                $faculty = Department::find($filters['faculty']);
                if ($faculty) {
                    $studentQuery->where('s.department_id', $faculty->department_hemis_id);
                }
            }
            if (!empty($filters['specialty'])) {
                $studentQuery->where('s.specialty_id', $filters['specialty']);
            }
            if (!empty($filters['level_code'])) {
                $studentQuery->where('s.level_code', $filters['level_code']);
            }
            if (!empty($filters['group'])) {
                $group = \App\Models\Group::find($filters['group']);
                if ($group) {
                    $studentQuery->where('s.group_id', $group->group_hemis_id);
                }
            }
            if (!empty($filters['education_type'])) {
                $studentQuery->where('s.education_type_code', $filters['education_type']);
            }
            if (!empty($filters['student_type'])) {
                $studentQuery->where('s.student_type_code', $filters['student_type']);
            }

            // Nazoratchi cheklovi — sessiya emas, request vaqtida hal qilinib massivda keladi.
            if (array_key_exists('nazoratchi_group_ids', $filters)) {
                if (empty($filters['nazoratchi_group_ids'])) {
                    return [];
                }
                $studentQuery->whereIn('s.group_id', $filters['nazoratchi_group_ids']);
            }

            $students = $studentQuery->get();
            if ($students->isEmpty()) {
                return [];
            }

            $tick(8, 'Academic records yuklanmoqda...');

            $studentHemisIds = $students->pluck('hemis_id')->values()->all();
            // Oddiy massiv — Collection::merge har chunkda butun massivni nusxalab
            // katta to'plamda (butun universitet) xotira sakrashiga olib keladi.
            $arRows = [];
            foreach (array_chunk($studentHemisIds, 1000) as $chunk) {
                $chunkRows = DB::table('academic_records')
                    ->whereIn('student_id', $chunk)
                    ->select(
                        'student_id',
                        'subject_id',
                        'subject_name',
                        'semester_id',
                        'semester_name',
                        'curriculum_id',
                        'credit',
                        'total_acload',
                        'total_point',
                        'grade',
                        'retraining_status',
                        'finish_credit_status'
                    )
                    ->get();
                foreach ($chunkRows as $arRow) {
                    $arRows[] = $arRow;
                }
                unset($chunkRows);
            }

            $studentMap = $students->keyBy('hemis_id');
            $studentSemCurr = [];
            $studentSemCurrCounts = [];
            $arByStudentSemSubject = [];
            $arLegacyByStudentSemSubject = [];

            foreach ($arRows as $ar) {
                $semCode = (string) $ar->semester_id;
                if ($ar->curriculum_id) {
                    $studentSemCurrCounts[$ar->student_id][$semCode][$ar->curriculum_id]
                        = ($studentSemCurrCounts[$ar->student_id][$semCode][$ar->curriculum_id] ?? 0) + 1;
                } else {
                    $arLegacyByStudentSemSubject[$ar->student_id][$semCode][(string) $ar->subject_id] = $ar;
                }
            }

            foreach ($studentSemCurrCounts as $studentId => $semesterRows) {
                $studentCurriculumId = $studentMap[$studentId]->curriculum_id ?? null;
                foreach ($semesterRows as $semCode => $curriculumCounts) {
                    $pickedCurriculumId = $this->pickSemesterCurriculumId($curriculumCounts, $studentCurriculumId);
                    if ($pickedCurriculumId) {
                        $studentSemCurr[$studentId][$semCode] = $pickedCurriculumId;
                    }
                }
            }

            foreach ($arRows as $ar) {
                $semCode = (string) $ar->semester_id;
                $pickedCurriculumId = $studentSemCurr[$ar->student_id][$semCode] ?? null;
                if ($ar->curriculum_id && $pickedCurriculumId !== null && (string) $ar->curriculum_id !== (string) $pickedCurriculumId) {
                    continue;
                }
                $arByStudentSemSubject[$ar->student_id][$semCode][(string) $ar->subject_id] = $ar;
            }

            $curriculumPairs = [];
            foreach ($students as $st) {
                $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
                foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                    if ($semesterFilter !== null && $semesterFilter !== '' && (string) $semCode !== (string) $semesterFilter) {
                        continue;
                    }
                    // Joriy semestr faqat include_current_semester yoqilganda
                    // hisoblanadi; kelasi semestrlar hech qachon. Bu semester_code
                    // filtri berilganda ham qo'llanadi — aks holda joriy semestrni
                    // filter qilib tanlaganda toggle o'chiq bo'lsa ham chiqib qolardi.
                    if ($studentSemCode
                        && ($includeCurrentSemester ? (int) $semCode > $studentSemCode : (int) $semCode >= $studentSemCode)) {
                        continue;
                    }
                    $curriculumPairs[$currId . '|' . $semCode] = true;
                }
            }

            if (empty($curriculumPairs)) {
                return [];
            }

            $tick(30, "O'quv reja fanlari yuklanmoqda...");

            $allCurriculumIds = collect(array_keys($curriculumPairs))
                ->map(fn ($k) => explode('|', $k)[0])
                ->unique()
                ->values()
                ->all();
            $allSemCodes = collect(array_keys($curriculumPairs))
                ->map(fn ($k) => explode('|', $k)[1])
                ->unique()
                ->values()
                ->all();

            $currSubjectsQuery = DB::table('curriculum_subjects as cs')
                ->whereIn('cs.curricula_hemis_id', $allCurriculumIds ?: [0])
                ->whereIn('cs.semester_code', $allSemCodes ?: [0])
                ->where('cs.is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
                })
                ->select(
                    'cs.curricula_hemis_id',
                    'cs.curriculum_subject_hemis_id',
                    'cs.semester_code',
                    'cs.semester_name',
                    'cs.subject_id',
                    'cs.subject_name',
                    'cs.subject_type_code',
                    'cs.credit',
                    'cs.total_acload',
                    'cs.closing_form'
                )
                ->distinct();

            foreach (config('app.excluded_rating_subject_patterns', []) as $pattern) {
                $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
            }

            $subjectsByPair = $currSubjectsQuery->get()
                ->groupBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->semester_code);

            $tanlovCsHemisIds = $subjectsByPair
                ->flatten(1)
                ->where('subject_type_code', '12')
                ->pluck('curriculum_subject_hemis_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $tanlovPicksMap = [];
            if (!empty($tanlovCsHemisIds)) {
                $tanlovPicks = DB::table('student_subjects')
                    ->whereIn('student_hemis_id', $studentHemisIds)
                    ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                    ->select('student_hemis_id', 'curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                    ->get();

                foreach ($tanlovPicks as $tp) {
                    $tanlovPicksMap[$tp->student_hemis_id . '|' . $tp->curriculum_subject_hemis_id] = [
                        'subject_id' => $tp->subject_id,
                        'subject_name' => $tp->subject_name,
                    ];
                }
            }

            $tick(45, "Qayta o'qish arizalari yuklanmoqda...");

            $retakeApps = \App\Models\RetakeApplication::query()
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('final_status', [
                    \App\Models\RetakeApplication::STATUS_PENDING,
                    \App\Models\RetakeApplication::STATUS_APPROVED,
                ])
                ->get([
                    'id',
                    'group_id',
                    'student_hemis_id',
                    'subject_id',
                    'semester_id',
                    'final_status',
                    'dean_status',
                    'registrar_status',
                    'retake_group_id',
                    'joriy_score',
                    'joriy_graded_by_name',
                    'joriy_graded_at',
                    'oske_score',
                    'test_score',
                    'final_grade_value',
                    'final_grade_set_at',
                ]);

            $retakeGroupIds = $retakeApps->pluck('group_id')->filter()->unique()->values()->all();
            $retakeGroups = empty($retakeGroupIds)
                ? collect()
                : \App\Models\RetakeApplicationGroup::whereIn('id', $retakeGroupIds)
                    ->get(['id', 'payment_uploaded_at', 'payment_verification_status'])
                    ->keyBy('id');

            // Test markazi guruhlari (RetakeGroup) — yopilish shakli (assessment_type)
            // va yakuniy natija (xulosa) hisoblash uchun retake_group_id bo'yicha.
            $retakeGroupModelIds = $retakeApps->pluck('retake_group_id')->filter()->unique()->values()->all();
            $retakeGroupsById = empty($retakeGroupModelIds)
                ? collect()
                : \App\Models\RetakeGroup::whereIn('id', $retakeGroupModelIds)
                    ->get(['id', 'assessment_type'])
                    ->keyBy('id');

            $retakeJournalService = app(\App\Services\Retake\RetakeJournalService::class);

            // Appelyatsiyada o'chirilgan test baholari soni — "o'qish holati"da
            // urinishlar sonini "(N)" ko'rinishida ko'rsatish uchun (test markazi
            // jurnali bilan bir xil: urinishlar jami = o'chirilgan + 1).
            $removedCountMap = $retakeJournalService->removedAppealCounts($retakeApps);

            // Test markazi yopilish shakli (assessment_type) → yorliq.
            $assessmentTypeLabels = [
                'oske' => 'OSKE',
                'test' => 'Test',
                'oske_test' => 'OSKE + Test',
                'sinov' => 'Sinov',
                'sinov_fan' => 'Sinov',
            ];

            $mustaqilMap = empty($retakeApps->pluck('id')->all())
                ? collect()
                : \App\Models\RetakeMustaqilSubmission::query()
                    ->whereIn('application_id', $retakeApps->pluck('id')->all())
                    ->get(['application_id', 'grade', 'graded_by_name', 'graded_at'])
                    ->keyBy('application_id');

            $retakeMap = [];
            $retakeAppDataMap = [];
            $retakePriority = [
                'Ariza bermagan' => 0,
                "Ko'rib chiqilmoqda" => 1,
                "To'lovini qilmagan" => 2,
                "To'lov tekshirilmoqda" => 3,
                "To'lov tasdiqlandi" => 4,
                'Guruhga tasdiqlangan' => 5,
            ];
            foreach ($retakeApps as $app) {
                $key = $app->student_hemis_id . '|' . $app->subject_id . '|' . $app->semester_id;
                $label = $this->retakeApplicationStatusLabel($app, $retakeGroups->get($app->group_id));
                if (!isset($retakeMap[$key]) || ($retakePriority[$label] ?? 0) > ($retakePriority[$retakeMap[$key]] ?? 0)) {
                    $retakeMap[$key] = $label;
                    $retakeAppDataMap[$key] = $app;
                }
            }

            $cfLabels = [
                'oski' => 'Faqat OSKI',
                'test' => 'Faqat Test',
                'oski_test' => 'OSKI + Test',
                'normativ' => 'Normativ',
                'sinov' => 'Sinov',
                'none' => "Yakuniy nazorat yo'q",
            ];

            // Joriy semestr XAVFLARI (student_grades jurnali) hisobotdan OLIB
            // TASHLANDI: og'ir bo'lib timeout berardi. Hisobot faqat
            // academic_records qarzlari bilan cheklanadi; joriy semestr
            // academic_records qarzi include_current_semester toggle orqali
            // asosiy siklda hisoblanadi.
            $tick(60, 'Qatorlar shakllantirilmoqda...');

            $data = [];
            $processedStudents = 0;
            $totalStudents = max(1, $students->count());
            foreach ($students as $st) {
                $processedStudents++;
                if ($processedStudents % 500 === 0) {
                    $tick(60 + 37 * $processedStudents / $totalStudents, "Qatorlar shakllantirilmoqda: {$processedStudents}/{$totalStudents} talaba...");
                }

                // Akademik mobillik: boshqa OTMdan kelgan talaba — bizda YOZUVI
                // BO'LMAGAN oldingi (o'quv yili/semestr) fanlari qarz emas, ularni
                // o'z OTMiga borib yopadi. Bizda mavjud yozuv (masalan yiqilgan)
                // esa qarzligicha qoladi.
                $isMobility = str_contains(mb_strtolower((string) ($st->student_type_name ?? '')), 'mobil');

                $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
                foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                    if ($semesterFilter !== null && $semesterFilter !== '' && (string) $semCode !== (string) $semesterFilter) {
                        continue;
                    }
                    // Joriy semestr faqat include_current_semester yoqilganda
                    // hisoblanadi; kelasi semestrlar hech qachon. Bu semester_code
                    // filtri berilganda ham qo'llanadi — aks holda joriy semestrni
                    // filter qilib tanlaganda toggle o'chiq bo'lsa ham chiqib qolardi.
                    if ($studentSemCode
                        && ($includeCurrentSemester ? (int) $semCode > $studentSemCode : (int) $semCode >= $studentSemCode)) {
                        continue;
                    }

                    $subjectsForSem = $subjectsByPair->get($currId . '|' . $semCode, collect());
                    $subjectsForSem = $this->filterSubjectsByGroupSuffix($subjectsForSem, $st->group_name ?? '');

                    foreach ($subjectsForSem as $sub) {
                        $resolvedSubject = $this->resolveAcademicDebtSubjectForStudent($st->hemis_id, $sub, $tanlovPicksMap);
                        if ($resolvedSubject === null) {
                            continue;
                        }

                        $effectiveSubjectId = $resolvedSubject['subject_id'];
                        $effectiveSubjectName = $resolvedSubject['subject_name'];

                        $matchedAr = $arByStudentSemSubject[$st->hemis_id][(string) $semCode][(string) $effectiveSubjectId]
                            ?? $arLegacyByStudentSemSubject[$st->hemis_id][(string) $semCode][(string) $effectiveSubjectId]
                            ?? null;
                        $study = $matchedAr
                            ? $this->academicRecordStudyStatus($matchedAr)
                            : ['code' => 'not_graded', 'label' => "Yozuv yo'q"];

                        // Qarz aniqlash (academicRecordStudyStatus bilan izchil):
                        //  - Yozuv bor: "passed" bo'lmasa qarz (baho < 3 — jumladan 1 —, yiqilgan,
                        //    baholanmagan). Kredit olingan (finish_credit_status) yoki baho >= 3 /
                        //    matnli baho bo'lsa "passed" — qarz emas.
                        //  - Yozuv yo'q: faqat talabaning JORIY curriculumi uchun qarz. Tiklangan
                        //    talabalarda eski/boshqa curriculum semestri tanlangan bo'lsa, undagi
                        //    "yozuv yo'q" fanlar false qarz bo'lib chiqmasligi uchun qarz sanalmaydi
                        //    (eski curriculumda faqat haqiqiy yiqilgan yozuv qarz bo'ladi).
                        $isCurrentCurriculum = (string) $currId === (string) ($st->curriculum_id ?? '');
                        $isDebt = $matchedAr
                            ? ($study['code'] ?? '') !== 'passed'
                            : ($isCurrentCurriculum && !$isMobility);
                        if ($onlyDebtors && !$isDebt) {
                            continue;
                        }

                        $retakeKey = $st->hemis_id . '|' . $effectiveSubjectId . '|' . $semCode;
                        $retakeApp = $retakeAppDataMap[$retakeKey] ?? null;
                        $mustaqil = $retakeApp ? ($mustaqilMap->get($retakeApp->id) ?? null) : null;
                        $scoreDetails = [];
                        if ($retakeApp) {
                            if ($retakeApp->joriy_score !== null) {
                                $scoreDetails[] = [
                                    'type' => 'JN',
                                    'score' => (float) $retakeApp->joriy_score,
                                    'teacher' => $retakeApp->joriy_graded_by_name ?: null,
                                    'date' => $retakeApp->joriy_graded_at ? \Carbon\Carbon::parse($retakeApp->joriy_graded_at)->format('d.m.Y H:i') : null,
                                ];
                            }
                            if ($mustaqil && $mustaqil->grade !== null) {
                                $scoreDetails[] = [
                                    'type' => 'MT',
                                    'score' => (float) $mustaqil->grade,
                                    'teacher' => $mustaqil->graded_by_name ?: null,
                                    'date' => $mustaqil->graded_at ? \Carbon\Carbon::parse($mustaqil->graded_at)->format('d.m.Y H:i') : null,
                                ];
                            }
                            if ($retakeApp->oske_score !== null) {
                                $scoreDetails[] = [
                                    'type' => 'OSKI',
                                    'score' => (float) $retakeApp->oske_score,
                                    'teacher' => null,
                                    'date' => $retakeApp->final_grade_set_at ? \Carbon\Carbon::parse($retakeApp->final_grade_set_at)->format('d.m.Y H:i') : null,
                                ];
                            }
                            if ($retakeApp->test_score !== null) {
                                $scoreDetails[] = [
                                    'type' => 'TEST',
                                    'score' => (float) $retakeApp->test_score,
                                    'teacher' => null,
                                    'date' => $retakeApp->final_grade_set_at ? \Carbon\Carbon::parse($retakeApp->final_grade_set_at)->format('d.m.Y H:i') : null,
                                ];
                            }
                        }

                        $retakeStatus = $retakeMap[$retakeKey] ?? 'Ariza bermagan';
                        $retakeGroupModel = ($retakeApp && $retakeApp->retake_group_id)
                            ? $retakeGroupsById->get($retakeApp->retake_group_id)
                            : null;

                        // Yopilish shakli: qayta o'qishga "Guruhga tasdiqlangan" bo'lsa,
                        // o'quv reja fanidagi shakl o'rniga test markazi guruhining
                        // yopilish shakli (assessment_type) ko'rsatiladi.
                        $closingForm = $sub->closing_form ? ($cfLabels[$sub->closing_form] ?? $sub->closing_form) : '-';
                        if ($retakeStatus === 'Guruhga tasdiqlangan' && $retakeGroupModel && $retakeGroupModel->assessment_type) {
                            $closingForm = $assessmentTypeLabels[$retakeGroupModel->assessment_type]
                                ?? $retakeGroupModel->assessment_type;
                        }

                        // O'qish holati: test markazi guruhi mavjud bo'lsa, jurnal yakuniy
                        // natijasining xulosasi (yiqildi / imtihonga kelmagan / o'qituvchi
                        // bahosini qo'ymagan / o'zlashtirdi) ko'rsatiladi. Aks holda academic
                        // records asosidagi holat qoladi.
                        $displayStudy = $study;
                        if ($retakeGroupModel && $retakeGroupModel->assessment_type) {
                            $at = $retakeGroupModel->assessment_type;
                            $isSinov = in_array($at, ['sinov', 'sinov_fan'], true);
                            $effTest = $isSinov ? $retakeApp->joriy_score : $retakeApp->test_score;
                            $final = $retakeJournalService->testMarkaziFinalResult(
                                $retakeApp->joriy_score,
                                $mustaqil?->grade,
                                $retakeApp->oske_score,
                                $effTest,
                                $at,
                                $st->level_code ?? null,
                            );
                            $displayStudy = $this->testMarkaziStudyStatus($final, $study, $removedCountMap[$retakeApp->id] ?? 0);
                        }

                        $data[] = [
                            'hemis_id' => $st->hemis_id,
                            'full_name' => $st->full_name ?? '-',
                            'student_id_number' => $st->student_id_number ?? '-',
                            'department_name' => $st->department_name ?? '-',
                            'specialty_name' => $st->specialty_name ?? '-',
                            'level_name' => $st->level_name ?? '-',
                            'group_name' => $st->group_name ?? '-',
                            'subject_name' => $effectiveSubjectName ?? '-',
                            'closing_form' => $closingForm,
                            'semester_code' => $sub->semester_code,
                            'semester_name' => $sub->semester_name ?: ($sub->semester_code ? $sub->semester_code . '-semestr' : '-'),
                            'total_acload' => $matchedAr->total_acload ?? $sub->total_acload,
                            'credit' => $matchedAr->credit ?? $sub->credit,
                            'total_point' => ($matchedAr && $matchedAr->total_point !== '' ? $matchedAr->total_point : null),
                            'grade' => ($matchedAr && $matchedAr->grade !== '' ? $matchedAr->grade : null),
                            'mastered' => $matchedAr ? (bool) ($matchedAr->finish_credit_status ?? false) : null,
                            'retake_status' => $retakeStatus,
                            'study_status' => $displayStudy['label'],
                            'study_status_code' => $displayStudy['code'],
                            'is_debt' => $isDebt,
                            'score_details' => $scoreDetails,
                            'has_score_details' => !empty($scoreDetails),
                        ];
                    }
                }
            }

            $tick(99, 'Yakunlanmoqda...');

            return $data;
    }

    /**
     * AJAX ma'lumot endpointi — calc_key berilsa fon jobi natijasidan o'qiydi,
     * bo'lmasa sinxron hisoblaydi; so'ng holat filtri, saralash, Excel eksport
     * va sahifalash qo'llanadi.
     */
    private function retakeNotAppliedRespond(Request $request, array $data, string $retakeStatusFilter, int $perPage)
    {
            if ($retakeStatusFilter !== '') {
                $data = array_values(array_filter($data, function ($row) use ($retakeStatusFilter) {
                    $status = (string) ($row['retake_status'] ?? '');

                    return match ($retakeStatusFilter) {
                        'no_application' => $status === 'Ariza bermagan',
                        'group_assigned' => $status === 'Guruhga tasdiqlangan',
                        default => true,
                    };
                }));
            }

            $sortMap = [
                'full_name' => 'full_name',
                'department_name' => 'department_name',
                'specialty_name' => 'specialty_name',
                'level_name' => 'level_name',
                'group_name' => 'group_name',
                'subject_name' => 'subject_name',
                'semester_name' => 'semester_code',
                'total_point' => 'total_point',
                'grade' => 'grade',
            ];
            $sortField = $sortMap[$request->get('sort')] ?? 'full_name';
            $sortDirection = strtolower($request->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

            usort($data, function ($a, $b) use ($sortField, $sortDirection) {
                $av = $a[$sortField] ?? null;
                $bv = $b[$sortField] ?? null;

                if (in_array($sortField, ['semester_code', 'total_point', 'grade'], true)) {
                    $av = is_numeric($av) ? (float) $av : -INF;
                    $bv = is_numeric($bv) ? (float) $bv : -INF;
                } else {
                    $av = mb_strtolower((string) $av);
                    $bv = mb_strtolower((string) $bv);
                }

                $cmp = $av <=> $bv;
                if ($cmp === 0) {
                    $cmp = mb_strtolower((string) ($a['full_name'] ?? '')) <=> mb_strtolower((string) ($b['full_name'] ?? ''));
                }

                return $sortDirection === 'desc' ? -$cmp : $cmp;
            });

            if ($request->get('export') === 'excel') {
                $exportRows = $this->mapRetakeNotAppliedExportRows($data);

                return \Maatwebsite\Excel\Facades\Excel::download(
                    new class($exportRows) implements
                        \Maatwebsite\Excel\Concerns\FromArray,
                        \Maatwebsite\Excel\Concerns\WithHeadings,
                        \Maatwebsite\Excel\Concerns\WithStyles,
                        \Maatwebsite\Excel\Concerns\ShouldAutoSize
                    {
                        public function __construct(private array $rows) {}

                        public function array(): array
                        {
                            return $this->rows;
                        }

                        public function headings(): array
                        {
                            return [
                                'Talaba FISH',
                                'Talaba ID',
                                'Fakultet',
                                "Yo'nalish",
                                'Kurs',
                                'Guruh',
                                'Fan',
                                'Yopilish shakli',
                                'Semestr',
                                'Soat',
                                'Kredit',
                                'JN',
                                'MT',
                                'OSKI',
                                'TEST',
                                "Olgan bahosi",
                                "O'zlashtirdi",
                                "Qayta o'qish holati",
                                "O'qish holati",
                            ];
                        }

                        public function styles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): array
                        {
                            return [
                                1 => [
                                    'font' => ['bold' => true],
                                    'fill' => [
                                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                                        'startColor' => ['rgb' => 'DBEAFE'],
                                    ],
                                ],
                            ];
                        }
                    },
                    'academic-records-qarzdorlar.xlsx'
                );
            }

            $total = count($data);
            if ($total === 0) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'last_page' => 1]);
            }

            $page = max(1, (int) $request->get('page', 1));
            $offset = ($page - 1) * $perPage;
            $pageRows = array_slice($data, $offset, $perPage);
            foreach ($pageRows as $index => &$row) {
                $row['row_num'] = $offset + $index + 1;
            }
            unset($row);

            return response()->json([
                'data' => $pageRows,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ]);
    }

    private function retakeNotAppliedReportDataCurriculumBased(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        try {
            ini_set('memory_limit', '512M');
            set_time_limit(300);

            $retakeStatusFilter = (string) $request->get('retake_status_filter', '');
            $perPage = max(1, (int) $request->get('per_page', 50));

            if ($request->filled('calc_key')) {
                // Fon jobi hisoblagan tayyor natija — sahifalash/saralash/Excel tez.
                $calcKey = (string) $request->get('calc_key');
                if (!str_starts_with($calcKey, 'retake_na_calc_' . $this->retakeNotAppliedCalcUserToken() . '_')) {
                    return response()->json(['error' => 'Hisob kaliti mos emas'], 403);
                }
                $data = \App\Jobs\ComputeRetakeNotAppliedReportJob::loadRows($calcKey);
                if ($data === null) {
                    return response()->json(['error' => 'calc_expired'], 410);
                }
            } else {
                // Sinxron yo'l (eski xatti-harakat) — calc_key siz so'rovlar uchun.
                $data = $this->computeRetakeNotAppliedRows($this->buildRetakeNotAppliedFilters($request));
            }

            return $this->retakeNotAppliedRespond($request, $data, $retakeStatusFilter, $perPage);
        } catch (\Throwable $e) {
            \Log::error('Retake-not-applied report error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Hisobotni fon rejimida hisoblashni boshlash. calc_key qaytadi — holat
     * calc-status orqali polling qilinadi, tayyor bo'lgach data endpointi
     * calc_key bilan chaqiriladi (sahifalash/saralash qayta hisobsiz).
     */
    public function startRetakeNotAppliedCalc(Request $request): \Illuminate\Http\JsonResponse
    {
        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        $filters = $this->buildRetakeNotAppliedFilters($request);
        $calcKey = 'retake_na_calc_' . $this->retakeNotAppliedCalcUserToken() . '_' . md5(json_encode($filters));

        $existing = \Illuminate\Support\Facades\Cache::get($calcKey);
        if ($existing && in_array($existing['status'] ?? '', ['queued', 'running'], true)) {
            // Xuddi shu filtrlar bilan hisob allaqachon ketmoqda — davom etamiz.
            return response()->json(['calc_key' => $calcKey] + $existing);
        }

        // Bir xil filtrlar bilan avvalgi run natijasini o'chiramiz — aks holda
        // status endpointi yangi hisob tugamasidan "tayyor" (eski natija) deb
        // ko'rsatib qo'yishi mumkin.
        \App\Jobs\ComputeRetakeNotAppliedReportJob::clearResult($calcKey);

        \Illuminate\Support\Facades\Cache::put($calcKey, [
            'status' => 'queued',
            'percent' => 0,
            'message' => "Navbatga qo'shildi...",
            'updated_at' => now()->toDateTimeString(),
        ], 1800);

        \App\Jobs\ComputeRetakeNotAppliedReportJob::dispatch($filters, $calcKey);

        return response()->json(['calc_key' => $calcKey, 'status' => 'queued']);
    }

    public function retakeNotAppliedCalcStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $calcKey = (string) $request->get('calc_key', '');
        if (!str_starts_with($calcKey, 'retake_na_calc_' . $this->retakeNotAppliedCalcUserToken() . '_')) {
            return response()->json(['status' => 'error', 'message' => 'Hisob kaliti mos emas'], 403);
        }

        // Natija fayli diskda bo'lsa — job tugagan (kesh 'failed'/yo'q bo'lsa ham,
        // masalan retry_after tufayli dublikat urinish). Haqiqiy natijaga ishonamiz.
        if (\App\Jobs\ComputeRetakeNotAppliedReportJob::hasResult($calcKey)) {
            return response()->json(['status' => 'done', 'percent' => 100]);
        }

        $state = \Illuminate\Support\Facades\Cache::get($calcKey);
        if (!$state) {
            return response()->json(['status' => 'error', 'message' => 'Hisob topilmadi yoki muddati tugagan']);
        }

        return response()->json($state);
    }

    private function mapRetakeNotAppliedExportRows(array $rows): array
    {
        return array_map(function (array $row) {
            $scores = collect($row['score_details'] ?? [])->keyBy('type');

            return [
                $row['full_name'] ?? '-',
                $row['student_id_number'] ?? '-',
                $row['department_name'] ?? '-',
                $row['specialty_name'] ?? '-',
                $row['level_name'] ?? '-',
                $row['group_name'] ?? '-',
                $row['subject_name'] ?? '-',
                $row['closing_form'] ?? '-',
                $row['semester_name'] ?? '-',
                $row['total_acload'] ?? '',
                $row['credit'] ?? '',
                $scores->get('JN')['score'] ?? '',
                $scores->get('MT')['score'] ?? '',
                $scores->get('OSKI')['score'] ?? '',
                $scores->get('TEST')['score'] ?? '',
                $row['grade'] ?? '',
                array_key_exists('mastered', $row) && $row['mastered'] !== null
                    ? ($row['mastered'] ? 'Ha' : "Yo'q")
                    : '-',
                $row['retake_status'] ?? '',
                $row['study_status'] ?? '',
            ];
        }, $rows);
    }

    /**
     * Academic record yozuvining "o'qish holati" (talaba nuqtai nazaridan).
     * Qarzdorlik filtri bilan bir xil mantiq — 'passed' bo'lmaganlari qarzdor.
     *   - passed       : muvaffaqiyatli o'tgan — kredit olingan (finish_credit_status = 1,
     *                    "O'tdi" pass/fail fanlar) YOKI o'tish bahosi bor (baho >= 3 / matnli)
     *   - not_graded   : o'qituvchi bahosini qo'ymagan (baho ham, ball ham yo'q)
     *   - not_examined : ball to'plangan, ammo yakuniy baho yo'q (imtihonga kirmagan)
     *   - failed       : yiqilgan (kredit olinmagan, baho numerik va < 3)
     */
    private function academicRecordStudyStatus($ar): array
    {
        $grade = $ar->grade;
        $gradeEmpty = ($grade === null || trim((string) $grade) === '');

        // O'tish bahosi: numerik va >= 3, yoki matnli (numerik bo'lmagan) baho.
        $gradePass = !$gradeEmpty && (!is_numeric($grade) || round((float) $grade, 2) >= 3.0);

        // Kredit olingan (O'zlashtirgan = Ha) yoki o'tish bahosi bor → o'tgan.
        if ((bool) ($ar->finish_credit_status ?? false) || $gradePass) {
            return ['code' => 'passed', 'label' => "Muvaffaqiyatli o'tgan"];
        }

        if ($gradeEmpty) {
            $point = $ar->total_point ?? null;
            $pointVal = ($point === null || trim((string) $point) === '' || !is_numeric($point))
                ? null : (float) $point;
            if ($pointVal !== null && $pointVal > 0) {
                return ['code' => 'not_examined', 'label' => 'Imtihonga kirmagan'];
            }
            return ['code' => 'not_graded', 'label' => "O'qituvchi bahosini qo'ymagan"];
        }

        // Baho bor (numerik < 3), kredit olinmagan → yiqilgan.
        return ['code' => 'failed', 'label' => 'Yiqilgan'];
    }

    /**
     * Test markazi jurnalining yakuniy natijasini ("o'qish holati" ustuni uchun)
     * pill kodi + yorlig'iga aylantiradi. Natija yo'q holatlarda academic records
     * asosidagi holatga ($fallback) qaytadi.
     *
     * $removed — appelyatsiyada o'chirilgan (oldingi) test baholari soni; urinishlar
     * jami = $removed + 1. Faqat qayta topshirganda (>= 2) "(N)" suffiks qo'shiladi
     * (test markazi jurnalidagi logika bilan bir xil).
     *
     * @param  array{status:string, value:?int, baho:string}  $final
     * @param  array{code:string, label:string}  $fallback
     * @return array{code:string, label:string}
     */
    private function testMarkaziStudyStatus(array $final, array $fallback, int $removed = 0): array
    {
        $suffix = $removed >= 1 ? ' (' . ($removed + 1) . ')' : '';

        return match ($final['status'] ?? '') {
            'passed' => [
                'code' => 'passed',
                'label' => "O'zlashtirdi"
                    . (isset($final['value']) && $final['value'] !== null ? ": {$final['value']}" : '')
                    . $suffix,
            ],
            'failed' => ['code' => 'failed', 'label' => 'Yiqildi' . $suffix],
            'absent' => ['code' => 'not_examined', 'label' => 'Imtihonga kelmagan' . $suffix],
            'no_teacher_grade' => ['code' => 'not_graded', 'label' => "O'qituvchi bahosini qo'ymagan" . $suffix],
            default => $fallback,
        };
    }

    /**
     * Qayta o'qishga ariza holati matni (bitta ariza + uning to'lov guruhi bo'yicha).
     */
    private function retakeApplicationStatusLabel($app, $group): string
    {
        // Guruhga biriktirilgan / yakuniy tasdiqlangan.
        if ($app->retake_group_id
            || $app->final_status === \App\Models\RetakeApplication::STATUS_APPROVED) {
            return 'Guruhga tasdiqlangan';
        }

        $dualApproved = $app->dean_status === \App\Models\RetakeApplication::STATUS_APPROVED
            && $app->registrar_status === \App\Models\RetakeApplication::STATUS_APPROVED;

        if ($dualApproved) {
            // Dekan+registrator tasdiqlagan — endi to'lov bosqichi.
            if ($group) {
                if ($group->payment_verification_status === \App\Models\RetakeApplicationGroup::PAYMENT_VERIFICATION_APPROVED) {
                    return "To'lov tasdiqlandi";
                }
                if ($group->payment_uploaded_at !== null
                    && $group->payment_verification_status === \App\Models\RetakeApplicationGroup::PAYMENT_VERIFICATION_PENDING) {
                    return "To'lov tekshirilmoqda";
                }
            }
            return "To'lovini qilmagan";
        }

        return "Ko'rib chiqilmoqda";
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

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Hozirgi semestr',
            'Qarzlar soni', 'Fan semestr', 'Fan nomi', 'Kredit', 'Soat', 'Baho',
            'Joriy semestr xavfi (soni)', 'Joriy semestr xavflari'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a3268']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $lastCol = 'P';
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray($headerStyle);

        // Har bir talaba uchun barcha curriculum fanlarini academic records bilan birlashtirish
        $rowNum = 2;
        $idx = 1;
        $debtFill = [
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF2F2']],
            'font' => ['color' => ['rgb' => 'DC2626'], 'bold' => true],
        ];

        foreach ($data as $student) {
            $hemisId = $student['hemis_id'];
            $curriculumId = null;

            // Talabaning curriculum_id sini olish
            $st = DB::table('students')->where('hemis_id', $hemisId)->first();
            if (!$st || !$st->curriculum_id) continue;
            $curriculumId = $st->curriculum_id;
            $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;

            // Curriculum subjects
            $currSubjects = DB::table('curriculum_subjects')
                ->where('curricula_hemis_id', $curriculumId)
                ->where('is_active', true)
                ->where('subject_code', 'not like', '%/%')
                ->select('subject_id', 'subject_name', 'semester_code', 'semester_name', 'credit', 'total_acload')
                ->distinct()
                ->orderBy('semester_code')
                ->orderBy('subject_name')
                ->get();

            $currSubjects = $this->filterSubjectsByGroupSuffix($currSubjects, $student['group_name'] ?? '');

            // Academic records
            $arRecords = DB::table('academic_records')
                ->where('student_id', $hemisId)
                ->select('subject_id', 'semester_id', 'grade')
                ->get()
                ->keyBy(fn($ar) => $ar->subject_id . '|' . $ar->semester_id);

            // Joriy semestr xavflari (journal/individual grafik): matn ko'rinishida
            $currentRisks = $student['current_risks'] ?? [];
            $currentRiskCount = $student['current_risk_count'] ?? count($currentRisks);
            $riskParts = [];
            foreach ($currentRisks as $cr) {
                $rname = $cr['subject_name'] ?? 'Fan';
                $rreasons = implode(', ', $cr['reasons'] ?? []);
                $riskParts[] = $rreasons !== '' ? ($rname . ': ' . $rreasons) : $rname;
            }
            $riskText = implode('; ', $riskParts);

            $firstRow = $rowNum;
            foreach ($currSubjects as $sub) {
                $subSemCode = (int) $sub->semester_code;
                // Joriy semestrdan oldingi semestrlar
                if ($studentSemCode && $subSemCode >= $studentSemCode) continue;

                $arKey = $sub->subject_id . '|' . $sub->semester_code;
                $ar = $arRecords->get($arKey);
                $isDebt = !$ar;

                $sheet->setCellValue([1, $rowNum], $idx);
                $sheet->setCellValue([2, $rowNum], $student['full_name']);
                $sheet->setCellValueExplicit([3, $rowNum], $student['student_id_number'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue([4, $rowNum], $student['department_name']);
                $sheet->setCellValue([5, $rowNum], $student['specialty_name']);
                $sheet->setCellValue([6, $rowNum], $student['level_name']);
                $sheet->setCellValue([7, $rowNum], $student['group_name']);
                $sheet->setCellValue([8, $rowNum], $student['semester_name']);
                $sheet->setCellValue([9, $rowNum], $student['debt_count']);
                $sheet->setCellValue([10, $rowNum], $sub->semester_name);
                $sheet->setCellValue([11, $rowNum], $sub->subject_name);
                $sheet->setCellValue([12, $rowNum], $sub->credit);
                $sheet->setCellValue([13, $rowNum], $sub->total_acload);
                $sheet->setCellValue([14, $rowNum], $isDebt ? 'Qarzdor' : $ar->grade);
                $sheet->setCellValue([15, $rowNum], $currentRiskCount);
                $sheet->setCellValue([16, $rowNum], $riskText);

                if ($isDebt) {
                    $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray($debtFill);
                }

                $rowNum++;
            }
            $idx++;
        }

        $widths = [5, 30, 15, 25, 30, 8, 15, 10, 10, 12, 35, 8, 8, 10, 12, 45];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = $rowNum - 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
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

        $headers = ['#', 'Talaba FISH', 'ID raqam', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Toifa', 'Semestr',
            'Fan', 'Fan semestri', 'JB', 'MT', 'ON', 'JN%', 'OSKI', 'Test', 'Davomat %', 'Sababsiz soat', 'Auditoriya soati', 'Sabab'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:U1')->applyFromArray($headerStyle);

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
                $sheet->setCellValue([7, $rowNum], $student['group_name']);
                $sheet->setCellValue([8, $rowNum], $student['student_type_name'] ?? '');
                $sheet->setCellValue([9, $rowNum], $student['semester_name']);
                $sheet->setCellValue([10, $rowNum], $debt['subject_name']);
                $sheet->setCellValue([11, $rowNum], $debt['semester_name'] ?? '');
                $sheet->setCellValue([12, $rowNum], $debt['jb']);
                $sheet->setCellValue([13, $rowNum], $debt['mt']);
                $sheet->setCellValue([14, $rowNum], $debt['on'] ?? '-');
                $sheet->setCellValue([15, $rowNum], $debt['jn_percent']);
                $sheet->setCellValue([16, $rowNum], $debt['oski'] ?? '-');
                $sheet->setCellValue([17, $rowNum], $debt['test'] ?? '-');
                $sheet->setCellValue([18, $rowNum], $debt['absence_percent'] . '%');
                $sheet->setCellValue([19, $rowNum], $debt['unexcused_hours']);
                $sheet->setCellValue([20, $rowNum], $debt['auditory_hours']);
                $sheet->setCellValue([21, $rowNum], implode('; ', $debt['reasons']));

                // Qizil fon yiqilgan ballarga
                $redFill = [
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF0F0']],
                ];
                $minLimit = $debt['minimum_limit'] ?? 60;
                if ($debt['jn_percent'] < $minLimit) $sheet->getStyle("O{$rowNum}")->applyFromArray($redFill);
                if ($debt['mt'] < $minLimit) $sheet->getStyle("M{$rowNum}")->applyFromArray($redFill);
                if ($debt['on'] !== null && $debt['on'] < $minLimit) $sheet->getStyle("N{$rowNum}")->applyFromArray($redFill);
                if ($debt['oski'] !== null && $debt['oski'] < $minLimit) $sheet->getStyle("P{$rowNum}")->applyFromArray($redFill);
                if ($debt['test'] !== null && $debt['test'] < $minLimit) $sheet->getStyle("Q{$rowNum}")->applyFromArray($redFill);
                if ($debt['absence_percent'] > 25) $sheet->getStyle("R{$rowNum}")->applyFromArray($redFill);

                $rowNum++;
                $idx++;
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 15, 18, 10, 35, 12, 8, 8, 8, 8, 8, 8, 12, 12, 12, 40];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = $rowNum - 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:U{$lastRow}")->applyFromArray([
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
     * Talabaning ma'lum semestrdagi barcha fanlari (curriculum_subjects dan)
     */
    public function studentSemesterGrades(Request $request)
    {
        try {
            $studentId = $request->get('student_id');
            $semesterCode = $request->get('semester_code');

            if (!$studentId || !$semesterCode) {
                return response()->json(['grades' => []]);
            }

            $student = DB::table('students')->where('hemis_id', $studentId)->first();
            if (!$student || !$student->curriculum_id) {
                return response()->json(['grades' => []]);
            }

            $groupName = $request->get('group_name', '');

            // Shu semestr uchun tarixiy curriculum_id'ni academic_records'dan olamiz.
            // Agar talaba transfer qilingan bo'lsa, o'tgan semestrlarda eski curriculum
            // saqlangan. Joriy/kelgusi semester uchun students.curriculum_id'ga qaytamiz.
            $historicalCurriculumRows = DB::table('academic_records')
                ->where('student_id', $studentId)
                ->where('semester_id', $semesterCode)
                ->whereNotNull('curriculum_id')
                ->select('curriculum_id', DB::raw('COUNT(*) as records_count'))
                ->groupBy('curriculum_id')
                ->get();
            $historicalCurriculumCounts = [];
            foreach ($historicalCurriculumRows as $row) {
                $historicalCurriculumCounts[(string) $row->curriculum_id] = (int) $row->records_count;
            }
            $historicalCurriculumId = $this->pickSemesterCurriculumId($historicalCurriculumCounts, $student->curriculum_id);
            $effectiveCurriculumId = $historicalCurriculumId ?: $student->curriculum_id;

            // Curriculum subjects — shu semestrga tegishli barcha fanlar.
            $currSubjectsQuery = DB::table('curriculum_subjects as cs')
                ->where('cs.curricula_hemis_id', $effectiveCurriculumId)
                ->where('cs.semester_code', $semesterCode)
                ->where('cs.is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
                })
                ->select('cs.curriculum_subject_hemis_id', 'cs.subject_id', 'cs.subject_name', 'cs.semester_name', 'cs.subject_type_code', 'cs.credit', 'cs.total_acload')
                ->distinct()
                ->orderBy('cs.subject_name');

            foreach (config('app.excluded_rating_subject_patterns', []) as $pattern) {
                $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
            }

            $currSubjects = $currSubjectsQuery->get();

            $currSubjects = $this->filterSubjectsByGroupSuffix($currSubjects, $groupName);

            // Tanlov fanlar (subject_type_code = 12) uchun talabaning haqiqiy tanlovini olamiz
            $tanlovCsHemisIds = $currSubjects
                ->where('subject_type_code', '12')
                ->pluck('curriculum_subject_hemis_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $tanlovPicksMap = [];
            if (!empty($tanlovCsHemisIds)) {
                $picks = DB::table('student_subjects')
                    ->where('student_hemis_id', $studentId)
                    ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                    ->select('curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                    ->get();
                foreach ($picks as $p) {
                    $tanlovPicksMap[$studentId . '|' . $p->curriculum_subject_hemis_id] = [
                        'subject_id'   => $p->subject_id,
                        'subject_name' => $p->subject_name,
                    ];
                }
            }

            // Academic records — shu semestrga tegishli baholar
            $arRecords = DB::table('academic_records')
                ->where('student_id', $studentId)
                ->where('semester_id', $semesterCode)
                ->where(function ($q) use ($effectiveCurriculumId) {
                    $q->where('curriculum_id', $effectiveCurriculumId)
                        ->orWhereNull('curriculum_id');
                })
                ->select('subject_id', 'subject_name', 'credit', 'total_acload', 'total_point', 'grade', 'finish_credit_status', 'retraining_status')
                ->get()
                ->keyBy(fn ($row) => (string) $row->subject_id);

            $grades = $this->buildSemesterAcademicGradeRows(
                $studentId,
                (string) $semesterCode,
                $currSubjects,
                $arRecords,
                $tanlovPicksMap
            );

            $semesterName = $currSubjects->first()->semester_name ?? $semesterCode . '-semestr';

            return response()->json([
                'semester_name' => $semesterName,
                'grades' => $grades,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'grades' => []], 500);
        }
    }

    private function isAcademicRecordDebt($record): bool
    {
        if (!$record) {
            return true;
        }

        if ((bool) ($record->finish_credit_status ?? false)) {
            return false;
        }

        if ($record->grade === null || $record->grade === '') {
            return true;
        }

        if ((bool) ($record->retraining_status ?? false)) {
            return true;
        }

        if (!is_numeric($record->grade)) {
            return false;
        }

        $numericGrade = round((float) $record->grade, 2);

        return $numericGrade === 0.0 || $numericGrade === 2.0;
    }

    private function resolveAcademicDebtSubjectForStudent($studentHemisId, $subjectRow, array $tanlovPicksMap): ?array
    {
        $isTanlov = (string) ($subjectRow->subject_type_code ?? '') === '12';
        if (!$isTanlov) {
            return [
                'subject_id' => $subjectRow->subject_id,
                'subject_name' => $subjectRow->subject_name,
            ];
        }

        $pickKey = $studentHemisId . '|' . ($subjectRow->curriculum_subject_hemis_id ?? '');
        $pick = $tanlovPicksMap[$pickKey] ?? null;
        if (!$pick || empty($pick['subject_id'])) {
            return null;
        }

        return [
            'subject_id' => $pick['subject_id'],
            'subject_name' => $pick['subject_name'] ?: $subjectRow->subject_name,
        ];
    }

    private function buildSemesterAcademicGradeRows($studentHemisId, $semesterCode, $currSubjects, $arRecordsBySubject, array $tanlovPicksMap): array
    {
        $grades = [];

        foreach ($currSubjects as $sub) {
            $resolvedSubject = $this->resolveAcademicDebtSubjectForStudent($studentHemisId, $sub, $tanlovPicksMap);
            if ($resolvedSubject === null) {
                continue;
            }

            $effectiveSubjectId = (string) $resolvedSubject['subject_id'];
            $ar = $arRecordsBySubject->get($effectiveSubjectId);

            $grades[] = (object) [
                'subject_id'    => $effectiveSubjectId,
                'semester_code' => (string) $semesterCode,
                'semester_name' => $sub->semester_name,
                'subject_name'  => $resolvedSubject['subject_name'],
                'credit'        => $sub->credit,
                'total_acload'  => $sub->total_acload,
                'has_record'    => $ar !== null,
                'total_point'   => $ar->total_point ?? null,
                'grade'         => $ar->grade ?? null,
                'finish_credit_status' => (bool) ($ar->finish_credit_status ?? false),
                'is_debt'       => $this->isAcademicRecordDebt($ar),
                'is_orphan'     => false,
            ];
        }

        return $grades;
    }

    private function pickSemesterCurriculumId(array $curriculumCounts, $currentCurriculumId): ?string
    {
        if ($currentCurriculumId !== null) {
            foreach ($curriculumCounts as $curriculumId => $count) {
                if ((string) $curriculumId === (string) $currentCurriculumId) {
                    return (string) $curriculumId;
                }
            }
        }

        $pickedCurriculumId = null;
        $pickedCount = -1;

        foreach ($curriculumCounts as $curriculumId => $count) {
            if ($count > $pickedCount) {
                $pickedCurriculumId = (string) $curriculumId;
                $pickedCount = $count;
            }
        }

        return $pickedCurriculumId;
    }

    /**
     * Academic records eksportini background jobda boshlash
     */
    public function startAcademicRecordsExport(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();
        if ($dekanFacultyId && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyId]);
        }

        $filters = $request->only([
            'education_type', 'faculty', 'specialty', 'level_code', 'semester_code',
            'group', 'student_status', 'student_name', 'student_type',
        ]);

        $exportKey = 'academic_records_export_' . auth()->id() . '_' . md5(json_encode($filters));

        $existing = \Illuminate\Support\Facades\Cache::get($exportKey);
        if ($existing && ($existing['status'] ?? '') === 'running') {
            return response()->json([
                'export_key' => $exportKey,
                'status'     => 'running',
                'message'    => $existing['message'] ?? 'Ishlanmoqda...',
                'percent'    => $existing['percent'] ?? 0,
            ]);
        }

        \Illuminate\Support\Facades\Cache::put($exportKey, [
            'status'     => 'running',
            'message'    => 'Navbatga qo\'shilmoqda...',
            'percent'    => 0,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);

        \App\Jobs\ExportAcademicRecordsJob::dispatch($filters, $exportKey);

        return response()->json([
            'export_key' => $exportKey,
            'status'     => 'running',
            'message'    => 'Eksport boshlandi',
        ]);
    }

    public function academicRecordsExportStatus(Request $request)
    {
        $exportKey = $request->get('export_key');
        if (!$exportKey) {
            return response()->json(['status' => 'error', 'message' => 'Export key topilmadi'], 400);
        }

        $data = \Illuminate\Support\Facades\Cache::get($exportKey);

        // Cache yo'qolgan bo'lsa, diskdagi meta'dan o'qiymiz
        if (!$data) {
            $paths = \App\Jobs\ExportAcademicRecordsJob::pathsFor($exportKey);
            if (file_exists($paths['meta'])) {
                $data = json_decode(@file_get_contents($paths['meta']), true) ?: null;
            }
        }

        if (!$data) {
            return response()->json(['status' => 'error', 'message' => 'Eksport topilmadi yoki muddati tugagan']);
        }

        unset($data['file_content']);
        return response()->json($data);
    }

    public function academicRecordsExportDownload(Request $request)
    {
        $exportKey = $request->get('export_key');
        if (!$exportKey) {
            return response()->json(['error' => 'Export key topilmadi'], 400);
        }

        $paths = \App\Jobs\ExportAcademicRecordsJob::pathsFor($exportKey);

        // Holatni cache yoki diskdan o'qiymiz
        $data = \Illuminate\Support\Facades\Cache::get($exportKey);
        $source = $data ? 'cache' : null;
        if (!$data && file_exists($paths['meta'])) {
            $data = json_decode(@file_get_contents($paths['meta']), true) ?: null;
            $source = 'meta';
        }

        $debug = [
            'export_key' => $exportKey,
            'meta_path'  => $paths['meta'],
            'meta_exists'=> file_exists($paths['meta']),
            'xlsx_path'  => $paths['xlsx'],
            'xlsx_exists'=> file_exists($paths['xlsx']),
            'data_source'=> $source,
            'data_status'=> $data['status'] ?? null,
            'percent'    => $data['percent'] ?? null,
        ];

        if (!$data || ($data['status'] ?? '') !== 'done') {
            \Illuminate\Support\Facades\Log::warning('[AR Export Download] Tayyor emas', $debug);
            return response()->json(['error' => 'Fayl topilmadi yoki hali tayyor emas', 'debug' => $debug], 404);
        }

        $fileName = $data['file_name'] ?? 'Academic_records.xlsx';

        if (file_exists($paths['xlsx'])) {
            return response()->download($paths['xlsx'], $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }

        // Eski format bilan orqaga moslik
        $filePath = $data['file_path'] ?? null;
        if ($filePath && file_exists($filePath)) {
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);
        }
        if (!empty($data['file_content'])) {
            $content = base64_decode($data['file_content']);
            return response($content, 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length'      => strlen($content),
            ]);
        }

        \Illuminate\Support\Facades\Log::warning('[AR Export Download] Fayl topilmadi', $debug);
        return response()->json(['error' => 'Fayl serverda topilmadi', 'debug' => $debug], 404);
    }

    /**
     * Talabaning barcha semestrlari — curriculum_subjects dan
     */
    public function studentAllRecords(Request $request)
    {
        try {
            $studentId = $request->get('student_id');

            if (!$studentId) {
                return response()->json(['semesters' => [], 'planned_subjects' => [], 'grade_debts' => [], 'extra_subjects' => []]);
            }

            $student = DB::table('students')->where('hemis_id', $studentId)->first();
            if (!$student || !$student->curriculum_id) {
                return response()->json(['semesters' => [], 'planned_subjects' => [], 'grade_debts' => [], 'extra_subjects' => []]);
            }

            $groupName = $request->get('group_name', '');
            $showCurrentSemester = $request->get('current_semester', '0') == '1';

            // Talabaning joriy semester kodi
            $studentSemesterCode = $student->semester_code ? (string) $student->semester_code : null;

            $excludedPatterns = config('app.excluded_rating_subject_patterns', []);

            // 1) academic_records'dan har semester uchun tarixiy curriculum_id va arExists
            $arRows = DB::table('academic_records')
                ->where('student_id', $studentId)
                ->select('subject_id', 'subject_name', 'credit', 'total_point', 'grade', 'finish_credit_status', 'retraining_status', 'semester_id', 'curriculum_id')
                ->get();
            $arExists = [];
            $arLegacyExists = [];
            $semCurrCounts = [];
            $semCurr = []; // [sem_code => curriculum_id]
            $arBySem = [];
            foreach ($arRows as $ar) {
                if ($ar->curriculum_id) {
                    $semCurrCounts[(string) $ar->semester_id][(string) $ar->curriculum_id]
                        = ($semCurrCounts[(string) $ar->semester_id][(string) $ar->curriculum_id] ?? 0) + 1;
                } else {
                    $arLegacyExists[$ar->subject_id . '|' . $ar->semester_id] = true;
                }
            }
            foreach ($semCurrCounts as $semesterCode => $curriculumCounts) {
                $pickedCurriculumId = $this->pickSemesterCurriculumId($curriculumCounts, $student->curriculum_id);
                if ($pickedCurriculumId) {
                    $semCurr[$semesterCode] = $pickedCurriculumId;
                }
            }

            foreach ($arRows as $ar) {
                $semesterCode = (string) $ar->semester_id;
                $pickedCurriculumId = $semCurr[$semesterCode] ?? null;

                if ($ar->curriculum_id && $pickedCurriculumId !== null && (string) $ar->curriculum_id !== (string) $pickedCurriculumId) {
                    continue;
                }

                $arExists[$ar->subject_id . '|' . $ar->semester_id] = true;
                $arBySem[$semesterCode][] = $ar;
            }

            // Joriy semester uchun students.curriculum_id
            if ($studentSemesterCode && !isset($semCurr[$studentSemesterCode])) {
                $semCurr[$studentSemesterCode] = $student->curriculum_id;
            }

            // 2) Curriculum_subjects'ni har (curr_id, sem_code) juftligi uchun yuklab olamiz
            $allCurrIds = collect($semCurr)->values()->unique()->all();
            $allSems = collect($semCurr)->keys()->all();

            $currSubjectsQuery = DB::table('curriculum_subjects as cs')
                ->whereIn('cs.curricula_hemis_id', $allCurrIds ?: [0])
                ->whereIn('cs.semester_code', $allSems ?: [0])
                ->where('cs.is_active', 1)
                ->where(function ($q) {
                    $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
                })
                ->select('cs.curricula_hemis_id', 'cs.curriculum_subject_hemis_id', 'cs.semester_code', 'cs.semester_name', 'cs.subject_id', 'cs.subject_name', 'cs.subject_type_code', 'cs.credit', 'cs.total_acload')
                ->distinct()
                ->orderBy('cs.semester_code')
                ->orderBy('cs.subject_name');
            foreach ($excludedPatterns as $pattern) {
                $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
            }
            $allCsRows = $currSubjectsQuery->get();

            // (curr_id|sem) bo'yicha guruhlash + faqat shu talabaga tegishli ((sem -> curr) mapidan
            // foydalanib har sem uchun aynan o'sha sem'da ishlatilgan curriculum'dan)
            $allCsRows = $this->filterSubjectsByGroupSuffix($allCsRows, $groupName);

            $currSubjects = $allCsRows->filter(function ($s) use ($semCurr) {
                $expected = $semCurr[(string) $s->semester_code] ?? null;
                return $expected !== null && (string) $s->curricula_hemis_id === (string) $expected;
            })->values();

            // 3) Semestr tab'lari (toggle bilan)
            $semesters = $currSubjects->groupBy('semester_code')
                ->when($showCurrentSemester && $studentSemesterCode, function ($collection) use ($studentSemesterCode) {
                    return $collection->filter(fn($items, $code) => (string) $code === $studentSemesterCode);
                })
                ->when(!$showCurrentSemester && $studentSemesterCode, function ($collection) use ($studentSemesterCode) {
                    return $collection->filter(fn($items, $code) => (int) $code < (int) $studentSemesterCode);
                })
                ->map(function ($items, $semesterCode) {
                    return (object) [
                        'semester_id' => $semesterCode,
                        'semester_name' => $items->first()->semester_name,
                        'subject_count' => $items->count(),
                    ];
                })->values();

            // 4) Tanlov fanlar uchun talabaning tanlovi
            $tanlovCsHemisIds = $currSubjects
                ->where('subject_type_code', '12')
                ->pluck('curriculum_subject_hemis_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
            $tanlovPicksMap = [];
            if (!empty($tanlovCsHemisIds)) {
                $picks = DB::table('student_subjects')
                    ->where('student_hemis_id', $studentId)
                    ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                    ->select('curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                    ->get();
                foreach ($picks as $p) {
                    $tanlovPicksMap[$studentId . '|' . $p->curriculum_subject_hemis_id] = [
                        'subject_id'   => $p->subject_id,
                        'subject_name' => $p->subject_name,
                    ];
                }
            }

            $currSubjects = $currSubjects->filter(function ($sub) use ($studentId, $tanlovPicksMap) {
                return $this->resolveAcademicDebtSubjectForStudent($studentId, $sub, $tanlovPicksMap) !== null;
            })->values();

            // 5) Qarzlar: semester card ichidagi is_debt natijasiga qarab olinadi.
            $debtsAll = [];
            $plannedSubjects = [];
            $expectedSubjectIdsBySem = [];
            $currSubjectsBySem = $currSubjects->groupBy('semester_code');

            foreach ($currSubjectsBySem as $semCode => $subjectsForSem) {
                $semInt = (int) $semCode;

                if ($showCurrentSemester) {
                    if ($studentSemesterCode && $semInt !== (int) $studentSemesterCode) {
                        continue;
                    }
                } else {
                    if ($studentSemesterCode && $semInt >= (int) $studentSemesterCode) {
                        continue;
                    }
                }

                $arRecordsBySubject = collect($arBySem[(string) $semCode] ?? [])
                    ->keyBy(fn ($row) => (string) $row->subject_id);

                $semesterGrades = $this->buildSemesterAcademicGradeRows(
                    $studentId,
                    (string) $semCode,
                    $subjectsForSem->values(),
                    $arRecordsBySubject,
                    $tanlovPicksMap
                );

                foreach ($semesterGrades as $gradeRow) {
                    $expectedSubjectIdsBySem[(string) $gradeRow->semester_code][(string) $gradeRow->subject_id] = true;

                    $plannedSubjects[] = [
                        'semester_code' => $gradeRow->semester_code,
                        'semester_name' => $gradeRow->semester_name,
                        'subject_name'  => $gradeRow->subject_name,
                        'credit'        => $gradeRow->credit,
                        'total_acload'  => $gradeRow->total_acload,
                        'has_record'    => $gradeRow->has_record,
                        'total_point'   => $gradeRow->total_point,
                        'grade'         => $gradeRow->grade,
                        'is_debt'       => $gradeRow->is_debt,
                    ];

                    if (!$gradeRow->is_debt) {
                        continue;
                    }

                    $debtsAll[] = [
                        'semester_code' => $gradeRow->semester_code,
                        'semester_name' => $gradeRow->semester_name,
                        'subject_name'  => $gradeRow->subject_name,
                        'credit'        => $gradeRow->credit,
                        'total_acload'  => $gradeRow->total_acload,
                        'status'        => 'Qarzdor',
                    ];
                }
            }

            $extraSubjects = [];
            foreach ($arBySem as $semCode => $rows) {
                $semInt = (int) $semCode;
                if ($showCurrentSemester) {
                    if ($studentSemesterCode && $semInt !== (int) $studentSemesterCode) continue;
                } else {
                    if ($studentSemesterCode && $semInt >= (int) $studentSemesterCode) continue;
                }

                foreach ($rows as $ar) {
                    if (isset($expectedSubjectIdsBySem[$semCode][(string) $ar->subject_id])) {
                        continue;
                    }

                    $extraSubjects[] = [
                        'semester_code' => $semCode,
                        'semester_name' => $semCode . '-semestr',
                        'subject_name'  => $ar->subject_name,
                        'credit'        => $ar->credit,
                        'total_point'   => $ar->total_point,
                        'grade'         => $ar->grade,
                    ];
                }
            }

            return response()->json([
                'semesters'   => $semesters,
                'planned_subjects' => $plannedSubjects,
                'grade_debts' => $debtsAll,
                'extra_subjects' => $extraSubjects,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage(), 'semesters' => [], 'planned_subjects' => [], 'grade_debts' => [], 'extra_subjects' => []], 500);
        }
    }

    /**
     * Debug: talabaning student_subjects (DB) va HEMIS API real ma'lumotlarini taqqoslash
     */
    public function debugStudentSubjects(Request $request)
    {
        $studentIdParam = $request->get('student_id');
        if (!$studentIdParam) {
            return response()->json(['error' => 'student_id required']);
        }

        // student_id_number (368...) yoki hemis_id (6957) bo'lishi mumkin — ikkalasini tekshiramiz
        $studentRow = DB::table('students')
            ->where('hemis_id', $studentIdParam)
            ->orWhere('student_id_number', $studentIdParam)
            ->first();

        if (!$studentRow) {
            return response()->json(['error' => 'Talaba topilmadi: ' . $studentIdParam]);
        }

        $studentId = $studentRow->hemis_id; // internal HEMIS ID (e.g. 6957)
        $studentIdNumber = $studentRow->student_id_number; // e.g. 368231100383

        // 1. Bazadagi student_subjects
        $dbSubjects = DB::table('student_subjects')
            ->where('student_hemis_id', $studentId)
            ->select('subject_id', 'semester_id', 'subject_name', 'curriculum_subject_hemis_id', 'updated_at')
            ->orderBy('semester_id')
            ->orderBy('subject_name')
            ->get();

        // 2. HEMIS API dan real-time ma'lumot
        $baseUrl = config('services.hemis.base_url');
        $token   = config('services.hemis.token');

        $apiSubjects = [];
        $apiError = null;
        $page = 1;

        do {
            try {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->timeout(30)
                    ->withToken($token)
                    ->get($baseUrl . 'v1/data/student-subject-list', [
                        '_student' => $studentId,
                        'page'     => $page,
                        'limit'    => 100,
                    ]);

                if (!$response->successful()) {
                    $apiError = 'HTTP ' . $response->status();
                    break;
                }

                $data = $response->json();
                if (!($data['success'] ?? false)) {
                    $apiError = 'API success=false';
                    break;
                }

                $items      = $data['data']['items'] ?? [];
                $pagination = $data['data']['pagination'] ?? [];

                foreach ($items as $item) {
                    $cs = $item['curriculumSubject'] ?? [];
                    $apiSubjects[] = [
                        'curriculum_subject_hemis_id' => $cs['id'] ?? null,
                        'subject_id'                  => $cs['subject']['id'] ?? null,
                        'subject_name'                => $cs['subject']['name'] ?? null,
                        'semester_id'                 => $item['_semester'] ?? null,
                    ];
                }

                $hasMore = ($pagination['page'] ?? 1) < ($pagination['pageCount'] ?? 1);
                $page++;
            } catch (\Exception $e) {
                $apiError = $e->getMessage();
                break;
            }
        } while ($hasMore ?? false);

        // 3. Taqqoslash
        $apiKeys = collect($apiSubjects)->keyBy(fn($s) => $s['subject_id'] . '|' . $s['semester_id']);
        $dbKeys  = $dbSubjects->keyBy(fn($s) => $s->subject_id . '|' . $s->semester_id);

        $onlyInDb  = $dbSubjects->filter(fn($s) => !$apiKeys->has($s->subject_id . '|' . $s->semester_id))->values();
        $onlyInApi = collect($apiSubjects)->filter(fn($s) => !$dbKeys->has($s['subject_id'] . '|' . $s['semester_id']))->values();

        return response()->json([
            'student_id_number' => $studentIdNumber,
            'hemis_id'      => $studentId,
            'full_name'     => $studentRow->full_name ?? null,
            'db_count'      => $dbSubjects->count(),
            'api_count'     => count($apiSubjects),
            'api_error'     => $apiError,
            'only_in_db'    => $onlyInDb,   // DB da bor, API da yo'q (eskirgan yozuvlar)
            'only_in_api'   => $onlyInApi,  // API da bor, DB da yo'q (import qilinmagan)
            'db_subjects'   => $dbSubjects,
            'api_subjects'  => collect($apiSubjects)->sortBy(['semester_id', 'subject_name'])->values(),
        ]);
    }

    /**
     * Guruh suffiksi bo'yicha fanlarni filtrlash
     * "d1/23-01b" → suffix "b" → faqat "(b)" yoki suffixsiz fanlar
     */
    private function filterSubjectsByGroupSuffix($records, $groupName)
    {
        if (empty($groupName)) {
            return $records;
        }

        // Guruh nomidan suffixni ajratish: "d1/23-01b" → "b"
        $groupSuffix = '';
        if (preg_match('/(\d+)([a-zA-Z])$/', trim($groupName), $m)) {
            $groupSuffix = mb_strtolower($m[2]);
        }

        if (empty($groupSuffix)) {
            return $records;
        }

        return $records->filter(function ($record) use ($groupSuffix) {
            $name = $record->subject_name ?? '';
            // Agar fan nomida (a), (b), (c) kabi suffix bo'lsa
            if (preg_match('/\(([a-zA-Zа-яА-Я])\)\s*$/u', $name, $m)) {
                return mb_strtolower($m[1]) === $groupSuffix;
            }
            // Suffixsiz fan — har doim ko'rsatiladi
            return true;
        })->values();
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
      try {
        // Filtrlar
        $groupIds = [];
        if ($request->filled('education_type')) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $request->education_type)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
        }

        // Group PK (groups.id) dan group_hemis_id ga convert
        $groupHemisId = null;
        if ($request->filled('group')) {
            $groupHemisId = DB::table('groups')->where('id', $request->group)->value('group_hemis_id');
        }

        // Umumiy filtr funksiyasi
        $applyFilters = function ($query) use ($request, $groupIds, $groupHemisId) {
            if ($request->filled('education_type')) {
                $query->whereIn('s.group_id', $groupIds);
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
            if ($groupHemisId) {
                $query->where('s.group_id', $groupHemisId);
            }
            if ($request->filled('student_name')) {
                $query->where('s.full_name', 'LIKE', '%' . $request->student_name . '%');
            }
        };

        // ========================================
        // 1-QADAM: Tasdiqlangan arizalarni olish (asosiy manba)
        // ========================================
        // A) Fanga bog'langan tasdiqlangan arizalar
        $excWithSubjectQuery = DB::table('absence_excuses as ae')
            ->join('students as s', 's.hemis_id', '=', 'ae.student_hemis_id')
            ->join('absence_excuse_makeups as aem', 'aem.absence_excuse_id', '=', 'ae.id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->where('ae.status', 'approved')
            ->whereNotNull('aem.subject_id');

        $applyFilters($excWithSubjectQuery);

        $excWithSubject = $excWithSubjectQuery->select(
            'ae.id as excuse_id', 'ae.student_hemis_id', 's.full_name', 's.student_id_number',
            's.department_name', 's.specialty_name', 's.level_name',
            's.group_name', 's.semester_name', 's.group_id',
            'ae.start_date', 'ae.end_date', 'g.id as group_pk',
            'aem.subject_name', 'aem.subject_id as aem_subject_id',
            DB::raw("COALESCE(
                CASE WHEN aem.subject_id >= 100000 THEN
                    (SELECT cs.subject_id FROM curriculum_subjects cs
                     WHERE cs.curriculum_subject_hemis_id = aem.subject_id LIMIT 1)
                ELSE NULL END,
                (SELECT att.subject_id FROM attendances att
                 WHERE att.student_hemis_id = ae.student_hemis_id
                 AND TRIM(att.subject_name) = TRIM(aem.subject_name) LIMIT 1),
                (SELECT att.subject_id FROM attendances att
                 WHERE att.student_hemis_id = ae.student_hemis_id
                 AND (TRIM(att.subject_name) LIKE CONCAT('%', TRIM(aem.subject_name), '%')
                      OR TRIM(aem.subject_name) LIKE CONCAT('%', TRIM(att.subject_name), '%'))
                 LIMIT 1),
                aem.subject_id
            ) as subject_id")
        )->get();

        // B) Umumiy arizalar (fansiz)
        $excGeneralQuery = DB::table('absence_excuses as ae')
            ->join('students as s', 's.hemis_id', '=', 'ae.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->where('ae.status', 'approved')
            ->where(function ($q) {
                $q->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('absence_excuse_makeups as aem2')
                        ->whereColumn('aem2.absence_excuse_id', 'ae.id')
                        ->whereNotNull('aem2.subject_id');
                });
            });

        $applyFilters($excGeneralQuery);

        $excGeneral = $excGeneralQuery->select(
            'ae.id as excuse_id', 'ae.student_hemis_id', 's.full_name', 's.student_id_number',
            's.department_name', 's.specialty_name', 's.level_name',
            's.group_name', 's.semester_name', 's.group_id',
            'ae.start_date', 'ae.end_date', 'g.id as group_pk'
        )->get();

        // ========================================
        // 2-QADAM: HEMIS davomatini batch olish
        // ========================================
        $allStudentIds = $excWithSubject->pluck('student_hemis_id')
            ->merge($excGeneral->pluck('student_hemis_id'))
            ->unique()->values()->toArray();

        $attMap = [];
        $attByName = [];
        if (!empty($allStudentIds)) {
            $attQuery = DB::table('attendances as a')
                ->whereIn('a.student_hemis_id', $allStudentIds)
                ->where(function ($q) {
                    $q->where('a.absent_on', '>', 0)->orWhere('a.absent_off', '>', 0);
                });

            if ($request->filled('semester_code')) {
                $attQuery->where('a.semester_code', $request->semester_code);
            }

            // Joriy semestr filtri
            if ($request->get('current_semester', '1') == '1') {
                $attQuery->where('a.education_year_current', true)
                         ->whereExists(function ($sub) {
                             $sub->select(DB::raw(1))
                                 ->from('students as st')
                                 ->whereColumn('st.hemis_id', 'a.student_hemis_id')
                                 ->whereColumn('a.semester_code', 'st.semester_code');
                         });
            }

            $attRows = $attQuery
                ->select('a.student_hemis_id', 'a.subject_id', 'a.subject_name',
                    'a.lesson_date', 'a.lesson_pair_start_time', 'a.lesson_pair_end_time',
                    'a.absent_on', 'a.absent_off', 'a.semester_code',
                    'a.training_type_code', 'a.training_type_name')
                ->get();

            foreach ($attRows as $att) {
                $attMap[$att->student_hemis_id . '|' . $att->subject_id][] = $att;
                $attMap['general|' . $att->student_hemis_id][] = $att;
                $attByName[$att->student_hemis_id][mb_strtolower(trim($att->subject_name))][] = $att;
            }
        }

        $semesterNames = DB::table('semesters')->pluck('name', 'code')->toArray();

        // ========================================
        // 3-QADAM: Arizalarni HEMIS bilan solishtirish
        // ========================================
        $results = [];
        $debugLog = [];
        $resolveLog = [];
        $addedKeys = [];

        // A) Fanga bog'langan arizalar
        foreach ($excWithSubject as $exc) {
            // Resolve log: subject_id o'zgargan bo'lsa
            if (isset($exc->aem_subject_id) && $exc->aem_subject_id != $exc->subject_id) {
                $resolveLog[] = [
                    'student_hemis_id' => $exc->student_hemis_id,
                    'full_name' => $exc->full_name ?? '-',
                    'subject_name' => $exc->subject_name ?? '',
                    'original_id' => $exc->aem_subject_id,
                    'resolved_id' => $exc->subject_id,
                    'excuse_id' => $exc->excuse_id,
                ];
            }

            $excKey = $exc->excuse_id . '|' . $exc->subject_id;
            if (isset($addedKeys[$excKey])) continue;
            $addedKeys[$excKey] = true;

            $startDate = substr($exc->start_date, 0, 10);
            $endDate = substr($exc->end_date, 0, 10);

            // HEMIS da fan topish: 1) subject_id, 2) aniq nom, 3) fuzzy nom
            $hemisRecords = $attMap[$exc->student_hemis_id . '|' . $exc->subject_id] ?? [];
            $matchMethod = !empty($hemisRecords) ? 'subject_id' : 'topilmadi';

            if (empty($hemisRecords) && !empty($exc->subject_name) && isset($attByName[$exc->student_hemis_id])) {
                $norm = mb_strtolower(trim($exc->subject_name));
                $byName = $attByName[$exc->student_hemis_id];
                if (isset($byName[$norm])) {
                    $hemisRecords = $byName[$norm];
                    $matchMethod = 'fan_nomi_aniq';
                } else {
                    foreach ($byName as $n => $recs) {
                        if (str_contains($n, $norm) || str_contains($norm, $n)) {
                            $hemisRecords = $recs;
                            $matchMethod = 'fan_nomi_fuzzy';
                            break;
                        }
                    }
                }
            }

            // Sana oralig'idagi yozuvlar
            $pairs = [];
            $totalOn = 0; $totalOff = 0;
            foreach ($hemisRecords as $att) {
                $d = substr($att->lesson_date, 0, 10);
                if ($d >= $startDate && $d <= $endDate) {
                    $totalOn += (int)$att->absent_on;
                    $totalOff += (int)$att->absent_off;
                    $pairs[] = [
                        'lesson_date' => date('d.m.Y', strtotime($d)),
                        'lesson_date_raw' => $d,
                        'lesson_pair' => ($att->lesson_pair_start_time && $att->lesson_pair_end_time) ? $att->lesson_pair_start_time.'-'.$att->lesson_pair_end_time : '-',
                        'hemis_status' => ((int)$att->absent_on > 0) ? 'Sababli' : 'Sababsiz',
                        'mark_status' => 'Sababli (ariza)',
                        'absent_on' => (int)$att->absent_on,
                        'absent_off' => (int)$att->absent_off,
                        'training_type' => $att->training_type_name ?? '-',
                    ];
                }
            }

            $totalHours = $totalOn + $totalOff;
            if (empty($pairs)) {
                $hemisStatus = "Ma'lumot yo'q";
                $match = 'match';
                $pairs = [['lesson_date' => date('d.m.Y', strtotime($startDate)).' — '.date('d.m.Y', strtotime($endDate)),
                    'lesson_date_raw' => $startDate, 'lesson_pair' => '-', 'hemis_status' => $hemisStatus,
                    'mark_status' => 'Sababli (ariza)', 'absent_on' => 0, 'absent_off' => 0, 'training_type' => '-']];
            } else {
                $hemisStatus = ($totalOn > 0 && $totalOff > 0) ? 'Aralash' : (($totalOn > 0) ? 'Sababli' : 'Sababsiz');
                $match = in_array($hemisStatus, ['Sababli', 'Aralash']) ? 'match' : 'mismatch';
                usort($pairs, fn($a, $b) => strcmp($a['lesson_date_raw'], $b['lesson_date_raw']));
            }

            $semCode = !empty($hemisRecords) ? $hemisRecords[0]->semester_code : null;
            $results[] = [
                'student_hemis_id' => $exc->student_hemis_id,
                'student_id_number' => $exc->student_id_number ?? '-',
                'full_name' => $exc->full_name ?? '-',
                'department_name' => $exc->department_name ?? '-',
                'specialty_name' => $exc->specialty_name ?? '-',
                'level_name' => $exc->level_name ?? '-',
                'group_name' => $exc->group_name ?? '-',
                'semester_name' => ($semCode ? ($semesterNames[$semCode] ?? $exc->semester_name) : $exc->semester_name) ?? '-',
                'subject_name' => $exc->subject_name ?? '-',
                'subject_id' => $exc->subject_id,
                'excuse_id' => $exc->excuse_id,
                'excuse_start' => date('d.m.Y', strtotime($startDate)),
                'excuse_end' => date('d.m.Y', strtotime($endDate)),
                'total_absent_on' => $totalOn, 'total_absent_off' => $totalOff, 'total_hours' => $totalHours,
                'hemis_status' => $hemisStatus, 'mark_status' => 'Sababli (ariza)', 'match' => $match,
                'pairs' => $pairs,
                'journal_url' => ($exc->group_pk && $exc->subject_id && $semCode)
                    ? route('admin.journal.show', ['groupId' => $exc->group_pk, 'subjectId' => $exc->subject_id, 'semesterCode' => $semCode]) : '#',
                'match_method' => $matchMethod,
                'aem_subject_id' => $exc->aem_subject_id ?? null,
            ];

            // Debug log
            $availSubjects = [];
            if (isset($attByName[$exc->student_hemis_id])) {
                foreach ($attByName[$exc->student_hemis_id] as $n => $recs) {
                    $availSubjects[] = $recs[0]->subject_id . ': ' . $recs[0]->subject_name;
                }
            }
            $debugLog[] = [
                'student_hemis_id' => $exc->student_hemis_id, 'full_name' => $exc->full_name ?? '-',
                'group_name' => $exc->group_name ?? '-', 'ariza_subject_name' => $exc->subject_name ?? '-',
                'ariza_subject_id' => $exc->subject_id, 'match_method' => $matchMethod,
                'hemis_subject_id' => !empty($hemisRecords) ? $hemisRecords[0]->subject_id : null,
                'hemis_subject_name' => !empty($hemisRecords) ? $hemisRecords[0]->subject_name : null,
                'found' => !empty($hemisRecords),
                'debug_status' => empty($pairs) || $pairs[0]['hemis_status'] === 'Fan topilmadi' ? 'fan_topilmadi' : (!empty($hemisRecords) && $totalHours > 0 ? 'topildi' : ($matchMethod !== 'topilmadi' ? 'sana_mos_emas' : 'fan_topilmadi')),
                'ariza_dates' => date('d.m.Y', strtotime($startDate)).' — '.date('d.m.Y', strtotime($endDate)),
                'hemis_date_range' => !empty($hemisRecords) ? date('d.m.Y', strtotime(substr($hemisRecords[0]->lesson_date,0,10))).' — '.date('d.m.Y', strtotime(substr(end($hemisRecords)->lesson_date,0,10))) : '-',
                'hemis_total_records' => count($hemisRecords), 'hemis_in_range' => count($pairs) - (empty($totalHours) && count($pairs) === 1 ? 1 : 0),
                'hemis_available' => implode(' | ', array_slice($availSubjects, 0, 15)),
            ];
        }

        // B) Umumiy arizalar (fansiz)
        foreach ($excGeneral as $exc) {
            $excKey = 'general|' . $exc->excuse_id;
            if (isset($addedKeys[$excKey])) continue;
            $addedKeys[$excKey] = true;

            $startDate = substr($exc->start_date, 0, 10);
            $endDate = substr($exc->end_date, 0, 10);
            $hemisRecords = $attMap['general|' . $exc->student_hemis_id] ?? [];
            $pairs = []; $totalOn = 0; $totalOff = 0;
            foreach ($hemisRecords as $att) {
                $d = substr($att->lesson_date, 0, 10);
                if ($d >= $startDate && $d <= $endDate) {
                    $totalOn += (int)$att->absent_on; $totalOff += (int)$att->absent_off;
                    $pairs[] = ['lesson_date' => date('d.m.Y', strtotime($d)), 'lesson_date_raw' => $d,
                        'lesson_pair' => ($att->lesson_pair_start_time && $att->lesson_pair_end_time) ? $att->lesson_pair_start_time.'-'.$att->lesson_pair_end_time : '-',
                        'hemis_status' => ((int)$att->absent_on > 0) ? 'Sababli' : 'Sababsiz',
                        'mark_status' => 'Sababli (ariza)', 'absent_on' => (int)$att->absent_on, 'absent_off' => (int)$att->absent_off,
                        'training_type' => $att->training_type_name ?? '-'];
                }
            }
            $totalHours = $totalOn + $totalOff;
            if (empty($pairs)) {
                $hemisStatus = 'Davomat topilmadi'; $match = 'mismatch';
                $pairs = [['lesson_date' => date('d.m.Y', strtotime($startDate)).' — '.date('d.m.Y', strtotime($endDate)),
                    'lesson_date_raw' => $startDate, 'lesson_pair' => '-', 'hemis_status' => 'Davomat topilmadi',
                    'mark_status' => 'Sababli (ariza)', 'absent_on' => 0, 'absent_off' => 0]];
            } else {
                $hemisStatus = ($totalOn > 0 && $totalOff > 0) ? 'Aralash' : (($totalOn > 0) ? 'Sababli' : 'Sababsiz');
                $match = in_array($hemisStatus, ['Sababli', 'Aralash']) ? 'match' : 'mismatch';
                usort($pairs, fn($a, $b) => strcmp($a['lesson_date_raw'], $b['lesson_date_raw']));
            }
            $results[] = [
                'student_hemis_id' => $exc->student_hemis_id,
                'student_id_number' => $exc->student_id_number ?? '-',
                'full_name' => $exc->full_name ?? '-', 'department_name' => $exc->department_name ?? '-',
                'specialty_name' => $exc->specialty_name ?? '-', 'level_name' => $exc->level_name ?? '-',
                'group_name' => $exc->group_name ?? '-', 'semester_name' => $exc->semester_name ?? '-',
                'subject_name' => 'Umumiy ariza: '.date('d.m.Y', strtotime($startDate)).' - '.date('d.m.Y', strtotime($endDate)),
                'subject_id' => null,
                'excuse_id' => $exc->excuse_id,
                'excuse_start' => date('d.m.Y', strtotime($startDate)),
                'excuse_end' => date('d.m.Y', strtotime($endDate)),
                'total_absent_on' => $totalOn, 'total_absent_off' => $totalOff, 'total_hours' => $totalHours,
                'hemis_status' => $hemisStatus, 'mark_status' => 'Sababli (ariza)', 'match' => $match,
                'pairs' => $pairs, 'journal_url' => '#',
            ];
        }

        // Qidirish filtri
        if ($request->filled('search')) {
            $search = mb_strtolower($request->search);
            $results = array_values(array_filter($results, function ($r) use ($search) {
                return str_contains(mb_strtolower($r['full_name'] ?? ''), $search)
                    || str_contains(mb_strtolower($r['group_name'] ?? ''), $search)
                    || str_contains(mb_strtolower($r['subject_name'] ?? ''), $search)
                    || str_contains(mb_strtolower($r['department_name'] ?? ''), $search)
                    || str_contains(mb_strtolower($r['student_hemis_id'] ?? ''), $search)
                    || str_contains(mb_strtolower($r['specialty_name'] ?? ''), $search);
            }));
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

        // DEBUG LOG: student_subjects dan resolve qilib, attendance da topilmaganlarni aniqlash (faqat 10-semestr)
        $debugLog = [];
        $allMakeupRows = DB::table('absence_excuse_makeups as aem')
            ->join('absence_excuses as ae', 'ae.id', '=', 'aem.absence_excuse_id')
            ->join('students as s', 's.hemis_id', '=', 'ae.student_hemis_id')
            ->where('ae.status', 'approved')
            ->whereNotNull('aem.subject_id')
            ->where('s.semester_code', '10')
            ->select(
                'aem.id as makeup_id',
                'aem.subject_name',
                'aem.subject_id',
                'ae.student_hemis_id',
                'ae.start_date',
                'ae.end_date',
                's.full_name',
                's.group_name'
            )
            ->get();

        foreach ($allMakeupRows as $m) {
            // student_subjects dan subject_id ni resolve qilish
            $ssResult = DB::table('student_subjects')
                ->where('student_hemis_id', $m->student_hemis_id)
                ->where('subject_name', $m->subject_name)
                ->select('subject_id')
                ->first();

            $resolvedId = $ssResult->subject_id ?? $m->subject_id;
            $resolvedVia = $ssResult ? 'student_subjects' : 'aem.subject_id (original)';

            // attendance da resolved_id bilan nb yozuvi bor-yo'qligini tekshirish
            $attExists = DB::table('attendances')
                ->where('student_hemis_id', $m->student_hemis_id)
                ->where('subject_id', $resolvedId)
                ->where(function ($q) {
                    $q->where('absent_on', '>', 0)->orWhere('absent_off', '>', 0);
                })
                ->exists();

            // Faqat topilmaganlarni log ga qo'shish
            if (!$attExists) {
                // attendance da shu talaba uchun qanday subject_id lar bor ekanligini tekshirish
                $existingSubjects = DB::table('attendances')
                    ->where('student_hemis_id', $m->student_hemis_id)
                    ->where(function ($q) {
                        $q->where('absent_on', '>', 0)->orWhere('absent_off', '>', 0);
                    })
                    ->select('subject_id', 'subject_name')
                    ->distinct()
                    ->limit(10)
                    ->get()
                    ->map(fn($r) => $r->subject_id . ' (' . $r->subject_name . ')')
                    ->implode(', ');

                $debugLog[] = [
                    'makeup_id' => $m->makeup_id,
                    'student_hemis_id' => $m->student_hemis_id,
                    'full_name' => $m->full_name,
                    'group_name' => $m->group_name,
                    'subject_name' => $m->subject_name,
                    'original_id' => $m->subject_id,
                    'resolved_id' => $resolvedId,
                    'resolved_via' => $resolvedVia,
                    'att_exists' => false,
                    'reason' => 'attendance da nb topilmadi. Mavjud fanlar: ' . ($existingSubjects ?: 'hech qaysi'),
                ];
            }
        }

        return response()->json([
            'data' => $pageData,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / max($perPage, 1)),
            'match_count' => $matchCount,
            'mismatch_count' => $mismatchCount,
            'debug_log' => $debugLog,
            'resolve_log' => $resolveLog,
        ]);
      } catch (\Exception $e) {
            return response()->json([
                'data' => [], 'total' => 0, 'match_count' => 0, 'mismatch_count' => 0, 'debug_log' => [],
                'error' => $e->getMessage(), 'error_line' => $e->getFile() . ':' . $e->getLine(),
            ]);
      }
    }

    /**
     * Sababli check: talabaning attendance detallari (absent_off > 0).
     */
    public function sababliCheckAttendanceDetail(Request $request)
    {
        $request->validate([
            'student_hemis_id' => 'required',
            'subject_id' => 'required',
        ]);

        $query = DB::table('attendances as a')
            ->where('a.student_hemis_id', $request->student_hemis_id)
            ->where(function ($q) {
                $q->where('a.absent_on', '>', 0)->orWhere('a.absent_off', '>', 0);
            });

        // subject_id = 0 bo'lsa barcha fanlarni olish
        if ($request->subject_id && $request->subject_id != '0') {
            $query->where('a.subject_id', $request->subject_id);
        }

        if ($request->get('current_semester', '1') == '1') {
            $query->where('a.education_year_current', true)
                  ->whereExists(function ($sub) {
                      $sub->select(DB::raw(1))
                          ->from('students as st')
                          ->whereColumn('st.hemis_id', 'a.student_hemis_id')
                          ->whereColumn('a.semester_code', 'st.semester_code');
                  });
        }

        $rows = $query->select(
            'a.subject_id', 'a.subject_name', 'a.lesson_date',
            'a.absent_on', 'a.absent_off',
            'a.semester_code', 'a.semester_name',
            'a.education_year_code', 'a.education_year_name', 'a.education_year_current',
            'a.training_type_code', 'a.training_type_name',
            'a.lesson_pair_start_time', 'a.lesson_pair_end_time'
        )->orderBy('a.lesson_date')->get();

        return response()->json(['rows' => $rows]);
    }

    /**
     * Sababli check Excel export
     */
    private function exportSababliCheckExcel(array $data)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sababli check');

        $headers = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Guruh', 'Semestr', 'Fan', 'Sana', 'Juftlik', 'Mark', 'HEMIS holati', 'Natija'];
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
            $sheet->setCellValue([2, $row], $r['full_name']);
            $sheet->setCellValue([3, $row], $r['department_name']);
            $sheet->setCellValue([4, $row], $r['specialty_name']);
            $sheet->setCellValue([5, $row], $r['level_name']);
            $sheet->setCellValue([6, $row], $r['group_name']);
            $sheet->setCellValue([7, $row], $r['semester_name'] ?? '-');
            $sheet->setCellValue([8, $row], $r['subject_name']);
            $sheet->setCellValue([9, $row], $r['lesson_date']);
            $sheet->setCellValue([10, $row], $r['lesson_pair'] ?? '-');
            $sheet->setCellValue([11, $row], $r['mark_status']);
            $sheet->setCellValue([12, $row], $r['hemis_status']);
            $sheet->setCellValue([13, $row], $r['match'] === 'match' ? 'Mos' : 'Mos emas');

            // Natija rangini qo'yish
            if ($r['match'] === 'match') {
                $sheet->getStyle("M{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D1FAE5']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                ]);
            } else {
                $sheet->getStyle("M{$row}")->applyFromArray([
                    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
                ]);
            }
        }

        $widths = [5, 30, 25, 30, 8, 15, 15, 35, 12, 20, 18, 18, 12];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:M{$lastRow}")->applyFromArray([
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
        if (is_active_nazoratchi()) {
            abort(403, 'Bu hisobotga ruxsatingiz yo\'q.');
        }
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
        if (is_active_nazoratchi()) {
            abort(403, 'Bu hisobotga ruxsatingiz yo\'q.');
        }
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

            // Schedule dan date+pair columns olish (jnReportData formulasi bilan bir xil)
            $scheduleDetailQuery = DB::table('schedules as sch2')
                ->whereNotIn('sch2.training_type_code', $excludedCodes)
                ->whereNotNull('sch2.lesson_date')
                ->whereIn('sch2.group_id', $scheduleGroupIds)
                ->whereIn('sch2.subject_id', $validSubjectIds)
                ->whereIn('sch2.semester_code', $validSemesterCodes)
                ->select('sch2.group_id', 'sch2.subject_id', 'sch2.semester_code', 'sch2.lesson_date', 'sch2.lesson_pair_code');

            if ($isCurrentSemester) {
                $scheduleDetailQuery
                    ->join('groups as gr3', 'gr3.group_hemis_id', '=', 'sch2.group_id')
                    ->join('semesters as sem3', function ($join) {
                        $join->on('sem3.code', '=', 'sch2.semester_code')
                            ->on('sem3.curriculum_hemis_id', '=', 'gr3.curriculum_hemis_id');
                    })
                    ->where('sem3.current', true)
                    ->whereNull('sch2.deleted_at');
            }

            $columns = [];    // [combo_key][date_pair] = true
            $minDates = [];   // [combo_key] = eng kichik sana

            foreach ($scheduleDetailQuery->get() as $schRow) {
                $dateKey = substr($schRow->lesson_date, 0, 10);
                $comboKey = $schRow->group_id . '|' . $schRow->subject_id . '|' . $schRow->semester_code;
                $datePairKey = $dateKey . '_' . $schRow->lesson_pair_code;
                $columns[$comboKey][$datePairKey] = true;

                if (!isset($minDates[$comboKey]) || $dateKey < $minDates[$comboKey]) {
                    $minDates[$comboKey] = $dateKey;
                }
            }

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

            // 3-QADAM: student_grades dan faqat JN baholarni olish (jnReportData formulasi)
            $cutoffDate = Carbon::now('Asia/Tashkent')->format('Y-m-d');

            $gradesByDay = [];      // [student|subject|date] => [pair_code => grade]
            $studentSubjects = [];  // [student|subject] => info

            foreach (array_chunk($studentHemisIds, 1000) as $hemisChunk) {
                $gradesChunk = DB::table('student_grades')
                    ->whereIn('student_hemis_id', $hemisChunk)
                    ->whereIn('subject_id', $validSubjectIds)
                    ->whereIn('semester_code', $validSemesterCodes)
                    ->whereNotIn('training_type_code', $excludedCodes)
                    ->where(function ($q) {
                        $q->whereNotNull('grade')->orWhereNotNull('retake_grade');
                    })
                    ->whereNotNull('lesson_date')
                    ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                        'grade', 'lesson_date', 'lesson_pair_code', 'retake_grade', 'status', 'reason')
                    ->get();

                foreach ($gradesChunk as $g) {
                    $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
                    if (!$groupId) continue;

                    $comboKey = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
                    $minDate = $minDates[$comboKey] ?? null;

                    $dateKey = substr($g->lesson_date, 0, 10);
                    if ($minDate && $dateKey < $minDate) continue;

                    // Grade date_pair ni columns ga birlashtirish (jurnal fallback logikasi)
                    $datePairKey = $dateKey . '_' . $g->lesson_pair_code;
                    $columns[$comboKey][$datePairKey] = true;

                    // Baholarni kun bo'yicha guruhlash (pair_code bo'yicha deduplikatsiya)
                    $gradeKey = $g->student_hemis_id . '|' . $g->subject_id . '|' . $dateKey;
                    $effectiveGrade = $this->getEffectiveGradeForJn($g);
                    if ($effectiveGrade !== null) {
                        $gradesByDay[$gradeKey][$g->lesson_pair_code] = $effectiveGrade;
                    }

                    // Student-subject ma'lumotlarini saqlash
                    $ssKey = $g->student_hemis_id . '|' . $g->subject_id;
                    if (!isset($studentSubjects[$ssKey])) {
                        $studentSubjects[$ssKey] = [
                            'hemis_id' => $g->student_hemis_id,
                            'subject_id' => $g->subject_id,
                            'subject_name' => $g->subject_name,
                            'semester_code' => $g->semester_code,
                            'combo_key' => $comboKey,
                        ];
                    }
                }
                unset($gradesChunk);
            }

            // pairs_per_day va lesson_dates ni yakuniy columns dan hisoblash (jnReportData kabi)
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

            // 4-QADAM: Har bir talaba-fan uchun JN o'rtachasini hisoblash (jnReportData formulasi)
            $studentResults = [];

            foreach ($studentSubjects as $ssKey => $info) {
                $comboKey = $info['combo_key'];
                $comboLessonDates = $lessonDates[$comboKey] ?? [];

                $dailySum = 0;
                $daysForAverage = 0;
                $lastDate = null;

                foreach ($comboLessonDates as $dateKey) {
                    if ($dateKey > $cutoffDate) continue;

                    $gradeKey = $info['hemis_id'] . '|' . $info['subject_id'] . '|' . $dateKey;
                    $dayGrades = $gradesByDay[$gradeKey] ?? [];

                    $ppdKey = $comboKey . '|' . $dateKey;
                    $pairsInDay = $pairsPerDay[$ppdKey] ?? 1;

                    $gradeSum = array_sum($dayGrades);
                    $dailyAvg = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);

                    $dailySum += $dailyAvg;
                    $daysForAverage++;
                    $lastDate = $dateKey;
                }

                $jnAverage = $daysForAverage > 0
                    ? round($dailySum / $daysForAverage, 0, PHP_ROUND_HALF_UP)
                    : 0;

                $groupId = explode('|', $comboKey)[0];
                if ($jnAverage < $scoreLimit) {
                    $studentResults[] = [
                        'hemis_id' => $info['hemis_id'],
                        'subject_id' => $info['subject_id'],
                        'subject_name' => $info['subject_name'],
                        'semester_code' => $info['semester_code'],
                        'group_id' => $groupId,
                        'grade' => $jnAverage,
                        'days_count' => $daysForAverage,
                        'last_date' => $lastDate,
                    ];
                }
            }
            unset($gradesByDay, $studentSubjects, $columns);

            if (empty($studentResults)) {
                return response()->json(['data' => [], 'total' => 0, 'per_page' => 50, 'current_page' => 1, 'last_page' => 1]);
            }

            // Talaba ma'lumotlarini biriktirish
            $hemisIds = array_unique(array_column($studentResults, 'hemis_id'));
            $studentInfo = DB::table('students')
                ->whereIn('hemis_id', $hemisIds)
                ->select('hemis_id', 'full_name', 'student_id_number', 'department_name',
                    'specialty_name', 'level_name', 'semester_name', 'group_name', 'group_id')
                ->get()
                ->keyBy('hemis_id');

            // Har bir talaba-fan bitta qator
            $finalResults = [];
            foreach ($studentResults as $result) {
                $st = $studentInfo[$result['hemis_id']] ?? null;
                if (!$st) continue;

                $finalResults[] = [
                    'hemis_id' => $result['hemis_id'],
                    'full_name' => $st->full_name ?? 'Noma\'lum',
                    'student_id_number' => $st->student_id_number ?? '-',
                    'department_name' => $st->department_name ?? '-',
                    'specialty_name' => $st->specialty_name ?? '-',
                    'level_name' => $st->level_name ?? '-',
                    'semester_name' => $st->semester_name ?? '-',
                    'group_name' => $st->group_name ?? '-',
                    'group_id' => $groupDbIdMap[$st->group_id] ?? '',
                    'subject_name' => $result['subject_name'],
                    'subject_id' => $result['subject_id'],
                    'semester_code' => $result['semester_code'],
                    'grade' => $result['grade'],
                    'days_count' => $result['days_count'],
                    'lesson_date' => $result['last_date'] ? \Carbon\Carbon::parse($result['last_date'])->format('d.m.Y') : '-',
                    'lesson_date_raw' => $result['last_date'] ?? '',
                ];
            }

            // Saralash
            $sortColumn = $request->get('sort', 'grade');
            $sortDirection = $request->get('direction', 'asc');
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
            'Fan', "JN o'rtacha", 'Darslar soni', 'Oxirgi dars'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

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
            $sheet->setCellValue([11, $row], $r['days_count'] ?? '-');
            $sheet->setCellValue([12, $row], $r['lesson_date']);

            if ($r['grade'] < $scoreLimit) {
                $sheet->getStyle("J{$row}")->applyFromArray($redFill);
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 25, 12, 12, 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:L{$lastRow}")->applyFromArray([
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
            'Fan', "JN o'rtacha", 'Darslar soni', 'Oxirgi dars'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

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
            $sheet->setCellValue([11, $row], $r['days_count'] ?? '-');
            $sheet->setCellValue([12, $row], $r['lesson_date']);

            if ($r['grade'] < $scoreLimit) {
                $sheet->getStyle("J{$row}")->applyFromArray($redFill);
            }
        }

        $widths = [5, 30, 15, 25, 30, 8, 10, 15, 25, 12, 12, 14];
        foreach ($widths as $col => $w) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($w);
        }

        $lastRow = count($data) + 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:L{$lastRow}")->applyFromArray([
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

    public function getUsersWithoutRatingsEmployees(Request $request)
    {
        $query = DB::table('schedules as sch')
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at')
            ->whereNotNull('sch.employee_id');

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('sch.faculty_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('department_id')) {
            $query->where('sch.department_id', $request->department_id);
        }

        return $query->select('sch.employee_id', 'sch.employee_name')
            ->groupBy('sch.employee_id', 'sch.employee_name')
            ->orderBy('sch.employee_name')
            ->get()
            ->pluck('employee_name', 'employee_id');
    }

    public function usersWithoutRatingsData(Request $request)
    {
        $results = $this->getUsersWithoutRatingsResults($request);

        // Excel export
        if ($request->get('export') === 'excel') {
            return $this->exportUsersWithoutRatingsExcel($results);
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

    private function getUsersWithoutRatingsResults(Request $request): array
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $gradeExcludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test", "Klinik mashg'ulot", "Klinik mashgulot"];
        // Ma'ruza = training_type_code 11 — DB'dagi apostrof varianti farq qilsa ham ushlaymiz
        $gradeExcludedCodes = [11, 17, 99, 100, 101, 102, 103];

        // 1-QADAM: Asosiy jadval so'rovi (filtrlar bilan)
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_name', $gradeExcludedNames)
            ->whereNotIn('sch.training_type_code', $gradeExcludedCodes)
            // REGEXP orqali "ma...ruza" pattern'ini har qanday apostrof varianti
            // (ASCII ', U+2018, U+2019, U+02BB, U+02BC, backtick) yoki apostrofsiz
            // (Maruza) holatda ushlaymiz. .{0,3} - "ma" va "ruza" o'rtasida 0..3 ta belgi.
            ->whereRaw("LOWER(IFNULL(sch.training_type_name, '')) NOT REGEXP 'ma.{0,3}ruza'")
            ->where('sch.education_year_current', true)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->whereRaw('DATE(sch.lesson_date) < CURDATE()');

        // Baho qo'yilmaydigan fanlarni chiqarish (masalan, O'quv amaliyoti)
        $excludedPatterns = ["tanishuv amaliyoti", "quv amaliyoti"];
        foreach ($excludedPatterns as $pattern) {
            $scheduleQuery->where('sch.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

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

        if ($request->filled('employee')) {
            $scheduleQuery->where('sch.employee_id', $request->employee);
        }

        if ($request->filled('date_from')) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$request->date_from]);
        }

        if ($request->filled('date_to')) {
            $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$request->date_to]);
        }

        // 2-QADAM: Baho mavjudligini SQL darajasida tekshirish (NOT EXISTS)
        // 1-usul: subject_schedule_id orqali
        $scheduleQuery->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('student_grades as sg1')
                ->whereColumn('sg1.subject_schedule_id', 'sch.schedule_hemis_id')
                ->whereNull('sg1.deleted_at')
                ->where(function ($q2) {
                    $q2->where('sg1.grade', '>', 0)
                       ->orWhere('sg1.retake_grade', '>', 0)
                       ->orWhere('sg1.status', 'recorded');
                });
        });

        // 2-usul: guruh + fan + sana orqali
        $scheduleQuery->whereNotExists(function ($q) {
            $q->select(DB::raw(1))
                ->from('student_grades as sg2')
                ->join('students as st', 'st.hemis_id', '=', 'sg2.student_hemis_id')
                ->whereColumn('st.group_id', 'sch.group_id')
                ->whereColumn('sg2.subject_id', 'sch.subject_id')
                ->whereRaw('DATE(sg2.lesson_date) = DATE(sch.lesson_date)')
                ->whereNull('sg2.deleted_at')
                ->whereNotNull('sg2.lesson_date')
                ->where(function ($q2) {
                    $q2->where('sg2.grade', '>', 0)
                       ->orWhere('sg2.retake_grade', '>', 0)
                       ->orWhere('sg2.status', 'recorded');
                });
        });

        // 3-QADAM: Natijani olish — faqat baho qo'yilmaganlar + dars ochilganlik holati
        $schedules = $scheduleQuery->select(
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
            DB::raw('DATE(sch.lesson_date) as lesson_date_str'),
            DB::raw('EXISTS (SELECT 1 FROM lesson_openings lo WHERE lo.group_hemis_id = sch.group_id AND lo.subject_id = sch.subject_id AND lo.semester_code = sch.semester_code AND DATE(lo.lesson_date) = DATE(sch.lesson_date)) as has_opening')
        )->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        // 4-QADAM: Guruhlab, dublikatlarni olib tashlash
        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

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
                    'has_opening' => (bool) $sch->has_opening,
                ];
            }
        }

        $results = array_values($grouped);

        // "Dars ochilgan" filtri
        if ($request->filled('lesson_opened')) {
            $filterOpened = $request->lesson_opened === '1';
            $results = array_values(array_filter($results, function ($item) use ($filterOpened) {
                return $item['has_opening'] === $filterOpened;
            }));
        }

        return $results;
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

    public function sendUsersWithoutRatingsTelegram(Request $request)
    {
        $employees = $request->input('employees', []);
        if (empty($employees) || !is_array($employees)) {
            return response()->json(['success' => false, 'message' => 'O\'qituvchilar tanlanmagan'], 422);
        }

        $telegram = new TelegramService();
        $sentCount = 0;
        $failedCount = 0;
        $noTelegramCount = 0;

        foreach ($employees as $emp) {
            $employeeId = $emp['employee_id'] ?? null;
            if (!$employeeId) {
                $failedCount++;
                continue;
            }

            $teacher = Teacher::where('hemis_id', $employeeId)->first();

            if (!$teacher || !$teacher->telegram_chat_id) {
                $noTelegramCount++;
                continue;
            }

            $lines = [];
            $lines[] = "Hurmatli {$teacher->full_name}!\n";
            $lines[] = "Sizda quyidagi darslarda baho qo'yilmagan:\n";

            foreach ($emp['lessons'] as $lesson) {
                $date = $lesson['lesson_date'] ?? '';
                $subject = $lesson['subject_name'] ?? '';
                $group = $lesson['group_name'] ?? '';
                $type = $lesson['training_type'] ?? '';
                $lines[] = "  - {$date} | {$subject} | {$group} | {$type}";
            }

            $lines[] = "\nIltimos, tezroq baholarni kiriting.";
            $lines[] = "\nHurmat bilan,\nRegistrator ofisi";

            $message = implode("\n", $lines);

            try {
                $telegram->sendToUser($teacher->telegram_chat_id, $message);
                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'no_telegram' => $noTelegramCount,
        ]);
    }

    public function sendAllUsersWithoutRatingsTelegram(Request $request)
    {
        $results = $this->getUsersWithoutRatingsResults($request);

        if (empty($results)) {
            return response()->json(['success' => false, 'message' => 'Ma\'lumot topilmadi'], 422);
        }

        // O'qituvchilar bo'yicha guruhlash
        $byEmployee = [];
        foreach ($results as $r) {
            $empId = $r['employee_id'];
            if (!isset($byEmployee[$empId])) {
                $byEmployee[$empId] = [];
            }
            $byEmployee[$empId][] = $r;
        }

        $telegram = new TelegramService();
        $sentCount = 0;
        $failedCount = 0;
        $noTelegramCount = 0;

        foreach ($byEmployee as $employeeId => $lessons) {
            $teacher = Teacher::where('hemis_id', $employeeId)->first();

            if (!$teacher || !$teacher->telegram_chat_id) {
                $noTelegramCount++;
                continue;
            }

            $lines = [];
            $lines[] = "Hurmatli {$teacher->full_name}!\n";
            $lines[] = "Sizda quyidagi darslarda baho qo'yilmagan:\n";

            foreach ($lessons as $lesson) {
                $lines[] = "  - {$lesson['lesson_date']} | {$lesson['subject_name']} | {$lesson['group_name']} | {$lesson['training_type']}";
            }

            $lines[] = "\nIltimos, tezroq baholarni kiriting.";
            $lines[] = "\nHurmat bilan,\nRegistrator ofisi";

            $message = implode("\n", $lines);

            try {
                $telegram->sendToUser($teacher->telegram_chat_id, $message);
                $sentCount++;
            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'sent' => $sentCount,
            'failed' => $failedCount,
            'no_telegram' => $noTelegramCount,
            'total_teachers' => count($byEmployee),
        ]);
    }

    /**
     * Davomat va baho qo'yish vaqtlari statistikasi - sahifa
     */
    public function gradingTimeStats(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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

        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        return view('admin.reports.grading-time-stats', compact(
            'faculties',
            'kafedras',
            'dekanFacultyIds'
        ));
    }

    /**
     * Davomat va baho qo'yish vaqtlari statistikasi - AJAX data
     */
    public function gradingTimeStatsData(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
            $request->merge(['faculty' => $dekanFacultyIds[0]]);
        }

        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        if (!$dateFrom || !$dateTo) {
            if ($request->get('export') === 'excel') {
                abort(422, "Sana oralig'ini tanlang");
            }
            return response()->json(['error' => 'Sana oralig\'ini tanlang'], 422);
        }

        // Faculty filter uchun department_hemis_id
        $facultyDepartmentHemisId = null;
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $facultyDepartmentHemisId = $faculty->department_hemis_id;
            }
        }

        // Kafedra filter: subject_ids
        $allowedSubjectIds = null;
        if ($request->filled('department')) {
            $allowedSubjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
        }

        // Fan filter
        $subjectFilter = $request->filled('subject') ? $request->subject : null;

        // Subject -> kafedra mapping
        $subjectKafedraMap = DB::table('curriculum_subjects')
            ->whereNotNull('department_id')
            ->whereNotNull('department_name')
            ->select('subject_id', 'department_id', 'department_name')
            ->groupBy('subject_id', 'department_id', 'department_name')
            ->get()
            ->groupBy('subject_id')
            ->map(fn($items) => $items->first());

        // Excel export - batafsil talaba ma'lumotlari
        // NOTE: Excel eksport try/catch dan TASHQARIDA ishlaydi, aks holda
        // xatolik JSON sifatida qaytariladi va foydalanuvchi window.location.href
        // orqali yuklab olishga harakat qilganda chalkash javob ko'rsatadi.
        if ($request->get('export') === 'excel') {
            return $this->exportGradingTimeStatsExcel($request, $dateFrom, $dateTo, $facultyDepartmentHemisId, $allowedSubjectIds, $subjectFilter, $subjectKafedraMap);
        }

        try {
            // student_grades uchun created_at_api (haqiqiy baho qo'yilgan vaqt)
            // attendances uchun updated_at (oxirgi yangilangan vaqt - import/sinxron paytida)
            $gradeHourExpr = "HOUR(created_at_api)";
            $attHourExpr = "HOUR(updated_at)";

            // ===================== ATTENDANCE =====================
            $attQuery = DB::table('attendances')
                ->whereBetween('updated_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($facultyDepartmentHemisId) {
                $attQuery->whereIn('student_hemis_id', function ($q) use ($facultyDepartmentHemisId) {
                    $q->select('hemis_id')->from('students')
                        ->where('department_id', $facultyDepartmentHemisId);
                });
            }
            if ($allowedSubjectIds !== null) {
                $attQuery->whereIn('subject_id', $allowedSubjectIds);
            }
            if ($subjectFilter) {
                $attQuery->where('subject_id', $subjectFilter);
            }

            // Umumiy soat kesimida - attendance
            $attHourly = (clone $attQuery)
                ->select(DB::raw("{$attHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('cnt', 'hour')
                ->toArray();

            // Fan kesimida - attendance
            $attBySubject = (clone $attQuery)
                ->select('subject_id', 'subject_name', DB::raw("{$attHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('subject_id', 'subject_name', 'hour')
                ->orderBy('subject_name')
                ->get();

            // Kafedra kesimida - attendance
            $attBySubjectForKafedra = (clone $attQuery)
                ->select('subject_id', DB::raw("{$attHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('subject_id', 'hour')
                ->get();

            $attByKafedra = $this->mapSubjectDataToKafedra($attBySubjectForKafedra, $subjectKafedraMap);

            // ===================== GRADES =====================
            $gradeQuery = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereBetween('created_at_api', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']);

            if ($facultyDepartmentHemisId) {
                $gradeQuery->whereIn('student_hemis_id', function ($q) use ($facultyDepartmentHemisId) {
                    $q->select('hemis_id')->from('students')
                        ->where('department_id', $facultyDepartmentHemisId);
                });
            }
            if ($allowedSubjectIds !== null) {
                $gradeQuery->whereIn('subject_id', $allowedSubjectIds);
            }
            if ($subjectFilter) {
                $gradeQuery->where('subject_id', $subjectFilter);
            }

            // Umumiy soat kesimida - grades
            $gradeHourly = (clone $gradeQuery)
                ->select(DB::raw("{$gradeHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->pluck('cnt', 'hour')
                ->toArray();

            // Fan kesimida - grades
            $gradeBySubject = (clone $gradeQuery)
                ->select('subject_id', 'subject_name', DB::raw("{$gradeHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('subject_id', 'subject_name', 'hour')
                ->orderBy('subject_name')
                ->get();

            // Kafedra kesimida - grades
            $gradeBySubjectForKafedra = (clone $gradeQuery)
                ->select('subject_id', DB::raw("{$gradeHourExpr} as hour"), DB::raw('COUNT(*) as cnt'))
                ->groupBy('subject_id', 'hour')
                ->get();

            $gradeByKafedra = $this->mapSubjectDataToKafedra($gradeBySubjectForKafedra, $subjectKafedraMap);

            // ========== Ma'lumotlarni formatlash ==========
            $hours = range(0, 23);

            // Umumiy
            $attTotal = array_sum($attHourly);
            $gradeTotal = array_sum($gradeHourly);

            $overallAttendance = [];
            $overallGrades = [];
            foreach ($hours as $h) {
                $overallAttendance[$h] = [
                    'count' => $attHourly[$h] ?? 0,
                    'percent' => $attTotal > 0 ? round(($attHourly[$h] ?? 0) / $attTotal * 100, 1) : 0,
                ];
                $overallGrades[$h] = [
                    'count' => $gradeHourly[$h] ?? 0,
                    'percent' => $gradeTotal > 0 ? round(($gradeHourly[$h] ?? 0) / $gradeTotal * 100, 1) : 0,
                ];
            }

            // Fan kesimida
            $subjectData = $this->formatGroupedTimeData($attBySubject, $gradeBySubject, 'subject_id', 'subject_name', $hours);

            // Kafedra kesimida
            $kafedraData = $this->formatGroupedTimeData($attByKafedra, $gradeByKafedra, 'department_id', 'department_name', $hours);

            return response()->json([
                'overall' => [
                    'attendance' => $overallAttendance,
                    'grades' => $overallGrades,
                    'attendance_total' => $attTotal,
                    'grades_total' => $gradeTotal,
                ],
                'by_subject' => $subjectData,
                'by_kafedra' => $kafedraData,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Subject bo'yicha guruhlab olingan ma'lumotlarni kafedra ga mapping qilish
     */
    private function mapSubjectDataToKafedra($subjectHourlyData, $subjectKafedraMap)
    {
        $kafedraData = [];
        foreach ($subjectHourlyData as $row) {
            $kafedra = $subjectKafedraMap[$row->subject_id] ?? null;
            if (!$kafedra) continue;

            $key = $kafedra->department_id . '|' . $row->hour;
            if (!isset($kafedraData[$key])) {
                $kafedraData[$key] = (object) [
                    'department_id' => $kafedra->department_id,
                    'department_name' => $kafedra->department_name,
                    'hour' => $row->hour,
                    'cnt' => 0,
                ];
            }
            $kafedraData[$key]->cnt += $row->cnt;
        }
        return collect(array_values($kafedraData));
    }

    private function formatGroupedTimeData($attRows, $gradeRows, $idField, $nameField, $hours)
    {
        $result = [];

        // Attendance
        $attGrouped = [];
        foreach ($attRows as $row) {
            $key = $row->$idField;
            if (!isset($attGrouped[$key])) {
                $attGrouped[$key] = ['name' => $row->$nameField, 'hours' => [], 'total' => 0];
            }
            $attGrouped[$key]['hours'][$row->hour] = $row->cnt;
            $attGrouped[$key]['total'] += $row->cnt;
        }

        // Grades
        $gradeGrouped = [];
        foreach ($gradeRows as $row) {
            $key = $row->$idField;
            if (!isset($gradeGrouped[$key])) {
                $gradeGrouped[$key] = ['name' => $row->$nameField, 'hours' => [], 'total' => 0];
            }
            $gradeGrouped[$key]['hours'][$row->hour] = $row->cnt;
            $gradeGrouped[$key]['total'] += $row->cnt;
        }

        $allKeys = array_unique(array_merge(array_keys($attGrouped), array_keys($gradeGrouped)));
        sort($allKeys);

        foreach ($allKeys as $key) {
            $name = $attGrouped[$key]['name'] ?? $gradeGrouped[$key]['name'] ?? '-';
            $attTotal = $attGrouped[$key]['total'] ?? 0;
            $gradeTotal = $gradeGrouped[$key]['total'] ?? 0;

            $attHours = [];
            $gradeHours = [];
            foreach ($hours as $h) {
                $ac = $attGrouped[$key]['hours'][$h] ?? 0;
                $gc = $gradeGrouped[$key]['hours'][$h] ?? 0;
                $attHours[$h] = [
                    'count' => $ac,
                    'percent' => $attTotal > 0 ? round($ac / $attTotal * 100, 1) : 0,
                ];
                $gradeHours[$h] = [
                    'count' => $gc,
                    'percent' => $gradeTotal > 0 ? round($gc / $gradeTotal * 100, 1) : 0,
                ];
            }

            $result[] = [
                'id' => $key,
                'name' => $name,
                'attendance' => $attHours,
                'grades' => $gradeHours,
                'attendance_total' => $attTotal,
                'grades_total' => $gradeTotal,
            ];
        }

        // Sort by name
        usort($result, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Excel export - batafsil talaba bo'yicha davomat va baho vaqtlari
     *
     * Katta hajmdagi ma'lumotlar (yuz minglab yozuvlar) uchun PhpSpreadsheet o'rniga
     * xotirada to'planmaydigan oqim (streaming) rejimidagi Box\Spout ishlatiladi.
     * StudentGradeBox va TeacherExport bilan bir xil shaklda openToBrowser orqali
     * to'g'ridan-to'g'ri javobga yoziladi.
     */
    private function exportGradingTimeStatsExcel($request, $dateFrom, $dateTo, $facultyDepartmentHemisId, $allowedSubjectIds, $subjectFilter, $subjectKafedraMap)
    {
        // Katta hajmli export uchun memory va execution time limitlarini oshirish
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $fileName = 'Vaqtlar_statistikasi_' . date('Y-m-d_H-i') . '.xlsx';
        $tempPath = tempnam(sys_get_temp_dir(), 'gts_') . '.xlsx';

        $writer = null;
        try {
            $writer = \Box\Spout\Writer\Common\Creator\WriterEntityFactory::createXLSXWriter();
            $writer->openToFile($tempPath);

            // ========== Sheet: Baholar ==========
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Baholar');

            $gradeHeaders = ['#', 'Talaba FISH', 'Fakultet', "Yo'nalish", 'Kurs', 'Semestr', 'Guruh', 'Fan', "O'qituvchi", 'Kafedra', 'Baho', 'Dars sanasi', 'Juftlik vaqti', 'Juftlik nomi', "Baho qo'yilgan sana va vaqt"];
            $writer->addRow(\Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray($gradeHeaders));

            $gradeQuery = DB::table('student_grades as sg')
                ->join('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
                ->whereNull('sg.deleted_at')
                ->whereBetween('sg.created_at_api', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
                ->select(
                    's.full_name',
                    's.department_name as faculty_name',
                    's.specialty_name',
                    's.level_name',
                    'sg.semester_name',
                    's.group_name',
                    'sg.subject_name',
                    'sg.employee_name',
                    'sg.subject_id',
                    'sg.grade',
                    'sg.lesson_pair_name',
                    'sg.lesson_pair_start_time',
                    'sg.lesson_pair_end_time',
                    DB::raw("DATE(sg.lesson_date) as lesson_date"),
                    DB::raw("DATE_FORMAT(sg.created_at_api, '%Y-%m-%d %H:%i:%s') as graded_at")
                )
                ->orderBy('sg.id');

            if ($facultyDepartmentHemisId) {
                $gradeQuery->where('s.department_id', $facultyDepartmentHemisId);
            }
            if ($allowedSubjectIds !== null) {
                $gradeQuery->whereIn('sg.subject_id', $allowedSubjectIds);
            }
            if ($subjectFilter) {
                $gradeQuery->where('sg.subject_id', $subjectFilter);
            }

            $num = 0;
            foreach ($gradeQuery->cursor() as $g) {
                $num++;
                $kafedra = $subjectKafedraMap[$g->subject_id] ?? null;
                $pairTime = '';
                if (!empty($g->lesson_pair_start_time) || !empty($g->lesson_pair_end_time)) {
                    $pairTime = trim(($g->lesson_pair_start_time ?? '') . ' - ' . ($g->lesson_pair_end_time ?? ''), ' -');
                }
                // HEMIS student_grades'da lesson_pair_name faqat raqam bo'lib keladi
                // ("8" kabi) — uni "8-juftlik" formatiga keltiramiz
                $pairName = $g->lesson_pair_name ?? '';
                if ($pairName !== '' && is_numeric(str_replace(',', '.', $pairName))) {
                    $pairName = $pairName . '-juftlik';
                }
                $writer->addRow(\Box\Spout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray([
                    $num,
                    $g->full_name,
                    $g->faculty_name,
                    $g->specialty_name,
                    $g->level_name,
                    $g->semester_name,
                    $g->group_name,
                    $g->subject_name,
                    $g->employee_name,
                    $kafedra->department_name ?? '-',
                    $g->grade,
                    $g->lesson_date,
                    $pairTime,
                    $pairName,
                    $g->graded_at,
                ]));
            }

            $writer->close();
            $writer = null;
        } catch (\Throwable $e) {
            if ($writer !== null) {
                try { $writer->close(); } catch (\Throwable $ignored) {}
            }
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
            \Illuminate\Support\Facades\Log::error('exportGradingTimeStatsExcel xatolik: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'faculty' => $facultyDepartmentHemisId,
                'subject' => $subjectFilter,
            ]);
            return response()->json([
                'error' => 'Excel fayli tayyorlashda xatolik: ' . $e->getMessage(),
            ], 500);
        }

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Test markazi vaqtlari - filtr sahifasi
     * Test markazidagi imtihon sanalari va vaqtlari belgilanganligini tekshiradi
     */
    public function testMarkaziTimes(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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

        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        }
        $kafedraQuery->where('s.current', true);

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        return view('admin.reports.test-markazi-times', compact(
            'faculties',
            'kafedras',
            'dekanFacultyIds'
        ));
    }

    /**
     * Test markazi vaqtlari - AJAX/Excel ma'lumotlari
     * exam_schedules jadvalidagi OSKI va Test imtihon sanalari va vaqtlari
     * belgilanganligini tekshiradi
     */
    public function testMarkaziTimesData(Request $request)
    {
        try {
            $dekanFacultyIds = get_dekan_faculty_ids();
            if (!empty($dekanFacultyIds) && !$request->filled('faculty')) {
                $request->merge(['faculty' => $dekanFacultyIds[0]]);
            }

            $dateFrom = $request->filled('date_from') ? $request->date_from : null;
            $dateTo = $request->filled('date_to') ? $request->date_to : null;

            // Faculty filter
            $facultyDepartmentHemisId = null;
            if ($request->filled('faculty')) {
                $faculty = Department::find($request->faculty);
                if ($faculty) {
                    $facultyDepartmentHemisId = $faculty->department_hemis_id;
                }
            }

            // Kafedra filter: subject_ids
            $allowedSubjectIds = null;
            if ($request->filled('department')) {
                $allowedSubjectIds = DB::table('curriculum_subjects')
                    ->where('department_id', $request->department)
                    ->pluck('subject_id')
                    ->unique()
                    ->toArray();
            }

            // Fan filter
            $subjectFilter = $request->filled('subject') ? $request->subject : null;

            // Semestr filter
            $semesterFilter = $request->filled('semester') ? $request->semester : null;

            // Holat filter: 'complete', 'missing_date', 'missing_time', 'na', yoki null (barchasi)
            $statusFilter = $request->filled('status') && $request->status !== '' ? $request->status : null;

            // Joriy semestr bo'yicha filtrlash
            $currentSemesterCodes = null;
            if ($request->get('current_semester', '1') === '1' && !$semesterFilter) {
                $currentSemesterCodes = DB::table('semesters')
                    ->where('current', true)
                    ->pluck('code')
                    ->unique()
                    ->toArray();
            }

            // Subject -> kafedra mapping
            $subjectKafedraMap = DB::table('curriculum_subjects')
                ->whereNotNull('department_id')
                ->whereNotNull('department_name')
                ->select('subject_id', 'department_id', 'department_name')
                ->groupBy('subject_id', 'department_id', 'department_name')
                ->get()
                ->groupBy('subject_id')
                ->map(fn($items) => $items->first());

            // Excel export
            if ($request->get('export') === 'excel') {
                return $this->exportTestMarkaziTimesExcel($request, $dateFrom, $dateTo, $facultyDepartmentHemisId, $allowedSubjectIds, $subjectFilter, $semesterFilter, $statusFilter, $currentSemesterCodes, $subjectKafedraMap);
            }

            // Asosiy query - exam_schedules jadvali
            $baseQuery = DB::table('exam_schedules');

            if ($dateFrom || $dateTo) {
                $baseQuery->where(function ($q) use ($dateFrom, $dateTo) {
                    $q->where(function ($q2) use ($dateFrom, $dateTo) {
                        if ($dateFrom) $q2->where('oski_date', '>=', $dateFrom);
                        if ($dateTo) $q2->where('oski_date', '<=', $dateTo);
                    })->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                        if ($dateFrom) $q2->where('test_date', '>=', $dateFrom);
                        if ($dateTo) $q2->where('test_date', '<=', $dateTo);
                    });
                });
            }

            if ($facultyDepartmentHemisId) {
                $baseQuery->where('department_hemis_id', $facultyDepartmentHemisId);
            }
            if ($allowedSubjectIds !== null) {
                $baseQuery->whereIn('subject_id', $allowedSubjectIds);
            }
            if ($subjectFilter) {
                $baseQuery->where('subject_id', $subjectFilter);
            }
            if ($semesterFilter) {
                $baseQuery->where('semester_code', $semesterFilter);
            } elseif ($currentSemesterCodes !== null && count($currentSemesterCodes) > 0) {
                $baseQuery->whereIn('semester_code', $currentSemesterCodes);
            }

            // Har bir exam_schedules qatori 2 ta nazorat turini (OSKI va Test) o'z ichiga oladi
            // Ularni alohida holatlarga (completed/missing_date/missing_time/na) bo'lib chiqaramiz
            $hasTestTime = \Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'test_time');
            $selectCols = [
                'id',
                'department_hemis_id',
                'subject_id',
                'subject_name',
                'group_hemis_id',
                'semester_code',
                'oski_date',
                'oski_na',
                'test_date',
                'test_na',
                'created_at',
                'updated_at',
            ];
            if ($hasTestTime) {
                $selectCols[] = 'test_time';
            }
            $rows = (clone $baseQuery)->select($selectCols)->get();

            // Umumiy ko'rsatkichlar va fan kesimida yig'ish
            $total = 0;            // Jami nazorat turlari (OSKI + Test)
            $oskiCompleted = 0;    // OSKI sanasi belgilangan
            $oskiMissing = 0;      // OSKI sanasi belgilanmagan
            $oskiNa = 0;           // OSKI kerak emas
            $testCompleted = 0;    // Test sanasi va vaqti belgilangan
            $testMissingDate = 0;  // Test sanasi belgilanmagan
            $testMissingTime = 0;  // Test sanasi bor, vaqti yo'q
            $testNa = 0;           // Test kerak emas

            // Vaqt taqsimoti (test_time soati bo'yicha)
            $hourCounts = array_fill(0, 24, 0);

            // Fan va kafedra kesimida
            $subjectAgg = [];
            $kafedraAgg = [];
            $facultyAgg = [];

            // Faculty name mapping
            $facultyMap = Department::where('structure_type_code', 11)
                ->pluck('name', 'department_hemis_id')
                ->toArray();

            foreach ($rows as $r) {
                $total += 2; // OSKI + Test

                // OSKI hisobi
                if ($r->oski_na) {
                    $oskiNa++;
                } elseif ($r->oski_date) {
                    $oskiCompleted++;
                } else {
                    $oskiMissing++;
                }

                // Test hisobi
                $testState = 'missing_date';
                if ($r->test_na) {
                    $testNa++;
                    $testState = 'na';
                } elseif ($r->test_date) {
                    if ($hasTestTime && !empty($r->test_time)) {
                        $testCompleted++;
                        $testState = 'complete';
                        // soat hisobiga qo'shish
                        try {
                            $hour = (int) substr($r->test_time, 0, 2);
                            if ($hour >= 0 && $hour < 24) {
                                $hourCounts[$hour]++;
                            }
                        } catch (\Throwable $e) {
                        }
                    } elseif (!$hasTestTime) {
                        $testCompleted++;
                        $testState = 'complete';
                    } else {
                        $testMissingTime++;
                        $testState = 'missing_time';
                    }
                } else {
                    $testMissingDate++;
                    $testState = 'missing_date';
                }

                $oskiState = $r->oski_na ? 'na' : ($r->oski_date ? 'complete' : 'missing_date');

                // Status filter - skip agar filter bor bo'lsa va mos kelmasa
                $matchesStatus = true;
                if ($statusFilter !== null) {
                    $matchesStatus = ($statusFilter === $oskiState) || ($statusFilter === $testState);
                }
                if (!$matchesStatus) {
                    continue;
                }

                // Fan agregatsiyasi
                $subjKey = $r->subject_id;
                if (!isset($subjectAgg[$subjKey])) {
                    $kafedra = $subjectKafedraMap[$r->subject_id] ?? null;
                    $subjectAgg[$subjKey] = [
                        'id' => $r->subject_id,
                        'name' => $r->subject_name,
                        'kafedra' => $kafedra->department_name ?? '-',
                        'total' => 0,
                        'oski_complete' => 0,
                        'oski_missing' => 0,
                        'oski_na' => 0,
                        'test_complete' => 0,
                        'test_missing_date' => 0,
                        'test_missing_time' => 0,
                        'test_na' => 0,
                    ];
                }
                $subjectAgg[$subjKey]['total'] += 2;
                $subjectAgg[$subjKey]['oski_' . ($oskiState === 'complete' ? 'complete' : ($oskiState === 'na' ? 'na' : 'missing'))]++;
                $subjectAgg[$subjKey]['test_' . $testState]++;

                // Kafedra agregatsiyasi
                $kafedra = $subjectKafedraMap[$r->subject_id] ?? null;
                if ($kafedra) {
                    $kafedraKey = $kafedra->department_id;
                    if (!isset($kafedraAgg[$kafedraKey])) {
                        $kafedraAgg[$kafedraKey] = [
                            'id' => $kafedra->department_id,
                            'name' => $kafedra->department_name,
                            'total' => 0,
                            'oski_complete' => 0,
                            'oski_missing' => 0,
                            'oski_na' => 0,
                            'test_complete' => 0,
                            'test_missing_date' => 0,
                            'test_missing_time' => 0,
                            'test_na' => 0,
                        ];
                    }
                    $kafedraAgg[$kafedraKey]['total'] += 2;
                    $kafedraAgg[$kafedraKey]['oski_' . ($oskiState === 'complete' ? 'complete' : ($oskiState === 'na' ? 'na' : 'missing'))]++;
                    $kafedraAgg[$kafedraKey]['test_' . $testState]++;
                }

                // Fakultet agregatsiyasi
                $facultyKey = $r->department_hemis_id;
                if (!isset($facultyAgg[$facultyKey])) {
                    $facultyAgg[$facultyKey] = [
                        'id' => $r->department_hemis_id,
                        'name' => $facultyMap[$r->department_hemis_id] ?? '-',
                        'total' => 0,
                        'oski_complete' => 0,
                        'oski_missing' => 0,
                        'oski_na' => 0,
                        'test_complete' => 0,
                        'test_missing_date' => 0,
                        'test_missing_time' => 0,
                        'test_na' => 0,
                    ];
                }
                $facultyAgg[$facultyKey]['total'] += 2;
                $facultyAgg[$facultyKey]['oski_' . ($oskiState === 'complete' ? 'complete' : ($oskiState === 'na' ? 'na' : 'missing'))]++;
                $facultyAgg[$facultyKey]['test_' . $testState]++;
            }

            // Hourly data
            $hourlyData = [];
            $hourTotal = array_sum($hourCounts);
            foreach (range(0, 23) as $h) {
                $hourlyData[$h] = [
                    'count' => $hourCounts[$h],
                    'percent' => $hourTotal > 0 ? round($hourCounts[$h] / $hourTotal * 100, 1) : 0,
                ];
            }

            // ========= Rejalashtirish vaqti tahlili =========
            // Test markazi jadvalni imtihondan necha kun oldin belgilagan
            $setupBuckets = [
                'same_day' => 0,     // 0 kun - xuddi o'sha kuni
                'day_before' => 0,   // 1 kun oldin
                'week_before' => 0,  // 2-7 kun oldin
                'two_weeks' => 0,    // 8-14 kun oldin
                'month' => 0,        // 15-30 kun oldin
                'early' => 0,        // 30+ kun oldin
                'late' => 0,         // jadvalga imtihondan keyin o'zgartirish kiritilgan
                'no_date' => 0,      // imtihon sanasi yo'q
            ];
            foreach ($rows as $r) {
                $examDate = $r->test_date ?: $r->oski_date;
                if (!$examDate) {
                    $setupBuckets['no_date']++;
                    continue;
                }
                $setDate = $r->updated_at ?: $r->created_at;
                if (!$setDate) {
                    $setupBuckets['no_date']++;
                    continue;
                }
                try {
                    $diff = Carbon::parse($setDate)->startOfDay()->diffInDays(Carbon::parse($examDate)->startOfDay(), false);
                    if ($diff < 0) {
                        $setupBuckets['late']++;
                    } elseif ($diff === 0) {
                        $setupBuckets['same_day']++;
                    } elseif ($diff === 1) {
                        $setupBuckets['day_before']++;
                    } elseif ($diff <= 7) {
                        $setupBuckets['week_before']++;
                    } elseif ($diff <= 14) {
                        $setupBuckets['two_weeks']++;
                    } elseif ($diff <= 30) {
                        $setupBuckets['month']++;
                    } else {
                        $setupBuckets['early']++;
                    }
                } catch (\Throwable $e) {
                    $setupBuckets['no_date']++;
                }
            }

            // ========= Talaba boshlanish vaqti tahlili =========
            // hemis_quiz_results bilan solishtirib, talabalar imtihonni vaqtida yoki kechikib boshlashganini aniqlaymiz
            $testOnTime = 0;
            $testLate = 0;
            $oskiOnTime = 0;
            $oskiLate = 0;

            $testTypesQz = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypesQz = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

            // Faqat sanalar belgilangan schedule'larni qayta ishlaymiz
            $scheduledTestPairs = [];  // [group_hemis_id, subject_id] => ['scheduled_dt' => 'YYYY-MM-DD HH:MM:SS', 'test_date' => 'YYYY-MM-DD']
            $scheduledOskiPairs = [];  // [group_hemis_id, subject_id] => ['oski_date' => 'YYYY-MM-DD']
            $groupIdsForPunctuality = [];
            $subjectIdsForPunctuality = [];

            foreach ($rows as $r) {
                if ($r->test_date && !$r->test_na) {
                    $testTime = $hasTestTime && !empty($r->test_time) ? $r->test_time : '00:00:00';
                    if (strlen($testTime) === 5) $testTime .= ':00';
                    $key = $r->group_hemis_id . '|' . $r->subject_id;
                    $scheduledTestPairs[$key] = [
                        'scheduled_dt' => $r->test_date . ' ' . $testTime,
                        'test_date' => $r->test_date,
                        'has_time' => $hasTestTime && !empty($r->test_time),
                    ];
                    $groupIdsForPunctuality[$r->group_hemis_id] = true;
                    $subjectIdsForPunctuality[$r->subject_id] = true;
                }
                if ($r->oski_date && !$r->oski_na) {
                    $key = $r->group_hemis_id . '|' . $r->subject_id;
                    $scheduledOskiPairs[$key] = [
                        'oski_date' => $r->oski_date,
                    ];
                    $groupIdsForPunctuality[$r->group_hemis_id] = true;
                    $subjectIdsForPunctuality[$r->subject_id] = true;
                }
            }

            $punctualityAvailable = count($scheduledTestPairs) > 0 || count($scheduledOskiPairs) > 0;
            $studentDetails = [];

            if ($punctualityAvailable && \Illuminate\Support\Facades\Schema::hasTable('hemis_quiz_results')) {
                try {
                    $groupIds = array_keys($groupIdsForPunctuality);
                    $subjectIds = array_keys($subjectIdsForPunctuality);

                    $quizRows = DB::table('hemis_quiz_results as hqr')
                        ->join('students as st', 'st.student_id_number', '=', 'hqr.student_id')
                        ->whereIn('st.group_id', $groupIds)
                        ->whereIn('hqr.fan_id', $subjectIds)
                        ->where('hqr.is_active', 1)
                        ->whereNotNull('hqr.date_start')
                        ->select(
                            'st.group_id',
                            'st.group_name',
                            'st.full_name',
                            'st.student_id_number',
                            'hqr.fan_id',
                            'hqr.fan_name',
                            'hqr.quiz_type',
                            'hqr.date_start',
                            'hqr.date_finish',
                            'hqr.attempt_number',
                            'hqr.grade'
                        )
                        ->orderBy('hqr.date_start')
                        ->get();

                    foreach ($quizRows as $q) {
                        $key = $q->group_id . '|' . $q->fan_id;
                        $isTest = in_array($q->quiz_type, $testTypesQz);
                        $isOski = in_array($q->quiz_type, $oskiTypesQz);

                        if ($isTest) {
                            if (!isset($scheduledTestPairs[$key])) continue;
                            $sched = $scheduledTestPairs[$key];
                            $scheduledDt = $sched['has_time']
                                ? $sched['scheduled_dt']
                                : ($sched['test_date'] . ' 23:59:59');
                            $onTime = $sched['has_time']
                                ? ($q->date_start <= $sched['scheduled_dt'])
                                : (substr($q->date_start, 0, 10) <= $sched['test_date']);

                            if ($onTime) $testOnTime++; else $testLate++;

                            $studentDetails[] = [
                                'group' => $q->group_name,
                                'student' => $q->full_name,
                                'student_id' => $q->student_id_number,
                                'subject' => $q->fan_name,
                                'type' => 'TEST',
                                'scheduled' => $sched['has_time']
                                    ? $sched['scheduled_dt']
                                    : $sched['test_date'],
                                'has_time' => $sched['has_time'],
                                'date_start' => $q->date_start,
                                'date_finish' => $q->date_finish,
                                'attempt' => $q->attempt_number,
                                'grade' => $q->grade,
                                'status' => $onTime ? 'on_time' : 'late',
                            ];
                        } elseif ($isOski) {
                            if (!isset($scheduledOskiPairs[$key])) continue;
                            $sched = $scheduledOskiPairs[$key];
                            $onTime = substr($q->date_start, 0, 10) <= $sched['oski_date'];

                            if ($onTime) $oskiOnTime++; else $oskiLate++;

                            $studentDetails[] = [
                                'group' => $q->group_name,
                                'student' => $q->full_name,
                                'student_id' => $q->student_id_number,
                                'subject' => $q->fan_name,
                                'type' => 'OSKI',
                                'scheduled' => $sched['oski_date'],
                                'has_time' => false,
                                'date_start' => $q->date_start,
                                'date_finish' => $q->date_finish,
                                'attempt' => $q->attempt_number,
                                'grade' => $q->grade,
                                'status' => $onTime ? 'on_time' : 'late',
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('testMarkaziTimesData: hemis_quiz_results so\'rovi xatolik: ' . $e->getMessage());
                }
            }

            // "Kechikib" holatdagilarni oldinga qo'yamiz - shunda foydalanuvchi ularni ko'ra oladi
            // Keyin vaqtida boshlaganlarni qo'shamiz
            $lateRows = [];
            $onTimeRows = [];
            foreach ($studentDetails as $sd) {
                if ($sd['status'] === 'late') {
                    $lateRows[] = $sd;
                } else {
                    $onTimeRows[] = $sd;
                }
            }
            $sortedDetails = array_merge($lateRows, $onTimeRows);

            // JSON hajmini cheklash: barcha kechikkanlar + birinchi 3000 ta vaqtida
            $maxRows = 15000;
            $studentDetailsLimited = array_slice($sortedDetails, 0, $maxRows);
            $studentDetailsTruncated = count($sortedDetails) > $maxRows;
            $totalLate = count($lateRows);
            $totalOnTime = count($onTimeRows);

            // Sort
            $subjectData = array_values($subjectAgg);
            usort($subjectData, fn($a, $b) => strcmp($a['name'], $b['name']));

            $kafedraData = array_values($kafedraAgg);
            usort($kafedraData, fn($a, $b) => strcmp($a['name'], $b['name']));

            $facultyData = array_values($facultyAgg);
            usort($facultyData, fn($a, $b) => strcmp($a['name'], $b['name']));

            return response()->json([
                'overall' => [
                    'total_schedules' => $rows->count(),
                    'total' => $total,
                    'oski_complete' => $oskiCompleted,
                    'oski_missing' => $oskiMissing,
                    'oski_na' => $oskiNa,
                    'test_complete' => $testCompleted,
                    'test_missing_date' => $testMissingDate,
                    'test_missing_time' => $testMissingTime,
                    'test_na' => $testNa,
                    'has_test_time' => $hasTestTime,
                ],
                'setup_timing' => $setupBuckets,
                'punctuality' => [
                    'test_on_time' => $testOnTime,
                    'test_late' => $testLate,
                    'oski_on_time' => $oskiOnTime,
                    'oski_late' => $oskiLate,
                ],
                'hourly' => $hourlyData,
                'hourly_total' => $hourTotal,
                'by_subject' => $subjectData,
                'by_kafedra' => $kafedraData,
                'by_faculty' => $facultyData,
                'by_student' => $studentDetailsLimited,
                'by_student_total' => count($studentDetails),
                'by_student_truncated' => $studentDetailsTruncated,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Test markazi vaqtlari - Excel export
     */
    private function exportTestMarkaziTimesExcel($request, $dateFrom, $dateTo, $facultyDepartmentHemisId, $allowedSubjectIds, $subjectFilter, $semesterFilter, $statusFilter, $currentSemesterCodes, $subjectKafedraMap)
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4EF']],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
        ];
        $borderStyle = [
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
        ];

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Test markazi vaqtlari');

        $headers = [
            '#',
            'Fakultet',
            'Kafedra',
            'Guruh',
            'Fan',
            'Semestr',
            'OSKI sanasi',
            'OSKI holati',
            'Test sanasi',
            'Test vaqti',
            'Test holati',
        ];
        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
        }
        $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

        $hasTestTime = \Illuminate\Support\Facades\Schema::hasColumn('exam_schedules', 'test_time');

        $selects = [
            'es.id',
            'd.name as faculty_name',
            'es.subject_id',
            'es.subject_name',
            'es.group_hemis_id',
            'es.semester_code',
            'es.oski_date',
            'es.oski_na',
            'es.test_date',
            'es.test_na',
        ];
        if ($hasTestTime) {
            $selects[] = 'es.test_time';
        }

        $query = DB::table('exam_schedules as es')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'es.department_hemis_id')
            ->select($selects)
            ->orderBy('es.department_hemis_id')
            ->orderBy('es.subject_name');

        if ($dateFrom || $dateTo) {
            $query->where(function ($q) use ($dateFrom, $dateTo) {
                $q->where(function ($q2) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $q2->where('es.oski_date', '>=', $dateFrom);
                    if ($dateTo) $q2->where('es.oski_date', '<=', $dateTo);
                })->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                    if ($dateFrom) $q2->where('es.test_date', '>=', $dateFrom);
                    if ($dateTo) $q2->where('es.test_date', '<=', $dateTo);
                });
            });
        }

        if ($facultyDepartmentHemisId) {
            $query->where('es.department_hemis_id', $facultyDepartmentHemisId);
        }
        if ($allowedSubjectIds !== null) {
            $query->whereIn('es.subject_id', $allowedSubjectIds);
        }
        if ($subjectFilter) {
            $query->where('es.subject_id', $subjectFilter);
        }
        if ($semesterFilter) {
            $query->where('es.semester_code', $semesterFilter);
        } elseif ($currentSemesterCodes !== null && count($currentSemesterCodes) > 0) {
            $query->whereIn('es.semester_code', $currentSemesterCodes);
        }

        // Guruh nomlarini tayyorlaymiz
        $groupIds = $query->pluck('es.group_hemis_id')->unique()->toArray();
        $groupMap = DB::table('groups')
            ->whereIn('group_hemis_id', $groupIds)
            ->pluck('name', 'group_hemis_id')
            ->toArray();

        $semesterMap = DB::table('semesters')
            ->pluck('name', 'code')
            ->toArray();

        $row = 2;
        $num = 0;
        $query->chunk(2000, function ($records) use ($sheet, &$row, &$num, $subjectKafedraMap, $groupMap, $semesterMap, $hasTestTime, $statusFilter) {
            foreach ($records as $t) {
                $oskiState = $t->oski_na ? 'Kerak emas' : ($t->oski_date ? 'Belgilangan' : 'Belgilanmagan');

                if ($t->test_na) {
                    $testState = 'Kerak emas';
                } elseif ($t->test_date) {
                    if ($hasTestTime && !empty($t->test_time)) {
                        $testState = 'Toliq';
                    } elseif (!$hasTestTime) {
                        $testState = 'Belgilangan';
                    } else {
                        $testState = "Vaqti belgilanmagan";
                    }
                } else {
                    $testState = 'Belgilanmagan';
                }

                if ($statusFilter !== null) {
                    $stateMap = [
                        'complete' => ['Belgilangan', 'Toliq'],
                        'missing_date' => ['Belgilanmagan'],
                        'missing_time' => ['Vaqti belgilanmagan'],
                        'na' => ['Kerak emas'],
                    ];
                    $allowed = $stateMap[$statusFilter] ?? [];
                    if (!in_array($oskiState, $allowed) && !in_array($testState, $allowed)) {
                        continue;
                    }
                }

                $num++;
                $kafedra = $subjectKafedraMap[$t->subject_id] ?? null;

                // Sanalarni DD.MM.YYYY formatida ko'rsatamiz
                $oskiDateDisplay = '-';
                if ($t->oski_date) {
                    try { $oskiDateDisplay = Carbon::parse($t->oski_date)->format('d.m.Y'); } catch (\Throwable $e) {}
                }
                $testDateTimeDisplay = '-';
                if ($t->test_date) {
                    try {
                        $tt = ($hasTestTime && !empty($t->test_time)) ? substr($t->test_time, 0, 5) : '--:--';
                        $testDateTimeDisplay = Carbon::parse($t->test_date)->format('d.m.Y') . ' ' . $tt;
                    } catch (\Throwable $e) {}
                }

                $sheet->setCellValue([1, $row], $num);
                $sheet->setCellValue([2, $row], $t->faculty_name ?? '-');
                $sheet->setCellValue([3, $row], $kafedra->department_name ?? '-');
                $sheet->setCellValue([4, $row], $groupMap[$t->group_hemis_id] ?? $t->group_hemis_id);
                $sheet->setCellValue([5, $row], $t->subject_name);
                $sheet->setCellValue([6, $row], $semesterMap[$t->semester_code] ?? $t->semester_code);
                $sheet->setCellValueExplicit([7, $row], $oskiDateDisplay, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue([8, $row], $oskiState);
                $sheet->setCellValueExplicit([9, $row], $testDateTimeDisplay, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValueExplicit([10, $row], $hasTestTime && !empty($t->test_time) ? substr($t->test_time, 0, 5) : '-', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue([11, $row], $testState);
                $row++;
            }
        });

        $lastRow = $row - 1;
        if ($lastRow > 1) {
            $sheet->getStyle("A2:K{$lastRow}")->applyFromArray($borderStyle);
        }
        $widths = [5, 28, 28, 14, 30, 14, 14, 16, 14, 12, 18];
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($w);
        }

        // ===================== Sheet 2: Talabalar kesimida =====================
        if (\Illuminate\Support\Facades\Schema::hasTable('hemis_quiz_results')) {
            $sheet2 = $spreadsheet->createSheet();
            $sheet2->setTitle('Talabalar');

            $studentHeaders = [
                '#',
                'Guruh',
                'Talaba (FISH)',
                'Student ID',
                'Fan',
                'Tur',
                'Belgilangan vaqt',
                'Boshlangan vaqt',
                'Tugatilgan vaqt',
                'Urinish',
                'Baho',
                'Holat',
            ];
            foreach ($studentHeaders as $col => $header) {
                $sheet2->setCellValue([$col + 1, 1], $header);
            }
            $sheet2->getStyle('A1:L1')->applyFromArray($headerStyle);

            $testTypesQz = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypesQz = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

            // exam_schedules dan jadvalni yig'amiz (filtr bilan)
            $schedQuery = DB::table('exam_schedules as es')
                ->select('es.group_hemis_id', 'es.subject_id', 'es.subject_name', 'es.oski_date', 'es.oski_na', 'es.test_date', 'es.test_na');
            if ($hasTestTime) {
                $schedQuery->addSelect('es.test_time');
            }
            if ($dateFrom || $dateTo) {
                $schedQuery->where(function ($q) use ($dateFrom, $dateTo) {
                    $q->where(function ($q2) use ($dateFrom, $dateTo) {
                        if ($dateFrom) $q2->where('es.oski_date', '>=', $dateFrom);
                        if ($dateTo) $q2->where('es.oski_date', '<=', $dateTo);
                    })->orWhere(function ($q2) use ($dateFrom, $dateTo) {
                        if ($dateFrom) $q2->where('es.test_date', '>=', $dateFrom);
                        if ($dateTo) $q2->where('es.test_date', '<=', $dateTo);
                    });
                });
            }
            if ($facultyDepartmentHemisId) {
                $schedQuery->where('es.department_hemis_id', $facultyDepartmentHemisId);
            }
            if ($allowedSubjectIds !== null) {
                $schedQuery->whereIn('es.subject_id', $allowedSubjectIds);
            }
            if ($subjectFilter) {
                $schedQuery->where('es.subject_id', $subjectFilter);
            }
            if ($semesterFilter) {
                $schedQuery->where('es.semester_code', $semesterFilter);
            } elseif ($currentSemesterCodes !== null && count($currentSemesterCodes) > 0) {
                $schedQuery->whereIn('es.semester_code', $currentSemesterCodes);
            }

            $schedRows = $schedQuery->get();

            $testMap = [];
            $oskiMap = [];
            $groupIds = [];
            $subjectIds = [];
            foreach ($schedRows as $sr) {
                if ($sr->test_date && !$sr->test_na) {
                    $tt = $hasTestTime && !empty($sr->test_time) ? $sr->test_time : null;
                    if ($tt && strlen($tt) === 5) $tt .= ':00';
                    $testMap[$sr->group_hemis_id . '|' . $sr->subject_id] = [
                        'test_date' => $sr->test_date,
                        'test_time' => $tt,
                        'subject_name' => $sr->subject_name,
                    ];
                    $groupIds[$sr->group_hemis_id] = true;
                    $subjectIds[$sr->subject_id] = true;
                }
                if ($sr->oski_date && !$sr->oski_na) {
                    $oskiMap[$sr->group_hemis_id . '|' . $sr->subject_id] = [
                        'oski_date' => $sr->oski_date,
                        'subject_name' => $sr->subject_name,
                    ];
                    $groupIds[$sr->group_hemis_id] = true;
                    $subjectIds[$sr->subject_id] = true;
                }
            }

            $row2 = 2;
            $num2 = 0;

            if (!empty($groupIds) && !empty($subjectIds)) {
                $studentQuery = DB::table('hemis_quiz_results as hqr')
                    ->join('students as st', 'st.student_id_number', '=', 'hqr.student_id')
                    ->whereIn('st.group_id', array_keys($groupIds))
                    ->whereIn('hqr.fan_id', array_keys($subjectIds))
                    ->where('hqr.is_active', 1)
                    ->whereNotNull('hqr.date_start')
                    ->select(
                        'st.group_id',
                        'st.group_name',
                        'st.full_name',
                        'st.student_id_number',
                        'hqr.fan_id',
                        'hqr.fan_name',
                        'hqr.quiz_type',
                        'hqr.date_start',
                        'hqr.date_finish',
                        'hqr.attempt_number',
                        'hqr.grade'
                    )
                    ->orderBy('st.group_name')
                    ->orderBy('st.full_name')
                    ->orderBy('hqr.date_start');

                $studentQuery->chunk(3000, function ($records) use ($sheet2, &$row2, &$num2, $testMap, $oskiMap, $testTypesQz, $oskiTypesQz) {
                    // Vaqtni DD.MM.YYYY HH:MM formatiga keltiradigan yordamchi
                    $fmt = function ($val) {
                        if (empty($val)) return '-';
                        try {
                            return Carbon::parse($val)->format('d.m.Y H:i');
                        } catch (\Throwable $e) {
                            return (string) $val;
                        }
                    };

                    foreach ($records as $q) {
                        $key = $q->group_id . '|' . $q->fan_id;
                        $isTest = in_array($q->quiz_type, $testTypesQz);
                        $isOski = in_array($q->quiz_type, $oskiTypesQz);

                        $scheduledRaw = null;   // solishtirish uchun
                        $scheduledDisplay = null; // ko'rsatish uchun
                        $type = null;
                        $onTime = null;

                        if ($isTest && isset($testMap[$key])) {
                            $m = $testMap[$key];
                            $type = 'TEST';
                            if ($m['test_time']) {
                                $scheduledRaw = $m['test_date'] . ' ' . $m['test_time'];
                                $scheduledDisplay = Carbon::parse($m['test_date'] . ' ' . $m['test_time'])->format('d.m.Y H:i');
                                $onTime = $q->date_start <= $scheduledRaw;
                            } else {
                                // Vaqt belgilanmagan - DD.MM.YYYY --:-- deb ko'rsatamiz
                                $scheduledDisplay = Carbon::parse($m['test_date'])->format('d.m.Y') . ' --:--';
                                // lekin solishtirish uchun kun oxirigacha ruxsat beramiz
                                $onTime = substr($q->date_start, 0, 10) <= $m['test_date'];
                            }
                        } elseif ($isOski && isset($oskiMap[$key])) {
                            $m = $oskiMap[$key];
                            $type = 'OSKI';
                            // OSKI vaqti jadvalda mavjud emas - DD.MM.YYYY --:-- deb ko'rsatamiz
                            $scheduledDisplay = Carbon::parse($m['oski_date'])->format('d.m.Y') . ' --:--';
                            $onTime = substr($q->date_start, 0, 10) <= $m['oski_date'];
                        } else {
                            continue;
                        }

                        $num2++;
                        $sheet2->setCellValue([1, $row2], $num2);
                        $sheet2->setCellValue([2, $row2], $q->group_name);
                        $sheet2->setCellValue([3, $row2], $q->full_name);
                        $sheet2->setCellValue([4, $row2], $q->student_id_number);
                        $sheet2->setCellValue([5, $row2], $q->fan_name);
                        $sheet2->setCellValue([6, $row2], $type);
                        $sheet2->setCellValueExplicit([7, $row2], $scheduledDisplay, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet2->setCellValueExplicit([8, $row2], $fmt($q->date_start), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet2->setCellValueExplicit([9, $row2], $fmt($q->date_finish), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        $sheet2->setCellValue([10, $row2], $q->attempt_number);
                        $sheet2->setCellValue([11, $row2], $q->grade);
                        $sheet2->setCellValue([12, $row2], $onTime ? 'Vaqtida' : 'Kechikish');
                        $row2++;
                    }
                });
            }

            $lastRow2 = $row2 - 1;
            if ($lastRow2 > 1) {
                $sheet2->getStyle("A2:L{$lastRow2}")->applyFromArray($borderStyle);
            }
            $widths2 = [5, 16, 30, 14, 30, 8, 18, 20, 20, 10, 10, 14];
            foreach ($widths2 as $i => $w) {
                $sheet2->getColumnDimensionByColumn($i + 1)->setWidth($w);
            }
        }

        $fileName = 'Test_markazi_vaqtlari_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'tmt_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Joriy semestr bo'yicha talabalarning xavf holatini student_grades dan hisoblash.
     * Qaytaradi: [hemis_id => [['subject_name'=>..., 'reasons'=>[...]], ...]]
     */
    /**
     * @param array<string,string|int> $studentSemCodeMap  [hemis_id => semester_code]
     */
    private function getCurrentSemesterRisksForReport(array $studentHemisIds, array $studentSemCodeMap = []): array
    {
        if (empty($studentHemisIds)) return [];

        // Talabalarning o'z semester_code laridan foydalanamiz.
        // Agar map uzatilmagan bo'lsa — semesters.current=true ga fallback.
        if (!empty($studentSemCodeMap)) {
            $currentSemesterCodes = array_values(array_unique(array_map('strval', $studentSemCodeMap)));
        } else {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->map('strval')
                ->toArray();
        }

        if (empty($currentSemesterCodes)) return [];

        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

        $grades = collect();
        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            // Har bir talabaning o'z semester_code si ishlatiladi
            $chunkGrades = DB::table('student_grades')
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
            $grades = $grades->merge($chunkGrades);
        }

        if ($grades->isEmpty()) return [];

        // Biriktirilganlik (enrollment) — student_subjects + JORIY O'QUV YILI bo'yicha.
        //
        // Tiklangan talaba bir semestrni 2 marta (eski + joriy o'quv yili) o'qishi
        // mumkin. student_subjects da bir xil semestrda 2024 va 2025 yil yozuvlari
        // turishi mumkin. Faqat ENG SO'NGGI (joriy) o'quv yili biriktirilgan
        // fanlar haqiqatda hozir o'qilayotgan fanlardir.
        //
        // Mantiq:
        //  - $curYear[hemis]       = talabaning joriy semestrdagi eng katta education_year
        //  - $enrolledCur[hemis]   = [subject_id => true] joriy yil biriktirilgan fanlar
        //  - $hasEnrollment[hemis] = student_subjects da yozuv bor (fallback uchun)
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
        // Joriy yil biriktirilgan fanlar to'plami
        $enrolledCur = [];
        foreach ($ssRowsAll as $sr) {
            $hid = $sr->student_hemis_id;
            $cy = $curYear[$hid] ?? null;
            // Yil ko'rsatilmagan yoki joriy yilga teng bo'lsa — biriktirilgan deb olamiz
            if ($cy === null || (string) $sr->education_year === '' || (string) $sr->education_year === $cy) {
                $enrolledCur[$hid][$sr->subject_id] = true;
            }
        }

        // Baholarni joriy o'quv yili bo'yicha tozalash: eski yil (masalan 2024)
        // baholari hisobga olinmasin (faqat joriy yil yoki yili belgilanmagan
        // jurnal yozuvlari qoladi).
        $grades = $grades->filter(function ($g) use ($curYear) {
            $cy = $curYear[$g->student_hemis_id] ?? null;
            if ($cy === null) return true;
            $gy = (string) ($g->education_year_code ?? '');
            if ($gy === '') return true;        // jurnal (davomat) yozuvlari — qoldiriladi
            return $gy === $cy;                  // faqat joriy yil import baholari
        });

        if ($grades->isEmpty()) return [];

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
        // Jurnal davomatni dars SONIDAN emas, QOLDIRILGAN SOATLARdan hisoblaydi:
        // SUM(absent_off) / auditoriumHours * 100. 99,100,101,102 turlari va tasdiqlangan
        // sababli ariza sanalari chiqarib tashlanadi.
        $subjectIdsForAtt = $grades->pluck('subject_id')->filter()->unique()->values()->all();

        $absentHours = [];
        if (!empty($subjectIdsForAtt)) {
            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $attRows = DB::table('attendances')
                    ->whereIn('student_hemis_id', $chunk)
                    ->whereIn('semester_code', $currentSemesterCodes)
                    ->whereIn('subject_id', $subjectIdsForAtt)
                    ->whereNotIn('training_type_code', [99, 100, 101, 102])
                    ->when($hasExcuseTable, function ($q) {
                        $q->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('absence_excuses')
                                ->whereColumn('absence_excuses.student_hemis_id', 'attendances.student_hemis_id')
                                ->where('absence_excuses.status', 'approved')
                                ->whereRaw('absence_excuses.start_date <= DATE(attendances.lesson_date)')
                                ->whereRaw('absence_excuses.end_date >= DATE(attendances.lesson_date)');
                        });
                    })
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'education_year_code', DB::raw('SUM(absent_off) as tot'))
                    ->groupBy('student_hemis_id', 'subject_id', 'semester_code', 'education_year_code')
                    ->get();
                foreach ($attRows as $ar) {
                    $cy = $curYear[$ar->student_hemis_id] ?? null;
                    $ey = (string) ($ar->education_year_code ?? '');
                    if ($cy !== null && $ey !== '' && $ey !== $cy) continue;
                    $k = $ar->student_hemis_id . '|' . $ar->subject_id . '|' . $ar->semester_code;
                    $absentHours[$k] = ($absentHours[$k] ?? 0) + (float) $ar->tot;
                }
            }
        }

        $stuCurricula = DB::table('students')
            ->whereIn('students.hemis_id', $studentHemisIds)
            ->leftJoin('groups', 'students.group_id', '=', 'groups.group_hemis_id')
            ->select('students.hemis_id', 'groups.curriculum_hemis_id')
            ->pluck('curriculum_hemis_id', 'students.hemis_id')
            ->toArray();

        // Talaba -> guruh xaritasi va JN cache (jurnal mantig'ida JN hisoblash uchun)
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

        // test/oski yopilishli fanda talaba imtihonga UMUMAN kelmagan bo'lsa
        // (student_grades da 101/102 yozuvi yo'q), lekin guruhga imtihon
        // belgilangan va sanasi o'tgan bo'lsa — bu no-show (avtomatik yiqilgan).
        // exam_schedules dan sana va na (talab qilinadi/qilinmaydi) belgilarini olamiz.
        $examSchedGroup = [];   // subject|sem|group_hemis_id => row
        $examSchedInd   = [];   // student|subject|sem => row (individual, ustunlik beradi)
        // Jadval imtihon yozuvi bor fanlar bilan cheklanmasin — biriktirilgan (lekin
        // baho yozuvi umuman yo'q) fanlar ham no-show uchun tekshirilishi kerak.
        $schedSubjectIds = $subjectIdsForAtt;
        foreach ($enrolledCur as $subs) {
            $schedSubjectIds = array_merge($schedSubjectIds, array_keys($subs));
        }
        $schedSubjectIds = array_values(array_unique($schedSubjectIds));
        $schedSubjectName = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('exam_schedules') && !empty($schedSubjectIds)) {
            $esRows = DB::table('exam_schedules')
                ->whereIn('subject_id', $schedSubjectIds)
                ->whereIn('semester_code', $currentSemesterCodes)
                ->get([
                    'student_hemis_id', 'group_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'test_na', 'test_date', 'test_resit_date', 'test_resit2_date',
                    'oski_na', 'oski_date', 'oski_resit_date', 'oski_resit2_date',
                ]);
            foreach ($esRows as $es) {
                if ($es->student_hemis_id !== null) {
                    $examSchedInd[$es->student_hemis_id . '|' . $es->subject_id . '|' . $es->semester_code] = $es;
                } else {
                    $examSchedGroup[$es->subject_id . '|' . $es->semester_code . '|' . $es->group_hemis_id] = $es;
                }
                if ($es->subject_name && !isset($schedSubjectName[$es->subject_id])) {
                    $schedSubjectName[$es->subject_id] = $es->subject_name;
                }
            }
        }
        $todayStr = now()->format('Y-m-d');

        // Imtihonga kelmagan (no-show) aniqlash — jurnal mantig'i: test/oski talab
        // qilingan (na=0) va sanasi o'tgan bo'lsa, lekin talabada imtihon bahosi
        // bo'lmasa — u avtomatik yiqilgan. Individual jadval faqat override qilingan
        // sanani saqlaydi, shuning uchun har bir sana individual→guruh tartibida
        // birlashtiriladi; na bayrog'i esa fan/guruh darajasidan olinadi.
        $computeNoShow = function ($hid, $sid, $sem) use ($examSchedInd, $examSchedGroup, $stuGroup, $todayStr) {
            $gid = $stuGroup[$hid] ?? null;
            $indSched = $examSchedInd[$hid . '|' . $sid . '|' . $sem] ?? null;
            $grpSched = $gid !== null ? ($examSchedGroup[$sid . '|' . $sem . '|' . $gid] ?? null) : null;
            if (!$indSched && !$grpSched) return null;
            $pick = fn($f) => ($indSched && $indSched->$f !== null) ? $indSched->$f : ($grpSched->$f ?? null);
            $naTest = $grpSched ? (int) ($grpSched->test_na ?? 1) : (int) ($indSched->test_na ?? 1);
            $naOski = $grpSched ? (int) ($grpSched->oski_na ?? 1) : (int) ($indSched->oski_na ?? 1);
            $d = fn($v) => $v ? substr((string) $v, 0, 10) : null;
            if ($naTest === 0) {
                $td = $d($pick('test_date')); $tr1 = $d($pick('test_resit_date')); $tr2 = $d($pick('test_resit2_date'));
                if ($tr2 !== null && $tr2 <= $todayStr) return 'Akademik qarzdor (imtihonga kelmagan, 3-urinish)';
                if ($tr1 !== null && $tr1 <= $todayStr) return 'Imtihonga kelmagan (2-urinish)';
                if ($td !== null && $td <= $todayStr) return 'Imtihonga kelmagan (1-urinish)';
            }
            if ($naOski === 0) {
                $od = $d($pick('oski_date')); $or1 = $d($pick('oski_resit_date')); $or2 = $d($pick('oski_resit2_date'));
                if ($or2 !== null && $or2 <= $todayStr) return 'Akademik qarzdor (imtihonga kelmagan, 3-urinish)';
                if ($or1 !== null && $or1 <= $todayStr) return 'Imtihonga kelmagan (2-urinish)';
                if ($od !== null && $od <= $todayStr) return 'Imtihonga kelmagan (1-urinish)';
            }
            return null;
        };

        $auditMap = [];
        $auditAnyMap = [];
        $closingFormMap = [];
        $closingFormAnyMap = [];
        if (!empty($subjectIdsForAtt)) {
            $csRows = \App\Models\CurriculumSubject::whereIn('semester_code', $currentSemesterCodes)
                ->whereIn('subject_id', $subjectIdsForAtt)
                ->orderByDesc('is_active')
                ->get(['subject_id', 'semester_code', 'curricula_hemis_id', 'total_acload', 'subject_details', 'closing_form']);
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
                if (!isset($closingFormMap[$kc])) $closingFormMap[$kc] = (string) ($cs->closing_form ?? '');
                $ka = $cs->subject_id . '|' . $cs->semester_code;
                if (!isset($auditAnyMap[$ka])) $auditAnyMap[$ka] = $h;
                if (!isset($closingFormAnyMap[$ka])) $closingFormAnyMap[$ka] = (string) ($cs->closing_form ?? '');
            }
        }

        $grouped = $grades->groupBy(fn($g) => $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code);
        $risks = [];

        foreach ($grouped as $key => $rows) {
            [$hemisId, $subjectId, $semCode] = explode('|', $key, 3);
            // Agar talabaning o'z semester_code si map da bo'lsa —
            // boshqa semestrlar ma'lumotini o'tkazib yuboramiz.
            if (!empty($studentSemCodeMap)) {
                $expectedSem = (string)($studentSemCodeMap[$hemisId] ?? '');
                if ($expectedSem !== '' && $semCode !== $expectedSem) continue;
            }

            // Biriktirilganlik tekshiruvi: student_subjects da yozuv bo'lsa-yu,
            // bu fan joriy o'quv yili biriktirilganlar ro'yxatida bo'lmasa —
            // talaba bu fanga hozir biriktirilmagan (tiklangan, eski yil fani),
            // xavf hisoblanmaydi.
            if (($hasEnrollment[$hemisId] ?? false) && !isset($enrolledCur[$hemisId][$subjectId])) {
                continue;
            }

            $subjectName = $rows->first()->subject_name ?? 'Fan';
            $reasons = [];
            $cur = $stuCurricula[$hemisId] ?? null;
            $closingForm = $cur !== null
                ? ($closingFormMap[$cur . '|' . $subjectId . '|' . $semCode] ?? '')
                : '';
            if ($closingForm === '') {
                $closingForm = $closingFormAnyMap[$subjectId . '|' . $semCode] ?? '';
            }

            // Sinov fanlari uchun jurnaldagi "Sinov (test)" ustuni sinov_test_grades'dan
            // (qulflangan/override qiymat, odatda JN o'rtachasi) keladi — bu joriy
            // haqiqiy natija. student_grades'dagi xom urinish yozuvlari sinov
            // mexanizmidan oldingi eskirgan ma'lumot bo'lishi mumkin.
            $sinovKey = $hemisId . '|' . $subjectId . '|' . $semCode;
            if (isset($sinovGradeMap[$sinovKey])) {
                if ($sinovGradeMap[$sinovKey] < 60) {
                    $reasons[] = '1-urinish: V<60';
                }
            } else {
                // Imtihon urinishlari (OSKI/Test) — faqat ENG OXIRGI (sana bo'yicha) yozuvga qaraladi.
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
                    // Faqat oxirgi urinish muvaffaqiyatsiz bo'lsa — xavf bor
                    if ($lastBaho !== null && $lastBaho < 60) {
                        if ($lastAttempt <= 1) $reasons[] = '1-urinish: V<60';
                        elseif ($lastAttempt === 2) $reasons[] = '2-urinish: V<60';
                        else $reasons[] = 'Akademik qarzdor (3 urinish tugadi)';
                    }
                } else {
                    // Imtihon yozuvi UMUMAN yo'q — talaba imtihonga kelmaganmi tekshiramiz.
                    $noShowLabel = $computeNoShow($hemisId, $subjectId, $semCode);
                    if ($noShowLabel !== null) $reasons[] = $noShowLabel;
                }
            }

            // MT (training_type_code = 99)
            $mtRows = $rows->where('training_type_code', 99);
            if ($mtRows->isNotEmpty()) {
                $mtGrade = null;
                foreach ($mtRows as $r) {
                    $val = $r->grade !== null ? (float)$r->grade : null;
                    if ($val !== null && ($mtGrade === null || $val > $mtGrade)) $mtGrade = $val;
                }
                if ($mtGrade !== null && $mtGrade < 60) $reasons[] = 'MT<60';
            } elseif (in_array($closingForm, ['test', 'oski_test'], true)) {
                $reasons[] = 'MT yo\'q';
            }

            // JN o'rtachasi — jurnaldagi AYNAN bir xil hisob (computeJnAveragesForGroup)
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

            // Sababsiz davomat >= 25% (jurnal mantig'i: qoldirilgan soat / auditoriya soati)
            $absH = $absentHours[$hemisId . '|' . $subjectId . '|' . $semCode] ?? 0;
            if ($absH > 0) {
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
                    'subject_id' => $subjectId,
                    'semester_code' => $semCode,
                    'subject_name' => $subjectName,
                    'reasons' => $reasons,
                ];
            }
        }

        // Ikkinchi o'tish: biriktirilgan-u student_grades da UMUMAN yozuvi yo'q fanlar.
        // Birinchi sikl faqat baho yozuvi bor (grouped) fanlarni ko'radi. Talaba
        // biror fanga qatnashmagan (JN/MT ham yo'q) bo'lsa, u $grouped da yo'q —
        // lekin imtihon jadvali o'tgan bo'lsa, u ham no-show (avtomatik yiqilgan).
        foreach ($studentHemisIds as $hid) {
            $sem = (string) ($studentSemCodeMap[$hid] ?? '');
            if ($sem === '') continue;
            foreach (array_keys($enrolledCur[$hid] ?? []) as $sid) {
                $key = $hid . '|' . $sid . '|' . $sem;
                if (isset($grouped[$key])) continue;         // birinchi siklda ko'rilgan
                // Sinov fani — sinov_test_grades orqali (jurnaldagi yakuniy natija)
                if (isset($sinovGradeMap[$key])) {
                    if ($sinovGradeMap[$key] < 60) {
                        $nm = $schedSubjectName[$sid] ?? 'Fan';
                        $risks[$hid][] = ['subject_id' => $sid, 'semester_code' => $sem, 'subject_name' => $nm, 'reasons' => ['1-urinish: V<60']];
                    }
                    continue;
                }
                $noShowLabel = $computeNoShow($hid, $sid, $sem);
                if ($noShowLabel !== null) {
                    $nm = $schedSubjectName[$sid] ?? 'Fan';
                    $risks[$hid][] = ['subject_id' => $sid, 'semester_code' => $sem, 'subject_name' => $nm, 'reasons' => [$noShowLabel]];
                }
            }
        }

        return $risks;
    }

    /**
     * "Qo'lda yarim to'ldirilgan otrabotka" ogohlantirish sahifasi.
     * Faqat KO'RSATISH — baholar o'zgartirilmaydi (qaydnomada tasdiqlangan).
     */
    public function manualRetakeGaps(Request $request)
    {
        return view('admin.reports.manual-retake-gaps');
    }

    /**
     * AJAX: ko'p juftlikli kunda retake ba'zi juftliklarga qo'yilib, qoidaviy
     * juftlik (NB yoki <60) retakesiz qolgan holatlar. O'qituvchi qo'lda
     * to'ldirishda ba'zi juftlikni unutgan bo'lishi mumkin — admin ko'rib
     * to'ldiradi. Read-only.
     */
    public function manualRetakeGapsData(Request $request)
    {
        $dateFrom = $request->input('date_from') ?: now('Asia/Tashkent')->subMonths(6)->toDateString();
        $dateTo   = $request->input('date_to')   ?: now('Asia/Tashkent')->toDateString();

        $rows = DB::select("
            SELECT s.full_name, s.group_name, sg.subject_id, sg.subject_name,
                   DATE(sg.lesson_date) AS kun,
                   COUNT(*) AS juftliklar,
                   SUM(sg.retake_grade IS NOT NULL) AS retake_bor,
                   SUM(sg.retake_grade IS NULL AND ((sg.grade IS NULL AND sg.reason = 'absent') OR sg.grade < 60)) AS qoldirilgan,
                   GROUP_CONCAT(
                       CONCAT(sg.lesson_pair_code, ':', COALESCE(sg.grade, 'NB'), '/',
                              COALESCE(sg.retake_grade, '-'),
                              IF(sg.quiz_result_id IS NOT NULL, '(q)', ''))
                       ORDER BY sg.lesson_pair_code SEPARATOR ' | ') AS tafsilot
            FROM student_grades sg
            JOIN students s ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
            WHERE sg.deleted_at IS NULL
              AND sg.training_type_code NOT IN (11, 17, 99, 100, 101, 102, 103)
              AND DATE(sg.lesson_date) >= ?
              AND DATE(sg.lesson_date) <= ?
            GROUP BY sg.student_hemis_id, s.full_name, s.group_name, sg.subject_id, sg.subject_name, DATE(sg.lesson_date)
            HAVING juftliklar > 1 AND retake_bor > 0 AND qoldirilgan > 0
            ORDER BY kun DESC
            LIMIT 2000
        ", [$dateFrom, $dateTo]);

        return response()->json([
            'success'    => true,
            'rows'       => $rows,
            'total'      => count($rows),
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
        ]);
    }

    /**
     * Qarzdor hisobotida talaba noto'g'ri ko'rinishini aniqlash uchun debug endpoint.
     * URL: /admin/reports/debug-debt?hemis_id=368261100054
     */
    public function debugDebt(Request $request)
    {
        $hemisId = $request->get('hemis_id');
        if (!$hemisId) {
            return response()->json(['error' => 'hemis_id parametri kerak. Masalan: ?hemis_id=368261100054'], 400);
        }

        $student = DB::table('students')
            ->where('hemis_id', $hemisId)
            ->orWhere('student_id_number', $hemisId)
            ->first();
        if (!$student) {
            return response()->json(['error' => 'Talaba topilmadi: ' . $hemisId . ' (hemis_id va student_id_number ham tekshirildi)'], 404);
        }
        // Haqiqiy hemis_id ni ishlatamiz (foydalanuvchi student_id_number bergan bo'lishi mumkin)
        $hemisId = (string) $student->hemis_id;

        $curriculumId = $student->curriculum_id;
        $semesterCode = $student->semester_code ? (int) $student->semester_code : null;

        // 1. academic_records
        $arRows = DB::table('academic_records')
            ->where('student_id', $hemisId)
            ->orderBy('semester_id')
            ->get(['semester_id', 'subject_id', 'subject_name', 'grade', 'curriculum_id', 'hemis_updated_at'])
            ->toArray();

        // AR lookup — hisobot ishlatadigan format
        $arLookup = [];
        foreach ($arRows as $ar) {
            $arLookup[$ar->subject_id . '|' . $ar->semester_id] = $ar->grade;
        }

        // 2. curriculum_subjects — o'tgan semestrlar
        $currSubjects = DB::table('curriculum_subjects')
            ->where('curricula_hemis_id', $curriculumId)
            ->where('is_active', 1)
            ->whereNull('in_group')
            ->orderBy('semester_code')
            ->get(['semester_code', 'semester_name', 'subject_id', 'subject_name', 'subject_type_code'])
            ->toArray();

        // 3. Har fan uchun: AR bor/yo'q + kalitlar
        $analysis = [];
        foreach ($currSubjects as $cs) {
            if ($semesterCode && (int)$cs->semester_code >= $semesterCode) continue; // joriy va keyingi — skip

            $arKey  = $hemisId . '|' . $cs->subject_id . '|' . $cs->semester_code;
            $arKey2 = $cs->subject_id . '|' . $cs->semester_code; // AR lookup kaliti
            $hasAR  = isset($arLookup[$arKey2]);

            $analysis[] = [
                'semester'     => $cs->semester_name . ' (' . $cs->semester_code . ')',
                'subject_id'   => $cs->subject_id,
                'subject_name' => $cs->subject_name,
                'ar_key'       => $arKey,
                'has_ar'       => $hasAR,
                'ar_grade'     => $arLookup[$arKey2] ?? null,
                'verdict'      => $hasAR ? '✅ qarz emas' : '❌ QARZ (AR yo\'q)',
            ];
        }

        // 4. Qarz ko'rinayotgan fanlarni AR da nom bo'yicha qidirish (subject_id farq qilsa)
        $debtSubjectNames = collect($analysis)
            ->where('has_ar', false)
            ->pluck('subject_name')
            ->unique()->values()->all();

        $arByName = [];
        foreach ($debtSubjectNames as $name) {
            $found = DB::table('academic_records')
                ->where('student_id', $hemisId)
                ->where('subject_name', 'like', '%' . $name . '%')
                ->get(['semester_id', 'subject_id', 'subject_name', 'grade'])
                ->toArray();
            if ($found) {
                $arByName[$name] = $found;
            }
        }

        // 5. subjects jadvali (agar mavjud bo'lsa) — subject_id xaritalash
        $subjectIdMap = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('subjects')) {
            $subjectIdMap = DB::table('subjects')
                ->whereIn('name', $debtSubjectNames)
                ->orWhere(function ($q) use ($debtSubjectNames) {
                    foreach ($debtSubjectNames as $n) {
                        $q->orWhere('name', 'like', '%' . $n . '%');
                    }
                })
                ->get(['id', 'hemis_id', 'name'])
                ->toArray();
        }

        return response()->json([
            'talaba' => [
                'hemis_id'      => $hemisId,
                'full_name'     => $student->full_name,
                'group'         => $student->group_name,
                'curriculum'    => $curriculumId,
                'semester_code' => $semesterCode,
            ],
            'academic_records_count' => count($arRows),
            'academic_records'       => $arRows,
            'curriculum_analysis'    => $analysis,
            'QARZ_fanlar_AR_da_nom_boyicha' => $arByName
                ?: '❌ AR da bu fanlar nom bo\'yicha ham topilmadi — sinxronlash muammosi',
            'subjects_jadval'        => $subjectIdMap ?: 'subjects jadvali yo\'q yoki topilmadi',
            'xulosa' => count($arByName) > 0
                ? '⚠️ SUBJECT_ID MISMATCH: curriculum_subjects.subject_id va academic_records.subject_id farq qiladi. AR da fan bor lekin boshqa ID bilan.'
                : '❌ SINXRONLASH: Bu fanlar HEMIS dan academic_records ga umuman kelmagan.',
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /* =========================================================================
     |  OQIM (talabalarni oqim va guruhlarga taqsimlash) hisoboti
     |  Fakultet -> yo'nalish -> kurs -> oqim -> guruh kesimida talaba soni.
     |  "Guruh" (to'liq), "A.B" (2 ga bo'lib), "A.B.C" (3 ga bo'lib) variantlari.
     * ======================================================================= */

    /**
     * Oqim hisoboti sahifasi — faqat filtrlar ko'rsatiladi.
     */
    public function oqimReport(Request $request)
    {
        $dekanFacultyId = get_dekan_faculty_id();

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

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if ($dekanFacultyId) {
            $facultyQuery->where('id', $dekanFacultyId);
        }

        $faculties = $facultyQuery->get();

        return view('admin.reports.oqim', compact(
            'educationTypes',
            'selectedEducationType',
            'faculties',
            'dekanFacultyId'
        ));
    }

    /**
     * AJAX: oqim hisoboti ma'lumotlari (JSON).
     */
    public function oqimReportData(Request $request)
    {
        try {
            ini_set('memory_limit', '512M');
            set_time_limit(120);

            $report = $this->buildOqimReport($request);
            return response()->json($report);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Oqim hisobotini Excel (.xlsx) ko'rinishida yuklab olish.
     * Har bir variant (guruh / a.b / a.b.c) alohida varaqda emas — tanlangan variant.
     */
    public function oqimReportExport(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $report = $this->buildOqimReport($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Agar "barcha variantlar" tanlangan bo'lsa — har biri alohida varaqda
        $variants = $report['variants']; // [1=>'Guruh', 2=>'A.B', ...]
        foreach ($variants as $vNum => $vTitle) {
            $blocks = $report['byVariant'][$vNum];
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($vTitle);
            $this->fillOqimSheet($sheet, $blocks, $report['header']);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'Oqim_' . date('Y-m-d_H-i') . '.xlsx';
        $temp = tempnam(sys_get_temp_dir(), 'oqim_');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($temp);
        $spreadsheet->disconnectWorksheets();

        return response()->download($temp, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Guruh tuzatishlari (override) sahifasi: aralash tilli / muammoli guruhlar
     * ro'yxati va joriy qo'lda tuzatishlar. HEMIS to'g'rilanmaguncha ishlatiladi.
     */
    public function oqimOverrides(Request $request)
    {
        $all       = $this->buildOqimGroupList();
        $mixed     = array_values(array_filter($all, fn($g) => $g['is_mixed']));
        $overrides = \App\Models\GroupOverride::orderBy('group_name')->get();

        return view('admin.reports.oqim-overrides', compact('mixed', 'all', 'overrides'));
    }

    /**
     * Bitta guruh uchun qo'lda tuzatishni saqlaydi (til yoki hisobdan chiqarish).
     */
    public function oqimOverrideSave(Request $request)
    {
        $data = $request->validate([
            'group_hemis_id' => 'required|integer',
            'group_name'     => 'nullable|string',
            'lang'           => 'nullable|in:uz,rus,ing',
            'excluded'       => 'nullable|boolean',
            'note'           => 'nullable|string|max:255',
        ]);

        $excluded = (bool) ($data['excluded'] ?? false);
        $lang     = $data['lang'] ?? null;

        // Agar hech qanday tuzatish qolmasa — yozuvni o'chiramiz
        if (!$excluded && empty($lang)) {
            \App\Models\GroupOverride::where('group_hemis_id', $data['group_hemis_id'])->delete();
            return response()->json(['ok' => true, 'removed' => true]);
        }

        \App\Models\GroupOverride::updateOrCreate(
            ['group_hemis_id' => $data['group_hemis_id']],
            [
                'group_name' => $data['group_name'] ?? null,
                'lang'       => $lang,
                'excluded'   => $excluded,
                'note'       => $data['note'] ?? null,
                'updated_by' => optional($request->user())->id,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Guruh tuzatishini o'chiradi (HEMISdagi holatga qaytaradi).
     */
    public function oqimOverrideDelete(Request $request)
    {
        $request->validate(['group_hemis_id' => 'required|integer']);
        \App\Models\GroupOverride::where('group_hemis_id', $request->group_hemis_id)->delete();
        return response()->json(['ok' => true]);
    }

    /**
     * Aralash tilli guruhlarni aniqlaydi: bir xil "katta guruh" (a/b/c birlashtirilgan)
     * ichida bir nechta til bo'lsa — muammoli. Qo'lда tuzatishlar allaqachon qo'llangan holда.
     */
    private function detectMixedLanguageGroups(): array
    {
        return array_values(array_filter($this->buildOqimGroupList(), fn($g) => $g['is_mixed']));
    }

    /**
     * Barcha guruhlarni "katta guruh" (base) kesimida yig'adi va har biriga 'is_mixed'
     * bayrog'ini qo'yadi (bir nechta til bo'lsa — aralash). Qo'lda tuzatishlar (override)
     * qo'llangan holda. Bu ro'yxat ham aralash guruhlarni aniqlash, ham "Guruh tuzatish"
     * sahifasida ISTALGAN guruh tilini o'zgartirish uchun ishlatiladi.
     */
    private function buildOqimGroupList(): array
    {
        $rows = DB::table('students as s')
            ->join('departments as d', 's.department_id', '=', 'd.department_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('d.structure_type_code', 11)
            ->where('d.active', true)
            ->where('g.active', true)
            ->where('s.student_status_code', 11)
            ->whereNotNull('s.group_id')
            ->select(
                's.department_name', 's.level_name',
                's.group_id', 's.group_name', 'g.education_lang_name',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('s.department_name', 's.level_name', 's.group_id', 's.group_name', 'g.education_lang_name')
            ->get();

        // Qo'lda tuzatishlarni qo'llaymiz
        $overrides = \App\Models\GroupOverride::all()->keyBy('group_hemis_id');

        $groups = [];
        foreach ($rows as $r) {
            $ov = $overrides[$r->group_id] ?? null;
            if ($ov && $ov->excluded) {
                continue;
            }
            $nameNoLang = $this->oqimStripLang($r->group_name);
            [$base] = $this->oqimSplitBase($nameNoLang);
            $lang = ($ov && $ov->lang) ? $ov->lang : $this->oqimLangKey($r->education_lang_name, $r->group_name);

            $key = mb_strtolower(trim($r->department_name)) . '|' . mb_strtolower(trim($r->level_name)) . '|' . $base;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'department_name' => $r->department_name,
                    'level_name'      => $r->level_name,
                    'base'            => $base,
                    'langs'           => [],
                    'members'         => [],
                ];
            }
            $groups[$key]['langs'][$lang] = true;
            $groups[$key]['members'][] = [
                'group_id'   => (int) $r->group_id,
                'group_name' => $nameNoLang,
                'hemis_lang' => $r->education_lang_name,
                'lang'       => $lang,
                'count'      => (int) $r->cnt,
                'overridden' => (bool) ($ov && ($ov->lang || $ov->excluded)),
            ];
        }

        // Barcha guruhlar — aralash (bir nechta til) bo'lsa 'is_mixed' bilan belgilaymiz
        $all = [];
        foreach ($groups as $gr) {
            usort($gr['members'], fn($a, $b) => strcmp($a['group_name'], $b['group_name']));
            $gr['is_mixed'] = count($gr['langs']) > 1;
            $gr['langs']    = array_keys($gr['langs']);
            $all[] = $gr;
        }
        usort($all, fn($a, $b) => [$a['department_name'], $a['level_name'], $a['base']] <=> [$b['department_name'], $b['level_name'], $b['base']]);

        return $all;
    }

    /**
     * Oqim hisobotini qurish — filtrlar bo'yicha talabalarni fakultet/yo'nalish/kurs/oqim/guruh
     * kesimida hisoblab, tanlangan variant(lar) bo'yicha tuzilma qaytaradi.
     */
    private function buildOqimReport(Request $request): array
    {
        $dekanFacultyId = get_dekan_faculty_id();

        // ---- Talabalarni guruh kesimida sanaymiz ----
        // Faqat FAOL guruhlar (g.active) — eski/nofaol guruhga biriktirilib qolgan
        // talabalar "fantom" guruh sifatida chiqmasligi uchun.
        $q = DB::table('students as s')
            ->join('departments as d', 's.department_id', '=', 'd.department_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 's.group_id')
            ->where('d.structure_type_code', 11)
            ->where('d.active', true)
            ->where('g.active', true)
            ->where('s.student_status_code', 11) // faqat faol (o'qiyotgan) talabalar
            ->whereNotNull('s.group_id')
            ->select(
                's.department_id', 's.department_name',
                's.specialty_id', 's.specialty_name',
                's.level_code', 's.level_name',
                's.group_id', 's.group_name',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy(
                's.department_id', 's.department_name',
                's.specialty_id', 's.specialty_name',
                's.level_code', 's.level_name',
                's.group_id', 's.group_name'
            );

        if ($dekanFacultyId) {
            $faculty = Department::find($dekanFacultyId);
            if ($faculty) {
                $q->where('s.department_id', $faculty->department_hemis_id);
            }
        } elseif ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $q->where('s.department_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('education_type')) {
            $q->where('s.education_type_code', $request->education_type);
        }

        $rows = $q->get();

        // Guruhlarning ta'lim tili (rus/ingliz oqimlarini alohida ajratish uchun)
        $langMap = DB::table('groups')
            ->whereNotNull('group_hemis_id')
            ->pluck('education_lang_name', 'group_hemis_id');

        // ---- Qo'lda tuzatishlar (override): HEMIS xato bo'lsa til/exclude ----
        $overrides = DB::table('group_overrides')->get();
        $overrideLang = [];   // group_hemis_id => 'uz'|'rus'|'ing'
        $excludedIds = [];    // hisobga olinmaydigan guruhlar
        foreach ($overrides as $ov) {
            if ($ov->excluded) {
                $excludedIds[(int) $ov->group_hemis_id] = true;
            }
            if (!empty($ov->lang)) {
                $overrideLang[(int) $ov->group_hemis_id] = $ov->lang;
            }
        }

        // ---- Ta'lim turi (guruh bo'yicha): Qo'shma ta'lim vs Oddiy (kunduzgi) ----
        // Guruh Qo'shma deb hisoblanadi, agar undagi talabalarning ko'pchiligi
        // student_type = "Qo'shma" bo'lsa. Qo'shma va oddiy guruhlar aralashmasin.
        $trackRows = DB::table('students')
            ->where('student_status_code', 11)
            ->whereNotNull('group_id')
            ->selectRaw('group_id, COUNT(*) as tot, SUM(student_type_name = ?) as q', ['Qo\'shma'])
            ->groupBy('group_id')
            ->get();
        $trackMap = [];
        foreach ($trackRows as $tr) {
            $trackMap[(int) $tr->group_id] = ((int) $tr->q * 2 > (int) $tr->tot) ? 'qoshma' : 'oddiy';
        }
        // Filter: barchasi / oddiy (kunduzgi) / qoshma
        $talimFilter = $request->get('talim', 'all');
        if (!in_array($talimFilter, ['all', 'oddiy', 'qoshma'], true)) {
            $talimFilter = 'all';
        }

        // Fakultetlararo optimizatsiya: bir xil yo'nalishli fakultetlar (masalan 1-son va
        // 2-son davolash) guruhlarini BIRGA optimallashtirish uchun pool qilinadi — bu kam
        // to'lgan guruhlarni fakultetlararo birlashtirib, oqim/guruhlarni butun qiladi.
        // Fakultetlarning o'zi saqlanadi: bu bayroq FAQAT optimizatsiya so'rovida yuboriladi,
        // "joriy (tasdiqlangan) holat"ga hech qachon ta'sir qilmaydi.
        // Fakultetlararo oqim optimizatsiyasi: fakultetlar ALOHIDA qoladi (har birining o'z
        // dekani bor). Optimizatsiyada bir fakultetning kam to'lgan (ortiqcha) oqimi qo'shni
        // fakultetning (bir yo'nalishli — masalan 1-son/2-son davolash) shu kurs va tildagi
        // oqimiga — joy bo'lsa — ko'chiriladi; shunda oqimlar soni kamayadi. Fakultetlar
        // birlashmaydi. Bayroq FAQAT optimizatsiya so'rovida yuboriladi.
        $crossFaculty = $request->boolean('merge_faculties');

        // ---- Tuzilmaga yig'amiz: fakultet+yo'nalish -> kurs -> guruhlar (fakultetlar alohida) ----
        $blocks = $this->assembleOqimBlocks(
            $rows, $excludedIds, $trackMap, $talimFilter, $langMap, $overrideLang
        );

        // ---- Me'yorlar (chegaralar) — qo'lda beriladi, tolerantlik (+/-) bilan ----
        $params = [
            'optimize' => $request->boolean('optimize'),
            'oqim_max' => max(1, (int) $request->get('oqim_max', 100)),
            'oqim_tol' => max(0, (int) $request->get('oqim_tol', 0)),
            'ab_max'   => max(1, (int) $request->get('ab_max', 15)),
            'ab_tol'   => max(0, (int) $request->get('ab_tol', 0)),
            'abc_max'  => max(1, (int) $request->get('abc_max', 10)),
            'abc_tol'  => max(0, (int) $request->get('abc_tol', 0)),
        ];

        // Tanlangan variant(lar). full=to'liq guruh, ab=a,b guruhcha, abc=a,b,c guruhcha,
        // auto=kursga qarab (1-3 kurs -> a,b, 4+ kurs -> a,b,c).
        $variantNames = [
            'full' => 'Guruh (to\'liq)',
            'ab'   => 'a,b guruhchalar',
            'abc'  => 'a,b,c guruhchalar',
            'auto' => 'Avtomatik (kursga qarab)',
        ];
        $variantParam = (string) $request->get('variant', 'auto');
        if ($variantParam === 'all') {
            $variants = [
                'full' => $variantNames['full'],
                'ab'   => $variantNames['ab'],
                'abc'  => $variantNames['abc'],
            ];
        } else {
            $key = $this->oqimNormalizeVariant($variantParam);
            $variants = [$key => $variantNames[$key]];
        }

        // Har bir variant uchun oqimlarni quramiz. Optimizatsiya + fakultetlararo yoqilgan
        // bo'lsa — (1) chala asosiy guruhlar qo'shni fakultet bilan to'ldiriladi (base
        // completion), (2) kam to'lgan oqimlar qo'shni fakultet oqimiga ko'chiriladi.
        $byVariant = [];
        $xmovesByVariant = [];
        $xbmovesByVariant = [];
        foreach ($variants as $vKey => $vTitle) {
            $xbmoves = [];
            $vb = $this->buildOqimBlocksForVariant($blocks, $params, $vKey, $crossFaculty, $xbmoves);
            $xmoves = [];
            if ($params['optimize'] && $crossFaculty) {
                [$vb, $xmoves] = $this->applyCrossFacultyOqimMerge($vb, $params);
            }
            $byVariant[$vKey]       = $vb;
            $xmovesByVariant[$vKey]  = $xmoves;
            $xbmovesByVariant[$vKey] = $xbmoves;
        }

        $header = [
            "Toshkent davlat tibbiyot universiteti Termiz filiali fakultetlar, yo'nalishlar va kurslar kesimi bo'yicha talabalarning oqim va guruhlarga taqsimlanish tartibi",
            "Kurslar va yo'nalishlar kesimida guruhlar va ulardagi talabalar soni (" . now()->format('d.m.Y') . " holati)",
        ];

        // JSON uchun asosiy (birinchi) variantni ham qulay ko'rinishda beramiz
        $firstVariant = array_key_first($byVariant);

        // Optimizatsiya bosilganda — nima o'zgarishi haqida reja (solishtirma vkladka uchun).
        // Jami sonlar HAQIQIY layoutlardan sanaladi (joriy va optimizatsiyalangan display).
        $plan = null;
        if ($params['optimize']) {
            $planVariant = array_key_first($variants);
            $curDisplay  = $this->buildOqimBlocksForVariant($blocks, ['optimize' => false] + $params, $planVariant, false);
            $plan = $this->computeOptimizationPlan(
                $blocks, $params, $planVariant,
                $curDisplay, $byVariant[$planVariant],
                $xmovesByVariant[$planVariant] ?? [], $xbmovesByVariant[$planVariant] ?? []
            );
        }

        return [
            'header'    => $header,
            'variants'  => $variants,
            'byVariant' => $byVariant,
            'blocks'    => $byVariant[$firstVariant],
            'params'    => $params,
            'optimize'  => $params['optimize'],
            'plan'      => $plan,
            'generated_at' => now()->format('d.m.Y H:i'),
        ];
    }

    /**
     * HEMIS qatorlaridan fakultet+yo'nalish -> kurs -> guruhlar tuzilmasini yig'adi.
     * Fakultetlar HAR DOIM alohida blok bo'ladi. Har blokka 'merge_key' beriladi —
     * bir xil yo'nalishli (masalan 1-son/2-son davolash) fakultetlar bir xil merge_key
     * oladi; fakultetlararo oqim ko'chirish faqat shu kalit doirasida bo'ladi.
     * Bloklar tartiblangan holda qaytariladi.
     */
    private function assembleOqimBlocks(
        $rows, array $excludedIds, array $trackMap,
        string $talimFilter, $langMap, array $overrideLang
    ): array {
        $blocks = [];
        foreach ($rows as $r) {
            // Hisobdan chiqarilgan (xato biriktirilgan) guruhlarni tashlab yuboramiz
            if (isset($excludedIds[(int) $r->group_id])) {
                continue;
            }

            // Guruh turi (qo'shma / oddiy) va filter
            $track = $trackMap[(int) $r->group_id] ?? 'oddiy';
            if ($talimFilter !== 'all' && $track !== $talimFilter) {
                continue;
            }

            // Blok kaliti — HAQIQIY fakultet + yo'nalish NOMI + ta'lim turi bo'yicha
            // (fakultetlar birlashmaydi). Qo'shma va oddiy ta'lim hech qachon aralashmaydi.
            $dept = $r->department_name;
            $blockKey = mb_strtolower(trim((string) $dept)) . '|'
                . mb_strtolower(trim((string) $r->specialty_name)) . '|' . $track;
            if (!isset($blocks[$blockKey])) {
                $title = $this->oqimBlockTitle($dept, $r->specialty_name);
                if ($track === 'qoshma') {
                    $title .= " — Qo'shma ta'lim";
                }
                // merge_key — "N-son" prefiksisiz yo'nalish: bir yo'nalishli fakultetlarni
                // fakultetlararo oqim ko'chirishda "qo'shni" deb topish uchun.
                $mergeKey = mb_strtolower(trim((string) $this->oqimMergeDeptName($dept))) . '|'
                    . mb_strtolower(trim((string) $r->specialty_name)) . '|' . $track;
                $blocks[$blockKey] = [
                    'department_name' => $dept,
                    'specialty_name'  => $r->specialty_name,
                    'track'           => $track,
                    'title'           => $title,
                    'merge_key'       => $mergeKey,
                    'courses'         => [],
                ];
            }
            $lvlKey = (string) $r->level_code;
            if (!isset($blocks[$blockKey]['courses'][$lvlKey])) {
                $blocks[$blockKey]['courses'][$lvlKey] = [
                    'level_code' => $r->level_code,
                    'level_name' => $r->level_name ?: ($r->level_code . '-kurs'),
                    'groups'     => [],
                ];
            }
            $langName = $langMap[$r->group_id] ?? null;
            // Nomdagi eski til yorlig'ini ("(rus)","(ang)"...) olib tashlaymiz.
            $nameNoLang = $this->oqimStripLang($r->group_name);
            // Nomdan asosiy guruh nomi (masalan d2/d25-01) va kichik guruh harfini (a/b/c) ajratamiz.
            [$base, $letter] = $this->oqimSplitBase($nameNoLang);
            // Til: avval qo'lda tuzatish (override), bo'lmasa HEMISdagi
            $lang = $overrideLang[(int) $r->group_id]
                ?? $this->oqimLangKey($langName, $r->group_name);
            $langLabelMap = ['uz' => "o'z", 'rus' => 'rus', 'ing' => 'ing'];
            $blocks[$blockKey]['courses'][$lvlKey]['groups'][] = [
                'group_id'   => $r->group_id,
                'name'       => $nameNoLang,
                'base'       => $base,
                'letter'     => $letter,
                'count'      => (int) $r->cnt,
                'lang'       => $lang,
                'lang_label' => $langLabelMap[$lang] ?? "o'z",
            ];
        }

        // Bloklarni fakultet + yo'nalish + ta'lim turi bo'yicha tartiblaymiz (oddiy oldin, qo'shma keyin)
        uasort($blocks, function ($a, $b) {
            return [$a['department_name'], $a['specialty_name'], $a['track']]
                <=> [$b['department_name'], $b['specialty_name'], $b['track']];
        });

        return $blocks;
    }

    /**
     * Optimizatsiya rejasi: joriy holatni me'yor bo'yicha optimal holat bilan
     * solishtiradi. Optimizatsiya kam to'lgan akademik guruhlarni birlashtirib,
     * guruhlar (va shu bilan kichik guruhlar hamda oqimlar) sonini kamaytiradi.
     * Kichik guruhlar soni kursga qarab qat'iy (1-3 a,b; 4-6 a,b,c) — o'zgarmaydi.
     *
     * $blocks — fakultetlar alohida bloklari (subgroup solishtirmasi uchun). Jami sonlar
     * HAQIQIY layoutlardan sanaladi: $curDisplay (joriy) va $optDisplay (optimizatsiyalangan,
     * fakultetlararo to'ldirish va oqim ko'chirish qo'llangan). $xmoves — ko'chirilgan
     * oqimlar; $xbmoves — fakultetlararo to'ldirilgan chala guruhlar.
     */
    private function computeOptimizationPlan(array $blocks, array $params, string $variant, array $curDisplay, array $optDisplay, array $xmoves = [], array $xbmoves = []): array
    {
        // Jami sonlar — haqiqiy display layoutlardan (fakultetlararo o'zgarishlarni ham qamraydi)
        [$curBase, $curSub, $curOqim] = $this->oqimCountDisplay($curDisplay);
        [$optBase, $optSub, $optOqim] = $this->oqimCountDisplay($optDisplay);

        $curParams = ['optimize' => false] + $params;
        $optParams = ['optimize' => true] + $params;

        $moves = [];

        foreach ($blocks as $block) {
            foreach ($block['courses'] as $course) {
                $bases = $this->aggregateBaseGroups($course['groups']);
                if (empty($bases)) {
                    continue;
                }
                $levelNum = $this->oqimLevelNumber($course['level_name'], (string) $course['level_code']);
                [$subCount, $subMax, $subTol, $eff] = $this->oqimSubRule($variant, $levelNum, $params);

                $curB = $this->oqimCourseBases($bases, $curParams, $variant, $levelNum);
                $optB = $this->oqimCourseBases($bases, $optParams, $variant, $levelNum);

                // Til bo'yicha solishtirma (kichik guruh darajasida)
                $curByLang = [];
                foreach ($curB as $b) { $curByLang[$b['lang']][] = $b; }
                $optByLang = [];
                foreach ($optB as $b) { $optByLang[$b['lang']][] = $b; }

                $langs = array_unique(array_merge(array_keys($curByLang), array_keys($optByLang)));
                foreach ($langs as $lang) {
                    $cg = $curByLang[$lang] ?? [];
                    $og = $optByLang[$lang] ?? [];
                    usort($cg, fn($a, $b) => $this->oqimNatCmp($a['base'], $b['base']));
                    usort($og, fn($a, $b) => $this->oqimNatCmp($a['base'], $b['base']));

                    // Kichik guruhlarni tekis ro'yxatga yig'amiz (nom + son)
                    $curSubs = [];
                    foreach ($cg as $b) { foreach ($b['rows'] as $rr) { $curSubs[] = ['name' => $rr['name'], 'count' => $rr['count']]; } }
                    $newSubs = [];
                    foreach ($og as $b) { foreach ($b['rows'] as $rr) { $newSubs[] = ['name' => $rr['name'], 'count' => $rr['count']]; } }

                    // O'zgarish bo'lmasa — o'tkazamiz
                    if (count($cg) === count($og) && count($curSubs) === count($newSubs)) {
                        continue;
                    }

                    $curNames = array_map(fn($b) => $b['base'], $cg);
                    $optNames = array_map(fn($b) => $b['base'], $og);
                    $langLabel = $cg[0]['lang_label'] ?? ($og[0]['lang_label'] ?? '');

                    $moves[] = [
                        'block'      => $block['title'],
                        'course'     => $course['level_name'],
                        'lang'       => $langLabel,
                        'from'       => count($cg),
                        'to'         => count($og),
                        'cur_sub_n'  => count($curSubs),
                        'new_sub_n'  => count($newSubs),
                        'cur_subs'   => $curSubs,
                        'new_subs'   => $newSubs,
                        'dropped'    => array_values(array_diff($curNames, $optNames)),
                        'sub'        => $eff === 'full' ? 'to\'liq' : ($eff === 'abc' ? 'a,b,c' : 'a,b'),
                    ];
                }
            }
        }

        // Eng ko'p kichik guruh kamaytiradiganlar birinchi
        usort($moves, fn($a, $b) => ($b['cur_sub_n'] - $b['new_sub_n']) <=> ($a['cur_sub_n'] - $a['new_sub_n']));

        return [
            'cur_base'      => $curBase,
            'opt_base'      => $optBase,
            'base_reduce'   => $curBase - $optBase,
            'cur_subgroups' => $curSub,
            'opt_subgroups' => $optSub,
            'reduce'        => $curSub - $optSub,
            'cur_oqim'      => $curOqim,
            'opt_oqim'      => $optOqim,
            'oqim_reduce'   => $curOqim - $optOqim,
            'moves'         => $moves,
            'xmoves'        => array_values($xmoves),
            'xbmoves'       => array_values($xbmoves),
        ];
    }

    /**
     * Display layoutdan jami sonlarni sanaydi: [akademik guruh, kichik guruh (guruhcha), oqim].
     * Akademik guruh — qatorlardagi asosiy nom (kichik guruh harfisiz) bo'yicha noyob sanaladi.
     */
    private function oqimCountDisplay(array $displayBlocks): array
    {
        $bases = []; $sub = 0; $oqim = 0;
        foreach ($displayBlocks as $blk) {
            foreach ($blk['courses'] as $course) {
                foreach ($course['oqims'] as $oq) {
                    $oqim++;
                    foreach ($oq['rows'] as $r) {
                        $sub++;
                        $bases[$this->oqimBaseOfRow($r['name'])] = true;
                    }
                }
            }
        }
        return [count($bases), $sub, $oqim];
    }

    /**
     * Qator nomidan asosiy guruh nomini ajratadi: " (til)" qo'shimchasi va oxirgi kichik
     * guruh harfi (a-f) olib tashlanadi. "d1/21-08a (o'z)" -> "d1/21-08".
     */
    private function oqimBaseOfRow(string $name): string
    {
        $n = preg_replace('/\s*\([^)]*\)\s*$/u', '', trim($name)); // " (o'z)"
        $n = (string) $n;
        if (preg_match('/^(.*\d)[a-f]$/u', $n, $m)) { // oxirgi harf faqat raqamdan keyin
            return $m[1];
        }
        return $n;
    }

    /**
     * Variant kalitini normallashtiradi (eski raqamli qiymatlarni ham qabul qiladi).
     */
    private function oqimNormalizeVariant(string $v): string
    {
        return match ($v) {
            '1', 'full' => 'full',
            '2', 'ab'   => 'ab',
            '3', 'abc'  => 'abc',
            default     => 'auto',
        };
    }

    /**
     * Bitta variant uchun barcha bloklarning oqim tuzilmasini quradi.
     * $params — me'yorlar (oqim_max, ab_max, abc_max + tolerantlik) va optimize bayrog'i.
     */
    private function buildOqimBlocksForVariant(array $blocks, array $params, string $variant, bool $crossFaculty = false, array &$xbmoves = []): array
    {
        // ---- FAZA 1: har blok/kurs uchun asosiy guruhlarni (finalBases) quramiz ----
        $prepared = [];
        foreach ($blocks as $bi => $block) {
            $courseList = $block['courses'];
            uasort($courseList, fn($a, $b) => $this->oqimNatCmp((string) $a['level_code'], (string) $b['level_code']));

            $cx = [];
            foreach ($courseList as $course) {
                $bases    = $this->aggregateBaseGroups($course['groups']);
                $levelNum = $this->oqimLevelNumber($course['level_name'], (string) $course['level_code']);
                [$subCount, $subMax, $subTol] = $this->oqimSubRule($variant, $levelNum, $params);
                $cx[] = [
                    'level_name' => $course['level_name'],
                    'level_code' => $course['level_code'],
                    'sub_count'  => $subCount,
                    'sub_max'    => $subMax,
                    'sub_tol'    => $subTol,
                    'bases'      => $this->oqimCourseBases($bases, $params, $variant, $levelNum),
                ];
            }
            $prepared[$bi] = [
                'title'           => $block['title'],
                'department_name' => $block['department_name'] ?? $block['title'],
                'merge_key'       => $block['merge_key'] ?? ($block['title'] ?? ''),
                'courses'         => $cx,
            ];
        }

        // ---- FAZA 2: fakultetlararo — kurs oxiridagi CHALA asosiy guruhlarni qo'shni
        //      fakultet bilan birlashtirib to'liq (a,b yoki a,b,c) qilamiz (guruhcha qo'shmasdan) ----
        if (!empty($params['optimize']) && $crossFaculty) {
            $xbmoves = $this->completeCrossFacultyBases($prepared, $params);
        }

        // ---- FAZA 3: har blok/kurs asosiy guruhlarini oqimlarga qadoqlab, displayni quramiz ----
        $result = [];
        foreach ($prepared as $blk) {
            $courses = [];
            foreach ($blk['courses'] as $course) {
                $oqims = $this->packOqims($course['bases'], $params['oqim_max'], $params['oqim_tol']);
                $total = 0;
                $displayOqims = [];
                foreach ($oqims as $idx => $oq) {
                    $rowsOut = [];
                    $oqimTotal = 0;
                    $hasVisitor = false;
                    foreach ($oq as $bg) {
                        $total += $bg['total'];
                        $oqimTotal += $bg['total'];
                        foreach ($bg['rows'] as $sub) {
                            $rowsOut[] = $sub;
                            if (!empty($sub['visitor'])) {
                                $hasVisitor = true;
                            }
                        }
                    }
                    $displayOqims[] = [
                        'label'       => ($idx + 1) . '-oqim',
                        'total'       => $oqimTotal,
                        'lang'        => $oq[0]['lang'] ?? 'uz',
                        'lang_label'  => $oq[0]['lang_label'] ?? "o'z",
                        'has_visitor' => $hasVisitor,
                        'rows'        => $rowsOut,
                    ];
                }
                if (empty($displayOqims)) {
                    continue; // bu kursda guruh qolmadi (hammasi qo'shni fakultetga ko'chdi)
                }
                $courses[] = [
                    'level_name' => $course['level_name'],
                    'level_code' => $course['level_code'],
                    'oqims'      => $displayOqims,
                    'total'      => $total,
                ];
            }
            if (empty($courses)) {
                continue;
            }
            $result[] = [
                'title'           => $blk['title'],
                'department_name' => $blk['department_name'],
                'merge_key'       => $blk['merge_key'],
                'courses'         => $courses,
            ];
        }
        return $result;
    }

    /**
     * Fakultetlararo asosiy guruhlarni TO'LDIRISH: bir xil yo'nalishli (merge_key), kurs va
     * tildagi CHALA asosiy guruhlarni (kichik guruhlari subCount dan kam) qo'shni fakultetlar
     * bilan birlashtirib, minimal guruhchada TO'LIQ guruhlarga qayta yig'adi — shunda "bir
     * joyda a,b, boshqa joyda yolg'iz a" holati yo'qoladi. Guruhchalar soni OSHMAYDI (minimal).
     * Natijaviy guruhlar eng ko'p talaba beruvchi (qabul qiluvchi) fakultetga biriktiriladi.
     * $prepared ni o'zgartiradi (ref) va ko'chirishlar ro'yxatini (xbmoves) qaytaradi.
     */
    private function completeCrossFacultyBases(array &$prepared, array $params): array
    {
        $langLabels = ['uz' => "o'z", 'rus' => 'rus', 'ing' => 'ing'];

        // Chala asosiy guruhlarni (merge_key | level_code | til) bo'yicha guruhlaymiz.
        $groups = [];
        foreach ($prepared as $bi => $blk) {
            foreach ($blk['courses'] as $ci => $course) {
                $subCount = $course['sub_count'];
                foreach ($course['bases'] as $bidx => $base) {
                    if (count($base['rows']) >= $subCount) {
                        continue; // to'liq guruh — tegmaymiz
                    }
                    $gkey = $blk['merge_key'] . '|' . $course['level_code'] . '|' . ($base['lang'] ?? 'uz');
                    $groups[$gkey][] = ['bi' => $bi, 'ci' => $ci, 'bidx' => $bidx];
                }
            }
        }

        // Mutatsiyalar OXIRIDA qo'llanadi (indekslar buzilmasin uchun): $removeByCourse
        // bidx'larni, $addByCourse yangi guruhlarni to'playdi.
        $xbmoves = [];
        $removeByCourse = []; // [bi][ci] => [bidx, ...]
        $addByCourse    = []; // [bi][ci] => [newBase, ...]

        foreach ($groups as $refs) {
            // Faqat kamida 2 ta HAR XIL fakultetdagi chala guruh bo'lsa birlashtiramiz.
            $blocksInvolved = array_unique(array_map(fn($r) => $r['bi'], $refs));
            if (count($blocksInvolved) < 2) {
                continue;
            }

            // Birinchi chaladan kurs qoidalarini olamiz (barchasi bir xil kurs/til).
            $first    = $refs[0];
            $c0       = $prepared[$first['bi']]['courses'][$first['ci']];
            $subCount = $c0['sub_count'];

            // Chala guruhlarni yig'amiz: talabalar, nomlar, fakultetlar bo'yicha hissa.
            $Tp = 0; $names = []; $lang = 'uz'; $langLabel = "o'z";
            $byBlock = []; // bi => talaba soni
            foreach ($refs as $r) {
                $base = $prepared[$r['bi']]['courses'][$r['ci']]['bases'][$r['bidx']];
                $Tp  += $base['total'];
                $names[] = $base['base'];
                $lang = $base['lang'] ?? 'uz';
                $langLabel = $base['lang_label'] ?? ($langLabels[$lang] ?? "o'z");
                $byBlock[$r['bi']] = ($byBlock[$r['bi']] ?? 0) + $base['total'];
            }
            if ($Tp <= 0) {
                continue;
            }

            // Qabul qiluvchi fakultet — eng ko'p talaba beruvchi.
            arsort($byBlock);
            $hostBi = array_key_first($byBlock);

            // Qayta yig'amiz: MINIMAL guruhchada to'liq guruhlar (guruhcha oshmaydi).
            usort($names, fn($a, $b) => $this->oqimNatCmp($a, $b));
            [$genPrefix, $genWidth, $genNum] = $this->oqimBaseNameSeed($names);
            $chunks   = $this->oqimOptimalSubgroups($Tp, $subCount, $c0['sub_max'], $c0['sub_tol']);
            $suffix   = $langLabel !== '' ? ' (' . $langLabel . ')' : '';
            $letters  = ['a', 'b', 'c', 'd', 'e', 'f'];
            $fromNames = array_values(array_unique($names));

            $newBases = [];
            foreach ($chunks as $i => $chunk) {
                if (isset($names[$i])) {
                    $bname = $names[$i];
                } elseif ($genPrefix !== null) {
                    $bname = $genPrefix . str_pad((string) (++$genNum), $genWidth, '0', STR_PAD_LEFT);
                } else {
                    $bname = $names[0] . '-' . ($i + 1);
                }
                $rows = [];
                foreach (array_values($chunk) as $j => $size) {
                    $lbl = ($subCount <= 1) ? $bname : ($bname . ($letters[$j] ?? ($j + 1)));
                    $rows[] = ['name' => $lbl . $suffix, 'count' => $size, 'visitor' => true, 'from' => 'fakultetlararo'];
                }
                $newBases[] = [
                    'base'       => $bname,
                    'lang'       => $lang,
                    'lang_label' => $langLabel,
                    'total'      => array_sum($chunk),
                    'rows'       => $rows,
                ];
            }

            // Qabul qiluvchi fakultetning shu kursini topamiz.
            $hostCi = null;
            foreach ($prepared[$hostBi]['courses'] as $ci => $course) {
                if ((string) $course['level_code'] === (string) $c0['level_code']) {
                    $hostCi = $ci;
                    break;
                }
            }
            if ($hostCi === null) {
                $hostBi = $first['bi'];
                $hostCi = $first['ci'];
            }

            // Mutatsiyalarni to'playmiz (hozir qo'llamaymiz).
            foreach ($refs as $r) {
                $removeByCourse[$r['bi']][$r['ci']][] = $r['bidx'];
            }
            foreach ($newBases as $nb) {
                $addByCourse[$hostBi][$hostCi][] = $nb;
            }

            $hostDept = $this->oqimFacultyShort($prepared[$hostBi]['department_name']);
            $xbmoves[] = [
                'course'    => $c0['level_name'],
                'lang'      => $langLabel,
                'to_fac'    => $hostDept,
                'from'      => $fromNames,
                'total'     => $Tp,
                'new_bases' => count($newBases),
            ];
        }

        // Barcha o'chirishlarni bir vaqtda qo'llaymiz (har kurs ichida kattadan kichikka),
        // so'ng yangi to'liq guruhlarni qo'shamiz — shunda bidx indekslar buzilmaydi.
        foreach ($removeByCourse as $bi => $byCi) {
            foreach ($byCi as $ci => $idxs) {
                $idxs = array_unique($idxs);
                rsort($idxs);
                foreach ($idxs as $ix) {
                    array_splice($prepared[$bi]['courses'][$ci]['bases'], $ix, 1);
                }
            }
        }
        foreach ($addByCourse as $bi => $byCi) {
            foreach ($byCi as $ci => $newBases) {
                foreach ($newBases as $nb) {
                    $prepared[$bi]['courses'][$ci]['bases'][] = $nb;
                }
            }
        }

        return $xbmoves;
    }

    /**
     * Fakultetlararo OQIM ko'chirish (fakultetlar ALOHIDA qoladi): bir fakultetning kam
     * to'lgan (ortiqcha) oqimini qo'shni fakultetning (bir xil merge_key, kurs va til)
     * joyi bor oqimiga to'liq ko'chiradi. Shunda oqimlar soni kamayadi. Ko'chirilgan
     * guruhlar QABUL QILGAN fakultet blokida "mehmon" deb belgilanadi; yuboruvchi fakultet
     * o'sha oqimni endi ko'rsatmaydi. Qaytaradi: [yangilangan bloklar, ko'chirishlar (xmoves)].
     */
    private function applyCrossFacultyOqimMerge(array $blocks, array $params): array
    {
        $limit = $params['oqim_max'] + max(0, $params['oqim_tol']);
        $langLabels = ['uz' => "o'z", 'rus' => 'rus', 'ing' => 'ing'];

        // 1) Barcha oqimlarni egasi (blok, kurs) bilan guruhlab yig'amiz.
        //    Guruh kaliti: merge_key | level_code | til — ko'chirish faqat shu doirada.
        $groups = [];
        $order = 0;
        foreach ($blocks as $bi => $blk) {
            foreach ($blk['courses'] as $ci => $course) {
                foreach ($course['oqims'] as $oq) {
                    $oq['_bi']    = $bi;
                    $oq['_ci']    = $ci;
                    $oq['_dept']  = $this->oqimFacultyShort($blk['department_name'] ?? $blk['title']);
                    $oq['_level'] = $course['level_name'];
                    $oq['_order'] = $order++;
                    $gkey = ($blk['merge_key'] ?? $bi) . '|' . ($course['level_code'] ?? $ci) . '|' . ($oq['lang'] ?? 'uz');
                    $groups[$gkey][] = $oq;
                }
            }
        }

        // 2) Har bir guruhda — eng kichik oqimdan boshlab, boshqa fakultetdagi sig'adigan
        //    (best-fit) oqimga to'liq singdiramiz.
        $xmoves = [];
        $courseOqims = []; // "bi|ci" => [oqim, ...]
        foreach ($groups as $list) {
            $distinctBlocks = array_unique(array_map(fn($o) => $o['_bi'], $list));
            if (count($distinctBlocks) > 1) {
                usort($list, fn($a, $b) => $a['total'] <=> $b['total']);
                $removed = [];
                $n = count($list);
                for ($i = 0; $i < $n; $i++) {
                    if (isset($removed[$i]) || !empty($list[$i]['has_visitor'])) {
                        continue; // mehmon qabul qilgan oqim boshqa joyga ko'chmaydi
                    }
                    $S = $list[$i];
                    $bestJ = -1; $bestTotal = -1;
                    for ($j = 0; $j < $n; $j++) {
                        if ($j === $i || isset($removed[$j])) continue;
                        if ($list[$j]['_bi'] === $S['_bi']) continue; // bir fakultet ichida emas
                        $ht = $list[$j]['total'];
                        if ($ht + $S['total'] <= $limit && $ht > $bestTotal) {
                            $bestTotal = $ht; $bestJ = $j;
                        }
                    }
                    if ($bestJ < 0) continue;

                    $before = $list[$bestJ]['total'];
                    $moved = [];
                    foreach ($S['rows'] as $rr) {
                        $rr['visitor'] = true;
                        $rr['from']    = $S['_dept'];
                        $list[$bestJ]['rows'][] = $rr;
                        $moved[] = ['name' => $rr['name'], 'count' => $rr['count']];
                    }
                    $list[$bestJ]['total']      += $S['total'];
                    $list[$bestJ]['has_visitor'] = true;
                    $removed[$i] = true;
                    $xmoves[] = [
                        'course'      => $S['_level'],
                        'lang'        => $S['lang_label'] ?? ($langLabels[$S['lang'] ?? 'uz'] ?? ''),
                        'from_fac'    => $S['_dept'],
                        'to_fac'      => $list[$bestJ]['_dept'],
                        'moved'       => $moved,
                        'moved_total' => $S['total'],
                        'to_before'   => $before,
                        'to_after'    => $list[$bestJ]['total'],
                    ];
                }
                foreach ($list as $k => $oq) {
                    if (isset($removed[$k])) continue;
                    $courseOqims[$oq['_bi'] . '|' . $oq['_ci']][] = $oq;
                }
            } else {
                foreach ($list as $oq) {
                    $courseOqims[$oq['_bi'] . '|' . $oq['_ci']][] = $oq;
                }
            }
        }

        // 3) Bloklarni qayta quramiz — kursdagi oqimlarni asl tartibda qayta raqamlaymiz.
        foreach ($blocks as $bi => &$blk) {
            $newCourses = [];
            foreach ($blk['courses'] as $ci => $course) {
                $oqs = $courseOqims[$bi . '|' . $ci] ?? [];
                if (empty($oqs)) {
                    continue; // bu kursda oqim qolmadi (hammasi qo'shni fakultetga ko'chdi)
                }
                usort($oqs, fn($a, $b) => $a['_order'] <=> $b['_order']);
                $total = 0; $rebuilt = []; $num = 0;
                foreach ($oqs as $oq) {
                    $total += $oq['total'];
                    $oq['label'] = (++$num) . '-oqim';
                    unset($oq['_bi'], $oq['_ci'], $oq['_dept'], $oq['_level'], $oq['_order']);
                    $rebuilt[] = $oq;
                }
                $course['oqims'] = $rebuilt;
                $course['total'] = $total;
                $newCourses[] = $course;
            }
            $blk['courses'] = array_values($newCourses);
        }
        unset($blk);
        $blocks = array_values(array_filter($blocks, fn($b) => !empty($b['courses'])));

        return [$blocks, $xmoves];
    }

    /**
     * Fakultet nomining qisqa ko'rinishi (belgi/tag uchun): oxiridagi "fakulteti" so'zi olib
     * tashlanadi. "1-son davolash fakulteti" -> "1-son davolash".
     */
    private function oqimFacultyShort(string $dept): string
    {
        $d = preg_replace('/\s*fakultet(i|lari)?\s*$/ui', '', trim($dept));
        $d = trim((string) $d);
        return $d === '' ? trim($dept) : $d;
    }

    /**
     * HEMIS guruhlarini asosiy guruhlarga (base) yig'adi. Har bir asosiy guruh —
     * bitta tildagi bitta akademik guruh (masalan d2/d25-01), uning ichida kichik
     * guruhlar (a/b/c) a'zo sifatida saqlanadi. Har xil til hech qachon birlashmaydi.
     */
    private function aggregateBaseGroups(array $groups): array
    {
        $bases = [];
        foreach ($groups as $g) {
            $key = $g['base'] . '|' . $g['lang'];
            if (!isset($bases[$key])) {
                $bases[$key] = [
                    'base'       => $g['base'],
                    'lang'       => $g['lang'],
                    'lang_label' => $g['lang_label'],
                    'total'      => 0,
                    'members'    => [],
                ];
            }
            $bases[$key]['total'] += $g['count'];
            $bases[$key]['members'][] = [
                'letter' => $g['letter'],
                'count'  => $g['count'],
                'name'   => $g['name'], // HEMISdagi haqiqiy nom (til belgisisiz)
            ];
        }
        return array_values($bases);
    }

    /**
     * Asosiy guruhlarni oqimlarga taqsimlaydi.
     * Qoida: har xil tildagi guruhlar hech qachon bitta oqimga tushmaydi; guruhlar
     * raqami bo'yicha tartiblanadi va talaba soni bo'yicha (oqim_max + tolerantlik)
     * ochko'zlik bilan qadoqlanadi (bitta oqim ~ ma'ruzaga birga boradigan guruhlar).
     */
    private function packOqims(array $bases, int $oqimMax, int $oqimTol): array
    {
        if (empty($bases)) {
            return [];
        }

        usort($bases, fn($a, $b) => $this->oqimNatCmp($a['base'], $b['base']));
        foreach ($bases as $i => &$b) {
            $b['_order'] = $i;
        }
        unset($b);

        // Til bo'yicha ajratamiz — har xil til bir oqimda bo'lmasin
        $byLang = [];
        foreach ($bases as $b) {
            $byLang[$b['lang']][] = $b;
        }

        $limit = $oqimMax + max(0, $oqimTol);
        $chunks = [];
        foreach ($byLang as $list) {
            usort($list, fn($a, $b) => $this->oqimNatCmp($a['base'], $b['base']));
            $cur = [];
            $sum = 0;
            foreach ($list as $b) {
                if (!empty($cur) && ($sum + $b['total']) > $limit) {
                    $chunks[] = $cur;
                    $cur = [];
                    $sum = 0;
                }
                $cur[] = $b;
                $sum += $b['total'];
            }
            if (!empty($cur)) {
                $chunks[] = $cur;
            }
        }

        // Oqimlarni eng kichik guruh tartibi bo'yicha raqamlaymiz
        usort($chunks, function ($a, $b) {
            $minA = min(array_map(fn($x) => $x['_order'], $a));
            $minB = min(array_map(fn($x) => $x['_order'], $b));
            return $minA <=> $minB;
        });

        return $chunks;
    }

    /**
     * Kursning kichik guruh qoidasini qaytaradi: [subCount, subMax, subTol].
     * Qoida: 1-3 kurs -> a,b (2 ta, tibbiy-biologik); 4-6 kurs -> a,b,c (3 ta, klinik).
     * variant qo'lda tanlansa (full/ab/abc) o'sha ustun bo'ladi.
     */
    private function oqimSubRule(string $variant, int $levelNum, array $params): array
    {
        $eff = $variant;
        if ($eff === 'auto') {
            $eff = $levelNum >= 4 ? 'abc' : 'ab';
        }
        if ($eff === 'full') {
            // to'liq guruh — bo'linmaydi; birlashtirish sig'imi standart guruh (~2*ab_max)
            return [1, 2 * $params['ab_max'], 0, 'full'];
        }
        if ($eff === 'abc') {
            return [3, $params['abc_max'], $params['abc_tol'], 'abc'];
        }
        return [2, $params['ab_max'], $params['ab_tol'], 'ab'];
    }

    /**
     * Kurs bo'yicha asosiy guruhlar ro'yxatini tayyorlaydi (har biriga tayyor kichik
     * guruh qatorlari — 'rows' — biriktirilgan holda).
     *
     * - Kichik guruhlar soni kursga qarab QAT'IY: 1-3 kurs a,b; 4-6 kurs a,b,c
     *   (majburiy — optimizatsiya buni o'zgartirmaydi).
     * - JORIY holat: HEMISdagi haqiqiy guruhlar.
     * - OPTIMIZATSIYA: bir tildagi kam to'lgan guruhlar birlashtirilib, guruhlar soni
     *   kamaytiriladi (har bir guruh ~ sig'imgacha to'ldiriladi). Guruh soni HECH QACHON
     *   ko'paymaydi.
     */
    private function oqimCourseBases(array $bases, array $params, string $variant, int $levelNum): array
    {
        if (empty($bases)) {
            return [];
        }

        [$subCount, $subMax, $subTol, $eff] = $this->oqimSubRule($variant, $levelNum, $params);
        $capacity = max(1, $subCount * ($subMax + max(0, $subTol)));

        // Til bo'yicha ajratamiz (har xil til aralashmaydi)
        $byLang = [];
        foreach ($bases as $b) {
            $byLang[$b['lang']][] = $b;
        }

        $out = [];
        foreach ($byLang as $list) {
            usort($list, fn($a, $b) => $this->oqimNatCmp($a['base'], $b['base']));
            $langLabel = $list[0]['lang_label'];
            $suffix = $langLabel !== '' ? ' (' . $langLabel . ')' : '';

            if (!empty($params['optimize'])) {
                // OPTIMIZATSIYA — kichik guruh (a/b/c) darajasida zich qadoqlash:
                // minimal kichik guruhlar soni = ceil(T / kichik_guruh_sig'imi); talabalar
                // shu kichik guruhlarga teng taqsimlanadi (oxirgi ortiqcha guruhchalar
                // yuqoridagilarga singdirilib, o'chiriladi). Keyin har subCount tadan bitta
                // asosiy guruhga (a,b yoki a,b,c) yig'iladi — oxirgi guruh kamroq bo'lishi mumkin.
                $T = array_sum(array_map(fn($x) => $x['total'], $list));
                // Guruh soni original guruhlar sonidan OSHMASIN (optimizatsiya guruh qo'shmaydi).
                $chunks = $this->oqimOptimalSubgroups($T, $subCount, $subMax, $subTol, count($list));
                $names = array_map(fn($x) => $x['base'], $list);
                // Ehtiyot chorasi: agar (kamdan-kam) guruh soni nomlardan oshsa — "d2/d25-01-11"
                // kabi chalkash nom o'rniga ketma-ket toza nom yaratamiz (masalan d2/d25-11).
                [$genPrefix, $genWidth, $genNum] = $this->oqimBaseNameSeed($names);
                $letters = ['a', 'b', 'c', 'd', 'e', 'f'];
                foreach ($chunks as $i => $chunk) {
                    if (isset($names[$i])) {
                        $bname = $names[$i];
                    } elseif ($genPrefix !== null) {
                        $bname = $genPrefix . str_pad((string) (++$genNum), $genWidth, '0', STR_PAD_LEFT);
                    } else {
                        $bname = $list[0]['base'] . '-' . ($i + 1);
                    }
                    $rows = [];
                    foreach (array_values($chunk) as $j => $size) {
                        $lbl = ($subCount <= 1) ? $bname : ($bname . ($letters[$j] ?? ($j + 1)));
                        $rows[] = ['name' => $lbl . $suffix, 'count' => $size];
                    }
                    $out[] = [
                        'base'       => $bname,
                        'lang'       => $list[0]['lang'],
                        'lang_label' => $langLabel,
                        'total'      => array_sum($chunk),
                        'rows'       => $rows,
                    ];
                }
            } else {
                foreach ($list as $b) {
                    $out[] = [
                        'base'       => $b['base'],
                        'lang'       => $b['lang'],
                        'lang_label' => $langLabel,
                        'total'      => $b['total'],
                        'rows'       => $this->oqimJoriyRows($b, $subCount, $eff, $suffix),
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * JORIY holat uchun kichik guruh qatorlari: HEMISdagi HAQIQIY guruhlarni
     * aynan o'zidek ko'rsatadi (real nom va real son) — hech qachon qayta bo'lmaydi.
     * Shu sabab bir kodли, lekin har xil tildagi guruhlar (masalan d2/21-01a (rus)
     * va d2/21-01с (o'z)) o'z nomi bilan alohida chiqadi, soxta nom to'qnashuvi bo'lmaydi.
     */
    private function oqimJoriyRows(array $b, int $subCount, string $eff, string $suffix): array
    {
        if ($eff === 'full') {
            return [['name' => $b['base'] . $suffix, 'count' => $b['total']]];
        }

        $members = $b['members'] ?? [];
        usort($members, fn($x, $y) => strcmp((string) ($x['name'] ?? $x['letter']), (string) ($y['name'] ?? $y['letter'])));

        $out = [];
        foreach ($members as $m) {
            // HEMISdagi haqiqiy nomni ishlatamiz; bo'lmasa base + harf
            $name = $m['name'] ?? ($b['base'] . $m['letter']);
            $out[] = ['name' => $name . $suffix, 'count' => (int) $m['count']];
        }
        return $out;
    }

    /**
     * Talabani QAT'IY sondagi ($n) kichik guruhga imkon qadar teng bo'ladi.
     */
    private function oqimFixedSubgroups(string $base, int $total, int $n, string $suffix): array
    {
        $parts = $this->oqimDistribute($total, $n);
        $letters = ['a', 'b', 'c', 'd', 'e', 'f'];
        $out = [];
        foreach ($parts as $i => $c) {
            $out[] = ['name' => $base . ($letters[$i] ?? ($i + 1)) . $suffix, 'count' => $c];
        }
        return $out;
    }

    /**
     * OPTIMIZATSIYA uchun: talabalarni MINIMAL sondagi kichik guruhlarga (guruhcha) zich
     * joylaydi — bu ASOSIY prioritet. minSub = ceil(T / guruhcha sig'imi). So'ng har
     * subCount tadan to'liq asosiy guruhga (a,b yoki a,b,c) yig'iladi: to'liqlaridan
     * boshlab, faqat OXIRGI asosiy guruh chala (a yoki a,b) bo'lishi mumkin. Yolg'iz
     * qolgan chala guruhchalarni to'liq qilish uchun fakultetlararo birlashtirish alohida
     * qadam sifatida qo'llanadi (applyCrossFacultyOqimMerge).
     * Qaytaradi: har bir element — bitta asosiy guruhning kichik guruh sonlari.
     */
    private function oqimOptimalSubgroups(int $total, int $subCount, int $subMax, int $subTol, int $maxBases = 0): array
    {
        $subCount = max(1, $subCount);
        $subCap   = max(1, $subMax + max(0, $subTol));

        // PRIORITET 1: guruhchalar soni minimal bo'lsin.
        $minSub = max(1, (int) ceil($total / $subCap));
        // Asosiy guruhlar soni original guruhlar sonidan OSHMASIN (optimizatsiya guruh
        // QO'SHMAYDI). Bu holda oxirgi guruhchalar me'yordan sal ko'proq to'ladi.
        if ($maxBases > 0) {
            $minSub = min($minSub, $maxBases * $subCount);
        }
        $subSizes = $this->oqimDistribute($total, $minSub);
        // PRIORITET 2: to'liq guruhlardan boshlab yig'amiz (oxirgisi chala bo'lishi mumkin).
        return array_chunk($subSizes, $subCount);
    }

    /**
     * Optimizatsiya uchun overflow guruh nomlarini davom ettirish uchun urug': nomlar
     * ichidan eng katta raqamli nomni topib, uning prefiksi, raqam kengligi va raqamini
     * qaytaradi. "d2/d25-10" -> ["d2/d25-", 2, 10]. Raqam topilmasa [null, 0, 0].
     */
    private function oqimBaseNameSeed(array $names): array
    {
        $prefix = null; $width = 2; $max = 0; $seen = false;
        foreach ($names as $nm) {
            if (preg_match('/^(.*?)(\d+)$/u', (string) $nm, $mm)) {
                $num = (int) $mm[2];
                if (!$seen || $num > $max) {
                    $prefix = $mm[1];
                    $width  = strlen($mm[2]);
                    $max    = $num;
                    $seen   = true;
                }
            }
        }
        return $seen ? [$prefix, $width, $max] : [null, 0, 0];
    }

    /**
     * Sonni $n bo'lakka imkon qadar teng bo'ladi (0 bo'lganlari tashlanadi).
     */
    private function oqimDistribute(int $total, int $n): array
    {
        $n = max(1, $n);
        $q = intdiv($total, $n);
        $rem = $total % $n;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $q + ($i < $rem ? 1 : 0);
            if ($c > 0) {
                $out[] = $c;
            }
        }
        return $out ?: [0];
    }

    /**
     * Asosiy guruh nomini (masalan "d2/d25-01") va kichik guruh harfini (a/b/c) ajratadi.
     * Kichik guruh harfi ba'zan xato bilan kirill ko'rinishida yozilgan bo'lishi mumkin
     * (masalan lotin "c" o'rniga kirill "с") — ularni ham tanib, lotinga keltiramiz,
     * aks holda bitta guruh ikki marta bo'linib, guruhlar soni sun'iy ko'payadi.
     */
    private function oqimSplitBase(string $nameNoLang): array
    {
        // Lotin a-e va ularning kirill ko'rinishdoshlari (а, е, с, о, р, х)
        if (preg_match('/^(.*?\d)\s*\(?([a-eA-EаАеЕсСоОрРхХ])\)?$/u', $nameNoLang, $m)) {
            return [rtrim($m[1]), $this->oqimNormalizeLetter($m[2])];
        }
        return [$nameNoLang, ''];
    }

    /**
     * Kichik guruh harfini standart lotin harfiga keltiradi (kirill ko'rinishdoshlarni ham).
     */
    private function oqimNormalizeLetter(string $ch): string
    {
        $map = [
            'а' => 'a', 'А' => 'a', // kirill a
            'е' => 'e', 'Е' => 'e', // kirill e
            'с' => 'c', 'С' => 'c', // kirill s (lotin c ko'rinishi)
            'о' => 'o', 'О' => 'o',
            'р' => 'p', 'Р' => 'p',
            'х' => 'x', 'Х' => 'x',
        ];
        return mb_strtolower($map[$ch] ?? $ch);
    }

    /**
     * Kurs nomidan yoki kodidan kurs raqamini ajratadi ("2-kurs" -> 2).
     */
    private function oqimLevelNumber(?string $levelName, string $levelCode): int
    {
        if ($levelName && preg_match('/(\d+)/', $levelName, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/(\d+)/', $levelCode, $m)) {
            // level_code ba'zan 11,12.. ko'rinishida — oxirgi raqamni kurs deb olamiz
            $n = (int) $m[1];
            return $n > 6 ? ($n % 10) : $n;
        }
        return 0;
    }

    /**
     * Guruh nomidan yoki ta'lim tili nomidan til kalitini aniqlaydi.
     */
    private function oqimLangKey(?string $langName, string $groupName): string
    {
        $s = mb_strtolower(trim(($langName ?? '') . ' ' . $groupName));
        if (str_contains($s, 'рус') || str_contains($s, 'rus') || str_contains($s, 'russ') || str_contains($s, '(rus')) {
            return 'rus';
        }
        if (str_contains($s, 'ingl') || str_contains($s, 'engl') || str_contains($s, 'англ')
            || str_contains($s, '(ing') || str_contains($s, '(ang') || str_contains($s, 'angl')) {
            return 'ing';
        }
        return 'uz';
    }

    /**
     * Guruh nomidagi oxirgi til qavsini ("(rus)", "(ang)", "(o'z)"...) olib tashlaydi.
     */
    private function oqimStripLang(string $name): string
    {
        $clean = preg_replace(
            "/\\s*\\((?:rus|ru|рус|russ|ing|eng|engl|ang|angl|англ|o['’‘]?z|oz|uz|ўз|узб)[^)]*\\)\\s*$/ui",
            '',
            $name
        );
        return trim($clean === null ? $name : $clean);
    }

    /**
     * Guruh yonida ko'rsatiladigan qisqa til yorlig'i: o'z / rus / ing.
     */
    private function oqimLangLabel(?string $langName, string $groupName): string
    {
        return match ($this->oqimLangKey($langName, $groupName)) {
            'rus' => 'rus',
            'ing' => 'ing',
            default => "o'z",
        };
    }

    /**
     * Fakultet + yo'nalish sarlavhasini shakllantiradi.
     */
    private function oqimBlockTitle(?string $department, ?string $specialty): string
    {
        $department = trim((string) $department);
        $specialty = trim((string) $specialty);
        if ($specialty === '') {
            return $department;
        }
        $spec = $specialty;
        if (!preg_match("/yo'nalish/ui", $spec) && !preg_match('/yўnalish/ui', $spec)) {
            $spec .= " yo'nalishi";
        }
        return $department === '' ? $spec : ($department . ': ' . $spec);
    }

    /**
     * Fakultet nomidan "N-son" prefiksini olib tashlaydi (birlashtirish uchun).
     * "1-son davolash fakulteti" -> "Davolash fakulteti". Prefiks bo'lmasa — o'zgarmaydi.
     */
    private function oqimMergeDeptName(?string $name): string
    {
        $name = trim((string) $name);
        $stripped = preg_replace('/^\s*\d+\s*-?\s*son\s+/ui', '', $name);
        $stripped = trim($stripped === null ? $name : $stripped);
        if ($stripped === '') {
            return $name;
        }
        return mb_strtoupper(mb_substr($stripped, 0, 1)) . mb_substr($stripped, 1);
    }

    /**
     * Tabiiy (natural) taqqoslash — "d1/d26-2" < "d1/d26-10".
     */
    private function oqimNatCmp(string $a, string $b): int
    {
        return strnatcasecmp($a, $b);
    }

    /**
     * Bitta variant bloklarini Excel varaqasiga yozadi (yuklangan namunadagi ko'rinishda).
     * Har bir kurs 3 ustundan iborat: oqim | guruh | talaba soni. Kurslar yonma-yon.
     */
    private function fillOqimSheet($sheet, array $blocks, array $header): void
    {
        $B = \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN;
        $center = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
        $vcenter = \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER;

        $maxCols = 18; // 6 kurs * 3 ustun
        $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCols);

        // Sarlavha
        $sheet->setCellValue('A1', $header[0] ?? '');
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal($center)->setVertical($vcenter)->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(40);

        $sheet->setCellValue('A2', $header[1] ?? '');
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal($center)->setVertical($vcenter)->setWrapText(true);

        $row = 4;
        foreach ($blocks as $block) {
            // Blok sarlavhasi (fakultet + yo'nalish)
            $sheet->setCellValue([1, $row], $block['title']);
            $sheet->mergeCells([1, $row, $maxCols, $row]);
            $sheet->getStyle([1, $row])->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle([1, $row])->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E8EDF5');
            $sheet->getStyle([1, $row])->getAlignment()->setVertical($vcenter);
            $row++;

            $courses = $block['courses'];
            $headerRow = $row;      // kurs nomlari
            $dataStartRow = $row + 1;

            // Har bir kurs uchun ustun bloklari
            $colBase = 1;
            $blockBottomRow = $dataStartRow; // eng uzun kurs qayerda tugashini kuzatamiz
            foreach ($courses as $course) {
                // Kurs sarlavhasi (3 ustunni birlashtiramiz)
                $sheet->setCellValue([$colBase, $headerRow], $course['level_name']);
                $sheet->mergeCells([$colBase, $headerRow, $colBase + 2, $headerRow]);
                $sheet->getStyle([$colBase, $headerRow])->getFont()->setBold(true);
                $sheet->getStyle([$colBase, $headerRow])->getAlignment()->setHorizontal($center)->setVertical($vcenter);
                $sheet->getStyle([$colBase, $headerRow])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('DBE4EF');

                $cur = $dataStartRow;
                foreach ($course['oqims'] as $oqim) {
                    $oqimTop = $cur;
                    foreach ($oqim['rows'] as $gr) {
                        $sheet->setCellValue([$colBase + 1, $cur], $gr['name']);
                        $sheet->setCellValue([$colBase + 2, $cur], $gr['count']);
                        $sheet->getStyle([$colBase + 2, $cur])->getAlignment()->setHorizontal($center);
                        $cur++;
                    }
                    $oqimBottom = $cur - 1;
                    if ($oqimBottom >= $oqimTop) {
                        // Oqim yorlig'ini birinchi ustunga, guruhlar sonicha birlashtirib yozamiz.
                        // Yorliq ostida oqimdagi jami talaba soni ko'rsatiladi.
                        $label = $oqim['label'] . "\n(" . ($oqim['total'] ?? 0) . " ta)";
                        $sheet->setCellValue([$colBase, $oqimTop], $label);
                        if ($oqimBottom > $oqimTop) {
                            $sheet->mergeCells([$colBase, $oqimTop, $colBase, $oqimBottom]);
                        }
                        $sheet->getStyle([$colBase, $oqimTop])->getAlignment()
                            ->setHorizontal($center)->setVertical($vcenter)->setWrapText(true);
                        $sheet->getStyle([$colBase, $oqimTop])->getFont()->setBold(true);
                    }
                }

                // Jami (kurs bo'yicha talaba soni)
                $sheet->setCellValue([$colBase, $cur], 'Jami');
                $sheet->mergeCells([$colBase, $cur, $colBase + 1, $cur]);
                $sheet->setCellValue([$colBase + 2, $cur], $course['total']);
                $sheet->getStyle([$colBase, $cur, $colBase + 2, $cur])->getFont()->setBold(true);
                $sheet->getStyle([$colBase, $cur])->getAlignment()->setHorizontal($center);
                $sheet->getStyle([$colBase + 2, $cur])->getAlignment()->setHorizontal($center);
                $sheet->getStyle([$colBase, $cur, $colBase + 2, $cur])->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F1F5F9');

                $courseBottom = $cur;
                if ($courseBottom > $blockBottomRow) {
                    $blockBottomRow = $courseBottom;
                }
                $colBase += 3;
            }

            // Ramka: kurs sarlavhasidan blok oxirigacha
            $usedCols = max(3, ($colBase - 1));
            $lastCL = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($usedCols);
            $sheet->getStyle("A{$headerRow}:{$lastCL}{$blockBottomRow}")
                ->getBorders()->getAllBorders()->setBorderStyle($B);

            $row = $blockBottomRow + 2; // bloklar orasida bo'sh qator
        }

        // Ustun kengliklari: har kurs uchun oqim(6) guruh(16) son(8)
        for ($c = 1; $c <= $maxCols; $c += 3) {
            $sheet->getColumnDimensionByColumn($c)->setWidth(9);
            $sheet->getColumnDimensionByColumn($c + 1)->setWidth(16);
            $sheet->getColumnDimensionByColumn($c + 2)->setWidth(7);
        }
    }
}

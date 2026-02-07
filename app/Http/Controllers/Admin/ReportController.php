<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
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
     */
    public function jnReportData(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // 1-QADAM: schedules dan to'g'ri kombinatsiyalarni olish
        //   Joriy semestr filtri: schedules.semester_code shu GURUHNING
        //   curriculum'i uchun joriy bo'lishi kerak (har qanday curriculum emas)
        $scheduleQuery = DB::table('schedules as sch')
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->select(
                'sch.group_id',
                'sch.subject_id',
                'sch.semester_code',
                DB::raw('MIN(sch.lesson_date) as min_date')
            )
            ->groupBy('sch.group_id', 'sch.subject_id', 'sch.semester_code');

        // Joriy semestr filtri — guruhning curriculum'iga mos joriy semestr
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
            $scheduleQuery->where('semester_code', $request->semester_code);
        }

        if ($request->filled('subject')) {
            $scheduleQuery->where('subject_id', $request->subject);
        }

        $validSchedules = $scheduleQuery->get();

        if ($validSchedules->isEmpty()) {
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

        // Kafedra filtri — faqat shu kafedraga tegishli fanlarni olish
        $allowedSubjectIds = null;
        if ($request->filled('department')) {
            $allowedSubjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
        }

        // 3-QADAM: schedules + students birlashtirish va student_grades dan baholar olish
        // schedules bo'yicha iteratsiya — har bir valid schedule uchun baholarni to'plash
        $scheduleMap = [];
        foreach ($validSchedules as $sch) {
            $key = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code;
            $scheduleMap[$key] = $sch->min_date;
        }

        // group_id lar ro'yxati
        $scheduleGroupIds = $validSchedules->pluck('group_id')->unique()->toArray();

        // Faqat valid schedule gruppalardagi talabalarni olish
        $studentQuery->whereIn('s.group_id', $scheduleGroupIds);
        $students = $studentQuery->get();

        if ($students->isEmpty()) {
            return response()->json(['data' => [], 'total' => 0]);
        }

        // student_hemis_id -> group_id map
        $studentGroupMap = [];
        foreach ($students as $st) {
            $studentGroupMap[$st->hemis_id] = $st->group_id;
        }
        $studentHemisIds = array_keys($studentGroupMap);

        // Valid (subject_id, semester_code) juftliklari
        $validSubjectSemesters = $validSchedules->map(fn($s) => $s->subject_id . '|' . $s->semester_code)->unique()->toArray();
        $validSubjectIds = $validSchedules->pluck('subject_id')->unique()->toArray();
        $validSemesterCodes = $validSchedules->pluck('semester_code')->unique()->toArray();

        if ($allowedSubjectIds !== null) {
            $validSubjectIds = array_intersect($validSubjectIds, $allowedSubjectIds);
            if (empty($validSubjectIds)) {
                return response()->json(['data' => [], 'total' => 0]);
            }
        }

        // 4-QADAM: student_grades dan baholarni olish (oddiy WHERE IN - TEZ)
        $gradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('grade')
            ->where('grade', '>', 0)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'grade', 'lesson_date')
            ->get();

        // 5-QADAM: PHP da hisoblash (scheduleMap bilan filtrlash + aggregatsiya)
        $results = [];
        foreach ($gradesRaw as $g) {
            $groupId = $studentGroupMap[$g->student_hemis_id] ?? null;
            if (!$groupId) continue;

            $key = $groupId . '|' . $g->subject_id . '|' . $g->semester_code;
            $minDate = $scheduleMap[$key] ?? null;
            if (!$minDate) continue;

            // lesson_date >= minScheduleDate tekshiruvi
            if ($g->lesson_date < $minDate) continue;

            $resultKey = $g->student_hemis_id . '|' . $g->subject_id;
            if (!isset($results[$resultKey])) {
                $results[$resultKey] = [
                    'student_hemis_id' => $g->student_hemis_id,
                    'subject_id' => $g->subject_id,
                    'subject_name' => $g->subject_name,
                    'grade_sum' => 0,
                    'grades_count' => 0,
                ];
            }
            $results[$resultKey]['grade_sum'] += $g->grade;
            $results[$resultKey]['grades_count']++;
        }

        // O'rtachalarni hisoblash
        foreach ($results as &$r) {
            $r['avg_grade'] = round($r['grade_sum'] / $r['grades_count'], 2);
            unset($r['grade_sum']);
        }
        unset($r);

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
}

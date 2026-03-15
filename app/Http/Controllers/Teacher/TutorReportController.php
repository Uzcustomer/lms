<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\ContractList;
use App\Models\CurriculumSubject;
use App\Models\Setting;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorReportController extends Controller
{
    /**
     * Tyutor guruhlarining hemis_id larini olish
     */
    private function getTutorGroupIds()
    {
        $teacher = auth()->guard('teacher')->user();
        return $teacher->groups()->where('active', true)->pluck('group_hemis_id')->toArray();
    }

    /**
     * Tyutor guruhlarini olish (filtr uchun)
     */
    private function getTutorGroups()
    {
        $teacher = auth()->guard('teacher')->user();
        return $teacher->groups()->where('active', true)->orderBy('name')->get();
    }

    /**
     * Guruh filtri qo'llangan group_ids
     */
    private function getFilteredGroupIds(Request $request)
    {
        $groupIds = $this->getTutorGroupIds();
        if (empty($groupIds)) {
            abort(403, 'Sizga biriktirilgan guruhlar yo\'q.');
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
     * JN o'zlashtirish hisoboti (admin mantiqi bilan)
     */
    public function jnReport(Request $request)
    {
        $tutorGroups = $this->getTutorGroups();
        $groupIds = $this->getFilteredGroupIds($request);

        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Schedule dan barcha fanlarni olish (joriy semestr)
        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->select('sch.group_id', 'sch.subject_id', 'sch.subject_name', 'sch.semester_code')
            ->groupBy('sch.group_id', 'sch.subject_id', 'sch.subject_name', 'sch.semester_code')
            ->get();

        if ($scheduleQuery->isEmpty()) {
            return view('teacher.reports.jn', compact('tutorGroups'))->with('results', []);
        }

        // Baholarni olish (admin mantiqi: status, reason, retake_grade ham)
        $students = Student::whereIn('group_id', $groupIds)->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        $validSubjectIds = $scheduleQuery->pluck('subject_id')->unique()->toArray();
        $validSemesterCodes = $scheduleQuery->pluck('semester_code')->unique()->toArray();

        $grades = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereIn('subject_id', $validSubjectIds)
            ->whereIn('semester_code', $validSemesterCodes)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'subject_id', 'grade', 'retake_grade', 'status', 'reason',
                'lesson_date', 'lesson_pair_code')
            ->get();

        // Admin mantiqi: kunlik o'rtachani pair_code bo'yicha hisoblash
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

        // Natijalarni yig'ish
        $results = [];
        foreach ($scheduleQuery as $combo) {
            $groupName = $groupNameMap[$combo->group_id] ?? $combo->group_id;
            $groupStudents = $students->where('group_id', $combo->group_id);

            foreach ($groupStudents as $student) {
                $key = $student->hemis_id . '|' . $combo->subject_id;
                $dailyGrades = $gradeMap[$key] ?? [];

                if (empty($dailyGrades)) {
                    $avg = null;
                } else {
                    $dailyAvgs = [];
                    foreach ($dailyGrades as $dayGrades) {
                        $dailyAvgs[] = round(array_sum($dayGrades) / count($dayGrades), 0, PHP_ROUND_HALF_UP);
                    }
                    $avg = round(array_sum($dailyAvgs) / count($dailyAvgs), 0, PHP_ROUND_HALF_UP);
                }

                $results[] = [
                    'group_name' => $groupName,
                    'subject_name' => $combo->subject_name,
                    'student_name' => $student->full_name,
                    'student_id' => $student->student_id_number,
                    'avg_grade' => $avg,
                    'grade_count' => count($dailyGrades),
                ];
            }
        }

        // Filtr
        if ($request->filled('filter') && $request->filter === 'low') {
            $results = array_filter($results, fn($r) => $r['avg_grade'] !== null && $r['avg_grade'] < 60);
        } elseif ($request->filled('filter') && $request->filter === 'no_grade') {
            $results = array_filter($results, fn($r) => $r['avg_grade'] === null);
        }

        usort($results, function ($a, $b) {
            $cmp = strcmp($a['group_name'], $b['group_name']);
            if ($cmp !== 0) return $cmp;
            $cmp = strcmp($a['subject_name'], $b['subject_name']);
            if ($cmp !== 0) return $cmp;
            return strcmp($a['student_name'], $b['student_name']);
        });

        $results = array_values($results);

        return view('teacher.reports.jn', compact('tutorGroups', 'results'));
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
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Schedule kombinatsiyalarini olish (joriy semestr)
        $scheduleCombos = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->whereNull('sch.deleted_at')
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
                ->whereNotIn('training_type_code', $excludedCodes)
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
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);
        $scoreLimit = (int) $request->get('score_limit', 90);

        // Fanlar ro'yxatini olish
        $scheduleSubjects = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
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
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('lesson_date')
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
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Schedule yozuvlarini olish (o'tgan kunlar uchun)
        $schedules = DB::table('schedules as sch')
            ->join('groups as gr', 'gr.group_hemis_id', '=', 'sch.group_id')
            ->join('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'gr.curriculum_hemis_id');
            })
            ->whereIn('sch.group_id', $groupIds)
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at')
            ->where('sem.current', true)
            ->where('sch.education_year_current', true)
            ->whereRaw('DATE(sch.lesson_date) < CURDATE()')
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
            ->whereNotIn('training_type_code', $excludedCodes)
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

<?php

namespace App\Console\Commands;

use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherDashboardSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BuildTeacherDashboardSnapshots extends Command
{
    protected $signature = 'teachers:build-dashboard-snapshots';
    protected $description = 'O\'qituvchilar dashboardi uchun kunlik snapshot (grading time stats + tutor stats)';

    public function handle(): int
    {
        $startedAt = microtime(true);
        $this->info('Teacher dashboard snapshots hisoblanmoqda...');

        $currentSemesters = Semester::where('current', true)
            ->whereNotNull('code')
            ->get(['semester_hemis_id', 'code']);

        if ($currentSemesters->isEmpty()) {
            $this->warn('Joriy semestr topilmadi. Snapshot yaratilmadi.');
            return self::SUCCESS;
        }

        $semesterCodes = $currentSemesters->pluck('code')->unique()->values()->toArray();
        $semesterHemisIds = $currentSemesters->pluck('semester_hemis_id')->unique()->values()->toArray();

        $dateRange = DB::table('curriculum_weeks')
            ->whereIn('semester_hemis_id', $semesterHemisIds)
            ->selectRaw('MIN(start_date) as min_date, MAX(end_date) as max_date')
            ->first();

        $dateFrom = $dateRange->min_date ?? null;
        $dateTo   = $dateRange->max_date ?? null;

        $gradingData = $this->buildGradingTimeData($semesterCodes, $dateFrom, $dateTo);
        $this->info('Grading time stats hisoblandi: ' . count($gradingData['per_teacher']) . ' ta o\'qituvchi.');

        $tutorData = $this->buildTutorStats();
        $this->info('Tutor stats hisoblandi: ' . count($tutorData) . ' ta tyutor.');

        $workloadData = $this->buildWorkloadAndLoadErrors($semesterCodes, $dateFrom, $dateTo);
        $this->info('Yuklama va load xatoliklari hisoblandi: ' . count($workloadData) . ' ta o\'qituvchi.');

        $studentsData = $this->buildSubjectTopStudents($semesterCodes, $dateFrom, $dateTo);
        $this->info('Eng yaxshi/eng yomon talabalar ro\'yxati hisoblandi: ' . count($studentsData) . ' ta o\'qituvchi.');

        $now = now();
        $this->persistSnapshots($gradingData, $tutorData, $workloadData, $studentsData, $now);

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Snapshot tayyor. Vaqt: {$elapsed}s");
        Log::info('teachers:build-dashboard-snapshots yakunlandi', [
            'teachers' => count($gradingData['per_teacher']),
            'tutors' => count($tutorData),
            'workload' => count($workloadData),
            'students' => count($studentsData),
            'elapsed_seconds' => $elapsed,
        ]);

        return self::SUCCESS;
    }

    private function buildGradingTimeData(array $semesterCodes, ?string $dateFrom, ?string $dateTo): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $pairEndExpr  = "(DATE(lesson_date)::text || ' ' || lesson_pair_end_time)::timestamp";
            $dayLimitExpr = "(DATE(lesson_date)::text || ' 18:00:00')::timestamp";
        } else {
            $pairEndExpr  = "CONCAT(DATE(lesson_date), ' ', lesson_pair_end_time)";
            $dayLimitExpr = "CONCAT(DATE(lesson_date), ' 18:00:00')";
        }

        // Baho qo'yilgan vaqt: HEMIS import qilgan bo'lsa created_at_api, aks holda created_at.
        // LMS'da qo'lda qo'yilgan baholarda created_at_api null bo'lishi mumkin.
        $gradeTsExpr = "COALESCE(created_at_api, created_at)";

        $caseDuringClass = "SUM(CASE WHEN {$gradeTsExpr} <= {$pairEndExpr} THEN 1 ELSE 0 END)";
        $caseWorkHours   = "SUM(CASE WHEN {$gradeTsExpr} > {$pairEndExpr} AND {$gradeTsExpr} <= {$dayLimitExpr} THEN 1 ELSE 0 END)";
        $caseAfterHours  = "SUM(CASE WHEN {$gradeTsExpr} > {$dayLimitExpr} THEN 1 ELSE 0 END)";

        // Joriy baholar: config'dagi excluded kodlardan tashqari hamma training turlar (OSKI, test,
        // ON, mustaqil — excluded, JN — joriy). status='recorded' asl yozuvlar (retake/closed emas).
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $query = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('semester_code', $semesterCodes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->where('status', 'recorded')
            ->whereNotNull('lesson_date')
            ->whereNotNull('lesson_pair_end_time')
            ->where('lesson_pair_end_time', '!=', '');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('lesson_date', [$dateFrom, $dateTo]);
        }

        $rows = $query->selectRaw("
                employee_id,
                {$caseDuringClass} as during_class,
                {$caseWorkHours}   as work_hours,
                {$caseAfterHours}  as after_hours,
                COUNT(*)           as total
            ")
            ->groupBy('employee_id')
            ->get();

        $ranked = $rows->map(function ($row) {
                $row->during_class = (int) $row->during_class;
                $row->work_hours   = (int) $row->work_hours;
                $row->after_hours  = (int) $row->after_hours;
                $row->total        = (int) $row->total;
                $row->score = $row->during_class * 1.0 + $row->work_hours * 0.5;
                return $row;
            })
            ->sortByDesc(function ($row) {
                return [$row->score, $row->during_class, $row->total];
            })
            ->values();

        $totalTeachers = $ranked->count();

        $top100 = $ranked->take(100);
        $topEmployeeIds = $top100->pluck('employee_id')->filter()->unique()->toArray();

        $teacherInfoMap = collect();
        if (!empty($topEmployeeIds)) {
            $teacherInfoMap = DB::table('teachers')
                ->whereIn('hemis_id', $topEmployeeIds)
                ->select('hemis_id', 'full_name', 'short_name', 'department')
                ->get()
                ->keyBy('hemis_id');
        }

        $topList = [];
        foreach ($top100 as $idx => $row) {
            $info = $teacherInfoMap[$row->employee_id] ?? null;
            $topList[] = [
                'rank'         => $idx + 1,
                'hemis_id'     => $row->employee_id,
                'full_name'    => $info->full_name ?? $info->short_name ?? '—',
                'department'   => $info->department ?? '—',
                'during_class' => $row->during_class,
                'work_hours'   => $row->work_hours,
                'after_hours'  => $row->after_hours,
                'total'        => $row->total,
                'score'        => round((float) $row->score, 1),
                'during_class_percent' => $row->total > 0 ? round($row->during_class / $row->total * 100, 1) : 0,
                'work_hours_percent'   => $row->total > 0 ? round($row->work_hours   / $row->total * 100, 1) : 0,
                'after_hours_percent'  => $row->total > 0 ? round($row->after_hours  / $row->total * 100, 1) : 0,
            ];
        }

        $perTeacher = [];
        foreach ($ranked as $idx => $row) {
            if (!$row->employee_id) continue;
            $total = $row->total;
            $perTeacher[(string) $row->employee_id] = [
                'total'          => $total,
                'during_class'   => $row->during_class,
                'work_hours'     => $row->work_hours,
                'after_hours'    => $row->after_hours,
                'during_class_percent' => $total > 0 ? round($row->during_class / $total * 100, 1) : 0,
                'work_hours_percent'   => $total > 0 ? round($row->work_hours   / $total * 100, 1) : 0,
                'after_hours_percent'  => $total > 0 ? round($row->after_hours  / $total * 100, 1) : 0,
                'rank'           => $idx + 1,
            ];
        }

        return [
            'global' => [
                'total_teachers' => $totalTeachers,
                'top_list' => $topList,
            ],
            'per_teacher' => $perTeacher,
        ];
    }

    private function buildTutorStats(): array
    {
        $tutorGroups = DB::table('group_teacher')
            ->join('groups', 'group_teacher.group_id', '=', 'groups.id')
            ->where('groups.active', true)
            ->select(
                'group_teacher.teacher_id',
                'groups.group_hemis_id',
                'groups.name as group_name'
            )
            ->orderBy('groups.name')
            ->get()
            ->groupBy('teacher_id');

        if ($tutorGroups->isEmpty()) {
            return [];
        }

        $allGroupHemisIds = $tutorGroups->flatten(1)->pluck('group_hemis_id')->unique()->values()->toArray();
        $students = Student::whereIn('group_id', $allGroupHemisIds)
            ->select('hemis_id', 'group_id', 'gender_code', 'province_name')
            ->get()
            ->groupBy('group_id');

        $teacherHemisMap = DB::table('teachers')
            ->whereIn('id', $tutorGroups->keys()->toArray())
            ->pluck('hemis_id', 'id');

        $result = [];
        foreach ($tutorGroups as $teacherId => $groups) {
            $hemisId = $teacherHemisMap[$teacherId] ?? null;
            if (!$hemisId) continue;

            $groupStats = [];
            $allTutorStudents = collect();
            foreach ($groups as $g) {
                $groupStudents = $students[$g->group_hemis_id] ?? collect();
                $allTutorStudents = $allTutorStudents->merge($groupStudents);
                $groupStats[] = [
                    'name'   => $g->group_name,
                    'total'  => $groupStudents->count(),
                    'male'   => $groupStudents->where('gender_code', '11')->count(),
                    'female' => $groupStudents->where('gender_code', '12')->count(),
                ];
            }

            $provinceStats = $allTutorStudents->groupBy('province_name')
                ->map(fn($g, $k) => ['name' => $k ?: 'Noma\'lum', 'count' => $g->count()])
                ->sortByDesc('count')
                ->values()
                ->toArray();

            $result[(string) $hemisId] = [
                'totalGroups'   => $groups->count(),
                'totalStudents' => $allTutorStudents->count(),
                'maleCount'     => $allTutorStudents->where('gender_code', '11')->count(),
                'femaleCount'   => $allTutorStudents->where('gender_code', '12')->count(),
                'groupStats'    => $groupStats,
                'provinceStats' => $provinceStats,
            ];
        }

        return $result;
    }

    /**
     * Attendance_controls asosida har o'qituvchining joriy semestrdagi:
     *  - umumiy akademik soat yuklamasi (SUM(load))
     *  - "load" va juftlik davomidan hisoblangan soat orasidagi nomuvofiqlik (xatolik)
     * ro'yxatini tayyorlaydi.
     *
     * Juftlik akademik soati: max(1, round(minutes / 40))  — 40 daq = 1, 80 daq = 2.
     */
    private function buildWorkloadAndLoadErrors(array $semesterCodes, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('attendance_controls')
            ->whereIn('semester_code', $semesterCodes)
            ->whereNull('deleted_at');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('lesson_date', [$dateFrom, $dateTo]);
        }

        $rows = $query->select(
                'employee_id', 'subject_id', 'subject_name',
                'group_id', 'group_name',
                'lesson_date', 'lesson_pair_name',
                'lesson_pair_start_time', 'lesson_pair_end_time',
                'load'
            )
            ->orderBy('employee_id')
            ->orderBy('lesson_date')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $hemisId = (string) $row->employee_id;
            if ($hemisId === '') continue;

            $pairHours = $this->computePairHours($row->lesson_pair_start_time, $row->lesson_pair_end_time);
            $loadHours = (int) $row->load;

            if (!isset($result[$hemisId])) {
                $result[$hemisId] = [
                    'workload_hours'     => 0,
                    'total_checked'      => 0,
                    'errors_count'       => 0,
                    'errors'             => [],
                ];
            }

            $result[$hemisId]['workload_hours'] += $loadHours;
            $result[$hemisId]['total_checked'] += 1;

            if ($pairHours > 0 && $pairHours !== $loadHours) {
                $result[$hemisId]['errors_count'] += 1;
                $result[$hemisId]['errors'][] = [
                    'subject_name' => $row->subject_name ?? '-',
                    'group_name'   => $row->group_name ?? '-',
                    'lesson_date'  => $row->lesson_date,
                    'pair_name'    => $row->lesson_pair_name ?? '-',
                    'pair_start'   => $row->lesson_pair_start_time,
                    'pair_end'     => $row->lesson_pair_end_time,
                    'pair_hours'   => $pairHours,
                    'load_hours'   => $loadHours,
                    'diff'         => $pairHours - $loadHours,
                ];
            }
        }

        foreach ($result as &$entry) {
            $entry['error_rate_percent'] = $entry['total_checked'] > 0
                ? round($entry['errors_count'] / $entry['total_checked'] * 100, 1)
                : 0;
        }
        unset($entry);

        return $result;
    }

    private function computePairHours(?string $start, ?string $end): int
    {
        if (!$start || !$end) return 0;
        $startTs = strtotime((string) $start);
        $endTs   = strtotime((string) $end);
        if (!$startTs || !$endTs || $endTs <= $startTs) return 0;
        $minutes = ($endTs - $startTs) / 60;
        return (int) max(1, round($minutes / 40));
    }

    /**
     * Har o'qituvchining har fani bo'yicha joriy semestrdagi eng yaxshi 5
     * va eng yomon 5 talabani (o'rtacha JN bahosi bo'yicha) tayyorlaydi.
     *
     * Natija: [
     *   hemis_id => [
     *     [
     *       'subject_id' => ..., 'subject_name' => ...,
     *       'top'    => [['student_hemis_id','full_name','group_name','avg_grade','grades_count'], ...],
     *       'bottom' => [...],
     *     ],
     *   ],
     * ]
     */
    private function buildSubjectTopStudents(array $semesterCodes, ?string $dateFrom, ?string $dateTo): array
    {
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $query = DB::table('student_grades as sg')
            ->leftJoin('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->whereIn('sg.semester_code', $semesterCodes)
            ->whereNotIn('sg.training_type_code', $excludedTrainingCodes)
            ->where('sg.status', 'recorded')
            ->whereNotNull('sg.grade');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('sg.lesson_date', [$dateFrom, $dateTo]);
        }

        $rows = $query->selectRaw('
                sg.employee_id,
                sg.subject_id,
                MAX(sg.subject_name) as subject_name,
                sg.student_hemis_id,
                MAX(s.full_name)  as full_name,
                MAX(s.group_name) as group_name,
                AVG(sg.grade)     as avg_grade,
                COUNT(*)          as grades_count
            ')
            ->groupBy('sg.employee_id', 'sg.subject_id', 'sg.student_hemis_id')
            ->get();

        $bySubject = [];
        foreach ($rows as $r) {
            $hemisId = (string) $r->employee_id;
            $subjectId = (string) $r->subject_id;
            if ($hemisId === '' || $subjectId === '') continue;

            $bySubject[$hemisId][$subjectId]['subject_name'] = $r->subject_name ?: '-';
            $bySubject[$hemisId][$subjectId]['students'][] = [
                'student_hemis_id' => $r->student_hemis_id,
                'full_name'        => $r->full_name ?: '-',
                'group_name'       => $r->group_name ?: '-',
                'avg_grade'        => round((float) $r->avg_grade, 1),
                'grades_count'     => (int) $r->grades_count,
            ];
        }

        $result = [];
        foreach ($bySubject as $hemisId => $subjects) {
            $list = [];
            foreach ($subjects as $subjectId => $data) {
                $students = $data['students'];
                usort($students, fn($a, $b) => $b['avg_grade'] <=> $a['avg_grade']);
                $top = array_slice($students, 0, 5);
                $bottom = count($students) >= 10
                    ? array_reverse(array_slice($students, -5))
                    : [];
                $list[] = [
                    'subject_id'   => $subjectId,
                    'subject_name' => $data['subject_name'],
                    'total_students' => count($students),
                    'top'          => $top,
                    'bottom'       => $bottom,
                ];
            }
            usort($list, fn($a, $b) => strcmp($a['subject_name'], $b['subject_name']));
            $result[$hemisId] = $list;
        }

        return $result;
    }

    private function persistSnapshots(array $gradingData, array $tutorData, array $workloadData, array $studentsData, $generatedAt): void
    {
        DB::transaction(function () use ($gradingData, $tutorData, $workloadData, $studentsData, $generatedAt) {
            TeacherDashboardSnapshot::query()->delete();

            TeacherDashboardSnapshot::create([
                'scope' => TeacherDashboardSnapshot::SCOPE_GLOBAL,
                'teacher_hemis_id' => null,
                'payload' => $gradingData['global'],
                'generated_at' => $generatedAt,
            ]);

            $hemisIds = array_unique(array_merge(
                array_keys($gradingData['per_teacher']),
                array_keys($tutorData),
                array_keys($workloadData),
                array_keys($studentsData)
            ));

            $rows = [];
            foreach ($hemisIds as $hemisId) {
                $payload = [
                    'grading'  => $gradingData['per_teacher'][$hemisId] ?? null,
                    'tutor'    => $tutorData[$hemisId] ?? null,
                    'workload' => $workloadData[$hemisId] ?? null,
                    'subjects' => $studentsData[$hemisId] ?? null,
                ];
                $rows[] = [
                    'scope' => TeacherDashboardSnapshot::SCOPE_TEACHER,
                    'teacher_hemis_id' => (string) $hemisId,
                    'payload' => json_encode($payload),
                    'generated_at' => $generatedAt,
                    'created_at' => $generatedAt,
                    'updated_at' => $generatedAt,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('teacher_dashboard_snapshots')->insert($chunk);
            }
        });
    }
}

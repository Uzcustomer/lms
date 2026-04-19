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

        $now = now();
        $this->persistSnapshots($gradingData, $tutorData, $now);

        $elapsed = round(microtime(true) - $startedAt, 2);
        $this->info("Snapshot tayyor. Vaqt: {$elapsed}s");
        Log::info('teachers:build-dashboard-snapshots yakunlandi', [
            'teachers' => count($gradingData['per_teacher']),
            'tutors' => count($tutorData),
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

        $caseDuringClass = "SUM(CASE WHEN created_at_api <= {$pairEndExpr} THEN 1 ELSE 0 END)";
        $caseWorkHours   = "SUM(CASE WHEN created_at_api > {$pairEndExpr} AND created_at_api <= {$dayLimitExpr} THEN 1 ELSE 0 END)";
        $caseAfterHours  = "SUM(CASE WHEN created_at_api > {$dayLimitExpr} THEN 1 ELSE 0 END)";

        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $query = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('semester_code', $semesterCodes)
            ->where('training_type_code', 100)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->where('status', 'recorded')
            ->whereNotNull('created_at_api')
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

    private function persistSnapshots(array $gradingData, array $tutorData, $generatedAt): void
    {
        DB::transaction(function () use ($gradingData, $tutorData, $generatedAt) {
            TeacherDashboardSnapshot::query()->delete();

            TeacherDashboardSnapshot::create([
                'scope' => TeacherDashboardSnapshot::SCOPE_GLOBAL,
                'teacher_hemis_id' => null,
                'payload' => $gradingData['global'],
                'generated_at' => $generatedAt,
            ]);

            $hemisIds = array_unique(array_merge(
                array_keys($gradingData['per_teacher']),
                array_keys($tutorData)
            ));

            $rows = [];
            foreach ($hemisIds as $hemisId) {
                $payload = [
                    'grading' => $gradingData['per_teacher'][$hemisId] ?? null,
                    'tutor'   => $tutorData[$hemisId] ?? null,
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

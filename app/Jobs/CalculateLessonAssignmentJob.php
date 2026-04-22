<?php

namespace App\Jobs;

use App\Models\Curriculum;
use App\Models\Department;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateLessonAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    private array $filters;
    private string $calcKey;

    public function __construct(array $filters, string $calcKey)
    {
        $this->filters = $filters;
        $this->calcKey = $calcKey;
    }

    public function handle(): void
    {
        Cache::put($this->calcKey, [
            'status' => 'running',
            'message' => 'Hisoblanmoqda...',
        ], 600);

        try {
            $results = $this->calculate();

            Cache::put($this->calcKey, [
                'status' => 'done',
                'results' => $results,
            ], 600);
        } catch (\Throwable $e) {
            Log::error('[CalculateLessonAssignment] ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'filters' => $this->filters,
            ]);

            Cache::put($this->calcKey, [
                'status' => 'failed',
                'message' => $e->getMessage(),
            ], 600);
        }
    }

    private function calculate(): array
    {
        $f = $this->filters;

        $excludedCodes = config('app.attendance_excluded_training_types', [99, 100, 101, 102]);
        $gradeExcludedTypes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $scheduleQuery = DB::table('schedules as sch')
            ->join('groups as g', 'g.group_hemis_id', '=', 'sch.group_id')
            ->leftJoin('semesters as sem', function ($join) {
                $join->on('sem.code', '=', 'sch.semester_code')
                    ->on('sem.curriculum_hemis_id', '=', 'g.curriculum_hemis_id');
            })
            ->whereNotIn('sch.training_type_code', $excludedCodes)
            ->whereNotNull('sch.lesson_date')
            ->whereNull('sch.deleted_at');

        if (($f['current_semester'] ?? '1') == '1') {
            $scheduleQuery->where(function ($q) {
                $q->where('sem.current', true)->orWhereNull('sem.id');
            });
        }

        if (!empty($f['education_type'])) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $f['education_type'])->pluck('curricula_hemis_id')
                )->pluck('group_hemis_id')->toArray();
            $scheduleQuery->whereIn('sch.group_id', $groupIds);
        }

        if (!empty($f['faculty'])) {
            $faculty = Department::find($f['faculty']);
            if ($faculty) $scheduleQuery->where('sch.faculty_id', $faculty->department_hemis_id);
        }

        if (!empty($f['specialty'])) $scheduleQuery->where('g.specialty_hemis_id', $f['specialty']);
        if (!empty($f['level_code'])) $scheduleQuery->where('sem.level_code', $f['level_code']);
        if (!empty($f['semester_code'])) $scheduleQuery->where('sch.semester_code', $f['semester_code']);
        if (!empty($f['department'])) $scheduleQuery->where('sch.department_id', $f['department']);
        if (!empty($f['subject'])) $scheduleQuery->where('sch.subject_id', $f['subject']);
        if (!empty($f['group'])) $scheduleQuery->where('sch.group_id', $f['group']);

        if (!empty($f['date_from'])) $scheduleQuery->whereRaw('DATE(sch.lesson_date) >= ?', [$f['date_from']]);
        if (!empty($f['date_to'])) $scheduleQuery->whereRaw('DATE(sch.lesson_date) <= ?', [$f['date_to']]);

        if (!empty($f['dekan_faculty_ids'])) {
            $deptHemisIds = Department::whereIn('id', $f['dekan_faculty_ids'])->pluck('department_hemis_id')->toArray();
            if (!empty($deptHemisIds)) $scheduleQuery->whereIn('sch.faculty_id', $deptHemisIds);
        }

        $schedules = $scheduleQuery->select(
            'sch.schedule_hemis_id', 'sch.employee_id', 'sch.employee_name',
            'sch.faculty_name', 'g.specialty_name', 'sem.level_name',
            'sch.semester_code', 'sch.semester_name', 'sch.department_name',
            'sch.subject_id', 'sch.subject_name', 'sch.group_id', 'sch.group_name',
            'sch.training_type_code', 'sch.training_type_name',
            'sch.lesson_pair_code', 'sch.lesson_pair_start_time', 'sch.lesson_pair_end_time',
            'g.id as group_db_id',
            DB::raw('DATE(sch.lesson_date) as lesson_date_str')
        )->get();

        if ($schedules->isEmpty()) return [];

        $employeeIds = $schedules->pluck('employee_id')->unique()->values()->toArray();
        $subjectIds = $schedules->pluck('subject_id')->unique()->values()->toArray();
        $groupHemisIds = $schedules->pluck('group_id')->unique()->values()->toArray();
        $scheduleHemisIds = $schedules->pluck('schedule_hemis_id')->unique()->values()->toArray();
        $minDate = $schedules->min('lesson_date_str');
        $maxDate = $schedules->max('lesson_date_str');

        $attendanceByScheduleId = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->where('load', '>', 0)
            ->pluck('subject_schedule_id')->flip();

        $attendanceByKey = DB::table('attendance_controls')
            ->whereNull('deleted_at')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('group_id', $groupHemisIds)
            ->whereIn('subject_id', $subjectIds)
            ->whereRaw('DATE(lesson_date) BETWEEN ? AND ?', [$minDate, $maxDate])
            ->where('load', '>', 0)
            ->select(DB::raw("DISTINCT CONCAT(employee_id, '|', group_id, '|', subject_id, '|', DATE(lesson_date), '|', training_type_code, '|', lesson_pair_code) as ck"))
            ->pluck('ck')->flip();

        $processedCountByScheduleId = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('subject_schedule_id', $scheduleHemisIds)
            ->select('subject_schedule_id', DB::raw('COUNT(DISTINCT student_hemis_id) as cnt'))
            ->groupBy('subject_schedule_id')
            ->pluck('cnt', 'subject_schedule_id');

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

        $semesterCodes = $schedules->pluck('semester_code')->unique()->values()->toArray();

        $subjectStudentCounts = DB::table('student_subjects as ss')
            ->join('students as st', 'st.hemis_id', '=', 'ss.student_hemis_id')
            ->whereIn('st.group_id', $groupHemisIds)
            ->whereIn('ss.subject_id', $subjectIds)
            ->whereIn('ss.semester_id', $semesterCodes)
            ->where('st.student_status_code', 11)
            ->select(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id) as gs_key"), DB::raw('COUNT(DISTINCT ss.student_hemis_id) as cnt'))
            ->groupBy(DB::raw("CONCAT(st.group_id, '|', ss.subject_id, '|', ss.semester_id)"))
            ->pluck('cnt', 'gs_key');

        $groupStudentCounts = DB::table('students')
            ->whereIn('group_id', $groupHemisIds)
            ->where('student_status_code', 11)
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        $grouped = [];
        foreach ($schedules as $sch) {
            $key = $sch->employee_id . '|' . $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str
                 . '|' . $sch->training_type_code . '|' . $sch->lesson_pair_code;

            $pairStart = $sch->lesson_pair_start_time ? substr($sch->lesson_pair_start_time, 0, 5) : '';
            $pairEnd = $sch->lesson_pair_end_time ? substr($sch->lesson_pair_end_time, 0, 5) : '';
            $pairTime = ($pairStart && $pairEnd) ? ($pairStart . '-' . $pairEnd) : '';

            $attKey = $key;
            $gradeKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->lesson_date_str . '|' . $sch->lesson_pair_code;

            $hasAtt = isset($attendanceByScheduleId[$sch->schedule_hemis_id]) || isset($attendanceByKey[$attKey]);

            $skipGradeCheck = in_array($sch->training_type_code, $gradeExcludedTypes);
            $gsKey = $sch->group_id . '|' . $sch->subject_id . '|' . $sch->semester_code;
            $totalStudents = $subjectStudentCounts[$gsKey] ?? ($groupStudentCounts[$sch->group_id] ?? 0);

            if ($skipGradeCheck) {
                $hasGrade = null;
                $missingGradeCount = 0;
            } else {
                $processedCount = max(
                    $processedCountByScheduleId[$sch->schedule_hemis_id] ?? 0,
                    $processedCountByKey[$gradeKey] ?? 0
                );
                if ($processedCount >= $totalStudents) {
                    $hasGrade = true;
                    $missingGradeCount = 0;
                } else {
                    $hasGrade = false;
                    $missingGradeCount = $totalStudents - $processedCount;
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
                if ($hasAtt && !$grouped[$key]['has_attendance']) $grouped[$key]['has_attendance'] = true;
                if ($hasGrade === true && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['has_grades'] = true;
                    $grouped[$key]['missing_grade_count'] = 0;
                } elseif ($hasGrade === false && $grouped[$key]['has_grades'] === false) {
                    $grouped[$key]['missing_grade_count'] = min($grouped[$key]['missing_grade_count'], $missingGradeCount);
                }
            }
        }

        return array_values($grouped);
    }
}

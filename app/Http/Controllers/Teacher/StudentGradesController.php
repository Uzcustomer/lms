<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Department;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Services\StudentGradeService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentGradesController extends Controller
{
    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }
    public function index(Request $request): View
    {
        $teacher = Auth::guard('teacher')->user()->load('groups');
        $groups = collect();
        if ($teacher->hasRole('dekan')) {
            $departmentGroups = Group::whereIn('department_hemis_id', $teacher->dean_faculty_ids)->get();
            $teachingGroups = $teacher->groups;
            $groups = $departmentGroups->merge($teachingGroups)->unique('id');
        } else {
            $groups = $teacher->groups;
        }
        $semesters = collect();
        $subjects = collect();
        $students = collect();
        $weeks = collect();
        $dates = collect();
        $subject = null;
        $teacherName = $teacher->full_name;
        $departmentId = $teacher->hasRole('dekan')
            ? $teacher->deanFaculties()->first()?->id
            : Department::where('department_hemis_id', $teacher->department_hemis_id)->first()?->id;
        $viewType = $request->input('viewType', 'week');

        $averageGradesForSubject = [];
        $averageGradesPerStudentPerPeriod = [];

        if ($request->filled('group')) {
            $group = Group::findOrFail($request->group);

            if (!$group) {
                abort(404, 'Guruh topilmadi.');
            }
            $hasAccess = $teacher->hasRole('dekan')
                ? (in_array($group->department_hemis_id, $teacher->dean_faculty_ids) || $teacher->groups->contains($group))
                : $teacher->groups->contains($group);
            if (!$hasAccess) {
                abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
            }

            $semesters = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)->get();
        }

        if ($request->filled('group') && $request->filled('semester')) {
            $group = Group::findOrFail($request->group);
            $semester = Semester::findOrFail($request->semester);

            $hasAccess = $teacher->hasRole('dekan')
                ? (in_array($group->department_hemis_id, $teacher->dean_faculty_ids) || $teacher->groups->contains($group))
                : $teacher->groups->contains($group);

            if (!$hasAccess) {
                abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
            }

            $subjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semester->code)
                ->get();
        }

        if ($request->filled(['group', 'semester', 'subject'])) {
            $group = Group::find($request->group);

            if (!$group) {
                abort(404, 'Guruh topilmadi.');
            }

            if (!$teacher->hasRole('dekan')) {
                if (!$teacher->groups->contains($group)) {
                    abort(403, 'Siz bu guruhga bog\'lanmagansiz.');
                }
            }

            $semester = Semester::findOrFail($request->semester);
            $subject = CurriculumSubject::findOrFail($request->subject);

            $students = Student::where('group_id', $group->group_hemis_id)->get();
            $studentIds = $students->pluck('hemis_id');

            if ($viewType == 'week') {
                $weeks = CurriculumWeek::where('semester_hemis_id', $semester->semester_hemis_id)
                    ->orderBy('start_date')
                    ->get();

                $startDate = $weeks->first()->start_date;
                $endDate = $weeks->last()->end_date;

                $dateToWeekIndex = [];
                foreach ($weeks as $index => $week) {
                    $period = CarbonPeriod::create($week->start_date, $week->end_date);
                    foreach ($period as $date) {
                        $dateToWeekIndex[$date->format('Y-m-d')] = $index;
                    }
                }
            } else {
                // whereHas('studentGrades')->
                $lessonDates = Schedule::where('subject_id', $subject->subject_id)
                    ->where('group_id', $group->group_hemis_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->distinct('lesson_date')
                    ->pluck('lesson_date')
                    ->map(function ($date) {
                        return Carbon::parse($date);
                    })->unique()->sort();

                $dates = $lessonDates;

                $startDate = $dates->first();
                $endDate = $dates->last();
            }

            $grades = StudentGrade::whereIn('student_hemis_id', $studentIds)
                ->where('subject_id', $subject->subject_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->whereBetween('lesson_date', [$startDate, $endDate])
                ->get();

            $gradesPerStudent = [];
            $gradesPerStudentPerPeriod = [];

            foreach ($grades as $grade) {
                $studentId = $grade->student_hemis_id;
                $lessonDate = Carbon::parse($grade->lesson_date)->format('Y-m-d');

                if (!isset($gradesPerStudent[$studentId])) {
                    $gradesPerStudent[$studentId] = [];
                }
                $gradesPerStudent[$studentId][] = $grade;

                if ($viewType == 'week') {
                    $periodKey = $dateToWeekIndex[$lessonDate] ?? null;
                } else {
                    $periodKey = $lessonDate;
                }

                if ($periodKey !== null) {
                    if (!isset($gradesPerStudentPerPeriod[$studentId][$periodKey])) {
                        $gradesPerStudentPerPeriod[$studentId][$periodKey] = [];
                    }
                    $gradesPerStudentPerPeriod[$studentId][$periodKey][] = $grade;
                }

                foreach ($students as $student) {
                    if (!isset($averageGradesPerStudentPerPeriod[$student->hemis_id])) {
                        $averageGradesPerStudentPerPeriod[$student->hemis_id] = [];
                    }

                    if ($viewType == 'week') {
                        foreach ($weeks as $index => $week) {
                            $weekGrades = $gradesPerStudentPerPeriod[$student->hemis_id][$index] ?? [];
                            $averageGradesPerStudentPerPeriod[$student->hemis_id][$index] =
                                empty($weekGrades) ? null : $this->studentGradeService->computeDailyAverage($weekGrades);
                        }
                    } else {
                        foreach ($dates as $date) {
                            $dateKey = $date->format('Y-m-d');
                            $dailyGrades = $gradesPerStudentPerPeriod[$student->hemis_id][$dateKey] ?? [];
                            $averageGradesPerStudentPerPeriod[$student->hemis_id][$dateKey] =
                                empty($dailyGrades) ? null : $this->studentGradeService->computeDailyAverage($dailyGrades);

                        }
                    }
                }
            }
            $averageGradesForSubject = $this->calculateMiddlePoint($averageGradesPerStudentPerPeriod);

            return view('teacher.students.student-grades', compact(
                'groups',
                'semesters',
                'subjects',
                'students',
                'weeks',
                'dates',
                'subject',
                'viewType',
                'teacherName',
                'averageGradesForSubject',
                'averageGradesPerStudentPerPeriod'
            ));
        }
        return view('teacher.students.student-grades', compact(
            'groups',
            'semesters',
            'subjects',
            'students',
            'weeks',
            'dates',
            'subject',
            'viewType',
            'teacherName',
            'departmentId',
            'averageGradesForSubject',
            'averageGradesPerStudentPerPeriod'
        ));
    }

    private function calculateMiddlePoint($prePeriod)
    {
        $gradesWithDays = [];
        foreach ($prePeriod as $studentId => $periods) {
            $total = 0.0;
            $dates = [];
            foreach ($periods as $date => $period) {
                if ($period !== null) {
                    $total += (float) $period;
                }
                $dates[] = $date;
            }
            $datesCount = count($dates);

            if ($datesCount > 0) {
                $gradesWithDays[$studentId] = [
                    'average' => round($total / $datesCount, 2),
                    'days' => $datesCount
                ];
            } else {
                $gradesWithDays[$studentId] = [
                    'average' => null,
                    'days' => 0
                ];
            }
        }
        return $gradesWithDays;
    }
}

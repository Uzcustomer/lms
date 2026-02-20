<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentMtSubmission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentApiController extends Controller
{
    /**
     * Dashboard — GPA, absent count, debt count, recent grades
     */
    public function dashboard(Request $request): JsonResponse
    {
        $student = $request->user();

        $avgGpa = $student->avg_gpa ?? 0;

        $totalAbsent = Attendance::where('student_id', $student->id)->count();

        $debtSubjectsCount = StudentGrade::where('student_id', $student->id)
            ->whereIn('status', ['pending'])
            ->count();

        $recentGrades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'employee_name' => $g->employee_name,
            ]);

        return response()->json([
            'data' => [
                'student_name' => $student->full_name,
                'gpa' => (float) $avgGpa,
                'avg_grade' => $student->avg_grade ?? 0,
                'debt_subjects' => $debtSubjectsCount,
                'total_absences' => $totalAbsent,
                'recent_grades' => $recentGrades,
            ],
        ]);
    }

    /**
     * Profile — student info
     */
    public function profile(Request $request): JsonResponse
    {
        $student = $request->user();

        // Calculate course number from semester name (e.g. "8-semestr" -> kurs 4)
        $course = null;
        if ($student->semester_name && preg_match('/(\d+)/', $student->semester_name, $matches)) {
            $semNum = (int) $matches[1];
            $course = (int) ceil($semNum / 2);
        }
        if (!$course && $student->year_of_enter) {
            $enterYear = (int) $student->year_of_enter;
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');
            $course = $currentMonth >= 9
                ? $currentYear - $enterYear + 1
                : $currentYear - $enterYear;
        }

        return response()->json([
            'data' => [
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'image' => $student->image,
                'birth_date' => $student->birth_date?->format('Y-m-d'),
                'phone' => $student->other['phone'] ?? '',
                'email' => $student->other['email'] ?? '',
                'gender' => $student->gender,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'specialty_name' => $student->specialty_name,
                'level_code' => $student->level_code,
                'level_name' => $student->level_name,
                'course' => $course,
                'education_type_name' => $student->education_type_name,
                'education_form_name' => $student->education_form_name ?? null,
                'education_year_code' => $student->education_year_code,
                'education_year_name' => $student->education_year_name,
                'year_of_enter' => $student->year_of_enter,
                'semester_code' => $student->semester_code,
                'semester_name' => $student->semester_name,
                'province_name' => $student->province_name,
                'district_name' => $student->district_name,
                'avg_gpa' => $student->avg_gpa,
                'avg_grade' => $student->avg_grade,
                'total_credit' => $student->total_credit ?? null,
            ],
        ]);
    }

    /**
     * Schedule — by semester and week
     */
    public function schedule(Request $request): JsonResponse
    {
        $student = $request->user();

        // Get semesters
        $semesters = Semester::where('curriculum_hemis_id', $student->curriculum_id)
            ->get()
            ->map(fn($sem) => [
                'id' => $sem->semester_hemis_id,
                'name' => $sem->name,
                'code' => $sem->code,
                'current' => (bool) $sem->current,
            ]);

        $currentSemester = $semesters->firstWhere('current', true);
        $selectedSemesterId = $request->input('semester_id', $currentSemester['id'] ?? $semesters->first()['id'] ?? null);
        $selectedSemester = $semesters->firstWhere('id', $selectedSemesterId);

        if (!$selectedSemester) {
            return response()->json(['message' => 'Semestr topilmadi.'], 404);
        }

        // Get weeks
        $weeks = CurriculumWeek::where('semester_hemis_id', $selectedSemesterId)
            ->orderBy('start_date')
            ->get()
            ->map(fn($week) => [
                'id' => $week->curriculum_week_hemis_id,
                'start_date' => $week->start_date->format('Y-m-d'),
                'end_date' => $week->end_date->format('Y-m-d'),
            ])->values();

        // Find current or selected week
        $currentDate = Carbon::now();
        $currentWeek = $weeks->first(fn($w) =>
            $currentDate->between(Carbon::parse($w['start_date']), Carbon::parse($w['end_date']))
        );
        if (!$currentWeek) {
            $currentWeek = $weeks->first(fn($w) => Carbon::parse($w['start_date'])->isAfter($currentDate));
        }

        $selectedWeekId = $request->input('week_id', $currentWeek['id'] ?? ($weeks->first()['id'] ?? null));
        $selectedWeek = $weeks->firstWhere('id', $selectedWeekId);

        $weekStart = $selectedWeek ? Carbon::parse($selectedWeek['start_date']) : null;
        $weekEnd = $selectedWeek ? Carbon::parse($selectedWeek['end_date']) : null;

        // Query schedule
        $scheduleQuery = Schedule::where('group_id', $student->group_id)
            ->where('semester_code', $selectedSemester['code']);

        if ($weekStart && $weekEnd) {
            $scheduleQuery->whereBetween('lesson_date', [$weekStart, $weekEnd]);
        }

        $scheduleRecords = $scheduleQuery->get();

        $groupedSchedule = $scheduleRecords
            ->groupBy(fn($lesson) => Carbon::parse($lesson->lesson_date)->format('Y-m-d'))
            ->map(function ($dayLessons, $date) {
                $lessons = $dayLessons
                    ->unique(fn($l) => $l->subject_id . $l->lesson_pair_start_time . $l->lesson_pair_end_time . $l->auditorium_code . $l->employee_id)
                    ->map(fn($l) => [
                        'subject_name' => $l->subject_name,
                        'subject_id' => $l->subject_id,
                        'employee_name' => $l->employee_name,
                        'auditorium_name' => $l->auditorium_name ?? '',
                        'lesson_pair_code' => $l->lesson_pair_code ?? null,
                        'lesson_pair_start_time' => $l->lesson_pair_start_time,
                        'lesson_pair_end_time' => $l->lesson_pair_end_time,
                        'training_type_name' => $l->training_type_name,
                    ])
                    ->sortBy('lesson_pair_start_time')
                    ->values();

                return [
                    'date' => $date,
                    'day_name' => Carbon::parse($date)->locale('uz')->dayName,
                    'lessons' => $lessons,
                ];
            })
            ->sortKeys()
            ->values();

        // Build days map keyed by day_name for mobile app
        $days = [];
        foreach ($groupedSchedule as $day) {
            $days[$day['day_name']] = $day['lessons'];
        }

        $weekLabel = null;
        if ($selectedWeek) {
            $weekLabel = Carbon::parse($selectedWeek['start_date'])->format('d.m') . ' - ' . Carbon::parse($selectedWeek['end_date'])->format('d.m.Y');
        }

        return response()->json([
            'data' => [
                'semesters' => $semesters,
                'selected_semester_id' => $selectedSemesterId,
                'weeks' => $weeks,
                'selected_week_id' => $selectedWeekId,
                'week_label' => $weekLabel,
                'days' => $days,
                'schedule' => $groupedSchedule,
            ],
        ]);
    }

    /**
     * Subjects with all grade breakdowns (JB, MT, ON, OSKI, Test)
     */
    public function subjects(Request $request): JsonResponse
    {
        $student = $request->user();
        $semesterCode = $student->semester_code;
        $studentHemisId = $student->hemis_id;
        $groupHemisId = $student->group_id;

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $gradingCutoffDate = Carbon::now('Asia/Tashkent')->subDay()->startOfDay();

        $getEffectiveGrade = function ($row) {
            if ($row->status === 'pending') return null;
            if ($row->reason === 'absent' && $row->grade === null) {
                return $row->retake_grade !== null ? $row->retake_grade : null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            if ($row->status === 'recorded') return $row->grade;
            if ($row->status === 'closed') return $row->grade;
            if ($row->retake_grade !== null) return $row->retake_grade;
            return null;
        };

        $curriculumSubjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $semesterCode)
            ->get();

        $subjects = $curriculumSubjects->map(function ($cs) use (
            $semesterCode, $studentHemisId, $groupHemisId, $educationYearCode,
            $excludedTrainingCodes, $gradingCutoffDate, $getEffectiveGrade
        ) {
            $subjectId = $cs->subject_id;

            // Education year code from schedule
            $subjectEducationYearCode = $educationYearCode;
            $scheduleEducationYear = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->value('education_year_code');
            if ($scheduleEducationYear) {
                $subjectEducationYearCode = $scheduleEducationYear;
            }

            // JB (Amaliyot) grades
            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')
                ->get();

            $minScheduleDate = $jbScheduleRows->pluck('lesson_date')->min();

            $jbGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode)
                        ->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                        });
                }))
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $jbColumns = $jbScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
                ->merge($jbGradesRaw->map(fn($g) => ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code]))
                ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
                ->sortBy('date')
                ->values();

            $jbPairsPerDay = [];
            foreach ($jbColumns as $col) {
                $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $jbLessonDates = $jbColumns->pluck('date')->unique()->sort()->values()->toArray();
            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, fn($date) =>
                Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate)
            ));
            $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
            $totalJbDaysForAverage = count($jbLessonDatesForAverage);

            $jbGradesByDatePair = [];
            foreach ($jbGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $jbGradesByDatePair[$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
                }
            }

            $dailySum = 0;
            foreach ($jbLessonDates as $date) {
                $dayGrades = $jbGradesByDatePair[$date] ?? [];
                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                if (isset($jbLessonDatesForAverageLookup[$date])) {
                    $dailySum += $dayAverage;
                }
            }
            $jnAverage = $totalJbDaysForAverage > 0
                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                : 0;

            // MT (Mustaqil ta'lim)
            $mtGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $mtColumns = $mtGradesRaw->map(fn($g) => ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code])
                ->unique(fn($item) => $item['date'] . '_' . $item['pair'])
                ->sortBy('date')
                ->values();

            $mtPairsPerDay = [];
            foreach ($mtColumns as $col) {
                $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
            }
            $mtLessonDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();
            $totalMtDays = count($mtLessonDates);

            $mtGradesByDatePair = [];
            foreach ($mtGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $mtGradesByDatePair[$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
                }
            }

            $mtDailySum = 0;
            foreach ($mtLessonDates as $date) {
                $dayGrades = $mtGradesByDatePair[$date] ?? [];
                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                $mtDailySum += round(array_sum($dayGrades) / $pairsInDay, 0, PHP_ROUND_HALF_UP);
            }
            $mtAverage = $totalMtDays > 0
                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                : 0;

            // Manual MT grade override
            $manualMt = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->value('grade');
            if ($manualMt !== null) {
                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
            }

            // ON, OSKI, Test (100, 101, 102)
            $otherGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [100, 101, 102])
                ->select('training_type_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $otherGrades = ['on' => null, 'oski' => null, 'test' => null];
            $otherByType = [];
            foreach ($otherGradesRaw as $g) {
                $effectiveGrade = $getEffectiveGrade($g);
                if ($effectiveGrade !== null) {
                    $otherByType[$g->training_type_code][] = $effectiveGrade;
                }
            }
            if (!empty($otherByType[100])) $otherGrades['on'] = round(array_sum($otherByType[100]) / count($otherByType[100]), 0, PHP_ROUND_HALF_UP);
            if (!empty($otherByType[101])) $otherGrades['oski'] = round(array_sum($otherByType[101]) / count($otherByType[101]), 0, PHP_ROUND_HALF_UP);
            if (!empty($otherByType[102])) $otherGrades['test'] = round(array_sum($otherByType[102]) / count($otherByType[102]), 0, PHP_ROUND_HALF_UP);

            // Attendance
            $absentOff = DB::table('attendances')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_code', [99, 100, 101, 102])
                ->sum('absent_off');

            $nonAuditoriumCodes = ['17'];
            $auditoriumHours = 0;
            if (is_array($cs->subject_details)) {
                foreach ($cs->subject_details as $detail) {
                    $trainingCode = (string) ($detail['trainingType']['code'] ?? '');
                    if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                        $auditoriumHours += (float) ($detail['academic_load'] ?? 0);
                    }
                }
            }
            if ($auditoriumHours <= 0) {
                $auditoriumHours = $cs->total_acload ?? 0;
            }
            $davomatPercent = $auditoriumHours > 0 ? round(($absentOff / $auditoriumHours) * 100, 2) : 0;

            $total = null;
            $gradeComponents = array_filter([$jnAverage, $mtAverage, $otherGrades['on'], $otherGrades['oski'], $otherGrades['test']], fn($v) => $v !== null && $v > 0);
            if (!empty($gradeComponents)) {
                $total = (int) round(array_sum($gradeComponents) / count($gradeComponents));
            }

            return [
                'subject_name' => $cs->subject_name,
                'credit' => $cs->credit,
                'subject_id' => $subjectId,
                'employee_name' => null,
                'grades' => [
                    'jn' => $jnAverage > 0 ? $jnAverage : null,
                    'mt' => $mtAverage > 0 ? $mtAverage : null,
                    'on' => $otherGrades['on'],
                    'oski' => $otherGrades['oski'],
                    'test' => $otherGrades['test'],
                    'total' => $total,
                ],
                'dav_percent' => $davomatPercent,
                'absent_hours' => $absentOff,
                'auditorium_hours' => $auditoriumHours,
            ];
        });

        return response()->json([
            'data' => $subjects->values(),
        ]);
    }

    /**
     * Grades detail for a specific subject
     */
    public function subjectGrades(Request $request, $subjectId): JsonResponse
    {
        $student = $request->user();
        $semester = $student->semester_code;

        $grades = StudentGrade::where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->orderBy('lesson_date', 'desc')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'status' => $g->status,
                'reason' => $g->reason,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'training_type_code' => $g->training_type_code,
                'employee_name' => $g->employee_name,
                'lesson_pair_name' => $g->lesson_pair_name,
                'lesson_pair_start_time' => $g->lesson_pair_start_time,
                'lesson_pair_end_time' => $g->lesson_pair_end_time,
            ]);

        return response()->json([
            'data' => [
                'subject_id' => $subjectId,
                'grades' => $grades,
            ],
        ]);
    }

    /**
     * Pending/retake lessons
     */
    public function pendingLessons(Request $request): JsonResponse
    {
        $student = $request->user();

        $pendingLessons = StudentGrade::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'retake'])
            ->orderBy('lesson_date')
            ->get()
            ->map(fn($g) => [
                'id' => $g->id,
                'subject_name' => $g->subject_name,
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'status' => $g->status,
                'reason' => $g->reason,
                'lesson_date' => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'employee_name' => $g->employee_name,
                'deadline' => $g->deadline,
            ]);

        return response()->json([
            'data' => $pendingLessons,
        ]);
    }

    /**
     * Upload MT (Mustaqil ta'lim) file
     */
    public function mtUpload(Request $request, $subjectId): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,ppt,pptx,zip,rar',
        ]);

        $student = $request->user();
        $file = $request->file('file');

        $cs = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $student->semester_code)
            ->where('subject_id', $subjectId)
            ->first();

        if (!$cs) {
            return response()->json(['message' => 'Fan topilmadi.'], 404);
        }

        $path = $file->store("mt_submissions/{$student->id}/{$subjectId}", 'public');

        $submission = StudentMtSubmission::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'subject_id' => $subjectId,
            'subject_name' => $cs->subject_name,
            'semester_code' => $student->semester_code,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Fayl muvaffaqiyatli yuklandi.',
            'data' => [
                'id' => $submission->id,
                'file_name' => $submission->file_name,
                'status' => $submission->status,
                'created_at' => $submission->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get MT submissions for a subject
     */
    public function mtSubmissions(Request $request, $subjectId): JsonResponse
    {
        $student = $request->user();

        $submissions = StudentMtSubmission::where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $student->semester_code)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'file_name' => $s->file_name,
                'file_size' => $s->file_size,
                'status' => $s->status,
                'teacher_comment' => $s->teacher_comment,
                'created_at' => $s->created_at->toISOString(),
            ]);

        return response()->json([
            'data' => $submissions,
        ]);
    }

    /**
     * Attendance records
     */
    public function attendance(Request $request): JsonResponse
    {
        $student = $request->user();
        $semester = $student->semester_code;

        $attendanceData = Attendance::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $semester)
            ->orderBy('lesson_date', 'desc')
            ->get()
            ->map(fn($item) => [
                'semester' => $item->semester_name,
                'date' => Carbon::parse($item->lesson_date)->format('Y-m-d'),
                'subject' => $item->subject_name,
                'training_type' => $item->training_type_name,
                'employee' => $item->employee_name,
                'lesson_pair' => $item->lesson_pair_name,
                'start_time' => $item->lesson_pair_start_time,
                'end_time' => $item->lesson_pair_end_time,
                'is_absent' => $item->absent_on > 0,
                'hours' => $item->absent_on == 0 ? 2 : 0,
            ]);

        return response()->json([
            'data' => [
                'attendance' => $attendanceData,
            ],
        ]);
    }
}

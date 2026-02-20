<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Group;
use App\Models\Independent;
use App\Models\IndependentGradeHistory;
use App\Models\IndependentSubmission;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\StudentGradeService;
use App\Models\MarkingSystemScore;
use App\Models\YnConsent;
use App\Models\YnSubmission;

class StudentController extends Controller
{
    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }

    private function redirectIfPasswordChangeRequired()
    {
        if (session('impersonating')) {
            return null;
        }

        if (Auth::guard('student')->user()?->must_change_password) {
            return redirect()->route('student.password.edit');
        }

        return null;
    }
    public function dashboard()
    {
        $student = Auth::guard('student')->user();

        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $avgGpa = $student->avg_gpa ?? 0;

        $totalAbsent = Attendance::where('student_id', $student->id)->count();

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $debtSubjectsCount = StudentGrade::where('student_id', $student->id)
            ->whereIn('status', ["pending"])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->count();

        $recentGrades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        return view('student.dashboard', compact('avgGpa', 'totalAbsent', 'debtSubjectsCount', 'recentGrades'));
    }

    public function getSchedule(Request $request)
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::guard('student')->user();

        $semesters = Semester::where('curriculum_hemis_id', $student->curriculum_id)
            ->get()
            ->map(function ($sem) {
                return [
                    'id' => $sem->semester_hemis_id,
                    'name' => $sem->name,
                    'code' => $sem->code,
                    'current' => $sem->current,
                    'education_year' => ['name' => $sem->education_year ?? ''],
                ];
            });

        $currentSemester = $semesters->firstWhere('current', true);
        $selectedSemesterId = $request->input('semester_id', $currentSemester['id'] ?? $semesters->first()['id'] ?? null);
        $selectedSemesterData = $semesters->firstWhere('id', $selectedSemesterId);

        if (!$selectedSemesterData) {
            return back()->withErrors('Semestr topilmadi.');
        }

        $weeks = CurriculumWeek::where('semester_hemis_id', $selectedSemesterId)
            ->orderBy('start_date')
            ->get()
            ->map(function ($week) {
                return [
                    'id' => $week->curriculum_week_hemis_id,
                    'start_date' => $week->start_date->timestamp,
                    'end_date' => $week->end_date->timestamp,
                ];
            })->values();

        $selectedSemester = array_merge($selectedSemesterData, ['weeks' => $weeks->toArray()]);

        $currentDate = Carbon::now();
        $currentWeek = $weeks->first(function ($week) use ($currentDate) {
            return $currentDate->between(
                Carbon::createFromTimestamp($week['start_date']),
                Carbon::createFromTimestamp($week['end_date'])
            );
        });

        if (!$currentWeek) {
            $currentWeek = $weeks->first(function ($week) use ($currentDate) {
                return Carbon::createFromTimestamp($week['start_date'])->isAfter($currentDate);
            });
        }

        $selectedWeekId = $request->input('week_id', $currentWeek['id'] ?? ($weeks->first()['id'] ?? null));

        $selectedWeek = $weeks->firstWhere('id', $selectedWeekId);
        $weekStart = $selectedWeek ? Carbon::createFromTimestamp($selectedWeek['start_date']) : null;
        $weekEnd = $selectedWeek ? Carbon::createFromTimestamp($selectedWeek['end_date']) : null;

        $scheduleQuery = Schedule::where('group_id', $student->group_id)
            ->where('semester_code', $selectedSemesterData['code']);

        if ($weekStart && $weekEnd) {
            $scheduleQuery->whereBetween('lesson_date', [$weekStart, $weekEnd]);
        }

        $scheduleRecords = $scheduleQuery->get();

        $groupedSchedule = $scheduleRecords
            ->groupBy(function ($lesson) {
                return Carbon::parse($lesson->lesson_date)->format('l');
            })
            ->map(function ($dayLessons) {
                return $dayLessons
                    ->unique(function ($lesson) {
                        return $lesson->subject_id . $lesson->lesson_pair_start_time . $lesson->lesson_pair_end_time . $lesson->auditorium_code . $lesson->employee_id;
                    })
                    ->map(function ($lesson) {
                        return [
                            'subject' => ['name' => $lesson->subject_name, 'id' => $lesson->subject_id],
                            'employee' => ['name' => $lesson->employee_name, 'id' => $lesson->employee_id],
                            'auditorium' => ['name' => $lesson->auditorium_name ?? '', 'code' => $lesson->auditorium_code],
                            'lessonPair' => [
                                'start_time' => $lesson->lesson_pair_start_time,
                                'end_time' => $lesson->lesson_pair_end_time,
                            ],
                            'lesson_date' => Carbon::parse($lesson->lesson_date)->timestamp,
                        ];
                    })
                    ->sortBy('lessonPair.start_time')
                    ->values();
            })
            ->sortKeys();

        return view('student.student_schedule', compact('groupedSchedule', 'selectedSemester', 'semesters', 'weeks', 'selectedWeekId'));
    }


    public function getAttendance(Request $request)
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::user();
        $semester = $student->semester_code;
        $level_code = $student->level_code;

        $attendanceData = Attendance::where('student_hemis_id', $student->hemis_id)
            ->where('semester_code', $semester)
            ->orderBy('lesson_date', 'desc')
            ->get();

        if ($level_code == 15 || $level_code == 16) {
            $formattedData = $attendanceData->map(function ($item) {
                return [
                    'semester' => $item->semester_name,
                    'date' => Carbon::parse($item->lesson_date)->format('d-m-Y'),
                    'subject' => $item->subject_name,
                    'training_type' => $item->training_type_name,
                    'lesson_pair' => $item->lesson_pair_name,
                    'start_time' => $item->lesson_pair_start_time,
                    'end_time' => $item->lesson_pair_end_time,
                    'auditorium' => null,
                    'building' => null,
                    'employee' => $item->employee_name,
                    'faculty' => '',
                    'department' => '',
                    'group' => $item->group_name,
                    'education_lang' => $item->education_lang_name,
                    'absent_on' => $item->absent_on > 0 ? 'Yo\'q' : 'Ha',
                    'hours' => $item->absent_on == 0 ? 2 : 0
                ];
            })->values();
        } else {
            $formattedData = $attendanceData->map(function ($item) {
                return [
                    'semester' => $item->semester_name,
                    'date' => Carbon::parse($item->lesson_date)->format('d-m-Y') . " " . $item->lesson_pair_start_time,
                    'subject' => $item->subject_name,
                    'training_type' => $item->training_type_name,
                    'employee' => $item->employee_name,
                    'absent_on' => $item->absent_on > 0 ? 'Yo\'q' : 'Ha',
                    'hours' => $item->absent_on == 0 ? 2 : 0
                ];
            })->values();
        }

        return view('student.attendance', [
            'attendanceData' => $formattedData,
            'level_code' => $level_code,
        ]);
    }

    public function getSubjects(Request $request)
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::user();
        $semesterCode = $student->semester_code;
        $semester_name = $student->semester_name;
        $studentHemisId = $student->hemis_id;
        $groupHemisId = $student->group_id;

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test"];
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $gradingCutoffDate = Carbon::now('Asia/Tashkent')->subDay()->startOfDay();

        // MT settings
        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);
        $timeParts = explode(':', $mtDeadlineTime);
        $mtHour = (int) ($timeParts[0] ?? 17);
        $mtMinute = (int) ($timeParts[1] ?? 0);

        // MT deadline type setting
        $mtDeadlineType = Setting::get('mt_deadline_type', 'before_last');

        // Fetch independents for this student's group (only current semester)
        $allIndependents = Independent::where('group_hemis_id', $student->group_id)
            ->where('semester_code', $semesterCode)
            ->get();
        // Index by subject_hemis_id (direct match with curriculum_subject_hemis_id)
        $independentsByHemisId = $allIndependents->groupBy('subject_hemis_id');
        // Also index by subject_name (fallback when hemis_ids differ across curricula)
        $independentsByName = $allIndependents->groupBy('subject_name');
        // Also build mapping: independent's subject_hemis_id -> subject_id (through CurriculumSubject)
        $indHemisIds = $allIndependents->pluck('subject_hemis_id')->unique()->filter()->toArray();
        $hemisToSubjectId = [];
        if (!empty($indHemisIds)) {
            $hemisToSubjectId = CurriculumSubject::whereIn('curriculum_subject_hemis_id', $indHemisIds)
                ->pluck('subject_id', 'curriculum_subject_hemis_id')
                ->toArray();
        }
        $independentsBySubjectId = collect();
        foreach ($allIndependents as $ind) {
            $resolvedSubjectId = $hemisToSubjectId[$ind->subject_hemis_id] ?? null;
            if ($resolvedSubjectId) {
                if (!$independentsBySubjectId->has($resolvedSubjectId)) {
                    $independentsBySubjectId[$resolvedSubjectId] = collect();
                }
                $independentsBySubjectId[$resolvedSubjectId]->push($ind);
            }
        }

        // Pre-load ALL schedule dates grouped by subject (for MT deadline calculation)
        // Barcha dars turlari (ma'ruza, amaliy, ...) kiritiladi - "oxirgi dars" = shu fanning oxirgi darsi
        $allScheduleDatesBySubject = Schedule::where('group_id', $student->group_id)
            ->where('semester_code', $semesterCode)
            ->whereNotNull('lesson_date')
            ->orderBy('lesson_date')
            ->get()
            ->groupBy('subject_id');

        $curriculumSubjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $semesterCode)
            ->get();

        // Helper: effective grade (aynan jurnal mantiqidan)
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

        $subjects = $curriculumSubjects->map(function ($cs) use (
            $semesterCode, $studentHemisId, $groupHemisId, $educationYearCode,
            $excludedTrainingTypes, $excludedTrainingCodes, $gradingCutoffDate, $getEffectiveGrade,
            $student, $independentsByHemisId, $independentsBySubjectId, $independentsByName,
            $mtHour, $mtMinute, $mtMaxResubmissions, $mtDeadlineTime, $mtDeadlineType,
            $allScheduleDatesBySubject
        ) {
            $subjectId = $cs->subject_id;

            // Education year code: schedule dan aniqlash (admin jurnaldek)
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

            // ---- JB (Amaliyot) schedule va baholar ----
            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_name', $excludedTrainingTypes)
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')
                ->orderBy('lesson_pair_code')
                ->get();

            $minScheduleDate = $jbScheduleRows->pluck('lesson_date')->min();

            $jbGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_name', $excludedTrainingTypes)
                ->whereNotIn('training_type_code', $excludedTrainingCodes)
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode);
                    if ($minScheduleDate !== null) {
                        $q2->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->where('lesson_date', '>=', $minScheduleDate);
                        });
                    }
                }))
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            // JB kunlik o'rtachalar (jurnal mantiqidek)
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
            $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
                return Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
            }));
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

            // JB daily data for horizontal view
            $jbAbsentDates = [];
            foreach ($jbGradesRaw as $g) {
                if ($g->reason === 'absent') {
                    $jbAbsentDates[$g->lesson_date] = true;
                }
            }
            $jbDailyData = [];
            foreach ($jbLessonDates as $date) {
                $dayGradesH = $jbGradesByDatePair[$date] ?? [];
                $pairsInDayH = $jbPairsPerDay[$date] ?? 1;
                $hasGradesH = !empty($dayGradesH);
                $gradeSumH = array_sum($dayGradesH);
                $dayAvgH = $hasGradesH ? round($gradeSumH / $pairsInDayH, 0, PHP_ROUND_HALF_UP) : 0;
                $jbDailyData[] = [
                    'date' => $date,
                    'average' => $dayAvgH,
                    'has_grades' => $hasGradesH,
                    'is_absent' => !$hasGradesH && isset($jbAbsentDates[$date]),
                ];
            }

            // ---- MT (Mustaqil ta'lim) ----
            $mtScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->get();

            $mtGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode);
                    if ($minScheduleDate !== null) {
                        $q2->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->where('lesson_date', '>=', $minScheduleDate);
                        });
                    }
                }))
                ->select('lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $mtColumns = $mtScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
                ->merge($mtGradesRaw->map(fn($g) => ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code]))
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
                $gradeSum = array_sum($dayGrades);
                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
            }
            $mtAverage = $totalMtDays > 0
                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                : 0;

            // MT daily data for horizontal view
            $mtAbsentDates = [];
            foreach ($mtGradesRaw as $g) {
                if ($g->reason === 'absent') {
                    $mtAbsentDates[$g->lesson_date] = true;
                }
            }
            $mtDailyData = [];
            foreach ($mtLessonDates as $date) {
                $dayGradesH = $mtGradesByDatePair[$date] ?? [];
                $pairsInDayH = $mtPairsPerDay[$date] ?? 1;
                $hasGradesH = !empty($dayGradesH);
                $gradeSumH = array_sum($dayGradesH);
                $dayAvgH = $hasGradesH ? round($gradeSumH / $pairsInDayH, 0, PHP_ROUND_HALF_UP) : 0;
                $mtDailyData[] = [
                    'date' => $date,
                    'average' => $dayAvgH,
                    'has_grades' => $hasGradesH,
                    'is_absent' => !$hasGradesH && isset($mtAbsentDates[$date]),
                ];
            }

            // Manual MT baho bo'lsa override
            $manualMt = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->value('grade');
            if ($manualMt !== null) {
                $mtAverage = round((float) $manualMt, 0, PHP_ROUND_HALF_UP);
            }

            // ---- ON, OSKI, Test (training_type_code: 100, 101, 102) ----
            $otherGradesRaw = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [100, 101, 102])
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode);
                    if ($minScheduleDate !== null) {
                        $q2->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->where(function ($q5) use ($minScheduleDate) {
                                    $q5->where('lesson_date', '>=', $minScheduleDate)->orWhereNull('lesson_date');
                                });
                        });
                    }
                }))
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

            // ---- Davomat (Attendance) ----
            $excludedAttendanceCodes = [99, 100, 101, 102];
            $absentOff = DB::table('attendances')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->whereNotIn('training_type_code', $excludedAttendanceCodes)
                ->sum('absent_off');

            // Auditoriya soatlari
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

            // ---- Batafsil uchun baholar (Ma'ruza, Amaliy, MT) ----
            $detailGrades = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [100, 101, 102])
                ->whereNotNull('lesson_date')
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where(function ($q2) use ($subjectEducationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $subjectEducationYearCode)
                        ->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                        });
                }))
                ->select('lesson_date', 'training_type_code', 'training_type_name', 'lesson_pair_name',
                    'lesson_pair_start_time', 'lesson_pair_end_time', 'employee_name',
                    'grade', 'retake_grade', 'status', 'reason')
                ->orderBy('lesson_date', 'desc')
                ->get();

            // Ma'ruza uchun davomat
            $lectureAttendance = DB::table('attendances')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->when($subjectEducationYearCode !== null, fn($q) => $q->where('education_year_code', $subjectEducationYearCode))
                ->where('training_type_code', 11)
                ->whereNotNull('lesson_date')
                ->select(DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_name',
                    'lesson_pair_start_time', 'lesson_pair_end_time', 'employee_name',
                    'absent_on', 'absent_off')
                ->orderBy('lesson_date', 'desc')
                ->get()
                ->map(function ($row) {
                    $isAbsent = ((int) $row->absent_on) > 0 || ((int) $row->absent_off) > 0;
                    return [
                        'lesson_date' => $row->lesson_date,
                        'lesson_pair_name' => $row->lesson_pair_name,
                        'lesson_pair_start_time' => $row->lesson_pair_start_time,
                        'lesson_pair_end_time' => $row->lesson_pair_end_time,
                        'employee_name' => $row->employee_name,
                        'status' => $isAbsent ? 'NB' : 'Qatnashdi',
                    ];
                });

            // Ma'ruza attendance grouped by date for horizontal view
            $lectureByDate = $lectureAttendance->groupBy('lesson_date')->map(function($items, $date) {
                $hasAbsent = $items->contains(fn($i) => $i['status'] === 'NB');
                return [
                    'date' => $date,
                    'status' => $hasAbsent ? 'NB' : 'QB',
                    'pairs' => $items->count(),
                ];
            })->sortKeys()->values()->toArray();

            // Baholarni tur bo'yicha ajratish
            $amaliyGrades = [];
            $mtDetailGrades = [];
            foreach ($detailGrades as $g) {
                $item = [
                    'lesson_date' => $g->lesson_date,
                    'lesson_pair_name' => $g->lesson_pair_name,
                    'lesson_pair_start_time' => $g->lesson_pair_start_time,
                    'lesson_pair_end_time' => $g->lesson_pair_end_time,
                    'employee_name' => $g->employee_name,
                    'grade' => $g->grade,
                    'retake_grade' => $g->retake_grade,
                    'status' => $g->status,
                    'reason' => $g->reason,
                ];
                if ($g->training_type_code == 11) {
                    // Ma'ruza baholar (agar bor bo'lsa) - odatda davomat orqali
                    continue;
                } elseif ($g->training_type_code == 99) {
                    $mtDetailGrades[] = $item;
                } else {
                    $amaliyGrades[] = $item;
                }
            }

            // MT data - haqiqiy dars sanalaridan deadline hisoblash
            $mtData = null;

            // Get actual lesson dates for this subject from pre-loaded data
            $subjectSchedules = $allScheduleDatesBySubject->get($cs->subject_id) ?? collect();
            $lessonDates = $subjectSchedules->pluck('lesson_date')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->unique()->sort()->values();

            // Calculate deadline and warning date from ACTUAL lesson dates (no prediction)
            $correctDeadline = null;
            $warningDate = null;
            if ($lessonDates->count() >= 2) {
                $correctDeadline = $mtDeadlineType === 'last'
                    ? $lessonDates->last()
                    : $lessonDates[$lessonDates->count() - 2]; // oxirgi darsdan bitta oldingi
                // Warning date = deadline darsdan bitta oldingi dars
                if ($mtDeadlineType === 'last' && $lessonDates->count() >= 2) {
                    $warningDate = $lessonDates[$lessonDates->count() - 2];
                } elseif ($lessonDates->count() >= 3) {
                    $warningDate = $lessonDates[$lessonDates->count() - 3];
                } else {
                    $warningDate = $lessonDates->first(); // faqat 2 ta dars bo'lsa, boshidan ogohlantirish
                }
            } elseif ($lessonDates->count() === 1) {
                $correctDeadline = $lessonDates->first();
                $warningDate = $lessonDates->first();
            }

            // 3-level fallback: hemis_id -> subject_id -> subject_name
            $subjectIndependents = $independentsByHemisId->get($cs->curriculum_subject_hemis_id)
                ?? $independentsBySubjectId->get($cs->subject_id)
                ?? $independentsByName->get($cs->subject_name);

            // Auto-create Independent from Schedule if none found
            if ((!$subjectIndependents || $subjectIndependents->count() === 0) && $correctDeadline) {
                try {
                    $latestSchedule = $subjectSchedules->sortByDesc('lesson_date')->first();
                    $grp = Group::where('group_hemis_id', $groupHemisId)->first();
                    $semModel = $grp ? Semester::where('code', $semesterCode)
                        ->where('curriculum_hemis_id', $grp->curriculum_hemis_id)->first() : null;

                    $independent = Independent::updateOrCreate(
                        [
                            'group_hemis_id' => $groupHemisId,
                            'subject_hemis_id' => $cs->curriculum_subject_hemis_id,
                            'semester_code' => $semesterCode,
                        ],
                        [
                            'schedule_id' => $latestSchedule->id,
                            'group_name' => $latestSchedule->group_name,
                            'teacher_hemis_id' => $latestSchedule->employee_id,
                            'teacher_name' => $latestSchedule->employee_name ?? '',
                            'teacher_short_name' => '',
                            'department_hemis_id' => $latestSchedule->faculty_id ?? '',
                            'deportment_name' => $latestSchedule->faculty_name ?? '',
                            'start_date' => $lessonDates->first(),
                            'semester_hemis_id' => $semModel?->semester_hemis_id ?? '',
                            'semester_name' => $latestSchedule->semester_name ?? '',
                            'subject_name' => $cs->subject_name,
                            'user_id' => 0,
                            'deadline' => $correctDeadline,
                        ]
                    );

                    $subjectIndependents = collect([$independent]);
                } catch (\Exception $e) {
                    // Silent fail - MT button won't show
                }
            }

            if ($subjectIndependents && $subjectIndependents->count() > 0) {
                $independent = $subjectIndependents->sortByDesc('deadline')->first();

                // Update deadline if incorrect (e.g. from old prediction)
                if ($correctDeadline && $independent->deadline !== $correctDeadline) {
                    $independent->update(['deadline' => $correctDeadline]);
                }

                $submission = $independent->submissionByStudent($student->id);

                $grade = StudentGrade::where('student_id', $student->id)
                    ->where('independent_id', $independent->id)
                    ->first();

                $gradeHistory = collect();
                try {
                    $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
                        ->where('student_id', $student->id)
                        ->orderBy('submission_number')
                        ->get();
                } catch (\Exception $e) {}

                $deadlineDateTime = Carbon::parse($independent->deadline)->setTime($mtHour, $mtMinute, 0);
                $submissionCount = $submission?->submission_count ?? 0;

                // Use mt_grade_history count for accurate resubmission tracking
                // (submission_count increments for every upload, including re-uploads before grading)
                $mtHistoryCount = DB::table('mt_grade_history')
                    ->where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $independent->subject_hemis_id)
                    ->where('semester_code', $independent->semester_code)
                    ->count();
                $remainingAttempts = max(0, $mtMaxResubmissions - $mtHistoryCount);
                $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
                $gradeLocked = $grade && $grade->grade >= $studentMinLimit;
                $isOverdue = Carbon::now()->gt($deadlineDateTime);

                $daysRemaining = null;
                if (!$isOverdue) {
                    $daysRemaining = (int) Carbon::now()->diffInDays($deadlineDateTime, false);
                }

                // Warning: oxirgi muddatdan bitta oldingi darsdan boshlab qizil
                $isWarning = false;
                if ($warningDate && !$isOverdue) {
                    $isWarning = Carbon::now()->gte(Carbon::parse($warningDate)->startOfDay());
                }

                $mtData = [
                    'id' => $independent->id,
                    'deadline' => Carbon::parse($independent->deadline)->format('d.m.Y'),
                    'deadline_time' => $mtDeadlineTime,
                    'is_overdue' => $isOverdue,
                    'is_warning' => $isWarning,
                    'days_remaining' => $daysRemaining,
                    'submission' => $submission,
                    'is_viewed' => (bool) $submission?->viewed_at,
                    'grade' => $grade?->grade,
                    'grade_locked' => $gradeLocked,
                    'grade_history' => $gradeHistory,
                    'submission_count' => $submissionCount,
                    'remaining_attempts' => $remainingAttempts,
                    'can_resubmit' => !$gradeLocked && $submission && $grade && $grade->grade < $studentMinLimit && $remainingAttempts > 0 && !$isOverdue,
                    'file_path' => $independent->file_path,
                    'file_original_name' => $independent->file_original_name,
                ];
            }

            return [
                'name' => $cs->subject_name,
                'credit' => $cs->credit,
                'subject_id' => $subjectId,
                'jn_average' => $jnAverage,
                'mt_average' => $mtAverage,
                'on' => $otherGrades['on'],
                'oski' => $otherGrades['oski'],
                'test' => $otherGrades['test'],
                'dav_percent' => $davomatPercent,
                'absent_hours' => $absentOff,
                'auditorium_hours' => $auditoriumHours,
                'lecture_attendance' => $lectureAttendance->toArray(),
                'amaliy_grades' => $amaliyGrades,
                'mt_grades' => $mtDetailGrades,
                'jb_daily_data' => $jbDailyData,
                'mt_daily_data' => $mtDailyData,
                'lecture_by_date' => $lectureByDate,
                'mt' => $mtData,
            ];
        });

        $minimumLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;

        return view('student.subjects', ['subjects' => $subjects, 'semester' => $semester_name, 'mtDeadlineTime' => $mtDeadlineTime, 'minimumLimit' => $minimumLimit]);
    }

    // Fetch grades for a selected subject
//    public function getSubjectGrades($subjectId)
//    {
//        $token = Auth::user()->token;
//        $semester = Auth::user()->semester_code;
//
//        $gradeResponse = Http::withToken($token)->get('https://student.ttatf.uz/rest/v1/education/performance', [
//            'subject' => $subjectId,
//            'semester' => $semester
//        ]);
//
//        if (!$gradeResponse->successful()) {
//            return back()->withErrors('Failed to fetch grades for the selected subject.');
//        }
//
//        $grades = collect($gradeResponse->json('data'));
//
//        return view('student.subject-grades', ['grades' => $grades]);
//    }


    public function getSubjectGrades($subjectId)
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::user();
        $semester = $student->semester_code;

        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $grades = StudentGrade::where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->orderBy('lesson_date', 'desc')
            ->get();

        $ynConsent = YnConsent::where('student_hemis_id', $student->hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->first();

        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semester)
            ->where('group_hemis_id', $student->group_id)
            ->first();

        return view('student.subject-grades', [
            'grades' => $grades,
            'ynConsent' => $ynConsent,
            'ynSubmission' => $ynSubmission,
            'subjectId' => $subjectId,
        ]);
    }

    public function getPendingLessons()
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::guard('student')->user();
        $curriculum = Curriculum::where('curricula_hemis_id', $student->curriculum_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        $pendingLessons = StudentGrade::where('student_id', $student->id)
            ->whereIn('status', ['pending', 'retake'])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->orderBy('lesson_date')
            ->get();

        return view('student.pending-lessons', compact('pendingLessons'));
    }

    public function getIndependents()
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::guard('student')->user();

        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);
        $timeParts = explode(':', $mtDeadlineTime);
        $hour = (int) ($timeParts[0] ?? 17);
        $minute = (int) ($timeParts[1] ?? 0);

        $independents = Independent::where('group_hemis_id', $student->group_id)
            ->orderBy('deadline', 'asc')
            ->get()
            ->map(function ($independent) use ($student, $hour, $minute, $mtMaxResubmissions) {
                $submission = $independent->submissionByStudent($student->id);
                $grade = StudentGrade::where('student_id', $student->id)
                    ->where('independent_id', $independent->id)
                    ->first();

                $gradeHistory = IndependentGradeHistory::where('independent_id', $independent->id)
                    ->where('student_id', $student->id)
                    ->orderBy('submission_number')
                    ->get();

                $deadlineDateTime = Carbon::parse($independent->deadline)->setTime($hour, $minute, 0);
                $submissionCount = $submission?->submission_count ?? 0;
                $isOverdue = Carbon::now()->gt($deadlineDateTime);

                // Use mt_grade_history count for accurate resubmission tracking
                $mtHistoryCount = DB::table('mt_grade_history')
                    ->where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $independent->subject_hemis_id)
                    ->where('semester_code', $independent->semester_code)
                    ->count();
                $remainingAttempts = max(0, $mtMaxResubmissions - $mtHistoryCount);
                $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
                $gradeLocked = $grade && $grade->grade >= $studentMinLimit;

                return [
                    'id' => $independent->id,
                    'subject_name' => $independent->subject_name,
                    'teacher_name' => $independent->teacher_short_name ?? $independent->teacher_name,
                    'start_date' => $independent->start_date,
                    'deadline' => $independent->deadline,
                    'is_overdue' => $isOverdue,
                    'submission' => $submission,
                    'grade' => $grade?->grade,
                    'grade_locked' => $gradeLocked,
                    'grade_history' => $gradeHistory,
                    'submission_count' => $submissionCount,
                    'remaining_attempts' => $remainingAttempts,
                    'can_resubmit' => !$gradeLocked && $submission && $grade && $grade->grade < $studentMinLimit && $remainingAttempts > 0 && !$isOverdue,
                    'status' => $independent->status,
                    'file_path' => $independent->file_path,
                    'file_original_name' => $independent->file_original_name,
                ];
            });

        $minimumLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;

        return view('student.independents', compact('independents', 'mtDeadlineTime', 'mtMaxResubmissions', 'minimumLimit'));
    }

    public function submitIndependent(Request $request, $id)
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::guard('student')->user();
        $independent = Independent::where('id', $id)
            ->where('group_hemis_id', $student->group_id)
            ->firstOrFail();

        // YN ga yuborilganligini tekshirish — qulflangan bo'lsa fayl yuklash mumkin emas
        $ynLocked = StudentGrade::where('student_hemis_id', $student->hemis_id)
            ->where('subject_id', $independent->subject_hemis_id)
            ->where('semester_code', $independent->semester_code)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return back()->with('error', 'YN ga yuborilgan. Fayl yuklash mumkin emas.');
        }

        // Check if grade is locked (>= minimum_limit)
        $existingGrade = StudentGrade::where('student_id', $student->id)
            ->where('independent_id', $independent->id)
            ->first();

        $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
        if ($existingGrade && $existingGrade->grade >= $studentMinLimit) {
            return back()->with('error', 'Baho ' . $studentMinLimit . ' va undan yuqori — qayta yuklash mumkin emas.');
        }

        // Check deadline using configured time from settings
        $mtDeadlineTime = Setting::get('mt_deadline_time', '17:00');
        $timeParts = explode(':', $mtDeadlineTime);
        $hour = (int) ($timeParts[0] ?? 17);
        $minute = (int) ($timeParts[1] ?? 0);

        $deadlineTime = Carbon::parse($independent->deadline)->setTime($hour, $minute, 0);
        if (Carbon::now()->gt($deadlineTime)) {
            return back()->with('error', 'Topshiriq muddati tugagan (muddat: ' . $independent->deadline . ' soat ' . $mtDeadlineTime . ')');
        }

        // Check resubmission limit
        $existing = IndependentSubmission::where('independent_id', $independent->id)
            ->where('student_id', $student->id)
            ->first();

        $mtMaxResubmissions = (int) Setting::get('mt_max_resubmissions', 3);

        if ($existing && $existingGrade && $existingGrade->grade < $studentMinLimit) {
            // Use mt_grade_history count for accurate resubmission tracking
            $mtHistoryCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $independent->subject_hemis_id)
                ->where('semester_code', $independent->semester_code)
                ->count();
            $remainingAttempts = $mtMaxResubmissions - $mtHistoryCount;
            if ($remainingAttempts <= 0) {
                return back()->with('error', 'Qayta yuklash imkoniyati tugagan (maksimum ' . $mtMaxResubmissions . ' marta).');
            }
        }

        $allowedExtensions = ['zip', 'doc', 'docx', 'ppt', 'pptx', 'pdf'];

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) use ($allowedExtensions) {
                    $clientExt = strtolower($value->getClientOriginalExtension());
                    $guessedExt = $value->guessExtension();
                    if (!in_array($clientExt, $allowedExtensions) && !in_array($guessedExt, $allowedExtensions)) {
                        $fail('Faqat zip, doc, docx, ppt, pptx, pdf formatdagi fayllar qabul qilinadi');
                    }
                },
            ],
        ], [
            'file.required' => 'Fayl yuklash majburiy',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('independent-submissions/' . $student->hemis_id, 'public');

        // If resubmitting after low grade, archive old grade + old file to mt_grade_history BEFORE overwriting
        if ($existingGrade && $existingGrade->grade < $studentMinLimit && $existing) {
            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $independent->subject_hemis_id)
                ->where('semester_code', $independent->semester_code)
                ->count();

            $now = now();
            // Archive with OLD file path (before overwrite)
            DB::table('mt_grade_history')->insert([
                'student_hemis_id' => $student->hemis_id,
                'subject_id' => $existingGrade->subject_id,
                'semester_code' => $independent->semester_code,
                'attempt_number' => $attemptCount + 1,
                'grade' => $existingGrade->grade,
                'file_path' => $existing->file_path, // eski fayl
                'file_original_name' => $existing->file_original_name,
                'graded_by' => $existingGrade->employee_name ?? 'Admin',
                'graded_at' => $existingGrade->updated_at ?? $existingGrade->created_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Delete old grade from student_grades
            DB::table('student_grades')->where('id', $existingGrade->id)->delete();

            // Do NOT delete old file — keep it for history download
        } elseif ($existing && $existing->file_path && !$existingGrade) {
            // No grade yet, just replacing file — delete old file
            Storage::disk('public')->delete($existing->file_path);
        }

        $newCount = $existing ? $existing->submission_count + 1 : 1;

        IndependentSubmission::updateOrCreate([
            'independent_id' => $independent->id,
            'student_id' => $student->id,
        ], [
            'student_hemis_id' => $student->hemis_id,
            'file_path' => $filePath,
            'file_original_name' => $file->getClientOriginalName(),
            'submitted_at' => now(),
            'submission_count' => $newCount,
            'viewed_at' => null, // Reset viewed status so teacher sees fresh submission
        ]);

        return back()->with('success', 'Fayl muvaffaqiyatli yuklandi');
    }

    public function downloadSubmission($submissionId)
    {
        $student = Auth::guard('student')->user();

        $submission = IndependentSubmission::where('id', $submissionId)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $filePath = storage_path('app/public/' . $submission->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        return response()->download($filePath, $submission->file_original_name);
    }

    public function submitYnConsent(Request $request)
    {
        $student = Auth::guard('student')->user();

        $request->validate([
            'subject_id' => 'required|string',
            'status' => 'required|in:approved,rejected',
        ]);

        $semester = $student->semester_code;

        // YN ga allaqachon yuborilgan bo'lsa, rozilikni o'zgartirish mumkin emas
        $ynSubmission = YnSubmission::where('subject_id', $request->subject_id)
            ->where('semester_code', $semester)
            ->where('group_hemis_id', $student->group_id)
            ->first();

        if ($ynSubmission) {
            return back()->with('error', 'YN ga allaqachon yuborilgan. Rozilikni o\'zgartirish mumkin emas.');
        }

        YnConsent::updateOrCreate(
            [
                'student_hemis_id' => $student->hemis_id,
                'subject_id' => $request->subject_id,
                'semester_code' => $semester,
            ],
            [
                'group_hemis_id' => $student->group_id,
                'status' => $request->status,
                'submitted_at' => now(),
            ]
        );

        $message = $request->status === 'approved'
            ? 'YN topshirishga rozilik muvaffaqiyatli yuborildi.'
            : 'YN topshirishdan rad etish muvaffaqiyatli yuborildi.';

        return back()->with('success', $message);
    }

    public function profile()
    {
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $student = Auth::guard('student')->user();

        $profileData = [
            'full_name' => $student->full_name,
            'student_id_number' => $student->student_id_number,
            'image' => $student->image ?? asset('images/default-avatar.png'),
            'birth_date' => $student->birth_date ? $student->birth_date->timestamp : null,
            'phone' => $student->other['phone'] ?? '',
            'email' => $student->other['email'] ?? '',
            'gender' => ['name' => $student->gender_name ?? ''],
            'faculty' => ['name' => $student->department_name ?? ''],
            'specialty' => ['name' => $student->specialty_name ?? ''],
            'group' => ['name' => $student->group_name ?? ''],
            'level' => ['name' => $student->level_name ?? ''],
            'educationType' => ['name' => $student->education_type_name ?? ''],
            'address' => $student->other['address'] ?? '',
            'province' => ['name' => $student->province_name ?? ''],
            'district' => ['name' => $student->district_name ?? ''],
        ];

        return view('student.profile', compact('profileData'));
    }
}

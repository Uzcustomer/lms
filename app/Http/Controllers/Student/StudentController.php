<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Services\StudentGradeService;

class StudentController extends Controller
{
    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }
    public function dashboard()
    {
        $student = Auth::guard('student')->user();

        $avgGpa = $student->avg_gpa ?? 0;

        $totalAbsent = Attendance::where('student_id', $student->id)->count();

        $debtSubjectsCount = StudentGrade::where('student_id', $student->id)
            ->whereIn('status', ["pending"])
            ->count();

        $recentGrades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        return view('student.dashboard', compact('avgGpa', 'totalAbsent', 'debtSubjectsCount', 'recentGrades'));
    }

    public function getSchedule(Request $request)
    {
        $token = Auth::guard('student')->user()->token;

        $semestersResponse = Http::withoutVerifying()->withToken($token)->get('https://student.ttatf.uz/rest/v1/education/semesters');
        $scheduleResponse = Http::withoutVerifying()->withToken($token)->get('https://student.ttatf.uz/rest/v1/education/schedule');

        if ($semestersResponse->successful() && $scheduleResponse->successful()) {
            $semesters = collect($semestersResponse->json('data'));
            $schedule = collect($scheduleResponse->json('data'));

            $currentSemester = $semesters->firstWhere('current', true);
            $selectedSemesterId = $request->input('semester_id', $currentSemester['id']);
            $selectedSemester = $semesters->firstWhere('id', $selectedSemesterId);

            $semesterSchedule = $schedule->where('semester.code', $selectedSemester['code']);

            $currentDate = Carbon::now();
            $currentWeek = collect($selectedSemester['weeks'])->first(function ($week) use ($currentDate) {
                $startDate = Carbon::createFromTimestamp($week['start_date']);
                $endDate = Carbon::createFromTimestamp($week['end_date']);
                return $currentDate->between($startDate, $endDate);
            });

            if (!$currentWeek) {
                $currentWeek = collect($selectedSemester['weeks'])
                    ->sortBy('start_date')
                    ->first(function ($week) use ($currentDate) {
                        return Carbon::createFromTimestamp($week['start_date'])->isAfter($currentDate);
                    });
            }

            $selectedWeekId = $request->input('week_id', $currentWeek['id'] ?? $selectedSemester['weeks'][0]['id']);

            $groupedSchedule = $semesterSchedule
                ->where('_week', $selectedWeekId)
                ->groupBy(function ($lesson) {
                    return Carbon::createFromTimestamp($lesson['lesson_date'])->format('l');
                })
                ->map(function ($dayLessons) {
                    return $dayLessons
                        ->unique(function ($lesson) {
                            return $lesson['subject']['id'] . $lesson['lessonPair']['start_time'] . $lesson['lessonPair']['end_time'] . (isset($lesson['auditorium']['code'])?$lesson['auditorium']['code']:null) . $lesson['employee']['id'];
                        })
                        ->sortBy('lessonPair.start_time')
                        ->values();
                })
                ->sortKeys();

            $weeks = collect($selectedSemester['weeks'])->sortBy('start_date')->values();

            return view('student.student_schedule', compact('groupedSchedule', 'selectedSemester', 'semesters', 'weeks', 'selectedWeekId'));
        } else {
            return back()->withErrors('Unable to retrieve schedule or semesters.');
        }
    }


    public function getAttendance(Request $request)
    {
        $token = Auth::user()->token;
        $semester = Auth::user()->semester_code;
        $level_code = Auth::user()->level_code;

        $attendanceResponse = Http::withoutVerifying()->withToken($token)->get('https://student.ttatf.uz/rest/v1/education/attendance', [
            'semester' => $semester
        ]);

        if (!$attendanceResponse->successful()) {
            return back()->withErrors('Failed to fetch attendance data.');
        }

        $attendanceData = collect($attendanceResponse->json('data'));

        if ($level_code == 15 || $level_code == 16) {
            $formattedData = $attendanceData->map(function ($item) {
                return [
                    'semester' => $item['semester']['name'],
                    'date' => Carbon::createFromTimestamp($item['lesson_date'])->format('d-m-Y'),
                    'subject' => $item['subject']['name'],
                    'training_type' => $item['trainingType']['name'],
                    'lesson_pair' => $item['lessonPair']['name'],
                    'start_time' => $item['lessonPair']['start_time'],
                    'end_time' => $item['lessonPair']['end_time'],
                    'auditorium' => isset($item['auditorium']['name'])?$item['auditorium']['name']:null,
                    'building' => isset($item['auditorium']['building']['name'])?$item['auditorium']['building']['name']:null,
                    'employee' => $item['employee']['name'],
                    'faculty' => $item['faculty']['name'],
                    'department' => $item['department']['name'],
                    'group' => $item['group']['name'],
                    'education_lang' => $item['group']['educationLang']['name'],
                    'absent_on' => $item['absent_on'] > 0 ? 'Yo‘q' : 'Ha',
                    'hours' => $item['absent_on'] == 0 ? 2 : 0
                ];
            })->sortByDesc('date')->values();
        } else {
            $formattedData = $attendanceData->map(function ($item) {
                return [
                    'semester' => $item['semester']['name'],
                    'date' => Carbon::createFromTimestamp($item['lesson_date'])->format('d-m-Y') . " " . $item['lessonPair']['start_time'],
                    'subject' => $item['subject']['name'],
                    'training_type' => $item['trainingType']['name'],
                    'employee' => $item['employee']['name'],
                    'absent_on' => $item['absent_on'] > 0 ? 'Yo‘q' : 'Ha',
                    'hours' => $item['absent_on'] == 0 ? 2 : 0
                ];
            })->sortByDesc('date')->values();
        }

        return view('student.attendance', [
            'attendanceData' => $formattedData,
            'level_code' => $level_code,
        ]);
    }

    public function getSubjects(Request $request)
    {
        $token = Auth::user()->token;
        $semester = Auth::user()->semester_code;
        $semester_name = Auth::user()->semester_name;
        $student_id = Auth::id();

        $subjectResponse = Http::withoutVerifying()->withToken($token)->get('https://student.ttatf.uz/rest/v1/education/subject-list', [
            'semester' => $semester
        ]);

        if (!$subjectResponse->successful()) {
            return back()->withErrors('Failed to fetch subjects data.');
        }

        $currentDate = Carbon::now();
        $student = Student::where('id', $student_id)->first();
        $subjects = collect($subjectResponse->json('data'))->map(function ($subject) use ($semester, $student_id, $currentDate, $student) {
            $overallScore = $subject['overallScore'] ?? null;
            $gradesByExam = collect($subject['gradesByExam'] ?? []);

            $subject_id = $subject['curriculumSubject']['subject']['id'];
            // whereHas('studentGrades')->
            $lessonDates = Schedule::where('subject_id', $subject_id)
                    ->where('group_id', $student->group_id)
                    ->where('semester_code', $semester)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->where('lesson_date', '<=', $currentDate)
                    ->distinct('lesson_date')
                    ->pluck('lesson_date')
                    ->map(function ($date) {
                        return Carbon::parse($date);
                    })->unique()->sort();
                $dates = $lessonDates;
                $startDate = $dates->first();
                $endDate = $dates->last();
            $grades = StudentGrade::where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $subject_id)
                ->whereNotIn('training_type_code', config('app.training_type_code'))
                ->whereBetween('lesson_date', [$startDate, $endDate])
                ->get();
            $overallAverageGrade = 0;
            $gradeInfo = $this->studentGradeService->computeAverageGrade($grades, $semester) ?? [
                'average' => null,
                'days' => 0,
            ];
            if ($gradeInfo['average'] !== null){
                $overallAverageGrade = $gradeInfo['average'];
            }
            $finalExam = $gradesByExam->firstWhere('examType.code', '13');
            $currentExam = $gradesByExam->firstWhere('examType.code', '11');

            return [
                'name' => $subject['curriculumSubject']['subject']['name'],
                'code' => $subject['curriculumSubject']['subject']['code'],
                'total_acload' => $subject['curriculumSubject']['total_acload'],
                'credit' => $subject['curriculumSubject']['credit'],
                'subject_type' => $subject['curriculumSubject']['subjectType']['name'],
                'subject_id' => $subject['curriculumSubject']['subject']['id'],
                'overall_score' => $overallScore ? "{$overallScore['grade']} / {$overallScore['max_ball']}" : 'Aniqlanmagan',
                'final_exam' => $finalExam ? "{$finalExam['grade']} / {$finalExam['max_ball']}" : 'Aniqlanmagan',
                'current_exam' => $currentExam ? "{$currentExam['grade']} / {$currentExam['max_ball']}" : 'Aniqlanmagan',
                'average_grade' => round($overallAverageGrade),
            ];
        });

        return view('student.subjects', ['subjects' => $subjects, 'semester' => $semester_name]);
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
        $student = Auth::user();
        $semester = $student->semester_code;

        $grades = StudentGrade::where('student_id', $student->id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semester)
//            ->where('training_type_code', "<>", 11)
            ->orderBy('lesson_date', 'desc')
            ->get();

        return view('student.subject-grades', ['grades' => $grades]);
    }

    public function getPendingLessons()
    {
        $pendingLessons = StudentGrade::where('student_id', auth()->id())
            ->whereIn('status', ['pending', 'retake'])
//            ->where('training_type_code', '<>', 11)
            ->orderBy('lesson_date')
            ->get();

        return view('student.pending-lessons', compact('pendingLessons'));
    }

    public function profile()
    {
        $token = Auth::guard('student')->user()->token;

        $response = Http::withoutVerifying()->withToken($token)->get('https://student.ttatf.uz/rest/v1/account/me');

        if ($response->successful()) {
            $profileData = $response->json()['data'];
            return view('student.profile', compact('profileData'));
        }

        return back()->withErrors('Failed to fetch profile data.');
    }
}

<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CurriculumSubject;
use App\Models\CurriculumWeek;
use App\Models\Group;
use App\Models\Independent;
use App\Models\IndependentSubmission;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Services\StudentGradeService;

class StudentController extends Controller
{
    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }

    private function redirectIfPasswordChangeRequired()
    {
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
        $semester = $student->semester_code;
        $semester_name = $student->semester_name;
        $currentDate = Carbon::now();

        $curriculumSubjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $semester)
            ->get();

        $subjects = $curriculumSubjects->map(function ($cs) use ($semester, $currentDate, $student) {
            $subject_id = $cs->subject_id;

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

            $startDate = $lessonDates->first();
            $endDate = $lessonDates->last();

            $overallAverageGrade = 0;
            if ($startDate && $endDate) {
                $grades = StudentGrade::where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $subject_id)
                    ->whereNotIn('training_type_code', config('app.training_type_code'))
                    ->whereBetween('lesson_date', [$startDate, $endDate])
                    ->get();

                $gradeInfo = $this->studentGradeService->computeAverageGrade($grades, $semester) ?? [
                    'average' => null,
                    'days' => 0,
                ];
                if ($gradeInfo['average'] !== null) {
                    $overallAverageGrade = $gradeInfo['average'];
                }
            }

            $finalExamGrade = StudentGrade::where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $subject_id)
                ->where('semester_code', $semester)
                ->where('training_type_code', 102)
                ->avg('grade');

            $currentExamGrade = StudentGrade::where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $subject_id)
                ->where('semester_code', $semester)
                ->where('training_type_code', 100)
                ->avg('grade');

            return [
                'name' => $cs->subject_name,
                'code' => $cs->subject_code,
                'total_acload' => $cs->total_acload,
                'credit' => $cs->credit,
                'subject_type' => $cs->subject_type_name,
                'subject_id' => $cs->subject_id,
                'overall_score' => 'Aniqlanmagan',
                'final_exam' => $finalExamGrade ? round($finalExamGrade) : 'Aniqlanmagan',
                'current_exam' => $currentExamGrade ? round($currentExamGrade) : 'Aniqlanmagan',
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
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

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
        if ($redirect = $this->redirectIfPasswordChangeRequired()) {
            return $redirect;
        }

        $pendingLessons = StudentGrade::where('student_id', auth()->id())
            ->whereIn('status', ['pending', 'retake'])
//            ->where('training_type_code', '<>', 11)
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

        $independents = Independent::where('group_hemis_id', $student->group_id)
            ->orderBy('deadline', 'asc')
            ->get()
            ->map(function ($independent) use ($student) {
                $submission = $independent->submissionByStudent($student->id);
                $grade = StudentGrade::where('student_id', $student->id)
                    ->where('independent_id', $independent->id)
                    ->first();

                return [
                    'id' => $independent->id,
                    'subject_name' => $independent->subject_name,
                    'teacher_name' => $independent->teacher_short_name ?? $independent->teacher_name,
                    'start_date' => $independent->start_date,
                    'deadline' => $independent->deadline,
                    'is_overdue' => Carbon::parse($independent->deadline)->endOfDay()->isPast(),
                    'submission' => $submission,
                    'grade' => $grade?->grade,
                    'status' => $independent->status,
                    'file_path' => $independent->file_path,
                    'file_original_name' => $independent->file_original_name,
                ];
            });

        return view('student.independents', compact('independents'));
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

        // Check deadline (17:00 on deadline date)
        $deadlineTime = Carbon::parse($independent->deadline)->setTime(17, 0, 0);
        if (Carbon::now()->gt($deadlineTime)) {
            return back()->with('error', 'Topshiriq muddati tugagan (muddat: ' . $independent->deadline . ' soat 17:00)');
        }

        $request->validate([
            'file' => 'required|file|max:2048|mimes:zip,doc,docx,ppt,pptx,pdf',
        ], [
            'file.required' => 'Fayl yuklash majburiy',
            'file.max' => 'Fayl hajmi 2MB dan oshmasligi kerak',
            'file.mimes' => 'Faqat zip, doc, docx, ppt, pptx, pdf formatdagi fayllar qabul qilinadi',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('independent-submissions/' . $student->hemis_id, 'public');

        // Delete old file if resubmitting
        $existing = IndependentSubmission::where('independent_id', $independent->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing && $existing->file_path) {
            Storage::disk('public')->delete($existing->file_path);
        }

        IndependentSubmission::updateOrCreate([
            'independent_id' => $independent->id,
            'student_id' => $student->id,
        ], [
            'student_hemis_id' => $student->hemis_id,
            'file_path' => $filePath,
            'file_original_name' => $file->getClientOriginalName(),
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Fayl muvaffaqiyatli yuklandi');
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

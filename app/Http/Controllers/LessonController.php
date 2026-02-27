<?php

namespace App\Http\Controllers;

use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\LessonHistory;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function index()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $lastFilters = session('last_lesson_filters', []);

        return view('admin.lessons.create', compact('departments', 'lastFilters'));
    }

    public function historyIndex(Request $request)
    {
        $query = LessonHistory::with(['user'])->latest();

        if ($request->filled('teacher')) {
            $query->where('teacher_name', 'like', '%' . $request->teacher . '%');
        }

        if ($request->filled('group')) {
            $query->where(function ($q) use ($request) {
                $q->where('group_name', 'like', '%' . $request->group . '%')
                    ->orWhere('student_name', 'like', '%' . $request->group . '%');
            });
        }

        $perPage = $request->get('per_page', 20);
        if (!in_array($perPage, [10, 25, 50, 100])) {
            $perPage = 50;
        }

        $histories = $query->paginate($perPage)
            ->appends($request->query());

        return view('admin.lessons.history-index', compact('histories'));
    }

    public function getGroups(Request $request)
    {
        $groups = Group::where('department_hemis_id', $request->department_id)
            ->select('group_hemis_id as id', 'name')
            ->get();
        return response()->json($groups);
    }
    public function getGroups_semester(Request $request)
    {
        $groups = Group::join('semesters', function ($join) use ($request) {
            $join->on('groups.curriculum_hemis_id', '=', 'semesters.curriculum_hemis_id')
                ->where('semesters.code', $request->semester_id);
        })
            ->where('groups.department_hemis_id', $request->department_id)
            ->select('groups.group_hemis_id as id', 'groups.name')
            ->get();
        return response()->json($groups);
    }

    public function getStudents(Request $request)
    {
        $students = Student::where('group_id', $request->group_id)
            ->select('id', 'full_name as name', 'student_id_number', 'hemis_id')
            ->get();
        return response()->json($students);
    }

    public function getSemesters(Request $request)
    {
        $semesters = Schedule::where('group_id', $request->group_id)
            ->select('semester_code as id', 'semester_name as name')
            ->distinct()
            ->get();

        return response()->json($semesters);
    }


    public function getSubjects(Request $request)
    {
        $subjects = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
        ])
            ->select('subject_id as id', 'subject_name as name')
            ->groupBy('subject_id', 'subject_name')
            ->get();

        return response()->json($subjects);
    }

    public function getTrainingTypes(Request $request)
    {
        $trainingTypes = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
            'subject_id' => $request->subject_id,
        ])
            ->select('training_type_code as id', 'training_type_name as name')
            ->groupBy('training_type_code', 'training_type_name')
            ->get();

        return response()->json($trainingTypes);
    }

    public function getTeacher(Request $request)
    {
        $teachers = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
            'subject_id' => $request->subject_id,
        ])
            ->where('training_type_code', '!=', 11)
            ->select(
                'employee_id as id',
                DB::raw("CONCAT(employee_name, ' (', COUNT(employee_id), ')') as name")
            )
            ->groupBy('employee_id', 'employee_name')
            ->get();

        return response()->json($teachers);
    }

    public function getScheduleDates(Request $request)
    {
        $dates = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
            'subject_id' => $request->subject_id,
            'training_type_code' => $request->training_type_code,
        ])
            ->where('lesson_date', '<', Carbon::now())
            ->select('lesson_date as ldate')
            ->distinct()
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->ldate,
                    'name' => Carbon::parse($item->ldate)->format('d-m-Y')
                ];
            });

        return response()->json($dates);
    }

    public function getLessonPairs(Request $request)
    {
        $pairs = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
            'subject_id' => $request->subject_id,
            'training_type_code' => $request->training_type_code,
            'lesson_date' => $request->date,
        ])
            ->select('lesson_pair_code as id', 'lesson_pair_name as name', 'schedule_hemis_id')
            ->get();

        return response()->json($pairs);
    }


    public function getFilteredStudents(Request $request)
    {
        $students = Student::where('students.group_id', $request->group_id)
            ->whereNotExists(function ($query) use ($request) {
                $query->select(DB::raw(1))
                    ->from('student_grades')
                    ->join('schedules', function ($join) use ($request) {
                        $join->on('student_grades.subject_schedule_id', '=', 'schedules.schedule_hemis_id')
                            ->where([
                                'schedules.group_id' => $request->group_id,
                                'schedules.semester_code' => $request->semester_code,
                                'schedules.subject_id' => $request->subject_id,
                                'schedules.training_type_code' => $request->training_type_code,
                                'schedules.lesson_date' => $request->date,
                            ])
                            ->whereIn('schedules.schedule_hemis_id', $request->schedule_hemis_ids);
                    })
                    ->whereRaw('student_grades.student_hemis_id = students.hemis_id');
            })
            ->select('students.id', 'students.full_name as name', 'students.student_id_number', 'students.hemis_id')
            ->get();

        return response()->json($students);
    }

    public function getSubjectTeacher(Request $request)
    {
        $schedule = Schedule::where([
            'group_id' => $request->group_id,
            'semester_code' => $request->semester_code,
            'subject_id' => $request->subject_id,
            'training_type_code' => $request->training_type_code,
        ])->first();

        if ($schedule) {
            return response()->json(['employee_name' => $schedule->employee_name]);
        } else {
            return response()->json(['employee_name' => null]);
        }
    }


    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $filePath = null;
            $fileOriginalName = null;
            if ($request->hasFile('lesson_file')) {
                $file = $request->file('lesson_file');
                $filePath = $file->store('lesson-files', 'public');
                $fileOriginalName = $file->getClientOriginalName();
            }

            if (!$request->has('schedule_hemis_ids') || empty($request->schedule_hemis_ids)) {
                throw new \Exception('Kamida bitta dars tanlanishi kerak');
            }

            $firstSchedule = Schedule::where('schedule_hemis_id', $request->schedule_hemis_ids[0])
                ->firstOrFail();

            $studentGradeIds = [];

            if (!$request->has('student_hemis_ids') || in_array('all', $request->student_hemis_ids)) {
                $students = Student::where('group_id', $request->group_id)->get();
            } else {
                $students = Student::whereIn('hemis_id', $request->student_hemis_ids)->get();
            }

            foreach ($request->schedule_hemis_ids as $scheduleId) {
                $existingGrades = StudentGrade::where('subject_schedule_id', $scheduleId)
                    ->whereIn('student_hemis_id', $students->pluck('hemis_id'))
                    ->exists();

                if ($existingGrades) {
                    $schedule = Schedule::where('schedule_hemis_id', $scheduleId)->first();
                    throw new \Exception("Ba'zi tanlangan talabalar uchun $schedule->lesson_date kunidagi $schedule->lesson_pair_name bo'yicha dars allaqachon yaratilgan");
                }
            }

            foreach ($request->schedule_hemis_ids as $scheduleId) {
                $schedule = Schedule::where('schedule_hemis_id', $scheduleId)->firstOrFail();

                foreach ($students as $student) {
                    $grade = $this->createStudentGrade($student, $schedule);
                    $studentGradeIds[] = $grade->id;
                }
            }

            LessonHistory::createFromSchedule(
                $request,
                $firstSchedule,
                $filePath,
                $fileOriginalName,
                $studentGradeIds
            );

            session([
                'last_lesson_filters' => [
                    'department_id' => $request->department_id,
                    'group_id' => $request->group_id,
                    'semester_code' => $request->semester_code,
                    'subject_id' => $request->subject_id,
                    'training_type_code' => $request->training_type_code
                ]
            ]);

            DB::commit();
            return back()->with('success', 'Dars muvaffaqiyatli yaratildi');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->with('error', 'Ma\'lumot topilmadi');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }


    private function createStudentGrade($student, $schedule)
    {
        return StudentGrade::create([
            'hemis_id' => 77777777,
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'semester_code' => $schedule->semester_code,
            'semester_name' => $schedule->semester_name,
            'subject_schedule_id' => $schedule->schedule_hemis_id,
            'subject_id' => $schedule->subject_id,
            'subject_name' => $schedule->subject_name,
            'subject_code' => $schedule->subject_code,
            'training_type_code' => $schedule->training_type_code,
            'training_type_name' => $schedule->training_type_name,
            'employee_id' => $schedule->employee_id,
            'employee_name' => $schedule->employee_name,
            'lesson_pair_name' => $schedule->lesson_pair_name,
            'lesson_pair_code' => $schedule->lesson_pair_code,
            'lesson_pair_start_time' => $schedule->lesson_pair_start_time,
            'lesson_pair_end_time' => $schedule->lesson_pair_end_time,
            'lesson_date' => $schedule->lesson_date,
            'created_at_api' => $schedule->created_at,
            'reason' => 'teacher_victim',
            'status' => 'pending',
            'grade' => 0,
            'deadline' => Carbon::now()->addWeek()->endOfDay(),
            'is_final' => true,
        ]);
    }


    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $lessonHistory = LessonHistory::findOrFail($id);

            if (!empty($lessonHistory->student_grade_ids)) {
                StudentGrade::whereIn('id', $lessonHistory->student_grade_ids)->delete();
            }

            if ($lessonHistory->file_path) {
                Storage::disk('public')->delete($lessonHistory->file_path);
            }

            $lessonHistory->delete();

            DB::commit();
            return back()->with('success', 'Dars muvaffaqiyatli o\'chirildi');

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Dars o\'chirishda xatolik: ' . $e->getMessage());
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
}
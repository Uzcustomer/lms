<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Specialty;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        // Get filter options for dropdowns
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            $selectedEducationType = $educationTypes
                ->first(function ($type) {
                    return str_contains(mb_strtolower($type->education_type_name ?? ''), 'bakalavr');
                })
                ?->education_type_code;
        }

        $educationYears = Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->get();

        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Base query builder (umumiy join va filtrlar)
        $baseQuery = function () {
            return DB::table('curriculum_subjects as cs')
                ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
                ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                ->join('semesters as s', function ($join) {
                    $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                        ->on('s.code', '=', 'cs.semester_code');
                })
                ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
                ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
                ->where('g.department_active', true)
                ->where('g.active', true);
        };

        // Kafedra dropdown uchun - faqat haqiqiy natija bor kafedralar
        $kafedraQuery = $baseQuery()
            ->whereNotNull('cs.department_id')
            ->whereNotNull('cs.department_name');

        if ($selectedEducationType) {
            $kafedraQuery->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('education_year')) {
            $kafedraQuery->where('c.education_year_code', $request->education_year);
        }
        if ($request->filled('faculty')) {
            $kafedraQuery->where('f.id', $request->faculty);
        }
        if ($request->get('current_semester', '1') == '1') {
            $kafedraQuery->where('s.current', true);
        }

        $kafedras = $kafedraQuery
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        // Build query for journal entries
        $query = $baseQuery()
            ->select([
                'cs.id',
                'cs.subject_id',
                'cs.subject_name',
                'cs.semester_code',
                'cs.semester_name',
                'c.education_type_name',
                'c.education_year_name',
                'g.id as group_id',
                'g.name as group_name',
                'cs.department_name as kafedra_name',
                'f.name as faculty_name',
                'sp.name as specialty_name',
                's.level_name',
            ])
            ->distinct();

        // Apply filters
        if ($selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }

        if ($request->filled('education_year')) {
            $query->where('c.education_year_code', $request->education_year);
        }

        if ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
        }

        if ($request->filled('department')) {
            $query->where('cs.department_id', $request->department);
        }

        if ($request->filled('specialty')) {
            $query->where('sp.specialty_hemis_id', $request->specialty);
        }

        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }

        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }

        if ($request->filled('subject')) {
            $query->where('cs.subject_id', $request->subject);
        }

        if ($request->filled('group')) {
            $query->where('g.id', $request->group);
        }

        // Joriy semestr filtri (default ON)
        if ($request->get('current_semester', '1') == '1') {
            $query->where('s.current', true);
        }

        // Sorting
        $sortColumn = $request->get('sort', 'group_name');
        $sortDirection = $request->get('direction', 'asc');

        // Map sort columns to actual database columns
        $sortMap = [
            'education_type' => 'c.education_type_name',
            'education_year' => 'c.education_year_name',
            'faculty' => 'f.name',
            'department' => 'cs.department_name',
            'specialty' => 'sp.name',
            'level' => 's.level_name',
            'semester' => 'cs.semester_name',
            'subject' => 'cs.subject_name',
            'group_name' => 'g.name',
        ];

        $orderByColumn = $sortMap[$sortColumn] ?? 'g.name';
        $query->orderBy($orderByColumn, $sortDirection);

        $perPage = $request->get('per_page', 50);
        $journals = $query->paginate($perPage)->appends($request->query());

        return view('admin.journal.index', compact(
            'journals',
            'educationTypes',
            'selectedEducationType',
            'educationYears',
            'faculties',
            'kafedras',
            'sortColumn',
            'sortDirection'
        ));
    }

    public function show(Request $request, $groupId, $subjectId, $semesterCode)
    {
        $group = Group::find($groupId);
        if (!$group) {
            abort(404, "Guruh topilmadi (ID: {$groupId})");
        }

        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->first();
        if (!$subject) {
            abort(404, "Fan topilmadi (subject_id: {$subjectId}, semester: {$semesterCode})");
        }

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        // Current education year: determine from schedules (most reliable source)
        // Pick the education_year_code that has the latest lesson_date for this group/subject/semester
        $educationYearCode = $curriculum?->education_year_code;
        $scheduleEducationYear = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->value('education_year_code');
        if ($scheduleEducationYear) {
            $educationYearCode = $scheduleEducationYear;
        }

        // Get student hemis IDs for this group
        $studentHemisIds = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->where('is_graduate', false)
            ->pluck('hemis_id');

        // Excluded training type names for Amaliyot (JB)
        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test"];
        // Excluded training type codes: 11=Ma'ruza, 99=MT, 100=ON, 101=Oski, 102=Test
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Calendar source: get scheduled lesson date/pair columns for JB and MT
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $lectureScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select(DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Get sessions from attendance_controls (authoritative source for classes that actually happened)
        $lectureControlRows = DB::table('attendance_controls')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select(DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Determine earliest schedule date for filtering old year grades
        $minScheduleDate = collect()
            ->merge($jbScheduleRows->pluck('lesson_date'))
            ->merge($mtScheduleRows->pluck('lesson_date'))
            ->merge($lectureScheduleRows->pluck('lesson_date'))
            ->min();

        // Data source: get all JB grades with lesson_pair info and status fields
        // Filter by education_year_code to exclude grades from previous education years
        // Fall back to minScheduleDate for legacy records without education_year_code
        $jbGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('id', 'student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Get all MT grades with lesson_pair info and status fields
        $mtGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('id', 'student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Helper function to get effective grade based on status
        // Returns array: ['grade' => value, 'is_retake' => bool] or null
        $getEffectiveGrade = function ($row) {
            // status = pending → null (skip)
            if ($row->status === 'pending') {
                return null;
            }
            // Absent entries with no actual grade: use retake_grade if available, otherwise null (show as NB)
            if ($row->reason === 'absent' && $row->grade === null) {
                if ($row->retake_grade !== null) {
                    return ['grade' => $row->retake_grade, 'is_retake' => true];
                }
                return null;
            }
            // status = closed AND reason = teacher_victim AND grade == 0 AND retake_grade === null → null
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            // status = recorded → use grade
            if ($row->status === 'recorded') {
                return ['grade' => $row->grade, 'is_retake' => false];
            }
            // status = closed (except teacher_victim case above) → use grade
            if ($row->status === 'closed') {
                return ['grade' => $row->grade, 'is_retake' => false];
            }
            // All other statuses → use retake_grade
            if ($row->retake_grade !== null) {
                return ['grade' => $row->retake_grade, 'is_retake' => true];
            }
            return null;
        };

        // Build unique date+pair columns for detailed view from schedules (calendar source).
        // Merge grade date+pair as fallback for old data where schedule rows might be missing.
        $jbColumns = $jbScheduleRows->map(function ($schedule) {
            return ['date' => $schedule->lesson_date, 'pair' => $schedule->lesson_pair_code];
        })->merge($jbGradesRaw->map(function ($grade) {
            return ['date' => $grade->lesson_date, 'pair' => $grade->lesson_pair_code];
        }))->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->sort(function ($a, $b) {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']);
        })->values()->toArray();

        // Build unique date+pair columns for detailed view from schedules (calendar source).
        // Merge grade date+pair as fallback for old data where schedule rows might be missing.
        $mtColumns = $mtScheduleRows->map(function ($schedule) {
            return ['date' => $schedule->lesson_date, 'pair' => $schedule->lesson_pair_code];
        })->merge($mtGradesRaw->map(function ($grade) {
            return ['date' => $grade->lesson_date, 'pair' => $grade->lesson_pair_code];
        }))->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->sort(function ($a, $b) {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']);
        })->values()->toArray();

        // Get distinct dates for compact view from schedules (calendar source).
        // Merge grade dates as a fallback for old data where schedule rows might be missing.
        $jbLessonDates = $jbScheduleRows
            ->pluck('lesson_date')
            ->merge($jbGradesRaw->pluck('lesson_date'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $mtLessonDates = $mtScheduleRows
            ->pluck('lesson_date')
            ->merge($mtGradesRaw->pluck('lesson_date'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $lectureColumns = $lectureScheduleRows->map(function ($schedule) {
            return ['date' => $schedule->lesson_date, 'pair' => $schedule->lesson_pair_code];
        })->merge($lectureControlRows->map(function ($row) {
            return ['date' => $row->lesson_date, 'pair' => $row->lesson_pair_code];
        }))->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->sort(function ($a, $b) {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']);
        })->values()->toArray();

        $lectureLessonDates = collect($lectureColumns)
            ->pluck('date')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Count pairs per day for JB (for correct daily average calculation)
        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            if (!isset($jbPairsPerDay[$col['date']])) {
                $jbPairsPerDay[$col['date']] = 0;
            }
            $jbPairsPerDay[$col['date']]++;
        }

        // Count pairs per day for MT
        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            if (!isset($mtPairsPerDay[$col['date']])) {
                $mtPairsPerDay[$col['date']] = 0;
            }
            $mtPairsPerDay[$col['date']]++;
        }

        // Build grades data structure: student_hemis_id => date => pair => ['grade' => value, 'is_retake' => bool, 'id' => id, 'status' => status, 'retake_grade' => retake_grade, 'reason' => reason]
        $jbGrades = [];
        foreach ($jbGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $jbGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = array_merge($effectiveGrade, [
                    'id' => $g->id,
                    'status' => $g->status,
                    'retake_grade' => $g->retake_grade,
                    'reason' => $g->reason,
                    'original_grade' => $g->grade
                ]);
            }
        }

        $mtGrades = [];
        foreach ($mtGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $mtGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = array_merge($effectiveGrade, [
                    'id' => $g->id,
                    'status' => $g->status,
                    'retake_grade' => $g->retake_grade,
                    'reason' => $g->reason,
                    'original_grade' => $g->grade
                ]);
            }
        }

        // Track absence markers (NB) separately from grades:
        // - absent row (any status) => NB if no effective grade exists
        // - real numeric grade (including retake) still has priority in cell rendering
        $jbAbsences = [];
        foreach ($jbGradesRaw as $g) {
            if ($g->reason === 'absent') {
                $jbAbsences[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'id' => $g->id,
                    'retake_grade' => $g->retake_grade
                ];
            }
        }

        $mtAbsences = [];
        foreach ($mtGradesRaw as $g) {
            if ($g->reason === 'absent') {
                $mtAbsences[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'id' => $g->id,
                    'retake_grade' => $g->retake_grade
                ];
            }
        }

        $lectureAttendanceRaw = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 11)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', DB::raw('DATE(lesson_date) as lesson_date'), 'lesson_pair_code', 'absent_on', 'absent_off')
            ->get();

        $lectureAttendance = [];
        $lectureMarkedPairs = [];
        foreach ($lectureAttendanceRaw as $row) {
            $lectureMarkedPairs[$row->lesson_date][$row->lesson_pair_code] = true;

            $isAbsent = ((int) $row->absent_on) > 0 || ((int) $row->absent_off) > 0;
            $status = $isAbsent ? 'NB' : '+';
            $existing = $lectureAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] ?? null;
            if ($existing === 'NB') {
                continue;
            }
            $lectureAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] = $status;
        }

        // Mark all sessions from attendance_controls as "happened"
        foreach ($lectureControlRows as $row) {
            $lectureMarkedPairs[$row->lesson_date][$row->lesson_pair_code] = true;
        }

        // Students without attendance records for attendance_control sessions → + (present)
        // Dars o'tilgan (load bor), NB yozilmagan → darsda qatnashgan
        foreach ($studentHemisIds as $studentHemisId) {
            foreach ($lectureControlRows as $row) {
                if (!isset($lectureAttendance[$studentHemisId][$row->lesson_date][$row->lesson_pair_code])) {
                    $lectureAttendance[$studentHemisId][$row->lesson_date][$row->lesson_pair_code] = '+';
                }
            }
        }

        // Get JB (Amaliyot) attendance to check for excused absences
        $jbAttendanceRaw = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'absent_on')
            ->get();

        $jbAttendance = [];
        foreach ($jbAttendanceRaw as $row) {
            $jbAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] = [
                'absent_on' => $row->absent_on
            ];
        }

        // Get MT (Mustaqil ta'lim) attendance to check for excused absences
        $mtAttendanceRaw = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'absent_on')
            ->get();

        $mtAttendance = [];
        foreach ($mtAttendanceRaw as $row) {
            $mtAttendance[$row->student_hemis_id][$row->lesson_date][$row->lesson_pair_code] = [
                'absent_on' => $row->absent_on
            ];
        }

        // Get students basic info
        $students = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->where('is_graduate', false)
            ->select('id', 'hemis_id', 'full_name', 'student_id_number')
            ->orderBy('full_name')
            ->get();

        // Get other averages (ON, OSKI, Test) with status-based grade calculation
        // Filter by education_year_code to exclude old education year data
        $otherGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('training_type_code', [100, 101, 102])
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where(function ($q5) use ($minScheduleDate) {
                                $q5->where('lesson_date', '>=', $minScheduleDate)->orWhereNull('lesson_date');
                            }));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where(function ($q2) use ($minScheduleDate) {
                $q2->where('lesson_date', '>=', $minScheduleDate)->orWhereNull('lesson_date');
            }))
            ->select('student_hemis_id', 'training_type_code', 'grade', 'retake_grade', 'status', 'reason')
            ->get();

        $otherGrades = [];
        foreach ($otherGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $otherGrades[$g->student_hemis_id][$g->training_type_code][] = $effectiveGrade['grade'];
            }
        }
        // Calculate averages
        foreach ($otherGrades as $studentId => $types) {
            $result = ['on' => null, 'oski' => null, 'test' => null];
            if (isset($types[100]) && count($types[100]) > 0) {
                $result['on'] = array_sum($types[100]) / count($types[100]);
            }
            if (isset($types[101]) && count($types[101]) > 0) {
                $result['oski'] = array_sum($types[101]) / count($types[101]);
            }
            if (isset($types[102]) && count($types[102]) > 0) {
                $result['test'] = array_sum($types[102]) / count($types[102]);
            }
            $otherGrades[$studentId] = $result;
        }

        // Get attendance data for each student (auditorium types only: exclude MT, ON, OSKI, Test)
        $excludedAttendanceCodes = [99, 100, 101, 102];
        $attendanceData = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_code', $excludedAttendanceCodes)
            ->select('student_hemis_id', DB::raw('SUM(absent_off) as total_absent_off'))
            ->groupBy('student_hemis_id')
            ->pluck('total_absent_off', 'student_hemis_id')
            ->toArray();

        // Get manual MT grades (entries without lesson_date)
        $manualMtGrades = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhereNull('education_year_code');
            }))
            ->pluck('grade', 'student_hemis_id')
            ->toArray();

        // Get total academic load from curriculum subject
        $totalAcload = $subject->total_acload ?? 0;

        // Calculate auditorium hours from subject_details
        // Exclude: 17=Mustaqil ta'lim (auditoriya soatiga kirmaydi)
        $nonAuditoriumCodes = ['17'];
        $auditoriumHours = 0;
        if (is_array($subject->subject_details)) {
            foreach ($subject->subject_details as $detail) {
                $trainingCode = (string) ($detail['trainingType']['code'] ?? '');
                if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                    $auditoriumHours += (float) ($detail['academic_load'] ?? 0);
                }
            }
        }
        if ($auditoriumHours <= 0) {
            $auditoriumHours = $totalAcload;
        }

        // Faculty (Fakultet) - department linked to curriculum
        $faculty = Department::where('department_hemis_id', $curriculum?->department_hemis_id)->first();
        $facultyName = $faculty->name ?? '';
        $facultyId = $faculty->id ?? '';

        // Yo'nalish (Specialty) - from group
        $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();
        $specialtyId = $specialty->specialty_hemis_id ?? '';
        $specialtyName = $specialty->name ?? '';

        // Kafedra - from curriculum subject
        $kafedraName = $subject->department_name ?? '';
        $kafedraId = $subject->department_id ?? '';

        // Kurs (Course level) - from semester
        $kursName = $semester?->level_name ?? '';
        $levelCode = $semester?->level_code ?? '';

        // O'qituvchi (Teacher) - jadvaldan tur bo'yicha ajratilgan, soatlari bilan
        $teacherData = $this->getTeachersByType($group->group_hemis_id, $subjectId, $semesterCode);
        $lectureTeacher = $teacherData['lecture_teacher'];
        $practiceTeachers = $teacherData['practice_teachers'];
        $teacherName = $lectureTeacher['name'] ?? ($practiceTeachers[0]['name'] ?? '');

        return view('admin.journal.show', compact(
            'group',
            'subject',
            'curriculum',
            'semester',
            'students',
            'facultyName',
            'facultyId',
            'specialtyId',
            'specialtyName',
            'kafedraName',
            'kafedraId',
            'kursName',
            'levelCode',
            'teacherName',
            'lectureTeacher',
            'practiceTeachers',
            'lectureLessonDates',
            'lectureColumns',
            'lectureAttendance',
            'lectureMarkedPairs',
            'jbLessonDates',
            'mtLessonDates',
            'jbGrades',
            'mtGrades',
            'jbAbsences',
            'mtAbsences',
            'jbAttendance',
            'mtAttendance',
            'jbColumns',
            'mtColumns',
            'jbPairsPerDay',
            'mtPairsPerDay',
            'otherGrades',
            'attendanceData',
            'manualMtGrades',
            'totalAcload',
            'auditoriumHours',
            'groupId',
            'subjectId',
            'semesterCode'
        ));
    }

    /**
     * Save manual MT grade for a student
     */
    public function saveMtGrade(Request $request)
    {
        $request->validate([
            'student_hemis_id' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $studentHemisId = $request->student_hemis_id;
        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $grade = $request->grade;

        // Get student info
        $student = DB::table('students')
            ->where('hemis_id', $studentHemisId)
            ->first();

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        // Get subject info
        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found'], 404);
        }

        // Check if a manual MT grade already exists for this student/subject/semester
        $existingGrade = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->first();

        $now = now();

        // Get education year from student's curriculum
        $curriculum = DB::table('curricula')
            ->where('curricula_hemis_id', $student->curriculum_id ?? null)
            ->first();
        $educationYearCode = $curriculum->education_year_code ?? null;
        $educationYearName = $curriculum->education_year_name ?? null;

        if ($existingGrade) {
            // Update existing grade
            DB::table('student_grades')
                ->where('id', $existingGrade->id)
                ->update([
                    'grade' => $grade,
                    'updated_at' => $now,
                ]);
        } else {
            // Insert new manual grade
            DB::table('student_grades')->insert([
                'hemis_id' => 0, // Manual entry
                'student_id' => $student->id,
                'student_hemis_id' => $studentHemisId,
                'semester_code' => $semesterCode,
                'semester_name' => $subject->semester_name ?? '',
                'education_year_code' => $educationYearCode,
                'education_year_name' => $educationYearName,
                'subject_schedule_id' => 0,
                'subject_id' => $subjectId,
                'subject_name' => $subject->subject_name ?? '',
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => 99, // MT
                'training_type_name' => "Mustaqil ta'lim",
                'employee_id' => 0,
                'employee_name' => 'Manual Entry',
                'lesson_pair_code' => '1',
                'lesson_pair_name' => 'Manual',
                'lesson_pair_start_time' => '00:00',
                'lesson_pair_end_time' => '00:00',
                'grade' => $grade,
                'lesson_date' => null, // Manual entries have no lesson date
                'created_at_api' => $now,
                'status' => 'recorded',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Grade saved successfully']);
    }

    /**
     * Save retake grade for a student from journal
     */
    public function saveRetakeGrade(Request $request)
    {
        // Check admin role
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'grade_id' => 'required|integer',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $gradeId = $request->grade_id;
        $enteredGrade = $request->grade;

        // Get the student grade record
        $studentGrade = DB::table('student_grades')
            ->where('id', $gradeId)
            ->first();

        if (!$studentGrade) {
            return response()->json(['success' => false, 'message' => 'Grade record not found'], 404);
        }

        // Check if retake grade already exists
        if ($studentGrade->retake_grade !== null) {
            return response()->json(['success' => false, 'message' => 'Retake bahosi allaqachon qo\'yilgan. O\'zgartirishga ruxsat berilmagan.'], 400);
        }

        // Check if there's an attendance record to determine if absence was excused
        $attendance = DB::table('attendances')
            ->where('student_hemis_id', $studentGrade->student_hemis_id)
            ->where('subject_id', $studentGrade->subject_id)
            ->where('semester_code', $studentGrade->semester_code)
            ->where('lesson_date', $studentGrade->lesson_date)
            ->where('lesson_pair_code', $studentGrade->lesson_pair_code)
            ->first();

        // Determine the percentage to apply
        $isExcused = $attendance && ((int) $attendance->absent_on) > 0;
        $percentage = $isExcused ? 1.0 : 0.8; // 100% if excused, 80% otherwise

        // Calculate final retake grade
        $retakeGrade = round($enteredGrade * $percentage, 2);

        $now = now();

        // Update the grade record with retake information
        DB::table('student_grades')
            ->where('id', $gradeId)
            ->update([
                'retake_grade' => $retakeGrade,
                'status' => 'retake',
                'graded_by_user_id' => auth()->id(),
                'retake_graded_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Retake bahosi muvaffaqiyatli saqlandi',
            'retake_grade' => $retakeGrade,
            'percentage' => $percentage * 100
        ]);
    }

    /**
     * Create a new retake grade for empty cells
     */
    public function createRetakeGrade(Request $request)
    {
        // Check admin role
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'student_hemis_id' => 'required',
            'lesson_date' => 'required|date',
            'lesson_pair_code' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        $studentHemisId = $request->student_hemis_id;
        $lessonDate = $request->lesson_date;
        $lessonPairCode = $request->lesson_pair_code;
        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $enteredGrade = $request->grade;

        // Get student info
        $student = DB::table('students')
            ->where('hemis_id', $studentHemisId)
            ->first();

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        }

        // Get subject info
        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found'], 404);
        }

        // Get schedule info for training type
        $schedule = DB::table('schedules')
            ->where('subject_id', $subjectId)
            ->where('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $lessonPairCode)
            ->first();

        // Check if there's an attendance record to determine if absence was excused
        $attendance = DB::table('attendances')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $lessonPairCode)
            ->first();

        // Determine the percentage to apply
        // For empty cells, default to 80% unless it's an excused absence
        $isExcused = $attendance && ((int) $attendance->absent_on) > 0;
        $percentage = $isExcused ? 1.0 : 0.8; // 100% if excused, 80% otherwise

        // Calculate final retake grade
        $retakeGrade = round($enteredGrade * $percentage, 2);

        $now = now();

        // Get education year from student's curriculum
        $curriculum = DB::table('curricula')
            ->where('curricula_hemis_id', $student->curriculum_id ?? null)
            ->first();
        $educationYearCode = $curriculum->education_year_code ?? null;
        $educationYearName = $curriculum->education_year_name ?? null;

        // Create new student_grades record
        DB::table('student_grades')->insert([
            'hemis_id' => 0, // Manual entry
            'student_id' => $student->id,
            'student_hemis_id' => $studentHemisId,
            'semester_code' => $semesterCode,
            'semester_name' => $subject->semester_name ?? '',
            'education_year_code' => $educationYearCode,
            'education_year_name' => $educationYearName,
            'subject_schedule_id' => $schedule->id ?? 0,
            'subject_id' => $subjectId,
            'subject_name' => $subject->subject_name ?? '',
            'subject_code' => $subject->subject_code ?? '',
            'training_type_code' => $schedule->training_type_code ?? 10,
            'training_type_name' => $schedule->training_type_name ?? 'Amaliyot',
            'employee_id' => $schedule->employee_id ?? 0,
            'employee_name' => $schedule->employee_name ?? 'Manual',
            'lesson_pair_code' => $lessonPairCode,
            'lesson_pair_name' => $schedule->lesson_pair_name ?? '',
            'lesson_pair_start_time' => $schedule->lesson_pair_start_time ?? '00:00',
            'lesson_pair_end_time' => $schedule->lesson_pair_end_time ?? '00:00',
            'grade' => 0,
            'retake_grade' => $retakeGrade,
            'lesson_date' => $lessonDate,
            'created_at_api' => $now,
            'status' => 'retake',
            'reason' => 'low_grade',
            'graded_by_user_id' => auth()->id(),
            'retake_graded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Retake bahosi muvaffaqiyatli saqlandi',
            'retake_grade' => $retakeGrade,
            'percentage' => $percentage * 100
        ]);
    }

    // AJAX endpoints for cascading dropdowns
    public function getSpecialties(Request $request)
    {
        // Faqat faol bo'limlar bilan bog'liq yo'nalishlarni olish
        $activeDepartmentHemisIds = Department::where('active', true)
            ->pluck('department_hemis_id');

        $query = Specialty::whereIn('department_hemis_id', $activeDepartmentHemisIds);

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }

        // Ta'lim turi bo'yicha filtrlash (curriculum -> specialty orqali)
        if ($request->filled('education_type')) {
            $specialtyHemisIds = Curriculum::where('education_type_code', $request->education_type)
                ->pluck('specialty_hemis_id')
                ->unique();
            $query->whereIn('specialty_hemis_id', $specialtyHemisIds);
        }

        return $query->select('specialty_hemis_id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'specialty_hemis_id');
    }

    public function getLevelCodes(Request $request)
    {
        $query = Semester::query();

        if ($request->filled('education_year')) {
            $query->where('education_year', $request->education_year);
        }

        return $query->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->get()
            ->pluck('level_name', 'level_code');
    }

    public function getSemesters(Request $request)
    {
        $query = Semester::query();

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        return $query->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->get()
            ->pluck('name', 'code');
    }

    public function getSubjects(Request $request)
    {
        // Asosiy journal query bilan bir xil joinlar - faqat haqiqiy natija bor fanlar
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);

        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }
        if ($request->filled('education_year')) {
            $query->where('c.education_year_code', $request->education_year);
        }
        if ($request->filled('faculty_id')) {
            $query->where('f.id', $request->faculty_id);
        }
        if ($request->filled('department_id')) {
            $query->where('cs.department_id', $request->department_id);
        }
        if ($request->filled('specialty_id')) {
            $query->where('sp.specialty_hemis_id', $request->specialty_id);
        }
        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->get('current_semester') == '1') {
            $query->where('s.current', true);
        }

        return $query->select('cs.subject_id', 'cs.subject_name')
            ->groupBy('cs.subject_id', 'cs.subject_name')
            ->orderBy('cs.subject_name')
            ->get()
            ->pluck('subject_name', 'subject_id');
    }

    public function getGroups(Request $request)
    {
        $query = Group::where('department_active', true)->where('active', true);

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }

        // Kafedra bo'yicha filtrlash (curriculum_subjects.department_id orqali)
        if ($request->filled('department_id')) {
            $curriculaWithDept = CurriculumSubject::where('department_id', $request->department_id)
                ->pluck('curricula_hemis_id')
                ->unique();
            $query->whereIn('curriculum_hemis_id', $curriculaWithDept);
        }

        if ($request->filled('specialty_id')) {
            $query->where('specialty_hemis_id', $request->specialty_id);
        }

        // Ta'lim turi bo'yicha filtrlash (curriculum orqali)
        if ($request->filled('education_type')) {
            $curriculaIds = Curriculum::where('education_type_code', $request->education_type)
                ->pluck('curricula_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // O'quv yili bo'yicha filtrlash
        if ($request->filled('education_year')) {
            $curriculaIds = Curriculum::where('education_year_code', $request->education_year)
                ->pluck('curricula_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Semestr bo'yicha filtrlash
        if ($request->filled('semester_code')) {
            $curriculaIds = Semester::where('code', $request->semester_code)
                ->pluck('curriculum_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Kurs bo'yicha filtrlash
        if ($request->filled('level_code')) {
            $curriculaIds = Semester::where('level_code', $request->level_code)
                ->pluck('curriculum_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Fan bo'yicha filtrlash
        if ($request->filled('subject_id')) {
            $curriculaIds = CurriculumSubject::where('subject_id', $request->subject_id)
                ->pluck('curricula_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        return $query->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');
    }

    // Ikki tomonlama bog'liq filtrlar
    public function getFacultiesBySpecialty(Request $request)
    {
        if (!$request->filled('specialty_id')) {
            return Department::where('structure_type_code', 11)
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $specialty = Specialty::where('specialty_hemis_id', $request->specialty_id)->first();
        if (!$specialty) {
            return [];
        }

        return Department::where('structure_type_code', 11)
            ->where('active', true)
            ->where('department_hemis_id', $specialty->department_hemis_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getLevelCodesBySemester(Request $request)
    {
        if (!$request->filled('semester_code')) {
            return Semester::select('level_code', 'level_name')
                ->groupBy('level_code', 'level_name')
                ->orderBy('level_code')
                ->pluck('level_name', 'level_code');
        }

        return Semester::where('code', $request->semester_code)
            ->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->pluck('level_name', 'level_code');
    }

    public function getEducationYearsByLevel(Request $request)
    {
        if (!$request->filled('level_code')) {
            return Curriculum::select('education_year_code', 'education_year_name')
                ->whereNotNull('education_year_code')
                ->groupBy('education_year_code', 'education_year_name')
                ->orderBy('education_year_code', 'desc')
                ->pluck('education_year_name', 'education_year_code');
        }

        $semesterCurriculumIds = Semester::where('level_code', $request->level_code)
            ->pluck('curriculum_hemis_id');

        return Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->whereIn('curricula_hemis_id', $semesterCurriculumIds)
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->pluck('education_year_name', 'education_year_code');
    }

    // Guruh bo'yicha fakultet va yo'nalishni olish
    public function getFacultiesByGroup(Request $request)
    {
        if (!$request->filled('group_id')) {
            return Department::where('structure_type_code', 11)
                ->where('active', true)
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $group = Group::find($request->group_id);
        if (!$group) {
            return [];
        }

        return Department::where('structure_type_code', 11)
            ->where('active', true)
            ->where('department_hemis_id', $group->department_hemis_id)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function getSpecialtiesByGroup(Request $request)
    {
        if (!$request->filled('group_id')) {
            $activeDepartmentHemisIds = Department::where('active', true)
                ->pluck('department_hemis_id');

            return Specialty::whereIn('department_hemis_id', $activeDepartmentHemisIds)
                ->select('specialty_hemis_id', 'name')
                ->orderBy('name')
                ->pluck('name', 'specialty_hemis_id');
        }

        $group = Group::find($request->group_id);
        if (!$group) {
            return [];
        }

        return Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)
            ->select('specialty_hemis_id', 'name')
            ->orderBy('name')
            ->pluck('name', 'specialty_hemis_id');
    }

    // Fan bo'yicha boshqa filtrlarni olish
    public function getFiltersBySubject(Request $request)
    {
        if (!$request->filled('subject_id')) {
            return [
                'faculties' => [],
                'specialties' => [],
                'groups' => [],
                'semesters' => [],
            ];
        }

        $subjectId = $request->subject_id;

        // Fan mavjud bo'lgan curriculum_subjects dan ma'lumot olish
        $curriculumSubjects = CurriculumSubject::where('subject_id', $subjectId)->get();

        $curriculaHemisIds = $curriculumSubjects->pluck('curricula_hemis_id')->unique();
        $semesterCodes = $curriculumSubjects->pluck('semester_code')->unique();

        // Guruhlar (faqat faol)
        $groups = Group::whereIn('curriculum_hemis_id', $curriculaHemisIds)
            ->where('department_active', true)
            ->where('active', true)
            ->select('id', 'name', 'department_hemis_id', 'specialty_hemis_id')
            ->orderBy('name')
            ->get();

        // Fakultetlar (faqat faol)
        $departmentHemisIds = $groups->pluck('department_hemis_id')->unique();
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->whereIn('department_hemis_id', $departmentHemisIds)
            ->orderBy('name')
            ->pluck('name', 'id');

        // Yo'nalishlar
        $specialtyHemisIds = $groups->pluck('specialty_hemis_id')->unique();
        $specialties = Specialty::whereIn('specialty_hemis_id', $specialtyHemisIds)
            ->orderBy('name')
            ->pluck('name', 'specialty_hemis_id');

        // Semestrlar
        $semesters = Semester::whereIn('code', $semesterCodes)
            ->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->pluck('name', 'code');

        return [
            'faculties' => $faculties,
            'specialties' => $specialties,
            'groups' => $groups->pluck('name', 'id'),
            'semesters' => $semesters,
        ];
    }

    // Guruh bo'yicha boshqa filtrlarni olish
    public function getFiltersByGroup(Request $request)
    {
        if (!$request->filled('group_id')) {
            return [
                'faculties' => [],
                'specialties' => [],
                'subjects' => [],
                'semesters' => [],
            ];
        }

        $group = Group::find($request->group_id);
        if (!$group) {
            return [
                'faculties' => [],
                'specialties' => [],
                'subjects' => [],
                'semesters' => [],
            ];
        }

        // Fakultet
        $faculties = Department::where('structure_type_code', 11)
            ->where('department_hemis_id', $group->department_hemis_id)
            ->orderBy('name')
            ->pluck('name', 'id');

        // Yo'nalish
        $specialties = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)
            ->orderBy('name')
            ->pluck('name', 'specialty_hemis_id');

        // Fanlar va semestrlar (guruhning curriculum_hemis_id dan)
        $curriculumSubjects = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)->get();

        $subjects = $curriculumSubjects->pluck('subject_name', 'subject_id')->unique();
        $semesterCodes = $curriculumSubjects->pluck('semester_code')->unique();

        $semesters = Semester::whereIn('code', $semesterCodes)
            ->where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->select('code', 'name')
            ->groupBy('code', 'name')
            ->orderBy('code')
            ->pluck('name', 'code');

        return [
            'faculties' => $faculties,
            'specialties' => $specialties,
            'subjects' => $subjects,
            'semesters' => $semesters,
        ];
    }

    // Semestr bo'yicha boshqa filtrlarni olish
    public function getFiltersBySemester(Request $request)
    {
        if (!$request->filled('semester_code')) {
            return [
                'subjects' => [],
                'level_codes' => [],
            ];
        }

        $semesterCode = $request->semester_code;

        // Fanlar (faqat faol guruhlar bilan bog'liq)
        $activeCurriculaIds = Group::where('department_active', true)->where('active', true)
            ->pluck('curriculum_hemis_id')
            ->unique();

        $subjects = CurriculumSubject::where('semester_code', $semesterCode)
            ->whereIn('curricula_hemis_id', $activeCurriculaIds)
            ->select('subject_id', 'subject_name')
            ->groupBy('subject_id', 'subject_name')
            ->orderBy('subject_name')
            ->pluck('subject_name', 'subject_id');

        // Kurslar
        $levelCodes = Semester::where('code', $semesterCode)
            ->select('level_code', 'level_name')
            ->groupBy('level_code', 'level_name')
            ->orderBy('level_code')
            ->pluck('level_name', 'level_code');

        return [
            'subjects' => $subjects,
            'level_codes' => $levelCodes,
        ];
    }

    /**
     * HEMIS API dan fan mavzularini olish.
     */
    public function getTopics(Request $request)
    {
        $baseUrl = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz/rest/v1/'), '/');
        $token = config('services.hemis.token');

        $params = [
            'page' => $request->get('page', 1),
            'limit' => $request->get('limit', 200),
        ];

        if ($request->filled('semester_id')) {
            $params['_semester'] = $request->semester_id;
        }
        if ($request->filled('curriculum_id')) {
            $params['_curriculum'] = $request->curriculum_id;
        }
        if ($request->filled('training_type')) {
            $params['_training_type'] = $request->training_type;
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(30)
                ->get($baseUrl . '/data/curriculum-subject-topic-list', $params);

            if ($response->successful()) {
                $data = $response->json();
                // Fan bo'yicha filtrlash (API da subject filter bo'lmasligi mumkin)
                if ($request->filled('subject_id') && isset($data['data']['items'])) {
                    $subjectId = (int) $request->subject_id;
                    $data['data']['items'] = array_values(
                        array_filter($data['data']['items'], function ($item) use ($subjectId) {
                            return isset($item['subject']['id']) && (int) $item['subject']['id'] === $subjectId;
                        })
                    );
                }
                return response()->json($data);
            }

            return response()->json(['success' => false, 'error' => 'HEMIS API xatolik: ' . $response->status(), 'data' => []]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage(), 'data' => []]);
        }
    }

    /**
     * Dars jadvalidagi vaqtdan akademik soatlarni hisoblash.
     * 80 min = 2 soat, 40-45 min = 1 soat.
     */
    private function calculateAcademicHours($rows)
    {
        return $rows->sum(function ($row) {
            if (!$row->lesson_pair_start_time || !$row->lesson_pair_end_time) return 0;
            $start = \Carbon\Carbon::parse($row->lesson_pair_start_time);
            $end = \Carbon\Carbon::parse($row->lesson_pair_end_time);
            $minutes = $start->diffInMinutes($end);
            return $minutes >= 60 ? 2 : 1;
        });
    }

    /**
     * O'qituvchilarni tur bo'yicha ajratish: Ma'ruza va Amaliyot.
     * Har bir o'qituvchi uchun akademik soatlar hisoblanadi.
     */
    private function getTeachersByType($groupHemisId, $subjectId, $semesterCode)
    {
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        $schedules = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotNull('employee_name')
            ->where('employee_name', '!=', 'Manual Entry')
            ->where('employee_name', '!=', '')
            ->select('employee_name', 'training_type_code', 'lesson_pair_start_time', 'lesson_pair_end_time')
            ->get();

        $lectureTeacher = null;
        $practiceTeachers = [];

        // Ma'ruza o'qituvchisi (training_type_code = 11)
        $lectureRows = $schedules->where('training_type_code', 11);
        if ($lectureRows->isNotEmpty()) {
            $grouped = $lectureRows->groupBy('employee_name');
            foreach ($grouped as $name => $rows) {
                $hours = $this->calculateAcademicHours($rows);
                $lectureTeacher = ['name' => $name, 'hours' => $hours];
                break; // Odatda bitta ma'ruza o'qituvchisi
            }
        }

        // Amaliyot o'qituvchilari (11, 99, 100, 101, 102 tashqaridagi turlar)
        $practiceRows = $schedules->filter(function ($row) use ($excludedTrainingCodes) {
            return !in_array($row->training_type_code, $excludedTrainingCodes);
        });
        if ($practiceRows->isNotEmpty()) {
            $grouped = $practiceRows->groupBy('employee_name');
            foreach ($grouped as $name => $rows) {
                $hours = $this->calculateAcademicHours($rows);
                $practiceTeachers[] = ['name' => $name, 'hours' => $hours];
            }
        }

        return [
            'lecture_teacher' => $lectureTeacher,
            'practice_teachers' => $practiceTeachers,
        ];
    }

    /**
     * Cascading sidebar filter options.
     * Chain: Fakultet(free) → Yo'nalish → Kurs → Semestr → [Guruh ↔ Fan]
     * Kafedra is free and filters Fan.
     */
    public function getSidebarOptions(Request $request)
    {
        // Fakultet department_hemis_id (reused in multiple queries)
        $facultyDeptHemisId = null;
        if ($request->filled('faculty_id')) {
            $facultyDeptHemisId = Department::where('id', $request->faculty_id)->value('department_hemis_id');
        }

        // 1. Fakultet - erkin, hamma faol fakultetlar
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        // 2. Yo'nalish - fakultetga bog'liq
        $specialtiesQuery = DB::table('specialties as sp')
            ->join('groups as g', 'g.specialty_hemis_id', '=', 'sp.specialty_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);
        if ($facultyDeptHemisId) {
            $specialtiesQuery->where('g.department_hemis_id', $facultyDeptHemisId);
        }
        $specialties = $specialtiesQuery
            ->select('sp.specialty_hemis_id', 'sp.name')
            ->groupBy('sp.specialty_hemis_id', 'sp.name')
            ->orderBy('sp.name')
            ->pluck('sp.name', 'sp.specialty_hemis_id');

        // 3. Kurs - yo'nalishga bog'liq
        $levelsQuery = DB::table('semesters as s')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 's.curriculum_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);
        if ($request->filled('specialty_id')) {
            $levelsQuery->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($facultyDeptHemisId) {
            $levelsQuery->where('g.department_hemis_id', $facultyDeptHemisId);
        }
        $levels = $levelsQuery
            ->select('s.level_code', 's.level_name')
            ->groupBy('s.level_code', 's.level_name')
            ->orderBy('s.level_code')
            ->pluck('s.level_name', 's.level_code');

        // 5. Semestr - kursga bog'liq (+ yo'nalish kontekst)
        $semestersQuery = DB::table('semesters as s')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 's.curriculum_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);
        if ($request->filled('level_code')) {
            $semestersQuery->where('s.level_code', $request->level_code);
        }
        if ($request->filled('specialty_id')) {
            $semestersQuery->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($facultyDeptHemisId) {
            $semestersQuery->where('g.department_hemis_id', $facultyDeptHemisId);
        }
        $semesters = $semestersQuery
            ->select('s.code', 's.name')
            ->groupBy('s.code', 's.name')
            ->orderBy('s.code')
            ->pluck('s.name', 's.code');

        // 6. Guruh - semestr + yo'nalish + fakultet + fan (ikki tomonlama)
        $groupsQuery = DB::table('groups as g')
            ->where('g.department_active', true)
            ->where('g.active', true);
        if ($request->filled('semester_code')) {
            // Tanlangan semestr guruhning curriculumida current bo'lishi shart
            $groupsQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('semesters as s2')
                    ->whereColumn('s2.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->where('s2.code', $request->semester_code)
                    ->where('s2.current', true);
            });
        } else {
            // Semestr tanlanmagan - ixtiyoriy joriy semestri bor guruhlar
            $groupsQuery->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('semesters as s_cur')
                    ->whereColumn('s_cur.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->where('s_cur.current', true);
            });
        }
        if ($request->filled('specialty_id')) {
            $groupsQuery->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($facultyDeptHemisId) {
            $groupsQuery->where('g.department_hemis_id', $facultyDeptHemisId);
        }
        // Ikki tomonlama: fan tanlansa, faqat o'sha fan bor guruhlar
        if ($request->filled('subject_id')) {
            $groupsQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('curriculum_subjects as cs2')
                    ->whereColumn('cs2.curricula_hemis_id', 'g.curriculum_hemis_id')
                    ->where('cs2.subject_id', $request->subject_id);
                if ($request->filled('semester_code')) {
                    $sub->where('cs2.semester_code', $request->semester_code);
                }
            });
        }
        $groups = $groupsQuery
            ->select('g.id', 'g.name')
            ->orderBy('g.name')
            ->pluck('g.name', 'g.id');

        // 7. Fan - semestr + guruh (ikki tomonlama) + yo'nalish kontekst
        $subjectsQuery = DB::table('curriculum_subjects as cs')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);
        if ($request->filled('semester_code')) {
            $subjectsQuery->where('cs.semester_code', $request->semester_code);
            // Faqat joriy semestr fanlari (eski curriculumlarni chiqarmaslik uchun)
            $subjectsQuery->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('semesters as s3')
                    ->whereColumn('s3.curriculum_hemis_id', 'cs.curricula_hemis_id')
                    ->where('s3.code', $request->semester_code)
                    ->where('s3.current', true);
            });
        }
        // Ikki tomonlama: guruh tanlansa, faqat o'sha guruh fanlari
        if ($request->filled('group_id')) {
            $subjectsQuery->where('g.id', $request->group_id);
        }
        if ($request->filled('specialty_id')) {
            $subjectsQuery->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($facultyDeptHemisId) {
            $subjectsQuery->where('g.department_hemis_id', $facultyDeptHemisId);
        }
        $subjects = $subjectsQuery
            ->select('cs.subject_id', 'cs.subject_name')
            ->groupBy('cs.subject_id', 'cs.subject_name')
            ->orderBy('cs.subject_name')
            ->pluck('cs.subject_name', 'cs.subject_id');

        // 8. O'qituvchi - tur bo'yicha ajratilgan (Ma'ruza / Amaliyot), soatlari bilan
        $teacherData = ['lecture_teacher' => null, 'practice_teachers' => []];
        $selectedGroup = $request->filled('group_id') ? Group::find($request->group_id) : null;
        if ($selectedGroup && $request->filled('subject_id') && $request->filled('semester_code')) {
            $teacherData = $this->getTeachersByType(
                $selectedGroup->group_hemis_id,
                $request->subject_id,
                $request->semester_code
            );
        }

        // Kafedra nomi - tanlangan fan bo'yicha (ma'lumot sifatida)
        $kafedraName = '';
        if ($request->filled('subject_id') && $selectedGroup) {
            $grp = $selectedGroup;
            if ($grp) {
                $kafedraName = CurriculumSubject::where('subject_id', $request->subject_id)
                    ->where('curricula_hemis_id', $grp->curriculum_hemis_id)
                    ->value('department_name') ?? '';
            }
        } elseif ($request->filled('subject_id')) {
            $kafedraName = CurriculumSubject::where('subject_id', $request->subject_id)
                ->value('department_name') ?? '';
        }

        return response()->json([
            'faculties' => $faculties,
            'specialties' => $specialties,
            'levels' => $levels,
            'semesters' => $semesters,
            'groups' => $groups,
            'subjects' => $subjects,
            'teacher_data' => $teacherData,
            'kafedra_name' => $kafedraName,
        ]);
    }
}

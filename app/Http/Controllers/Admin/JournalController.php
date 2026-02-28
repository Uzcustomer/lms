<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\CurriculumSubjectTeacher;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\LessonOpening;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Specialty;
use App\Jobs\ImportSchedulesPartiallyJob;
use App\Services\ActivityLogService;
use App\Services\HemisService;
use App\Services\ScheduleImportService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\MarkingSystemScore;
use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\ExamSchedule;
use App\Models\YnConsent;
use App\Models\YnStudentGrade;
use App\Models\YnSubmission;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\HemisQuizResult;
use App\Models\Attendance;
use App\Models\Teacher;
use App\Models\DocumentVerification;
use App\Enums\ProjectRole;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        // Dekan uchun fakultet cheklovi
        $dekanFacultyIds = get_dekan_faculty_ids();

        // O'qituvchi uchun fanga biriktirilgan cheklovi
        $isOqituvchi = is_active_oqituvchi();
        $teacherSubjectIds = [];
        $teacherGroupIds = [];
        if ($isOqituvchi) {
            $teacherHemisId = get_teacher_hemis_id();
            if ($teacherHemisId) {
                $assignments = $this->getTeacherSubjectAssignments($teacherHemisId);
                $teacherSubjectIds = $assignments['subject_ids'];
                $teacherGroupIds = $assignments['group_ids'];
            }
        }

        // Get filter options for dropdowns
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $selectedEducationType = $request->get('education_type');
        if (!$request->has('education_type')) {
            if ($isOqituvchi) {
                // O'qituvchi uchun: ta'lim turi filtrini qo'llamaydi (barcha ta'lim turlari ko'rinadi)
                $selectedEducationType = null;
            } else {
                $selectedEducationType = $educationTypes
                    ->first(function ($type) {
                        return str_contains(mb_strtolower($type->education_type_name ?? ''), 'bakalavr');
                    })
                    ?->education_type_code;
            }
        }

        $educationYears = Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->get();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        // Dekan faqat o'z fakultetlarini ko'radi
        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->get();

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
                ->where('g.active', true)
                ->where('cs.is_active', true);
        };

        // Kafedra dropdown uchun - faqat haqiqiy natija bor kafedralar
        $kafedraQuery = $baseQuery()
            ->whereNotNull('cs.department_id')
            ->whereNotNull('cs.department_name');

        // O'qituvchi uchun ta'lim turi filtrini qo'llamaydi
        if (!$isOqituvchi && $selectedEducationType) {
            $kafedraQuery->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('education_year')) {
            $kafedraQuery->where('c.education_year_code', $request->education_year);
        }
        if (!empty($dekanFacultyIds)) {
            $kafedraQuery->whereIn('f.id', $dekanFacultyIds);
        } elseif ($request->filled('faculty')) {
            $kafedraQuery->where('f.id', $request->faculty);
        }
        // O'qituvchi uchun faqat o'zi o'tadigan fanlar
        if ($isOqituvchi) {
            $kafedraQuery->whereIn('cs.subject_id', $teacherSubjectIds);
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
        // O'qituvchi uchun ta'lim turi filtrini qo'llamaydi
        if (!$isOqituvchi && $selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }

        if ($request->filled('education_year')) {
            $query->where('c.education_year_code', $request->education_year);
        }

        // Dekan uchun fakultet majburiy filtr
        if (!empty($dekanFacultyIds)) {
            if ($request->filled('faculty') && in_array((int) $request->faculty, $dekanFacultyIds)) {
                $query->where('f.id', $request->faculty);
            } else {
                $query->whereIn('f.id', $dekanFacultyIds);
            }
        } elseif ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
        }

        // O'qituvchi uchun faqat o'zi o'tadigan fanlar va guruhlar
        if ($isOqituvchi) {
            $query->whereIn('cs.subject_id', $teacherSubjectIds);
        }
        if ($isOqituvchi) {
            $query->whereIn('g.group_hemis_id', $teacherGroupIds);
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
            'sortDirection',
            'dekanFacultyIds',
            'isOqituvchi'
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
            ->whereNull('deleted_at')
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
            ->pluck('hemis_id');

        // Excluded training type names for Amaliyot (JB)
        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
        // Excluded training type codes: 11=Ma'ruza, 99=MT, 100=ON, 101=Oski, 102=Test, 103=Quiz
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // Calendar source: get scheduled lesson date/pair columns for JB and MT
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
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
            ->whereNull('deleted_at')
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
            ->whereNull('deleted_at')
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

        // Data source: barcha baholarni bitta so'rovda olish (training_type filter QILINMAYDI).
        // HEMIS API da schedule va grade endpointlari bitta dars uchun turli training_type
        // qaytarishi mumkin — shuning uchun baholarni training_type bo'yicha filtrlash o'rniga,
        // jadval (schedule) dagi (sana + para) juftliklari asosida JB va MT ga ajratamiz.
        // ON (100), OSKI (101), Test (102) alohida so'rovda olinadi.
        $allGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->whereNotNull('lesson_date')
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select('id', 'hemis_id', 'student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason', 'is_final', 'deadline', 'created_at')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Helper function to get effective grade based on status
        // Returns array: ['grade' => value, 'is_retake' => bool] or null
        $getEffectiveGrade = function ($row) {
            // status = pending + low_grade → show the grade (e.g. 55 ball)
            if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
                return ['grade' => $row->grade, 'is_retake' => false];
            }
            // status = pending (other reasons) → null (skip)
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

        // Build unique date+pair columns for detailed view from schedules only.
        // Schedule is the single source of truth — soft-deleted schedules won't appear.
        $jbColumns = $jbScheduleRows->map(function ($schedule) {
            return ['date' => $schedule->lesson_date, 'pair' => $schedule->lesson_pair_code];
        })->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->sort(function ($a, $b) {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']);
        })->values()->toArray();

        // Build unique date+pair columns for MT from schedules only.
        $mtColumns = $mtScheduleRows->map(function ($schedule) {
            return ['date' => $schedule->lesson_date, 'pair' => $schedule->lesson_pair_code];
        })->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->sort(function ($a, $b) {
            return strcmp($a['date'], $b['date']) ?: strcmp($a['pair'], $b['pair']);
        })->values()->toArray();

        // Get distinct dates for compact view from schedules only.
        $jbLessonDates = $jbScheduleRows
            ->pluck('lesson_date')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $mtLessonDates = $mtScheduleRows
            ->pluck('lesson_date')
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

        // Jadval (date+pair) asosida baholarni JB va MT ga ajratish.
        // HEMIS API da schedule va grade uchun training_type farq qilishi mumkin,
        // shuning uchun jadval tuzilishi (schedule) yagona manba hisoblanadi.
        $jbDatePairSet = [];
        foreach ($jbColumns as $c) {
            $normalizedDate = \Carbon\Carbon::parse($c['date'])->format('Y-m-d');
            $jbDatePairSet[$normalizedDate . '_' . $c['pair']] = true;
        }
        $mtDatePairSet = [];
        foreach ($mtColumns as $c) {
            $normalizedDate = \Carbon\Carbon::parse($c['date'])->format('Y-m-d');
            $mtDatePairSet[$normalizedDate . '_' . $c['pair']] = true;
        }

        $jbGradesRaw = $allGradesRaw->filter(function ($g) use ($jbDatePairSet) {
            $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            return isset($jbDatePairSet[$normalizedDate . '_' . $g->lesson_pair_code]);
        });
        $mtGradesRaw = $allGradesRaw->filter(function ($g) use ($mtDatePairSet) {
            $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            return isset($mtDatePairSet[$normalizedDate . '_' . $g->lesson_pair_code]);
        });

        // Build grades data structure: student_hemis_id => date => pair => ['grade' => value, 'is_retake' => bool, 'id' => id, 'status' => status, 'retake_grade' => retake_grade, 'reason' => reason]
        $jbGrades = [];
        foreach ($jbGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $jbGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = array_merge($effectiveGrade, [
                    'id' => $g->id,
                    'hemis_id' => $g->hemis_id,
                    'status' => $g->status,
                    'retake_grade' => $g->retake_grade,
                    'reason' => $g->reason,
                    'original_grade' => $g->grade,
                    'is_final' => $g->is_final,
                    'deadline' => $g->deadline,
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
                    'original_grade' => $g->grade,
                    'is_final' => $g->is_final,
                    'deadline' => $g->deadline,
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
                    'retake_grade' => $g->retake_grade,
                    'deadline' => $g->deadline,
                ];
            }
        }

        $mtAbsences = [];
        foreach ($mtGradesRaw as $g) {
            if ($g->reason === 'absent') {
                $mtAbsences[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = [
                    'id' => $g->id,
                    'retake_grade' => $g->retake_grade,
                    'deadline' => $g->deadline,
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
        // Chetlashgan talabalar: joriy semestrda bo'lsa — ko'rsatiladi (qizil), oldingi semestrlarda — chiqariladi
        // NULL student_status_code ham faol deb hisoblanadi (chiqarilmaydi)
        // Chetlashgan (60) talaba bahosi yoki davomati bo'lsa — ko'rsatiladi
        $students = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->where(function ($query) use ($semesterCode, $subjectId) {
                $query
                    // Faol talabalar (status != 60, shu jumladan NULL): doimo ko'rsatiladi
                    ->where(function ($q) {
                        $q->where('student_status_code', '!=', '60')
                          ->orWhereNull('student_status_code');
                    })
                    // Chetlashgan talabalar: semester mos kelsa ko'rsatiladi
                    ->orWhere('semester_code', $semesterCode)
                    // Chetlashgan talabalar: bahosi bo'lsa ko'rsatiladi (NB ham)
                    ->orWhereExists(function ($sub) use ($subjectId, $semesterCode) {
                        $sub->select(DB::raw(1))
                            ->from('student_grades')
                            ->whereColumn('student_grades.student_hemis_id', 'students.hemis_id')
                            ->where('student_grades.subject_id', $subjectId)
                            ->where('student_grades.semester_code', $semesterCode)
                            ->whereNull('student_grades.deleted_at');
                    });
            })
            ->select('id', 'hemis_id', 'full_name', 'student_id_number', 'student_status_code')
            ->orderBy('full_name')
            ->get();

        // Get other averages (ON, OSKI, Test, Quiz) with status-based grade calculation
        // Filter by education_year_code to exclude old education year data
        $otherGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('training_type_code', [100, 101, 102, 103])
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
            ->select('student_hemis_id', 'training_type_code', 'grade', 'retake_grade', 'status', 'reason', 'quiz_result_id')
            ->get();

        $otherGrades = [];
        foreach ($otherGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $typeCode = $g->training_type_code;
                // Legacy code 103 quiz grades: resolve to OSKI(101) or Test(102) via quiz_result
                if ($typeCode == 103 && $g->quiz_result_id) {
                    $quizType = DB::table('hemis_quiz_results')->where('id', $g->quiz_result_id)->value('quiz_type');
                    $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                    $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
                    if (in_array($quizType, $oskiTypes)) {
                        $typeCode = 101;
                    } elseif (in_array($quizType, $testTypes)) {
                        $typeCode = 102;
                    }
                }
                $otherGrades[$g->student_hemis_id][$typeCode][] = $effectiveGrade['grade'];
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
        // No education_year_code filter: manual MT grades are unique per student/subject/semester
        $manualMtGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->get()
            ->keyBy('student_hemis_id');
        $manualMtGrades = $manualMtGradesRaw->map(fn($g) => $g->grade)->toArray();

        // Get MT grade history for all students
        $mtGradeHistory = [];
        $mtGradeHistoryRaw = collect();
        if (\Schema::hasTable('mt_grade_history')) {
            $mtGradeHistoryRaw = DB::table('mt_grade_history')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->orderBy('attempt_number')
                ->get();

            foreach ($mtGradeHistoryRaw as $h) {
                $mtGradeHistory[$h->student_hemis_id][] = $h;
            }
        }

        $mtMaxResubmissions = (int) \App\Models\Setting::get('mt_max_resubmissions', 3);

        // Get student file submissions for this subject's independent assignments
        $mtSubmissions = [];
        $mtUngradedCount = 0;
        $mtDangerCount = 0;
        $independentIds = [];
        try {
            // Cross-curriculum support: search all hemis_ids for this subject
            $allCsHemisIds = DB::table('curriculum_subjects')
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->pluck('curriculum_subject_hemis_id')
                ->toArray();

            if (!empty($allCsHemisIds)) {
                $independentIds = DB::table('independents')
                    ->where('group_hemis_id', $group->group_hemis_id)
                    ->where('semester_code', $semesterCode)
                    ->where(function ($q) use ($allCsHemisIds, $subject) {
                        $q->whereIn('subject_hemis_id', $allCsHemisIds)
                          ->orWhere('subject_name', $subject->subject_name);
                    })
                    ->pluck('id')
                    ->toArray();

                if (!empty($independentIds) && \Schema::hasTable('independent_submissions')) {
                    $submissionsRaw = DB::table('independent_submissions')
                        ->whereIn('independent_id', $independentIds)
                        ->get();
                    foreach ($submissionsRaw as $sub) {
                        if (!isset($mtSubmissions[$sub->student_hemis_id])
                            || ($sub->submitted_at ?? '') > ($mtSubmissions[$sub->student_hemis_id]->submitted_at ?? '')) {
                            $mtSubmissions[$sub->student_hemis_id] = $sub;
                        }
                    }
                }
            }

            // Count ungraded/resubmitted submissions for MT tab badge
            foreach ($mtSubmissions as $hemisId => $sub) {
                $gradeVal = $manualMtGrades[$hemisId] ?? null;
                $gradeRowForBadge = $manualMtGradesRaw[$hemisId] ?? null;
                $needsBadge = false;

                if ($gradeVal === null) {
                    // No grade yet - needs grading
                    $needsBadge = true;
                } elseif ($gradeVal < MarkingSystemScore::getByStudentHemisId($hemisId)->minimum_limit && $gradeRowForBadge && $sub->submitted_at) {
                    // Grade < minimum_limit: check if student resubmitted after grade
                    $grTime = $gradeRowForBadge->updated_at ?? $gradeRowForBadge->created_at;
                    if ($grTime && \Carbon\Carbon::parse($sub->submitted_at)->gt(\Carbon\Carbon::parse($grTime))) {
                        $needsBadge = true;
                    }
                }

                if ($needsBadge) {
                    $mtUngradedCount++;
                    if ($sub->submitted_at) {
                        $daysSince = \Carbon\Carbon::parse($sub->submitted_at)->diffInDays(now());
                        if ($daysSince >= 3) {
                            $mtDangerCount++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('MT submissions query failed: ' . $e->getMessage());
        }

        // Mark unviewed submissions as viewed (o'qituvchi jurnal sahifasini ko'rdi)
        try {
            if (!empty($independentIds ?? [])) {
                DB::table('independent_submissions')
                    ->whereIn('independent_id', $independentIds)
                    ->whereNull('viewed_at')
                    ->update(['viewed_at' => now()]);
            }
        } catch (\Exception $e) {}

        // Auto-archive: talaba qayta yuklagan bo'lsa, eski bahoni tarixga o'tkazish
        // Bu sahifa yuklanganda Tarix ustunida oldingi baholar ko'rinadi
        try {
            foreach ($mtSubmissions as $hemisId => $sub) {
                $gradeRow = $manualMtGradesRaw[$hemisId] ?? null;
                if (!$gradeRow || $gradeRow->grade >= MarkingSystemScore::getByStudentHemisId($hemisId)->minimum_limit) continue; // only for low grades

                $gradeTime = $gradeRow->updated_at ?? $gradeRow->created_at;
                if (!$gradeTime || !$sub->submitted_at) continue;
                if (!\Carbon\Carbon::parse($sub->submitted_at)->gt(\Carbon\Carbon::parse($gradeTime))) continue;

                // Student resubmitted after low grade — archive old grade
                $attemptCount = DB::table('mt_grade_history')
                    ->where('student_hemis_id', $hemisId)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->count();

                if ($attemptCount + 1 > $mtMaxResubmissions) continue; // limit reached

                $now = now();
                // Get the OLD submission file (before resubmission) from history or current
                // The current submission is the NEW one; we need the file that was graded
                // For first attempt: file info might have changed. Use file_path from old submission or grade context
                DB::table('mt_grade_history')->insert([
                    'student_hemis_id' => $hemisId,
                    'subject_id' => $subjectId,
                    'semester_code' => $semesterCode,
                    'attempt_number' => $attemptCount + 1,
                    'grade' => $gradeRow->grade,
                    'file_path' => $sub->file_path ?? null, // old file (before resubmission overwrite)
                    'file_original_name' => $sub->file_original_name ?? null,
                    'graded_by' => auth()->user()?->name ?? 'Admin',
                    'graded_at' => $gradeTime,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Clear old grade so teacher can enter new one
                DB::table('student_grades')->where('id', $gradeRow->id)->delete();

                // Update local data
                unset($manualMtGrades[$hemisId]);
                $manualMtGradesRaw->forget($hemisId);
                $mtGradeHistory[$hemisId] = DB::table('mt_grade_history')
                    ->where('student_hemis_id', $hemisId)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->orderBy('attempt_number')
                    ->get()
                    ->all();
            }
        } catch (\Exception $e) {
            \Log::warning('Auto-archive MT grade failed: ' . $e->getMessage());
        }

        // Get total academic load from curriculum subject
        $totalAcload = $subject->total_acload ?? 0;

        // Calculate auditorium hours from subject_details
        // Exclude: 17=Mustaqil ta'lim (auditoriya soatiga kirmaydi)
        $nonAuditoriumCodes = ['17'];
        $auditoriumHours = 0;
        if (is_array($subject->subject_details)) {
            foreach ($subject->subject_details as $detail) {
                $trainingCode = (string) (($detail['trainingType'] ?? [])['code'] ?? '');
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
        $facultyName = $faculty?->name ?? '';
        $facultyId = $faculty?->id ?? '';

        // Yo'nalish (Specialty) - from group
        $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();
        $specialtyId = $specialty?->specialty_hemis_id ?? '';
        $specialtyName = $specialty?->name ?? '';

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

        // ===== Dars ochish: o'tkazib yuborilgan kunlarni aniqlash =====
        // Jadvalda dars bor lekin haqiqiy baho qo'yilmagan kunlar.
        // Faqat effective (haqiqiy) bahosi bor yozuvlar hisobga olinadi.
        // Davomat yozuvlari (attendance) buni bloklamaydi — chunki davomat
        // belgilangan bo'lsa ham, baho qo'yilmagan bo'lishi mumkin.
        $jbGradeDates = collect($jbGradesRaw)->filter(fn($g) => $getEffectiveGrade($g) !== null)->pluck('lesson_date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))->unique()->toArray();
        $jbAttendanceDates = collect($jbAttendanceRaw)->pluck('lesson_date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))->unique()->toArray();

        $missedDates = [];
        $today = \Carbon\Carbon::now('Asia/Tashkent')->format('Y-m-d');
        foreach ($jbLessonDates as $date) {
            $dateStr = \Carbon\Carbon::parse($date)->format('Y-m-d');
            // Faqat o'tgan kunlarni tekshirish (bugundan oldingi)
            if ($dateStr >= $today) continue;
            // Haqiqiy baho yo'q bo'lsa — missed (davomat buni bloklamaydi)
            if (!in_array($dateStr, $jbGradeDates)) {
                $missedDates[] = $dateStr;
            }
        }

        // Mavjud dars ochilishlarini olish
        $lessonOpeningsMap = [];
        $lessonOpeningDays = 3;
        $activeOpenedDates = [];
        if (\Schema::hasTable('lesson_openings')) {
            LessonOpening::expireOverdue();
            $lessonOpenings = LessonOpening::getAllOpenings($group->group_hemis_id, $subjectId, $semesterCode);
            foreach ($lessonOpenings as $lo) {
                $loDateStr = $lo->lesson_date->format('Y-m-d');
                // Shu kun uchun qo'yilgan baholar statistikasi
                $dateGrades = $jbGradesRaw->filter(fn($g) => \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d') === $loDateStr && $g->grade !== null);
                $gradeCount = $dateGrades->count();
                $lastGradeAt = $gradeCount > 0 ? $dateGrades->max('created_at') : null;

                $lessonOpeningsMap[$loDateStr] = [
                    'id' => $lo->id,
                    'status' => $lo->isActive() ? 'active' : $lo->status,
                    'deadline' => $lo->deadline->format('Y-m-d H:i'),
                    'opened_at' => $lo->created_at->format('d.m.Y H:i'),
                    'opened_by_name' => $lo->opened_by_name,
                    'file_original_name' => $lo->file_original_name,
                    'grade_count' => $gradeCount,
                    'last_grade_at' => $lastGradeAt ? \Carbon\Carbon::parse($lastGradeAt)->format('d.m.Y H:i') : null,
                ];
            }

            // Dars ochish sozlamasi
            $lessonOpeningDays = (int) Setting::get('lesson_opening_days', 3);

            // O'qituvchi uchun: ochilgan va muddati tugamagan darslar
            $activeOpenedDates = LessonOpening::getActiveOpenings($group->group_hemis_id, $subjectId, $semesterCode);
        }

        // Marking system minimum limit for this group's curriculum
        $markingScore = $curriculum && $curriculum->marking_system_code
            ? MarkingSystemScore::where('marking_system_code', $curriculum->marking_system_code)->first()
            : null;
        $minimumLimit = $markingScore ? $markingScore->minimum_limit : 60;

        // Kurs darajasi bo'yicha deadline sozlamalari (o'qituvchi edit huquqi)
        $levelDeadline = $levelCode ? Deadline::where('level_code', $levelCode)->first() : null;

        // YN rozilik va yuborish ma'lumotlari
        $ynConsents = YnConsent::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $group->group_hemis_id)
            ->get()
            ->keyBy('student_hemis_id');

        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $group->group_hemis_id)
            ->first();

        // exam_schedules dan OSKI/Test sanalarini olish
        $examSchedule = ExamSchedule::where('group_hemis_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        // YN ga yuborish huquqi: faqat shu fanga biriktirilgan o'qituvchi
        $canSubmitYn = false;
        $isOqituvchi = is_active_oqituvchi();
        if ($isOqituvchi) {
            $teacherHemisId = get_teacher_hemis_id();
            if ($teacherHemisId) {
                $assignments = $this->getTeacherSubjectAssignments($teacherHemisId);
                $canSubmitYn = in_array($subjectId, $assignments['subject_ids'])
                    && in_array($group->group_hemis_id, $assignments['group_ids']);
            }
        }

        // Sababli: tasdiqlangan sababli hujjatlarni olish (YN yuborilgandan keyin)
        $approvedExcuses = collect();
        $excuseGradeSnapshots = collect();
        if ($ynSubmission) {
            try {
                $approvedExcuses = AbsenceExcuse::where('status', 'approved')
                    ->whereIn('student_hemis_id', $studentHemisIds)
                    ->whereHas('makeups', function ($q) use ($subjectId) {
                        $q->where('subject_id', $subjectId)
                            ->whereIn('assessment_type', ['jn', 'mt']);
                    })
                    ->with(['makeups' => function ($q) use ($subjectId) {
                        $q->where('subject_id', $subjectId);
                    }])
                    ->get()
                    ->keyBy('student_hemis_id');
            } catch (\Exception $e) {
                Log::warning('AbsenceExcuse query failed: ' . $e->getMessage());
            }

            try {
                // Avval kiritilgan sababli baholarni olish
                $excuseGradeSnapshots = YnStudentGrade::where('yn_submission_id', $ynSubmission->id)
                    ->where('source', 'like', 'absence_excuse:%')
                    ->get()
                    ->keyBy('student_hemis_id');
            } catch (\Exception $e) {
                Log::warning('YnStudentGrade excuse query failed: ' . $e->getMessage());
            }
        }

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
            'mtGradeHistory',
            'mtMaxResubmissions',
            'mtSubmissions',
            'mtUngradedCount',
            'mtDangerCount',
            'totalAcload',
            'auditoriumHours',
            'groupId',
            'subjectId',
            'semesterCode',
            'missedDates',
            'lessonOpeningsMap',
            'lessonOpeningDays',
            'activeOpenedDates',
            'minimumLimit',
            'ynConsents',
            'ynSubmission',
            'examSchedule',
            'canSubmitYn',
            'levelDeadline',
            'approvedExcuses',
            'excuseGradeSnapshots'
        ));
    }

    /**
     * O'qituvchi jurnaldan dars jadvalini HEMIS bilan sinxronlash (sinxron, guruh+fan bo'yicha)
     */
    public function syncSchedule(Request $request)
    {
        $data = $request->validate([
            'group_id' => 'required',
            'subject_id' => 'required',
        ]);

        $cacheKey = "schedule_sync_{$data['group_id']}_{$data['subject_id']}";
        if (Cache::has($cacheKey)) {
            $seconds = (int) Cache::get($cacheKey) - now()->timestamp;
            $minutes = ceil($seconds / 60);
            return response()->json([
                'success' => false,
                'message' => "Sinxronizatsiya 5 daqiqada 1 marta mumkin. ~{$minutes} daqiqa kuting.",
            ], 429);
        }

        $userName = auth()->user()?->name ?? auth()->guard('teacher')->user()?->name ?? 'Noma\'lum';

        try {
            $service = app(ScheduleImportService::class);
            $result = $service->importForGroupSubject((int) $data['group_id'], (int) $data['subject_id']);

            Cache::put($cacheKey, now()->addMinutes(5)->timestamp, 300);

            ActivityLogService::log('import', 'schedule',
                "Jurnal orqali jadval sinxronizatsiyasi: {$userName} (guruh: {$data['group_id']}, fan: {$data['subject_id']}) — {$result['count']} ta yozuv"
            );

            if ($result['failed'] ?? false) {
                return response()->json([
                    'success' => false,
                    'message' => "HEMIS API xatolik berdi. Mavjud jadvallar saqlab qolindi. Keyinroq urinib ko'ring.",
                ], 502);
            }

            // Talabalar ro'yxatini sinxronlash (davomat va baholardan OLDIN)
            $hemisService = app(HemisService::class);
            $studentResult = $hemisService->importStudentsForGroup((int) $data['group_id']);

            // Attendance (sababli/sababsiz) sinxronizatsiyasi
            $attendanceResult = $this->syncAttendanceForGroupSubject((int) $data['group_id'], (int) $data['subject_id']);

            // Baholar sinxronizatsiyasi (HEMIS dan grade larni yangilash)
            $gradeResult = $this->syncGradesForGroupSubject((int) $data['group_id'], (int) $data['subject_id']);

            $message = "Jadval yangilandi. {$result['count']} ta yozuv sinxronlandi.";
            if ($studentResult['imported'] > 0) {
                $message .= " Talabalar: {$studentResult['imported']} ta sinxronlandi.";
            }
            if ($studentResult['deactivated'] > 0) {
                $message .= " {$studentResult['deactivated']} ta talaba chetlashgan deb belgilandi.";
            }
            $totalItems = $attendanceResult['total_api_items'] ?? 0;
            $nbCreated = $attendanceResult['absence_grades_created'] ?? 0;
            if ($attendanceResult['synced'] > 0 || $totalItems > 0) {
                $message .= " Davomat: API={$totalItems}, NB={$attendanceResult['synced']}, yangi_NB={$nbCreated}.";
            }
            if ($attendanceResult['retake_recalculated'] > 0) {
                $message .= " {$attendanceResult['retake_recalculated']} ta otrabotka bahosi qayta hisoblandi.";
            }
            if ($gradeResult['synced'] > 0 || $gradeResult['created'] > 0) {
                $message .= " Baholar: {$gradeResult['synced']} ta yangilandi, {$gradeResult['created']} ta yangi qo'shildi.";
            }

            // Fallback debug info — foydalanuvchiga ko'rsatish
            $fbDebug = $gradeResult['fallback_debug'] ?? [];
            if (!empty($fbDebug)) {
                $fbSummary = [];
                foreach ($fbDebug as $date => $info) {
                    if (is_string($info)) {
                        $fbSummary[] = "{$date}: {$info}";
                    } else {
                        $fbSummary[] = "{$date}: API={$info['api_total']}, fan={$info['matched_subject']}, juftliklar=" . implode(',', $info['all_pairs'] ?? []);
                    }
                }
                $message .= " [Fallback: " . implode('; ', $fbSummary) . "]";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sinxronizatsiyada xatolik: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guruh + fan bo'yicha HEMIS'dan davomat (sababli/sababsiz) sinxronlash
     * va o'zgargan retake baholarini qayta hisoblash
     */
    private function syncAttendanceForGroupSubject(int $groupId, int $subjectId): array
    {
        $token = config('services.hemis.token');
        $synced = 0;
        $retakeRecalculated = 0;
        $absenceGradesCreated = 0;
        $totalApiItems = 0;

        // Talabalar map (hemis_id → student row) — NB uchun student_grades yaratishda kerak
        $studentsMap = DB::table('students')
            ->where('group_id', $groupId)
            ->get()
            ->keyBy('hemis_id');

        try {
            $page = 1;
            $pages = 1;

            do {
                $response = Http::withoutVerifying()
                    ->timeout(30)
                    ->withToken($token)
                    ->get('https://student.ttatf.uz/rest/v1/data/attendance-list', [
                        '_group' => $groupId,
                        '_subject' => $subjectId,
                        'limit' => 200,
                        'page' => $page,
                    ]);

                if (!$response || !$response->successful()) {
                    Log::warning('Attendance sync: HEMIS API xatolik', [
                        'group_id' => $groupId,
                        'subject_id' => $subjectId,
                        'page' => $page,
                        'status' => $response ? $response->status() : 'timeout',
                    ]);
                    break;
                }

                $data = $response->json('data', []);
                $items = $data['items'] ?? [];
                $pages = $data['pagination']['pageCount'] ?? 1;

                $totalApiItems += count($items);

                foreach ($items as $item) {
                    if (($item['absent_off'] ?? 0) <= 0 && ($item['absent_on'] ?? 0) <= 0) {
                        continue;
                    }

                    $lessonDate = isset($item['lesson_date']) ? \Carbon\Carbon::createFromTimestamp($item['lesson_date']) : null;
                    if (!$lessonDate) {
                        continue;
                    }

                    // Attendance recordni yangilash yoki yaratish
                    $attendance = DB::table('attendances')
                        ->where('hemis_id', $item['id'])
                        ->first();

                    $oldAbsentOn = $attendance ? (int) $attendance->absent_on : null;
                    $newAbsentOn = (int) ($item['absent_on'] ?? 0);
                    $newAbsentOff = (int) ($item['absent_off'] ?? 0);

                    DB::table('attendances')->updateOrInsert(
                        ['hemis_id' => $item['id']],
                        [
                            'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                            'student_hemis_id' => $item['student']['id'],
                            'student_name' => $item['student']['name'],
                            'employee_id' => $item['employee']['id'],
                            'employee_name' => $item['employee']['name'],
                            'subject_id' => $item['subject']['id'],
                            'subject_name' => $item['subject']['name'],
                            'subject_code' => $item['subject']['code'],
                            'education_year_code' => $item['educationYear']['code'] ?? null,
                            'education_year_name' => $item['educationYear']['name'] ?? null,
                            'education_year_current' => $item['educationYear']['current'] ?? null,
                            'semester_code' => $item['semester']['code'],
                            'semester_name' => $item['semester']['name'],
                            'group_id' => $item['group']['id'],
                            'group_name' => $item['group']['name'],
                            'education_lang_code' => $item['group']['educationLang']['code'] ?? null,
                            'education_lang_name' => $item['group']['educationLang']['name'] ?? null,
                            'training_type_code' => $item['trainingType']['code'],
                            'training_type_name' => $item['trainingType']['name'],
                            'lesson_pair_code' => $item['lessonPair']['code'],
                            'lesson_pair_name' => $item['lessonPair']['name'],
                            'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                            'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                            'absent_on' => $newAbsentOn,
                            'absent_off' => $newAbsentOff,
                            'lesson_date' => $lessonDate,
                            'status' => 'absent',
                            'updated_at' => now(),
                        ]
                    );

                    $synced++;

                    // student_grades ga NB yozuv yaratish (agar mavjud bo'lmasa)
                    // Jurnal NB ni student_grades.reason='absent' dan o'qiydi
                    $studentHemisId = $item['student']['id'];
                    $student = $studentsMap->get($studentHemisId);
                    if ($student) {
                        $existingGrade = DB::table('student_grades')
                            ->where('student_id', $student->id)
                            ->where('subject_id', $item['subject']['id'])
                            ->whereDate('lesson_date', $lessonDate->format('Y-m-d'))
                            ->where('lesson_pair_code', $item['lessonPair']['code'])
                            ->where('training_type_code', $item['trainingType']['code'])
                            ->whereNull('deleted_at')
                            ->first();

                        if (!$existingGrade) {
                            $deadline = DB::table('deadlines')->where('level_code', $student->level_code)->first();
                            $deadlineDays = $deadline ? $deadline->deadline_days : 7;

                            DB::table('student_grades')->insert([
                                'hemis_id' => 111,
                                'student_id' => $student->id,
                                'student_hemis_id' => $studentHemisId,
                                'semester_code' => $item['semester']['code'],
                                'semester_name' => $item['semester']['name'],
                                'education_year_code' => $item['educationYear']['code'] ?? null,
                                'education_year_name' => $item['educationYear']['name'] ?? null,
                                'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                                'subject_id' => $item['subject']['id'],
                                'subject_name' => $item['subject']['name'],
                                'subject_code' => $item['subject']['code'],
                                'training_type_code' => $item['trainingType']['code'],
                                'training_type_name' => $item['trainingType']['name'],
                                'employee_id' => $item['employee']['id'],
                                'employee_name' => $item['employee']['name'],
                                'lesson_pair_code' => $item['lessonPair']['code'],
                                'lesson_pair_name' => $item['lessonPair']['name'],
                                'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                                'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                                'grade' => null,
                                'lesson_date' => $lessonDate,
                                'created_at_api' => now(),
                                'reason' => 'absent',
                                'deadline' => $lessonDate->copy()->addDays($deadlineDays)->endOfDay(),
                                'status' => 'pending',
                                'is_final' => !$lessonDate->isToday(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $absenceGradesCreated++;
                        }
                    }

                    // Agar sababli holat o'zgargan bo'lsa — retake bahosini qayta hisoblash
                    if ($oldAbsentOn !== null && $oldAbsentOn !== $newAbsentOn) {
                        $recalculated = $this->recalculateRetakeGrade(
                            $item['student']['id'],
                            $subjectId,
                            $lessonDate->format('Y-m-d'),
                            $item['lessonPair']['code'],
                            $newAbsentOn > 0
                        );
                        if ($recalculated) {
                            $retakeRecalculated++;
                        }
                    }
                }

                $page++;
                if ($page <= $pages) {
                    usleep(500000); // 0.5s
                }
            } while ($page <= $pages);

        } catch (\Throwable $e) {
            Log::error('Attendance sync xatolik: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'subject_id' => $subjectId,
            ]);
        }

        return [
            'synced' => $synced,
            'retake_recalculated' => $retakeRecalculated,
            'absence_grades_created' => $absenceGradesCreated,
            'total_api_items' => $totalApiItems,
        ];
    }

    /**
     * Sababli holat o'zgarganda retake bahosini qayta hisoblash
     */
    private function recalculateRetakeGrade(
        string $studentHemisId,
        int $subjectId,
        string $lessonDate,
        string $lessonPairCode,
        bool $isExcused
    ): bool {
        $grade = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->whereDate('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $lessonPairCode)
            ->where('reason', 'absent')
            ->where('status', 'retake')
            ->whereNotNull('retake_grade')
            ->first();

        if (!$grade) {
            return false;
        }

        // Hozirgi foiz va yangi foizni aniqlash
        $oldPercentage = $isExcused ? 0.8 : 1.0; // o'zgarishdan oldingi (teskari)
        $newPercentage = $isExcused ? 1.0 : 0.8;

        // Asl kiritilgan bahoni qayta hisoblash
        if ($oldPercentage > 0) {
            $originalEnteredGrade = round($grade->retake_grade / $oldPercentage, 2);
        } else {
            return false;
        }

        $newRetakeGrade = round($originalEnteredGrade * $newPercentage, 2);

        // Agar baho o'zgargan bo'lsa — yangilash
        if (abs($newRetakeGrade - $grade->retake_grade) > 0.01) {
            DB::table('student_grades')
                ->where('id', $grade->id)
                ->update([
                    'retake_grade' => $newRetakeGrade,
                    'updated_at' => now(),
                ]);
            return true;
        }

        return false;
    }

    /**
     * Guruh + fan bo'yicha HEMIS'dan baholarni sinxronlash
     */
    private function syncGradesForGroupSubject(int $groupId, int $subjectId): array
    {
        $token = config('services.hemis.token');
        $baseUrl = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz/rest'), '/');
        if (!str_contains($baseUrl, '/v1')) {
            $baseUrl .= '/v1';
        }

        $synced = 0;
        $created = 0;

        try {
            // Guruh talabalarini oldindan yuklash (hemis_id → student row)
            $students = DB::table('students')
                ->where('group_id', $groupId)
                ->get()
                ->keyBy('hemis_id');

            // Grade item ni bazaga yozish uchun yordamchi funksiya (takrorlanmaslik uchun)
            $processGradeItem = function (array $item) use ($students, &$synced, &$created) {
                $studentHemisId = $item['_student'] ?? null;
                if (!$studentHemisId) return;

                $student = $students[$studentHemisId] ?? null;
                if (!$student) return;

                $gradeValue = $item['grade'] ?? null;
                $lessonDate = isset($item['lesson_date'])
                    ? \Carbon\Carbon::createFromTimestamp($item['lesson_date'])
                    : null;
                if (!$lessonDate) return;

                $hemisId = $item['id'];

                // Mavjud grade tekshirish (faqat AKTIV yozuvlar — soft-deleted larni tiklamaslik)
                $existingGrade = DB::table('student_grades')
                    ->where('hemis_id', $hemisId)
                    ->whereNull('deleted_at')
                    ->first();

                $markingScore = MarkingSystemScore::getByStudentHemisId($studentHemisId);
                $isLowGrade = ($student->level_code == 16 && $gradeValue < 3)
                    || ($student->level_code != 16 && $gradeValue < $markingScore->minimum_limit);

                if ($existingGrade) {
                    $updateData = [
                        'grade' => $gradeValue,
                        'employee_id' => $item['employee']['id'],
                        'employee_name' => $item['employee']['name'],
                        'updated_at' => now(),
                    ];

                    if ($existingGrade->status !== 'retake' && $existingGrade->reason !== 'absent') {
                        $updateData['status'] = $isLowGrade ? 'pending' : 'recorded';
                        $updateData['reason'] = $isLowGrade ? 'low_grade' : null;
                        if ($isLowGrade && !$existingGrade->deadline) {
                            $deadline = DB::table('deadlines')->where('level_code', $student->level_code)->first();
                            $deadlineDays = $deadline ? $deadline->deadline_days : 7;
                            $updateData['deadline'] = $lessonDate->copy()->addDays($deadlineDays)->endOfDay();
                        }
                    }

                    DB::table('student_grades')
                        ->where('id', $existingGrade->id)
                        ->update($updateData);
                    $synced++;
                } else {
                    $deadline = null;
                    $status = 'recorded';
                    $reason = null;

                    if ($isLowGrade) {
                        $status = 'pending';
                        $reason = 'low_grade';
                        $deadlineRow = DB::table('deadlines')->where('level_code', $student->level_code)->first();
                        $deadlineDays = $deadlineRow ? $deadlineRow->deadline_days : 7;
                        $deadline = $lessonDate->copy()->addDays($deadlineDays)->endOfDay();
                    }

                    DB::table('student_grades')->insert([
                        'hemis_id' => $hemisId,
                        'student_id' => $student->id,
                        'student_hemis_id' => $studentHemisId,
                        'semester_code' => $item['semester']['code'],
                        'semester_name' => $item['semester']['name'],
                        'education_year_code' => $item['educationYear']['code'] ?? null,
                        'education_year_name' => $item['educationYear']['name'] ?? null,
                        'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                        'subject_id' => $item['subject']['id'],
                        'subject_name' => $item['subject']['name'],
                        'subject_code' => $item['subject']['code'],
                        'training_type_code' => $item['trainingType']['code'],
                        'training_type_name' => $item['trainingType']['name'],
                        'employee_id' => $item['employee']['id'],
                        'employee_name' => $item['employee']['name'],
                        'lesson_pair_code' => $item['lessonPair']['code'],
                        'lesson_pair_name' => $item['lessonPair']['name'],
                        'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                        'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                        'grade' => $gradeValue,
                        'lesson_date' => $lessonDate,
                        'created_at_api' => isset($item['created_at'])
                            ? \Carbon\Carbon::createFromTimestamp($item['created_at'])
                            : now(),
                        'reason' => $reason,
                        'deadline' => $deadline,
                        'status' => $status,
                        'is_final' => !$lessonDate->isToday(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            };

            // === 1-QADAM: Asosiy sync (_group + _subject) ===
            $page = 1;
            $pages = 1;
            $mainApiItemCount = 0;
            $mainApiPairs = [];

            do {
                $response = Http::withoutVerifying()
                    ->timeout(30)
                    ->withToken($token)
                    ->get($baseUrl . '/data/student-grade-list', [
                        '_group' => $groupId,
                        '_subject' => $subjectId,
                        'limit' => 200,
                        'page' => $page,
                    ]);

                if (!$response || !$response->successful()) {
                    Log::warning('Grade sync: HEMIS API xatolik', [
                        'group_id' => $groupId,
                        'subject_id' => $subjectId,
                        'page' => $page,
                        'status' => $response ? $response->status() : 'timeout',
                    ]);
                    break;
                }

                $data = $response->json('data', []);
                $items = $data['items'] ?? [];
                $pages = $data['pagination']['pageCount'] ?? 1;
                $mainApiItemCount += count($items);

                foreach ($items as $item) {
                    $pairCode = $item['lessonPair']['code'] ?? '?';
                    $dateStr = isset($item['lesson_date']) ? date('Y-m-d', $item['lesson_date']) : '?';
                    $mainApiPairs[$dateStr . '_' . $pairCode] = true;
                    $processGradeItem($item);
                }

                $page++;
                if ($page <= $pages) {
                    usleep(500000);
                }
            } while ($page <= $pages);

            Log::info("Grade sync 1-qadam: {$groupId}/{$subjectId}", [
                'api_total_items' => $mainApiItemCount,
                'api_pages' => $pages,
                'api_date_pairs' => array_keys($mainApiPairs),
                'synced' => $synced,
                'created' => $created,
            ]);

            // === 2-QADAM: Fallback — jadvalda bahosi yo'q kunlar uchun _group + sana orqali olish ===
            // HEMIS API _subject bilan hamma baholarni qaytarmasligi mumkin (turli subject_schedule),
            // shuning uchun bahosi yo'q (date+pair) lar uchun _group + lesson_date_from/to bilan olish
            $scheduleDatePairs = DB::table('schedules')
                ->where('group_id', $groupId)
                ->where('subject_id', $subjectId)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
                ->select(DB::raw('DATE(lesson_date) as lesson_date_str'), 'lesson_pair_code', 'training_type_code')
                ->distinct()
                ->get();

            Log::info("Grade sync 2-qadam: jadval tekshiruvi", [
                'group_id' => $groupId,
                'subject_id' => $subjectId,
                'schedule_date_pairs' => $scheduleDatePairs->map(fn($sp) => "{$sp->lesson_date_str}_{$sp->lesson_pair_code}(tt:{$sp->training_type_code})")->toArray(),
            ]);

            $fallbackDebug = [];

            if ($scheduleDatePairs->isNotEmpty()) {
                $studentHemisIds = $students->keys()->toArray();
                $existingGradeKeys = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $studentHemisIds)
                    ->where('subject_id', $subjectId)
                    ->whereNotNull('lesson_date')
                    ->whereNotNull('grade')
                    ->selectRaw("CONCAT(DATE(lesson_date), '_', lesson_pair_code) as dp")
                    ->distinct()
                    ->pluck('dp')
                    ->flip()
                    ->toArray();

                Log::info("Grade sync 2-qadam: mavjud baholar", [
                    'existing_grade_keys' => array_keys($existingGradeKeys),
                ]);

                $missingDatePairs = [];
                $missingDates = [];
                foreach ($scheduleDatePairs as $sp) {
                    $key = $sp->lesson_date_str . '_' . $sp->lesson_pair_code;
                    if (!isset($existingGradeKeys[$key])) {
                        $missingDatePairs[] = $key;
                        $missingDates[$sp->lesson_date_str] = true;
                    }
                }

                if (!empty($missingDates)) {
                    Log::info("Grade sync fallback: {$groupId}/{$subjectId} — " . count($missingDates) . " ta sanada baholar yo'q", [
                        'missing_date_pairs' => $missingDatePairs,
                        'missing_dates' => array_keys($missingDates),
                    ]);

                    $syncedBefore = $synced;
                    $createdBefore = $created;
                    foreach (array_keys($missingDates) as $missingDate) {
                        $dateCarbon = \Carbon\Carbon::parse($missingDate);
                        $fromTs = $dateCarbon->copy()->startOfDay()->timestamp;
                        $toTs = $dateCarbon->copy()->endOfDay()->timestamp;

                        $fbPage = 1;
                        $fbPages = 1;
                        $fbTotalItems = 0;
                        $fbMatchedItems = 0;
                        $fbAllSubjects = [];
                        $fbAllPairs = [];

                        do {
                            $fbResponse = Http::withoutVerifying()
                                ->timeout(30)
                                ->withToken($token)
                                ->get($baseUrl . '/data/student-grade-list', [
                                    '_group' => $groupId,
                                    'lesson_date_from' => $fromTs,
                                    'lesson_date_to' => $toTs,
                                    'limit' => 200,
                                    'page' => $fbPage,
                                ]);

                            if (!$fbResponse || !$fbResponse->successful()) {
                                Log::warning("Grade sync fallback: API xatolik — {$missingDate}", [
                                    'group_id' => $groupId,
                                    'status' => $fbResponse ? $fbResponse->status() : 'timeout',
                                    'body' => $fbResponse ? substr($fbResponse->body(), 0, 300) : 'N/A',
                                    'from_ts' => $fromTs,
                                    'to_ts' => $toTs,
                                ]);
                                $fallbackDebug[$missingDate] = 'API_ERROR:' . ($fbResponse ? $fbResponse->status() : 'timeout');
                                break;
                            }

                            $fbData = $fbResponse->json('data', []);
                            $fbItems = $fbData['items'] ?? [];
                            $fbPages = $fbData['pagination']['pageCount'] ?? 1;
                            $fbTotalItems += count($fbItems);

                            foreach ($fbItems as $fbItem) {
                                $fbSubjectId = $fbItem['subject']['id'] ?? null;
                                $fbPairCode = $fbItem['lessonPair']['code'] ?? null;
                                $fbAllSubjects[$fbSubjectId] = ($fbItem['subject']['name'] ?? '?');
                                $fbAllPairs[$fbPairCode] = true;

                                // Faqat kerakli fan uchun filtrlash
                                if ($fbSubjectId != $subjectId) continue;
                                $fbMatchedItems++;
                                $processGradeItem($fbItem);
                            }

                            $fbPage++;
                            if ($fbPage <= $fbPages) {
                                usleep(500000);
                            }
                        } while ($fbPage <= $fbPages);

                        $fallbackDebug[$missingDate] = [
                            'api_total' => $fbTotalItems,
                            'matched_subject' => $fbMatchedItems,
                            'all_subjects' => $fbAllSubjects,
                            'all_pairs' => array_keys($fbAllPairs),
                        ];

                        Log::info("Grade sync fallback: {$missingDate} natijalari", [
                            'group_id' => $groupId,
                            'subject_id' => $subjectId,
                            'from_ts' => $fromTs,
                            'to_ts' => $toTs,
                            'api_total_items' => $fbTotalItems,
                            'matched_subject_items' => $fbMatchedItems,
                            'all_subjects_in_response' => $fbAllSubjects,
                            'all_pairs_in_response' => array_keys($fbAllPairs),
                        ]);
                    }

                    $fallbackSynced = $synced - $syncedBefore;
                    $fallbackCreated = $created - $createdBefore;
                    Log::info("Grade sync fallback yakuniy: {$groupId}/{$subjectId}", [
                        'fallback_synced' => $fallbackSynced,
                        'fallback_created' => $fallbackCreated,
                        'debug' => $fallbackDebug,
                    ]);
                } else {
                    Log::info("Grade sync: hamma baholar mavjud, fallback kerak emas", [
                        'group_id' => $groupId,
                        'subject_id' => $subjectId,
                    ]);
                }
            } else {
                Log::info("Grade sync: jadvalda dars topilmadi (schedules bo'sh)", [
                    'group_id' => $groupId,
                    'subject_id' => $subjectId,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Grade sync xatolik: ' . $e->getMessage(), [
                'group_id' => $groupId,
                'subject_id' => $subjectId,
            ]);
        }

        return [
            'synced' => $synced,
            'created' => $created,
            'fallback_debug' => $fallbackDebug ?? [],
        ];
    }

    /**
     * Save manual MT grade for a student
     */
    public function saveMtGrade(Request $request)
    {
        // Dekan va registrator faqat ko'rish huquqiga ega
        if (is_active_dekan() || is_active_registrator()) {
            return response()->json(['success' => false, 'message' => 'Sizda tahrirlash huquqi yo\'q'], 403);
        }

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
        $gradeComment = $request->input('grade_comment');
        $isRegrade = (bool) $request->input('regrade', false);

        // YN ga yuborilganligini tekshirish — qulflangan bo'lsa tahrirlash mumkin emas
        $ynLocked = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                'yn_locked' => true,
            ], 403);
        }

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

        // Check if student has uploaded a file for this subject's MT assignment
        // Search across all curriculum_subject_hemis_ids for this subject (cross-curriculum support)
        $allCsHemisIds = DB::table('curriculum_subjects')
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->pluck('curriculum_subject_hemis_id')
            ->toArray();

        $independents = DB::table('independents')
            ->where('group_hemis_id', $student->group_id)
            ->where('semester_code', $semesterCode)
            ->where(function ($q) use ($allCsHemisIds, $subject) {
                $q->whereIn('subject_hemis_id', !empty($allCsHemisIds) ? $allCsHemisIds : [0])
                  ->orWhere('subject_name', $subject->subject_name);
            })
            ->get();

        $independentIds = $independents->pluck('id')->toArray();
        $matchedIndependentId = $independents->first()?->id;

        $studentSubmission = null;
        if (!empty($independentIds)) {
            $studentSubmission = DB::table('independent_submissions')
                ->whereIn('independent_id', $independentIds)
                ->where('student_hemis_id', $studentHemisId)
                ->orderByDesc('submitted_at')
                ->first();

            // Track which independent the submission belongs to
            if ($studentSubmission) {
                $matchedIndependentId = $studentSubmission->independent_id;
            }
        }

        if (!$studentSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'Talaba fayl yuklamagan. Baholardan oldin fayl yuklashi kerak.',
                'no_file' => true,
            ], 422);
        }

        // Check if a manual MT grade already exists for this student/subject/semester
        $existingGrade = DB::table('student_grades')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->first();

        // If existing grade >= minimum_limit, it's permanently locked
        $minimumLimit = MarkingSystemScore::getByStudentHemisId($studentHemisId)->minimum_limit;
        if ($existingGrade && $existingGrade->grade >= $minimumLimit) {
            return response()->json([
                'success' => false,
                'locked' => true,
                'can_regrade' => false,
                'message' => 'Baho qulflangan (>= ' . $minimumLimit . '). O\'zgartirib bo\'lmaydi.',
                'grade' => $existingGrade->grade,
            ], 403);
        }

        // If existing grade exists but not a regrade request — it's locked
        if ($existingGrade && !$isRegrade) {
            // Count previous attempts
            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();
            $currentAttempt = $attemptCount + 1;
            $maxResubmissions = (int) \App\Models\Setting::get('mt_max_resubmissions', 3);

            // Check if student has resubmitted after the grade
            $hasResubmitted = false;
            if ($studentSubmission && $existingGrade->grade < $minimumLimit) {
                $gradeTime = $existingGrade->updated_at ?? $existingGrade->created_at;
                if ($gradeTime && $studentSubmission->submitted_at) {
                    $hasResubmitted = \Carbon\Carbon::parse($studentSubmission->submitted_at)
                        ->gt(\Carbon\Carbon::parse($gradeTime));
                }
            }

            return response()->json([
                'success' => false,
                'locked' => true,
                'can_regrade' => $hasResubmitted && $existingGrade->grade < $minimumLimit && $currentAttempt <= $maxResubmissions,
                'message' => 'Baho allaqachon qo\'yilgan.',
                'grade' => $existingGrade->grade,
                'attempt' => $currentAttempt,
                'max_attempts' => $maxResubmissions,
            ], 403);
        }

        $now = now();

        // Get education year: prefer schedule-based (same logic as show() method)
        $curriculum = DB::table('curricula')
            ->where('curricula_hemis_id', $student->curriculum_id ?? null)
            ->first();
        $educationYearCode = $curriculum?->education_year_code;
        $educationYearName = $curriculum?->education_year_name;

        // Override with schedule's education_year_code if available (matches show() query)
        $scheduleEduYear = DB::table('schedules')
            ->where('group_id', $student->group_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->first(['education_year_code', 'education_year_name']);
        if ($scheduleEduYear) {
            $educationYearCode = $scheduleEduYear->education_year_code;
            $educationYearName = $scheduleEduYear->education_year_name ?? $educationYearName;
        }

        if ($existingGrade && $isRegrade) {
            // Verify student has resubmitted after the last grade
            $gradeTime = $existingGrade->updated_at ?? $existingGrade->created_at;
            $hasResubmitted = false;
            if ($gradeTime && $studentSubmission->submitted_at) {
                $hasResubmitted = \Carbon\Carbon::parse($studentSubmission->submitted_at)
                    ->gt(\Carbon\Carbon::parse($gradeTime));
            }
            if (!$hasResubmitted) {
                return response()->json([
                    'success' => false,
                    'locked' => true,
                    'can_regrade' => false,
                    'message' => 'Talaba hali qayta fayl yuklamagan. Qayta baholash mumkin emas.',
                    'grade' => $existingGrade->grade,
                ], 403);
            }

            // Re-grading: archive old grade to history first
            $attemptCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();

            $maxResubmissions = (int) \App\Models\Setting::get('mt_max_resubmissions', 3);
            $currentAttempt = $attemptCount + 1; // current grade's attempt number

            if ($currentAttempt > $maxResubmissions) {
                return response()->json([
                    'success' => false,
                    'locked' => true,
                    'can_regrade' => false,
                    'message' => "Qayta baholash limiti tugagan ({$maxResubmissions} urinish).",
                    'grade' => $existingGrade->grade,
                ], 403);
            }

            // Archive current grade to history (with file info)
            DB::table('mt_grade_history')->insert([
                'student_hemis_id' => $studentHemisId,
                'subject_id' => $subjectId,
                'semester_code' => $semesterCode,
                'attempt_number' => $currentAttempt,
                'grade' => $existingGrade->grade,
                'file_path' => $studentSubmission->file_path ?? null,
                'file_original_name' => $studentSubmission->file_original_name ?? null,
                'graded_by' => auth()->user()?->name ?? 'Admin',
                'graded_at' => $existingGrade->updated_at ?? $existingGrade->created_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $newAttempt = $currentAttempt + 1;

            // Update existing grade with new value
            DB::table('student_grades')
                ->where('id', $existingGrade->id)
                ->update([
                    'grade' => $grade,
                    'grade_comment' => $gradeComment,
                    'updated_at' => $now,
                ]);
        } elseif (!$existingGrade) {
            // Attempt number = history count + 1 (could be 1st or resubmission after auto-archive)
            $historyCount = DB::table('mt_grade_history')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->count();
            $newAttempt = $historyCount + 1;
            // Insert manual grade
            DB::table('student_grades')->insert([
                'hemis_id' => 0,
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
                'training_type_code' => 99,
                'training_type_name' => "Mustaqil ta'lim",
                'employee_id' => 0,
                'employee_name' => 'Manual Entry',
                'lesson_pair_code' => '1',
                'lesson_pair_name' => 'Manual',
                'lesson_pair_start_time' => '00:00',
                'lesson_pair_end_time' => '00:00',
                'grade' => $grade,
                'grade_comment' => $gradeComment,
                'lesson_date' => null,
                'independent_id' => $matchedIndependentId,
                'created_at_api' => $now,
                'status' => 'recorded',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Get updated history for response
        $history = DB::table('mt_grade_history')
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->orderBy('attempt_number')
            ->get()
            ->map(fn($h) => [
                'id' => $h->id,
                'attempt' => $h->attempt_number,
                'grade' => round($h->grade),
                'has_file' => !empty($h->file_path),
            ])
            ->toArray();

        $maxResubmissions = (int) \App\Models\Setting::get('mt_max_resubmissions', 3);

        return response()->json([
            'success' => true,
            'message' => 'Baho saqlandi',
            'locked' => true,
            'can_regrade' => false, // Talaba qayta yuklamaguncha qayta baholab bo'lmaydi
            'waiting_resubmit' => $grade < $minimumLimit && ($newAttempt ?? 1) <= $maxResubmissions,
            'grade' => $grade,
            'attempt' => $newAttempt ?? 1,
            'max_attempts' => $maxResubmissions,
            'history' => $history,
        ]);
    }

    /**
     * Build custom download filename: "FISH FanNomi_MT[_vN].ext"
     */
    private function buildMtFileName(string $studentHemisId, string $subjectId, string $semesterCode, string $originalName, ?int $attempt = null): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);

        $student = DB::table('students')->where('hemis_id', $studentHemisId)->first();
        $fullName = $student->full_name ?? 'Talaba';

        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();
        $subjectName = $subject->subject_name ?? 'Fan';

        // Sanitize names for filename (remove special chars but keep spaces in FISH)
        $fullName = preg_replace('/[\/\\\\:*?"<>|]/', '', $fullName);
        $subjectName = preg_replace('/[\/\\\\:*?"<>|]/', '', $subjectName);

        $mtPart = 'MT';
        if ($attempt && $attempt > 1) {
            $mtPart = 'MT_v' . $attempt;
        }

        return "{$fullName} {$subjectName}_{$mtPart}.{$ext}";
    }

    /**
     * Download student submission file with custom name
     */
    public function downloadSubmission($submissionId)
    {
        $submission = DB::table('independent_submissions')->where('id', $submissionId)->first();
        if (!$submission) {
            abort(404, 'Fayl topilmadi');
        }

        $filePath = storage_path('app/public/' . $submission->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        // Determine attempt number (current = history count + 1)
        $independent = DB::table('independents')->where('id', $submission->independent_id)->first();
        $subjectHemisId = $independent->subject_hemis_id ?? 0;
        $semesterCode = $independent->semester_code ?? '';

        $subject = CurriculumSubject::where('curriculum_subject_hemis_id', $subjectHemisId)
            ->where('semester_code', $semesterCode)
            ->first();
        $subjectId = $subject->subject_id ?? '';

        $attemptCount = DB::table('mt_grade_history')
            ->where('student_hemis_id', $submission->student_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->count();
        $currentAttempt = $attemptCount + 1;

        $downloadName = $this->buildMtFileName(
            $submission->student_hemis_id,
            $subjectId,
            $semesterCode,
            $submission->file_original_name,
            $currentAttempt
        );

        return response()->download($filePath, $downloadName);
    }

    /**
     * Download history file with custom name
     */
    public function downloadHistoryFile($historyId)
    {
        $history = DB::table('mt_grade_history')->where('id', $historyId)->first();
        if (!$history || !$history->file_path) {
            abort(404, 'Fayl topilmadi');
        }

        $filePath = storage_path('app/public/' . $history->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        $downloadName = $this->buildMtFileName(
            $history->student_hemis_id,
            $history->subject_id,
            $history->semester_code,
            $history->file_original_name,
            $history->attempt_number
        );

        return response()->download($filePath, $downloadName);
    }

    /**
     * Save retake grade for a student from journal
     */
    public function saveRetakeGrade(Request $request)
    {
        // Check admin or teacher role
        $isAdmin = auth()->user()->hasAnyRole(['admin', 'superadmin']);
        $isTeacher = is_active_oqituvchi();

        if (!$isAdmin && !$isTeacher) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'grade_id' => 'required|integer',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        try {
            $gradeId = $request->grade_id;
            $enteredGrade = $request->grade;

            // Get the student grade record
            $studentGrade = DB::table('student_grades')
                ->where('id', $gradeId)
                ->first();

            if (!$studentGrade) {
                return response()->json(['success' => false, 'message' => 'Baho yozuvi topilmadi: id=' . $gradeId], 404);
            }

            // O'qituvchi uchun: biriktirilgan + Deadline sozlamasi + faqat NB/<60
            if ($isTeacher && !$isAdmin) {
                // Faqat NB (absent) yoki past baho (<60) larga ruxsat
                $isAbsentReason = $studentGrade->reason === 'absent';
                $isLowGrade = $studentGrade->reason === 'low_grade'
                    || ($studentGrade->grade !== null && round((float)$studentGrade->grade) < 60);

                if (!$isAbsentReason && !$isLowGrade) {
                    return response()->json(['success' => false, 'message' => 'O\'qituvchi faqat NB va past baholarni tahrirlashi mumkin.'], 403);
                }

                // Biriktirilgan o'qituvchini tekshirish
                $teacherHemisId = get_teacher_hemis_id();
                $student = DB::table('students')->where('hemis_id', $studentGrade->student_hemis_id)->first();
                $groupHemisId = $student?->group_id;

                if (!$teacherHemisId) {
                    return response()->json(['success' => false, 'message' => 'O\'qituvchi topilmadi (hemis_id null)'], 403);
                }

                $isAssigned = \App\Models\CurriculumSubjectTeacher::where('employee_id', $teacherHemisId)
                    ->where('subject_id', $studentGrade->subject_id)
                    ->where('group_id', $groupHemisId)
                    ->exists();

                if (!$isAssigned) {
                    $isAssigned = DB::table('schedules')
                        ->where('group_id', $groupHemisId)
                        ->where('subject_id', $studentGrade->subject_id)
                        ->where('semester_code', $studentGrade->semester_code)
                        ->where('employee_id', $teacherHemisId)
                        ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
                        ->exists();
                }

                if (!$isAssigned) {
                    return response()->json(['success' => false, 'message' => 'Siz bu guruh/fanga biriktirilmagansiz.'], 403);
                }

                // Deadline sozlamasi tekshirish
                $semesterLevelCode = DB::table('semesters')
                    ->where('code', $studentGrade->semester_code)
                    ->value('level_code');

                $levelDeadline = $semesterLevelCode
                    ? Deadline::where('level_code', $semesterLevelCode)->first()
                    : null;

                if (!$levelDeadline || !$levelDeadline->retake_by_oqituvchi) {
                    return response()->json(['success' => false, 'message' => 'Bu kurs darajasida o\'qituvchiga baho qo\'yish ruxsati yo\'q.'], 403);
                }

                $editDays = $levelDeadline->deadline_days;

                $todayStr = \Carbon\Carbon::now('Asia/Tashkent')->format('Y-m-d');
                $lastNDates = DB::table('schedules')
                    ->where('group_id', $groupHemisId)
                    ->where('subject_id', $studentGrade->subject_id)
                    ->where('semester_code', $studentGrade->semester_code)
                    ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
                    ->whereNull('deleted_at')
                    ->where(DB::raw('DATE(lesson_date)'), '<=', $todayStr)
                    ->select(DB::raw('DATE(lesson_date) as lesson_date'))
                    ->distinct()
                    ->orderBy('lesson_date')
                    ->pluck('lesson_date')
                    ->toArray();

                $lastNDates = array_slice($lastNDates, -$editDays);
                $gradeDateStr = \Carbon\Carbon::parse($studentGrade->lesson_date)->format('Y-m-d');

                if (!in_array($gradeDateStr, $lastNDates)) {
                    return response()->json(['success' => false, 'message' => "O'qituvchi faqat oxirgi {$editDays} kunlik darslarga baho qo'ya oladi."], 403);
                }
            }

            // YN ga yuborilganligini tekshirish
            if ($studentGrade->is_yn_locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                    'yn_locked' => true,
                ], 403);
            }

            // Check if retake grade already exists
            if ($studentGrade->retake_grade !== null) {
                return response()->json(['success' => false, 'message' => 'Retake bahosi allaqachon qo\'yilgan. O\'zgartirishga ruxsat berilmagan.'], 400);
            }

            // Muddat tekshirish: deadline o'tgan bo'lsa, baho qo'yishga ruxsat bermash (admin uchun cheklov yo'q)
            if (!$isAdmin && $studentGrade->deadline && now()->greaterThan($studentGrade->deadline)) {
                $deadlineFormatted = \Carbon\Carbon::parse($studentGrade->deadline)->format('d.m.Y H:i');
                return response()->json([
                    'success' => false,
                    'message' => "Otrabotka muddati o'tgan ({$deadlineFormatted}). Baho qo'yish mumkin emas.",
                    'deadline_expired' => true,
                ], 403);
            }

            // Check if there's an attendance record to determine if absence was excused
            $attendance = DB::table('attendances')
                ->where('student_hemis_id', $studentGrade->student_hemis_id)
                ->where('subject_id', $studentGrade->subject_id)
                ->where('semester_code', $studentGrade->semester_code)
                ->whereDate('lesson_date', $studentGrade->lesson_date)
                ->where('lesson_pair_code', $studentGrade->lesson_pair_code)
                ->first();

            // Determine the percentage to apply based on reason:
            // - absent + excused (sababli): 100%
            // - absent + unexcused (sababsiz): 80%
            // - low_grade (60 dan past, otrabotka): 80%
            // - no reason (baho qo'yilmagan, talaba kelgan): 100%
            if ($studentGrade->reason === 'absent') {
                $isExcused = $attendance && ((int) $attendance->absent_on) > 0;
                $percentage = $isExcused ? 1.0 : 0.8;
            } elseif ($studentGrade->reason === 'low_grade') {
                $percentage = 0.8;
            } else {
                $percentage = 1.0;
            }

            // Calculate final retake grade
            $retakeGrade = round($enteredGrade * $percentage, 2);

            $now = now();

            // Check if auth user exists in users table (admin may use different auth table)
            $gradedByUserId = DB::table('users')->where('id', auth()->id())->exists() ? auth()->id() : null;

            // Update the grade record with retake information
            DB::table('student_grades')
                ->where('id', $gradeId)
                ->update([
                    'retake_grade' => $retakeGrade,
                    'status' => 'retake',
                    'graded_by_user_id' => $gradedByUserId,
                    'retake_graded_at' => $now,
                    'updated_at' => $now,
                ]);

            // Determine display info for frontend diagonal cell
            $isAbsentReason = $studentGrade->reason === 'absent';
            $originalGrade = $studentGrade->grade;
            $isExcusedForDisplay = $isAbsentReason && $attendance && ((int) $attendance->absent_on) > 0;

            return response()->json([
                'success' => true,
                'message' => 'Retake bahosi muvaffaqiyatli saqlandi',
                'retake_grade' => $retakeGrade,
                'percentage' => $percentage * 100,
                'reason' => $studentGrade->reason,
                'original_grade' => $originalGrade,
                'is_excused' => $isExcusedForDisplay,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a retake grade and restore original NB/low_grade status
     */
    public function deleteRetakeGrade(Request $request)
    {
        // Faqat admin/superadmin o'chira oladi
        if (!auth()->user()->hasAnyRole(['admin', 'superadmin'])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'grade_id' => 'required|integer',
        ]);

        try {
            $gradeId = $request->grade_id;

            $studentGrade = DB::table('student_grades')
                ->where('id', $gradeId)
                ->first();

            if (!$studentGrade) {
                return response()->json(['success' => false, 'message' => 'Baho yozuvi topilmadi: id=' . $gradeId], 404);
            }

            if ($studentGrade->retake_grade === null) {
                return response()->json(['success' => false, 'message' => 'Bu yozuvda retake bahosi yo\'q.'], 400);
            }

            // YN ga yuborilganligini tekshirish
            if ($studentGrade->is_yn_locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                    'yn_locked' => true,
                ], 403);
            }

            // Sababli/sababsiz tekshirish (NB uchun)
            $isExcused = false;
            if ($studentGrade->reason === 'absent') {
                $attendance = DB::table('attendances')
                    ->where('student_hemis_id', $studentGrade->student_hemis_id)
                    ->where('subject_id', $studentGrade->subject_id)
                    ->where('semester_code', $studentGrade->semester_code)
                    ->whereDate('lesson_date', $studentGrade->lesson_date)
                    ->where('lesson_pair_code', $studentGrade->lesson_pair_code)
                    ->first();
                $isExcused = $attendance && ((int) $attendance->absent_on) > 0;
            }

            // Retake bahoni o'chirish va oldingi holatni tiklash
            DB::table('student_grades')
                ->where('id', $gradeId)
                ->update([
                    'retake_grade' => null,
                    'retake_graded_at' => null,
                    'status' => 'pending',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Retake bahosi o\'chirildi',
                'reason' => $studentGrade->reason,
                'original_grade' => $studentGrade->grade,
                'is_excused' => $isExcused,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new retake grade for empty cells
     */
    public function createRetakeGrade(Request $request)
    {
        // Bo'sh katakga baho qo'yish — faqat admin/superadmin
        if (!auth()->user()->hasAnyRole(['admin', 'superadmin'])) {
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

        try {
            $studentHemisId = $request->student_hemis_id;
            $lessonDate = $request->lesson_date;
            $lessonPairCode = $request->lesson_pair_code;
            $subjectId = $request->subject_id;
            $semesterCode = $request->semester_code;
            $enteredGrade = $request->grade;

            // YN ga yuborilganligini tekshirish
            $ynLocked = DB::table('student_grades')
                ->where('student_hemis_id', $studentHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('is_yn_locked', true)
                ->exists();

            if ($ynLocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                    'yn_locked' => true,
                ], 403);
            }

            // Get student info
            $student = DB::table('students')
                ->where('hemis_id', $studentHemisId)
                ->first();

            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Talaba topilmadi: ' . $studentHemisId], 404);
            }

            // Get subject info
            $subject = CurriculumSubject::where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();

            if (!$subject) {
                return response()->json(['success' => false, 'message' => 'Fan topilmadi: subject_id=' . $subjectId . ', semester=' . $semesterCode], 404);
            }

            // Get schedule info for training type (whereDate for datetime column)
            $schedule = DB::table('schedules')
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereDate('lesson_date', $lessonDate)
                ->where('lesson_pair_code', $lessonPairCode)
                ->first();

            // Bo'sh katak = talaba kelgan, baho qo'yilmagan
            // Oddiy baho sifatida saqlanadi (retake emas, 100%)
            $now = now();

            // Get education year from schedule or student's curriculum
            $educationYearCode = $schedule->education_year_code ?? null;
            $educationYearName = $schedule->education_year_name ?? null;

            if (!$educationYearCode) {
                $curriculum = DB::table('curricula')
                    ->where('curricula_hemis_id', $student->curriculum_id ?? null)
                    ->first();
                $educationYearCode = $curriculum?->education_year_code;
                $educationYearName = $curriculum?->education_year_name;
            }

            // Check if auth user exists in users table (admin may use different auth table)
            $gradedByUserId = DB::table('users')->where('id', auth()->id())->exists() ? auth()->id() : null;

            // Create normal grade record (not retake)
            DB::table('student_grades')->insert([
                'hemis_id' => 0,
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
                'training_type_code' => (string) ($schedule->training_type_code ?? '10'),
                'training_type_name' => $schedule->training_type_name ?? 'Amaliyot',
                'employee_id' => $schedule->employee_id ?? 0,
                'employee_name' => $schedule->employee_name ?? 'Manual',
                'lesson_pair_code' => $lessonPairCode,
                'lesson_pair_name' => $schedule->lesson_pair_name ?? '',
                'lesson_pair_start_time' => $schedule->lesson_pair_start_time ?? '00:00',
                'lesson_pair_end_time' => $schedule->lesson_pair_end_time ?? '00:00',
                'grade' => $enteredGrade,
                'retake_grade' => null,
                'lesson_date' => $lessonDate,
                'created_at_api' => $now,
                'status' => 'recorded',
                'reason' => null,
                'graded_by_user_id' => $gradedByUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Baho muvaffaqiyatli saqlandi',
                'grade' => $enteredGrade,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage()
            ], 500);
        }
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
            ->where('g.active', true)
            ->where('cs.is_active', true);

        // O'qituvchi uchun faqat o'zi o'tadigan fanlar
        // O'qituvchi uchun faqat o'zi o'tadigan fanlar/guruhlar
        $isOqituvchi = is_active_oqituvchi();
        $teacherSubjectIds = [];
        if ($isOqituvchi) {
            $teacherHemisId = get_teacher_hemis_id();
            if ($teacherHemisId) {
                $assignments = $this->getTeacherSubjectAssignments($teacherHemisId);
                $teacherSubjectIds = $assignments['subject_ids'];
            }
        }

        // O'qituvchi uchun ta'lim turi filtrini qo'llamaydi
        // (chunki o'qituvchi bir nechta ta'lim turida dars berishi mumkin)
        if (!$isOqituvchi && $request->filled('education_type')) {
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

        if ($isOqituvchi) {
            $query->whereIn('cs.subject_id', $teacherSubjectIds);
        }

        // Baho qo'yilmaydigan fanlarni dropdown'dan chiqarish
        $excludedSubjectNames = config('app.excluded_rating_subject_names', []);
        if (!empty($excludedSubjectNames)) {
            $query->whereNotIn('cs.subject_name', $excludedSubjectNames);
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

        // O'qituvchi uchun faqat o'zi o'tadigan guruhlar
        // O'qituvchi uchun faqat o'zi o'tadigan fanlar/guruhlar
        $isOqituvchi = is_active_oqituvchi();
        $teacherGroupIds = [];
        if ($isOqituvchi) {
            $teacherHemisId = get_teacher_hemis_id();
            if ($teacherHemisId) {
                $assignments = $this->getTeacherSubjectAssignments($teacherHemisId);
                $teacherGroupIds = $assignments['group_ids'];
            }
        }

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
        // O'qituvchi uchun ta'lim turi filtrini qo'llamaydi
        if (!$isOqituvchi && $request->filled('education_type')) {
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

        // Joriy semestr bo'yicha filtrlash
        if ($request->get('current_semester') == '1') {
            $curriculaIds = Semester::where('current', true)
                ->pluck('curriculum_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Semestr bo'yicha filtrlash (joriy semestr orqali guruh aniqlanadi)
        if ($request->filled('semester_code')) {
            $curriculaIds = Semester::where('code', $request->semester_code)
                ->where('current', true)
                ->pluck('curriculum_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Kurs bo'yicha filtrlash (joriy semestr orqali guruh kursini aniqlash)
        if ($request->filled('level_code')) {
            $curriculaIds = Semester::where('level_code', $request->level_code)
                ->where('current', true)
                ->pluck('curriculum_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        // Fan bo'yicha filtrlash
        if ($request->filled('subject_id')) {
            $curriculaIds = CurriculumSubject::where('subject_id', $request->subject_id)
                ->pluck('curricula_hemis_id');
            $query->whereIn('curriculum_hemis_id', $curriculaIds);
        }

        if ($isOqituvchi) {
            $query->whereIn('group_hemis_id', $teacherGroupIds);
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
        $baseUrl = rtrim(config('services.hemis.base_url', 'https://student.ttatf.uz/rest'), '/');
        // Ensure /v1 is in the path (env may have /rest without /v1)
        if (!str_contains($baseUrl, '/v1')) {
            $baseUrl .= '/v1';
        }
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

                $items = $data['data']['items'] ?? [];

                // Fan bo'yicha filtrlash (API da subject filter yo'q)
                if ($request->filled('subject_id')) {
                    $subjectId = (int) $request->subject_id;
                    $items = array_values(
                        array_filter($items, function ($item) use ($subjectId) {
                            return isset($item['subject']['id']) && (int) $item['subject']['id'] === $subjectId;
                        })
                    );
                }

                // Avtomatik moslash: topic position → schedule sanasi (training_type bo'yicha)
                if ($request->filled('group_hemis_id') && $request->filled('subject_id') && $request->filled('semester_id')) {
                    $scheduleDates = DB::table('schedules')
                        ->where('group_id', $request->group_hemis_id)
                        ->where('subject_id', $request->subject_id)
                        ->where('semester_code', $request->semester_id)
                        ->whereNull('deleted_at')
                        ->whereNotNull('lesson_date')
                        ->select('lesson_date', 'training_type_code')
                        ->orderBy('lesson_date')
                        ->get();

                    // Training type bo'yicha guruhlash va unique sanalar
                    $datesByType = [];
                    foreach ($scheduleDates as $row) {
                        $typeCode = (string) $row->training_type_code;
                        $dateStr = \Carbon\Carbon::parse($row->lesson_date)->format('Y-m-d');
                        if (!isset($datesByType[$typeCode])) {
                            $datesByType[$typeCode] = [];
                        }
                        // Bir xil sana uchun faqat birinchisini olish
                        if (!in_array($dateStr, $datesByType[$typeCode])) {
                            $datesByType[$typeCode][] = $dateStr;
                        }
                    }

                    // Topiclarni training_type bo'yicha guruhlash va position bo'yicha tartiblash
                    $topicsByType = [];
                    foreach ($items as $idx => $item) {
                        $typeCode = (string) ($item['_training_type'] ?? '');
                        if (!isset($topicsByType[$typeCode])) {
                            $topicsByType[$typeCode] = [];
                        }
                        $topicsByType[$typeCode][] = $idx;
                    }

                    // Har bir training_type uchun moslash
                    $today = now()->format('Y-m-d');
                    foreach ($topicsByType as $typeCode => $topicIndexes) {
                        $dates = $datesByType[$typeCode] ?? [];
                        // Topic position bo'yicha tartiblash
                        usort($topicIndexes, function ($a, $b) use ($items) {
                            return ($items[$a]['position'] ?? 0) - ($items[$b]['position'] ?? 0);
                        });

                        foreach ($topicIndexes as $i => $topicIdx) {
                            if (isset($dates[$i]) && $dates[$i] <= $today) {
                                $items[$topicIdx]['taught_date'] = \Carbon\Carbon::parse($dates[$i])->format('d.m.Y');
                            } else {
                                $items[$topicIdx]['taught_date'] = null;
                            }
                        }
                    }
                }

                return response()->json([
                    'success' => $data['success'] ?? true,
                    'data' => ['items' => $items],
                ]);
            }

            Log::warning('HEMIS topics API failed', ['status' => $response->status(), 'body' => substr($response->body(), 0, 500)]);

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
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $schedules = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
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
        // Dekan uchun fakultet cheklovi
        $dekanFacultyIds = get_dekan_faculty_ids();

        // O'qituvchi uchun fanga biriktirilgan cheklovi
        // O'qituvchi uchun faqat o'zi o'tadigan fanlar/guruhlar
        $isOqituvchi = is_active_oqituvchi();
        $teacherAssignments = ['subject_ids' => [], 'group_ids' => []];
        if ($isOqituvchi) {
            $teacherHemisId = get_teacher_hemis_id();
            if ($teacherHemisId) {
                $teacherAssignments = $this->getTeacherSubjectAssignments($teacherHemisId);
            }
        }

        // Fakultet department_hemis_id (reused in multiple queries)
        $facultyDeptHemisId = null;
        if ($request->filled('faculty_id')) {
            $facultyDeptHemisId = Department::where('id', $request->faculty_id)->value('department_hemis_id');
        }

        // 1. Fakultet - erkin, hamma faol fakultetlar (dekan uchun faqat o'ziniki)
        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');

        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }

        $faculties = $facultyQuery->pluck('name', 'id');

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
        // O'qituvchi uchun faqat o'zi o'tadigan guruhlar
        if ($isOqituvchi) {
            $groupsQuery->whereIn('g.group_hemis_id', $teacherAssignments['group_ids']);
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
        // O'qituvchi uchun faqat o'zi o'tadigan fanlar
        if ($isOqituvchi) {
            $subjectsQuery->whereIn('cs.subject_id', $teacherAssignments['subject_ids']);
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

    /**
     * O'qituvchiga biriktirilgan fanlar va guruhlarni olish.
     * 1-manba: curriculum_subject_teachers jadvali
     * 2-manba (fallback): schedules (dars jadvali) - ko'p dars o'tgan o'qituvchi "egasi"
     *
     * @param int $employeeHemisId O'qituvchining HEMIS ID si
     * @return array ['subject_ids' => [...], 'group_ids' => [...]]
     */
    private function getTeacherSubjectAssignments(int $employeeHemisId): array
    {
        // 1-manba: curriculum_subject_teachers
        $records = CurriculumSubjectTeacher::where('employee_id', $employeeHemisId)->get();

        $subjectIds = $records->pluck('subject_id')->unique()->filter()->values()->toArray();
        $groupIds = $records->pluck('group_id')->unique()->filter()->values()->toArray();

        // 2-manba (fallback): dars jadvalidan aniqlash
        $scheduleAssignments = $this->getTeacherScheduleAssignments($employeeHemisId);

        // Ikki manbani birlashtirish
        $subjectIds = array_values(array_unique(array_merge($subjectIds, $scheduleAssignments['subject_ids'])));
        $groupIds = array_values(array_unique(array_merge($groupIds, $scheduleAssignments['group_ids'])));

        return [
            'subject_ids' => $subjectIds,
            'group_ids' => $groupIds,
        ];
    }

    /**
     * Dars jadvalidan o'qituvchining fan-guruh biriktirishlarini aniqlash.
     * Faqat joriy semestr darslari hisobga olinadi (curriculum_weeks orqali aniq sana).
     * Har bir fan+guruh uchun eng ko'p dars o'tgan o'qituvchi "primary" hisoblanadi.
     *
     * @param int $employeeHemisId O'qituvchining HEMIS ID si
     * @return array ['subject_ids' => [...], 'group_ids' => [...]]
     */
    private function getTeacherScheduleAssignments(int $employeeHemisId): array
    {
        // Joriy semestr boshlanish sanasini curriculum_weeks dan aniqlash
        $semesterStart = $this->getCurrentSemesterStartDate();

        if (!$semesterStart) {
            return ['subject_ids' => [], 'group_ids' => []];
        }

        // 1-query: O'qituvchining joriy semestrdagi fan+guruh kombinatsiyalari
        $teacherCombos = DB::table('schedules')
            ->where('employee_id', $employeeHemisId)
            ->where('education_year_current', true)
            ->where('lesson_date', '>=', $semesterStart)
            ->whereNull('deleted_at')
            ->select('subject_id', 'group_id')
            ->groupBy('subject_id', 'group_id')
            ->get();

        if ($teacherCombos->isEmpty()) {
            return ['subject_ids' => [], 'group_ids' => []];
        }

        // 2-query: Joriy semestrdagi BARCHA o'qituvchilarning statistikasi
        $comboSubjectIds = $teacherCombos->pluck('subject_id')->unique()->toArray();
        $comboGroupIds = $teacherCombos->pluck('group_id')->unique()->toArray();

        $allStats = DB::table('schedules')
            ->where('education_year_current', true)
            ->where('lesson_date', '>=', $semesterStart)
            ->whereNull('deleted_at')
            ->whereIn('subject_id', $comboSubjectIds)
            ->whereIn('group_id', $comboGroupIds)
            ->select('subject_id', 'group_id', 'employee_id')
            ->selectRaw('COUNT(*) as lesson_count')
            ->selectRaw('MAX(lesson_date) as last_lesson')
            ->groupBy('subject_id', 'group_id', 'employee_id')
            ->get();

        // Har bir fan+guruh uchun eng ko'p dars o'tgan o'qituvchini aniqlash
        $statsByCombo = $allStats->groupBy(fn($item) => $item->subject_id . '-' . $item->group_id);

        $subjectIds = [];
        $groupIds = [];
        $comboKeys = $teacherCombos->map(fn($c) => $c->subject_id . '-' . $c->group_id)->toArray();

        foreach ($statsByCombo as $key => $teachers) {
            if (!in_array($key, $comboKeys)) {
                continue;
            }

            $primary = $teachers->sort(function ($a, $b) {
                if ($a->lesson_count !== $b->lesson_count) {
                    return $b->lesson_count - $a->lesson_count;
                }
                return strcmp($b->last_lesson ?? '', $a->last_lesson ?? '');
            })->first();

            if ($primary && $primary->employee_id == $employeeHemisId) {
                $subjectIds[] = $primary->subject_id;
                $groupIds[] = $primary->group_id;
            }
        }

        return [
            'subject_ids' => array_values(array_unique(array_filter($subjectIds))),
            'group_ids' => array_values(array_unique(array_filter($groupIds))),
        ];
    }

    /**
     * Joriy semestrning boshlanish sanasini aniqlash.
     * curriculum_weeks dan bugungi sana qaysi haftaga tushishini topib,
     * o'sha semestrning birinchi haftasi boshlanish sanasini qaytaradi.
     */
    private function getCurrentSemesterStartDate(): ?string
    {
        $now = now();

        // Bugungi sana qaysi curriculum_weeks ga tushadi?
        $currentWeekSemesterIds = DB::table('curriculum_weeks')
            ->where('start_date', '<=', $now)
            ->where('end_date', '>=', $now)
            ->pluck('semester_hemis_id')
            ->unique()
            ->toArray();

        // Agar topilmasa, curriculum_weeks.current=true dan olish
        if (empty($currentWeekSemesterIds)) {
            $currentWeekSemesterIds = DB::table('curriculum_weeks')
                ->where('current', true)
                ->pluck('semester_hemis_id')
                ->unique()
                ->toArray();
        }

        if (empty($currentWeekSemesterIds)) {
            return null;
        }

        // Shu semestrlarning eng erta boshlanish sanasi
        return DB::table('curriculum_weeks')
            ->whereIn('semester_hemis_id', $currentWeekSemesterIds)
            ->min('start_date');
    }

    /**
     * Dars ochish - o'tkazib yuborilgan kun uchun baho qo'yishni ochish
     */
    public function openLesson(Request $request)
    {
        // Faqat admin, kichik_admin, superadmin, registrator_ofisi
        $allowedRoles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];
        $hasRole = (auth()->guard('web')->user()?->hasAnyRole($allowedRoles) ?? false)
            || (auth()->guard('teacher')->user()?->hasAnyRole($allowedRoles) ?? false);
        if (!$hasRole) {
            return response()->json(['success' => false, 'message' => 'Ruxsat yo\'q'], 403);
        }

        $request->validate([
            'group_hemis_id' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'lesson_date' => 'required|date',
            'file' => 'required|file|max:10240',
        ]);

        // Avval mavjud ochilish bormi tekshirish (har qanday statusda)
        $existing = LessonOpening::where('group_hemis_id', $request->group_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('lesson_date', $request->lesson_date)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Bu kun uchun dars allaqachon ochilgan'], 409);
        }

        // Faylni yuklash
        $file = $request->file('file');
        $filePath = $file->store('lesson-openings', 'public');
        $fileOriginalName = $file->getClientOriginalName();

        // Muddat hisoblash
        $days = (int) Setting::get('lesson_opening_days', 3);
        $deadline = \Carbon\Carbon::now('Asia/Tashkent')->addDays($days)->endOfDay();

        // Guard va foydalanuvchi ma'lumotlari
        $webUser = auth()->guard('web')->user();
        $teacherUser = auth()->guard('teacher')->user();
        $guard = $teacherUser ? 'teacher' : 'web';
        $user = $webUser ?? $teacherUser;

        $opening = LessonOpening::create([
            'group_hemis_id' => $request->group_hemis_id,
            'subject_id' => $request->subject_id,
            'semester_code' => $request->semester_code,
            'lesson_date' => $request->lesson_date,
            'file_path' => $filePath,
            'file_original_name' => $fileOriginalName,
            'opened_by_id' => $user->id,
            'opened_by_name' => $user->name ?? $user->full_name ?? 'Unknown',
            'opened_by_guard' => $guard,
            'deadline' => $deadline,
            'status' => 'active',
        ]);

        // O'qituvchilarga Telegram orqali xabar yuborish
        try {
            $teacherEmployeeIds = DB::table('schedules')
                ->where('group_id', $request->group_hemis_id)
                ->where('subject_id', $request->subject_id)
                ->where('semester_code', $request->semester_code)
                ->whereRaw('DATE(lesson_date) = ?', [$request->lesson_date])
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('employee_id');

            if ($teacherEmployeeIds->isNotEmpty()) {
                $telegram = app(TelegramService::class);
                $deadlineFormatted = $opening->deadline->format('d.m.Y H:i');
                $lessonDateFormatted = \Carbon\Carbon::parse($request->lesson_date)->format('d.m.Y');

                $subjectName = DB::table('schedules')
                    ->where('group_id', $request->group_hemis_id)
                    ->where('subject_id', $request->subject_id)
                    ->whereNull('deleted_at')
                    ->value('subject_name') ?? 'Noma\'lum fan';

                $groupName = DB::table('groups')
                    ->where('group_hemis_id', $request->group_hemis_id)
                    ->value('name') ?? 'Noma\'lum guruh';

                $teachers = Teacher::whereIn('hemis_id', $teacherEmployeeIds)
                    ->whereNotNull('telegram_chat_id')
                    ->get();

                foreach ($teachers as $teacher) {
                    $message = "Hurmatli {$teacher->full_name}!\n\n"
                        . "{$subjectName} fani bo'yicha {$groupName} guruhiga {$lessonDateFormatted} sanasidagi dars ochildi.\n\n"
                        . "Baho qo'yish uchun muhlat: {$deadlineFormatted}\n\n"
                        . "Iltimos, muddatgacha baholarni kiritib qo'ying.";

                    $telegram->sendToUser($teacher->telegram_chat_id, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Dars ochilganda Telegram xabar yuborishda xato: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Dars muvaffaqiyatli ochildi',
            'opening' => [
                'id' => $opening->id,
                'deadline' => $opening->deadline->format('Y-m-d H:i'),
                'opened_by_name' => $opening->opened_by_name,
                'file_original_name' => $opening->file_original_name,
            ],
        ]);
    }

    /**
     * Dars ochishni yopish
     */
    public function closeLesson(Request $request)
    {
        $allowedRoles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];
        $hasRole = (auth()->guard('web')->user()?->hasAnyRole($allowedRoles) ?? false)
            || (auth()->guard('teacher')->user()?->hasAnyRole($allowedRoles) ?? false);
        if (!$hasRole) {
            return response()->json(['success' => false, 'message' => 'Ruxsat yo\'q'], 403);
        }

        $request->validate([
            'opening_id' => 'required|integer',
        ]);

        $opening = LessonOpening::find($request->opening_id);
        if (!$opening) {
            return response()->json(['success' => false, 'message' => 'Dars ochilishi topilmadi'], 404);
        }

        $opening->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Dars yopildi',
        ]);
    }

    /**
     * Dars ochish faylini yuklab olish
     */
    public function downloadLessonFile(LessonOpening $lessonOpening)
    {
        if (!$lessonOpening->file_path || !\Storage::disk('public')->exists($lessonOpening->file_path)) {
            abort(404, 'Fayl topilmadi');
        }

        return \Storage::disk('public')->download($lessonOpening->file_path, $lessonOpening->file_original_name);
    }

    /**
     * O'qituvchi uchun: ochilgan dars kuniga baho qo'yish
     */
    public function saveOpenedLessonGrade(Request $request)
    {
        $request->validate([
            'student_hemis_id' => 'required',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'lesson_date' => 'required|date',
            'lesson_pair_code' => 'required',
            'grade' => 'required|numeric|min:0|max:100',
            'group_hemis_id' => 'required',
        ]);

        // YN ga yuborilganligini tekshirish
        $ynLocked = DB::table('student_grades')
            ->where('student_hemis_id', $request->student_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('is_yn_locked', true)
            ->exists();

        if ($ynLocked) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga yuborilgan. Baholarni o\'zgartirish mumkin emas.',
                'yn_locked' => true,
            ], 403);
        }

        // Tekshirish: bu kun uchun faol dars ochilishi bormi
        $groupHemisId = $request->group_hemis_id;
        $lessonDate = \Carbon\Carbon::parse($request->lesson_date)->format('Y-m-d');
        $opening = LessonOpening::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('lesson_date', $lessonDate)
            ->where('status', 'active')
            ->first();

        if (!$opening) {
            return response()->json(['success' => false, 'message' => "Dars ochilmagan (group={$groupHemisId}, date={$lessonDate})"], 403);
        }
        if (!$opening->isActive()) {
            return response()->json(['success' => false, 'message' => 'Dars ochilish muddati tugagan'], 403);
        }

        // O'qituvchi ekanligini va shu guruhga biriktirilganligini tekshirish
        $adminRoles = ['superadmin', 'admin', 'kichik_admin'];
        $isAdmin = (auth()->guard('web')->user()?->hasAnyRole($adminRoles) ?? false)
            || (auth()->guard('teacher')->user()?->hasAnyRole($adminRoles) ?? false);
        $isTeacher = is_active_oqituvchi();

        if ($isTeacher && !$isAdmin) {
            $teacherHemisId = get_teacher_hemis_id();
            if (!$teacherHemisId) {
                return response()->json(['success' => false, 'message' => 'O\'qituvchi topilmadi (hemis_id null)'], 403);
            }

            // O'qituvchini tekshirish: curriculum_subject_teachers YOKI jadvaldan
            $isAssigned = CurriculumSubjectTeacher::where('employee_id', $teacherHemisId)
                ->where('subject_id', $request->subject_id)
                ->where('group_id', $groupHemisId)
                ->exists();

            if (!$isAssigned) {
                $isAssigned = DB::table('schedules')
                    ->where('group_id', $groupHemisId)
                    ->where('subject_id', $request->subject_id)
                    ->where('semester_code', $request->semester_code)
                    ->where('employee_id', $teacherHemisId)
                    ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
                    ->exists();
            }

            if (!$isAssigned) {
                return response()->json(['success' => false, 'message' => "Biriktirilmagan (teacher={$teacherHemisId}, group={$groupHemisId})"], 403);
            }
        } elseif (!$isAdmin && !$isTeacher) {
            return response()->json(['success' => false, 'message' => 'Ruxsat yo\'q (admin=' . ($isAdmin?'1':'0') . ', teacher=' . ($isTeacher?'1':'0') . ')'], 403);
        }

        // Jadvaldan dars ma'lumotlarini olish
        $schedule = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereDate('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $request->lesson_pair_code)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
            ->first();

        if (!$schedule) {
            return response()->json(['success' => false, 'message' => "Jadvalda dars topilmadi (group={$groupHemisId}, date={$lessonDate}, pair={$request->lesson_pair_code})"], 404);
        }

        // Mavjud baho bormi tekshirish
        $existing = DB::table('student_grades')
            ->where('student_hemis_id', $request->student_hemis_id)
            ->where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->whereDate('lesson_date', $lessonDate)
            ->where('lesson_pair_code', $request->lesson_pair_code)
            ->whereNotIn('training_type_code', [11, 99, 100, 101, 102])
            ->first();

        $gradeValue = (float) $request->grade;
        $now = now();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Bu katak uchun baho allaqachon mavjud.'], 409);
        }

        // Talabani topish (student_id uchun)
        $student = DB::table('students')->where('hemis_id', $request->student_hemis_id)->first();
        if (!$student) {
            return response()->json(['success' => false, 'message' => "Talaba topilmadi (hemis_id={$request->student_hemis_id})"], 404);
        }

        // Yangi baho yaratish
        DB::table('student_grades')->insert([
            'hemis_id' => 88888888,
            'student_id' => $student->id,
            'student_hemis_id' => $request->student_hemis_id,
            'subject_id' => $request->subject_id,
            'subject_name' => $schedule->subject_name,
            'subject_code' => $schedule->subject_code,
            'semester_code' => $request->semester_code,
            'semester_name' => $schedule->semester_name,
            'subject_schedule_id' => $schedule->schedule_hemis_id,
            'training_type_code' => $schedule->training_type_code,
            'training_type_name' => $schedule->training_type_name,
            'employee_id' => $schedule->employee_id,
            'employee_name' => $schedule->employee_name,
            'lesson_pair_code' => $schedule->lesson_pair_code,
            'lesson_pair_name' => $schedule->lesson_pair_name,
            'lesson_pair_start_time' => $schedule->lesson_pair_start_time,
            'lesson_pair_end_time' => $schedule->lesson_pair_end_time,
            'lesson_date' => $schedule->lesson_date,
            'grade' => $gradeValue,
            'status' => 'recorded',
            'reason' => null,
            'education_year_code' => $schedule->education_year_code ?? null,
            'education_year_name' => $schedule->education_year_name ?? null,
            'created_at_api' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Baho saqlandi',
            'grade' => $gradeValue,
        ]);
    }

    /**
     * O'qituvchi YN ga yuborish — barcha baholarni qulflaydi
     */
    public function submitToYn(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
        ]);

        // Faqat biriktirilgan o'qituvchi YN ga yuborishi mumkin
        if (is_active_oqituvchi()) {
            $teacherHemisId = get_teacher_hemis_id();
            if (!$teacherHemisId) {
                return response()->json([
                    'success' => false,
                    'message' => 'O\'qituvchi topilmadi.',
                ], 403);
            }
            $assignments = $this->getTeacherSubjectAssignments($teacherHemisId);
            $isAssigned = in_array($request->subject_id, $assignments['subject_ids'])
                && in_array($request->group_hemis_id, $assignments['group_ids']);
            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siz bu fan va guruhga biriktirilmagansiz. YN ga yuborish huquqi yo\'q.',
                ], 403);
            }
        } elseif (!auth()->user()?->hasAnyRole(['superadmin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga yuborish huquqi faqat biriktirilgan o\'qituvchida.',
            ], 403);
        }

        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $groupHemisId = $request->group_hemis_id;

        // Allaqachon yuborilganligini tekshirish
        $existing = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'YN ga allaqachon yuborilgan (' . $existing->submitted_at->format('d.m.Y H:i') . ')',
            ], 409);
        }

        // Guruh talabalarini olish
        $studentHemisIds = DB::table('students')
            ->where('group_id', $groupHemisId)
            ->pluck('hemis_id');

        if ($studentHemisIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Guruhda talabalar topilmadi',
            ], 404);
        }

        // Check if auth user exists in users table (teacher guard may use different auth table)
        $submittedByUserId = DB::table('users')->where('id', auth()->id())->exists() ? auth()->id() : null;

        if (!$submittedByUserId) {
            // Teacher guard orqali kirgan bo'lsa, teachers jadvalidan login bo'yicha users jadvalidan qidirish
            $teacher = auth()->guard('teacher')->user();
            if ($teacher) {
                $submittedByUserId = DB::table('users')->where('email', $teacher->login)->value('id');
            }
        }

        if (!$submittedByUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Foydalanuvchi topilmadi. Iltimos, qayta kiring.',
            ], 403);
        }

        // --- JN/MT hisoblash (jurnal logikasi bilan bir xil) ---
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // Jadvaldan (schedule) dars sanalarini olish
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $jbColumns = $jbScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();

        $jbLessonDates = $jbScheduleRows->pluck('lesson_date')->unique()->sort()->values()->toArray();

        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
        }

        $jbDatePairSet = [];
        foreach ($jbColumns as $col) {
            $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }

        $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->subDay()->startOfDay();
        $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
            return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
        }));
        $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
        $totalJbDaysForAverage = count($jbLessonDatesForAverage);

        // MT jadval sanalarini olish
        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $mtColumns = $mtScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();

        $mtLessonDates = $mtScheduleRows->pluck('lesson_date')->unique()->sort()->values()->toArray();

        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
        }

        $mtDatePairSet = [];
        foreach ($mtColumns as $col) {
            $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }

        $totalMtDays = count($mtLessonDates);

        // Baholarni olish
        $allGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        // Baho filtrlash (jurnal logikasi bilan bir xil)
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

        // JB baholarni tuzish
        $jbGrades = [];
        $mtGrades = [];
        foreach ($allGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade === null) continue;
            $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            $key = $normalizedDate . '_' . $g->lesson_pair_code;
            if (isset($jbDatePairSet[$key])) {
                $jbGrades[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
            }
            if (isset($mtDatePairSet[$key])) {
                $mtGrades[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
            }
        }

        // Manual MT baholarini olish
        $manualMtGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->whereNotNull('grade')
            ->select('student_hemis_id', 'grade')
            ->get()
            ->keyBy('student_hemis_id');

        // Har bir talaba uchun JN va MT hisoblash
        $studentGradeSnapshots = [];
        foreach ($studentHemisIds as $hemisId) {
            // JN hisoblash
            $dailySum = 0;
            $studentDayGrades = $jbGrades[$hemisId] ?? [];
            foreach ($jbLessonDates as $date) {
                $dayGrades = $studentDayGrades[$date] ?? [];
                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                if (isset($jbLessonDatesForAverageLookup[$date])) {
                    $dailySum += $dayAverage;
                }
            }
            $jn = $totalJbDaysForAverage > 0
                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                : 0;

            // MT hisoblash
            $mtDailySum = 0;
            $studentMtGrades = $mtGrades[$hemisId] ?? [];
            foreach ($mtLessonDates as $date) {
                $dayGrades = $studentMtGrades[$date] ?? [];
                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
            }
            $mt = $totalMtDays > 0
                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                : 0;

            // Manual MT override
            if (isset($manualMtGrades[$hemisId])) {
                $mt = round((float) $manualMtGrades[$hemisId]->grade, 0, PHP_ROUND_HALF_UP);
            }

            $studentGradeSnapshots[$hemisId] = ['jn' => $jn, 'mt' => $mt];
        }

        DB::beginTransaction();
        try {
            // exam_schedules dan OSKI/Test sanalarini olish
            $examSchedule = ExamSchedule::where('group_hemis_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();

            // Eng yaqin sana (OSKI yoki Test) ni exam_date sifatida saqlash
            $examDate = null;
            if ($examSchedule) {
                $dates = array_filter([
                    $examSchedule->oski_date?->format('Y-m-d'),
                    $examSchedule->test_date?->format('Y-m-d'),
                ]);
                if (!empty($dates)) {
                    sort($dates);
                    $examDate = $dates[0];
                }
            }

            // YN submission yozuvini yaratish
            $ynSubmission = YnSubmission::create([
                'subject_id' => $subjectId,
                'semester_code' => $semesterCode,
                'group_hemis_id' => $groupHemisId,
                'submitted_by' => $submittedByUserId,
                'submitted_at' => now(),
                'exam_date' => $examDate,
            ]);

            // Har bir talaba uchun JN/MT snapshot saqlash
            $insertData = [];
            $now = now();
            foreach ($studentGradeSnapshots as $hemisId => $grades) {
                $insertData[] = [
                    'yn_submission_id' => $ynSubmission->id,
                    'student_hemis_id' => $hemisId,
                    'jn' => $grades['jn'],
                    'mt' => $grades['mt'],
                    'source' => 'yn_submitted',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (!empty($insertData)) {
                YnStudentGrade::insert($insertData);
            }

            // Barcha tegishli student_grades yozuvlarini qulflash (JN va MT)
            DB::table('student_grades')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->update(['is_yn_locked' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'YN ga muvaffaqiyatli yuborildi. Baholar qulflandi.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sababli talabaning bahosini saqlash (retake_grade sifatida)
     * YN qulflangan bo'lsa ham, tasdiqlangan sababli uchun ruxsat beriladi.
     */
    public function saveExcuseGrade(Request $request)
    {
        $request->validate([
            'student_hemis_id' => 'required|string',
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
            'grade_id' => 'required|integer',
            'grade' => 'required|numeric|min:0|max:100',
            'comment' => 'nullable|string|max:500',
            'absence_excuse_id' => 'required|integer',
        ]);

        $studentHemisId = $request->student_hemis_id;
        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;

        // Tasdiqlangan sababli hujjat mavjudligini tekshirish
        $excuse = AbsenceExcuse::where('id', $request->absence_excuse_id)
            ->where('student_hemis_id', $studentHemisId)
            ->where('status', 'approved')
            ->first();

        if (!$excuse) {
            return response()->json([
                'success' => false,
                'message' => 'Tasdiqlangan sababli hujjat topilmadi.',
            ], 403);
        }

        // YN yuborilganligini tekshirish (sababli faqat YN yuborilgandan keyin ishlaydi)
        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $request->group_hemis_id)
            ->first();

        if (!$ynSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'YN hali yuborilmagan. Avval YN ga yuborilishi kerak.',
            ], 400);
        }

        // student_grades jadvalida retake baho qo'yish
        $studentGrade = DB::table('student_grades')
            ->where('id', $request->grade_id)
            ->where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->where('reason', 'absent')
            ->whereNull('deleted_at')
            ->first();

        if (!$studentGrade) {
            return response()->json([
                'success' => false,
                'message' => 'NB yozuvi topilmadi.',
            ], 404);
        }

        DB::table('student_grades')
            ->where('id', $request->grade_id)
            ->update([
                'retake_grade' => $request->grade,
                'retake_comment' => $request->comment,
                'status' => 'closed',
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Sababli baho saqlandi.',
            'grade' => $request->grade,
        ]);
    }

    /**
     * Sababli talabalarning yangilangan JN/MT baholarini YN ga yuborish.
     * yn_student_grades jadvaliga yangi snapshot qo'shadi (source = 'absence_excuse:ID').
     */
    public function submitExcuseToYn(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
        ]);

        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $groupHemisId = $request->group_hemis_id;

        // YN yuborilganligini tekshirish
        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId)
            ->first();

        if (!$ynSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'YN hali yuborilmagan.',
            ], 400);
        }

        // Sababli talabalar ro'yxati
        $studentHemisIds = DB::table('students')
            ->where('group_id', $groupHemisId)
            ->pluck('hemis_id');

        $approvedExcuses = AbsenceExcuse::where('status', 'approved')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->whereHas('makeups', function ($q) use ($subjectId) {
                $q->where('subject_id', $subjectId)
                    ->whereIn('assessment_type', ['jn', 'mt']);
            })
            ->get();

        if ($approvedExcuses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Sababli talabalar topilmadi.',
            ], 404);
        }

        $excuseStudentHemisIds = $approvedExcuses->pluck('student_hemis_id')->unique()->values();

        // --- JN/MT qayta hisoblash (submitToYn logikasi bilan bir xil) ---
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $jbColumns = $jbScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();
        $jbLessonDates = $jbScheduleRows->pluck('lesson_date')->unique()->sort()->values()->toArray();

        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
        }
        $jbDatePairSet = [];
        foreach ($jbColumns as $col) {
            $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }

        $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->subDay()->startOfDay();
        $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
            return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
        }));
        $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
        $totalJbDaysForAverage = count($jbLessonDatesForAverage);

        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

        $mtColumns = $mtScheduleRows->map(fn($s) => ['date' => $s->lesson_date, 'pair' => $s->lesson_pair_code])
            ->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();
        $mtLessonDates = $mtScheduleRows->pluck('lesson_date')->unique()->sort()->values()->toArray();

        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
        }
        $mtDatePairSet = [];
        foreach ($mtColumns as $col) {
            $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }
        $totalMtDays = count($mtLessonDates);

        // Faqat sababli talabalar uchun baholarni olish
        $allGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $excuseStudentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
            ->orderBy('lesson_date')
            ->orderBy('lesson_pair_code')
            ->get();

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

        $jbGrades = [];
        $mtGrades = [];
        foreach ($allGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade === null) continue;
            $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            $key = $normalizedDate . '_' . $g->lesson_pair_code;
            if (isset($jbDatePairSet[$key])) {
                $jbGrades[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
            }
            if (isset($mtDatePairSet[$key])) {
                $mtGrades[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effectiveGrade;
            }
        }

        $manualMtGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $excuseStudentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->whereNotNull('grade')
            ->select('student_hemis_id', 'grade')
            ->get()
            ->keyBy('student_hemis_id');

        // YN ga yangi snapshot yozish
        $insertData = [];
        $now = now();
        $updatedStudents = [];

        foreach ($approvedExcuses as $excuse) {
            $hemisId = $excuse->student_hemis_id;

            // JN hisoblash
            $dailySum = 0;
            $studentDayGrades = $jbGrades[$hemisId] ?? [];
            foreach ($jbLessonDates as $date) {
                $dayGrades = $studentDayGrades[$date] ?? [];
                $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                if (isset($jbLessonDatesForAverageLookup[$date])) {
                    $dailySum += $dayAverage;
                }
            }
            $jn = $totalJbDaysForAverage > 0
                ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                : 0;

            // MT hisoblash
            $mtDailySum = 0;
            $studentMtGrades = $mtGrades[$hemisId] ?? [];
            foreach ($mtLessonDates as $date) {
                $dayGrades = $studentMtGrades[$date] ?? [];
                $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                $gradeSum = array_sum($dayGrades);
                $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
            }
            $mt = $totalMtDays > 0
                ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                : 0;

            if (isset($manualMtGrades[$hemisId])) {
                $mt = round((float) $manualMtGrades[$hemisId]->grade, 0, PHP_ROUND_HALF_UP);
            }

            $insertData[] = [
                'yn_submission_id' => $ynSubmission->id,
                'student_hemis_id' => $hemisId,
                'jn' => $jn,
                'mt' => $mt,
                'source' => 'absence_excuse:' . $excuse->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $updatedStudents[] = [
                'hemis_id' => $hemisId,
                'name' => $excuse->student_full_name,
                'jn' => $jn,
                'mt' => $mt,
            ];
        }

        DB::beginTransaction();
        try {
            if (!empty($insertData)) {
                YnStudentGrade::insert($insertData);
            }

            // Sababli makeups statusini completed ga o'zgartirish
            $excuseIds = $approvedExcuses->pluck('id');
            AbsenceExcuseMakeup::whereIn('absence_excuse_id', $excuseIds)
                ->where('subject_id', $subjectId)
                ->whereIn('assessment_type', ['jn', 'mt'])
                ->update(['status' => 'completed']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sababli baholar YN ga muvaffaqiyatli yuborildi.',
                'updated_students' => $updatedStudents,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Xatolik: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * YN consent holatlarini olish (journal detail uchun)
     */
    public function getYnConsents(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
        ]);

        $consents = YnConsent::where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('group_hemis_id', $request->group_hemis_id)
            ->get()
            ->keyBy('student_hemis_id');

        $ynSubmission = YnSubmission::where('subject_id', $request->subject_id)
            ->where('semester_code', $request->semester_code)
            ->where('group_hemis_id', $request->group_hemis_id)
            ->first();

        return response()->json([
            'consents' => $consents,
            'yn_submitted' => $ynSubmission !== null,
            'yn_submitted_at' => $ynSubmission?->submitted_at?->format('d.m.Y H:i'),
        ]);
    }

    /**
     * YN o'tkazish sanasi o'tgandan keyin OSKI va Test natijalarini avtomatik tortish.
     * hemis_quiz_results dan student_grades ga yuklanmagan natijalarni topib, student_grades ga saqlaydi.
     */
    public function fetchYnResults(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
        ]);

        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $groupHemisId = $request->group_hemis_id;

        // YN yuborilganligini tekshirish
        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId)
            ->first();

        if (!$ynSubmission) {
            return response()->json([
                'success' => false,
                'message' => 'YN hali yuborilmagan.',
            ], 400);
        }

        // exam_schedules dan OSKI/Test sanalarini tekshirish
        $examSchedule = ExamSchedule::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        $oskiDatePassed = $examSchedule && $examSchedule->oski_date && $examSchedule->oski_date->isPast();
        $testDatePassed = $examSchedule && $examSchedule->test_date && $examSchedule->test_date->isPast();

        if (!$oskiDatePassed && !$testDatePassed) {
            return response()->json([
                'success' => false,
                'message' => 'OSKI yoki Test o\'tkazish sanasi hali kelmagan.',
            ], 400);
        }

        // Guruh talabalarini olish
        $students = Student::where('group_id', $groupHemisId)->get();
        if ($students->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Guruhda talabalar topilmadi.',
            ], 404);
        }

        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        $studentIdNumbers = $students->pluck('student_id_number')->filter()->toArray();

        // hemis_quiz_results dan natijalarni olish (OSKI va Test turlari)
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

        $quizResults = HemisQuizResult::where('is_active', 1)
            ->where('fan_id', $subjectId)
            ->where(function ($q) use ($studentHemisIds, $studentIdNumbers) {
                $q->whereIn('student_id', $studentHemisIds);
                if (!empty($studentIdNumbers)) {
                    $q->orWhereIn('student_id', $studentIdNumbers);
                }
            })
            ->whereIn('quiz_type', array_merge($oskiTypes, $testTypes))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('student_grades')
                    ->whereColumn('student_grades.quiz_result_id', 'hemis_quiz_results.id')
                    ->where('student_grades.reason', 'quiz_result');
            })
            ->get();

        if ($quizResults->isEmpty()) {
            // Natijalar yo'q — ammo allaqachon yuklanganlarni tekshirish
            $existingCount = StudentGrade::whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [101, 102])
                ->where('reason', 'quiz_result')
                ->count();

            if ($existingCount > 0) {
                $ynSubmission->update(['results_fetched' => true]);
                return response()->json([
                    'success' => true,
                    'message' => 'Natijalar avval yuklangan. Jami: ' . $existingCount . ' ta.',
                    'fetched_count' => 0,
                    'existing_count' => $existingCount,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'OSKI va Test natijalari topilmadi. Natijalar hali tizimga yuklanmagan bo\'lishi mumkin.',
            ], 404);
        }

        // Student lookup
        $studentLookup = [];
        foreach ($students as $student) {
            $studentLookup[$student->hemis_id] = $student;
            if ($student->student_id_number) {
                $studentLookup[$student->student_id_number] = $student;
            }
        }

        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->first();

        $successCount = 0;
        $errors = [];
        $duplicateTracker = [];

        foreach ($quizResults as $result) {
            $student = $studentLookup[$result->student_id] ?? null;
            if (!$student) {
                continue;
            }

            // Dublikat tekshirish
            $key = $result->student_id . '_' . $result->fan_id;
            $ynTuri = in_array($result->quiz_type, $oskiTypes) ? 'oski' : 'test';
            $dupKey = $key . '_' . $ynTuri;
            if (isset($duplicateTracker[$dupKey])) {
                continue;
            }
            $duplicateTracker[$dupKey] = $result->id;

            // training_type_code aniqlash
            if (in_array($result->quiz_type, $oskiTypes)) {
                $trainingTypeCode = 101;
                $trainingTypeName = 'Oski';
            } elseif (in_array($result->quiz_type, $testTypes)) {
                $trainingTypeCode = 102;
                $trainingTypeName = 'Yakuniy test';
            } else {
                continue;
            }

            StudentGrade::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'hemis_id' => 999999999,
                'semester_code' => $semester->code ?? $student->semester_code,
                'semester_name' => $semester->name ?? $student->semester_name,
                'subject_schedule_id' => 0,
                'subject_id' => $subject->subject_id ?? $subjectId,
                'subject_name' => $subject->subject_name ?? '',
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => $trainingTypeCode,
                'training_type_name' => $trainingTypeName,
                'employee_id' => 0,
                'employee_name' => auth()->user()->name ?? 'Avtomatik yuklash',
                'lesson_pair_name' => '',
                'lesson_pair_code' => '',
                'lesson_pair_start_time' => '',
                'lesson_pair_end_time' => '',
                'lesson_date' => $result->date_finish ?? now(),
                'created_at_api' => $result->created_at ?? now(),
                'reason' => 'quiz_result',
                'status' => 'recorded',
                'grade' => round($result->grade),
                'deadline' => now(),
                'quiz_result_id' => $result->id,
                'is_final' => true,
                'is_yn_locked' => true,
            ]);

            $successCount++;
        }

        if ($successCount > 0) {
            $ynSubmission->update(['results_fetched' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => $successCount . ' ta natija muvaffaqiyatli yuklandi.',
            'fetched_count' => $successCount,
            'error_count' => count($errors),
        ]);
    }

    /**
     * Yakuniy baholash qaydnomasini yaratish (DOCX).
     * JN, MT, OSKI, Test natijalarini o'z ichiga oladi.
     */
    public function generateYakuniyQaydnoma(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'semester_code' => 'required',
            'group_hemis_id' => 'required',
        ]);

        $subjectId = $request->subject_id;
        $semesterCode = $request->semester_code;
        $groupHemisId = $request->group_hemis_id;

        // YN yuborilganligini tekshirish
        $ynSubmission = YnSubmission::where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('group_hemis_id', $groupHemisId)
            ->first();

        if (!$ynSubmission) {
            return response()->json(['error' => 'YN hali yuborilmagan.'], 400);
        }

        if (!$ynSubmission->results_fetched) {
            return response()->json(['error' => 'OSKI va Test natijalari hali yuklanmagan.'], 400);
        }

        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        if (!$group) {
            return response()->json(['error' => 'Guruh topilmadi.'], 404);
        }

        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        $department = Department::where('department_hemis_id', $group->department_hemis_id)
            ->where('structure_type_code', 11)
            ->first();

        $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();

        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->first();

        if (!$subject) {
            return response()->json(['error' => 'Fan topilmadi.'], 404);
        }

        // YN snapshot baholarini olish (JN va MT)
        $latestSnapshots = YnStudentGrade::latestPerStudent($ynSubmission->id)->get();
        $savedJn = $latestSnapshots->pluck('jn', 'student_hemis_id')->toArray();
        $savedMt = $latestSnapshots->pluck('mt', 'student_hemis_id')->toArray();

        // Talabalarni olish
        $students = Student::select('full_name as student_name', 'student_id_number as student_id', 'hemis_id', 'id')
            ->where('group_id', $groupHemisId)
            ->groupBy('id')
            ->orderBy('full_name')
            ->get();

        // OSKI va Test natijalarini olish
        $studentHemisIds = $students->pluck('hemis_id')->toArray();

        $oskiGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 101)
            ->select('student_hemis_id', DB::raw('ROUND(AVG(grade)) as avg_grade'))
            ->groupBy('student_hemis_id')
            ->pluck('avg_grade', 'student_hemis_id')
            ->toArray();

        $testGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 102)
            ->select('student_hemis_id', DB::raw('ROUND(AVG(grade)) as avg_grade'))
            ->groupBy('student_hemis_id')
            ->pluck('avg_grade', 'student_hemis_id')
            ->toArray();

        // Davomat
        $attendanceData = [];
        foreach ($students as $student) {
            $qoldirgan = (int) Attendance::where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('student_hemis_id', $student->hemis_id)
                ->sum('absent_off');

            $totalAcload = $subject->total_acload ?: 1;
            $attendanceData[$student->hemis_id] = round($qoldirgan * 100 / $totalAcload, 2);
        }

        // O'qituvchilarni olish
        $studentIds = $students->pluck('hemis_id');

        $maruzaTeacher = DB::table('student_grades as s')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
            ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
            ->where('s.subject_id', $subjectId)
            ->where('s.training_type_code', 11)
            ->whereIn('s.student_hemis_id', $studentIds)
            ->groupBy('s.employee_id')
            ->first();

        $otherTeachers = DB::table('student_grades as s')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
            ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
            ->where('s.subject_id', $subjectId)
            ->where('s.training_type_code', '!=', 11)
            ->whereNotIn('s.training_type_code', [99, 100, 101, 102, 103])
            ->whereIn('s.student_hemis_id', $studentIds)
            ->groupBy('s.employee_id')
            ->get();

        $otherTeacherNames = [];
        foreach ($otherTeachers as $t) {
            foreach (explode(', ', $t->full_names) as $name) {
                $name = trim($name);
                if ($name && !in_array($name, $otherTeacherNames)) {
                    $otherTeacherNames[] = $name;
                }
            }
        }

        $maruzaText = $maruzaTeacher->full_names ?? '-';
        $otherText = implode(', ', $otherTeacherNames) ?: '-';

        // Word hujjat yaratish
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(12);

        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginTop' => 600,
            'marginBottom' => 600,
            'marginLeft' => 800,
            'marginRight' => 600,
        ]);

        // Sarlavha
        $section->addText(
            '12-shakl',
            ['bold' => true, 'size' => 11],
            ['alignment' => Jc::END, 'spaceAfter' => 100]
        );

        $section->addText(
            'YAKUNIY BAHOLASH QAYDNOMASI',
            ['bold' => true, 'size' => 14],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        // Ma'lumotlar
        $infoStyle = ['size' => 11];
        $infoBold = ['bold' => true, 'size' => 11];
        $infoParaStyle = ['spaceAfter' => 40];

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText('Fakultet: ', $infoBold);
        $textRun->addText($department->name ?? '-', $infoStyle);

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText('Kurs: ', $infoBold);
        $textRun->addText($semester->level_name ?? '-', $infoStyle);
        $textRun->addText('     Semestr: ', $infoBold);
        $textRun->addText($semester->name ?? '-', $infoStyle);
        $textRun->addText('     Guruh: ', $infoBold);
        $textRun->addText($group->name ?? '-', $infoStyle);

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText('Fan: ', $infoBold);
        $textRun->addText($subject->subject_name ?? '-', $infoStyle);

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText("Ma'ruzachi: ", $infoBold);
        $textRun->addText($maruzaText, $infoStyle);

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText("Amaliyot o'qituvchilari: ", $infoBold);
        $textRun->addText($otherText, $infoStyle);

        $textRun = $section->addTextRun($infoParaStyle);
        $textRun->addText('Soatlar soni: ', $infoBold);
        $textRun->addText($subject->total_acload ?? '-', $infoStyle);

        // exam_schedules dan OSKI/Test sanalarini olish
        $wordExamSchedule = ExamSchedule::where('group_hemis_id', $group->group_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        $textRun = $section->addTextRun(['spaceAfter' => 100]);
        if ($wordExamSchedule) {
            if ($wordExamSchedule->oski_date) {
                $textRun->addText('OSKI sanasi: ', $infoBold);
                $textRun->addText($wordExamSchedule->oski_date->format('d.m.Y'), $infoStyle);
                $textRun->addText('     ', $infoStyle);
            } elseif ($wordExamSchedule->oski_na) {
                $textRun->addText('OSKI: ', $infoBold);
                $textRun->addText('n/a', $infoStyle);
                $textRun->addText('     ', $infoStyle);
            }
            if ($wordExamSchedule->test_date) {
                $textRun->addText('Test sanasi: ', $infoBold);
                $textRun->addText($wordExamSchedule->test_date->format('d.m.Y'), $infoStyle);
            } elseif ($wordExamSchedule->test_na) {
                $textRun->addText('Test: ', $infoBold);
                $textRun->addText('n/a', $infoStyle);
            }
        } else {
            $textRun->addText('YN sanalari belgilanmagan', $infoStyle);
        }

        $section->addText(
            'Sana: ' . now()->format('d.m.Y'),
            $infoStyle,
            ['alignment' => Jc::END, 'spaceAfter' => 150]
        );

        // Jadval
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => '000000',
            'cellMargin' => 40,
        ];
        $phpWord->addTableStyle('YakuniyTable', $tableStyle);
        $table = $section->addTable('YakuniyTable');

        $headerFont = ['bold' => true, 'size' => 10];
        $cellFont = ['size' => 10];
        $cellFontRed = ['size' => 10, 'color' => 'FF0000'];
        $cellFontGreen = ['size' => 10, 'color' => '008000'];
        $headerBg = ['bgColor' => 'D9E2F3', 'valign' => 'center'];
        $cellCenter = ['alignment' => Jc::CENTER];
        $cellLeft = ['alignment' => Jc::START];

        // Sarlavha qatori
        $headerRow = $table->addRow(400);
        $headerRow->addCell(500, $headerBg)->addText('№', $headerFont, $cellCenter);
        $headerRow->addCell(3500, $headerBg)->addText('Talaba F.I.O', $headerFont, $cellCenter);
        $headerRow->addCell(1500, $headerBg)->addText('Talaba ID', $headerFont, $cellCenter);
        $headerRow->addCell(900, $headerBg)->addText('JN', $headerFont, $cellCenter);
        $headerRow->addCell(900, $headerBg)->addText("O'N", $headerFont, $cellCenter);
        $headerRow->addCell(900, $headerBg)->addText('OSKI', $headerFont, $cellCenter);
        $headerRow->addCell(900, $headerBg)->addText('Test', $headerFont, $cellCenter);
        $headerRow->addCell(1200, $headerBg)->addText('Davomat %', $headerFont, $cellCenter);
        $headerRow->addCell(1200, $headerBg)->addText('Yakuniy ball', $headerFont, $cellCenter);
        $headerRow->addCell(1500, $headerBg)->addText('Xulosa', $headerFont, $cellCenter);

        $rowNum = 1;
        foreach ($students as $student) {
            $markingScore = MarkingSystemScore::getByStudentHemisId($student->hemis_id);

            $jn = $savedJn[$student->hemis_id] ?? 0;
            $mt = $savedMt[$student->hemis_id] ?? 0;
            $oski = $oskiGrades[$student->hemis_id] ?? 0;
            $test = $testGrades[$student->hemis_id] ?? 0;
            $davomat = $attendanceData[$student->hemis_id] ?? 0;

            // Yakuniy ball hisoblash: JN(30%) + MT(10%) + OSKI/Test(60%)
            // Agar OSKI va Test ikkalasi ham bo'lsa, o'rtachasini olish
            $ynBall = 0;
            if ($oski > 0 && $test > 0) {
                $ynBall = round(($oski + $test) / 2);
            } elseif ($test > 0) {
                $ynBall = $test;
            } elseif ($oski > 0) {
                $ynBall = $oski;
            }

            $yakuniyBall = round($jn * 0.3 + $mt * 0.1 + $ynBall * 0.6);

            // Xulosa
            $jnFailed = $jn < $markingScore->effectiveLimit('jn');
            $mtFailed = $mt < $markingScore->effectiveLimit('mt');
            $davomatFailed = $davomat > 25;
            $oskiFailed = $oski > 0 && $oski < $markingScore->effectiveLimit('oski');
            $testFailed = $test > 0 && $test < $markingScore->effectiveLimit('test');

            $xulosa = 'O\'tdi';
            if ($jnFailed || $mtFailed || $davomatFailed || $oskiFailed || $testFailed || $yakuniyBall < ($markingScore->minimum_limit ?? 60)) {
                $xulosa = 'O\'tmadi';
            }

            $dataRow = $table->addRow();
            $dataRow->addCell(500)->addText($rowNum, $cellFont, $cellCenter);
            $dataRow->addCell(3500)->addText($student->student_name, $cellFont, $cellLeft);
            $dataRow->addCell(1500)->addText($student->student_id, $cellFont, $cellCenter);
            $dataRow->addCell(900)->addText($jn, $jnFailed ? $cellFontRed : $cellFont, $cellCenter);
            $dataRow->addCell(900)->addText($mt, $mtFailed ? $cellFontRed : $cellFont, $cellCenter);
            $dataRow->addCell(900)->addText($oski ?: '-', $cellFont, $cellCenter);
            $dataRow->addCell(900)->addText($test ?: '-', $cellFont, $cellCenter);
            $dataRow->addCell(1200)->addText(
                ($davomat != 0 ? $davomat . '%' : '0%'),
                $davomatFailed ? $cellFontRed : $cellFont,
                $cellCenter
            );
            $dataRow->addCell(1200)->addText(
                $yakuniyBall,
                $yakuniyBall < 60 ? $cellFontRed : $cellFontGreen,
                $cellCenter
            );
            $dataRow->addCell(1500)->addText(
                $xulosa,
                $xulosa === 'O\'tmadi' ? $cellFontRed : $cellFontGreen,
                $cellCenter
            );

            $rowNum++;
        }

        // Imzolar
        $section->addTextBreak(1);

        $dekan = Teacher::whereHas('deanFaculties', fn($q) => $q->where('dean_faculties.department_hemis_id', $department->department_hemis_id ?? ''))
            ->whereHas('roles', fn($q) => $q->where('name', ProjectRole::DEAN->value))
            ->first();

        $signTable = $section->addTable();

        $signRow = $signTable->addRow();
        $signRow->addCell(6500)->addText("Dekan: ___________________  " . ($dekan->full_name ?? ''), ['size' => 11]);
        $signRow->addCell(6500)->addText("Ma'ruzachi: ___________________  " . $maruzaText, ['size' => 11]);

        // QR code
        $generatedBy = null;
        if (auth()->guard('teacher')->check()) {
            $generatedBy = auth()->guard('teacher')->user()->full_name ?? auth()->guard('teacher')->user()->name ?? null;
        } elseif (auth()->guard('web')->check()) {
            $generatedBy = auth()->guard('web')->user()->name ?? null;
        }

        $tempDir = storage_path('app/public/yn_qaydnoma');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $verification = DocumentVerification::createForDocument([
            'document_type' => 'Yakuniy baholash qaydnomasi',
            'subject_name' => $subject->subject_name,
            'group_names' => $group->name,
            'semester_name' => $semester->name ?? null,
            'department_name' => $department->name ?? null,
            'generated_by' => $generatedBy,
        ]);

        $verificationUrl = $verification->getVerificationUrl();
        $qrImagePath = null;

        try {
            $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($verificationUrl) . '&size=200x200';
            $client = new \GuzzleHttp\Client();
            $qrTempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_qr.png';
            $response = $client->request('GET', $qrApiUrl, [
                'verify' => false,
                'sink' => $qrTempPath,
                'timeout' => 10,
            ]);
            if ($response->getStatusCode() == 200 && file_exists($qrTempPath) && filesize($qrTempPath) > 0) {
                $qrImagePath = $qrTempPath;
            }
        } catch (\Exception $e) {
            // QR code generatsiyasi muvaffaqiyatsiz bo'lsa, davom etamiz
        }

        if ($qrImagePath) {
            $section->addTextBreak(1);
            $section->addImage($qrImagePath, [
                'width' => 80,
                'height' => 80,
                'alignment' => Jc::START,
            ]);
            $section->addText(
                'Hujjat haqiqiyligini tekshirish uchun QR kodni skanerlang',
                ['size' => 8, 'italic' => true, 'color' => '666666'],
                ['spaceAfter' => 0]
            );
        }

        $groupNameStr = str_replace(['/', '\\', ' '], '_', $group->name);
        $subjectNameStr = str_replace(['/', '\\', ' '], '_', $subject->subject_name);
        $fileName = 'Yakuniy_baholash_qaydnomasi_' . $groupNameStr . '_' . $subjectNameStr . '.docx';
        $tempPath = $tempDir . '/' . time() . '_' . mt_rand(1000, 9999) . '_' . $fileName;

        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempPath);

        // PDF ga aylantirish
        try {
            $pdfStorageDir = storage_path('app/public/documents/verified');
            if (!is_dir($pdfStorageDir)) {
                mkdir($pdfStorageDir, 0755, true);
            }

            $pdfCommand = sprintf(
                'soffice --headless --convert-to pdf --outdir %s %s 2>&1',
                escapeshellarg($pdfStorageDir),
                escapeshellarg($tempPath)
            );
            exec($pdfCommand, $pdfOutput, $pdfReturnCode);

            $generatedPdfName = pathinfo(basename($tempPath), PATHINFO_FILENAME) . '.pdf';
            $generatedPdfFullPath = $pdfStorageDir . '/' . $generatedPdfName;

            if ($pdfReturnCode === 0 && file_exists($generatedPdfFullPath)) {
                $permanentPdfName = $verification->token . '.pdf';
                $permanentPdfPath = $pdfStorageDir . '/' . $permanentPdfName;
                rename($generatedPdfFullPath, $permanentPdfPath);
                $verification->update(['document_path' => 'documents/verified/' . $permanentPdfName]);
            }
        } catch (\Throwable $e) {
            // PDF saqlash muvaffaqiyatsiz bo'lsa, davom etamiz
        }

        // QR vaqtinchalik faylni tozalash
        if ($qrImagePath) {
            @unlink($qrImagePath);
        }

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

}

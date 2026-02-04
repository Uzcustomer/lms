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

class JournalController extends Controller
{
    public function index(Request $request)
    {
        // Get filter options for dropdowns
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $educationYears = Curriculum::select('education_year_code', 'education_year_name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderBy('education_year_code', 'desc')
            ->get();

        $faculties = Department::where('structure_type_code', 11)
            ->orderBy('name')
            ->get();

        // Build query for journal entries
        $query = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'g.department_hemis_id')
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
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
                'd.name as department_name',
                'sp.name as specialty_name',
                's.level_name',
            ])
            ->distinct();

        // Apply filters
        if ($request->filled('education_type')) {
            $query->where('c.education_type_code', $request->education_type);
        }

        if ($request->filled('education_year')) {
            $query->where('c.education_year_code', $request->education_year);
        }

        if ($request->filled('faculty')) {
            $query->where('d.id', $request->faculty);
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

        // Sorting
        $sortColumn = $request->get('sort', 'group_name');
        $sortDirection = $request->get('direction', 'asc');

        // Map sort columns to actual database columns
        $sortMap = [
            'education_type' => 'c.education_type_name',
            'education_year' => 'c.education_year_name',
            'faculty' => 'd.name',
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
            'educationYears',
            'faculties',
            'sortColumn',
            'sortDirection'
        ));
    }

    public function show(Request $request, $groupId, $subjectId, $semesterCode)
    {
        $group = Group::where('id', $groupId)->firstOrFail();
        $subject = CurriculumSubject::where('subject_id', $subjectId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('semester_code', $semesterCode)
            ->firstOrFail();

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        // Get student hemis IDs for this group
        $studentHemisIds = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->where('is_graduate', false)
            ->pluck('hemis_id');

        // Excluded training type names for Amaliyot (JB)
        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test"];
        // Excluded training type codes: 11=Ma'ruza, 99=MT, 100=ON, 101=Oski, 102=Test
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Get all JB grades with lesson_pair info and status fields
        $jbGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
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
            ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
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

        // Build unique date+pair columns for detailed view (JB) - only for pairs that have grades
        $jbColumns = $jbGradesRaw->filter(function ($g) use ($getEffectiveGrade) {
            return $getEffectiveGrade($g) !== null;
        })->map(function ($g) {
            return ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code];
        })->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->values()->toArray();

        // Build unique date+pair columns for detailed view (MT)
        $mtColumns = $mtGradesRaw->filter(function ($g) use ($getEffectiveGrade) {
            return $getEffectiveGrade($g) !== null;
        })->map(function ($g) {
            return ['date' => $g->lesson_date, 'pair' => $g->lesson_pair_code];
        })->unique(function ($item) {
            return $item['date'] . '_' . $item['pair'];
        })->values()->toArray();

        // Get distinct dates for compact view
        $jbLessonDates = $jbGradesRaw->filter(function ($g) use ($getEffectiveGrade) {
            return $getEffectiveGrade($g) !== null;
        })->pluck('lesson_date')->unique()->sort()->values()->toArray();

        $mtLessonDates = $mtGradesRaw->filter(function ($g) use ($getEffectiveGrade) {
            return $getEffectiveGrade($g) !== null;
        })->pluck('lesson_date')->unique()->sort()->values()->toArray();

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

        // Build grades data structure: student_hemis_id => date => pair => ['grade' => value, 'is_retake' => bool]
        $jbGrades = [];
        foreach ($jbGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $jbGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
            }
        }

        $mtGrades = [];
        foreach ($mtGradesRaw as $g) {
            $effectiveGrade = $getEffectiveGrade($g);
            if ($effectiveGrade !== null) {
                $mtGrades[$g->student_hemis_id][$g->lesson_date][$g->lesson_pair_code] = $effectiveGrade;
            }
        }

        // Get students basic info
        $students = DB::table('students')
            ->where('group_id', $group->group_hemis_id)
            ->where('is_graduate', false)
            ->select('id', 'hemis_id', 'full_name', 'student_id_number')
            ->orderBy('full_name')
            ->get();

        // Get other averages (ON, OSKI, Test) with status-based grade calculation
        $otherGradesRaw = DB::table('student_grades')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereIn('training_type_code', [100, 101, 102])
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

        // Get attendance data for each student
        $attendanceData = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
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
            ->pluck('grade', 'student_hemis_id')
            ->toArray();

        // Get total academic load from curriculum subject
        $totalAcload = $subject->total_acload ?? 0;

        return view('admin.journal.show', compact(
            'group',
            'subject',
            'curriculum',
            'semester',
            'students',
            'jbLessonDates',
            'mtLessonDates',
            'jbGrades',
            'mtGrades',
            'jbColumns',
            'mtColumns',
            'jbPairsPerDay',
            'mtPairsPerDay',
            'otherGrades',
            'attendanceData',
            'manualMtGrades',
            'totalAcload',
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

    // AJAX endpoints for cascading dropdowns
    public function getSpecialties(Request $request)
    {
        $query = Specialty::query();

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
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
        $query = CurriculumSubject::query();

        if ($request->filled('semester_code')) {
            $query->where('semester_code', $request->semester_code);
        }

        return $query->select('subject_id', 'subject_name')
            ->groupBy('subject_id', 'subject_name')
            ->orderBy('subject_name')
            ->get()
            ->pluck('subject_name', 'subject_id');
    }

    public function getGroups(Request $request)
    {
        $query = Group::query();

        if ($request->filled('faculty_id')) {
            $faculty = Department::find($request->faculty_id);
            if ($faculty) {
                $query->where('department_hemis_id', $faculty->department_hemis_id);
            }
        }

        if ($request->filled('specialty_id')) {
            $query->where('specialty_hemis_id', $request->specialty_id);
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
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $specialty = Specialty::where('specialty_hemis_id', $request->specialty_id)->first();
        if (!$specialty) {
            return [];
        }

        return Department::where('structure_type_code', 11)
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
}

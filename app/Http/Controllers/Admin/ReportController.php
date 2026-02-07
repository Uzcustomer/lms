<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Curriculum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function jnReport(Request $request)
    {
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);

        // Filtr uchun dropdown ma'lumotlari
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

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

        // Kafedra dropdown uchun
        $kafedraQuery = DB::table('curriculum_subjects as cs')
            ->join('curricula as c', 'cs.curricula_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
            ->join('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'c.curricula_hemis_id')
                    ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true)
            ->whereNotNull('cs.department_id')
            ->whereNotNull('cs.department_name');

        if ($selectedEducationType) {
            $kafedraQuery->where('c.education_type_code', $selectedEducationType);
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

        // ===== ASOSIY SO'ROV (Jurnal mantiqi bilan mos) =====
        // Subquery: student_grades + students JOIN (group_id uchun) + schedules EXISTS tekshiruvi
        $gradesSubquery = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->select([
                'sg.student_hemis_id',
                'sg.subject_id',
                'sg.subject_name',
                DB::raw('ROUND(AVG(sg.grade), 2) as avg_grade'),
                DB::raw('COUNT(*) as grades_count'),
            ])
            ->whereNotNull('sg.grade')
            ->where('sg.grade', '>', 0)
            ->whereNotIn('sg.training_type_code', $excludedCodes)
            ->whereNotNull('sg.lesson_date')
            // Faqat dars jadvali mavjud bo'lgan kombinatsiyalar
            ->whereExists(function ($q) use ($excludedCodes) {
                $q->select(DB::raw(1))
                    ->from('schedules as sch')
                    ->whereColumn('sch.group_id', 'st.group_id')
                    ->whereColumn('sch.subject_id', 'sg.subject_id')
                    ->whereColumn('sch.semester_code', 'sg.semester_code')
                    ->whereNotIn('sch.training_type_code', $excludedCodes)
                    ->whereNotNull('sch.lesson_date');
            })
            // Faqat joriy o'quv yili baholarini olish (eski yil baholarini chiqarish)
            ->where('sg.lesson_date', '>=', DB::raw("(
                SELECT MIN(sch2.lesson_date) FROM schedules as sch2
                WHERE sch2.group_id = st.group_id
                AND sch2.subject_id = sg.subject_id
                AND sch2.semester_code = sg.semester_code
                AND sch2.lesson_date IS NOT NULL
            )"));

        // Joriy semestr filtri (default ON)
        if ($request->get('current_semester', '1') == '1') {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->unique()
                ->toArray();
            if (!empty($currentSemesterCodes)) {
                $gradesSubquery->whereIn('sg.semester_code', $currentSemesterCodes);
            }
        }

        // Semestr filtri
        if ($request->filled('semester_code')) {
            $gradesSubquery->where('sg.semester_code', $request->semester_code);
        }

        // Fan filtri
        if ($request->filled('subject')) {
            $gradesSubquery->where('sg.subject_id', $request->subject);
        }

        // Kafedra filtri
        if ($request->filled('department')) {
            $subjectIds = DB::table('curriculum_subjects')
                ->where('department_id', $request->department)
                ->pluck('subject_id')
                ->unique()
                ->toArray();
            $gradesSubquery->whereIn('sg.subject_id', $subjectIds);
        }

        // Fakultet filtri (subquery ichida, chunki st mavjud)
        if ($request->filled('faculty')) {
            $faculty = Department::find($request->faculty);
            if ($faculty) {
                $gradesSubquery->where('st.department_id', $faculty->department_hemis_id);
            }
        }

        // Yo'nalish filtri
        if ($request->filled('specialty')) {
            $gradesSubquery->where('st.specialty_id', $request->specialty);
        }

        // Kurs filtri
        if ($request->filled('level_code')) {
            $gradesSubquery->where('st.level_code', $request->level_code);
        }

        // Guruh filtri
        if ($request->filled('group')) {
            $gradesSubquery->where('st.group_id', $request->group);
        }

        // Ta'lim turi filtri
        if ($selectedEducationType) {
            $groupIds = DB::table('groups')
                ->whereIn('curriculum_hemis_id',
                    Curriculum::where('education_type_code', $selectedEducationType)
                        ->pluck('curricula_hemis_id')
                )
                ->pluck('group_hemis_id')
                ->toArray();
            $gradesSubquery->whereIn('st.group_id', $groupIds);
        }

        $gradesSubquery->groupBy('sg.student_hemis_id', 'sg.subject_id', 'sg.subject_name');

        // Outer query: aggregatsiya natijasini students bilan JOIN (ko'rsatish uchun)
        $query = DB::table(DB::raw("({$gradesSubquery->toSql()}) as g"))
            ->mergeBindings($gradesSubquery)
            ->join('students as s', 's.hemis_id', '=', 'g.student_hemis_id')
            ->select([
                'g.student_hemis_id',
                'g.subject_id',
                'g.subject_name',
                'g.avg_grade',
                'g.grades_count',
                's.full_name',
                's.department_name',
                's.specialty_name',
                's.level_name',
                's.semester_name',
                's.group_name',
            ]);

        // Saralash
        $sortColumn = $request->get('sort', 'avg_grade');
        $sortDirection = $request->get('direction', 'desc');

        $sortMap = [
            'full_name' => 's.full_name',
            'department_name' => 's.department_name',
            'specialty_name' => 's.specialty_name',
            'level_name' => 's.level_name',
            'semester_name' => 's.semester_name',
            'group_name' => 's.group_name',
            'subject_name' => 'g.subject_name',
            'avg_grade' => 'g.avg_grade',
            'grades_count' => 'g.grades_count',
        ];

        $orderByColumn = $sortMap[$sortColumn] ?? 'g.avg_grade';
        $query->orderBy($orderByColumn, $sortDirection);

        $perPage = $request->get('per_page', 50);
        $results = $query->paginate($perPage)->appends($request->query());

        return view('admin.reports.jn', compact(
            'results',
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'kafedras',
            'sortColumn',
            'sortDirection'
        ));
    }
}

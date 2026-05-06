<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\Student;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeFilterCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetakeTeacherSubjectStatsController extends Controller
{
    /**
     * O'qituvchi-fan kesimida talabalar statistikasi (faqat o'quv bo'limi).
     * Tasdiqlangan retake arizalari + retake_groups orqali joinlanadi.
     */
    public function index(Request $request)
    {
        $this->authorize();

        $filters = $this->extractFilters($request);

        $rows = $this->buildRows($filters);

        // O'qituvchi -> Fan -> Talabalar daraxti
        $tree = [];
        foreach ($rows as $row) {
            $teacherKey = $row->teacher_id ?? 0;
            if (!isset($tree[$teacherKey])) {
                $tree[$teacherKey] = [
                    'teacher_id' => $row->teacher_id,
                    'teacher_name' => $row->teacher_name ?: __("(O'qituvchi biriktirilmagan)"),
                    'subjects' => [],
                    'total_students' => 0,
                ];
            }
            $subjectKey = $row->subject_id ?? ('name:' . $row->subject_name);
            if (!isset($tree[$teacherKey]['subjects'][$subjectKey])) {
                $tree[$teacherKey]['subjects'][$subjectKey] = [
                    'subject_id' => $row->subject_id,
                    'subject_name' => $row->subject_name,
                    'group_name' => $row->group_name,
                    'students' => [],
                ];
            }
            $tree[$teacherKey]['subjects'][$subjectKey]['students'][] = [
                'hemis_id' => $row->student_hemis_id,
                'full_name' => $row->student_full_name,
                'department_name' => $row->student_department_name,
                'specialty_name' => $row->student_specialty_name,
                'level_name' => $row->student_level_name,
                'group_id' => $row->student_group_id,
            ];
            $tree[$teacherKey]['total_students']++;
        }

        // Sortirovka: o'qituvchilar talaba sonidan kattadan kichikga
        uasort($tree, fn ($a, $b) => $b['total_students'] <=> $a['total_students']);
        // Har bir o'qituvchidagi fanlarni ham talaba sonidan kattadan kichikga
        foreach ($tree as &$t) {
            uasort($t['subjects'], fn ($a, $b) => count($b['students']) <=> count($a['students']));
        }
        unset($t);

        $totalStudents = array_sum(array_column($tree, 'total_students'));
        $totalTeachers = count($tree);
        $totalSubjects = array_sum(array_map(fn ($t) => count($t['subjects']), $tree));

        return view('teacher.academic-dept.retake-teacher-subjects.index', [
            'filters' => $filters,
            'tree' => $tree,
            'totalStudents' => $totalStudents,
            'totalTeachers' => $totalTeachers,
            'totalSubjects' => $totalSubjects,
            'educationTypes' => RetakeFilterCache::educationTypes(),
            'subjects' => RetakeFilterCache::subjects(),
        ]);
    }

    private function extractFilters(Request $request): array
    {
        return [
            'subject' => $request->input('subject') ?: $request->input('subject_id'),
            'semester_code' => $request->input('semester_code') ?: $request->input('semester_id'),
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department') ?: $request->input('department_id'),
            'specialty' => $request->input('specialty') ?: $request->input('specialty_id'),
            'level_code' => $request->input('level_code'),
            'group' => $request->input('group'),
            'teacher_search' => trim((string) $request->input('teacher_search')),
        ];
    }

    private function buildRows(array $filters)
    {
        $q = RetakeApplication::query()
            ->where('retake_applications.final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_applications.retake_group_id')
            ->join('retake_groups', 'retake_groups.id', '=', 'retake_applications.retake_group_id')
            ->whereNull('retake_groups.deleted_at')
            ->leftJoin('students', 'students.hemis_id', '=', 'retake_applications.student_hemis_id')
            ->select([
                'retake_applications.student_hemis_id',
                'students.full_name as student_full_name',
                'students.department_name as student_department_name',
                'students.specialty_name as student_specialty_name',
                'students.level_name as student_level_name',
                'students.group_id as student_group_id',
                'retake_groups.id as retake_group_id',
                'retake_groups.name as group_name',
                'retake_groups.subject_id',
                'retake_groups.subject_name',
                'retake_groups.teacher_id',
                'retake_groups.teacher_name',
            ]);

        if (!empty($filters['subject'])) {
            $q->where('retake_groups.subject_id', $filters['subject']);
        }
        if (!empty($filters['semester_code'])) {
            $q->where('retake_applications.semester_id', $filters['semester_code']);
        }

        $hasStudentFilter = !empty($filters['education_type'])
            || !empty($filters['department'])
            || !empty($filters['specialty'])
            || !empty($filters['level_code'])
            || !empty($filters['group']);

        if ($hasStudentFilter) {
            $sub = Student::query();
            if (!empty($filters['education_type'])) $sub->where('education_type_code', $filters['education_type']);
            if (!empty($filters['department']))    $sub->where('department_id', $filters['department']);
            if (!empty($filters['specialty']))     $sub->where('specialty_id', $filters['specialty']);
            if (!empty($filters['level_code']))    $sub->where('level_code', $filters['level_code']);
            if (!empty($filters['group']))         $sub->where('group_id', $filters['group']);

            $q->whereIn('retake_applications.student_hemis_id', $sub->select('hemis_id'));
        }

        if (!empty($filters['teacher_search'])) {
            $needle = '%' . $filters['teacher_search'] . '%';
            $q->where('retake_groups.teacher_name', 'like', $needle);
        }

        return $q->orderBy('retake_groups.teacher_name')
            ->orderBy('retake_groups.subject_name')
            ->orderBy('students.full_name')
            ->get();
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canViewTeacherSubjectStats(RetakeAccess::currentStaff())) {
            abort(403, "Sizda bu sahifani ko'rish ruxsati yo'q");
        }
    }
}

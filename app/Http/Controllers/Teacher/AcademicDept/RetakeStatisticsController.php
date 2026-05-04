<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Exports\RetakeApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\RetakeApplication;
use App\Models\Specialty;
use App\Models\Student;
use App\Services\Retake\RetakeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class RetakeStatisticsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize();

        $filters = $this->extractFilters($request);

        $base = $this->buildBaseQuery($filters);

        // Holat bo'yicha umumiy statistika
        $statusStats = (clone $base)
            ->select('final_status', DB::raw('count(*) as total'))
            ->groupBy('final_status')
            ->pluck('total', 'final_status')
            ->toArray();

        $statusStats = array_merge([
            'pending' => 0, 'approved' => 0, 'rejected' => 0,
        ], $statusStats);

        // Bosqich bo'yicha (faqat pending'lar uchun)
        $stageStats = [
            'dean_pending' => (clone $base)->where('dean_status', 'pending')->count(),
            'registrar_pending' => (clone $base)->where('registrar_status', 'pending')->count(),
            'academic_pending' => (clone $base)
                ->where('dean_status', 'approved')
                ->where('registrar_status', 'approved')
                ->where('academic_dept_status', 'pending')
                ->count(),
        ];

        // Eng ko'p qarzdorlikli fanlar (top 10)
        $topSubjects = (clone $base)
            ->select('subject_name', 'semester_name', DB::raw('count(*) as total'))
            ->groupBy('subject_name', 'semester_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Fakultet kesimida
        $departmentStats = (clone $base)
            ->join('students', 'students.hemis_id', '=', 'retake_applications.student_hemis_id')
            ->select('students.department_name', DB::raw('count(*) as total'))
            ->groupBy('students.department_name')
            ->orderByDesc('total')
            ->get();

        // Cascade filtrlar uchun manbalar (keshlangan)
        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();
        $subjects = \App\Services\Retake\RetakeFilterCache::subjects();

        $totalApplications = (clone $base)->count();
        $groupIds = (clone $base)->distinct()->pluck('group_id');
        $totalAmount = $groupIds->isEmpty()
            ? 0
            : \App\Models\RetakeApplicationGroup::whereIn('id', $groupIds)->sum('receipt_amount');

        return view('teacher.academic-dept.retake-statistics.index', [
            'filters' => $filters,
            'statusStats' => $statusStats,
            'stageStats' => $stageStats,
            'topSubjects' => $topSubjects,
            'departmentStats' => $departmentStats,
            'educationTypes' => $educationTypes,
            'subjects' => $subjects,
            'totalApplications' => $totalApplications,
            'totalAmount' => (float) $totalAmount,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorize();

        $filters = $this->extractFilters($request);

        $hasStudentFilter = !empty($filters['education_type'])
            || !empty($filters['department'])
            || !empty($filters['specialty'])
            || !empty($filters['level_code'])
            || !empty($filters['semester_code'])
            || !empty($filters['group']);
        $filters['student_filter'] = $hasStudentFilter;

        // Eskidan kelayotgan keylarni Export legacy nomlariga o'tkazamiz
        $filters['department_id'] = $filters['department'] ?? null;
        $filters['specialty_id'] = $filters['specialty'] ?? null;

        $fileName = 'retake_arizalar_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new RetakeApplicationsExport($filters), $fileName);
    }

    /**
     * `_retake_filters` partial yuboradigan key'larni qabul qiladi.
     * Backward compat uchun eski key'larni ham (department_id/specialty_id) tushunadi.
     */
    private function extractFilters(Request $request): array
    {
        return [
            'final_status' => $request->input('final_status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'subject' => $request->input('subject') ?: $request->input('subject_id'),
            'semester_code' => $request->input('semester_code') ?: $request->input('semester_id'),
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department') ?: $request->input('department_id'),
            'specialty' => $request->input('specialty') ?: $request->input('specialty_id'),
            'level_code' => $request->input('level_code'),
            'group' => $request->input('group'),
        ];
    }

    private function buildBaseQuery(array $filters)
    {
        $q = RetakeApplication::query();

        if (!empty($filters['final_status'])) {
            $q->where('final_status', $filters['final_status']);
        }
        if (!empty($filters['date_from'])) {
            $q->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['subject'])) {
            $q->where('subject_id', $filters['subject']);
        }
        if (!empty($filters['semester_code'])) {
            $q->where('semester_id', $filters['semester_code']);
        }

        $hasStudentFilter = !empty($filters['education_type'])
            || !empty($filters['department'])
            || !empty($filters['specialty'])
            || !empty($filters['level_code'])
            || !empty($filters['group']);

        if ($hasStudentFilter) {
            $studentQuery = Student::query();
            if (!empty($filters['education_type'])) {
                $studentQuery->where('education_type_code', $filters['education_type']);
            }
            if (!empty($filters['department'])) {
                $studentQuery->where('department_id', $filters['department']);
            }
            if (!empty($filters['specialty'])) {
                $studentQuery->where('specialty_id', $filters['specialty']);
            }
            if (!empty($filters['level_code'])) {
                $studentQuery->where('level_code', $filters['level_code']);
            }
            if (!empty($filters['group'])) {
                $studentQuery->where('group_id', $filters['group']);
            }

            $q->whereIn('student_hemis_id', $studentQuery->select('hemis_id'));
        }

        return $q;
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canViewStatistics(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda statistikani ko\'rish ruxsati yo\'q');
        }
    }
}

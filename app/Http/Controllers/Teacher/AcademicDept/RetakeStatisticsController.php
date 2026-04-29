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

        $filters = $request->only([
            'final_status', 'date_from', 'date_to',
            'subject_id', 'semester_id',
            'department_id', 'specialty_id', 'level_code',
        ]);

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

        // Form filterlari uchun manbalar
        $departments = Department::orderBy('name')->get(['department_hemis_id', 'name']);
        $specialties = Specialty::orderBy('name')->get(['specialty_hemis_id', 'name']);
        $levels = [
            ['code' => '11', 'name' => '1-kurs'],
            ['code' => '12', 'name' => '2-kurs'],
            ['code' => '13', 'name' => '3-kurs'],
            ['code' => '14', 'name' => '4-kurs'],
            ['code' => '15', 'name' => '5-kurs'],
            ['code' => '16', 'name' => '6-kurs'],
        ];

        $totalApplications = (clone $base)->count();
        $totalAmount = (clone $base)
            ->join('retake_application_groups', 'retake_application_groups.id', '=', 'retake_applications.group_id')
            ->sum('retake_application_groups.receipt_amount');

        return view('teacher.academic-dept.retake-statistics.index', [
            'filters' => $filters,
            'statusStats' => $statusStats,
            'stageStats' => $stageStats,
            'topSubjects' => $topSubjects,
            'departmentStats' => $departmentStats,
            'departments' => $departments,
            'specialties' => $specialties,
            'levels' => $levels,
            'totalApplications' => $totalApplications,
            'totalAmount' => (float) $totalAmount,
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->authorize();

        $filters = $request->only([
            'final_status', 'date_from', 'date_to',
            'subject_id', 'semester_id',
            'department_id', 'specialty_id', 'level_code',
        ]);

        $hasStudentFilter = !empty($filters['department_id']) || !empty($filters['specialty_id']) || !empty($filters['level_code']);
        $filters['student_filter'] = $hasStudentFilter;

        $fileName = 'retake_arizalar_' . now()->format('Y_m_d_His') . '.xlsx';

        return Excel::download(new RetakeApplicationsExport($filters), $fileName);
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
        if (!empty($filters['subject_id'])) {
            $q->where('subject_id', $filters['subject_id']);
        }
        if (!empty($filters['semester_id'])) {
            $q->where('semester_id', $filters['semester_id']);
        }

        $hasStudentFilter = !empty($filters['department_id']) || !empty($filters['specialty_id']) || !empty($filters['level_code']);
        if ($hasStudentFilter) {
            $studentIds = Student::query();
            if (!empty($filters['department_id'])) $studentIds->where('department_id', $filters['department_id']);
            if (!empty($filters['specialty_id']))  $studentIds->where('specialty_id', $filters['specialty_id']);
            if (!empty($filters['level_code']))    $studentIds->where('level_code', $filters['level_code']);
            $q->whereIn('student_hemis_id', $studentIds->pluck('hemis_id'));
        }

        return $q;
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canViewStatistics(Auth::guard('teacher')->user())) {
            abort(403, 'Sizda statistikani ko\'rish ruxsati yo\'q');
        }
    }
}

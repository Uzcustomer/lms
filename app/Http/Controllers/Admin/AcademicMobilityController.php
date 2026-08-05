<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AkademikMobillikAriza;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicMobilityController extends Controller
{
    public function index(Request $request): View
    {
        $studentsQuery = Student::query();
        $this->applyStudentFilters($studentsQuery, $request);

        if (!$request->has('student_status')) {
            $studentsQuery->where('student_status_code', '11');
            $selectedStatus = '11';
        } else {
            $selectedStatus = (string) $request->input('student_status', '');
        }

        $students = $studentsQuery
            ->orderBy('full_name')
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString();

        return view('admin.academic-mobility.index', [
            'students' => $students,
            'filters' => $this->filterOptions(),
            'selectedStatus' => $selectedStatus,
        ]);
    }

    public function applications(Request $request): View
    {
        $query = AkademikMobillikAriza::query()
            ->with('student')
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('phone', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('student', function (Builder $studentQuery) use ($search) {
                        $studentQuery->where('full_name', 'like', "%{$search}%")
                            ->orWhere('student_id_number', 'like', "%{$search}%")
                            ->orWhere('hemis_id', 'like', "%{$search}%");
                    });
            });
        }

        return view('admin.academic-mobility.applications', [
            'applications' => $query->paginate(30)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'phone' => ['required', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
        ], [
            'student_id.required' => 'Talabani tanlang.',
            'phone.required' => 'Telefon raqamini kiriting.',
            'reason.required' => 'Ariza berish sababini kiriting.',
            'reason.min' => 'Ariza sababi kamida 5 ta belgidan iborat bo\'lsin.',
        ]);

        $user = auth()->user() ?? auth('teacher')->user();

        AkademikMobillikAriza::create([
            ...$validated,
            'status' => 'pending',
            'created_by_id' => $user?->id,
            'created_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name,
        ]);

        return redirect()
            ->route('admin.academic-mobility.applications')
            ->with('success', 'Akademik mobillik arizasi muvaffaqiyatli yuborildi.');
    }

    private function applyStudentFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id_number', 'like', "%{$search}%")
                    ->orWhere('hemis_id', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $filters = [
            'education_type' => 'education_type_code',
            'department' => 'department_id',
            'specialty' => 'specialty_id',
            'level_code' => 'level_code',
            'semester_code' => 'semester_code',
            'group' => 'group_id',
            'student_status' => 'student_status_code',
        ];

        foreach ($filters as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }
    }

    private function filterOptions(): array
    {
        return [
            'educationTypes' => Student::query()
                ->select('education_type_code', 'education_type_name')
                ->whereNotNull('education_type_code')
                ->distinct()
                ->orderBy('education_type_name')
                ->get(),
            'departments' => Student::query()
                ->select('department_id', 'department_name')
                ->whereNotNull('department_id')
                ->distinct()
                ->orderBy('department_name')
                ->get(),
            'specialties' => Student::query()
                ->select('specialty_id', 'specialty_name')
                ->whereNotNull('specialty_id')
                ->distinct()
                ->orderBy('specialty_name')
                ->get(),
            'levels' => Student::query()
                ->select('level_code', 'level_name')
                ->whereNotNull('level_code')
                ->distinct()
                ->orderBy('level_code')
                ->get(),
            'semesters' => Student::query()
                ->select('semester_code', 'semester_name')
                ->whereNotNull('semester_code')
                ->distinct()
                ->orderBy('semester_code')
                ->get(),
            'groups' => Student::query()
                ->select('group_id', 'group_name')
                ->whereNotNull('group_id')
                ->distinct()
                ->orderBy('group_name')
                ->get(),
            'statuses' => Student::query()
                ->select('student_status_code', 'student_status_name')
                ->whereNotNull('student_status_code')
                ->distinct()
                ->orderBy('student_status_name')
                ->get(),
        ];
    }
}

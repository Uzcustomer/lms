<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AkademikMobillikAriza;
use App\Models\AkademikMobillikTasdiq;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }

        $students = $studentsQuery
            ->orderByDesc('id')
            ->paginate($perPage)
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
            ->with(['student', 'approvals'])
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('student', function (Builder $studentQuery) use ($search) {
                $studentQuery->where('full_name', 'like', "%{$search}%");
            });
        }

        return view('admin.academic-mobility.applications', [
            'applications' => $query->paginate(25)->withQueryString(),
            'activeRole' => $this->activeRole(),
            'stats' => [
                'total' => AkademikMobillikAriza::count(),
                'pending' => AkademikMobillikAriza::where('status', 'pending')->count(),
                'withDocument' => AkademikMobillikAriza::whereNotNull('document_path')->count(),
                'fullyApproved' => AkademikMobillikAriza::query()
                    ->whereHas('approvals', fn (Builder $approval) => $approval
                        ->where('role', 'oquv_bolimi')
                        ->where('status', 'approved'))
                    ->whereHas('approvals', fn (Builder $approval) => $approval
                        ->where('role', 'oquv_prorektori')
                        ->where('status', 'approved'))
                    ->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'phone' => ['required', 'string', 'max:50'],
            'transfer_destination' => ['required', 'string', 'min:2', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ], [
            'student_id.required' => 'Talabani tanlang.',
            'phone.required' => 'Telefon raqamini kiriting.',
            'transfer_destination.required' => "Mobillik bo'layotgan joyni kiriting.",
            'transfer_destination.min' => "Mobillik bo'layotgan joy kamida 2 ta belgidan iborat bo'lsin.",
            'transfer_destination.max' => "Mobillik bo'layotgan joy 1000 ta belgidan oshmasligi kerak.",
            'document.file' => 'Yuklangan hujjat fayl bo\'lishi kerak.',
            'document.mimes' => 'Hujjat PDF, Word, JPG yoki PNG formatida bo\'lishi kerak.',
            'document.max' => 'Hujjat hajmi 10 MB dan oshmasligi kerak.',
        ]);

        $user = auth()->user() ?? auth('teacher')->user();

        $application = AkademikMobillikAriza::create([
            'student_id' => $validated['student_id'],
            'phone' => $validated['phone'],
            // Eski jadval sxemasi bilan moslik uchun reason bo'sh saqlanadi.
            'reason' => '',
            'transfer_destination' => trim($validated['transfer_destination']),
            'status' => 'pending',
            'created_by_id' => $user?->id,
            'created_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name,
        ]);

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                "academic-mobility-applications/{$application->id}",
                "hujjat.{$extension}"
            );

            if (!$path) {
                $application->delete();
                throw ValidationException::withMessages([
                    'document' => 'Hujjatni saqlashda xatolik yuz berdi. Qayta urinib ko\'ring.',
                ]);
            }

            $application->update([
                'document_path' => $path,
                'document_name' => $file->getClientOriginalName(),
                'document_mime' => $file->getMimeType(),
                'document_size' => $file->getSize(),
            ]);
        }

        return redirect()
            ->route('admin.academic-mobility.applications')
            ->with('success', 'Akademik mobillik arizasi muvaffaqiyatli yuborildi.');
    }

    public function updateTransferDestination(Request $request, AkademikMobillikAriza $application): RedirectResponse
    {
        abort_unless(
            in_array($this->activeRole(), ['superadmin', 'admin', 'registrator_ofisi'], true),
            403
        );

        $validated = $request->validate([
            'transfer_destination' => ['nullable', 'string', 'max:1000'],
        ], [
            'transfer_destination.max' => "O'tish joyi 1000 ta belgidan oshmasligi kerak.",
        ]);

        $application->update([
            'transfer_destination' => filled($validated['transfer_destination'] ?? null)
                ? trim($validated['transfer_destination'])
                : null,
        ]);

        return back()->with('success', "Talabaning o'tish joyi saqlandi.");
    }

    public function downloadDocument(AkademikMobillikAriza $application): BinaryFileResponse
    {
        abort_if(
            !$application->document_path || !Storage::exists($application->document_path),
            404
        );

        return response()->file(
            Storage::path($application->document_path),
            [
                'Content-Type' => $application->document_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($application->document_path) . '"',
            ]
        );
    }

    public function uploadCurriculumDocument(Request $request, AkademikMobillikAriza $application): RedirectResponse
    {
        $this->ensureAcademicDepartmentRole();

        $validated = $request->validate([
            'curriculum_document' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ], [
            'curriculum_document.required' => "O'quv reja mosligi hujjatini tanlang.",
            'curriculum_document.mimes' => 'Hujjat PDF, Word, JPG yoki PNG formatida bo\'lishi kerak.',
            'curriculum_document.max' => 'Hujjat hajmi 10 MB dan oshmasligi kerak.',
        ]);

        $file = $validated['curriculum_document'];
        $extension = strtolower($file->getClientOriginalExtension());
        $path = $file->storeAs(
            "academic-mobility-curriculum/{$application->id}",
            "moslik.{$extension}"
        );

        if (!$path) {
            throw ValidationException::withMessages([
                'curriculum_document' => 'Hujjatni saqlashda xatolik yuz berdi. Qayta urinib ko\'ring.',
            ]);
        }

        $oldPath = $application->curriculum_document_path;

        DB::transaction(function () use ($application, $file, $path) {
            $application->update([
                'curriculum_document_path' => $path,
                'curriculum_document_name' => $file->getClientOriginalName(),
                'curriculum_document_mime' => $file->getMimeType(),
                'curriculum_document_size' => $file->getSize(),
                'status' => 'pending',
            ]);

            // Hujjat almashtirilsa, ikkala bosqich ham hujjatni qayta ko'rib chiqadi.
            $application->approvals()->delete();
        });

        if ($oldPath && $oldPath !== $path) {
            Storage::delete($oldPath);
        }

        return back()->with('success', "O'quv reja mosligi hujjati saqlandi.");
    }

    public function deleteCurriculumDocument(AkademikMobillikAriza $application): RedirectResponse
    {
        $this->ensureAcademicDepartmentRole();

        if (!$application->curriculum_document_path) {
            return back()->with('success', "O'quv reja mosligi hujjati allaqachon olib tashlangan.");
        }

        $path = $application->curriculum_document_path;

        DB::transaction(function () use ($application) {
            $application->update([
                'curriculum_document_path' => null,
                'curriculum_document_name' => null,
                'curriculum_document_mime' => null,
                'curriculum_document_size' => null,
                'status' => 'pending',
            ]);

            $application->approvals()->delete();
        });

        Storage::delete($path);

        return back()->with('success', "O'quv reja mosligi hujjati olib tashlandi.");
    }

    public function curriculumDocument(AkademikMobillikAriza $application): BinaryFileResponse
    {
        abort_unless(
            in_array($this->activeRole(), ['oquv_bolimi', 'oquv_bolimi_boshligi', 'oquv_prorektori'], true),
            403
        );

        abort_if(
            !$application->curriculum_document_path || !Storage::exists($application->curriculum_document_path),
            404
        );

        return response()->file(
            Storage::path($application->curriculum_document_path),
            [
                'Content-Type' => $application->curriculum_document_mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . basename($application->curriculum_document_path) . '"',
            ]
        );
    }

    public function decide(Request $request, AkademikMobillikAriza $application): RedirectResponse
    {
        $stage = $this->decisionStage();

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        if (!$application->curriculum_document_path) {
            throw ValidationException::withMessages([
                'decision' => "Avval o'quv reja mosligi hujjatini yuklang.",
            ]);
        }

        DB::transaction(function () use ($application, $stage, $validated) {
            $current = $application->approvals()
                ->where('role', $stage)
                ->first();

            if (
                $stage === 'oquv_bolimi'
                && (!$current || $current->status !== $validated['decision'])
            ) {
                $application->approvals()
                    ->where('role', 'oquv_prorektori')
                    ->delete();
            }

            if ($stage === 'oquv_prorektori') {
                $departmentApproved = $application->approvals()
                    ->where('role', 'oquv_bolimi')
                    ->where('status', 'approved')
                    ->exists();

                if (!$departmentApproved) {
                    throw ValidationException::withMessages([
                        'decision' => "Avval O'quv bo'limi arizani qabul qilishi kerak.",
                    ]);
                }
            }

            $user = auth()->user() ?? auth('teacher')->user();

            AkademikMobillikTasdiq::updateOrCreate(
                [
                    'application_id' => $application->id,
                    'role' => $stage,
                ],
                [
                    'status' => $validated['decision'],
                    'reviewed_by_id' => $user?->id,
                    'reviewed_by_name' => $user?->name ?? $user?->full_name ?? $user?->short_name,
                    'reviewed_at' => now(),
                ]
            );

            $statuses = $application->approvals()->pluck('status', 'role');

            $application->update([
                'status' => match (true) {
                    $statuses->contains('rejected') => 'rejected',
                    $statuses->get('oquv_bolimi') === 'approved'
                        && $statuses->get('oquv_prorektori') === 'approved' => 'approved',
                    default => 'pending',
                },
            ]);
        });

        $message = $validated['decision'] === 'approved'
            ? 'Ariza qabul qilindi.'
            : 'Ariza rad etildi.';

        return back()->with('success', $message);
    }

    private function activeRole(): string
    {
        $user = auth()->user() ?? auth('teacher')->user();

        if (!$user || !method_exists($user, 'getRoleNames')) {
            return '';
        }

        $roles = $user->getRoleNames()->values()->all();
        $sessionRole = (string) session('active_role', '');

        if ($sessionRole !== '' && in_array($sessionRole, $roles, true)) {
            return $sessionRole;
        }

        foreach (['oquv_prorektori', 'oquv_bolimi_boshligi', 'oquv_bolimi', 'registrator_ofisi'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return $roles[0] ?? '';
    }

    private function ensureAcademicDepartmentRole(): void
    {
        abort_unless(
            in_array($this->activeRole(), ['oquv_bolimi', 'oquv_bolimi_boshligi'], true),
            403
        );
    }

    private function decisionStage(): string
    {
        $activeRole = $this->activeRole();

        if (in_array($activeRole, ['oquv_bolimi', 'oquv_bolimi_boshligi'], true)) {
            return 'oquv_bolimi';
        }

        abort_unless($activeRole === 'oquv_prorektori', 403);

        return 'oquv_prorektori';
    }

    private function applyStudentFilters(Builder $query, Request $request): void
    {
        if ($request->filled('full_name')) {
            $query->where('full_name', 'like', '%' . trim((string) $request->input('full_name')) . '%');
        }

        if ($request->filled('student_id_number')) {
            $query->where('student_id_number', $request->input('student_id_number'));
        }

        $filters = [
            'education_type' => 'education_type_code',
            'department' => 'department_id',
            'specialty' => 'specialty_id',
            'level_code' => 'level_code',
            'semester_code' => 'semester_code',
            'group' => 'group_id',
            'country' => 'country_name',
            'student_status' => 'student_status_code',
        ];

        foreach ($filters as $input => $column) {
            if ($request->filled($input)) {
                $query->where($column, $request->input($input));
            }
        }

        if ($request->input('has_files') === 'yes') {
            $query->whereHas('files');
        } elseif ($request->input('has_files') === 'no') {
            $query->whereDoesntHave('files');
        }

        if ($request->input('has_admission_data') === 'yes') {
            $query->whereHas('admissionData');
        } elseif ($request->input('has_admission_data') === 'no') {
            $query->whereDoesntHave('admissionData');
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
            'countries' => Student::query()
                ->whereNotNull('country_name')
                ->where('country_name', '!=', '')
                ->distinct()
                ->orderBy('country_name')
                ->pluck('country_name'),
            'statuses' => Student::query()
                ->select('student_status_code', 'student_status_name')
                ->whereNotNull('student_status_code')
                ->distinct()
                ->orderBy('student_status_name')
                ->get(),
        ];
    }
}

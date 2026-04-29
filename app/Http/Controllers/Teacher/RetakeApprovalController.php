<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationGroup;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeApplicationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Dekan va Registrator ofisi paneli — qayta o'qish arizalarini ko'rib chiqish.
 *
 * Ikki rol bitta controllerni ishlatadi (UI va mantiq deyarli bir xil),
 * lekin ko'rinish va amal $role param bilan ajraladi.
 */
class RetakeApprovalController extends Controller
{
    public function __construct(
        private RetakeApplicationService $applicationService,
    ) {}

    /**
     * Arizalar ro'yxati. Ariza-guruhlari bo'yicha guruhlangan.
     */
    public function index(Request $request)
    {
        $user = Auth::guard('teacher')->user();
        $role = $this->detectRole($user);

        // Ariza-guruhlari joiniga ariza-bog'liq filtr
        $query = RetakeApplicationGroup::query()
            ->with([
                'student',
                'window',
                'applications' => function ($q) {
                    $q->orderBy('semester_id')->orderBy('subject_name');
                },
                'applications.deanUser',
                'applications.registrarUser',
            ])
            ->orderByDesc('created_at');

        // Dekan — faqat o'z fakulteti talabalari
        if ($role === 'dean') {
            $facultyIds = $user->deanFacultyIds; // department_hemis_id ro'yxati
            if (empty($facultyIds)) {
                $query->whereRaw('1=0');
            } else {
                $query->whereIn('student_hemis_id', function ($sub) use ($facultyIds) {
                    $sub->select('hemis_id')
                        ->from('students')
                        ->whereIn('department_id', $facultyIds);
                });
            }
        }

        // Filtrlar
        if ($filter = $request->input('filter', 'pending_mine')) {
            $this->applyFilter($query, $filter, $role);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('hemis_id', 'like', "%{$search}%");
            });
        }

        $groups = $query->paginate(20)->withQueryString();

        // Statistika
        $stats = $this->calculateStats($role, $user);

        return view('teacher.retake.index', [
            'groups' => $groups,
            'role' => $role,
            'filter' => $filter,
            'search' => $search,
            'stats' => $stats,
            'minReasonLength' => \App\Models\RetakeSetting::rejectReasonMinLength(),
        ]);
    }

    /**
     * Bitta ariza-guruhini ko'rsatish (modal yoki alohida sahifa).
     */
    public function show(int $groupId)
    {
        $user = Auth::guard('teacher')->user();
        $role = $this->detectRole($user);

        $group = RetakeApplicationGroup::with([
            'student',
            'window',
            'applications.deanUser',
            'applications.registrarUser',
            'applications.academicDeptUser',
            'applications.logs.user',
            'applications.retakeGroup.teacher',
        ])->findOrFail($groupId);

        $this->authorizeGroupView($user, $role, $group);

        return view('teacher.retake.show', [
            'group' => $group,
            'role' => $role,
            'minReasonLength' => \App\Models\RetakeSetting::rejectReasonMinLength(),
        ]);
    }

    /**
     * Ariza bo'yicha qaror (tasdiqlash yoki rad etish).
     */
    public function decide(Request $request, int $applicationId): RedirectResponse
    {
        $user = Auth::guard('teacher')->user();
        $role = $this->detectRole($user);

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|min:' . \App\Models\RetakeSetting::rejectReasonMinLength() . '|max:1000',
        ]);

        $app = RetakeApplication::findOrFail($applicationId);

        $this->authorizeApplicationDecision($user, $role, $app);

        try {
            if ($role === 'dean') {
                $this->applicationService->deanDecide(
                    $app,
                    $user,
                    $data['decision'],
                    $data['reason'] ?? null,
                );
            } else {
                $this->applicationService->registrarDecide(
                    $app,
                    $user,
                    $data['decision'],
                    $data['reason'] ?? null,
                );
            }
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', 'Qaror muvaffaqiyatli yozildi');
    }

    // ──────────────────────────────────────────────────────────────────

    private function detectRole(Teacher $user): string
    {
        // Registrator ofisi ham teacher guard'da (teachers jadvalida)
        if ($user->hasRole(ProjectRole::REGISTRAR_OFFICE->value)
            || $user->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value])
        ) {
            return 'registrar';
        }
        if ($user->hasRole(ProjectRole::DEAN->value)) {
            return 'dean';
        }
        abort(403, 'Sizda qayta o\'qish arizalarini ko\'rib chiqish ruxsati yo\'q');
    }

    private function applyFilter($query, string $filter, string $role): void
    {
        match ($filter) {
            'pending_mine' => $role === 'dean'
                ? $query->whereHas('applications', fn ($q) => $q->where('dean_status', 'pending'))
                : $query->whereHas('applications', fn ($q) => $q->where('registrar_status', 'pending')),
            'approved' => $query->whereHas('applications', fn ($q) => $q->where('final_status', 'approved')),
            'rejected' => $query->whereHas('applications', fn ($q) => $q->where('final_status', 'rejected')),
            'all' => null,
            default => null,
        };
    }

    private function calculateStats(string $role, Teacher $user): array
    {
        $base = RetakeApplication::query();

        if ($role === 'dean') {
            $facultyIds = $user->deanFacultyIds;
            if (empty($facultyIds)) {
                return ['pending' => 0, 'approved' => 0, 'rejected' => 0];
            }
            $base->whereIn('student_hemis_id', function ($sub) use ($facultyIds) {
                $sub->select('hemis_id')->from('students')->whereIn('department_id', $facultyIds);
            });
        }

        $statusColumn = $role === 'dean' ? 'dean_status' : 'registrar_status';

        return [
            'pending' => (clone $base)->where($statusColumn, 'pending')->count(),
            'approved' => (clone $base)->where($statusColumn, 'approved')->count(),
            'rejected' => (clone $base)->where($statusColumn, 'rejected')->count(),
        ];
    }

    private function authorizeGroupView(Teacher $user, string $role, RetakeApplicationGroup $group): void
    {
        if ($role === 'registrar') {
            return; // Registrator hammasini ko'radi
        }

        // Dekan — o'z fakulteti
        $student = Student::where('hemis_id', $group->student_hemis_id)->first();
        if (!$student || !RetakeAccess::deanHandlesStudent($user, $student)) {
            abort(403, 'Bu ariza sizning fakultetingizga tegishli emas');
        }
    }

    private function authorizeApplicationDecision(Teacher $user, string $role, RetakeApplication $app): void
    {
        if ($role === 'registrar') {
            return;
        }
        if (!RetakeAccess::deanCanReviewApplication($user, $app)) {
            abort(403, 'Bu ariza sizning fakultetingizga tegishli emas');
        }
    }
}

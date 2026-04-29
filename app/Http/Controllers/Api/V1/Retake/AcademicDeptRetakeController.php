<?php

namespace App\Http\Controllers\Api\V1\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\CreateRetakeGroupRequest;
use App\Http\Requests\Retake\CreateRetakePeriodRequest;
use App\Http\Requests\Retake\RejectRetakeApplicationRequest;
use App\Http\Requests\Retake\UpdateRetakeGroupRequest;
use App\Models\RetakeApplication;
use App\Models\RetakeApplicationPeriod;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use App\Services\Retake\RetakeApprovalService;
use App\Services\Retake\RetakeGroupService;
use App\Services\Retake\RetakePeriodService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicDeptRetakeController extends Controller
{
    public function __construct(
        private readonly RetakePeriodService $periodService,
        private readonly RetakeApprovalService $approvalService,
        private readonly RetakeGroupService $groupService,
    ) {
    }

    // === Qabul oynalari ===

    /**
     * GET /api/v1/academic/retake/periods
     */
    public function periodsIndex(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $query = RetakeApplicationPeriod::query()->orderByDesc('start_date');

        if ($specialtyId = $request->query('specialty_id')) {
            $query->where('specialty_id', $specialtyId);
        }
        if ($course = $request->query('course')) {
            $query->where('course', $course);
        }
        if ($semesterId = $request->query('semester_id')) {
            $query->where('semester_id', $semesterId);
        }

        return response()->json([
            'data' => $query->get()->map(fn (RetakeApplicationPeriod $p) => $this->serializePeriod($p))->values(),
        ]);
    }

    /**
     * GET /api/v1/academic/retake/periods/active
     */
    public function periodsActive(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $periods = RetakeApplicationPeriod::query()->active()->get();

        return response()->json([
            'data' => $periods->map(fn ($p) => $this->serializePeriod($p))->values(),
        ]);
    }

    /**
     * POST /api/v1/academic/retake/periods
     */
    public function periodsStore(CreateRetakePeriodRequest $request): JsonResponse
    {
        $period = $this->periodService->create(
            $request->user(),
            (int) $request->input('specialty_id'),
            (int) $request->input('course'),
            (int) $request->input('semester_id'),
            Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
            Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
        );

        return response()->json([
            'data' => $this->serializePeriod($period),
            'message' => 'Qabul oynasi yaratildi.',
        ], 201);
    }

    // === Arizalar (academic_dept ko'lami) ===

    /**
     * GET /api/v1/academic/retake/applications
     *
     * Default — academic_dept_status=pending (dekan va registrator allaqachon
     * tasdiqlagan, o'quv bo'limi qaroriy kutilmoqda).
     */
    public function applicationsIndex(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $status = $request->query('academic_dept_status', 'pending');

        $query = RetakeApplication::query()
            ->with(['student:id,hemis_id,full_name,group_name,group_id,department_id,department_name,specialty_name'])
            ->orderByDesc('submitted_at');

        if ($status !== 'all') {
            $query->where('academic_dept_status', $status);
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }
        if ($semesterId = $request->query('semester_id')) {
            $query->where('semester_id', $semesterId);
        }
        if ($departmentId = $request->query('department_id')) {
            $query->whereHas('student', fn ($q) => $q->where('department_id', $departmentId));
        }

        $applications = $query->paginate(min(100, max(10, (int) $request->query('per_page', 50))));

        return response()->json([
            'data' => collect($applications->items())->map(fn ($a) => $this->serializeApplication($a))->values(),
            'meta' => [
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/academic/retake/applications/grouped
     *
     * Kutilayotgan arizalarni fan + semestr bo'yicha guruhlangan ko'rinishda.
     */
    public function applicationsGrouped(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $rows = RetakeApplication::query()
            ->where('academic_dept_status', RetakeAcademicDeptStatus::PENDING->value)
            ->select(
                'subject_id',
                'subject_name',
                'semester_id',
                'semester_name',
                DB::raw('COUNT(*) as applications_count'),
            )
            ->groupBy('subject_id', 'subject_name', 'semester_id', 'semester_name')
            ->orderByDesc('applications_count')
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'subject_id' => (int) $r->subject_id,
                'subject_name' => $r->subject_name,
                'semester_id' => (int) $r->semester_id,
                'semester_name' => $r->semester_name,
                'applications_count' => (int) $r->applications_count,
            ])->values(),
        ]);
    }

    /**
     * POST /api/v1/academic/retake/applications/{id}/reject
     *
     * Yakka ariza rad etish (boshqalarini tasdiqlashga halaqit bermaydi).
     */
    public function applicationsReject(RejectRetakeApplicationRequest $request, int $id): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $application = RetakeApplication::findOrFail($id);
        $this->authorize('approveAsAcademicDept', $application);

        $application = $this->approvalService->rejectAsAcademicDept(
            $request->user(),
            $application,
            $request->input('rejection_reason'),
        );

        return response()->json([
            'data' => $this->serializeApplication($application),
            'message' => 'Ariza rad etildi.',
        ]);
    }

    // === Guruhlar ===

    /**
     * GET /api/v1/academic/retake/groups
     */
    public function groupsIndex(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $query = RetakeGroup::query()
            ->with('teacher:id,full_name,short_name')
            ->withCount('applications')
            ->orderByDesc('start_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        return response()->json([
            'data' => $query->get()->map(fn (RetakeGroup $g) => $this->serializeGroup($g))->values(),
        ]);
    }

    /**
     * POST /api/v1/academic/retake/groups
     */
    public function groupsStore(CreateRetakeGroupRequest $request): JsonResponse
    {
        $group = $this->groupService->createAndAssign(
            $request->user(),
            $request->input('name'),
            (int) $request->input('subject_id'),
            $request->input('subject_name'),
            (int) $request->input('semester_id'),
            $request->input('semester_name'),
            Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
            Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
            (int) $request->input('teacher_id'),
            $request->input('max_students'),
            array_map('intval', $request->input('application_ids', [])),
        );

        $group->load('teacher:id,full_name,short_name');
        $group->loadCount('applications');

        return response()->json([
            'data' => $this->serializeGroup($group),
            'message' => 'Guruh yaratildi va arizalar tasdiqlandi.',
        ], 201);
    }

    /**
     * PUT /api/v1/academic/retake/groups/{id}
     */
    public function groupsUpdate(UpdateRetakeGroupRequest $request, int $id): JsonResponse
    {
        $group = RetakeGroup::findOrFail($id);
        $this->authorize('update', $group);

        $group = $this->groupService->update(
            $group,
            Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
            Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
            (int) $request->input('teacher_id'),
            $request->input('max_students'),
        );
        $group->load('teacher:id,full_name,short_name');
        $group->loadCount('applications');

        return response()->json([
            'data' => $this->serializeGroup($group),
            'message' => 'Guruh yangilandi.',
        ]);
    }

    /**
     * GET /api/v1/academic/retake/teachers?subject_id=...
     *
     * Guruhga biriktirish uchun o'qituvchilar ro'yxati.
     */
    public function teachers(Request $request): JsonResponse
    {
        $this->ensureAcademicDept($request);

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->select('id', 'full_name', 'short_name', 'department_hemis_id')
            ->orderBy('full_name')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => $teachers->map(fn (Teacher $t) => [
                'id' => $t->id,
                'full_name' => $t->full_name,
                'short_name' => $t->short_name,
                'department_hemis_id' => $t->department_hemis_id,
            ])->values(),
        ]);
    }

    private function ensureAcademicDept(Request $request): void
    {
        $user = $request->user();
        if ($user === null
            || ! method_exists($user, 'hasRole')
            || ! $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'admin', 'superadmin'])) {
            abort(403, 'Faqat o\'quv bo\'limi xodimi foydalana oladi.');
        }
    }

    private function serializePeriod(RetakeApplicationPeriod $period): array
    {
        return [
            'id' => $period->id,
            'specialty_id' => $period->specialty_id,
            'course' => $period->course,
            'semester_id' => $period->semester_id,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'is_active' => $period->is_active,
            'is_upcoming' => $period->is_upcoming,
            'is_closed' => $period->is_closed,
            'days_left' => $period->days_left,
            'state' => match (true) {
                $period->is_active => 'active',
                $period->is_upcoming => 'upcoming',
                default => 'closed',
            },
            'created_at' => $period->created_at?->toIso8601String(),
        ];
    }

    private function serializeApplication(RetakeApplication $application): array
    {
        return [
            'id' => $application->id,
            'application_group_id' => $application->application_group_id,
            'subject_id' => $application->subject_id,
            'subject_name' => $application->subject_name,
            'semester_id' => $application->semester_id,
            'semester_name' => $application->semester_name,
            'credit' => (float) $application->credit,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'student' => [
                'id' => $application->student?->id,
                'full_name' => $application->student?->full_name,
                'group_name' => $application->student?->group_name,
                'department_name' => $application->student?->department_name,
                'specialty_name' => $application->student?->specialty_name,
            ],
            'dean_status' => $application->dean_status?->value,
            'registrar_status' => $application->registrar_status?->value,
            'academic_dept_status' => $application->academic_dept_status?->value,
            'final_status' => $application->final_status,
            'has_verification_code' => $application->verification_code !== null,
        ];
    }

    private function serializeGroup(RetakeGroup $group): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'subject_id' => $group->subject_id,
            'subject_name' => $group->subject_name,
            'semester_id' => $group->semester_id,
            'semester_name' => $group->semester_name,
            'start_date' => $group->start_date->toDateString(),
            'end_date' => $group->end_date->toDateString(),
            'teacher' => $group->teacher === null ? null : [
                'id' => $group->teacher->id,
                'full_name' => $group->teacher->full_name,
                'short_name' => $group->teacher->short_name,
            ],
            'max_students' => $group->max_students,
            'status' => $group->status?->value,
            'applications_count' => $group->applications_count ?? null,
        ];
    }
}

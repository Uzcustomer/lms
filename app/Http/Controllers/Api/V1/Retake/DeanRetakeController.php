<?php

namespace App\Http\Controllers\Api\V1\Retake;

use App\Enums\RetakeReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\RejectRetakeApplicationRequest;
use App\Models\RetakeApplication;
use App\Models\Teacher;
use App\Services\Retake\RetakeApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeanRetakeController extends Controller
{
    public function __construct(
        private readonly RetakeApprovalService $approvalService,
    ) {
    }

    /**
     * GET /api/v1/dean/retake/applications
     *
     * Dekanning fakulteti talabalarining arizalari. Default — dean_status=pending.
     */
    public function index(Request $request): JsonResponse
    {
        $teacher = $this->resolveTeacher($request);
        $facultyIds = array_map('intval', $teacher->dean_faculty_ids ?? []);

        if (empty($facultyIds)) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $deanStatus = $request->query('dean_status', 'pending');

        $query = RetakeApplication::query()
            ->whereHas('student', fn ($q) => $q->whereIn('department_id', $facultyIds))
            ->with(['student:id,hemis_id,full_name,group_name,group_id,department_id,department_name,specialty_name', 'period'])
            ->orderByDesc('submitted_at');

        if ($deanStatus !== 'all') {
            $query->where('dean_status', $deanStatus);
        }

        if ($subjectId = $request->query('subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($groupId = $request->query('group_id')) {
            $query->whereHas('student', fn ($q) => $q->where('group_id', $groupId));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->whereHas('student', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }

        if ($registrarStatus = $request->query('registrar_status')) {
            $query->where('registrar_status', $registrarStatus);
        }

        $applications = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => $applications->items() ? collect($applications->items())->map(fn ($a) => $this->serialize($a))->values() : [],
            'meta' => [
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/dean/retake/applications/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $application = $this->loadApplication($id);
        $this->authorize('view', $application);

        return response()->json([
            'data' => $this->serialize($application, includeFull: true),
        ]);
    }

    /**
     * POST /api/v1/dean/retake/applications/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $application = $this->loadApplication($id);
        $this->authorize('approveAsDean', $application);

        $application = $this->approvalService->approveAsDean($request->user(), $application);

        return response()->json([
            'data' => $this->serialize($application),
            'message' => 'Ariza tasdiqlandi.',
        ]);
    }

    /**
     * POST /api/v1/dean/retake/applications/{id}/reject
     */
    public function reject(RejectRetakeApplicationRequest $request, int $id): JsonResponse
    {
        $application = $this->loadApplication($id);
        $this->authorize('approveAsDean', $application);

        $application = $this->approvalService->rejectAsDean(
            $request->user(),
            $application,
            $request->input('rejection_reason'),
        );

        return response()->json([
            'data' => $this->serialize($application),
            'message' => 'Ariza rad etildi.',
        ]);
    }

    private function resolveTeacher(Request $request): Teacher
    {
        $user = $request->user();
        if (! $user instanceof Teacher) {
            abort(403, 'Faqat Teacher modelidagi xodim foydalana oladi.');
        }
        if (! $user->hasRole('dekan')) {
            abort(403, 'Faqat dekan rolidagi xodim foydalana oladi.');
        }
        return $user;
    }

    private function loadApplication(int $id): RetakeApplication
    {
        return RetakeApplication::query()
            ->with(['student', 'period', 'retakeGroup.teacher', 'logs'])
            ->findOrFail($id);
    }

    private function serialize(RetakeApplication $application, bool $includeFull = false): array
    {
        $data = [
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
            'stage_description' => $application->stage_description,
        ];

        if ($includeFull) {
            $data['receipt'] = [
                'original_name' => $application->receipt_original_name,
                'size' => $application->receipt_size,
                'mime' => $application->receipt_mime,
            ];
            $data['student_note'] = $application->student_note;
            $data['rejection_reasons'] = [
                'dean' => $application->dean_rejection_reason,
                'registrar' => $application->registrar_rejection_reason,
                'academic_dept' => $application->academic_dept_rejection_reason,
            ];
            if ($application->relationLoaded('logs')) {
                $data['logs'] = $application->logs->map(fn ($log) => [
                    'action' => $log->action?->value,
                    'note' => $log->note,
                    'created_at' => $log->created_at?->toIso8601String(),
                ])->values();
            }
        }

        return $data;
    }
}

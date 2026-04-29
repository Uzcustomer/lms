<?php

namespace App\Http\Controllers\Api\V1\Retake;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\SubmitRetakeApplicationRequest;
use App\Models\RetakeApplication;
use App\Models\Student;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakePeriodService;
use App\Services\Retake\StudentDebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

class StudentRetakeController extends Controller
{
    public function __construct(
        private readonly StudentDebtService $debtService,
        private readonly RetakeApplicationService $applicationService,
        private readonly RetakePeriodService $periodService,
    ) {
    }

    /**
     * GET /api/v1/student/retake/curriculum
     *
     * Talabaning akkreditatsiya bahosi mavjud bo'lmagan (qarzdor) fanlari va
     * har biri uchun joriy ariza holati.
     */
    public function curriculum(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $debts = $this->debtService->getDebtSubjects($student);

        return response()->json([
            'data' => $debts->values(),
            'meta' => [
                'total' => $debts->count(),
                'eligible_count' => $debts->where('is_eligible_for_new', true)->count(),
                'max_subjects_per_application' => RetakeApplicationService::MAX_SUBJECTS,
            ],
        ]);
    }

    /**
     * GET /api/v1/student/retake/period/active
     *
     * Talabaning yo'nalish va kursi uchun joriy faol oyna.
     */
    public function activePeriod(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $active = $this->periodService->findActiveForStudent($student);
        $latest = $active ?? $this->periodService->findLatestForStudent($student);

        if ($latest === null) {
            return response()->json([
                'data' => null,
                'state' => 'no_period',
                'message' => "Sizning yo'nalishingiz va kursingiz uchun qayta o'qish ariza qabul qilish oynasi hali ochilmagan.",
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $latest->id,
                'specialty_id' => $latest->specialty_id,
                'course' => $latest->course,
                'semester_id' => $latest->semester_id,
                'start_date' => $latest->start_date->toDateString(),
                'end_date' => $latest->end_date->toDateString(),
                'is_active' => $latest->is_active,
                'is_upcoming' => $latest->is_upcoming,
                'is_closed' => $latest->is_closed,
                'days_left' => $latest->days_left,
            ],
            'state' => match (true) {
                $latest->is_active => 'active',
                $latest->is_upcoming => 'upcoming',
                default => 'closed',
            },
        ]);
    }

    /**
     * POST /api/v1/student/retake/applications
     *
     * Yangi ko'p fanli ariza yuborish.
     */
    public function store(SubmitRetakeApplicationRequest $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $applications = $this->applicationService->submit(
            $student,
            $request->input('subjects'),
            $request->file('receipt'),
            $request->input('student_note'),
        );

        $first = $applications->first();
        return response()->json([
            'data' => [
                'application_group_id' => $first?->application_group_id,
                'submitted_at' => $first?->submitted_at?->toIso8601String(),
                'count' => $applications->count(),
                'applications' => $applications->map(fn (RetakeApplication $a) => $this->serialize($a))->values(),
            ],
            'message' => 'Ariza muvaffaqiyatli yuborildi.',
        ], 201);
    }

    /**
     * GET /api/v1/student/retake/applications
     *
     * Talabaning barcha arizalari (eng yangisidan).
     */
    public function index(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        $query = RetakeApplication::query()
            ->where('student_id', $student->id)
            ->with(['period', 'retakeGroup.teacher'])
            ->orderByDesc('submitted_at');

        if ($groupId = $request->query('application_group_id')) {
            $query->where('application_group_id', $groupId);
        }

        $applications = $query->get();

        return response()->json([
            'data' => $applications->map(fn ($a) => $this->serialize($a))->values(),
        ]);
    }

    /**
     * GET /api/v1/student/retake/applications/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $student = $this->resolveStudent($request);
        $application = RetakeApplication::query()
            ->where('student_id', $student->id)
            ->with(['period', 'retakeGroup.teacher', 'logs'])
            ->findOrFail($id);

        $this->authorize('view', $application);

        return response()->json([
            'data' => $this->serialize($application, includeLogs: true),
        ]);
    }

    /**
     * GET /api/v1/student/retake/applications/{id}/document
     *
     * Avto-generatsiya qilingan ariza DOCX fayli (8-bosqichda implementatsiya).
     */
    public function downloadDocument(Request $request, int $id): JsonResponse|BinaryFileResponse|StreamedResponse
    {
        $student = $this->resolveStudent($request);
        $application = RetakeApplication::where('student_id', $student->id)->findOrFail($id);
        $this->authorize('downloadFiles', $application);

        if ($application->generated_doc_path === null
            || ! Storage::disk('local')->exists($application->generated_doc_path)) {
            return response()->json([
                'message' => 'Ariza hujjati hali generatsiya qilinmagan.',
            ], 404);
        }

        return Storage::disk('local')->download(
            $application->generated_doc_path,
            "ariza-{$application->application_group_id}.docx",
        );
    }

    /**
     * GET /api/v1/student/retake/applications/{id}/tasdiqnoma
     *
     * Tasdiqnoma PDF (8-bosqichda generatsiya qilinadi).
     */
    public function downloadTasdiqnoma(Request $request, int $id): JsonResponse|BinaryFileResponse|StreamedResponse
    {
        $student = $this->resolveStudent($request);
        $application = RetakeApplication::where('student_id', $student->id)->findOrFail($id);
        $this->authorize('downloadFiles', $application);

        if ($application->tasdiqnoma_pdf_path === null
            || ! Storage::disk('local')->exists($application->tasdiqnoma_pdf_path)) {
            return response()->json([
                'message' => 'Tasdiqnoma hali generatsiya qilinmagan.',
            ], 404);
        }

        return Storage::disk('local')->download(
            $application->tasdiqnoma_pdf_path,
            "tasdiqnoma-{$application->id}.pdf",
        );
    }

    private function resolveStudent(Request $request): Student
    {
        $user = $request->user();
        if (! $user instanceof Student) {
            abort(403, 'Faqat talaba foydalana oladi.');
        }
        return $user;
    }

    private function serialize(RetakeApplication $application, bool $includeLogs = false): array
    {
        $data = [
            'id' => $application->id,
            'application_group_id' => $application->application_group_id,
            'subject_id' => $application->subject_id,
            'subject_name' => $application->subject_name,
            'semester_id' => $application->semester_id,
            'semester_name' => $application->semester_name,
            'credit' => (float) $application->credit,
            'student_note' => $application->student_note,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'final_status' => $application->final_status,
            'stage_description' => $application->stage_description,
            'dean_status' => $application->dean_status?->value,
            'registrar_status' => $application->registrar_status?->value,
            'academic_dept_status' => $application->academic_dept_status?->value,
            'rejection_reason' => $application->dean_rejection_reason
                ?? $application->registrar_rejection_reason
                ?? $application->academic_dept_rejection_reason,
            'has_verification_code' => $application->verification_code !== null,
            'has_tasdiqnoma' => $application->tasdiqnoma_pdf_path !== null,
        ];

        if ($application->retakeGroup !== null) {
            $group = $application->retakeGroup;
            $data['retake_group'] = [
                'id' => $group->id,
                'name' => $group->name,
                'start_date' => $group->start_date->toDateString(),
                'end_date' => $group->end_date->toDateString(),
                'teacher_name' => $group->teacher?->full_name,
                'status' => $group->status?->value,
            ];
        }

        if ($includeLogs && $application->relationLoaded('logs')) {
            $data['logs'] = $application->logs->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action?->value,
                'note' => $log->note,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values();
        }

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Services\AbsenceExcuseSubmission;
use App\Services\SababliRetakePlan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil ilova uchun sababli ariza. Qoidalar veb forma bilan bir xil —
 * AbsenceExcuseSubmission ikkalasida ham ishlatiladi.
 */
class AbsenceExcuseApiController extends Controller
{
    public function reasons(): JsonResponse
    {
        $reasons = collect(AbsenceExcuse::REASONS)->map(fn($data, $key) => [
            'key' => $key,
            'label' => $data['label'],
            'document' => $data['document'],
            'max_days' => $data['max_days'],
            'note' => $data['note'],
        ])->values();

        return response()->json([
            'data' => $reasons,
            // Yoqilgan bo'lsa — 10 kunlik topshirish muddati tekshirilmaydi
            'no_day_limit' => AbsenceExcuseSubmission::noDayLimit(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $excuses = AbsenceExcuse::byStudent($student->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($e) => $this->formatExcuse($e));

        return response()->json(['data' => $excuses]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->with('makeups')->findOrFail($id);

        return response()->json(['data' => $this->formatExcuseDetail($excuse)]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user();

        $request->validate(AbsenceExcuseSubmission::rules($student, $request), AbsenceExcuseSubmission::messages());

        $makeupDates = AbsenceExcuseSubmission::makeupPlan($request);
        if (is_string($makeupDates)) {
            return response()->json([
                'message' => $makeupDates,
                'errors' => ['makeup_dates' => [$makeupDates]],
            ], 422);
        }

        try {
            $excuse = AbsenceExcuseSubmission::create($student, $request, $makeupDates);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Xatolik: ' . $e->getMessage()], 500);
        }

        $excuse->load('makeups');

        return response()->json([
            'message' => 'Arizangiz muvaffaqiyatli yuborildi!',
            'data' => $this->formatExcuseDetail($excuse),
        ], 201);
    }

    /**
     * Veb formadagi kabi nazoratlar ro'yxati; har biriga avtomatik qayta topshirish kuni
     * (makeup_date) qo'yilgan. Ilova kunlarni ko'chirsa — retake_start..retake_until
     * oralig'ida, SababliRetakePlan::validate qoidalari bo'yicha (saqlashda qayta tekshiriladi).
     */
    public function missedAssessments(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $student = $request->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $excuseDays = AbsenceExcuseSubmission::countNonSundays($startDate, $endDate);

        $assessments = AbsenceExcuseSubmission::missedAssessments($student, $startDate, $endDate)->all();
        $retakeStart = SababliRetakePlan::firstDay();
        $plan = array_values(SababliRetakePlan::assign($assessments, $retakeStart));

        return response()->json([
            'data' => $plan,
            'excuse_days' => $excuseDays,
            'retake_start' => $retakeStart->format('Y-m-d'),
            'retake_until' => SababliRetakePlan::lastAllowedDay($plan, $retakeStart, $excuseDays),
            'max_per_day' => SababliRetakePlan::MAX_SUBJECTS_PER_DAY,
            'overlap' => AbsenceExcuse::overlapMessage((string) $student->hemis_id, $startDate, $endDate),
        ]);
    }

    public function download(Request $request, $id)
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $filePath = storage_path('app/public/' . $excuse->file_path);
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Fayl topilmadi'], 404);
        }

        return response()->download($filePath, $excuse->file_original_name);
    }

    public function downloadPdf(Request $request, $id)
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            return response()->json(['message' => 'PDF hujjat topilmadi'], 404);
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'PDF fayl topilmadi'], 404);
        }

        return response()->download($filePath, 'sababli_ariza_' . $excuse->id . '.pdf');
    }

    private function formatExcuse(AbsenceExcuse $e): array
    {
        return [
            'id' => $e->id,
            'reason' => $e->reason,
            'reason_label' => $e->reason_label,
            'doc_number' => $e->doc_number,
            'start_date' => $e->start_date->format('Y-m-d'),
            'end_date' => $e->end_date->format('Y-m-d'),
            'status' => $e->status,
            'status_label' => $e->status_label,
            'status_color' => $e->status_color,
            'created_at' => $e->created_at->format('Y-m-d H:i'),
        ];
    }

    private function formatExcuseDetail(AbsenceExcuse $e): array
    {
        $data = $this->formatExcuse($e);
        $data['description'] = $e->description;
        $data['file_original_name'] = $e->file_original_name;
        $data['reviewed_by_name'] = $e->reviewed_by_name;
        $data['rejection_reason'] = $e->rejection_reason;
        $data['reviewed_at'] = $e->reviewed_at?->format('Y-m-d H:i');
        $data['has_approved_pdf'] = $e->isApproved() && !empty($e->approved_pdf_path);
        $data['makeups'] = $e->makeups->map(fn($m) => [
            'id' => $m->id,
            'subject_name' => $m->subject_name,
            'assessment_type' => $m->assessment_type,
            'assessment_type_label' => $m->assessment_type_label ?? $m->assessment_type,
            'original_date' => $m->original_date?->format('Y-m-d'),
            'makeup_date' => $m->makeup_date?->format('Y-m-d'),
            'status' => $m->status,
        ])->toArray();

        return $data;
    }
}

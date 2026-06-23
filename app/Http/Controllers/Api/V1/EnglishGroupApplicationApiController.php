<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EnglishGroupApplicationApiController extends Controller
{
    private const ENGLISH_LEVELS = [
        'boshlangich' => "Boshlang'ich",
        'orta' => "O'rta",
        'mukammal' => 'Mukammal',
    ];

    public function index(Request $request): JsonResponse
    {
        if (!Schema::hasTable('ingliz_guruh_arizalar')) {
            return response()->json([
                'success' => true,
                'data' => [
                    'student' => $this->studentPayload($request->user()),
                    'applications' => [],
                    'latest' => null,
                    'can_submit' => true,
                    'can_resubmit' => false,
                    'english_levels' => $this->levelsPayload(),
                ],
            ]);
        }

        $student = $request->user();
        $applications = InglizGuruhAriza::where('student_hemis_id', $student->hemis_id)
            ->latest()
            ->get();

        $latest = $applications->first();
        $canResubmit = $latest
            && $latest->status === 'rejected'
            && $latest->rejection_reason_code !== 'interview_failed';
        $canSubmit = $latest === null || $canResubmit;

        return response()->json([
            'success' => true,
            'data' => [
                'student' => $this->studentPayload($student),
                'applications' => $applications->map(fn($item) => $this->formatApplication($item))->values(),
                'latest' => $latest ? $this->formatApplication($latest) : null,
                'can_submit' => $canSubmit,
                'can_resubmit' => $canResubmit,
                'english_levels' => $this->levelsPayload(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!Schema::hasTable('ingliz_guruh_arizalar')) {
            return response()->json([
                'success' => false,
                'message' => "Xizmat hozircha mavjud emas.",
            ], 503);
        }

        $student = $request->user();

        $existing = InglizGuruhAriza::where('student_hemis_id', $student->hemis_id)
            ->latest()
            ->first();

        if ($existing && ($existing->status !== 'rejected' || $existing->rejection_reason_code === 'interview_failed')) {
            return response()->json([
                'success' => false,
                'message' => "Siz bu xizmat bo'yicha allaqachon ariza yuborgansiz.",
                'data' => $this->formatApplication($existing),
            ], 422);
        }

        $data = $request->validate([
            'english_level' => 'required|in:boshlangich,orta,mukammal',
            'phone_number' => 'nullable|string|max:50',
            'certificate_pdf' => 'nullable|file|mimes:pdf|max:2048',
        ], [
            'english_level.required' => "Ingliz tilini bilish darajasi tanlanishi shart.",
            'english_level.in' => "Ingliz tili darajasi noto'g'ri tanlangan.",
            'phone_number.max' => "Telefon raqam juda uzun.",
            'certificate_pdf.mimes' => 'Til sertifikati faqat PDF formatda yuklanadi.',
            'certificate_pdf.max' => 'Til sertifikati 2 MB dan oshmasligi kerak.',
        ]);

        $application = InglizGuruhAriza::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'full_name' => $student->full_name,
            'phone_number' => $data['phone_number'] ?? $student->phone,
            'faculty_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'course_name' => $student->level_name,
            'semester_name' => $student->semester_name,
            'group_name' => $student->group_name,
            'english_level' => $data['english_level'],
            'status' => 'pending',
        ]);

        if ($request->hasFile('certificate_pdf')) {
            $dir = "english-group-applications/{$application->id}";
            $path = $request->file('certificate_pdf')->storeAs(
                $dir,
                'til_sertifikati.' . $request->file('certificate_pdf')->getClientOriginalExtension()
            );

            $application->update([
                'certificate_pdf_path' => $path,
            ]);
        }

        $this->notifyStudent($student, 'submitted', $application->fresh());

        return response()->json([
            'success' => true,
            'message' => "Ingliz tili guruhiga o'tish uchun topshirgan arizangiz muvaffaqqiyatli qabul qilindi. Til sertifikati bo'lmagan talabalar ingliz tilida suhbat asosida qabul qilinadi.",
            'data' => $this->formatApplication($application->fresh()),
        ]);
    }

    public function certificate(Request $request, int $id)
    {
        if (!Schema::hasTable('ingliz_guruh_arizalar')) {
            abort(404);
        }

        $student = $request->user();
        $application = InglizGuruhAriza::where('id', $id)
            ->where('student_hemis_id', $student->hemis_id)
            ->firstOrFail();

        abort_if(!$application->certificate_pdf_path || !Storage::exists($application->certificate_pdf_path), 404);

        return response()->file(Storage::path($application->certificate_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="til_sertifikati.pdf"',
        ]);
    }

    private function formatApplication(InglizGuruhAriza $application): array
    {
        return [
            'id' => $application->id,
            'phone_number' => $application->phone_number,
            'english_level' => $application->english_level,
            'english_level_label' => self::ENGLISH_LEVELS[$application->english_level] ?? 'Tanlanmagan',
            'status' => $application->status,
            'status_label' => $application->status_label,
            'rejection_reason_code' => $application->rejection_reason_code,
            'rejection_reason_label' => $application->rejection_reason_label,
            'admin_note' => $application->admin_note,
            'created_at' => $application->created_at?->format('d.m.Y H:i'),
            'created_at_iso' => $application->created_at?->toIso8601String(),
            'has_certificate' => !empty($application->certificate_pdf_path),
        ];
    }

    private function studentPayload($student): array
    {
        return [
            'full_name' => $student->full_name,
            'phone_number' => $student->phone,
            'faculty_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'course_name' => $student->level_name,
            'semester_name' => $student->semester_name,
            'group_name' => $student->group_name,
        ];
    }

    private function levelsPayload(): array
    {
        return collect(self::ENGLISH_LEVELS)
            ->map(fn($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    private function notifyStudent($student, string $event, InglizGuruhAriza $application): void
    {
        $title = match ($event) {
            'submitted' => "Ingliz tili guruhiga o'tish arizasi yuborildi",
            'approved' => "Ingliz tili guruhiga o'tish arizasi qabul qilindi",
            'rejected' => "Ingliz tili guruhiga o'tish arizasi rad etildi",
            default => "Ingliz tili guruhiga o'tish arizasi",
        };

        $message = match ($event) {
            'submitted' => "Arizangiz muvaffaqiyatli qabul qilindi va ko'rib chiqish uchun yuborildi.",
            'approved' => "Arizangiz admin tomonidan qabul qilindi.",
            'rejected' => "Arizangiz admin tomonidan rad etildi.",
            default => "Arizangiz bo'yicha holat yangilandi.",
        };

        if ($application->rejection_reason_label) {
            $message .= " Sabab: {$application->rejection_reason_label}.";
        }
        if ($application->admin_note) {
            $message .= " Izoh: {$application->admin_note}";
        }

        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'english_group_application',
            'title' => $title,
            'message' => $message,
            'link' => '/student/english-group-application',
            'data' => [
                'application_id' => $application->id,
                'status' => $application->status,
            ],
        ]);

        if (!empty($student->telegram_chat_id)) {
            try {
                app(TelegramService::class)->sendToUser(
                    (string) $student->telegram_chat_id,
                    "<b>{$title}</b>\n\n" . e($message)
                );
            } catch (\Throwable $e) {
                Log::warning('English group application telegram notify failed: ' . $e->getMessage(), [
                    'application_id' => $application->id,
                    'student_id' => $student->id,
                ]);
            }
        }
    }
}

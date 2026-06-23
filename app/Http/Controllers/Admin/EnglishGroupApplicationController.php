<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EnglishGroupApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InglizGuruhAriza::query()->latest();
        $statusFilter = $request->query('status');

        if ($statusFilter === null || $statusFilter === '') {
            $query->where('status', 'pending');
        } elseif ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_hemis_id', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%")
                    ->orWhere('faculty_name', 'like', "%{$search}%")
                    ->orWhere('specialty_name', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('english_level')) {
            $query->where('english_level', $request->english_level);
        }

        $applications = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => InglizGuruhAriza::where('status', 'pending')->count(),
            'approved' => InglizGuruhAriza::where('status', 'approved')->count(),
            'rejected' => InglizGuruhAriza::where('status', 'rejected')->count(),
            'total' => InglizGuruhAriza::count(),
        ];

        $englishLevels = [
            'boshlangich' => "Boshlang'ich",
            'orta' => "O'rta",
            'mukammal' => 'Mukammal',
        ];

        return view('admin.english-group-applications.index', compact('applications', 'stats', 'englishLevels'));
    }

    public function approve(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);
        $application->update([
            'status' => 'approved',
            'rejection_reason_code' => null,
            'admin_note' => null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyStudent($application, 'approved');

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', 'Ariza qabul qilindi.');
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'rejection_reason_code' => 'nullable|in:interview_failed',
            'admin_note' => 'nullable|string|max:1000|required_without:rejection_reason_code',
        ], [
            'rejection_reason_code.in' => "Noto'g'ri rad etish sababi tanlandi.",
            'admin_note.required_without' => 'Rad etish uchun sabab yoki izoh kiritilishi shart.',
            'admin_note.max' => 'Izoh juda uzun.',
        ]);

        $application = InglizGuruhAriza::findOrFail($id);
        $application->update([
            'status' => 'rejected',
            'rejection_reason_code' => $data['rejection_reason_code'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->notifyStudent($application, 'rejected');

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', 'Ariza rad etildi.');
    }

    public function certificate(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);
        abort_if(!$application->certificate_pdf_path || !Storage::exists($application->certificate_pdf_path), 404);

        return response()->file(Storage::path($application->certificate_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="til_sertifikati.pdf"',
        ]);
    }

    public function destroy(int $id)
    {
        $application = InglizGuruhAriza::findOrFail($id);

        if ($application->certificate_pdf_path) {
            Storage::delete($application->certificate_pdf_path);

            $dir = dirname($application->certificate_pdf_path);
            if ($dir && $dir !== '.') {
                Storage::deleteDirectory($dir);
            }
        }

        $application->delete();

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', "Ariza va unga biriktirilgan fayl o'chirildi.");
    }

    private function notifyStudent(InglizGuruhAriza $application, string $event): void
    {
        $student = null;
        if ($application->student_id) {
            $student = Student::find($application->student_id);
        }
        if (!$student && $application->student_hemis_id) {
            $student = Student::where('hemis_id', $application->student_hemis_id)->first();
        }
        if (!$student) {
            return;
        }

        $title = match ($event) {
            'approved' => "Ingliz tili guruhiga o'tish arizasi qabul qilindi",
            'rejected' => "Ingliz tili guruhiga o'tish arizasi rad etildi",
            default => "Ingliz tili guruhiga o'tish arizasi",
        };

        $message = match ($event) {
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

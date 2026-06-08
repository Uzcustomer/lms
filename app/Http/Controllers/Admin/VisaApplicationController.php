<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VisaApplicationsExport;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\VisaApplication;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\TemplateProcessor;

class VisaApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status'); // filter
        // Default: faqat har bir talabaning eng oxirgi arizasi. Admin "Hammasi"
        // tugmasini bossa, eski (qayta topshirilgan) arizalarni ham ko'rsatamiz.
        $showAll = $request->boolean('all');

        // Har bir hemis_id bo'yicha eng oxirgi (eng katta id) arizaning idsi
        $latestIds = VisaApplication::query()
            ->selectRaw('MAX(id) as id')
            ->groupBy('student_hemis_id')
            ->pluck('id');

        $base = VisaApplication::query();
        if (!$showAll) {
            $base->whereIn('id', $latestIds);
        }

        $query = (clone $base)->latest();
        if ($status && in_array($status, ['pending', 'reviewing', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }
        $applications = $query->paginate(50)->withQueryString();

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return view('admin.visa-applications.index', [
            'applications' => $applications,
            'counts'       => $counts,
            'status'       => $status,
            'showAll'      => $showAll,
        ]);
    }

    public function approve(VisaApplication $application, Request $request)
    {
        $application->update([
            'status'      => 'approved',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $this->notifyStudent($application);
        return redirect()->route('admin.visa-applications.index', ['status' => 'approved'])
            ->with('success', "Ariza #{$application->application_number} qabul qilindi.");
    }

    public function reject(VisaApplication $application, Request $request)
    {
        $application->update([
            'status'      => 'rejected',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        $this->notifyStudent($application);
        return redirect()->route('admin.visa-applications.index', ['status' => 'rejected'])
            ->with('success', "Ariza #{$application->application_number} rad etildi.");
    }

    /**
     * Bir nechta arizani bir vaqtning o'zida boshqa bosqichga ko'chirish.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:visa_applications,id',
            'action' => 'required|in:pending,reviewing,approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);

        $apps = VisaApplication::whereIn('id', $data['ids'])->get();
        $updated = 0;
        foreach ($apps as $app) {
            $app->update([
                'status'      => $data['action'],
                'admin_note'  => $data['admin_note'] ?? $app->admin_note,
                'reviewed_at' => now(),
                'reviewed_by' => auth()->id(),
            ]);
            $this->notifyStudent($app);
            $updated++;
        }

        return redirect()->route('admin.visa-applications.index', ['status' => $data['action']])
            ->with('success', "{$updated} ta ariza '{$data['action']}' bosqichiga ko'chirildi.");
    }

    public function destroy(VisaApplication $application)
    {
        foreach ([$application->passport_pdf_path, $application->application_pdf_path, $application->receipt_pdf_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        $num = $application->application_number;
        $application->delete();

        return back()->with('success', "Ariza #{$num} o'chirildi.");
    }

    public function file(VisaApplication $application, string $kind)
    {
        $map = [
            'passport'    => $application->passport_pdf_path,
            'application' => $application->application_pdf_path,
            'receipt'     => $application->receipt_pdf_path,
        ];
        $path = $map[$kind] ?? null;
        if (!$path || !Storage::disk('local')->exists($path)) {
            abort(404, 'File not found.');
        }
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * Excel export — agar id'lar berilsa, faqat shu arizalar; aks holda
     * joriy holat (status) bo'yicha yuklab beriladi.
     */
    public function export(Request $request)
    {
        $status = $request->input('status');
        $ids    = array_filter(array_map('intval', (array) $request->input('ids', [])));

        $name = 'visa-arizalar';
        if (!empty($ids)) {
            $name .= '-' . count($ids) . 'ta';
        } elseif ($status) {
            $name .= '-' . $status;
        }
        $name .= '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new VisaApplicationsExport($status, $ids), $name);
    }

    /**
     * Tanlangan arizalardan telex (so'rovnoma) Word hujjatini yaratadi.
     * Faqat 'approved' arizalar uchun ishlatish tavsiya etiladi.
     */
    public function telex(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:visa_applications,id',
        ]);

        $apps = VisaApplication::whereIn('id', $data['ids'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        if ($apps->isEmpty()) {
            return back()->with('error', 'Tanlangan arizalar topilmadi.');
        }

        $templatePath = resource_path('templates/visa/telex_template.docx');
        if (!file_exists($templatePath)) {
            abort(500, 'Telex shabloni topilmadi: ' . $templatePath);
        }

        $processor = new TemplateProcessor($templatePath);

        $lines = $apps
            ->values()
            ->map(function (VisaApplication $app, int $index) {
                $fullName = trim(implode(' ', array_filter([
                    $app->last_name,
                    $app->first_name,
                    $app->middle_name,
                ])));

                $details = array_filter([
                    $app->passport_number ? 'passport raqami ' . $app->passport_number : null,
                    optional($app->birth_date)->format('d.m.Y'),
                ]);

                return ($index + 1) . '. ' . $fullName
                    . ($details ? ' (' . implode(', ', $details) . ')' : '');
            })
            ->filter()
            ->implode("\n");

        $processor->setValue('applicants_list', $lines);

        $tmp = tempnam(sys_get_temp_dir(), 'telex_') . '.docx';
        $processor->saveAs($tmp);

        $filename = 'telex_' . now()->format('Ymd_His') . '.docx';

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Talabaga viza ariza holatining yangilanishi haqida Telegram orqali xabar
     * yuboradi. Talaba telegramni bog'lamagan bo'lsa, jimgina o'tib ketadi.
     */
    private function notifyStudent(VisaApplication $app): void
    {
        $student = null;
        if ($app->student_id) {
            $student = Student::find($app->student_id);
        }
        if (!$student && $app->student_hemis_id) {
            $student = Student::where('hemis_id', $app->student_hemis_id)->first();
        }
        if (!$student || !$student->telegram_chat_id) {
            return;
        }

        $statusLabel = match ($app->status) {
            'pending'   => "⏳ Kutilmoqda",
            'reviewing' => "👀 Ko'rilmoqda",
            'approved'  => "✅ Qabul qilindi",
            'rejected'  => "❌ Rad etildi",
            default     => $app->status,
        };

        $msg = "<b>Viza arizangiz holati yangilandi</b>\n\n"
            . "Ariza №: <b>{$app->application_number}</b>\n"
            . "Yangi holat: <b>{$statusLabel}</b>";

        if ($app->admin_note) {
            $note = htmlspecialchars($app->admin_note, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $msg .= "\n\n<b>Izoh:</b>\n{$note}";
        }

        try {
            app(TelegramService::class)->sendToUser((string) $student->telegram_chat_id, $msg);
        } catch (\Throwable $e) {
            Log::warning('Visa notify failed: ' . $e->getMessage(), [
                'application_id' => $app->id,
                'student_id'     => $student->id,
            ]);
        }
    }
}

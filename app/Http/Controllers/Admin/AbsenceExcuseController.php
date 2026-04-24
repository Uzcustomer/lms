<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AbsenceExcuseTemplate;
use App\Http\Controllers\Controller;
use App\Imports\AbsenceExcuseImport;
use App\Models\AbsenceExcuse;
use App\Models\DocumentTemplate;
use App\Models\ExcuseGradeOpening;
use App\Models\StudentNotification;
use App\Models\Setting;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AbsenceExcuseController extends Controller
{
    public function index(Request $request)
    {
        $query = AbsenceExcuse::with('student')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_full_name', 'like', "%{$search}%")
                    ->orWhere('student_hemis_id', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('end_date', '<=', $request->date_to);
        }

        // Filtrlash: reviewed_by bo'yicha
        if ($request->filled('reviewed_by')) {
            $query->where('reviewed_by', $request->reviewed_by);
        }

        $excuses = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => AbsenceExcuse::where('status', 'pending')->count(),
            'approved' => AbsenceExcuse::where('status', 'approved')->count(),
            'rejected' => AbsenceExcuse::where('status', 'rejected')->count(),
        ];

        // Reviewer statistikasi — kim qancha ariza tasdiqlagan/rad etgan
        $reviewerStats = AbsenceExcuse::whereNotNull('reviewed_by')
            ->selectRaw('reviewed_by, reviewed_by_name,
                SUM(CASE WHEN status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected_count,
                COUNT(*) as total_count')
            ->groupBy('reviewed_by', 'reviewed_by_name')
            ->orderByDesc('total_count')
            ->get();

        // Har bir reviewer uchun arizalar ro'yxati (modal ichida ko'rsatish uchun)
        $reviewerExcuses = AbsenceExcuse::whereNotNull('reviewed_by')
            ->whereIn('status', ['approved', 'rejected'])
            ->orderByDesc('reviewed_at')
            ->get()
            ->groupBy('reviewed_by');

        $reasons = AbsenceExcuse::reasonLabels();

        return view('admin.absence-excuses.index', compact('excuses', 'stats', 'reasons', 'reviewerStats', 'reviewerExcuses'));
    }

    public function show($id)
    {
        $excuse = AbsenceExcuse::with(['student', 'makeups' => function ($q) {
            $q->orderBy('subject_name')
              ->orderByRaw("FIELD(assessment_type, 'jn', 'mt', 'oski', 'test')");
        }])->findOrFail($id);

        // Yakshanbasiz kunlar soni
        $daysCount = 0;
        $d = $excuse->start_date->copy();
        while ($d->lte($excuse->end_date)) {
            if (!$d->isSunday()) $daysCount++;
            $d->addDay();
        }

        // Ariza sana oralig'ida talaba haqiqatan baho olgan kunlar —
        // ma'lumotnoma soxta bo'lishi mumkinligini ko'rsatuvchi konfliktlar.
        // Faqat haqiqiy baholar (grade != null va reason='absent' emas) inobatga olinadi.
        $gradeConflicts = [];
        if ($excuse->isPending()) {
            $subjectIds = $excuse->makeups->pluck('subject_id')->filter()->unique()->values();
            if ($subjectIds->isNotEmpty()) {
                $gradeConflicts = DB::table('student_grades')
                    ->where('student_hemis_id', $excuse->student_hemis_id)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereDate('lesson_date', '>=', $excuse->start_date)
                    ->whereDate('lesson_date', '<=', $excuse->end_date)
                    ->whereNotNull('grade')
                    ->where(function ($q) {
                        $q->whereNull('reason')->orWhere('reason', '!=', 'absent');
                    })
                    ->whereNull('deleted_at')
                    ->orderBy('lesson_date')
                    ->get(['lesson_date', 'subject_name', 'grade', 'lesson_pair_name'])
                    ->toArray();
            }
        }

        return view('admin.absence-excuses.show', compact('excuse', 'daysCount', 'gradeConflicts'));
    }

    public function approve($id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        if (!$excuse->isPending() && !($excuse->isApproved() && !$excuse->approved_pdf_path)) {
            return back()->with('error', 'Bu ariza allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();
        $reviewerName = $user->name ?? $user->full_name ?? $user->short_name;

        try {
            // Tasdiqlash uchun ma'lumotlarni oldindan o'rnatish
            if ($excuse->isPending()) {
                $excuse->forceFill([
                    'status' => 'approved',
                    'reviewed_by_name' => $reviewerName,
                    'reviewed_at' => now(),
                ]);
            }

            $verificationUrl = route('absence-excuse.verify', $excuse->verification_token);

            // Word shablon mavjudligini tekshirish
            $template = DocumentTemplate::getActiveByType('absence_excuse');

            $wordTemplateSuccess = false;

            if ($template) {
                try {
                    // Word shablon orqali PDF generatsiya
                    $service = new DocumentTemplateService();
                    $qrPath = $service->generateQrImage($verificationUrl);
                    $pdfPath = $service->generateAbsenceExcusePdf($excuse, $reviewerName, $qrPath);

                    // QR vaqtinchalik faylni tozalash
                    if ($qrPath) {
                        @unlink($qrPath);
                    }

                    $wordTemplateSuccess = true;
                } catch (\Throwable $e) {
                    // Word shablon orqali ishlamadi — Blade fallback ga o'tish
                    $templateError = $e->getMessage();
                    \Log::error('Word template PDF failed, falling back to Blade: ' . $templateError, [
                        'exception' => $e::class,
                        'file' => $e->getFile() . ':' . $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if (!$wordTemplateSuccess) {
                // Blade shablon orqali (fallback)
                $qrCodeSvg = null;
                $qrCodeBase64 = null;

                if (class_exists(\BaconQrCode\Writer::class)) {
                    $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                        new \BaconQrCode\Renderer\RendererStyle\RendererStyle(200, 1),
                        new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
                    );
                    $qrCodeSvg = (new \BaconQrCode\Writer($renderer))->writeString($verificationUrl);
                } else {
                    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&format=png&data=' . urlencode($verificationUrl);
                    $pngData = @file_get_contents($apiUrl);
                    if ($pngData) {
                        $qrCodeBase64 = base64_encode($pngData);
                    }
                }

                $pdf = Pdf::loadView('pdf.absence-excuse-certificate', [
                    'excuse' => $excuse,
                    'qrCodeSvg' => $qrCodeSvg,
                    'qrCodeBase64' => $qrCodeBase64,
                    'verificationUrl' => $verificationUrl,
                ]);

                $pdfPath = 'absence-excuses/approved/' . $excuse->verification_token . '.pdf';
                Storage::disk('public')->put($pdfPath, $pdf->output());
            }

            // Faqat PDF muvaffaqiyatli bo'lgandan keyin status o'zgartiriladi
            $excuse->update([
                'status' => 'approved',
                'reviewed_by' => $user->id,
                'reviewed_by_name' => $reviewerName,
                'reviewed_at' => $excuse->reviewed_at ?? now(),
                'approved_pdf_path' => $pdfPath,
            ]);

            // Talabaga notification yuborish
            $reasonLabel = $excuse->reason_label ?? $excuse->reason;
            StudentNotification::create([
                'student_id' => $excuse->student_id,
                'type' => 'absence_excuse',
                'title' => 'Sababli arizangiz qabul qilindi!',
                'message' => "Sizning \"{$reasonLabel}\" sababi bilan yuborgan arizangiz tasdiqlandi.",
                'link' => '/student/absence-excuses/' . $excuse->id,
                'data' => [
                    'excuse_id' => $excuse->id,
                    'status' => 'approved',
                    'reason_label' => $reasonLabel,
                    'start_date' => $excuse->start_date->format('d.m.Y'),
                    'end_date' => $excuse->end_date->format('d.m.Y'),
                    'reviewer_name' => $reviewerName,
                    'doc_number' => $excuse->doc_number,
                    'has_pdf' => true,
                ],
            ]);

            // Sababli ariza uchun baho ochish: ariza sana oralig'idagi baholar uchun
            $openingsCreated = 0;
            try {
                $excuse->load('makeups');

                // Ariza sana oralig'i = jurnalda ochiq bo'ladigan sanalar
                $dateFrom = $excuse->start_date;
                $dateTo = $excuse->end_date;
                $rangeDays = $dateFrom->diffInDays($dateTo) + 1;
                $jnOpeningDays = max((int) Setting::get('lesson_opening_days', 3), 1);
                $deadline = now()->addDays($jnOpeningDays)->endOfDay();

                // Har bir fan uchun faqat bitta opening yaratish (dublikat oldini olish)
                $processedSubjects = [];

                foreach ($excuse->makeups as $makeup) {
                    if (!$makeup->subject_id) {
                        continue;
                    }
                    // Bitta fan uchun bir marta ochish
                    if (in_array($makeup->subject_id, $processedSubjects)) {
                        continue;
                    }
                    $processedSubjects[] = $makeup->subject_id;

                    ExcuseGradeOpening::create([
                        'absence_excuse_id' => $excuse->id,
                        'absence_excuse_makeup_id' => $makeup->id,
                        'student_hemis_id' => $excuse->student_hemis_id,
                        'subject_id' => $makeup->subject_id,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                        'deadline' => $deadline,
                        'status' => 'active',
                    ]);
                    $openingsCreated++;
                }
            } catch (\Throwable $e) {
                \Log::warning('ExcuseGradeOpening yaratishda xatolik: ' . $e->getMessage());
            }

            $successMsg = 'Ariza muvaffaqiyatli tasdiqlandi. PDF hujjat yaratildi.';
            if ($openingsCreated > 0) {
                $successMsg .= " {$openingsCreated} ta fan uchun baho ochildi.";
            }
            if (!$wordTemplateSuccess && isset($templateError)) {
                $successMsg .= ' (Shablon xatosi: ' . $templateError . ' — Blade shablon ishlatildi)';
            }

            return back()->with('success', $successMsg);
        } catch (\Throwable $e) {
            return back()->with('error', 'PDF generatsiyada xatolik: ' . $e->getMessage());
        }
    }


    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:absence_excuses,id',
        ]);

        $excuses = AbsenceExcuse::whereIn('id', $request->ids)->get();
        $deleted = 0;

        foreach ($excuses as $excuse) {
            if ($excuse->file_path && Storage::disk('public')->exists($excuse->file_path)) {
                Storage::disk('public')->delete($excuse->file_path);
            }
            if ($excuse->approved_pdf_path && Storage::disk('public')->exists($excuse->approved_pdf_path)) {
                Storage::disk('public')->delete($excuse->approved_pdf_path);
            }
            $excuse->delete();
            $deleted++;
        }

        return back()->with('success', "{$deleted} ta ariza o'chirildi.");
    }

    public function destroy($id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        if ($excuse->file_path && Storage::disk('public')->exists($excuse->file_path)) {
            Storage::disk('public')->delete($excuse->file_path);
        }

        if ($excuse->approved_pdf_path && Storage::disk('public')->exists($excuse->approved_pdf_path)) {
            Storage::disk('public')->delete($excuse->approved_pdf_path);
        }

        $excuse->makeups()->delete();
        $excuse->delete();

        return redirect()->route('admin.absence-excuses.index')
            ->with('success', "Ariza o'chirildi.");
    }

    public function reject(Request $request, $id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        if (!$excuse->isPending()) {
            return back()->with('error', 'Bu ariza allaqachon ko\'rib chiqilgan.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'Rad etish sababini kiriting.',
        ]);

        $user = Auth::user();

        $excuse->update([
            'status' => 'rejected',
            'reviewed_by' => $user->id,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at' => now(),
        ]);

        // Talabaga notification yuborish
        $reasonLabel = $excuse->reason_label ?? $excuse->reason;
        $reviewerName = $user->name ?? $user->full_name ?? $user->short_name;
        StudentNotification::create([
            'student_id' => $excuse->student_id,
            'type' => 'absence_excuse',
            'title' => 'Sababli arizangiz rad etildi',
            'message' => "Sizning \"{$reasonLabel}\" sababi bilan yuborgan arizangiz rad etildi.",
            'link' => '/student/absence-excuses/' . $excuse->id,
            'data' => [
                'excuse_id' => $excuse->id,
                'status' => 'rejected',
                'reason_label' => $reasonLabel,
                'start_date' => $excuse->start_date->format('d.m.Y'),
                'end_date' => $excuse->end_date->format('d.m.Y'),
                'reviewer_name' => $reviewerName,
                'doc_number' => $excuse->doc_number,
                'rejection_reason' => $request->rejection_reason,
            ],
        ]);

        return back()->with('success', 'Ariza rad etildi.');
    }

    public function download($id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        $filePath = storage_path('app/public/' . $excuse->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="' . $excuse->file_original_name . '"',
        ]);
    }

    public function importTemplate()
    {
        return Excel::download(new AbsenceExcuseTemplate(), 'sababli_ariza_shablon.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ], [
            'file.required' => 'Excel faylni tanlang.',
            'file.mimes' => 'Faqat xlsx, xls yoki csv formatdagi fayllar qabul qilinadi.',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak.',
        ]);

        $user = Auth::user();
        $reviewerName = $user->name ?? $user->full_name ?? $user->short_name;

        $import = new AbsenceExcuseImport($user->id, $reviewerName);

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            foreach ($failures as $failure) {
                $errorMessages[] = "Qator {$failure->row()}: {$failure->errors()[0]}";
            }
            return back()->with('import_errors', $errorMessages)->with('error', 'Excelda validatsiya xatoliklari topildi.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Import xatolik: ' . $e->getMessage());
        }

        $message = "{$import->importedCount} ta ariza muvaffaqiyatli import qilindi.";
        if ($import->skippedCount > 0) {
            $message .= " {$import->skippedCount} ta dublikat o'tkazib yuborildi.";
        }

        if (count($import->errors) > 0) {
            return back()
                ->with('warning', $message)
                ->with('import_errors', collect($import->errors)->map(fn($e) => "Qator {$e['row']}: {$e['error']}")->toArray());
        }

        return back()->with('success', $message);
    }

    public function downloadPdf($id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            abort(404, 'PDF hujjat topilmadi');
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);
        if (!file_exists($filePath)) {
            abort(404, 'PDF fayl serverda topilmadi');
        }

        return response()->file($filePath, [
            'Content-Disposition' => 'inline; filename="sababli_ariza_' . $excuse->id . '.pdf"',
        ]);
    }
}

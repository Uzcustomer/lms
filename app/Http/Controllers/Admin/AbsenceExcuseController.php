<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AbsenceExcuseController extends Controller
{
    public function index(Request $request)
    {
        $query = AbsenceExcuse::query()->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

        $excuses = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => AbsenceExcuse::where('status', 'pending')->count(),
            'approved' => AbsenceExcuse::where('status', 'approved')->count(),
            'rejected' => AbsenceExcuse::where('status', 'rejected')->count(),
        ];

        $reasons = AbsenceExcuse::reasonLabels();

        return view('admin.absence-excuses.index', compact('excuses', 'stats', 'reasons'));
    }

    public function show($id)
    {
        $excuse = AbsenceExcuse::findOrFail($id);

        return view('admin.absence-excuses.show', compact('excuse'));
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

            $successMsg = 'Ariza muvaffaqiyatli tasdiqlandi. PDF hujjat yaratildi.';
            if (!$wordTemplateSuccess && isset($templateError)) {
                $successMsg .= ' (Shablon xatosi: ' . $templateError . ' — Blade shablon ishlatildi)';
            }

            return back()->with('success', $successMsg);
        } catch (\Throwable $e) {
            return back()->with('error', 'PDF generatsiyada xatolik: ' . $e->getMessage());
        }
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

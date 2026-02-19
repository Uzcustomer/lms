<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

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

        $reasons = AbsenceExcuse::REASONS;

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

        if (!$excuse->isPending()) {
            return back()->with('error', 'Bu ariza allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();

        $excuse->update([
            'status' => 'approved',
            'reviewed_by' => $user->id,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name,
            'reviewed_at' => now(),
        ]);

        // QR kod generatsiya
        $verificationUrl = route('absence-excuse.verify', $excuse->verification_token);
        $qrCodeBase64 = base64_encode(
            QrCode::format('png')
                ->size(200)
                ->margin(1)
                ->generate($verificationUrl)
        );

        // PDF generatsiya
        $pdf = Pdf::loadView('pdf.absence-excuse-certificate', [
            'excuse' => $excuse,
            'qrCodeBase64' => $qrCodeBase64,
            'verificationUrl' => $verificationUrl,
        ]);

        $pdfPath = 'absence-excuses/approved/' . $excuse->verification_token . '.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $excuse->update(['approved_pdf_path' => $pdfPath]);

        return back()->with('success', 'Ariza muvaffaqiyatli tasdiqlandi. PDF hujjat yaratildi.');
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

        return response()->download($filePath, $excuse->file_original_name);
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

        return response()->download($filePath, 'sababli_ariza_' . $excuse->id . '.pdf');
    }
}

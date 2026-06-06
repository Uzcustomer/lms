<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VisaApplicationController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status'); // filter

        $query = VisaApplication::query()->latest();
        if ($status && in_array($status, ['pending', 'reviewing', 'approved', 'rejected'])) {
            $query->where('status', $status);
        }

        $applications = $query->paginate(50)->withQueryString();

        $counts = VisaApplication::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return view('admin.visa-applications.index', [
            'applications' => $applications,
            'counts'       => $counts,
            'status'       => $status,
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
        return back()->with('success', "Ariza #{$application->application_number} qabul qilindi.");
    }

    public function reject(VisaApplication $application, Request $request)
    {
        $application->update([
            'status'      => 'rejected',
            'admin_note'  => $request->input('admin_note'),
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);
        return back()->with('success', "Ariza #{$application->application_number} rad etildi.");
    }

    public function destroy(VisaApplication $application)
    {
        // Fayllarni ham o'chiramiz
        foreach ([$application->passport_pdf_path, $application->application_pdf_path, $application->receipt_pdf_path] as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
        $num = $application->application_number;
        $application->delete();

        return back()->with('success', "Ariza #{$num} o'chirildi.");
    }

    /**
     * Yuklangan faylni yuklab berish/ko'rsatish.
     */
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
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentTransferApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

class StudentTransferApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $query = StudentTransferApplication::query()
            ->with('student')
            ->latest();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('phone', 'like', "%{$search}%")
                    ->orWhere('target_institution', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($student) use ($search) {
                        $student->where('full_name', 'like', "%{$search}%")
                            ->orWhere('hemis_id', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status') && in_array($request->input('status'), ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $request->input('status'));
        }

        return view('admin.student-transfer-applications.index', [
            'applications' => $query->paginate(25)->withQueryString(),
            'stats' => [
                'total' => StudentTransferApplication::count(),
                'pending' => StudentTransferApplication::where('status', 'pending')->count(),
                'approved' => StudentTransferApplication::where('status', 'approved')->count(),
                'rejected' => StudentTransferApplication::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function document(StudentTransferApplication $application): BinaryFileResponse
    {
        abort_if(
            !$application->order_path
                || $application->order_path === 'pending'
                || !Storage::exists($application->order_path),
            404
        );

        return response()->file(Storage::path($application->order_path), [
            'Content-Type' => $application->order_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($application->order_name) . '"',
        ]);
    }

    public function basisDocument(StudentTransferApplication $application): BinaryFileResponse
    {
        abort_if(
            !$application->basis_document_path
                || $application->basis_document_path === 'pending'
                || !Storage::exists($application->basis_document_path),
            404
        );

        return response()->file(Storage::path($application->basis_document_path), [
            'Content-Type' => $application->basis_document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($application->basis_document_name) . '"',
        ]);
    }

}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EnglishGroupApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = InglizGuruhAriza::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

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
}

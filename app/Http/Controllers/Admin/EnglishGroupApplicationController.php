<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'admin_note' => 'required|string|max:1000',
        ], [
            'admin_note.required' => 'Rad etish uchun izoh kiritilishi shart.',
            'admin_note.max' => 'Izoh juda uzun.',
        ]);

        $application = InglizGuruhAriza::findOrFail($id);
        $application->update([
            'status' => 'rejected',
            'admin_note' => $data['admin_note'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.english-group-applications.index', request()->query())
            ->with('success', 'Ariza rad etildi.');
    }
}

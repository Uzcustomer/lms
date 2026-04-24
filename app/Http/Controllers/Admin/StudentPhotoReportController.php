<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentPhotoReportController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentPhoto::query()
            ->leftJoin('students', 'students.student_id_number', '=', 'student_photos.student_id_number')
            ->select([
                'student_photos.*',
                'students.image as student_profile_image',
                'students.department_name as department_name',
                'students.specialty_name as specialty_name',
                'students.level_name as level_name',
                'students.group_name as student_group_name',
            ])
            ->orderByDesc('student_photos.created_at');

        if ($request->filled('status')) {
            $query->where('student_photos.status', $request->status);
        }

        if ($request->filled('search')) {
            $needle = trim($request->search);
            $query->where(function ($q) use ($needle) {
                $q->where('student_photos.full_name', 'like', "%{$needle}%")
                  ->orWhere('student_photos.student_id_number', 'like', "%{$needle}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('students.department_name', $request->department);
        }

        if ($request->filled('specialty')) {
            $query->where('students.specialty_name', $request->specialty);
        }

        if ($request->filled('level')) {
            $query->where('students.level_name', $request->level);
        }

        if ($request->filled('group')) {
            $query->where(function ($q) use ($request) {
                $q->where('students.group_name', $request->group)
                  ->orWhere('student_photos.group_name', $request->group);
            });
        }

        if ($request->filled('tutor')) {
            $query->where('student_photos.uploaded_by', 'like', '%' . $request->tutor . '%');
        }

        if ($request->filled('date_from')) {
            $query->where('student_photos.created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('student_photos.created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $photos = $query->paginate(30)->withQueryString();

        $stats = [
            'total' => StudentPhoto::count(),
            'pending' => StudentPhoto::where('status', StudentPhoto::STATUS_PENDING)->count(),
            'approved' => StudentPhoto::where('status', StudentPhoto::STATUS_APPROVED)->count(),
            'rejected' => StudentPhoto::where('status', StudentPhoto::STATUS_REJECTED)->count(),
        ];

        $departments = Student::select('department_name')
            ->whereNotNull('department_name')
            ->distinct()
            ->orderBy('department_name')
            ->pluck('department_name');

        $specialties = Student::select('specialty_name')
            ->whereNotNull('specialty_name')
            ->distinct()
            ->orderBy('specialty_name')
            ->pluck('specialty_name');

        $levels = Student::select('level_name')
            ->whereNotNull('level_name')
            ->distinct()
            ->orderBy('level_name')
            ->pluck('level_name');

        return view('admin.student-photos.index', compact(
            'photos', 'stats', 'departments', 'specialties', 'levels'
        ));
    }

    public function approve(Request $request, $id)
    {
        $photo = StudentPhoto::findOrFail($id);

        if (!$photo->isPending()) {
            return back()->with('error', 'Bu rasm allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();
        $photo->update([
            'status' => StudentPhoto::STATUS_APPROVED,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin',
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Rasm tasdiqlandi.');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $photo = StudentPhoto::findOrFail($id);

        if (!$photo->isPending()) {
            return back()->with('error', 'Bu rasm allaqachon ko\'rib chiqilgan.');
        }

        $user = Auth::user();
        $photo->update([
            'status' => StudentPhoto::STATUS_REJECTED,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? $user->short_name ?? 'Admin',
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Rasm rad etildi.');
    }
}

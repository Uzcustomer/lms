<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ExamAppealController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('exam_appeals')) {
            $appeals = collect();
            $stats = ['pending' => 0, 'reviewing' => 0, 'approved' => 0, 'rejected' => 0];
            return view('admin.exam-appeals.index', ['appeals' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20), 'stats' => $stats]);
        }

        $query = ExamAppeal::with('student')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject_name', 'like', "%{$search}%")
                  ->orWhere('employee_name', 'like', "%{$search}%")
                  ->orWhereHas('student', function ($sq) use ($search) {
                      $sq->where('full_name', 'like', "%{$search}%")
                         ->orWhere('student_id_number', 'like', "%{$search}%");
                  });
            });
        }

        $appeals = $query->paginate(20)->withQueryString();

        $stats = [
            'pending' => ExamAppeal::where('status', 'pending')->count(),
            'reviewing' => ExamAppeal::where('status', 'reviewing')->count(),
            'approved' => ExamAppeal::where('status', 'approved')->count(),
            'rejected' => ExamAppeal::where('status', 'rejected')->count(),
        ];

        // Reviewer statistika
        $reviewerStats = ExamAppeal::whereNotNull('reviewed_by')
            ->select(
                'reviewed_by',
                'reviewed_by_name',
                DB::raw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count"),
                DB::raw("COUNT(*) as total_count")
            )
            ->groupBy('reviewed_by', 'reviewed_by_name')
            ->orderByDesc('total_count')
            ->get();

        $reviewerAppeals = [];
        foreach ($reviewerStats as $reviewer) {
            $reviewerAppeals[$reviewer->reviewed_by] = ExamAppeal::with('student')
                ->where('reviewed_by', $reviewer->reviewed_by)
                ->whereIn('status', ['approved', 'rejected'])
                ->orderByDesc('reviewed_at')
                ->get();
        }

        return view('admin.exam-appeals.index', compact('appeals', 'stats', 'reviewerStats', 'reviewerAppeals'));
    }

    public function show($id)
    {
        $appeal = ExamAppeal::with('student')->findOrFail($id);

        return view('admin.exam-appeals.show', compact('appeal'));
    }

    public function approve(Request $request, $id)
    {
        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:1000'],
            'new_grade' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $appeal = ExamAppeal::findOrFail($id);
        $user = Auth::user() ?? Auth::guard('teacher')->user();

        $appeal->update([
            'status' => ExamAppeal::STATUS_APPROVED,
            'reviewed_by' => $user->id,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? 'Admin',
            'review_comment' => $request->review_comment,
            'new_grade' => $request->new_grade,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.exam-appeals.show', $appeal->id)
            ->with('success', 'Apellyatsiya qabul qilindi.');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'review_comment' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'review_comment.required' => 'Rad etish sababini yozing.',
            'review_comment.min' => 'Sabab kamida 5 ta belgidan iborat bo\'lishi kerak.',
        ]);

        $appeal = ExamAppeal::findOrFail($id);
        $user = Auth::user() ?? Auth::guard('teacher')->user();

        $appeal->update([
            'status' => ExamAppeal::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_by_name' => $user->name ?? $user->full_name ?? 'Admin',
            'review_comment' => $request->review_comment,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.exam-appeals.show', $appeal->id)
            ->with('success', 'Apellyatsiya rad etildi.');
    }

    public function download($id)
    {
        $appeal = ExamAppeal::findOrFail($id);

        if (!$appeal->file_path || !Storage::disk('public')->exists($appeal->file_path)) {
            abort(404, 'Fayl topilmadi.');
        }

        return Storage::disk('public')->download($appeal->file_path, $appeal->file_original_name);
    }
}

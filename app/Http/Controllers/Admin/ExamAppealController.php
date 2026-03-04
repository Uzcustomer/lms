<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamAppeal;
use App\Models\ExamAppealComment;
use App\Models\StudentNotification;
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
        $eagerLoad = ['student'];
        if (Schema::hasTable('exam_appeal_comments')) {
            $eagerLoad[] = 'comments';
        }
        $appeal = ExamAppeal::with($eagerLoad)->findOrFail($id);

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

        StudentNotification::create([
            'student_id' => $appeal->student_id,
            'type' => 'appeal',
            'title' => 'Apellyatsiyangiz qabul qilindi!',
            'message' => "Sizning \"{$appeal->subject_name}\" fani bo'yicha apellyatsiyangiz qabul qilindi."
                . ($request->review_comment ? "\nIzoh: {$request->review_comment}" : '')
                . ($request->new_grade ? "\nYangi baho: {$request->new_grade}" : ''),
            'link' => '/student/appeals/' . $appeal->id,
            'data' => [
                'appeal_id' => $appeal->id,
                'status' => 'approved',
                'subject_name' => $appeal->subject_name,
            ],
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

        StudentNotification::create([
            'student_id' => $appeal->student_id,
            'type' => 'appeal',
            'title' => 'Apellyatsiyangiz rad etildi',
            'message' => "Sizning \"{$appeal->subject_name}\" fani bo'yicha apellyatsiyangiz rad etildi.\nSabab: {$request->review_comment}",
            'link' => '/student/appeals/' . $appeal->id,
            'data' => [
                'appeal_id' => $appeal->id,
                'status' => 'rejected',
                'subject_name' => $appeal->subject_name,
            ],
        ]);

        return redirect()->route('admin.exam-appeals.show', $appeal->id)
            ->with('success', 'Apellyatsiya rad etildi.');
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'comment.required' => 'Izoh yozing.',
            'comment.min' => 'Izoh kamida 3 ta belgidan iborat bo\'lishi kerak.',
        ]);

        $appeal = ExamAppeal::findOrFail($id);
        $user = Auth::user() ?? Auth::guard('teacher')->user();

        ExamAppealComment::create([
            'exam_appeal_id' => $appeal->id,
            'user_type' => 'admin',
            'user_id' => $user->id,
            'user_name' => $user->name ?? $user->full_name ?? 'Admin',
            'comment' => $request->comment,
        ]);

        StudentNotification::create([
            'student_id' => $appeal->student_id,
            'type' => 'appeal',
            'title' => 'Apellyatsiyangizga izoh qoldirildi',
            'message' => "Sizning \"{$appeal->subject_name}\" fani bo'yicha apellyatsiyangizga yangi izoh qoldirildi.\nIzoh: {$request->comment}",
            'link' => '/student/appeals/' . $appeal->id,
            'data' => [
                'appeal_id' => $appeal->id,
                'subject_name' => $appeal->subject_name,
            ],
        ]);

        return redirect()->route('admin.exam-appeals.show', $appeal->id)
            ->with('success', 'Izoh qo\'shildi.');
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Student;
use App\Models\StudentGrade;
use Illuminate\Http\Request;

class GradeHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query()
            ->where('module', 'student_grade')
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_search')) {
            $query->where('user_name', 'like', '%' . $request->user_search . '%');
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('only_retake')) {
            $query->where(function ($q) {
                $q->whereRaw("JSON_EXTRACT(new_values, '$.retake_grade') IS NOT NULL")
                  ->orWhereRaw("JSON_EXTRACT(old_values, '$.retake_grade') IS NOT NULL");
            });
        }

        if ($request->filled('subject_search')) {
            $needle = $request->subject_search;
            $gradeIds = StudentGrade::withTrashed()
                ->where('subject_name', 'like', "%{$needle}%")
                ->pluck('id');
            $query->where('subject_type', StudentGrade::class)
                  ->whereIn('subject_id', $gradeIds);
        }

        if ($request->filled('student_search')) {
            $needle = $request->student_search;
            $studentHemisIds = Student::where('full_name', 'like', "%{$needle}%")
                ->orWhere('hemis_id', 'like', "%{$needle}%")
                ->pluck('hemis_id');
            $gradeIds = StudentGrade::whereIn('student_hemis_id', $studentHemisIds)->pluck('id');
            $query->where('subject_type', StudentGrade::class)->whereIn('subject_id', $gradeIds);
        }

        $logs = $query->paginate(50)->withQueryString();

        $gradeIds = $logs->pluck('subject_id')->unique()->filter()->all();
        $grades = StudentGrade::withTrashed()
            ->whereIn('id', $gradeIds)
            ->get()
            ->keyBy('id');

        $studentHemisIds = $grades->pluck('student_hemis_id')->unique()->filter()->all();
        $students = Student::whereIn('hemis_id', $studentHemisIds)
            ->get()
            ->keyBy('hemis_id');

        $roles = ActivityLog::where('module', 'student_grade')
            ->select('role')
            ->distinct()
            ->whereNotNull('role')
            ->orderBy('role')
            ->pluck('role');

        $stats = [
            'total' => $logs->total(),
            'retake_count' => ActivityLog::where('module', 'student_grade')
                ->whereRaw("JSON_EXTRACT(new_values, '$.retake_grade') IS NOT NULL")
                ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->date_from . ' 00:00:00'))
                ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'))
                ->count(),
            'updates' => ActivityLog::where('module', 'student_grade')
                ->where('action', 'update')
                ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->date_from . ' 00:00:00'))
                ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'))
                ->count(),
            'deletes' => ActivityLog::where('module', 'student_grade')
                ->where('action', 'delete')
                ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->date_from . ' 00:00:00'))
                ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->date_to . ' 23:59:59'))
                ->count(),
        ];

        return view('admin.grade-history.index', compact('logs', 'grades', 'students', 'roles', 'stats'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentExcuseRequest;
use Illuminate\Http\Request;

class ExcuseRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = StudentExcuseRequest::query()->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_name', 'like', "%{$search}%")
                  ->orWhere('group_name', 'like', "%{$search}%")
                  ->orWhere('subject_name', 'like', "%{$search}%");
            });
        }

        $requests = $query->paginate(20);

        return view('admin.excuse-requests.index', compact('requests'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_comment' => ['nullable', 'string', 'max:500'],
        ]);

        $excuseRequest = StudentExcuseRequest::findOrFail($id);
        $excuseRequest->update([
            'status' => $request->status,
            'admin_comment' => $request->admin_comment,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $statusText = $request->status === 'approved' ? 'qabul qilindi' : 'rad etildi';

        return back()->with('success', "Ariza {$statusText}.");
    }
}

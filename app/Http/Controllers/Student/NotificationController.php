<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = collect();
        $unreadCount = 0;

        if (Schema::hasTable('student_notifications')) {
            $studentId = auth()->guard('student')->id();

            $unreadCount = StudentNotification::where('student_id', $studentId)
                ->whereNull('read_at')
                ->count();

            // Sahifaga kirganida barcha xabarlarni o'qilgan deb belgilash
            if ($unreadCount > 0) {
                StudentNotification::where('student_id', $studentId)
                    ->whereNull('read_at')
                    ->update(['read_at' => now()]);
            }

            $notifications = StudentNotification::where('student_id', $studentId)
                ->orderByDesc('created_at')
                ->paginate(20);
        }

        return view('student.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function unreadCount()
    {
        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['count' => 0]);
        }

        $count = StudentNotification::where('student_id', auth()->guard('student')->id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead($id)
    {
        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        StudentNotification::where('id', $id)
            ->where('student_id', auth()->guard('student')->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        StudentNotification::where('student_id', auth()->guard('student')->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function bulkMarkRead(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        StudentNotification::whereIn('id', $request->ids)
            ->where('student_id', auth()->guard('student')->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function bulkDelete(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        StudentNotification::whereIn('id', $request->ids)
            ->where('student_id', auth()->guard('student')->id())
            ->delete();

        return response()->json(['success' => true]);
    }

    public function deleteAll()
    {
        if (!Schema::hasTable('student_notifications')) {
            return response()->json(['success' => true]);
        }

        StudentNotification::where('student_id', auth()->guard('student')->id())
            ->delete();

        return response()->json(['success' => true]);
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TeacherNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    /**
     * O'qilmagan xabarnomalar sonini olish (navbar uchun)
     */
    public function unreadCount()
    {
        if (!Schema::hasTable('teacher_notifications')) {
            return response()->json(['count' => 0]);
        }

        $count = TeacherNotification::where('teacher_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Xabarnomalar ro'yxati
     */
    public function index()
    {
        if (!Schema::hasTable('teacher_notifications')) {
            return response()->json(['notifications' => [], 'unread_count' => 0]);
        }

        $notifications = TeacherNotification::where('teacher_id', auth()->id())
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'link' => $n->link,
                'data' => $n->data,
                'is_read' => $n->isRead(),
                'created_at' => $n->created_at->diffForHumans(),
                'created_at_full' => $n->created_at->format('d.m.Y H:i'),
            ]);

        $unreadCount = TeacherNotification::where('teacher_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Xabarnomani o'qilgan deb belgilash
     */
    public function markAsRead($id)
    {
        if (!Schema::hasTable('teacher_notifications')) {
            return response()->json(['success' => true]);
        }

        TeacherNotification::where('id', $id)
            ->where('teacher_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Barcha xabarnomalarni o'qilgan deb belgilash
     */
    public function markAllAsRead()
    {
        if (!Schema::hasTable('teacher_notifications')) {
            return response()->json(['success' => true]);
        }

        TeacherNotification::where('teacher_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }
}

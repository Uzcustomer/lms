<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Student;

class NotificationController extends Controller
{
    public function unreadCount()
    {
        $count = Notification::where('recipient_id', auth()->guard('student')->id())
            ->where('recipient_type', Student::class)
            ->where('is_draft', false)
            ->where('is_read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function index()
    {
        $studentId = auth()->guard('student')->id();

        $notifications = Notification::where('recipient_id', $studentId)
            ->where('recipient_type', Student::class)
            ->where('is_draft', false)
            ->orderByDesc('sent_at')
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->subject,
                'message' => $n->body,
                'link' => $n->url,
                'data' => $n->data,
                'is_read' => $n->is_read,
                'created_at' => $n->created_at->diffForHumans(),
                'created_at_full' => $n->created_at->format('d.m.Y H:i'),
            ]);

        $unreadCount = Notification::where('recipient_id', $studentId)
            ->where('recipient_type', Student::class)
            ->where('is_draft', false)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead($id)
    {
        $notification = Notification::where('id', $id)
            ->where('recipient_id', auth()->guard('student')->id())
            ->where('recipient_type', Student::class)
            ->first();

        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        Notification::where('recipient_id', auth()->guard('student')->id())
            ->where('recipient_type', Student::class)
            ->where('is_draft', false)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}

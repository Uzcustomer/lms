<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected function getUserInfo()
    {
        $user = Auth::user();
        $userType = get_class($user);
        $userId = $user->id;
        return [$userId, $userType];
    }

    public function index(Request $request)
    {
        [$userId, $userType] = $this->getUserInfo();
        $tab = $request->get('tab', 'inbox');

        $query = Notification::query();

        switch ($tab) {
            case 'sent':
                $query->sent($userId, $userType)->orderByDesc('sent_at');
                break;
            case 'drafts':
                $query->drafts($userId, $userType)->orderByDesc('updated_at');
                break;
            default: // inbox
                $query->inbox($userId, $userType)->orderByDesc('sent_at');
                break;
        }

        $notifications = $query->paginate(20);

        $unreadCount = Notification::inbox($userId, $userType)->unread()->count();
        $inboxCount = Notification::inbox($userId, $userType)->count();
        $sentCount = Notification::sent($userId, $userType)->count();
        $draftsCount = Notification::drafts($userId, $userType)->count();

        return view('admin.notifications.index', compact(
            'notifications', 'tab', 'unreadCount', 'inboxCount', 'sentCount', 'draftsCount'
        ));
    }

    public function show(Notification $notification)
    {
        [$userId, $userType] = $this->getUserInfo();

        // Mark as read if recipient
        if ($notification->recipient_id == $userId && $notification->recipient_type == $userType) {
            $notification->markAsRead();
        }

        return view('admin.notifications.show', compact('notification'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name']);
        $teachers = Teacher::orderBy('full_name')->get(['id', 'full_name']);

        return view('admin.notifications.create', compact('users', 'teachers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|integer',
            'recipient_type' => 'required|string|in:App\\Models\\User,App\\Models\\Teacher',
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
        ]);

        [$senderId, $senderType] = $this->getUserInfo();

        $isDraft = $request->has('save_draft');

        $notification = Notification::create([
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'recipient_id' => $validated['recipient_id'],
            'recipient_type' => $validated['recipient_type'],
            'subject' => $validated['subject'],
            'body' => $validated['body'] ?? '',
            'type' => Notification::TYPE_MESSAGE,
            'is_draft' => $isDraft,
            'sent_at' => $isDraft ? null : now(),
        ]);

        if ($isDraft) {
            return redirect()->route('admin.notifications.index', ['tab' => 'drafts'])
                             ->with('success', __('notifications.draft_saved'));
        }

        return redirect()->route('admin.notifications.index')
                         ->with('success', __('notifications.sent_success'));
    }

    public function destroy(Notification $notification)
    {
        [$userId, $userType] = $this->getUserInfo();

        if ($notification->sender_id == $userId || $notification->recipient_id == $userId) {
            $notification->delete();
            return redirect()->back()->with('success', __('notifications.deleted'));
        }

        return redirect()->back()->with('error', __('notifications.no_permission'));
    }

    public function markAsRead(Notification $notification)
    {
        $notification->markAsRead();
        return redirect()->back();
    }

    public function markAllRead()
    {
        [$userId, $userType] = $this->getUserInfo();
        Notification::inbox($userId, $userType)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return redirect()->back()->with('success', __('notifications.all_marked_read'));
    }

    public function getUnreadCount()
    {
        [$userId, $userType] = $this->getUserInfo();
        $count = Notification::inbox($userId, $userType)->unread()->count();

        return response()->json(['count' => $count]);
    }
}

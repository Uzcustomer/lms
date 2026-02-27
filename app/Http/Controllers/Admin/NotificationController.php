<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Models\Teacher;
use App\Enums\ProjectRole;
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
        try {
            [$userId, $userType] = $this->getUserInfo();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index getUserInfo error: ' . $e->getMessage());
            abort(500, 'Auth error: ' . $e->getMessage());
        }

        $tab = $request->get('tab', 'inbox');
        $search = $request->get('search', '');
        $senderFilter = $request->get('sender_id');
        $readStatus = $request->get('status'); // 'unread'
        $subjectFilter = $request->get('subject'); // mavzu bo'yicha filtr

        try {
            $query = Notification::with('sender');

            switch ($tab) {
                case 'sent':
                    $query->sent($userId)->orderByDesc('sent_at');
                    break;
                case 'drafts':
                    $query->drafts($userId)->orderByDesc('updated_at');
                    break;
                default: // inbox
                    $query->inbox($userId)->orderByDesc('sent_at');
                    break;
            }

            if ($search) {
                $matchingSenderIds = User::where('name', 'like', "%{$search}%")->pluck('id')
                    ->merge(Teacher::where('full_name', 'like', "%{$search}%")->pluck('id'));

                $query->where(function ($q) use ($search, $matchingSenderIds) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%")
                      ->orWhereIn('sender_id', $matchingSenderIds);
                });
            }

            if ($senderFilter) {
                $query->where('sender_id', $senderFilter);
            }

            if ($readStatus === 'unread') {
                $query->where('is_read', false);
            }

            if ($subjectFilter) {
                $query->where('subject', $subjectFilter);
            }

            $notifications = $query->paginate(20);
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index query error: ' . $e->getMessage());
            $notifications = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        try {
            $unreadCount = Notification::inbox($userId)->unread()->count();
            $inboxCount = Notification::inbox($userId)->count();
            $sentCount = Notification::sent($userId)->count();
            $draftsCount = Notification::drafts($userId)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index counts error: ' . $e->getMessage());
            $unreadCount = $inboxCount = $sentCount = $draftsCount = 0;
        }

        // Kelgan xabarlar uchun jo'natuvchilar ro'yxati (filtr uchun)
        $senders = collect();
        $subjects = collect();
        try {
            if ($tab === 'inbox') {
                $inboxSenderIds = Notification::inbox($userId)->distinct()->pluck('sender_id');
                $senders = User::whereIn('id', $inboxSenderIds)->orderBy('name')->get(['id', 'name'])
                    ->merge(
                        Teacher::whereIn('id', $inboxSenderIds)->orderBy('full_name')
                            ->get(['id', 'full_name'])->map(fn ($t) => (object) ['id' => $t->id, 'name' => $t->full_name])
                    );

                // Unikal mavzular ro'yxati (har bir mavzu nechta xabar borligini ham ko'rsatish)
                $subjects = Notification::inbox($userId)
                    ->select('subject')
                    ->selectRaw('count(*) as count')
                    ->groupBy('subject')
                    ->orderByDesc('count')
                    ->limit(20)
                    ->get();
            }
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index senders error: ' . $e->getMessage());
            $senders = collect();
            $subjects = collect();
        }

        return view('admin.notifications.index', compact(
            'notifications', 'tab', 'search', 'senderFilter', 'readStatus', 'subjectFilter',
            'unreadCount', 'inboxCount', 'sentCount', 'draftsCount', 'senders', 'subjects'
        ));
    }

    public function show(Notification $notification)
    {
        [$userId, $userType] = $this->getUserInfo();

        // Mark as read if recipient
        if ($notification->recipient_id == $userId) {
            $notification->markAsRead();
        }

        // URL bo'lsa — to'g'ridan-to'g'ri o'sha sahifaga yo'naltirish
        if ($notification->url) {
            return redirect($notification->url);
        }

        $notification->load(['sender', 'recipient']);

        try {
            $unreadCount = Notification::inbox($userId)->unread()->count();
            $sentCount = Notification::sent($userId)->count();
            $draftsCount = Notification::drafts($userId)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@show counts error: ' . $e->getMessage());
            $unreadCount = $sentCount = $draftsCount = 0;
        }

        return view('admin.notifications.show', compact('notification', 'unreadCount', 'sentCount', 'draftsCount'));
    }

    public function create()
    {
        [$userId, $userType] = $this->getUserInfo();

        $users = User::with('roles')->orderBy('name')->get();
        $teachers = Teacher::orderBy('full_name')->get(['id', 'full_name']);

        // Rollar ro'yxati (talabadan tashqari)
        $roles = collect(ProjectRole::staffRoles())->map(fn ($role) => [
            'value' => $role->value,
            'label' => $role->label(),
        ]);

        try {
            $unreadCount = Notification::inbox($userId)->unread()->count();
            $sentCount = Notification::sent($userId)->count();
            $draftsCount = Notification::drafts($userId)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@create counts error: ' . $e->getMessage());
            $unreadCount = $sentCount = $draftsCount = 0;
        }

        return view('admin.notifications.create', compact('users', 'teachers', 'roles', 'unreadCount', 'sentCount', 'draftsCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_id' => 'required|integer',
            'recipient_type' => 'required|string|in:user,teacher',
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
        ]);

        $typeMap = [
            'user' => User::class,
            'teacher' => Teacher::class,
        ];

        [$senderId, $senderType] = $this->getUserInfo();

        $isDraft = $request->has('save_draft');

        $notification = Notification::create([
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'recipient_id' => $validated['recipient_id'],
            'recipient_type' => $typeMap[$validated['recipient_type']],
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

    public function reply(Request $request, Notification $notification)
    {
        $validated = $request->validate([
            'body' => 'required|string',
        ]);

        [$senderId, $senderType] = $this->getUserInfo();

        // Javob mavzusi: "Re: ..." shaklida
        $subject = $notification->subject;
        if (!str_starts_with($subject, 'Re: ')) {
            $subject = 'Re: ' . $subject;
        }

        // Jo'natuvchiga javob qaytarish
        Notification::create([
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'recipient_id' => $notification->sender_id,
            'recipient_type' => $notification->sender_type,
            'subject' => $subject,
            'body' => $validated['body'],
            'type' => Notification::TYPE_MESSAGE,
            'is_draft' => false,
            'sent_at' => now(),
        ]);

        return redirect()->route('admin.notifications.show', $notification)
                         ->with('success', __('notifications.reply_sent'));
    }

    public function markAsRead(Notification $notification)
    {
        $notification->markAsRead();
        return redirect()->back();
    }

    public function markAllRead()
    {
        [$userId, $userType] = $this->getUserInfo();
        Notification::inbox($userId)->unread()->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return redirect()->back()->with('success', __('notifications.all_marked_read'));
    }

    public function getUnreadCount()
    {
        [$userId, $userType] = $this->getUserInfo();
        $count = Notification::inbox($userId)->unread()->count();

        return response()->json(['count' => $count]);
    }
}

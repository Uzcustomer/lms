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

        $subjects = collect();
        $senders = collect();

        try {
            $query = Notification::with('sender');

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

            // Mavzular ro'yxati — xuddi shu so'rovdan olish (subject filtr qo'yilmasdan OLDIN)
            // Bu usul kafolatlaydi: agar xabarlar ko'rinsa, mavzular ham chiqadi
            $subjects = (clone $query)
                ->pluck('subject')
                ->filter(fn ($s) => $s !== null && $s !== '')
                ->countBy()
                ->sortDesc()
                ->take(20)
                ->map(fn ($count, $subject) => (object) ['subject' => $subject, 'subject_count' => $count])
                ->values();

            // Mavzu filtri — mavzular ro'yxati olinganidan KEYIN qo'yiladi
            if ($subjectFilter) {
                $query->where('subject', $subjectFilter);
            }

            $notifications = $query->paginate(20);
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index query error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $notifications = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20);
        }

        try {
            $unreadCount = Notification::inbox($userId, $userType)->unread()->count();
            $inboxCount = Notification::inbox($userId, $userType)->count();
            $sentCount = Notification::sent($userId, $userType)->count();
            $draftsCount = Notification::drafts($userId, $userType)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index counts error: ' . $e->getMessage());
            $unreadCount = $inboxCount = $sentCount = $draftsCount = 0;
        }

        // Jo'natuvchilar ro'yxati (filtr uchun)
        try {
            if ($tab === 'inbox') {
                $inboxSenderIds = Notification::inbox($userId, $userType)->distinct()->pluck('sender_id');
                $senders = User::whereIn('id', $inboxSenderIds)->orderBy('name')->get(['id', 'name'])
                    ->merge(
                        Teacher::whereIn('id', $inboxSenderIds)->orderBy('full_name')
                            ->get(['id', 'full_name'])->map(fn ($t) => (object) ['id' => $t->id, 'name' => $t->full_name])
                    );
            }
        } catch (\Throwable $e) {
            \Log::error('NotificationController@index senders error: ' . $e->getMessage());
            $senders = collect();
        }

        return view('admin.notifications.index', compact(
            'notifications', 'tab', 'search', 'senderFilter', 'readStatus', 'subjectFilter',
            'unreadCount', 'inboxCount', 'sentCount', 'draftsCount', 'senders', 'subjects'
        ));
    }

    public function show(Notification $notification)
    {
        [$userId, $userType] = $this->getUserInfo();

        // Faqat o'ziga tegishli xabarlarni ko'rish mumkin
        $isRecipient = $notification->recipient_id == $userId && $notification->recipient_type === $userType;
        $isSender = $notification->sender_id == $userId && $notification->sender_type === $userType;
        if (!$isRecipient && !$isSender) {
            abort(403, __('notifications.no_permission'));
        }

        // Mark as read if recipient
        if ($isRecipient) {
            $notification->markAsRead();
        }

        // URL bo'lsa — to'g'ridan-to'g'ri o'sha sahifaga yo'naltirish
        if ($notification->url) {
            return redirect($notification->url);
        }

        $notification->load(['sender', 'recipient']);

        try {
            $unreadCount = Notification::inbox($userId, $userType)->unread()->count();
            $sentCount = Notification::sent($userId, $userType)->count();
            $draftsCount = Notification::drafts($userId, $userType)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@show counts error: ' . $e->getMessage());
            $unreadCount = $sentCount = $draftsCount = 0;
        }

        return view('admin.notifications.show', compact('notification', 'unreadCount', 'sentCount', 'draftsCount'));
    }

    public function create()
    {
        try {
            [$userId, $userType] = $this->getUserInfo();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@create getUserInfo error: ' . $e->getMessage());
            abort(500, 'Auth error: ' . $e->getMessage());
        }

        // Xodimlar ro'yxatini JSON-xavfsiz massiv sifatida tayyorlash
        $teachersJson = [];
        try {
            $teachers = Teacher::with('roles')
                ->whereNotNull('full_name')
                ->where('full_name', '!=', '')
                ->orderBy('full_name')
                ->get();

            foreach ($teachers as $t) {
                try {
                    $roles = $t->relationLoaded('roles') ? $t->roles->pluck('name')->toArray() : [];
                } catch (\Throwable $e) {
                    $roles = [];
                }
                $name = $t->full_name ?? $t->short_name ?? ('ID: ' . $t->id);
                // UTF-8 xavfsizligi
                $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
                $position = mb_convert_encoding($t->staff_position ?? '', 'UTF-8', 'UTF-8');

                $teachersJson[] = [
                    'id' => (int) $t->id,
                    'name' => $name,
                    'position' => $position,
                    'roles' => $roles,
                ];
            }
        } catch (\Throwable $e) {
            \Log::error('NotificationController@create teachers error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            $teachersJson = [];
        }

        // Rollar ro'yxati (talabadan tashqari)
        $roles = collect(ProjectRole::staffRoles())->map(fn ($role) => [
            'value' => $role->value,
            'label' => $role->label(),
        ]);

        try {
            $unreadCount = Notification::inbox($userId, $userType)->unread()->count();
            $sentCount = Notification::sent($userId, $userType)->count();
            $draftsCount = Notification::drafts($userId, $userType)->count();
        } catch (\Throwable $e) {
            \Log::error('NotificationController@create counts error: ' . $e->getMessage());
            $unreadCount = $sentCount = $draftsCount = 0;
        }

        return view('admin.notifications.create', compact('teachersJson', 'roles', 'unreadCount', 'sentCount', 'draftsCount'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'recipient_ids' => 'required|array|min:1',
            'recipient_ids.*' => 'integer|exists:teachers,id',
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string',
        ]);

        [$senderId, $senderType] = $this->getUserInfo();

        $isDraft = $request->has('save_draft');

        foreach ($validated['recipient_ids'] as $recipientId) {
            Notification::create([
                'sender_id' => $senderId,
                'sender_type' => $senderType,
                'recipient_id' => $recipientId,
                'recipient_type' => Teacher::class,
                'subject' => $validated['subject'],
                'body' => $validated['body'] ?? '',
                'type' => Notification::TYPE_MESSAGE,
                'is_draft' => $isDraft,
                'sent_at' => $isDraft ? null : now(),
            ]);
        }

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

        if (($notification->sender_id == $userId && $notification->sender_type === $userType)
            || ($notification->recipient_id == $userId && $notification->recipient_type === $userType)) {
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

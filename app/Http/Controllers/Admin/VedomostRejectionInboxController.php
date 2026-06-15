<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VedomostSubmission;
use App\Services\VedomostRejectionInbox;
use Illuminate\Http\Request;

/**
 * "Vedomost rad etilish bildirgilari" — Gmail uslubidagi inbox (o'quv prorektori).
 */
class VedomostRejectionInboxController extends Controller
{
    /** Inboxni ko'ra oladigan rollar (rad etishlarni kuzatuvchi). */
    private const ALLOWED_ROLES = ['superadmin', 'oquv_prorektori'];

    public function __construct(private VedomostRejectionInbox $inbox)
    {
    }

    private function checkAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        if (!in_array(session('active_role', ''), self::ALLOWED_ROLES, true)) {
            abort(403, "Vedomost rad etilish bildirgilarini faqat o'quv prorektori ko'ra oladi.");
        }
    }

    public function index(Request $request)
    {
        $this->checkAccess();

        // Jamlashni bir marta hisoblaymiz (rad etilganlar to'plami kichik).
        $all = $this->inbox->decorate($this->inbox->aggregatedRejections(), auth()->user());
        $unreadCount = $all->filter(fn($r) => !$r->is_read)->count();
        $totalCount = $all->count();

        $filter = $request->get('filter'); // 'unread' | null
        $rows = $filter === 'unread'
            ? $all->filter(fn($r) => !$r->is_read)->values()
            : $all;

        return view('admin.vedomost-rejection.index', [
            'rows' => $rows,
            'filter' => $filter,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
        ]);
    }

    public function markRead(Request $request, $id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);
        $this->inbox->markRead($v, auth()->user());

        return back()->with('success', "Bildirgi o'qilgan deb belgilandi.");
    }

    public function markAllRead(Request $request)
    {
        $this->checkAccess();
        $this->inbox->markAllRead(auth()->user());

        return back()->with('success', "Barcha bildirgilar o'qilgan deb belgilandi.");
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimetableBoard;
use App\Models\TimetableChangeRequest;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dars jadvali o'zgarish so'rovlari: ro'yxat, tasdiqlash, rad etish, bekor qilish, rejim. */
class TimetableChangeRequestController extends Controller
{
    public function index(Request $request, TimetableBoard $board)
    {
        if (!TimetableChangeApproval::tableReady()) {
            return response()->json(['requests' => [], 'approval_required' => false, 'can_approve' => false]);
        }
        $actor = $this->actor($request);
        $role = $this->activeRole($request);
        $canApprove = in_array($role, TimetableChangeApproval::APPROVER_ROLES, true);

        $q = TimetableChangeRequest::where('board_id', $board->id);
        if (!$canApprove) {
            $q->where('requester_id', $actor?->id ?? 0);
        }
        $status = $request->query('status');
        if ($status === 'pending') {
            $q->where('status', TimetableChangeRequest::PENDING);
        } else {
            // Kutilayotganlar + so'nggi 30 kunda ko'rib chiqilganlar
            $q->where(fn ($w) => $w->where('status', TimetableChangeRequest::PENDING)
                ->orWhere('updated_at', '>=', now()->subDays(30)));
        }

        $rows = $q->orderByRaw("status = 'pending' desc")->orderByDesc('id')->limit(300)->get()
            ->map(fn (TimetableChangeRequest $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'kind' => $r->kind,
                'route' => $r->route_name,
                'summary' => $r->summary,
                'subject_name' => $r->subject_name,
                'kafedra_name' => $r->kafedra_name,
                'requester_name' => $r->requester_name,
                'requester_id' => $r->requester_id,
                'reviewer_name' => $r->reviewer_name,
                'review_note' => $r->review_note,
                'error' => $r->error,
                'card_ids' => ($r->details['card_ids'] ?? []),
                'groups' => ($r->details['groups'] ?? []),
                'payload' => $r->payload,
                'created_at' => optional($r->created_at)->format('d.m.Y H:i'),
                'reviewed_at' => optional($r->reviewed_at)->format('d.m.Y H:i'),
            ]);

        return response()->json([
            'requests' => $rows,
            'approval_required' => TimetableChangeApproval::boardRequiresApproval($board),
            'can_approve' => $canApprove,
            'pending_count' => $rows->where('status', TimetableChangeRequest::PENDING)->count(),
        ]);
    }

    public function approve(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        $this->ensureApprover($request);
        if ($changeRequest->status !== TimetableChangeRequest::PENDING) {
            return response()->json(['error' => 'Bu so\'rov allaqachon ko\'rib chiqilgan.'], 422);
        }
        $result = $svc->apply($changeRequest, $this->actor($request));

        return $result['ok']
            ? response()->json(['ok' => true])
            : response()->json(['error' => 'Qo\'llab bo\'lmadi: ' . $result['error']], 422);
    }

    public function reject(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        $this->ensureApprover($request);
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        if ($changeRequest->status !== TimetableChangeRequest::PENDING) {
            return response()->json(['error' => 'Bu so\'rov allaqachon ko\'rib chiqilgan.'], 422);
        }
        $svc->reject($changeRequest, $this->actor($request), $data['note'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** Kafedra mudiri o'z kutilayotgan so'rovini qaytarib oladi. */
    public function cancel(Request $request, TimetableChangeRequest $changeRequest)
    {
        $actor = $this->actor($request);
        $own = $actor && (int) $changeRequest->requester_id === (int) $actor->id;
        if (!$own && !in_array($this->activeRole($request), TimetableChangeApproval::APPROVER_ROLES, true)) {
            abort(403, 'Faqat o\'z so\'rovingizni bekor qila olasiz.');
        }
        if ($changeRequest->status !== TimetableChangeRequest::PENDING) {
            return response()->json(['error' => 'Bu so\'rov allaqachon ko\'rib chiqilgan.'], 422);
        }
        $changeRequest->update([
            'status' => TimetableChangeRequest::CANCELLED,
            'review_note' => 'Yuboruvchi bekor qildi',
            'reviewed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Doska uchun "o'zgarishlar tasdiqdan o'tsin" rejimi. */
    public function setMode(Request $request, TimetableBoard $board)
    {
        $this->ensureApprover($request);
        if (!TimetableChangeApproval::tableReady()) {
            return response()->json(['error' => 'Migratsiya hali ishga tushirilmagan (php artisan migrate).'], 503);
        }
        $on = $request->boolean('approval_required');
        $settings = $board->settings ?: [];
        $settings['approval_required'] = $on;
        $board->settings = $settings;
        $board->save();

        return response()->json(['ok' => true, 'approval_required' => $on]);
    }

    private function ensureApprover(Request $request): void
    {
        if (!in_array($this->activeRole($request), TimetableChangeApproval::APPROVER_ROLES, true)) {
            abort(403, 'Jadval o\'zgarishlarini faqat registrator ofisi tasdiqlaydi.');
        }
    }

    private function actor(Request $request)
    {
        return $request->user() ?? Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
    }

    private function activeRole(Request $request): string
    {
        $actor = $this->actor($request);
        $roles = $actor && method_exists($actor, 'getRoleNames') ? $actor->getRoleNames()->toArray() : [];
        $active = session('active_role', $roles[0] ?? '');

        return in_array($active, $roles, true) ? $active : ($roles[0] ?? '');
    }
}

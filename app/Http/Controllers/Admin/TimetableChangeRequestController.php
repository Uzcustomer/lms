<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\TimetableBoard;
use App\Models\TimetableChangeRequest;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Dars jadvali o'zgarishlari: tarix, so'rovlarni tasdiqlash, rad etish, bekor qilish, rejim. */
class TimetableChangeRequestController extends Controller
{
    /**
     * Kutilayotgan so'rovlar (status=pending) yoki doskaning to'liq o'zgarishlar tarixi
     * (sahifalab: before_id). Registrator, o'quv bo'limi va adminlar hammasini ko'radi,
     * kafedra mudiri — o'zinikini.
     */
    public function index(Request $request, TimetableBoard $board)
    {
        if (!TimetableChangeApproval::tableReady()) {
            return response()->json(['requests' => [], 'approval_required' => false, 'can_approve' => false, 'has_more' => false]);
        }
        $actor = $this->actor($request);
        $role = $this->activeRole($request);
        $canApprove = in_array($role, TimetableChangeApproval::APPROVER_ROLES, true);
        $seesAll = $canApprove || in_array($role, TimetableChangeApproval::HISTORY_ROLES, true);

        $q = TimetableChangeRequest::where('board_id', $board->id);
        if (!$seesAll) {
            $q->where('requester_type', $actor?->getMorphClass())->where('requester_id', $actor?->getKey() ?? 0);
        }
        $pendingOnly = $request->query('status') === 'pending';
        if ($pendingOnly) {
            $q->where('status', TimetableChangeRequest::PENDING);
        } elseif ($beforeId = (int) $request->query('before_id')) {
            $q->where('id', '<', $beforeId);
        }
        $limit = $pendingOnly ? 300 : 100;
        $found = $q->orderByDesc('id')->limit($limit + 1)->get();

        $rows = $found->take($limit)->map(fn (TimetableChangeRequest $r) => [
            'id' => $r->id,
            'status' => $r->status,
            'kind' => $r->kind,
            'route' => $r->route_name,
            'summary' => $r->summary,
            'subject_name' => $r->subject_name,
            'kafedra_name' => $r->kafedra_name,
            'requester_name' => $r->requester_name,
            'requester_role' => $this->roleLabel($r->requester_role),
            'mine' => $r->isBy($actor),
            'reviewer_name' => $r->reviewer_name,
            'review_note' => $r->review_note,
            'error' => $r->error,
            'card_ids' => ($r->details['card_ids'] ?? []),
            'groups' => ($r->details['groups'] ?? []),
            // Payload faqat kutilayotganlar uchun kerak (sikl kun oynasidagi so'ralgan soat).
            'payload' => $r->status === TimetableChangeRequest::PENDING ? $r->payload : null,
            'created_at' => optional($r->created_at)->format('d.m.Y H:i'),
            'reviewed_at' => optional($r->reviewed_at)->format('d.m.Y H:i'),
        ])->values();

        return response()->json([
            'requests' => $rows,
            'has_more' => $found->count() > $limit,
            'approval_required' => TimetableChangeApproval::boardRequiresApproval($board),
            'can_approve' => $canApprove,
            'pending_count' => $rows->where('status', TimetableChangeRequest::PENDING)->count(),
        ]);
    }

    public function approve(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        $this->ensureApprover($request);
        $result = $svc->apply($changeRequest, $this->actor($request));

        return $result['ok']
            ? response()->json(['ok' => true])
            : response()->json(['error' => !empty($result['stale']) ? $result['error'] : 'Qo\'llab bo\'lmadi: ' . $result['error']], 422);
    }

    public function reject(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        $this->ensureApprover($request);
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $svc->reject($changeRequest, $this->actor($request), $data['note'] ?? null)
            ? response()->json(['ok' => true])
            : response()->json(['error' => TimetableChangeApproval::ALREADY_REVIEWED], 422);
    }

    /** Kafedra mudiri o'z kutilayotgan so'rovini qaytarib oladi. */
    public function cancel(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        if (!$changeRequest->isBy($this->actor($request)) && !in_array($this->activeRole($request), TimetableChangeApproval::APPROVER_ROLES, true)) {
            abort(403, 'Faqat o\'z so\'rovingizni bekor qila olasiz.');
        }

        return $svc->cancel($changeRequest)
            ? response()->json(['ok' => true])
            : response()->json(['error' => TimetableChangeApproval::ALREADY_REVIEWED], 422);
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

    private function roleLabel(?string $role): ?string
    {
        return $role ? (ProjectRole::tryFrom($role)?->label() ?? $role) : null;
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

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\Teacher;
use App\Models\Department;
use Illuminate\Support\Facades\Schema;
use Illuminate\Routing\Route as RoutingRoute;
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
            $q->where(function ($q) use ($actor, $role) {
                $q->where(function ($own) use ($actor) {
                    $own->where('requester_type', $actor?->getMorphClass())->where('requester_id', $actor?->getKey() ?? 0);
                });
                if ($role === 'kafedra_mudiri' && Schema::hasColumn('timetable_change_requests', 'approval_stage')) {
                    $deptId = (int) ($actor?->head_department_hemis_id ?: ($actor?->department_hemis_id ?? 0));
                    if ($deptId > 0) {
                        $q->orWhere(fn ($incoming) => $incoming->where('requester_role', 'oqituvchi')->where('department_hemis_id', $deptId));
                    }
                }
            });
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
            'approval_stage' => $r->approval_stage,
            'head_reviewer_name' => $r->head_reviewer_name,
            'can_review' => $r->requester_role === 'oqituvchi'
                ? app(TimetableChangeApproval::class)->canReviewTeacherProposal($r, $actor, $role)
                : ($canApprove && $r->status === TimetableChangeRequest::PENDING),
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

    public function checkTime(Request $request, TimetableCard $card)
    {
        $actor = $this->actor($request);
        abort_unless($this->activeRole($request) === 'oqituvchi' && $actor instanceof Teacher
            && (int) $card->teacher_id === (int) $actor->id, 403);
        $data = $request->validate(['week' => 'required|integer|min:1|max:30',
            'day' => 'required|integer|min:1|max:7', 'pair' => 'required|integer|min:1|max:24']);
        $own = TimetableChangeRequest::where('board_id', $card->board_id)->where('requester_type', $actor->getMorphClass())
            ->where('requester_id', $actor->id)->where('status', TimetableChangeRequest::PENDING)
            ->where('route_name', 'admin.timetable.cards.week-override')->get()
            ->filter(fn ($r) => (int) ($r->params['card'] ?? 0) === (int) $card->id
                && (int) ($r->payload['week'] ?? 0) === (int) $data['week'])->pluck('id')->all();
        app(TimetableController::class)->validateTimeProposal($card, $data, $own);

        return response()->json(['ok' => true, 'message' => 'Vaqt bo‘sh. Taklif yuborishingiz mumkin.']);
    }

    public function proposeTime(Request $request, TimetableCard $card, TimetableChangeApproval $svc)
    {
        $actor = $this->actor($request);
        abort_unless($this->activeRole($request) === 'oqituvchi' && $actor instanceof Teacher
            && (int) $card->teacher_id === (int) $actor->id, 403, "Faqat o'zingizga biriktirilgan dars vaqtini taklif qila olasiz.");
        abort_unless(TimetableChangeApproval::tableReady() && Schema::hasColumn('timetable_change_requests', 'approval_stage'),
            503, 'Migratsiyani ishga tushiring (php artisan migrate).');
        $data = $request->validate([
            'week' => 'required|integer|min:1|max:30',
            'day' => 'required|integer|min:1|max:7',
            'pair' => 'required|integer|min:1|max:24',
            'reason' => 'required|string|max:1000',
        ]);
        $controller = app(TimetableController::class);
        // The authoritative conflict + booking check runs atomically in submit().
        // Resolve from the subject's department, not the teacher's primary job department.
        $departments = Department::where('structure_type_code', 12)->where('active', true)->get()
            ->filter(fn ($dept) => $controller->proposalDepartmentMatches($card, (int) $dept->department_hemis_id));
        abort_unless($departments->count() === 1, 422, "Fan kafedrasi aniq topilmadi. Registrator ofisiga murojaat qiling.");
        $department = $departments->first();
        abort_unless($svc->departmentHeads((int) $department->department_hemis_id)->contains(fn ($head) => $head->id !== $actor->id),
            422, "Ushbu kafedra uchun tasdiqlovchi kafedra mudiri topilmadi.");
        $payload = ['week' => (int) $data['week'], 'day' => (int) $data['day'], 'pair' => (int) $data['pair'],
            'action' => 'move', '_change_hint' => $data['reason']];
        $sub = Request::create('/', 'POST', $payload);
        $route = (new RoutingRoute('POST', '/', []))->name('admin.timetable.cards.week-override');
        $route->bind($sub);
        $route->setParameter('card', $card);
        $sub->setRouteResolver(fn () => $route);
        $draft = $svc->draft($sub, $actor, $card->board, 'oqituvchi');
        $draft['approval_stage'] = 'department';
        $draft['department_hemis_id'] = $department->department_hemis_id;
        $draft['kafedra_name'] = $department->name;
        $draft['details']['original_signature'] = $svc->proposalSignature($card, (int) $data['week']);
        $draft['details']['reason'] = $data['reason'];
        $change = $svc->submit($draft);

        return response()->json(['ok' => true, 'pending_approval' => true, 'change_request_id' => $change->id,
            'summary' => $change->summary, 'message' => "Taklif kafedra mudiriga yuborildi. Kafedra mudiri va registrator ofisi tasdiqlagach jadval o'zgaradi."]);
    }

    public function approve(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        if ($changeRequest->requester_role === 'oqituvchi') {
            abort_unless($svc->canReviewTeacherProposal($changeRequest, $this->actor($request), $this->activeRole($request)), 403);
            if ($changeRequest->approval_stage === 'department') {
                return $svc->endorse($changeRequest, $this->actor($request), $this->activeRole($request))
                    ? response()->json(['ok' => true, 'message' => "Kafedra mudiri tasdiqladi. Registrator ofisiga yuborildi; jadval hali o'zgarmadi."])
                    : response()->json(['error' => TimetableChangeApproval::ALREADY_REVIEWED], 422);
            }
        }
        $this->ensureApprover($request);
        $result = $svc->apply($changeRequest, $this->actor($request));

        return $result['ok']
            ? response()->json(['ok' => true])
            : response()->json(['error' => !empty($result['stale']) ? $result['error'] : 'Qo\'llab bo\'lmadi: ' . $result['error']], 422);
    }

    public function reject(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        if ($changeRequest->requester_role === 'oqituvchi') {
            abort_unless($svc->canReviewTeacherProposal($changeRequest, $this->actor($request), $this->activeRole($request)), 403);
        } else {
            $this->ensureApprover($request);
        }
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        return $svc->reject($changeRequest, $this->actor($request), $data['note'] ?? null, $this->activeRole($request))
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

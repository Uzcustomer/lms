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
                        $q->orWhere(fn ($incoming) => $incoming->whereIn('requester_role', TimetableChangeApproval::TEACHER_PROPOSAL_ROLES)->where('department_hemis_id', $deptId));
                    }
                }
                // Dars o'qituvchisi: fan mas'uli uning darsi vaqtini o'zgartirishni so'ragan bo'lsa — ko'radi (tasdiqlamaydi).
                if ($actor instanceof Teacher && in_array($role, TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true)) {
                    $q->orWhere(fn ($lesson) => $lesson->where('requester_role', 'fan_masuli')
                        ->whereJsonContains('details->lesson_teacher_ids', (int) $actor->id));
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
            'can_review' => TimetableChangeApproval::isTeacherProposal($r)
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
            // O'qituvchi so'rovi: eski → yangi vaqt va har bosqich (kim, qachon, izoh).
            'notice' => ($r->details['notice'] ?? null),
            'steps' => ($r->details['steps'] ?? []),
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
        $this->ensureProposalTable();
        $data = $request->validate([
            'week' => 'required|integer|min:1|max:30',
            'day' => 'required|integer|min:1|max:7',
            'pair' => 'required|integer|min:1|max:24',
            'reason' => 'required|string|max:1000',
        ]);
        $controller = app(TimetableController::class);
        // Resolve from the subject's department, not the teacher's primary job department.
        $department = $this->proposalDepartment(fn (int $id) => $controller->proposalDepartmentMatches($card, $id));
        $payload = ['week' => (int) $data['week'], 'day' => (int) $data['day'], 'pair' => (int) $data['pair'],
            'action' => 'move', '_change_hint' => $data['reason']];
        // Tavsif (eski → yangi) taklif paytidagi holatdan; tekshiruv (bandlik, sana) — submit() ichida, qulf ostida.
        $notice = $controller->weeklyProposalLabels($card, $payload);
        $draft = $svc->draft($this->proposalRequest('admin.timetable.cards.week-override', $payload, 'card', $card), $actor, $card->board, 'oqituvchi');
        $draft['details']['original_signature'] = $svc->proposalSignature($card, (int) $data['week']);

        return $this->submitProposal($svc, $draft, $department, $actor, $notice, $data['reason']);
    }

    /** Sikl (4–6-kurs) amaliy darsi: shu kunga (yoki shu kundan blok oxirigacha) yangi boshlanish soati — tekshiruv. */
    public function checkCycleTime(Request $request, TimetableBoard $board)
    {
        [$data, $units] = $this->cycleProposalInput($request, $board, false);
        $labels = app(TimetableController::class)->cycleProposalLabels($board, $units, $data);

        return response()->json(['ok' => true, 'message' => 'Vaqt bo‘sh. Taklif yuborishingiz mumkin.', 'old' => $labels['old'], 'new' => $labels['new']]);
    }

    public function proposeCycleTime(Request $request, TimetableBoard $board, TimetableChangeApproval $svc)
    {
        [$data, $units, $as, $lessonTeachers] = $this->cycleProposalInput($request, $board, true);
        $actor = $this->actor($request);
        $controller = app(TimetableController::class);
        $cards = TimetableCard::whereIn('id', collect($units)->flatMap(fn ($x) => array_map(fn ($c) => (int) $c->id, $x['u']['cards'])))->get();
        $department = $this->proposalDepartment(fn (int $id) => $cards->every(fn ($c) => $controller->proposalDepartmentMatches($c, $id)));
        $payload = ['specialty_name' => $data['specialty_name'], 'course' => (int) $data['course'], 'subject_name' => $data['subject_name'],
            'group_name' => $data['group_name'], 'units' => array_values(array_map(fn ($x) => (string) $x['u']['unit'], $units)),
            'date' => $data['date'], 'scope' => $data['scope'] ?? 'day', 'start_hour' => (int) $data['start_hour'],
            'layer' => 'internal', 'view' => 'group', 'training_type' => 'practice', '_change_hint' => $data['reason']];
        $notice = $controller->cycleProposalLabels($board, $units, $payload);
        // Kim yubordi: o'qituvchi (o'z darsi) yoki fan mas'uli (mas'ul fanidagi boshqa o'qituvchi darsi) — requester_role.
        $draft = $svc->draft($this->proposalRequest(TimetableController::TEACHER_CYCLE_ROUTE, $payload, 'board', $board), $actor, $board, $as);
        $draft['details'] = array_merge($draft['details'], $svc->detailsFromCards($cards));
        $draft['details']['proposer'] = ['as' => $as, 'teacher_id' => (int) $actor->id];
        $draft['details']['lesson_teacher_ids'] = $lessonTeachers;
        $draft['details']['original_signature'] = $controller->teacherProposalSignature(TimetableController::TEACHER_CYCLE_ROUTE, ['board' => $board->id], $payload);
        $draft['summary'] = $notice['subject'] . ' · ' . $notice['groups'] . ' (amaliy, sikl): ' . $notice['old'] . ' → ' . $notice['new']
            . ' — ' . mb_substr($data['reason'], 0, 300);

        return $this->submitProposal($svc, $draft, $department, $actor, $notice, $data['reason']);
    }

    /**
     * Sikl so'rovi kiritmasi: shu guruhcha(lar)dagi amaliy dars — o'qituvchining o'z darsi YOKI fani uning mas'ul fanlari
     * ro'yxatida (fan mas'uli — shu fanning istalgan o'qituvchisi darsi); bandlik va sana tekshiriladi.
     * @return array{0: array, 1: array, 2: string, 3: int[]} [ma'lumot, guruhchalar, kim (oqituvchi|fan_masuli), dars o'qituvchilari (o'zidan boshqa)]
     */
    private function cycleProposalInput(Request $request, TimetableBoard $board, bool $withReason): array
    {
        $actor = $this->actor($request);
        abort_unless(in_array($this->activeRole($request), TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true) && $actor instanceof Teacher,
            403, "Faqat o'qituvchi yoki fan mas'uli so'rov yuboradi.");
        $this->ensureProposalTable();
        $data = $request->validate([
            'specialty_name' => 'required|string|max:255',
            'course' => 'required|integer|min:1|max:7',
            'subject_name' => 'required|string|max:255',
            'group_name' => 'required|string|max:255',
            'date' => 'required|date_format:Y-m-d',
            'scope' => 'nullable|in:day,rest',
            'start_hour' => 'required|integer|min:1|max:24',
        ] + ($withReason ? ['reason' => 'required|string|max:1000'] : []));
        $controller = app(TimetableController::class);
        $tid = (int) $actor->id;
        // Mas'ul fani — guruhning shu fandagi barcha guruhchalari (har o'qituvchisi); aks holda faqat o'zi o'tadiganlari.
        $all = $controller->cycleProposalUnits($board, $data, null);
        $units = $all && $controller->cycleUnitsResponsible($all, $tid) ? $all : $controller->cycleProposalUnits($board, $data, $tid);
        abort_unless($units, 403, "Faqat o'zingizga biriktirilgan yoki mas'ul faningizdagi dars vaqtini taklif qila olasiz.");
        $own = fn ($x) => collect($x['u']['cards'])->contains(fn ($c) => (int) $c->teacher_id === $tid);
        $as = collect($units)->every($own) ? 'oqituvchi' : 'fan_masuli';
        $lessonTeachers = collect($units)->flatMap(fn ($x) => array_map(fn ($c) => (int) $c->teacher_id, $x['u']['cards']))
            ->filter(fn ($id) => $id > 0 && $id !== $tid)->unique()->sort()->values()->all();
        $data['units'] = array_map(fn ($x) => (string) $x['u']['unit'], $units);
        $controller->teacherProposalValidate(TimetableController::TEACHER_CYCLE_ROUTE, ['board' => $board->id], $data, []);

        return [$data, $units, $as, $lessonTeachers];
    }

    /** Fan kafedrasi (aniq bitta). */
    private function proposalDepartment(callable $matches): Department
    {
        $departments = Department::where('structure_type_code', 12)->where('active', true)->get()
            ->filter(fn ($dept) => $matches((int) $dept->department_hemis_id));
        abort_unless($departments->count() === 1, 422, "Fan kafedrasi aniq topilmadi. Registrator ofisiga murojaat qiling.");

        return $departments->first();
    }

    private function proposalRequest(string $routeName, array $payload, string $param, $model): Request
    {
        $sub = Request::create('/', 'POST', $payload);
        $route = (new RoutingRoute('POST', '/', []))->name($routeName);
        $route->bind($sub);
        $route->setParameter($param, $model);
        $sub->setRouteResolver(fn () => $route);

        return $sub;
    }

    /**
     * Taklifni yozadi: avval fan kafedrasi mudiriga, o'qituvchining o'zi shu kafedra mudiri bo'lsa — 1-bosqich bo'lmaydi,
     * to'g'ridan-to'g'ri registrator ofisiga (o'zini o'zi tasdiqlamaydi).
     */
    private function submitProposal(TimetableChangeApproval $svc, array $draft, Department $department, Teacher $actor, array $notice, string $reason)
    {
        $heads = $svc->departmentHeads((int) $department->department_hemis_id);
        $isHead = $heads->contains(fn ($head) => $head->id === $actor->id);
        abort_unless($isHead || $heads->isNotEmpty(), 422, "Ushbu kafedra uchun tasdiqlovchi kafedra mudiri topilmadi.");
        $draft['approval_stage'] = $isHead ? 'registrar' : 'department';
        $draft['department_hemis_id'] = $department->department_hemis_id;
        $draft['kafedra_name'] = $department->name;
        $draft['details']['reason'] = $reason;
        $draft['details']['notice'] = $notice;
        $draft['details']['head_skipped'] = $isHead;
        $draft['details']['steps'] = [['at' => now('Asia/Tashkent')->format('d.m.Y H:i'), 'by' => $actor->full_name,
            'action' => "So'rov yuborildi" . (($draft['requester_role'] ?? '') === 'fan_masuli' ? " (fan mas'uli)" : '') . ': ' . $notice['old'] . ' → ' . $notice['new'],
            'note' => mb_substr($reason, 0, 500)]];
        $change = $svc->submit($draft);

        return response()->json(['ok' => true, 'pending_approval' => true, 'change_request_id' => $change->id,
            'summary' => $change->summary, 'message' => $isHead
                ? "Taklif registrator ofisiga yuborildi (siz shu kafedra mudirisiz). Registrator tasdiqlagach jadval o'zgaradi."
                : "Taklif kafedra mudiriga yuborildi. Kafedra mudiri va registrator ofisi tasdiqlagach jadval o'zgaradi."]);
    }

    private function ensureProposalTable(): void
    {
        abort_unless(TimetableChangeApproval::tableReady() && Schema::hasColumn('timetable_change_requests', 'approval_stage'),
            503, 'Migratsiyani ishga tushiring (php artisan migrate).');
    }

    public function approve(Request $request, TimetableChangeRequest $changeRequest, TimetableChangeApproval $svc)
    {
        if (TimetableChangeApproval::isTeacherProposal($changeRequest)) {
            abort_unless($svc->canReviewTeacherProposal($changeRequest, $this->actor($request), $this->activeRole($request)), 403);
            if ($changeRequest->approval_stage === 'department') {
                $result = $svc->endorse($changeRequest, $this->actor($request), $this->activeRole($request));

                return $result['ok']
                    ? response()->json(['ok' => true, 'message' => "Kafedra mudiri tasdiqladi. Registrator ofisiga yuborildi; jadval hali o'zgarmadi."])
                    : response()->json(['error' => $result['error']], 422);
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
        if (TimetableChangeApproval::isTeacherProposal($changeRequest)) {
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

        return $svc->cancel($changeRequest, $this->actor($request))
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

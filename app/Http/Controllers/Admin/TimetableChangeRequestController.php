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
                        // Kafedra — bir xil nomli yozuvlari bilan (asosiy va o'rindoshlik).
                        $ids = app(\App\Services\Timetable\KafedraResolver::class)->group($deptId);
                        $q->orWhere(fn ($incoming) => $incoming->whereIn('requester_role', TimetableChangeApproval::TEACHER_PROPOSAL_ROLES)->whereIn('department_hemis_id', $ids));
                    }
                }
                // Dars o'qituvchisi: boshqa kishi (fan mas'uli, Takliflar) uning darsiga so'rov yuborgan bo'lsa — ko'radi (tasdiqlamaydi).
                if ($actor instanceof Teacher && in_array($role, TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true)) {
                    $q->orWhere(fn ($lesson) => $lesson->whereJsonContains('details->lesson_teacher_ids', (int) $actor->id));
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

    /**
     * Sikl (4–6-kurs) darsi: amaliy — shu kunga (shu kundan blok oxirigacha yoki tanlangan kunlarga); ma'ruza — butun oqim uchun
     * tanlangan kun(lar)ga yangi boshlanish soati — tekshiruv (har kun alohida; muammoli kunlar ro'yxati — 422).
     */
    public function checkCycleTime(Request $request, TimetableBoard $board)
    {
        [$data, $units] = $this->cycleProposalInput($request, $board, false);
        $labels = app(TimetableController::class)->cycleProposalLabels($board, $units, $data);

        return response()->json(['ok' => true, 'message' => 'Vaqt bo‘sh. Taklif yuborishingiz mumkin.', 'old' => $labels['old'], 'new' => $labels['new'],
            'days' => $labels['days'], 'units' => array_values(array_map(fn ($x) => (string) $x['u']['unit'], $units)), 'swaps' => $labels['swaps']]);
    }

    public function proposeCycleTime(Request $request, TimetableBoard $board, TimetableChangeApproval $svc)
    {
        [$data, $units, $as, $lessonTeachers, $cardIds] = $this->cycleProposalInput($request, $board, true);
        $actor = $this->actor($request);
        $controller = app(TimetableController::class);
        $lecture = TimetableController::cycleProposalIsLecture($data);
        // O'qituvchi (amaliy): har kun alohida — shu kunda amaliyot qizil to'qnashuvda va yangi soat toza bo'lsa — darhol (so'rovsiz,
        // TimetableTeacherConflictFix); qolgan kunlar — bitta so'rov. Sabab faqat so'rov qoladigan bo'lsa kerak.
        [$fixDays, $restDays] = $controller->teacherCycleFixSplit($request, $board, $units, $data);
        if ($restDays && trim((string) ($data['reason'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['reason' => "So'rov sababini kiriting."]);
        }
        // So'rov qoladigan bo'lsa — fan kafedrasi va tasdiqlovchi mudir darhol yozishdan OLDIN aniqlanadi (topilmasa — hech narsa o'zgarmaydi).
        $cards = TimetableCard::whereIn('id', $cardIds)->get();
        $department = $restDays ? $this->proposalDepartment(fn (int $id) => $cards->every(fn ($c) => $controller->proposalDepartmentMatches($c, $id))) : null;
        if ($department && $fixDays) {
            $heads = $svc->departmentHeads((int) $department->department_hemis_id);
            abort_unless($heads->isNotEmpty(), 422, "Ushbu kafedra uchun tasdiqlovchi kafedra mudiri topilmadi.");
        }
        $notified = $fixDays ? $controller->teacherCycleFixApply($request, $board, $units, $data, $fixDays, $as) : [];
        if (!$restDays) {
            return response()->json(['ok' => true, 'applied_days' => $fixDays, 'proposed_request_id' => null, 'notified' => $notified,
                'message' => count($fixDays) . " kun darhol joylandi (to'qnashuvdagi dars — so'rovsiz)."]);
        }
        if ($fixDays) {
            // Qolgan kunlar — aniq sanalar bilan bitta so'rov (darhol yozilgan kunlardan keyingi holat bo'yicha).
            $data = array_merge($data, ['dates' => $restDays, 'date' => $restDays[0], 'scope' => 'day']);
            $units = $controller->cycleProposalUnits($board, $data, null);
        }
        // Ko'p kunli so'rov: dates (bitta yangi soat barcha kunlarga); date — birinchi kun (eski maydon, tavsif va qidiruv uchun).
        $payload = ['specialty_name' => $data['specialty_name'], 'course' => (int) $data['course'], 'subject_name' => $data['subject_name'],
            'group_name' => $data['group_name'], 'units' => array_values(array_map(fn ($x) => (string) $x['u']['unit'], $units)),
            'date' => $data['date'], 'scope' => $data['scope'] ?? 'day', 'start_hour' => (int) $data['start_hour'],
            'layer' => 'internal', 'view' => 'group', 'training_type' => $lecture ? 'lecture' : 'practice', '_change_hint' => $data['reason']]
            + (!empty($data['dates']) ? ['dates' => array_values($data['dates'])] : [])
            // Ma'ruza: to'sib turgan amaliyot usuli — tasdiqlanganda ma'ruza bilan birga qo'llanadi.
            + ($lecture ? array_filter(array_intersect_key($data, array_flip(['practice_place', 'practice_gap', 'practice_pair'])), fn ($v) => $v !== null) : []);
        $notice = $controller->cycleProposalLabels($board, $units, $payload);
        // Kim yubordi: o'qituvchi (o'z darsi) yoki fan mas'uli (mas'ul fanidagi boshqa o'qituvchi darsi) — requester_role.
        $draft = $svc->draft($this->proposalRequest(TimetableController::TEACHER_CYCLE_ROUTE, $payload, 'board', $board), $actor, $board, $as);
        $draft['details'] = array_merge($draft['details'], $svc->detailsFromCards($cards));
        $draft['details']['proposer'] = ['as' => $as, 'teacher_id' => (int) $actor->id];
        $draft['details']['lesson_teacher_ids'] = $lessonTeachers;
        $draft['details']['original_signature'] = $controller->teacherProposalSignature(TimetableController::TEACHER_CYCLE_ROUTE, ['board' => $board->id], $payload);
        $type = $lecture ? "ma'ruza" : 'amaliy';
        $draft['summary'] = ($notice['days']
            ? $notice['subject'] . ' · ' . $notice['groups'] . ' · ' . $type . ' (sikl) · ' . $notice['when'] . ' → ' . $notice['new_time']
            : $notice['subject'] . ' · ' . $notice['groups'] . ' (' . $type . ', sikl' . ($lecture ? ', butun oqim' : '') . '): ' . $notice['old'] . ' → ' . $notice['new'])
            . ($notice['swaps'] ? ' · ' . $notice['swaps'] : '') . ' — ' . mb_substr($data['reason'], 0, 300);

        $res = $this->submitProposal($svc, $draft, $department, $actor, $notice, (string) $data['reason']);
        $j = $res->getData(true);
        $j['applied_days'] = $fixDays;
        $j['proposed_request_id'] = $j['change_request_id'] ?? null;
        if ($fixDays) {
            $j['notified'] = $notified;
            $j['message'] = count($fixDays) . ' kun darhol joylandi, ' . count($restDays) . ' kun — so‘rov #' . ($j['change_request_id'] ?? '') . '. ' . ($j['message'] ?? '');
        }

        return response()->json($j);
    }

    /**
     * Sikl so'rovi kiritmasi: shu guruhcha(lar)dagi amaliy dars — o'qituvchining o'z darsi YOKI fani uning mas'ul fanlari
     * ro'yxatida (fan mas'uli — shu fanning istalgan o'qituvchisi darsi); bandlik va sana tekshiriladi.
     * Ma'ruza (training_type = lecture) — doim oqim bo'yicha: istalgan guruhcha qatoridan oqimdagi barcha guruhchalar; o'qituvchi — oqim
     * ma'ruzasini o'tadigan, fan mas'uli — ma'ruza fani mas'ul fanlarida. Ko'p kunli (dates) — date birinchi sana bo'ladi.
     * @return array{0: array, 1: array, 2: string, 3: int[], 4: int[]} [ma'lumot, guruhchalar, kim (oqituvchi|fan_masuli), dars o'qituvchilari
     *     (o'zidan boshqa), dars kartalari (kafedra uchun)]
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
            'date' => 'required_without:dates|nullable|date_format:Y-m-d',
            'dates' => 'nullable|array|min:1|max:60',
            'dates.*' => 'distinct|date_format:Y-m-d',
            'scope' => 'nullable|in:day,rest',
            'start_hour' => 'required|integer|min:1|max:24',
            'training_type' => 'nullable|in:practice,lecture',
            // Ma'ruza: to'sib turgan amaliyot (keep — tegilmaydi, kesishsa xato; swap — o'rin almashish; after; gap; pair).
            'practice_place' => 'nullable|in:keep,swap,after,gap,pair',
            'practice_gap' => 'nullable|integer|min:0|max:6',
            'practice_pair' => 'nullable|integer|min:1|max:12',
        // Sabab — so'rov uchun (o'qituvchining to'qnashuvdagi darsi hamma kunda so'rovsiz joylansa — kerak emas; proposeCycleTime tekshiradi).
        ] + ($withReason ? ['reason' => 'nullable|string|max:1000'] : []));
        if (!empty($data['dates'])) {
            $data['dates'] = TimetableController::cycleProposalDates($data);
            $data['date'] = $data['dates'][0];
            $data['scope'] = 'day';
        } else {
            unset($data['dates']);
        }
        $controller = app(TimetableController::class);
        $tid = (int) $actor->id;
        if (TimetableController::cycleProposalIsLecture($data)) {
            // Oqim: so'rov qaysi guruhcha qatoridan bo'lsa ham — shu guruhchaning oqimidagi barcha guruhchalar.
            $first = $controller->cycleProposalUnits($board, $data, null);
            abort_unless($first, 422, 'Sikl darsi topilmadi (blok joylanmagan yoki guruhcha o‘zgargan).');
            $flow = (string) ($first[0]['u']['parent'] ?: $first[0]['u']['unit']);
            $units = $controller->cycleProposalUnits($board, ['group_name' => $flow] + $data, null) ?: $first;
            $lecCards = $controller->cycleLectureCardsFor($board, $units);
            $own = $lecCards->contains(fn ($c) => (int) $c->teacher_id === $tid);
            abort_unless($own || $controller->cycleLectureResponsible($lecCards, $tid), 403,
                "Faqat o'zingiz o'tadigan yoki mas'ul faningizdagi ma'ruza vaqtini taklif qila olasiz.");
            $as = $own ? 'oqituvchi' : 'fan_masuli';
            $lessonTeachers = $lecCards->pluck('teacher_id')->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0 && $id !== $tid)->unique()->sort()->values()->all();
            $data['units'] = array_map(fn ($x) => (string) $x['u']['unit'], $units);
            $controller->teacherProposalValidate(TimetableController::TEACHER_CYCLE_ROUTE, ['board' => $board->id], $data, []);

            return [$data, $units, $as, $lessonTeachers, $lecCards->pluck('id')->map(fn ($id) => (int) $id)->all()];
        }
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

        return [$data, $units, $as, $lessonTeachers, collect($units)->flatMap(fn ($x) => array_map(fn ($c) => (int) $c->id, $x['u']['cards']))->all()];
    }

    /**
     * "Takliflar"ni qo'llash (admin bo'lmaganlar): jadval darhol o'zgarmaydi — bitta so'rov (taklifning barcha amallari yaxlit).
     * O'qituvchi / fan mas'uli — kafedra mudiri → registrator ofisi; kafedra mudiri — faqat registrator ofisi. Huquq (eng keng rol),
     * qamrov, sana va bandlik shu yerda, keyin har tasdiq bosqichida qayta tekshiriladi.
     */
    public function proposeSuggestion(Request $request, TimetableBoard $board, TimetableChangeApproval $svc)
    {
        $this->ensureProposalTable();
        $actor = $this->actor($request);
        $ctl = app(TimetableController::class);
        $rights = $ctl->suggestionRights($actor);
        abort_if($rights['level'] === 'admin', 422, "Admin uchun taklif darhol qo'llanadi (week-suggestions/apply).");
        $data = $ctl->suggestionPayload($request);
        $data['actions'] = array_map(fn ($a) => $a + ['week' => (int) $data['week']], array_values($data['actions']));
        $check = $ctl->suggestionApplyCheck($rights, $board, $data);
        abort_unless($check['mode'] !== null, $check['code'], (string) $check['why']);
        // Sabab — so'rov yuboruvchi kiritadi (kafedra mudiri va registrator ko'radi; so'rov matniga qo'shiladi). Huquqdan keyin.
        $userReason = trim((string) $request->validate(['reason' => 'required|string|max:1000'],
            ['reason.required' => "So'rov sababini kiriting."])['reason']);
        $run = $ctl->suggestionDryRun($board, $data, []);
        $cards = $ctl->suggestionCards($board, $data);
        $primary = $cards->firstWhere('id', (int) $data['card_id']) ?? $cards->first();
        $tid = (int) $actor->id;
        $as = $check['mode'] === 'registrar' ? 'kafedra_mudiri'
            : ($cards->every(fn ($c) => (int) $c->teacher_id === $tid) ? 'oqituvchi' : 'fan_masuli');
        $payload = ['week' => (int) $data['week'], 'card_id' => (int) $data['card_id'], 'type' => $data['type'] ?? null,
            'title' => $data['title'] ?? null, 'actions' => $data['actions']];
        $draft = $svc->draft($this->proposalRequest(TimetableController::SUGGESTION_ROUTE, $payload + ['_change_hint' => $userReason], 'board', $board), $actor, $board, $as);
        $draft['details']['proposer'] = ['as' => $as, 'teacher_id' => $tid];
        $draft['details']['lesson_teacher_ids'] = array_values(array_diff($run['teacher_ids'], [$tid]));
        $draft['details']['notices'] = $run['notices'];
        $draft['details']['original_signature'] = $ctl->suggestionSignature($board, $payload);
        $first = $run['notices'][0] ?? ['old' => '—', 'new' => (string) ($data['title'] ?? ''), 'subject' => (string) $primary->subject_name,
            'teacher' => (string) $primary->teacher_name, 'room' => (string) ($primary->auditorium_name ?: $primary->auditorium_code)];
        $notice = ['old' => $first['old'], 'new' => $first['new'], 'subject' => $first['subject'], 'teacher' => $first['teacher'], 'room' => $first['room'] ?? ''];
        $reason = $userReason;
        if ($as !== 'kafedra_mudiri') {
            $department = $this->proposalDepartment(fn (int $id) => $ctl->proposalDepartmentMatches($primary, $id));

            return $this->submitProposal($svc, $draft, $department, $actor, $notice, $reason);
        }
        $deptId = app(\App\Services\Timetable\KafedraResolver::class)->teacherDepartment($actor);
        $draft['approval_stage'] = 'registrar';
        $draft['department_hemis_id'] = $deptId ?: null;
        $draft['kafedra_name'] = Department::where('department_hemis_id', $deptId)->value('name') ?: $draft['kafedra_name'];
        $draft['details']['reason'] = $reason;
        $draft['details']['notice'] = $notice;
        $draft['details']['steps'] = [['at' => now('Asia/Tashkent')->format('d.m.Y H:i'), 'by' => $actor->full_name,
            'action' => "So'rov yuborildi (kafedra mudiri): " . $notice['old'] . ' → ' . $notice['new'], 'note' => mb_substr($reason, 0, 500)]];
        $change = $svc->submit($draft);

        return response()->json(['ok' => true, 'pending_approval' => true, 'change_request_id' => $change->id, 'summary' => $change->summary,
            'message' => "Taklif registrator ofisi va o'quv bo'limiga yuborildi. Ulardan biri tasdiqlagach jadval o'zgaradi."]);
    }

    /**
     * Fan mas'uli (responsible-move, faol rol — fan_masuli): Takliflar so'rovi FAOL ROL huquqi bilan — fan mas'uli (o'qituvchi darajasi),
     * boshqa rollaridan (admin, kafedra mudiri) qat'i nazar: doim fan kafedrasi mudiri → registrator ofisi (mudirning o'zi so'rasa —
     * submitProposal kabi faqat registrator). Huquq, sana va bandlik chaqiruvchida (responsibleMoveEvaluate) tekshirilgan; submit() qulf
     * ostida qayta tekshiradi.
     */
    public function proposeResponsibleSuggestion(Request $request, TimetableBoard $board, array $payload, string $reason, TimetableChangeApproval $svc)
    {
        $this->ensureProposalTable();
        $actor = $this->actor($request);
        abort_unless($actor instanceof Teacher, 403);
        $ctl = app(TimetableController::class);
        $run = $ctl->suggestionDryRun($board, $payload, []);
        $cards = $ctl->suggestionCards($board, $payload);
        $primary = $cards->firstWhere('id', (int) $payload['card_id']) ?? $cards->first();
        $tid = (int) $actor->id;
        $draft = $svc->draft($this->proposalRequest(TimetableController::SUGGESTION_ROUTE, $payload + ['_change_hint' => $reason], 'board', $board), $actor, $board, 'fan_masuli');
        $draft['details']['proposer'] = ['as' => 'fan_masuli', 'teacher_id' => $tid];
        $draft['details']['lesson_teacher_ids'] = array_values(array_diff($run['teacher_ids'], [$tid]));
        $draft['details']['notices'] = $run['notices'];
        $draft['details']['original_signature'] = $ctl->suggestionSignature($board, $payload);
        $first = $run['notices'][0] ?? ['old' => '—', 'new' => (string) ($payload['title'] ?? ''), 'subject' => (string) $primary->subject_name,
            'teacher' => (string) $primary->teacher_name, 'room' => (string) ($primary->auditorium_name ?: $primary->auditorium_code)];
        $notice = ['old' => $first['old'], 'new' => $first['new'], 'subject' => $first['subject'], 'teacher' => $first['teacher'], 'room' => $first['room'] ?? ''];
        $department = $this->proposalDepartment(fn (int $id) => $ctl->proposalDepartmentMatches($primary, $id));

        return $this->submitProposal($svc, $draft, $department, $actor, $notice, $reason);
    }

    /**
     * Fan kafedrasi (aniq bitta) — id bo'yicha: bir xil nomli yozuvlar (asosiy va o'rindoshlik) bitta kafedra, natija — asosiy
     * yozuv (mudiri biriktirilgani). Turli kafedralar mos kelsa — aniq emas (422).
     */
    private function proposalDepartment(callable $matches): Department
    {
        $kaf = app(\App\Services\Timetable\KafedraResolver::class);
        $primaries = Department::where('structure_type_code', 12)->where('active', true)->get()
            ->filter(fn ($dept) => $matches((int) $dept->department_hemis_id))
            ->map(fn ($dept) => $kaf->primary((int) $dept->department_hemis_id))->unique()->values();
        abort_unless($primaries->count() === 1, 422, "Fan kafedrasi aniq topilmadi. Registrator ofisiga murojaat qiling.");

        return Department::where('department_hemis_id', $primaries->first())->firstOrFail();
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
                ? "Taklif registrator ofisi va o'quv bo'limiga yuborildi (siz shu kafedra mudirisiz). Ulardan biri tasdiqlagach jadval o'zgaradi."
                : "Taklif kafedra mudiriga yuborildi. Kafedra mudiri, so'ng registrator ofisi yoki o'quv bo'limi tasdiqlagach jadval o'zgaradi."]);
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
            abort(403, 'Jadval o\'zgarishlarini faqat registrator ofisi, o\'quv bo\'limi va adminlar tasdiqlaydi.');
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

<?php

namespace App\Services\Timetable;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Notification;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableChangeRequest;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Dars jadvali o'zgarishlarini registrator ofisi tasdig'idan o'tkazish.
 *
 * Doska sozlamasida "approval_required" yoqilgan bo'lsa, kafedra mudirining jadvalni
 * o'zgartiradigan so'rovi (joylash, ko'chirish, kun soati, o'qituvchi/xona) darhol
 * bajarilmaydi — `timetable_change_requests` ga yoziladi. Registrator tasdiqlaganda
 * asl so'rov kafedra mudiri nomidan qayta bajariladi (barcha tekshiruvlar bilan), so'ng
 * guruh talabalari, o'qituvchi, o'quv bo'limi, dekanat va kafedra mudiriga xabar boradi.
 *
 * Tasdiqsiz qo'llangan o'zgarishlar (o'quv bo'limi, admin, rejim o'chiq doska) ham shu
 * jadvalga "applied" holatida yoziladi — doskaning o'zgarishlar tarixi o'chirilmaydi.
 */
class TimetableChangeApproval
{
    /** Tasdiq talab qiladigan route'lar: [controller metodi, model parametri, turi]. */
    public const GATED = [
        'admin.timetable.cards.place'         => ['placeCard', 'card', 'move'],
        'admin.timetable.cards.week-override' => ['weekOverride', 'card', 'move'],
        'admin.timetable.boards.unplace'      => ['unplaceAll', 'board', 'unplace'],
        'admin.timetable.cycle-plan'          => ['cyclePlan', 'board', 'unplace'],   // faqat clear=1
        'admin.timetable.cycle-place'         => ['cyclePlace', 'board', 'cycle'],
        'admin.timetable.cycle-assign'        => ['cycleAssignSave', 'board', 'teacher'],
        'admin.timetable.cycle-lecture-slots' => ['cycleLectureSlots', 'board', 'hour'],
        'admin.timetable.cycle-auto-shift'    => ['cycleAutoShift', 'board', 'hour'],
        'admin.timetable.assign-teacher'      => ['assignTeacher', 'board', 'teacher'],
        'admin.timetable.assign-room'         => ['assignUnitRoom', 'board', 'room'],
    ];

    /** Tarixga yoziladigan amallar: tasdiq talab qilinadiganlar + kartani tahrirlash, KTR haftalari (admin). */
    public const LOGGED = self::GATED + [
        'admin.timetable.cards.update'        => ['updateCard', 'card', 'card'],
        'admin.timetable.ktr-weeks'           => ['ktrWeeks', 'board', 'weeks'],   // dry_run emas
        'admin.timetable.weekly-layer-copy'   => ['weeklyLayerCopy', 'board', 'layer'],   // dry_run emas
        'admin.timetable.boards.split-variant-mixed' => ['splitVariantMixed', 'board', 'split'],   // dry_run emas
        'admin.timetable.boards.merge-variant-lectures' => ['mergeVariantLectures', 'board', 'merge'],   // dry_run emas
        'admin.timetable.boards.add-missing-groups' => ['addMissingGroupCards', 'board', 'groups'],   // dry_run emas
    ];

    /** Tasdiqlay oladigan rollar. */
    public const APPROVER_ROLES = ['superadmin', 'admin', 'registrator_ofisi'];

    /** Doskaning butun o'zgarishlar tarixini ko'radigan (lekin tasdiqlamaydigan) rollar. */
    public const HISTORY_ROLES = ['kichik_admin', 'oquv_bolimi', 'oquv_bolimi_boshligi'];

    /** Tasdiqdan o'tishi kerak bo'lgan rollar. */
    public const GATED_ROLES = ['kafedra_mudiri'];

    /** Biriktirish amallari va ular o'zgartiradigan maydonlar (sikl biriktiruvi ikkalasini ham yozadi). */
    public const ASSIGN_FIELDS = [
        'admin.timetable.assign-teacher' => ['teacher'],
        'admin.timetable.assign-room'    => ['room'],
        'admin.timetable.cycle-assign'   => ['teacher', 'room'],
    ];

    public function canReviewTeacherProposal(TimetableChangeRequest $req, $actor, string $role): bool
    {
        if ($req->status !== TimetableChangeRequest::PENDING || $req->isBy($actor)) {
            return false;
        }
        if ($req->approval_stage === 'department') {
            $departmentId = (int) ($actor?->head_department_hemis_id ?: ($actor?->department_hemis_id ?? 0));
            return $actor instanceof Teacher && $role === 'kafedra_mudiri'
                && $actor->hasRole('kafedra_mudiri') && $departmentId > 0
                && $departmentId === (int) $req->department_hemis_id;
        }

        return $req->approval_stage === 'registrar' && in_array($role, self::APPROVER_ROLES, true)
            && $actor && $actor->hasRole($role);
    }

    public function proposalSignature(TimetableCard $card, int $week): string
    {
        $override = \App\Models\TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();

        return hash('sha256', json_encode([$card->only(['day', 'pair', 'start_half', 'teacher_id', 'auditorium_code', 'len_half', 'kafedra_name', 'subject_name', 'faculty_name', 'specialty_name', 'course', 'group_name', 'group_names', 'weeks']),
            $override?->only(['day', 'pair', 'start_half', 'cancelled', 'auditorium_code'])]));
    }

    /** The department approves the proposal only; the timetable is untouched. */
    public function endorse(TimetableChangeRequest $req, $actor, string $role): bool
    {
        return (bool) $this->whilePending($req, function ($locked) use ($actor, $role) {
            abort_unless($locked->approval_stage === 'department' && $this->canReviewTeacherProposal($locked, $actor, $role), 403);
            $locked->update([
                'approval_stage' => 'registrar', 'head_reviewer_id' => $actor->id,
                'head_reviewer_name' => $actor->full_name, 'head_reviewed_at' => now(),
            ]);
            $this->notifyRegistrars($locked);

            return true;
        });
    }

    public function departmentHeads(int $departmentId)
    {
        return $this->teachersWithRoles(['kafedra_mudiri'])->filter(fn (Teacher $t) =>
            (int) ($t->head_department_hemis_id ?: $t->department_hemis_id) === $departmentId);
    }

    private function notifyDepartmentHeads(TimetableChangeRequest $req): void
    {
        $id = $req->id;
        dispatch(function () use ($id) {
            $fresh = TimetableChangeRequest::find($id);
            if ($fresh && $fresh->status === TimetableChangeRequest::PENDING && $fresh->approval_stage === 'department') {
                $this->departmentHeads((int) $fresh->department_hemis_id)->each(fn (Teacher $t) =>
                    $this->sendToTeacher($t, "O'qituvchi vaqt o'zgarishini taklif qildi — tasdiq kutilmoqda", $fresh->requester_name . ":\n" . $fresh->summary, $fresh));
            }
        })->afterResponse();
    }

    public static function tableReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('timetable_change_requests');
    }

    public static function boardRequiresApproval(?TimetableBoard $board): bool
    {
        return $board && self::tableReady() && !empty(($board->settings ?? [])['approval_required']);
    }

    /** So'rov jadvalni o'zgartiradimi (tarixga yoziladigan amal). */
    public static function isChange(Request $request): bool
    {
        $route = (string) optional($request->route())->getName();
        if (!isset(self::LOGGED[$route]) || !self::tableReady()) {
            return false;
        }
        // Sikl rejasi faqat "Bo'shatish" (clear=1) bo'lganda o'zgartiradi; filtrli ko'rinishda clear yo'q.
        if ($route === 'admin.timetable.cycle-plan') {
            return $request->boolean('clear') && !$request->boolean('mine') && !$request->boolean('kafedra');
        }
        // KTR haftalari, qatlamga ko'chirish, bo'lak kartalarini bo'lish/birlashtirish: avval sinov (dry_run) — u hech narsani o'zgartirmaydi.
        if (in_array($route, ['admin.timetable.ktr-weeks', 'admin.timetable.weekly-layer-copy', 'admin.timetable.boards.split-variant-mixed', 'admin.timetable.boards.merge-variant-lectures', 'admin.timetable.boards.add-missing-groups'], true)) {
            return !$request->boolean('dry_run');
        }

        return true;
    }

    /**
     * Shu so'rov tasdiqqa yuborilishi kerakmi (o'zgartiradigan amal + gated rol + doska rejimi).
     * Istisno — bo'sh darsga birinchi biriktirish (isFirstAssignment): uni middleware doska qulfi ostida tekshiradi.
     */
    public static function shouldIntercept(Request $request, string $activeRole, ?TimetableBoard $board): bool
    {
        return in_array($activeRole, self::GATED_ROLES, true)
            && isset(self::GATED[(string) optional($request->route())->getName()])
            && self::isChange($request)
            && self::boardRequiresApproval($board);
    }

    /** O'qituvchi/xona birinchi marta biriktirilmoqda (bo'sh joyga) — tasdiq kerak emas. */
    public static function isFirstAssignment(Request $request, ?TimetableBoard $board): bool
    {
        $route = (string) optional($request->route())->getName();
        if (!$board || !isset(self::ASSIGN_FIELDS[$route])) {
            return false;
        }
        try {
            return app(TimetableController::class)->isFirstAssignment($board, $route, $request->all());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Amal muvaffaqiyatli bajarildimi (xato yoki tasdiqqa yuborilgan javob emas). */
    public static function succeeded($response): bool
    {
        if (!$response instanceof \Symfony\Component\HttpFoundation\Response || $response->getStatusCode() >= 400) {
            return false;
        }
        $data = $response instanceof JsonResponse ? $response->getData(true) : [];

        return !is_array($data) || (empty($data['error']) && empty($data['pending_approval']));
    }

    /**
     * O'zgarish yozuvi (TimetableChangeRequest atributlari). Amal bajarilishidan OLDIN
     * olinadi — keyin karta allaqachon ko'chgan bo'ladi va "qayerdan" yo'qoladi.
     */
    public function draft(Request $request, $actor, TimetableBoard $board, string $role = ''): array
    {
        $route = (string) $request->route()->getName();
        [, $param, $kind] = self::LOGGED[$route];
        $payload = collect($request->except(['_token', '_method']))->all();
        $hint = trim((string) ($payload['_change_hint'] ?? ''));
        unset($payload['_change_hint']);

        $card = $param === 'card' ? $this->routeCard($request) : null;
        $params = ['board' => $board->id] + ($card ? ['card' => $card->id] : []);

        $cards = $this->affectedCards($board, $route, $payload, $card);
        $details = $this->detailsFromCards($cards);
        [$subject, $core] = $this->describe($board, $route, $payload, $card, $cards);
        if (in_array($route, ['admin.timetable.cycle-assign', 'admin.timetable.assign-teacher', 'admin.timetable.cards.update'], true)
            && !empty($payload['teacher_id'])) {
            $details['new_teacher_id'] = (int) $payload['teacher_id'];
        }

        return [
            'board_id' => $board->id,
            'requester_id' => $actor?->getKey(),
            'requester_type' => $actor?->getMorphClass(),
            'requester_name' => $actor?->full_name ?? $actor?->name,
            'requester_role' => $role !== '' ? $role : null,
            'kafedra_name' => $this->requesterKafedra($actor),
            'route_name' => $route,
            'target_key' => $this->targetKey($route, $payload, $card, $board),
            'params' => $params,
            'payload' => $payload,
            'kind' => $kind,
            'subject_name' => $subject,
            'summary' => $core . ($hint !== '' ? ' — ' . mb_substr($hint, 0, 300) : ''),
            'details' => $details,
        ];
    }

    /** Tasdiq so'rovini yozadi (shu foydalanuvchining shu joy bo'yicha kutilayotgan so'rovi — almashtiriladi). */
    public function submit(array $draft): TimetableChangeRequest
    {
        return DB::transaction(function () use ($draft) {
            // Serialize booking checks on a stable row; concurrent requests cannot both reserve a free slot.
            TimetableBoard::whereKey($draft['board_id'])->lockForUpdate()->firstOrFail();
            if (($draft['requester_role'] ?? '') === 'oqituvchi') {
                $replaced = TimetableChangeRequest::where('board_id', $draft['board_id'])
                    ->where('requester_type', $draft['requester_type'])->where('requester_id', $draft['requester_id'])
                    ->where('target_key', $draft['target_key'])->where('status', TimetableChangeRequest::PENDING)->pluck('id')->all();
                $card = TimetableCard::findOrFail($draft['params']['card']);
                abort_unless((int) $card->teacher_id === (int) $draft['requester_id'], 403);
                app(TimetableController::class)->validateTimeProposal($card, $draft['payload'], $replaced);
            } elseif (!empty($draft['params']['card'])) {
                $replaced = TimetableChangeRequest::where('board_id', $draft['board_id'])
                    ->where('requester_type', $draft['requester_type'])->where('requester_id', $draft['requester_id'])
                    ->where('target_key', $draft['target_key'])->where('status', TimetableChangeRequest::PENDING)->pluck('id')->all();
                $card = TimetableCard::findOrFail($draft['params']['card']);
                app(TimetableController::class)->validatePendingTimeProposal($card, $draft['payload'], $draft['route_name'], $replaced);
            }
            $this->replacePending($draft, 'Yangi so\'rov bilan almashtirildi');

            $req = TimetableChangeRequest::create($draft + ['status' => TimetableChangeRequest::PENDING]);

            if ($req->approval_stage === 'department') {
                $this->notifyDepartmentHeads($req);
            } else {
                $this->notifyRegistrars($req);
            }

            return $req;
        });
    }

    /** Tasdiqsiz, darhol qo'llangan o'zgarish — tarix uchun yoziladi. */
    public function record(array $draft): TimetableChangeRequest
    {
        return TimetableChangeRequest::create($draft + ['status' => TimetableChangeRequest::APPLIED]);
    }

    /** Shu foydalanuvchining shu joy bo'yicha kutilayotgan so'rovlari bekor qilinadi (yangisi almashtiradi). */
    public function replacePending(array $draft, string $note): int
    {
        return $this->pendingOf($draft)->where('target_key', $draft['target_key'])
            ->update(['status' => TimetableChangeRequest::CANCELLED, 'review_note' => $note, 'reviewed_at' => now()]);
    }

    /**
     * Tasdiqsiz qo'llangan birinchi biriktirishdan keyin: shu foydalanuvchining shu darslardagi shu maydonni
     * (o'qituvchi/xona) yozadigan kutilayotgan biriktirish so'rovlari eskiradi — qaysi oynadan yuborilgan
     * bo'lmasin. Aks holda eski so'rov keyin tasdiqlanib, yangi biriktiruvni bosib ketardi.
     */
    public function supersedeAssignments(array $draft): int
    {
        $fields = self::ASSIGN_FIELDS[$draft['route_name']] ?? [];
        $cardIds = array_map('intval', $draft['details']['card_ids'] ?? []);
        $ids = $this->pendingOf($draft)->whereIn('route_name', array_keys(self::ASSIGN_FIELDS))->get()
            ->filter(fn (TimetableChangeRequest $r) => $r->target_key === $draft['target_key']
                || (array_intersect(self::ASSIGN_FIELDS[$r->route_name], $fields)
                    && array_intersect(array_map('intval', $r->details['card_ids'] ?? []), $cardIds)))
            ->pluck('id');

        return $ids->isEmpty() ? 0 : TimetableChangeRequest::whereIn('id', $ids)->where('status', TimetableChangeRequest::PENDING)
            ->update(['status' => TimetableChangeRequest::CANCELLED, 'review_note' => 'Yangi o\'zgarish bilan almashtirildi', 'reviewed_at' => now()]);
    }

    private function pendingOf(array $draft)
    {
        return TimetableChangeRequest::where('board_id', $draft['board_id'])
            ->where('requester_type', $draft['requester_type'])
            ->where('requester_id', $draft['requester_id'])
            ->where('status', TimetableChangeRequest::PENDING);
    }

    public const ALREADY_REVIEWED = 'Bu so\'rov allaqachon ko\'rib chiqilgan.';

    /**
     * Tasdiqlash: asl so'rov kafedra mudiri nomidan qayta bajariladi.
     * So'rov endi kutilmayotgan bo'lsa (parallel tasdiq/rad/bekor) — hech narsa qilinmaydi, stale=true.
     * @return array{ok: bool, error: ?string, stale?: bool}
     */
    public function apply(TimetableChangeRequest $req, $approver): array
    {
        $result = $this->whilePending($req, fn (TimetableChangeRequest $locked) => $this->applyLocked($locked, $approver));
        if ($result === null) {
            return ['ok' => false, 'error' => self::ALREADY_REVIEWED, 'stale' => true];
        }
        if ($result['ok']) {
            $id = $req->id;
            dispatch(function () use ($id) {
                $fresh = TimetableChangeRequest::find($id);
                if ($fresh) {
                    app(self::class)->notifyApproved($fresh);
                }
            })->afterResponse();
        }

        return $result;
    }

    /** Rad etish; so'rov endi kutilmayotgan bo'lsa false. */
    public function reject(TimetableChangeRequest $req, $approver, ?string $note, ?string $role = null): bool
    {
        $done = $this->whilePending($req, function (TimetableChangeRequest $locked) use ($approver, $note, $role) {
            if ($locked->requester_role === 'oqituvchi') {
                abort_unless($this->canReviewTeacherProposal($locked, $approver, $role ?? (string) session('active_role')), 403);
            }
            $locked->update([
                'status' => TimetableChangeRequest::REJECTED,
                'reviewer_id' => $approver?->id,
                'reviewer_name' => $approver?->full_name ?? $approver?->name,
                'review_note' => $note,
                'reviewed_at' => now(),
            ]);

            return $locked;
        });
        if (!$done) {
            return false;
        }
        if ($done->requester instanceof Teacher) {
            $this->sendToTeacher($done->requester, 'Dars jadvali o\'zgarishi rad etildi',
                $done->summary . ($note ? "\nIzoh: " . $note : ''), $done);
        }

        return true;
    }

    /** Yuboruvchi so'rovni qaytarib oladi; so'rov endi kutilmayotgan bo'lsa false. */
    public function cancel(TimetableChangeRequest $req): bool
    {
        return (bool) $this->whilePending($req, fn (TimetableChangeRequest $locked) => $locked->update([
            'status' => TimetableChangeRequest::CANCELLED,
            'review_note' => 'Yuboruvchi bekor qildi',
            'reviewed_at' => now(),
        ]));
    }

    /**
     * So'rov qatori qulflanadi va hali kutilayotgan bo'lsagina $fn bajariladi — ikki registrator
     * bir vaqtda tasdiqlasa yoki tasdiqlash paytida bekor qilinsa, amal ikki marta yoki
     * bekor qilingandan keyin bajarilmaydi. Kutilmayotgan bo'lsa null.
     */
    private function whilePending(TimetableChangeRequest $req, callable $fn)
    {
        return DB::transaction(function () use ($req, $fn) {
            TimetableBoard::whereKey($req->board_id)->lockForUpdate()->first();
            $locked = TimetableChangeRequest::whereKey($req->getKey())->lockForUpdate()->first();

            return $locked && $locked->status === TimetableChangeRequest::PENDING ? $fn($locked) : null;
        });
    }

    /** @return array{ok: bool, error: ?string} */
    private function applyLocked(TimetableChangeRequest $req, $approver): array
    {
        if ($req->requester_role === 'oqituvchi' && ($req->approval_stage !== 'registrar' || !$req->head_reviewed_at)) {
            return ['ok' => false, 'error' => 'Avval kafedra mudiri tasdiqlashi kerak.'];
        }
        if ($req->requester_role === 'oqituvchi') {
            abort_unless($this->canReviewTeacherProposal($req, $approver, (string) session('active_role')), 403);
        }
        [$method, $param] = self::GATED[$req->route_name] ?? [null, null];
        $requester = $req->requester;
        if (!$method || !$requester) {
            return $this->finish($req, $approver, false, 'So\'rov yuboruvchi yoki amal topilmadi.');
        }
        $board = TimetableBoard::find($req->board_id);
        $model = $param === 'card'
            ? TimetableCard::find($req->params['card'] ?? 0)
            : $board;
        if (!$board || !$model) {
            return $this->finish($req, $approver, false, 'Doska yoki kartochka endi mavjud emas.');
        }

        if ($model instanceof TimetableCard) {
            try {
                app(TimetableController::class)->validatePendingTimeProposal($model, $req->payload, $req->route_name, [$req->id]);
            } catch (ValidationException $e) {
                return $this->finish($req, $approver, false, collect($e->errors())->flatten()->first());
            }
        }
        if ($req->requester_role === 'oqituvchi') {
            $head = Teacher::find($req->head_reviewer_id);
            if (!$model instanceof TimetableCard || !$requester instanceof Teacher
                || (int) $model->teacher_id !== (int) $requester->id
                || !$head || !$head->hasRole('kafedra_mudiri')
                || (int) ($head->head_department_hemis_id ?: $head->department_hemis_id) !== (int) $req->department_hemis_id
                || !app(TimetableController::class)->proposalDepartmentMatches($model, (int) $req->department_hemis_id)) {
                return $this->finish($req, $approver, false, "Dars biriktiruvi yoki kafedra vakolati o'zgargan. Yangi taklif yuboring.");
            }
            if (($req->details['original_signature'] ?? '') !== $this->proposalSignature($model, (int) $req->payload['week'])) {
                return $this->finish($req, $approver, false, "Dars jadvali taklifdan keyin o'zgargan. Yangi taklif yuboring.");
            }
            try {
                app(TimetableController::class)->validateTimeProposal($model, $req->payload, [$req->id]);
            } catch (ValidationException $e) {
                return $this->finish($req, $approver, false, collect($e->errors())->flatten()->first());
            }
            // Replay with the endorsing head's real permissions, never by elevating a teacher.
            $requester = $head;
        }
        $sub = Request::create('/', 'POST', $req->payload ?? []);
        $sub->headers->set('Accept', 'application/json');
        $sub->setUserResolver(fn () => $requester);
        if (request()->hasSession()) {
            $sub->setLaravelSession(request()->session());
        }

        $prevRole = session('active_role');
        session(['active_role' => 'kafedra_mudiri']);
        $error = null;
        try {
            $response = app(TimetableController::class)->{$method}($sub, $model);
            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;
            $data = $response instanceof JsonResponse ? $response->getData(true) : [];
            if ($status >= 400 || !empty($data['error'])) {
                $error = (string) ($data['error'] ?? $data['message'] ?? ('HTTP ' . $status));
            }
        } catch (ValidationException $e) {
            $error = collect($e->errors())->flatten()->first() ?: 'Ma\'lumot noto\'g\'ri.';
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $error = $e->getMessage() ?: ('HTTP ' . $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('[TimetableChange] apply failed: ' . $e->getMessage(), ['request_id' => $req->id]);
            $error = 'Bajarishda xatolik: ' . $e->getMessage();
        } finally {
            session(['active_role' => $prevRole]);
        }

        return $this->finish($req, $approver, $error === null, $error);
    }

    private function finish(TimetableChangeRequest $req, $approver, bool $ok, ?string $error): array
    {
        $req->update([
            'status' => $ok ? TimetableChangeRequest::APPROVED : TimetableChangeRequest::FAILED,
            'reviewer_id' => $approver?->id,
            'reviewer_name' => $approver?->full_name ?? $approver?->name,
            'error' => $error,
            'reviewed_at' => now(),
        ]);

        return ['ok' => $ok, 'error' => $error];
    }

    // ─── Tavsif va qamrov ──────────────────────────────────────────

    private function routeCard(Request $request): ?TimetableCard
    {
        // Bazadan qayta o'qiladi: sinov bajarish route'dagi kartani xotirada o'zgartirib
        // qo'yadi (rollback faqat bazani tiklaydi) — tavsifda eski joy ko'rinsin.
        $card = $request->route('card');
        $id = $card instanceof TimetableCard ? $card->getKey() : $card;

        return $id ? TimetableCard::find($id) : null;
    }

    private function affectedCards(TimetableBoard $board, string $route, array $p, ?TimetableCard $card)
    {
        if ($card) {
            return collect([$card]);
        }
        if (in_array($route, ['admin.timetable.boards.unplace', 'admin.timetable.cycle-plan', 'admin.timetable.ktr-weeks', 'admin.timetable.weekly-layer-copy', 'admin.timetable.boards.split-variant-mixed', 'admin.timetable.boards.merge-variant-lectures', 'admin.timetable.boards.add-missing-groups'], true)) {
            return collect();
        }
        $controller = app(TimetableController::class);

        return $controller->changeRequestCards($board, $route, $p);
    }

    public function detailsFromCards($cards): array
    {
        $controller = app(TimetableController::class);
        $groups = [];
        $teachers = [];
        $faculties = [];
        $specialty = null;
        $course = null;
        foreach ($cards as $c) {
            foreach ($controller->changeRequestCardGroups($c) as $g) {
                $groups[$g] = true;
            }
            if ($c->teacher_id) {
                $teachers[(int) $c->teacher_id] = true;
            }
            if ($c->faculty_name) {
                $faculties[$c->faculty_name] = true;
            }
            $specialty ??= $c->specialty_name;
            $course ??= (int) $c->course;
        }

        return [
            'groups' => array_keys($groups),
            'teacher_ids' => array_keys($teachers),
            'faculties' => array_keys($faculties),
            'specialty' => $specialty,
            'course' => $course,
            'card_ids' => $cards->pluck('id')->take(200)->values()->all(),
        ];
    }

    private function describe(TimetableBoard $board, string $route, array $p, ?TimetableCard $card, $cards): array
    {
        $type = fn ($t) => $t === 'lecture' ? 'ma\'ruza' : ($t === 'practice' ? 'amaliy' : '');
        $dayName = function ($d) use ($board) {
            $names = $board->day_names ?: \App\Models\TimetableBoard::DEFAULT_DAY_NAMES;

            return $names[((int) $d) - 1] ?? ($d . '-kun');
        };
        $slot = fn ($d, $pr) => ($d && $pr) ? $dayName($d) . ', ' . $pr . '-para' : 'joylanmagan';

        $subject = $card->subject_name ?? ($p['subject_name'] ?? null);
        $group = $card
            ? ($card->group_name ?: implode(', ', (array) $card->group_names) ?: $card->oqim_label)
            : ($p['group_name'] ?? ($p['oqim_label'] ?? ''));
        $head = trim($subject . ($group ? ' · ' . $group : '') . (($card->training_type ?? $p['training_type'] ?? null) ? ' (' . $type($card->training_type ?? $p['training_type']) . ')' : ''));

        switch ($route) {
            case 'admin.timetable.cards.place':
                $ext = $this->externalLayer($p);
                $to = (!empty($p['day']) && !empty($p['pair'])) ? $slot($p['day'], $p['pair']) : 'jadvaldan olib tashlash';
                $from = $ext ? $slot($card->ext_day ?? null, $card->ext_pair ?? null) : $slot($card->day, $card->pair);
                $text = ($ext ? 'Tashqi jadval · ' : '') . $head . ': ' . $from . ' → ' . $to;
                break;
            case 'admin.timetable.cards.week-override':
                $act = ($p['action'] ?? '') === 'cancel' ? 'dars bekor qilinadi'
                    : (($p['action'] ?? '') === 'reset' ? 'asl joyiga qaytariladi' : 'ko\'chiriladi → ' . $slot($p['day'] ?? null, $p['pair'] ?? null));
                $text = $head . ': ' . ($p['week'] ?? '?') . '-hafta — ' . $act;
                break;
            case 'admin.timetable.cycle-lecture-slots':
                $day = isset($p['day_index']) ? ((int) $p['day_index'] + 1) . '-kun' : 'kunlar';
                $text = $head . ': ' . $day . (isset($p['start_hour']) ? ' — darslar ' . (int) $p['start_hour'] . '-soatdan boshlanadi' : ' — kun soatlari o\'zgartiriladi');
                break;
            case 'admin.timetable.cycle-place':
                $act = $p['action'] ?? 'place';
                $text = $head . ': sikl bloki ' . ($act === 'unplace' ? 'bo\'shatiladi' : (isset($p['start_index']) ? ((int) $p['start_index'] + 1) . '-kundan boshlab joylanadi' : 'ko\'chiriladi'));
                break;
            case 'admin.timetable.cycle-assign':
            case 'admin.timetable.assign-teacher':
                $t = !empty($p['teacher_id']) ? (Teacher::find($p['teacher_id'])?->full_name ?? ('#' . $p['teacher_id'])) : 'olib tashlanadi';
                $text = $head . ': o\'qituvchi → ' . $t;
                if (!empty($p['auditorium_code'])) {
                    $text .= '; xona → ' . $p['auditorium_code'];
                }
                break;
            case 'admin.timetable.assign-room':
                $text = $head . ': xona → ' . (!empty($p['auditorium_code']) ? $p['auditorium_code'] : 'olib tashlanadi');
                break;
            case 'admin.timetable.cards.update':
                $parts = [];
                if (array_key_exists('teacher_id', $p)) {
                    $parts[] = 'o\'qituvchi → ' . (!empty($p['teacher_id']) ? (Teacher::find($p['teacher_id'])?->full_name ?? ('#' . $p['teacher_id'])) : 'olib tashlanadi');
                }
                if (array_key_exists('auditorium_code', $p)) {
                    $parts[] = 'xona → ' . (!empty($p['auditorium_code']) ? $p['auditorium_code'] : 'olib tashlanadi');
                }
                if (!empty($p['len_half'])) {
                    $parts[] = 'davomiyligi → ' . ((int) $p['len_half'] / 2) . ' para';
                }
                $text = $head . ': ' . ($parts ? implode('; ', $parts) : 'kartochka tahrirlandi');
                break;
            default:
                $scope = trim(implode(', ', array_filter([
                    implode('/', (array) ($p['faculty_names'] ?? [])),
                    implode('/', (array) ($p['specialty_names'] ?? [])) ?: ($p['specialty_name'] ?? ''),
                    ($p['courses'] ?? null) ? implode(',', (array) $p['courses']) . '-kurs' : (!empty($p['course']) ? $p['course'] . '-kurs' : ''),
                ])));
                $text = match ($route) {
                    'admin.timetable.cycle-plan' => 'Sikl bloklarini bo\'shatish',
                    'admin.timetable.cycle-auto-shift' => 'Sikl to\'qnashuvlari — guruhcha smenalari avtomatik ajratildi',
                    'admin.timetable.ktr-weeks' => 'Haftalar KTR bo\'yicha belgilandi',
                    'admin.timetable.weekly-layer-copy' => 'Haftalik joylashuv tashqi jadvalga ko\'chirildi'
                        . (!empty($p['clear_internal']) ? ', ichki jadval bo\'shatildi' : ''),
                    'admin.timetable.boards.split-variant-mixed' => 'Aralash bo\'lak kartalari asos guruhlar bo\'yicha bo\'lindi',
                    'admin.timetable.boards.merge-variant-lectures' => 'Bo\'lakli fan ma\'ruzalari birlashtirildi',
                    'admin.timetable.boards.add-missing-groups' => 'Oqimga qo\'shilgan yangi guruhlar uchun kartochkalar qo\'shildi',
                    default => ($this->externalLayer($p) ? 'Tashqi jadval · ' : '') . 'Joylashuvlarni bo\'shatish',
                } . ($scope ? ': ' . $scope : '');
        }

        return [$subject, $text];
    }

    /** Haftalik jadval qatlami tashqimi: so'rovda bo'lmasa — rol bo'yicha (o'quv bo'limi — tashqi), controller kabi. */
    private function externalLayer(array $p): bool
    {
        return ($p['layer'] ?? (in_array((string) session('active_role'), ['oquv_bolimi', 'oquv_bolimi_boshligi'], true) ? 'external' : 'internal')) === 'external';
    }

    private function targetKey(string $route, array $p, ?TimetableCard $card, TimetableBoard $board): string
    {
        $pick = fn (array $keys) => implode('|', array_map(fn ($k) => mb_strtolower(trim((string) (is_array($p[$k] ?? null) ? implode(',', $p[$k]) : ($p[$k] ?? '')))), $keys));
        $short = match ($route) {
            'admin.timetable.cards.place', 'admin.timetable.cards.update' => 'card:' . $card?->id,
            'admin.timetable.cards.week-override' => 'card:' . $card?->id . ':w' . ($p['week'] ?? ''),
            'admin.timetable.cycle-lecture-slots' => $pick(['specialty_name', 'course', 'subject_name', 'group_name', 'placement_group', 'sub_group', 'training_type', 'day_index']),
            'admin.timetable.cycle-place', 'admin.timetable.cycle-assign' => $pick(['specialty_name', 'course', 'subject_name', 'group_name', 'training_type']),
            'admin.timetable.assign-teacher', 'admin.timetable.assign-room' => $pick(['faculty_name', 'specialty_name', 'course', 'subject_name', 'training_type', 'oqim_label', 'group_name']),
            default => md5(json_encode([$p['faculty_names'] ?? null, $p['specialty_names'] ?? null, $p['specialty_name'] ?? null, $p['courses'] ?? null, $p['course'] ?? null])),
        };

        return mb_substr($route . '#' . md5($short), 0, 191);
    }

    private function requesterKafedra($requester): ?string
    {
        if (!$requester) {
            return null;
        }
        $deptId = $requester->head_department_hemis_id ?? null ?: ($requester->department_hemis_id ?? null);
        $name = $deptId ? \App\Models\Department::where('department_hemis_id', $deptId)->value('name') : null;

        return $name ?: ($requester->department ?? null);
    }

    // ─── Xabarlar ──────────────────────────────────────────────────

    private function notifyRegistrars(TimetableChangeRequest $req): void
    {
        $title = 'Dars jadvali o\'zgarishi — tasdiq kutilmoqda';
        $body = ($req->kafedra_name ? $req->kafedra_name . "\n" : '') . ($req->requester_name ? $req->requester_name . ":\n" : '') . $req->summary;
        $id = $req->id;
        dispatch(function () use ($title, $body, $id) {
            $svc = app(self::class);
            $req = TimetableChangeRequest::find($id);
            if (!$req || $req->status !== TimetableChangeRequest::PENDING || $req->approval_stage === 'department') {
                return;
            }
            $svc->teachersWithRoles([\App\Enums\ProjectRole::REGISTRAR_OFFICE->value])
                ->each(fn (Teacher $t) => $svc->sendToTeacher($t, $title, $body, $req));
        })->afterResponse();
    }

    public function notifyApproved(TimetableChangeRequest $req): void
    {
        $details = $req->details ?? [];
        $title = 'Dars jadvali o\'zgardi';
        $body = $req->summary . "\n(Registrator ofisi tasdiqladi" . ($req->reviewer_name ? ': ' . $req->reviewer_name : '') . ')';
        $notified = [];
        $send = function (Teacher $t, ?string $customTitle = null) use (&$notified, $title, $body, $req) {
            if (isset($notified[$t->id])) {
                return;
            }
            $notified[$t->id] = true;
            $this->sendToTeacher($t, $customTitle ?? $title, $body, $req);
        };

        // Kafedra mudiri — natija
        if ($req->requester instanceof Teacher) {
            $send($req->requester, 'Dars jadvali o\'zgarishi tasdiqlandi');
        }
        if ($req->head_reviewer_id && ($head = Teacher::find($req->head_reviewer_id))) {
            $send($head);
        }
        // O'qituvchi(lar)
        $teacherIds = array_unique(array_merge((array) ($details['teacher_ids'] ?? []), array_filter([(int) ($details['new_teacher_id'] ?? 0)])));
        if ($teacherIds) {
            Teacher::whereIn('id', $teacherIds)->get()->each(fn (Teacher $t) => $send($t));
        }
        // O'quv bo'limi
        $this->teachersWithRoles([
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT->value,
            \App\Enums\ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
        ])->each(fn (Teacher $t) => $send($t));

        // Guruh talabalari
        $students = $this->studentsOfGroups((array) ($details['groups'] ?? []), (array) ($details['faculties'] ?? []));
        $facultyIds = $students->pluck('department_id')->filter()->map(fn ($v) => (int) $v)->unique()->all();
        // Dekanat — talabalar fakultetining dekani
        if ($facultyIds) {
            $this->teachersWithRoles([\App\Enums\ProjectRole::DEAN->value])
                ->filter(fn (Teacher $t) => (bool) array_intersect($facultyIds, array_map('intval', (array) $t->deanFacultyIds)))
                ->each(fn (Teacher $t) => $send($t));
        }

        $link = Route::has('student.schedule') ? route('student.schedule') : null;
        $telegram = new TelegramService();
        foreach ($students as $st) {
            try {
                StudentNotification::create([
                    'student_id' => $st->id,
                    'type' => 'timetable_change',
                    'title' => 'Dars jadvalingiz o\'zgardi',
                    'message' => $req->summary,
                    'link' => $link,
                    'data' => ['timetable_change_request_id' => $req->id],
                ]);
            } catch (\Throwable $e) {
                Log::warning('[TimetableChange] StudentNotification: ' . $e->getMessage());
            }
            if (!empty($st->telegram_chat_id) && !empty($st->telegram_verified_at)) {
                $telegram->sendToUser((string) $st->telegram_chat_id,
                    '<b>📅 Dars jadvalingiz o\'zgardi</b>' . "\n\n" . e($req->summary));
            }
        }
    }

    /** Faol xodimlar shu rollar bo'yicha (bazada yo'q rol xato bermaydi). */
    public function teachersWithRoles(array $roles)
    {
        $existing = \Spatie\Permission\Models\Role::whereIn('name', $roles)->pluck('name')->unique()->values()->all();

        return $existing ? Teacher::role($existing)->where('status', true)->get() : collect();
    }

    /**
     * Guruhlarning faol talabalari. Bir xil nomli guruh boshqa fakultetda ham bo'lishi
     * mumkin — kartaning fakulteti ma'lum bo'lsa, shu fakultet talabalari olinadi.
     */
    private function studentsOfGroups(array $groups, array $faculties = [])
    {
        $bases = [];
        foreach ($groups as $g) {
            $base = trim((string) preg_replace('/\s*\(.*$/u', '', (string) $g));
            if ($base !== '') {
                $bases[$base] = true;
            }
        }
        if (!$bases) {
            return collect();
        }

        $query = Student::where('student_status_code', 11)
            ->where(function ($q) use ($bases) {
                foreach (array_keys($bases) as $b) {
                    $q->orWhere('group_name', $b)->orWhere('group_name', 'like', $b . ' (%');
                }
            });
        $faculties = array_values(array_filter($faculties, fn ($f) => trim((string) $f) !== ''));
        if ($faculties) {
            $query->whereIn('department_name', $faculties);
        }

        return $query->get(['id', 'department_id', 'telegram_chat_id', 'telegram_verified_at', 'group_name']);
    }

    public function sendToTeacher(Teacher $teacher, string $title, string $message, TimetableChangeRequest $req): void
    {
        $link = Route::has('admin.timetable.index') ? route('admin.timetable.index') . '?board=' . $req->board_id . '&change_request=' . $req->id : null;
        try {
            TeacherNotification::create([
                'teacher_id' => $teacher->id,
                'type' => 'timetable_change',
                'title' => $title,
                'message' => $message,
                'link' => $link,
                'data' => ['timetable_change_request_id' => $req->id],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TimetableChange] TeacherNotification: ' . $e->getMessage());
        }
        try {
            Notification::create([
                'recipient_id' => $teacher->id,
                'recipient_type' => Teacher::class,
                'subject' => $title,
                'body' => $message,
                'type' => 'timetable_change',
                'url' => $link,
                'data' => ['timetable_change_request_id' => $req->id],
                'is_read' => false,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TimetableChange] Notification: ' . $e->getMessage());
        }
        if (!empty($teacher->telegram_chat_id)) {
            (new TelegramService())->sendToUser((string) $teacher->telegram_chat_id,
                '<b>' . e($title) . '</b>' . "\n\n" . e($message));
        }
    }
}

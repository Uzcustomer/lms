<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableChangeRequest;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Takliflar"ni qo'llash huquqi va bajarilishi.
 *
 * Huquq foydalanuvchining BARCHA rollaridan eng kengi bo'yicha (suggestionRights): admin, superadmin, registrator ofisi,
 * kichik admin — hammasi, darhol; kafedra mudiri — o'z kafedrasi fanlari, so'rov → registrator ofisi; fan mas'uli — mas'ul
 * fanlari (shu fanlarning barcha o'qituvchilari), o'qituvchi — o'z darslari, so'rov → kafedra mudiri → registrator ofisi;
 * o'quv bo'limi — hammasini ko'radi, qo'llamaydi.
 *
 * Taklif yaxlit: barcha amallari bitta tranzaksiyada (runSuggestionActions) — yo hammasi, yo hech biri; so'rov ham bitta.
 */
trait TimetableSuggestionApply
{
    /** Taklif amallarini yaxlit bajaruvchi route (tasdiqlangan so'rov ham shu bilan qayta bajariladi). */
    public const SUGGESTION_ROUTE = 'admin.timetable.week-suggestions.apply';

    private const SUGGESTION_ADMIN_ROLES = ['superadmin', 'admin', 'registrator_ofisi', 'kichik_admin'];

    /**
     * @return array{level: ?string, view: bool, tid: int, kafedra: ?array, keys: array}
     *   level: admin | kafedra | teacher | null (qo'llay olmaydi); view — hammasini ko'radi (admin, o'quv bo'limi).
     */
    public function suggestionRights($actor): array
    {
        $roles = $actor && method_exists($actor, 'getRoleNames') ? $actor->getRoleNames()->all() : [];
        $admin = (bool) array_intersect($roles, self::SUGGESTION_ADMIN_ROLES);
        $tid = $actor instanceof Teacher ? (int) $actor->id : 0;
        $kafedra = !$admin && $tid > 0 && in_array('kafedra_mudiri', $roles, true) ? $this->headKafedraNamesFor($actor) : null;
        $keys = [];
        if (!$admin && $tid > 0) {
            try {
                $keys = $this->responsibleSubjectKeysFor($tid);
            } catch (\Throwable $e) {
                $keys = [];
            }
        }
        $teacher = $tid > 0 && ($keys || array_intersect($roles, ['oqituvchi', 'fan_masuli', 'kafedra_mudiri']));

        return [
            'level' => $admin ? 'admin' : ($kafedra ? 'kafedra' : ($teacher ? 'teacher' : null)),
            'view' => $admin || (bool) array_intersect($roles, ['oquv_bolimi', 'oquv_bolimi_boshligi']),
            'tid' => $tid,
            'kafedra' => $kafedra,
            'keys' => $keys,
        ];
    }

    /** Kafedra mudirining kafedra nomlari (faol roldan qat'i nazar; departmentHeadKafedra bilan bir xil manba). */
    private function headKafedraNamesFor(Teacher $t): ?array
    {
        $id = $t->head_department_hemis_id ?: ($t->department_hemis_id ?? null);
        $dept = $id ? Department::where('department_hemis_id', $id)->first() : null;
        if (!$dept && trim((string) $t->department) !== '' && !$t->head_department_hemis_id) {
            $dept = Department::where('name', trim((string) $t->department))->where('structure_type_code', '!=', 11)->first();
        }
        if (!$dept) {
            return null;
        }
        $names = DB::table('curriculum_subjects')->where('department_id', $dept->department_hemis_id)
            ->whereNotNull('department_name')->where('department_name', '!=', '')->distinct()->pluck('department_name')->all();
        $names[] = (string) $dept->name;

        return array_values(array_unique($names));
    }

    /** Kartaga eng keng huquq: admin | kafedra | teacher | null. */
    private function suggestionCardRight(array $r, $card): ?string
    {
        if ($r['level'] === 'admin') {
            return 'admin';
        }
        if ($r['kafedra'] && $this->sameKafedra($card->kafedra_name, $r['kafedra'])) {
            return 'kafedra';
        }
        if ($r['tid'] > 0 && ((int) $card->teacher_id === $r['tid'] || $this->responsibleMatch($r['keys'], $card))) {
            return 'teacher';
        }

        return null;
    }

    /** Taklif kartasi foydalanuvchi qamrovidami (oynada ko'rinadimi). */
    public function suggestionInScope(array $r, $card): bool
    {
        return $r['view'] || $this->suggestionCardRight($r, $card) !== null;
    }

    /** Taklif amallari: validatsiya (so'rovdan). */
    public function suggestionPayload(Request $request): array
    {
        return $request->validate([
            'week' => 'required|integer|min:1|max:60',
            'card_id' => 'required|integer',
            'type' => 'nullable|string|max:20',
            'title' => 'nullable|string|max:500',
            'actions' => 'required|array|min:1|max:20',
            'actions.*.op' => 'required|in:move,reset,cancel,teacher',
            'actions.*.card_id' => 'required|integer',
            'actions.*.week' => 'nullable|integer|min:1|max:60',
            'actions.*.day' => 'nullable|integer|min:1|max:10',
            'actions.*.pair' => 'nullable|integer|min:1|max:24',
            'actions.*.auditorium_code' => 'nullable|string|max:50',
            'actions.*.teacher_id' => 'nullable|integer',
        ]);
    }

    /**
     * Taklifni shu foydalanuvchi qo'llay oladimi: qamrov (har amal kartasi), o'qituvchi almashtirish va ma'ruzani surish —
     * faqat kafedra mudiri va adminlarga, bitta kafedra, sana (eski va yangi — kamida ertaga), kartada kutilayotgan so'rov.
     * $cardOf / $ovOf — xotiradagi holat (takliflar hisobida), aks holda bazadan.
     * @return array{mode: ?string, why: ?string, code: int} mode: direct | registrar | department
     */
    public function suggestionApplyCheck(array $r, TimetableBoard $board, array $p, ?\Closure $cardOf = null, ?\Closure $ovOf = null,
        bool $dates = true, ?array $pendingCards = null, array $ignoreIds = []): array
    {
        $no = fn (string $why, int $code = 403) => ['mode' => null, 'why' => $why, 'code' => $code];
        if ($r['level'] === null) {
            return $no($r['view'] ? "O'quv bo'limi takliflarni faqat ko'radi." : "Takliflarni qo'llash huquqingiz yo'q.");
        }
        $cardOf ??= fn (int $id) => TimetableCard::where('board_id', $board->id)->find($id);
        $ovOf ??= fn (int $id, int $w) => TimetableCardOverride::where('card_id', $id)->where('week', $w)->first();
        $acts = array_values((array) ($p['actions'] ?? []));
        $primary = $cardOf((int) ($p['card_id'] ?? 0));
        if (!$primary || !$acts) {
            return $no('Taklifdagi dars topilmadi.', 422);
        }
        $teacherLevel = false;
        $cards = [];
        foreach ($acts as $a) {
            $c = $cardOf((int) ($a['card_id'] ?? 0));
            if (!$c) {
                return $no('Taklifdagi dars topilmadi.', 422);
            }
            $cards[(int) $c->id] = $c;
            $right = $this->suggestionCardRight($r, $c);
            $label = '«' . $c->subject_name . '»' . ($c->teacher_name ? ' (' . $c->teacher_name . ')' : '');
            if ($right === null) {
                return $no(!$this->sameKafedra($c->kafedra_name, [(string) $primary->kafedra_name]) ? "Boshqa kafedra fani: {$label}."
                    : ((int) $c->id !== (int) $primary->id ? "Boshqa o'qituvchining darsini surish kerak: {$label} — qamrovingizdan tashqarida."
                    : "Qamrovingizdan tashqaridagi dars: {$label}."));
            }
            if ($right === 'teacher') {
                $teacherLevel = true;
                if (($a['op'] ?? '') === 'teacher') {
                    return $no("O'qituvchini almashtirish — faqat kafedra mudiri va adminlar.");
                }
                if ($c->training_type === 'lecture') {
                    return $no("Ma'ruzani surish — faqat kafedra mudiri va adminlar: {$label}.");
                }
            }
        }
        if ($teacherLevel) {
            foreach ($cards as $c) {
                if (!$this->sameKafedra($c->kafedra_name, [(string) $primary->kafedra_name])) {
                    return $no('Boshqa kafedra fani: «' . $c->subject_name . '» (' . ($c->kafedra_name ?: '—') . ').');
                }
            }
        }
        if ($dates && ($why = $this->suggestionExpiredWith($board, $acts, $cardOf, $ovOf))) {
            return $no($why, 422);
        }
        if ($r['level'] !== 'admin') {
            $pending = $pendingCards ?? $this->pendingCardRequests($board->id, $ignoreIds);
            foreach (array_keys($cards) as $id) {
                if (isset($pending[$id])) {
                    return $no("Bu kartaga allaqachon so'rov bor (#{$pending[$id]['id']}, {$pending[$id]['stage']}): «" . $cards[$id]->subject_name . '».', 422);
                }
            }
        }

        return ['mode' => $r['level'] === 'admin' ? 'direct' : ($teacherLevel ? 'department' : 'registrar'), 'why' => null, 'code' => 200];
    }

    /** Doskadagi kutilayotgan so'rovlar tegadigan kartalar: [card_id => ['id', 'stage']]. */
    public function pendingCardRequests(int $boardId, array $ignoreIds = []): array
    {
        if (!TimetableChangeApproval::tableReady()) {
            return [];
        }
        $out = [];
        foreach (TimetableChangeRequest::where('board_id', $boardId)->where('status', TimetableChangeRequest::PENDING)
            ->when($ignoreIds, fn ($q) => $q->whereNotIn('id', $ignoreIds))->get(['id', 'details', 'approval_stage']) as $req) {
            foreach ((array) (($req->details ?? [])['card_ids'] ?? []) as $cid) {
                $out[(int) $cid] ??= ['id' => (int) $req->id,
                    'stage' => $req->approval_stage === 'department' ? 'kafedra mudiri tasdig‘ida' : 'registrator ofisi tasdig‘ida'];
            }
        }

        return $out;
    }

    /** Taklif tegadigan darslar sanalari (eski — hozirgi joyi, yangi — ko'chiriladigan kun); bugun yoki o'tgan bo'lsa — sababi. */
    private function suggestionExpiredWith(TimetableBoard $board, array $acts, \Closure $cardOf, \Closure $ovOf): ?string
    {
        $today = self::proposalToday();
        foreach ($acts as $a) {
            $c = $cardOf((int) ($a['card_id'] ?? 0));
            $w = (int) ($a['week'] ?? 0);
            if (!$c || $w < 1) {
                continue;
            }
            $monday = $this->weekStartDate($board, $c, $w);
            $ov = $ovOf((int) $c->id, $w);
            $cur = $ov ? ($ov->cancelled || !$ov->day || !$ov->pair ? 0 : (int) $ov->day) : ($c->day && $c->pair ? (int) $c->day : 0);
            $days = array_filter([$cur, ($a['op'] ?? '') === 'move' ? (int) ($a['day'] ?? 0) : 0,
                ($a['op'] ?? '') === 'reset' && $c->day && $c->pair ? (int) $c->day : 0]);
            foreach ($days as $d) {
                $date = $monday->copy()->addDays($d - 1)->toDateString();
                if ($date <= $today) {
                    return "Bugungi yoki o'tgan kunga tegadi (" . Carbon::parse($date)->format('d.m.Y') . ') — taklifni qo‘llab bo‘lmaydi.';
                }
            }
        }

        return null;
    }

    public function suggestionExpired(TimetableBoard $board, array $p): ?string
    {
        return $this->suggestionExpiredWith($board, array_values((array) ($p['actions'] ?? [])),
            fn (int $id) => TimetableCard::where('board_id', $board->id)->find($id),
            fn (int $id, int $w) => TimetableCardOverride::where('card_id', $id)->where('week', $w)->first());
    }

    /** Taklif tegadigan kartalar (amallar tartibida, takrorsiz). */
    public function suggestionCards(TimetableBoard $board, array $p)
    {
        $ids = array_values(array_unique(array_map(fn ($a) => (int) ($a['card_id'] ?? 0), (array) ($p['actions'] ?? []))));

        return TimetableCard::where('board_id', $board->id)->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search((int) $c->id, $ids, true))->values();
    }

    /**
     * Amallarni bajaradi (chaqiruvchining tranzaksiyasi ichida; huquq tekshirilmaydi). Har ko'chirish shu haftadagi guruh,
     * o'qituvchi va xona to'qnashuviga tekshiriladi; oxirida yakuniy holat — amaliy blok bo'linmasligi (eski va yangi kun),
     * sikl darslari bilan bandlik (unit-busy) va almashgan o'qituvchining boshqa haftalari. Xato — matni, aks holda null.
     */
    private function runSuggestionActions(TimetableBoard $board, array $acts): ?string
    {
        $moved = [];
        $days = [];
        $teacherChanged = [];
        foreach ($acts as $a) {
            $card = TimetableCard::where('board_id', $board->id)->find((int) ($a['card_id'] ?? 0));
            if (!$card) {
                return 'Taklifdagi dars topilmadi.';
            }
            $w = (int) ($a['week'] ?? 0);
            $op = (string) ($a['op'] ?? '');
            if ($op !== 'teacher' && ($w < 1 || $w > $this->cardGrid($board, $card)['weeks'])) {
                return 'Bu dasturda ushbu o‘quv haftasi yo‘q.';
            }
            if ($op !== 'teacher' && ($cur = $this->suggestionPlace($card, $w))) {
                $days[$card->id . '|' . $w][$cur[0]] = true;
            }
            if ($op === 'reset') {
                TimetableCardOverride::where('card_id', $card->id)->where('week', $w)->delete();
                $moved[$card->id . '|' . $w] = [$card, $w];
            } elseif ($op === 'cancel') {
                TimetableCardOverride::updateOrCreate(['card_id' => $card->id, 'week' => $w],
                    ['day' => null, 'pair' => null, 'cancelled' => true, 'auditorium_code' => null, 'auditorium_name' => null]);
            } elseif ($op === 'move') {
                $day = (int) ($a['day'] ?? 0);
                $pair = (int) ($a['pair'] ?? 0);
                if (!$day || !$pair) {
                    return 'Kun va para ko‘rsatilishi kerak.';
                }
                $room = null;
                if (!empty($a['auditorium_code'])) {
                    $room = Auditorium::where('code', $a['auditorium_code'])->first();
                    if (!$room) {
                        return 'Auditoriya topilmadi.';
                    }
                    if (!$this->auditoriumAllowedForCard($room, $card, $this->auditoriumTeacherMap())) {
                        return 'Bu auditoriya tanlangan o‘qituvchiga biriktirilmagan.';
                    }
                }
                if ($conflicts = $this->findWeekConflicts($card, $w, $day, $pair, 0, null, $room?->code)) {
                    return '«' . $card->subject_name . '»: ' . implode(' · ', $conflicts);
                }
                TimetableCardOverride::updateOrCreate(['card_id' => $card->id, 'week' => $w],
                    ['day' => $day, 'pair' => $pair, 'start_half' => 0, 'cancelled' => false]
                        + ($room ? ['auditorium_code' => $room->code, 'auditorium_name' => $room->name] : []));
                $moved[$card->id . '|' . $w] = [$card, $w];
            } elseif ($op === 'teacher') {
                $t = Teacher::find((int) ($a['teacher_id'] ?? 0));
                if (!$t) {
                    return 'O‘qituvchi topilmadi.';
                }
                $card->update(['teacher_id' => $t->id, 'teacher_name' => $t->short_name ?: $t->full_name]);
                // Saqlangan biriktiruv ham (updateCard kabi) — keyingi rejadan sinxronlash eski o'qituvchini qaytarmasin.
                $this->recordUnitAssignment($card);
                $teacherChanged[$card->id] = $card;
            }
        }
        foreach ($moved as $key => [$card, $w]) {
            $card->refresh();
            $card->setRelation('board', $board);
            if (!($pl = $this->suggestionPlace($card, $w))) {
                continue;
            }
            [$d, $p] = $pl;
            $days[$key][$d] = true;
            $date = $this->weekStartDate($board, $card, $w)->addDays($d - 1)->toDateString();
            if ($errs = $this->weeklyProposalBusy($card, $w, $d, $p, $date)) {
                return '«' . $card->subject_name . '»: ' . implode(' · ', $errs);
            }
        }
        foreach ($moved as $key => [$card, $w]) {
            if ($errs = $this->practiceDaySplits($card, $w, array_keys($days[$key] ?? []))) {
                return implode(' · ', $errs);
            }
        }
        foreach ($teacherChanged as $card) {
            $card->refresh();
            $card->setRelation('board', $board);
            for ($w = 1; $w <= (int) $this->cardGrid($board, $card)['weeks']; $w++) {
                if (($pl = $this->suggestionPlace($card, $w)) && ($c = $this->findWeekConflicts($card, $w, $pl[0], $pl[1]))) {
                    return '«' . $card->subject_name . '» (' . $w . '-hafta): ' . implode(' · ', $c);
                }
            }
        }

        return null;
    }

    /** Kartaning shu haftadagi joyi [kun, para] (ko'chirilgan yoki shablon), o'tilmasa/joysiz — null. */
    private function suggestionPlace(TimetableCard $card, int $w): ?array
    {
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $w)->first();
        if ($ov) {
            return $ov->cancelled || !$ov->day || !$ov->pair ? null : [(int) $ov->day, (int) $ov->pair];
        }

        return $card->day && $card->pair ? [(int) $card->day, (int) $card->pair] : null;
    }

    /**
     * Yakuniy holatda bir kunda bir fanning (bo'lak harfisiz) shu guruh amaliy soatlari bitta yaxlit blokmi — berilgan kunlarda
     * (kartaning eski va yangi kuni). Bo'lingan bo'lsa — sababi.
     */
    private function practiceDaySplits(TimetableCard $card, int $w, array $days): array
    {
        if ($card->training_type === 'lecture' || !$days) {
            return [];
        }
        $base = fn (TimetableCard $c) => $this->normSubject((string) ($this->cycleSubjectVariantBase((string) $c->subject_name) ?? $c->subject_name));
        $groups = fn (TimetableCard $c) => collect($c->occupiedGroups())->map(fn ($g) => $this->groupNameKey((string) $g))->sort()->values()->all();
        $sibs = TimetableCard::where('board_id', $card->board_id)->where('training_type', '!=', 'lecture')
            ->where('faculty_name', $card->faculty_name)->where('specialty_name', $card->specialty_name)->where('course', $card->course)->get()
            ->filter(fn (TimetableCard $o) => $base($o) === $base($card) && $groups($o) === $groups($card));
        $errors = [];
        foreach ($days as $d) {
            $ranges = [];
            foreach ($sibs as $o) {
                $ov = TimetableCardOverride::where('card_id', $o->id)->where('week', $w)->first();
                [$od, $op, $sh] = $ov ? ($ov->cancelled || !$ov->day || !$ov->pair ? [0, 0, 0] : [(int) $ov->day, (int) $ov->pair, (int) $ov->start_half])
                    : [(int) $o->day, (int) $o->pair, (int) $o->start_half];
                if ($od === (int) $d && $op) {
                    $ranges[] = $this->rangeFor($o, $op, $sh);
                }
            }
            usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
            for ($i = 1; $i < count($ranges); $i++) {
                if ($ranges[$i][0] > max(array_column(array_slice($ranges, 0, $i), 1))) {
                    $errors[] = '«' . $card->subject_name . "»: bir kunda bir fandan amaliy soatlar bo'linadi — blok yaxlit qolishi kerak.";
                    break;
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /** Darsning shu haftadagi tavsifi: kun, sana, vaqt, xona, o'qituvchi (talabalar xabari uchun). */
    private function suggestionSlot(TimetableBoard $board, TimetableCard $card, int $w): array
    {
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $w)->first();
        $pl = $this->suggestionPlace($card, $w);
        $room = $ov && $ov->auditorium_code ? ($ov->auditorium_name ?: $ov->auditorium_code) : ($card->auditorium_name ?: $card->auditorium_code);
        if (!$pl) {
            return ['key' => 'none', 'text' => 'o‘tilmaydi', 'room' => (string) $room, 'teacher' => (string) $card->teacher_name];
        }
        $names = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $date = $this->weekStartDate($board, $card, $w)->addDays($pl[0] - 1);

        return ['key' => $pl[0] . '|' . $pl[1] . '|' . $room . '|' . $card->teacher_id,
            'text' => ($names[$pl[0] - 1] ?? $pl[0] . '-kun') . ', ' . $date->format('d.m.Y') . ' ' . $this->halfRangeTime($board, $this->rangeFor($card, $pl[1], 0)),
            'room' => (string) $room, 'teacher' => (string) $card->teacher_name];
    }

    /**
     * Sinov bajarish (rollback): o'tsa — o'zgaradigan darslar tavsifi (talabalar xabari: fan, o'qituvchi, eski → yangi kun, vaqt,
     * xona) va guruhlari; o'tmasa — ValidationException (sababi bilan).
     * @return array{notices: array, teacher_ids: int[]}
     */
    public function suggestionDryRun(TimetableBoard $board, array $p): array
    {
        $acts = array_map(fn ($a) => $a + ['week' => (int) ($p['week'] ?? 0)], array_values((array) ($p['actions'] ?? [])));
        $cards = $this->suggestionCards($board, $p);
        $weeks = [];
        foreach ($acts as $a) {
            $weeks[(int) $a['card_id']][(int) $a['week']] = true;
        }
        $snap = function () use ($board, $cards, $weeks) {
            $out = [];
            foreach ($cards as $c) {
                $c = TimetableCard::find($c->id);
                foreach (array_keys($weeks[(int) $c->id]) as $w) {
                    $out[$c->id . '|' . $w] = $this->suggestionSlot($board, $c, max(1, $w)) + ['card' => $c];
                }
            }

            return $out;
        };
        $before = $snap();
        DB::beginTransaction();
        try {
            $error = $this->runSuggestionActions($board, $acts);
            $after = $error === null ? $snap() : [];
        } finally {
            DB::rollBack();
        }
        if ($error !== null) {
            throw ValidationException::withMessages(['actions' => $error]);
        }
        $notices = [];
        $teachers = [];
        foreach ($before as $k => $b) {
            $x = $after[$k];
            $teachers[(int) $b['card']->teacher_id] = true;
            $teachers[(int) $x['card']->teacher_id] = true;
            if ($b['key'] === $x['key']) {
                continue;
            }
            $c = $x['card'];
            $notices[] = ['card_id' => (int) $c->id, 'groups' => array_values($this->changeRequestCardGroups($c)),
                'faculties' => array_values(array_filter([(string) $c->faculty_name])),
                'subject' => (string) $c->subject_name, 'teacher' => $x['teacher'] ?: $b['teacher'],
                'old' => $b['text'] . ($b['room'] !== '' ? ', xona ' . $b['room'] : ''), 'new' => $x['text'] . ($x['room'] !== '' ? ', xona ' . $x['room'] : ''),
                'room' => $x['room']];
        }

        return ['notices' => $notices, 'teacher_ids' => array_values(array_filter(array_keys($teachers)))];
    }

    /** Taklif so'rovi imzosi: tegadigan kartalar va ularning shu haftalardagi joyi o'zgarsa — tasdiqda qo'llanmaydi. */
    public function suggestionSignature(TimetableBoard $board, array $p): string
    {
        $weeks = array_values(array_unique(array_map(fn ($a) => (int) ($a['week'] ?? 0), (array) ($p['actions'] ?? []))));
        $cards = $this->suggestionCards($board, $p);

        return hash('sha256', json_encode($cards->map(fn (TimetableCard $c) => [$c->only(['id', 'day', 'pair', 'start_half', 'teacher_id',
            'auditorium_code', 'len_half', 'kafedra_name', 'subject_name', 'group_name', 'group_names']),
            TimetableCardOverride::where('card_id', $c->id)->whereIn('week', $weeks)->orderBy('week')
                ->get(['week', 'day', 'pair', 'start_half', 'cancelled', 'auditorium_code'])->toArray()])->all()));
    }

    /**
     * Taklifni yaxlit qo'llash (admin — darhol; tasdiqlangan so'rov — kafedra mudiri nomidan qayta bajariladi). Yo hamma amal,
     * yo hech biri: xato bo'lsa hammasi bekor (rollback) va sababi qaytadi.
     */
    public function weekSuggestionApply(Request $request, TimetableBoard $board)
    {
        // Darhol — admin huquqi (barcha rollaridan, takliflar oynasidagidek); tasdiqlangan so'rovni qayta bajarish — applyLocked belgisi bilan.
        if (!$request->attributes->has('timetable.change_replay')
            && $this->suggestionRights($this->timetableActor($request))['level'] !== 'admin') {
            abort(403, 'Taklifni darhol qo‘llash faqat adminlar uchun — boshqa rollar so‘rov yuboradi.');
        }
        $data = $this->suggestionPayload($request);
        $acts = array_map(fn ($a) => $a + ['week' => (int) $data['week']], array_values($data['actions']));
        if ($why = $this->suggestionExpired($board, ['actions' => $acts])) {
            return response()->json(['error' => $why], 422);
        }
        $error = null;
        try {
            DB::transaction(function () use ($board, $acts, &$error) {
                if ($error = $this->runSuggestionActions($board, $acts)) {
                    throw new \RuntimeException('suggestion-failed');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'suggestion-failed') {
                throw $e;
            }
        }

        return $error !== null ? response()->json(['error' => $error], 422) : response()->json(['ok' => true]);
    }
}

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
 * o'quv bo'limi — jadval administratori (SUGGESTION_ADMIN_ROLES): ko'radi va qo'llaydi.
 *
 * Taklif yaxlit: barcha amallari bitta tranzaksiyada (runSuggestionActions) — yo hammasi, yo hech biri; so'rov ham bitta.
 */
trait TimetableSuggestionApply
{
    /** Taklif amallarini yaxlit bajaruvchi route (tasdiqlangan so'rov ham shu bilan qayta bajariladi). */
    public const SUGGESTION_ROUTE = 'admin.timetable.week-suggestions.apply';

    private const SUGGESTION_ADMIN_ROLES = \App\Http\Controllers\Admin\TimetableController::TIMETABLE_FULL_EDIT_ROLES;

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
        bool $dates = true, ?array $pendingCards = null, array $ignoreIds = [], bool $staffToday = false): array
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
        // Bugun: admin darhol qo'llaganda (faol rol — admin / superadmin / registrator ofisi) ochiq; so'rov (propose) — avvalgidek yopiq.
        if ($dates && ($why = $this->suggestionExpiredWith($board, $acts, $cardOf, $ovOf, $staffToday && $r['level'] === 'admin'))) {
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

    /**
     * Xona shu kartaga yaroqlimi (faol, talabalar sig'adi) — muammo matni yoki null. Sig'im: umumiy raqamsiz xona (isSharedRoom) va bandlik
     * tekshirilmaydigan karta (isNonBlocking) uchun tekshirilmaydi; sig'imi noma'lum (0) xona — tekshirilmaydi.
     */
    public function suggestionRoomProblem(TimetableBoard $board, TimetableCard $card, Auditorium $room): ?string
    {
        $name = (string) ($room->name ?: $room->code);
        if (!$room->active) {
            return 'xona nofaol — boshqa xona tanlang (' . $name . ').';
        }
        $students = $this->cardStudents($card);
        if (!$this->isNonBlocking($card, $board) && !$this->isSharedRoom((string) $room->name) && (int) $room->volume > 0 && $students > (int) $room->volume) {
            return self::capacityLine($name, (int) $room->volume, $students);
        }

        return null;
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

    /**
     * Taklif tegadigan darslar sanalari: har (karta, hafta) uchun boshidagi joyi va barcha amallardan keyingi yakuniy joyi (oraliq
     * "reset" — blokni qayta qo'yishdan oldingi bo'shatish — haqiqiy joy emas). Bugun yoki o'tgan bo'lsa — sababi. $staff — admin /
     * registrator ofisi darhol qo'llayapti (yoki tasdiqlayapti): bugun ochiq, faqat o'tgan kun rad.
     */
    private function suggestionExpiredWith(TimetableBoard $board, array $acts, \Closure $cardOf, \Closure $ovOf, bool $staff = false): ?string
    {
        $today = self::proposalToday();
        $orig = [];
        $final = [];
        foreach ($acts as $a) {
            $c = $cardOf((int) ($a['card_id'] ?? 0));
            $w = (int) ($a['week'] ?? 0);
            if (!$c || $w < 1) {
                continue;
            }
            $k = $c->id . '|' . $w;
            if (!isset($orig[$k])) {
                $ov = $ovOf((int) $c->id, $w);
                $cur = $ov ? ($ov->cancelled || !$ov->day || !$ov->pair ? 0 : (int) $ov->day) : ($c->day && $c->pair ? (int) $c->day : 0);
                $orig[$k] = [$c, $w, $cur];
                $final[$k] = $cur;
            }
            $final[$k] = match ($a['op'] ?? '') {
                'move' => (int) ($a['day'] ?? 0),
                'reset' => $c->day && $c->pair ? (int) $c->day : 0,
                'cancel' => 0,
                default => $final[$k],
            };
        }
        foreach ($orig as $k => [$c, $w, $cur]) {
            $monday = $this->weekStartDate($board, $c, $w);
            foreach (array_unique(array_filter([$cur, $final[$k]])) as $d) {
                $date = $monday->copy()->addDays($d - 1)->toDateString();
                if (self::pastFor($staff, $date)) {
                    return ($staff ? "O'tgan kunga tegadi (" : "Bugungi yoki o'tgan kunga tegadi (") . Carbon::parse($date)->format('d.m.Y')
                        . ') — taklifni qo‘llab bo‘lmaydi.' . (!$staff && $date === $today ? ' ' . self::todayStaffOnlyText() : '');
                }
            }
        }

        return null;
    }

    public function suggestionExpired(TimetableBoard $board, array $p, bool $staff = false): ?string
    {
        return $this->suggestionExpiredWith($board, array_values((array) ($p['actions'] ?? [])),
            fn (int $id) => TimetableCard::where('board_id', $board->id)->find($id),
            fn (int $id, int $w) => TimetableCardOverride::where('card_id', $id)->where('week', $w)->first(), $staff);
    }

    /** Taklif amallari bugungi kunga tegadimi (eski yoki yakuniy joyi) — tarix belgisi va HEMIS eslatmasi uchun. */
    private function suggestionTouchesToday(TimetableBoard $board, array $acts): bool
    {
        $today = self::proposalToday();
        foreach ($acts as $a) {
            $c = TimetableCard::where('board_id', $board->id)->find((int) ($a['card_id'] ?? 0));
            $w = (int) ($a['week'] ?? 0);
            if (!$c || $w < 1) {
                continue;
            }
            $monday = $this->weekStartDate($board, $c, $w);
            foreach (array_filter([($this->suggestionPlace($c, $w) ?? [0])[0], ($a['op'] ?? '') === 'move' ? (int) ($a['day'] ?? 0) : 0]) as $d) {
                if ($monday->copy()->addDays($d - 1)->toDateString() === $today) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Har (karta, hafta) uchun yakuniy joy: move — yangi kun / para / xona; reset — shablon joyi (shablonda joysiz — null);
     * cancel — null. Oraliq "reset" (keyin move) hisobga olinmaydi. Bron va tekshiruv shu joy bo'yicha.
     * @return array<string, ?array{card_id: int, week: int, day: int, pair: int, auditorium_code: ?string}>
     */
    public function suggestionFinalPlaces(array $acts, int $defaultWeek = 0): array
    {
        $out = [];
        foreach ($acts as $a) {
            $op = (string) ($a['op'] ?? '');
            if (!in_array($op, ['move', 'reset', 'cancel'], true)) {
                continue;
            }
            $cid = (int) ($a['card_id'] ?? 0);
            $w = (int) ($a['week'] ?? $defaultWeek);
            $k = $cid . '|' . $w;
            if ($op === 'move') {
                $out[$k] = ['card_id' => $cid, 'week' => $w, 'day' => (int) ($a['day'] ?? 0), 'pair' => (int) ($a['pair'] ?? 0),
                    'auditorium_code' => $a['auditorium_code'] ?? null];
            } elseif ($op === 'reset') {
                $c = TimetableCard::find($cid);
                // Xona ham shablondagisi (haftalik yozuvdagi xona o'chadi; shablonda xona yo'q bo'lsa — xonasiz).
                $out[$k] = $c && $c->day && $c->pair ? ['card_id' => $cid, 'week' => $w, 'day' => (int) $c->day, 'pair' => (int) $c->pair,
                    'auditorium_code' => (string) $c->auditorium_code, 'room_fixed' => true] : null;
            } else {
                $out[$k] = null;
            }
        }

        return $out;
    }

    /** Taklif tegadigan kartalar (amallar tartibida, takrorsiz). */
    public function suggestionCards(TimetableBoard $board, array $p)
    {
        $ids = array_values(array_unique(array_map(fn ($a) => (int) ($a['card_id'] ?? 0), (array) ($p['actions'] ?? []))));

        return TimetableCard::where('board_id', $board->id)->whereIn('id', $ids)->get()->sortBy(fn ($c) => array_search((int) $c->id, $ids, true))->values();
    }

    /**
     * Amallarni bajaradi (chaqiruvchining tranzaksiyasi ichida; huquq tekshirilmaydi). Har ko'chirish shu haftadagi guruh,
     * o'qituvchi va xona to'qnashuviga tekshiriladi; oxirida yakuniy holat — amaliy dars bo'linmasligi (birlik: bitta kunda, ketma-ket),
     * sikl darslari bilan bandlik (unit-busy) va almashgan o'qituvchining boshqa haftalari. Xato — matni, aks holda null.
     */
    private function runSuggestionActions(TimetableBoard $board, array $acts): ?string
    {
        $moved = [];
        $teacherChanged = [];
        // Amaliy dars yaxlit (birlik): yozishdan oldingi holat; oxirida — joyi o'zgargan birlik bo'linmaganmi. Bir amallar to'plamida
        // birga ko'chayotgan bo'laklar oraliq qadamda bir-birini "band" qilmaydi (yakuniy holat oxirida to'liq tekshiriladi).
        $touch = [];
        $batch = [];
        foreach ($acts as $a) {
            if (in_array((string) ($a['op'] ?? ''), ['move', 'reset', 'cancel'], true)
                && ($c = TimetableCard::where('board_id', $board->id)->find((int) ($a['card_id'] ?? 0)))) {
                $touch[] = [$c, (int) ($a['week'] ?? 0)];
                $batch[(int) ($a['week'] ?? 0)][(int) $c->id] = true;
            }
        }
        $snap = $this->unitSnapshot($touch);
        $mates = function (TimetableCard $card, int $w) use ($snap, $batch): array {
            foreach ($snap as $u) {
                if ($u['week'] === $w && $u['cards']->contains('id', $card->id)) {
                    return array_intersect_key($batch[$w] ?? [], array_flip($u['cards']->pluck('id')->map(fn ($id) => (int) $id)->all()));
                }
            }

            return [];
        };
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
            if ($op === 'reset') {
                TimetableCardOverride::where('card_id', $card->id)->where('week', $w)->delete();
                $moved[$card->id . '|' . $w] = [$card, $w];
            } elseif ($op === 'cancel') {
                TimetableCardOverride::updateOrCreate(['card_id' => $card->id, 'week' => $w],
                    ['day' => null, 'pair' => null, 'cancelled' => true, 'unplaced' => false, 'auditorium_code' => null, 'auditorium_name' => null]);
            } elseif ($op === 'move') {
                $day = (int) ($a['day'] ?? 0);
                $pair = (int) ($a['pair'] ?? 0);
                if (!$day || !$pair) {
                    return 'Kun va para ko‘rsatilishi kerak.';
                }
                // Dastur to'ri (validateTimeProposal bilan bir xil): kunlar soni va dars oxiri — paralar ichida.
                $grid = $this->cardGrid($board, $card);
                if ($day > (int) $grid['days'] || $pair + $card->lenHalf() - 1 > (int) $grid['pairs']) {
                    return '«' . $card->subject_name . '»: tanlangan vaqt jadval chegarasidan tashqarida.';
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
                    // Xona ANIQ berilganda: faol va sig'adi (umumiy raqamsiz xona va bandlik tekshirilmaydigan kartada sig'im — yo'q,
                    // hozirgi qoidalar bilan bir xil). Xonasiz amallar (eski takliflar, kutilayotgan so'rovlar) — avvalgidek.
                    if ($msg = $this->suggestionRoomProblem($board, $card, $room)) {
                        return '«' . $card->subject_name . '»: ' . $msg;
                    }
                }
                $state = ($ign = $mates($card, $w)) ? $this->calendarConflictState($card, $room?->code) + ['ignore' => $ign] : null;
                if ($conflicts = $this->findWeekConflicts($card, $w, $day, $pair, 0, $state, $room?->code)) {
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
            // Yakuniy joy (oxirgi amal "reset" bo'lsa — shablon joyi) yakuniy holatga nisbatan: guruh, o'qituvchi, xona.
            if ($c = $this->findWeekConflicts($card, $w, $d, $p)) {
                return '«' . $card->subject_name . '»: ' . implode(' · ', $c);
            }
            $date = $this->weekStartDate($board, $card, $w)->addDays($d - 1)->toDateString();
            if ($errs = $this->weeklyProposalBusy($card, $w, $d, $p, $date)) {
                return '«' . $card->subject_name . '»: ' . implode(' · ', $errs);
            }
        }
        if ($snap && ($errs = $this->unitIntegrityErrors($snap))) {
            return implode(' · ', $errs);
        }
        // Almashgan o'qituvchi butun semestr uchun: boshqa haftalarda faqat uning bandligi (takliflar hisobidagidek) — kartaning boshqa
        // haftadagi o'z (xona/guruh) to'qnashuvi bu almashtirishni to'smaydi; ko'chirilgan hafta yuqorida to'liq tekshirilgan.
        foreach ($teacherChanged as $card) {
            $card->refresh();
            $card->setRelation('board', $board);
            for ($w = 1; $w <= (int) $this->cardGrid($board, $card)['weeks']; $w++) {
                $c = ($pl = $this->suggestionPlace($card, $w)) ? array_values(array_filter($this->findWeekConflicts($card, $w, $pl[0], $pl[1]),
                    fn ($m) => str_starts_with((string) $m, "O'qituvchi band"))) : [];
                if ($c) {
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
    public function suggestionDryRun(TimetableBoard $board, array $p, ?array $ignorePending = null): array
    {
        $acts = array_map(fn ($a) => $a + ['week' => (int) ($p['week'] ?? 0)], array_values((array) ($p['actions'] ?? [])));
        // So'rov (tasdiq zanjiri): boshqa kutilayotgan so'rovlar band qilgan guruh / o'qituvchi / xona vaqti ham (o'zi bundan mustasno).
        if ($ignorePending !== null) {
            // O'qituvchi almashtirish: bron yangi o'qituvchi bilan; boshqa haftalar (butun semestr) — faqat o'qituvchi broni.
            $subst = [];
            $moved = [];
            foreach ($acts as $a) {
                if (($a['op'] ?? '') === 'teacher') {
                    $subst[(int) $a['card_id']] = (int) ($a['teacher_id'] ?? 0);
                }
            }
            $cardWith = function (int $id) use ($board, $subst) {
                $c = TimetableCard::where('board_id', $board->id)->find($id);
                if ($c && isset($subst[$id])) {
                    $c->teacher_id = $subst[$id];   // saqlanmaydi
                }

                return $c;
            };
            $fail = fn ($c, array $errs) => throw ValidationException::withMessages(['actions' => '«' . $c->subject_name . '»: ' . implode(' · ', $errs)]);
            // Har (karta, hafta) yakuniy joyi (ko'chirish yoki "reset" — shablon joyi) bo'yicha.
            foreach ($this->suggestionFinalPlaces($acts) as $k => $fp) {
                $moved[$k] = true;
                $c = $fp ? $cardWith($fp['card_id']) : null;
                if ($c && ($errs = $this->pendingTimeConflicts($c, ['week' => $fp['week'], 'day' => $fp['day'], 'pair' => $fp['pair'],
                    'auditorium_code' => $fp['auditorium_code'], 'room_fixed' => !empty($fp['room_fixed'])], $ignorePending))) {
                    $fail($c, $errs);
                }
            }
            foreach (array_keys($subst) as $cid) {
                $c = $cardWith($cid);
                for ($w = 1; $c && $w <= (int) $this->cardGrid($board, $c)['weeks']; $w++) {
                    if (!isset($moved[$cid . '|' . $w]) && ($pl = $this->suggestionPlace($c, $w))
                        && ($errs = $this->pendingTimeConflicts($c, ['week' => $w, 'day' => $pl[0], 'pair' => $pl[1]], $ignorePending, ['teacher']))) {
                        $fail($c, $errs);
                    }
                }
            }
        }
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
        // O'qituvchi / fan mas'uli (faol rol) — faqat o'z darsini, u to'qnashuvda (yoki shu haftada joylanmagan) bo'lsa va yangi joyi to'liq
        // toza bo'lsa (teacherConflictFix — serverda qayta hisoblanadi, mijozga ishonilmaydi); aks holda — so'rov orqali.
        $rights = $this->suggestionRights($this->timetableActor($request));
        $teacherFix = !$request->attributes->has('timetable.change_replay') && $rights['level'] !== 'admin'
            && in_array($this->timetableActiveRole($request), TimetableChangeApproval::TEACHER_PROPOSAL_ROLES, true);
        if (!$request->attributes->has('timetable.change_replay') && $rights['level'] !== 'admin' && !$teacherFix) {
            abort(403, 'Taklifni darhol qo‘llash faqat adminlar uchun — boshqa rollar so‘rov yuboradi.');
        }
        $data = $this->suggestionPayload($request);
        $acts = array_map(fn ($a) => $a + ['week' => (int) $data['week']], array_values($data['actions']));
        if ($teacherFix) {
            // Huquq, ma'ruza, sana (bugun — yopiq) va kartadagi kutilayotgan so'rov — so'rov yuborishdagidek.
            $check = $this->suggestionApplyCheck($rights, $board, ['card_id' => $data['card_id'], 'actions' => $acts]);
            if ($check['mode'] === null) {
                return response()->json(['error' => $check['why'], 'message' => $check['why']], $check['code']);
            }
            if ($why = $this->teacherConflictFixWeekly($board, $acts, $rights)) {
                $msg = "Joylashgan darsni o'zgartirish — so'rov orqali.";

                return response()->json(['error' => $msg, 'message' => $msg, 'why' => $why], 403);
            }
        }
        // Bugun — faqat admin / superadmin / registrator ofisi (faol rol) yoki ular tasdiqlagan so'rovni qayta bajarish uchun ochiq.
        if ($why = $this->suggestionExpired($board, ['actions' => $acts], $this->staffTodayOk($request))) {
            return response()->json(['error' => $why], 422);
        }
        $today = $this->suggestionTouchesToday($board, $acts);
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

        if ($error !== null) {
            return response()->json(['error' => $error], 422);
        }
        if ($today) {
            $this->markTodayMoved($request);
        }
        $notified = $teacherFix ? $this->teacherConflictFixDone($request, $board, $acts) : [];

        return response()->json(['ok' => true, 'today_moved' => $today] + ($today ? ['hemis_hint' => self::todayHemisHint()] : [])
            + ($teacherFix ? ['fixed' => true, 'notified' => $notified, 'message' => "To'qnashuvdagi dars so'rovsiz joylandi."] : []));
    }
}

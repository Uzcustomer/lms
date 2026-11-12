<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Haftalik jadvalda darsning yaxlit joylanishi ("birlik").
 *
 * Qoida: bir kunda guruhning bitta fandan amaliy soatlari bo'linmaydi — 3 soat bo'lsa ketma-ket 3 soat, hatto dars bir nechta
 * kartaga (2 soat + 1 soat) bo'lingan bo'lsa ham. Birlik — bir guruh (karta guruh yorlig'i), bir fan, bir mashg'ulot turi va bir
 * o'qituvchiga tegishli barcha bo'laklar (kartalar), tanlangan haftada o'tiladiganlari (bekor qilinmagan). Ma'ruza — o'zi birlik.
 *
 * - unitPlace: birlikni to'liq va ketma-ket joylaydi / suradi / olib tashlaydi; sig'masa — hech biri, sababi bilan
 *   ("4 soat sig'maydi: 13:30 dan keyin 3 soat bo'sh"). Bandlik tekshiruvida birlikning o'z bo'laklari hisobga olinmaydi.
 * - partialUnits: qisman joylangan birliklar (bir qismi joylangan, qolgani joylanmagan) — endpoint va artisan buyrug'i.
 * - unitFix: qisman joylanganlarni yaxlit joylash (qolgan bo'laklar joylangan qismga yopishtiriladi) yoki yaxlit bo'shatish.
 */
trait TimetableWeeklyUnits
{
    /** Birlik kaliti: guruh, fan, tur, o'qituvchi (qamrovi bilan). Ma'ruza — karta o'zi. */
    private function weeklyUnitKey(TimetableCard $c): string
    {
        if ($c->training_type === 'lecture') {
            return 'L#' . $c->id;
        }

        return implode('|', [mb_strtolower(trim((string) $c->faculty_name)), mb_strtolower(trim((string) $c->specialty_name)), (int) $c->course,
            mb_strtolower(trim((string) $c->group_name)), mb_strtolower(trim((string) $c->subject_name)), (string) $c->training_type,
            (int) $c->teacher_id]);
    }

    /** Kartaning birlikdagi barcha bo'laklari (o'zi bilan), id tartibida. */
    private function weeklyUnitCards(TimetableCard $card): Collection
    {
        if ($card->training_type === 'lecture') {
            return collect([$card]);
        }
        $key = $this->weeklyUnitKey($card);

        return TimetableCard::where('board_id', $card->board_id)->where('training_type', $card->training_type)
            ->where('specialty_name', $card->specialty_name)->where('course', $card->course)
            ->where('subject_name', $card->subject_name)->where('group_name', $card->group_name)
            ->orderBy('id')->get()->filter(fn (TimetableCard $o) => $this->weeklyUnitKey($o) === $key)->values();
    }

    /** Haftalik o'zgarishlar: card_id => week => override. */
    private function unitOverrides(Collection $cards): array
    {
        $out = [];
        if (Schema::hasTable('timetable_card_overrides')) {
            foreach (TimetableCardOverride::whereIn('card_id', $cards->pluck('id'))->get() as $o) {
                $out[(int) $o->card_id][(int) $o->week] = $o;
            }
        }

        return $out;
    }

    /**
     * Birlik bo'laklarining shu haftadagi holati: [id => ['card', 'active' (bekor emas), 'day', 'pair']]. $week = 0 — shablon
     * (ichki) yoki tashqi qatlam ($external) joyi.
     */
    private function unitPieces(Collection $cards, int $week, bool $external = false, ?array $ovs = null): array
    {
        $ovs ??= $this->unitOverrides($cards);
        $out = [];
        foreach ($cards as $c) {
            if ($external) {
                $out[$c->id] = ['card' => $c, 'active' => true, 'day' => $c->ext_day ? (int) $c->ext_day : null, 'pair' => $c->ext_pair ? (int) $c->ext_pair : null];
                continue;
            }
            $o = $week ? ($ovs[$c->id][$week] ?? null) : null;
            $active = !($o && $o->cancelled) && (!$week || $week <= $this->cardGrid($c->board, $c)['weeks']);
            [$d, $p] = $o && $o->day && $o->pair ? [(int) $o->day, (int) $o->pair] : [$c->day ? (int) $c->day : null, $c->pair ? (int) $c->pair : null];
            $out[$c->id] = ['card' => $c, 'active' => $active, 'day' => $active ? $d : null, 'pair' => $active ? $p : null];
        }

        return $out;
    }

    /** Birlikning ketma-ket tartibi: joylanganlar joyi bo'yicha, keyin joylanmaganlar (id). [[card, offset], ...], jami uzunlik. */
    private function unitChain(array $pieces): array
    {
        $list = array_values(array_filter($pieces, fn ($p) => $p['active']));
        usort($list, fn ($a, $b) => [$a['pair'] === null ? 1 : 0, $a['pair'] ?? 0, $a['card']->id] <=> [$b['pair'] === null ? 1 : 0, $b['pair'] ?? 0, $b['card']->id]);
        $chain = [];
        $off = 0;
        foreach ($list as $p) {
            $chain[] = [$p['card'], $off];
            $off += $p['card']->lenHalf();
        }

        return [$chain, $off];
    }

    /**
     * Joylangan faol bo'laklar bo'linganmi: turli kunlarda yoki bir kunda oraliq / ustma-ust bilan. null — yaxlit (yoki 0–1 joylangan
     * bo'lak); aks holda ['days' => [kun, ...], 'gap' => bir kunda oraliq bormi].
     */
    private function unitSplitInfo(array $pieces): ?array
    {
        $placed = array_values(array_filter($pieces, fn ($p) => $p['active'] && $p['day'] && $p['pair']));
        if (count($placed) < 2) {
            return null;
        }
        $days = array_values(array_unique(array_map(fn ($p) => (int) $p['day'], $placed)));
        sort($days);
        $gap = false;
        foreach ($days as $d) {
            $on = array_values(array_filter($placed, fn ($p) => (int) $p['day'] === $d));
            usort($on, fn ($a, $b) => [(int) $a['pair'], $a['card']->id] <=> [(int) $b['pair'], $b['card']->id]);
            for ($i = 1; $i < count($on); $i++) {
                if ((int) $on[$i]['pair'] !== (int) $on[$i - 1]['pair'] + $on[$i - 1]['card']->lenHalf()) {
                    $gap = true;
                    break 2;
                }
            }
        }

        return count($days) > 1 || $gap ? ['days' => $days, 'gap' => $gap] : null;
    }

    /** Birlikning shu haftadagi faol bo'laklari soati: "6 soat (2+2+2)" (zanjir tartibida; bitta bo'lak — "2 soat"). */
    private function unitHoursText(array $pieces): string
    {
        [$chain, $total] = $this->unitChain($pieces);
        $parts = array_map(fn ($x) => $x[0]->lenHalf(), $chain);

        return $this->unitHours($total) . (count($parts) > 1 ? ' (' . implode('+', $parts) . ')' : '');
    }

    /** Bo'lingan birlik sababi: "Amaliy dars bo'linmaydi: 6 soat (2+2+2) bir kunda ketma-ket bo'lishi kerak («Fan», guruh, 11-hafta)." */
    private function unitSplitText(TimetableCard $card, array $pieces, int $week = 0): string
    {
        return "Amaliy dars bo'linmaydi: " . $this->unitHoursText($pieces) . " bir kunda ketma-ket bo'lishi kerak («" . $card->subject_name . '», '
            . $card->group_name . ($week ? ', ' . $week . '-hafta' : '') . ').';
    }

    /** Birlik bo'laklarining shu haftadagi joylari: [id => [faol, kun, para]] (yaxlitlik nazorati uchun). */
    private function unitPositions(Collection $cards, int $week): array
    {
        return array_map(fn ($p) => [$p['active'], $p['day'], $p['pair']], $this->unitPieces($cards, $week));
    }

    /**
     * Yaxlitlik nazorati — yozishdan OLDIN: tegadigan (karta, hafta) lar birliklarining holati (ma'ruza — bo'linmaydi; bitta bo'lakli birlik
     * ham yoziladi: o'qituvchisi almashsa, karta boshqa birlikka qo'shilishi mumkin). Yozilgandan keyin unitIntegrityErrors bilan solishtiriladi.
     * @param iterable<array{0: ?TimetableCard, 1: int}> $touch
     * @return array<string, array{cards: Collection, week: int, pos: array}>
     */
    private function unitSnapshot(iterable $touch): array
    {
        $out = [];
        foreach ($touch as [$card, $week]) {
            if (!$card || $card->training_type === 'lecture' || (int) $week < 1) {
                continue;
            }
            $k = $card->board_id . '#' . $this->weeklyUnitKey($card) . '#' . (int) $week;
            if (isset($out[$k])) {
                continue;
            }
            $cards = $this->weeklyUnitCards($card);
            $out[$k] = ['cards' => $cards, 'week' => (int) $week, 'pos' => $this->unitPositions($cards, (int) $week)];
        }

        return $out;
    }

    /**
     * Yaxlitlik nazorati — yozilgandan KEYIN (yakuniy holat): birliklar YAKUNIY tarkibi bo'yicha (o'qituvchi almashgan bo'lak — yangi
     * o'qituvchisining birligida, eski birlikda emas; yangi birlikning boshqa bo'laklari ham), a'zolaridan birining joyi o'zgargan va
     * bo'lingan birlik (turli kunda yoki oraliq bilan) — sababi. Avvaldan bo'lingan, lekin joyi o'zgarmagan birlik (masalan faqat xona yoki
     * faqat o'qituvchi almashdi) — to'siq emas.
     */
    private function unitIntegrityErrors(array $snap): array
    {
        $byWeek = [];
        foreach ($snap as $s) {
            foreach ($s['pos'] as $id => $p) {
                $byWeek[$s['week']][(int) $id] = $p;
            }
        }
        $errors = [];
        foreach ($byWeek as $week => $pos) {
            $units = [];
            foreach (TimetableCard::whereIn('id', array_keys($pos))->orderBy('id')->get() as $c) {
                if ($c->training_type !== 'lecture') {
                    $units[$c->board_id . '#' . $this->weeklyUnitKey($c)] ??= $this->weeklyUnitCards($c);
                }
            }
            foreach ($units as $cards) {
                if ($cards->count() < 2) {
                    continue;
                }
                $pieces = $this->unitPieces($cards, (int) $week);
                $changed = false;
                foreach ($pieces as $id => $p) {
                    if (isset($pos[(int) $id]) && [$p['active'], $p['day'], $p['pair']] !== $pos[(int) $id]) {
                        $changed = true;
                        break;
                    }
                }
                if ($changed && $this->unitSplitInfo($pieces)) {
                    $errors[] = $this->unitSplitText($cards->first(), $pieces, (int) $week);
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /**
     * Yozish ($write) va yaxlitlik nazorati bitta tranzaksiyada: birlik bo'linib qolsa — hech narsa yozilmaydi (rollback), sabablari
     * qaytadi; aks holda [].
     */
    private function unitGuardedWrite(iterable $touch, callable $write): array
    {
        $snap = $this->unitSnapshot($touch);
        DB::beginTransaction();
        try {
            $write();
            $errors = $snap ? $this->unitIntegrityErrors($snap) : [];
            if ($errors) {
                DB::rollBack();

                return $errors;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [];
    }

    /** Yarim-slot (qator) boshlanish vaqti: "13:30". */
    private function unitRowTime(TimetableBoard $board, int $row): string
    {
        return (string) ($this->hemisSlotTimes($board)[$row][0] ?? ($row . '-katak'));
    }

    /** Akademik soat (yarim-slot) soni matni: 3 → "3 soat". */
    private function unitHours(int $half): string
    {
        return $half . ' soat';
    }

    /**
     * Birlikni [day, pair) dan boshlab ketma-ket joylash tekshiruvi. Qaytaradi: [xatolar, [id => yangi pair]].
     * Bo'laklar o'z tartibida (unitChain) ketma-ket; har biri o'z o'qituvchi/xona/guruhi bilan, birlikning boshqa bo'laklari
     * hisobga olinmaydi. Sig'masa (kun oxiri yoki band) — "4 soat sig'maydi: 13:30 dan keyin 3 soat bo'sh" va to'qnashuv sababi.
     * $roomCode — barcha bo'laklar shu xona bilan (shu haftaga; "Boshqa xona bilan joylash").
     */
    private function unitPlanAt(TimetableCard $card, Collection $cards, int $week, int $day, int $pair, bool $external = false, ?string $roomCode = null,
        ?array $order = null): array
    {
        $board = $card->board;
        $pieces = $this->unitPieces($cards, $week, $external);
        [$chain, $total] = $this->unitChain($pieces);
        // $order — bo'laklar tartibi (id lar; "Yaxlitlash": maqsad kundagi bo'laklar joyida qolsin).
        if ($order !== null) {
            $byId = [];
            foreach ($chain as [$c]) {
                $byId[(int) $c->id] = $c;
            }
            $chain = [];
            $off = 0;
            foreach ($order as $id) {
                if (isset($byId[(int) $id])) {
                    $chain[] = [$byId[(int) $id], $off];
                    $off += $byId[(int) $id]->lenHalf();
                    unset($byId[(int) $id]);
                }
            }
            foreach ($byId as $c) {
                $chain[] = [$c, $off];
                $off += $c->lenHalf();
            }
        }
        if (!$chain) {
            return [['Bu haftada bu dars o\'tilmaydi.'], []];
        }
        $ignore = array_fill_keys($cards->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $rows = (int) $this->cardGrid($board, $card)['pairs'];
        $check = function (TimetableCard $piece, int $at) use ($week, $day, $ignore, $external, $roomCode): array {
            if ($external) {
                return $this->findExternalConflicts($piece, $day, $at, array_keys($ignore));
            }
            $state = $this->calendarConflictState($piece, $roomCode) + ['ignore' => $ignore];
            $state['ignore'] = $ignore;

            return $week ? $this->findWeekConflicts($piece, $week, $day, $at, 0, $state, $roomCode) : $this->findConflicts($piece, $day, $at, 0, $state);
        };
        $errors = [];
        $plan = [];
        foreach ($chain as [$piece, $off]) {
            $plan[$piece->id] = $pair + $off;
            if ($pair + $off + $piece->lenHalf() - 1 > $rows) {
                $errors = ['kun oxiri'];
                break;
            }
            if ($e = $check($piece, $pair + $off)) {
                array_push($errors, ...$e);
            }
        }
        if (!$errors) {
            return [[], $plan];
        }
        // Qancha soat bo'sh: boshlanishdan birinchi band yarim-slotgacha (birinchi bo'lak bilan 1 soatlik sinov).
        $free = 0;
        $probe = clone $chain[0][0];
        for ($r = $pair; $r <= $rows && $free < $total; $r++) {
            $probe->len_half = 1;
            if ($check($probe, $r)) {
                break;
            }
            $free++;
        }
        $head = $this->unitHours($total) . " sig'maydi: " . $this->unitRowTime($board, $pair) . ' dan keyin ' . ($free ? $this->unitHours($free) . ' bo\'sh' : 'bo\'sh joy yo\'q');
        $why = array_values(array_unique(array_filter($errors, fn ($e) => $e !== 'kun oxiri')));

        return [[$head . ($why ? ' — ' . implode(' · ', $why) : '')], []];
    }

    /** Birlik bo'laklarini yozish: [id => pair|null] (null — joydan olish). $room — shu haftada barcha bo'laklarga shu xona. */
    private function unitWrite(Collection $cards, int $week, ?int $day, array $plan, bool $external = false, ?\App\Models\Auditorium $room = null): void
    {
        DB::transaction(function () use ($cards, $week, $day, $plan, $external, $room) {
            $ovs = $this->unitOverrides($cards);
            foreach ($cards as $c) {
                if (!array_key_exists($c->id, $plan)) {
                    continue;
                }
                $p = $plan[$c->id];
                if ($external) {
                    $c->update(['ext_day' => $p ? $day : null, 'ext_pair' => $p]);
                } elseif (!$week) {
                    $c->update(['day' => $p ? $day : null, 'pair' => $p, 'start_half' => 0, 'placement_reason_code' => null, 'placement_reason' => null]);
                } elseif ($p) {
                    TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week],
                        ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]
                            + ($room ? ['auditorium_code' => $room->code, 'auditorium_name' => $room->name] : []));
                } elseif (!$c->day && isset($ovs[$c->id][$week])) {
                    // Shablonda joyi yo'q karta — shu haftadagi joyi olib tashlanadi (panelga qaytadi).
                    TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->delete();
                } else {
                    // Shablonda joyi bor — shu haftada joydan olinadi (unplaceOneCard dagidek).
                    TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week],
                        ['day' => null, 'pair' => null, 'cancelled' => true, 'auditorium_code' => null, 'auditorium_name' => null]);
                }
            }
        });
    }

    /**
     * POST cards/{card}/unit-place {week?, day?, pair?, layer?, auditorium_code?}: birlikni yaxlit joylash / surish (day + pair — birinchi
     * bo'lak boshlanishi) yoki olib tashlash (day/pair yo'q). auditorium_code — tanlangan haftada barcha bo'laklar shu xona bilan ("Boshqa
     * xona bilan joylash"). Sig'masa — 422, hech narsa o'zgarmaydi.
     */
    public function unitPlace(Request $request, TimetableCard $card)
    {
        $grid = $this->cardGrid($card->board, $card);
        $data = $request->validate([
            'week' => 'nullable|integer|min:0|max:30',
            'day' => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair' => 'nullable|integer|min:1|max:' . max(1, (int) $grid['pairs']),
            'layer' => 'nullable|in:external,internal',
            'auditorium_code' => 'nullable|string|max:50',
        ]);
        $layer = $this->weeklyLayer($request, $data['layer'] ?? null);
        if ($denied = $this->weeklyLayerDenied($request, $layer, $card)) {
            return $denied;
        }
        $external = $layer === 'external';
        $week = $external ? 0 : (int) ($data['week'] ?? 0);
        if ($week > $grid['weeks']) {
            return response()->json(['error' => 'Bu dasturda ushbu o‘quv haftasi yo‘q.'], 422);
        }
        $cards = $this->weeklyUnitCards($card);
        // Birlikning har bo'lagi — foydalanuvchi qamrovida (kafedra mudiri — o'z kafedrasi).
        foreach ($cards as $x) {
            if ($denied = $this->weeklyLayerDenied($request, $layer, $x)) {
                return $denied;
            }
        }
        $pieces = $this->unitPieces($cards, $week, $external);
        if (empty($data['day']) || empty($data['pair'])) {
            $plan = [];
            foreach ($pieces as $id => $p) {
                if ($p['active'] && $p['day']) {
                    $plan[$id] = null;
                }
            }
            $this->unitWrite($cards, $week, null, $plan, $external);

            return response()->json(['ok' => true, 'unit' => $this->unitPayload($cards, $week, $external)]);
        }
        $room = null;
        if (!empty($data['auditorium_code'])) {
            // Xona — faqat tanlangan haftada (ichki jadval) va har bo'lak o'qituvchisiga ruxsat etilgan.
            if ($external || !$week) {
                return response()->json(['error' => 'Xona faqat tanlangan haftada (ichki jadvalda) beriladi.'], 422);
            }
            $room = \App\Models\Auditorium::where('code', $data['auditorium_code'])->first();
            if (!$room) {
                return response()->json(['error' => 'Auditoriya topilmadi'], 422);
            }
            $map = $this->auditoriumTeacherMap();
            if ($cards->contains(fn (TimetableCard $x) => !$this->auditoriumAllowedForCard($room, $x, $map))) {
                return response()->json(['error' => 'Bu auditoriya tanlangan o\'qituvchiga biriktirilmagan.'], 422);
            }
        }
        [$errors, $plan] = $this->unitPlanAt($card, $cards, $week, (int) $data['day'], (int) $data['pair'], $external, $room?->code);
        if ($errors) {
            return response()->json(['error' => implode(' · ', $errors)], 422);
        }
        $this->unitWrite($cards, $week, (int) $data['day'], $plan, $external, $room);

        return response()->json(['ok' => true, 'unit' => $this->unitPayload($cards, $week, $external)]);
    }

    /** Birlikning hozirgi holati (klient yangilanishi uchun): [{id, day, pair, len_half}]. */
    private function unitPayload(Collection $cards, int $week, bool $external): array
    {
        $fresh = TimetableCard::whereIn('id', $cards->pluck('id'))->orderBy('id')->get();

        return array_values(array_map(fn ($p) => ['id' => (int) $p['card']->id, 'active' => $p['active'], 'day' => $p['day'], 'pair' => $p['pair'],
            'len_half' => $p['card']->lenHalf()], $this->unitPieces($fresh, $week, $external)));
    }

    /**
     * Qisman joylangan birliklar: tanlangan hafta(lar)da bir qismi joylangan, qolgani joylanmagan (kind = partial). $kinds ichida
     * 'split' bo'lsa — bo'lingan birliklar ham (kind = split): hamma bo'lak joylangan, lekin turli kunlarda yoki bir kunda oraliq bilan
     * (days, days_text — "Seshanba 08:30–09:50; Chorshanba 10:00–13:20"). Har biri: guruh, fan, hafta, kun, joylangan va qolgan soat,
     * karta id lari. $weeks bo'sh — doskaning barcha haftalari.
     */
    private function partialUnitList(TimetableBoard $board, array $weeks = [], ?array $scope = null, array $kinds = ['partial']): array
    {
        $q = TimetableCard::where('board_id', $board->id)->where('training_type', '!=', 'lecture');
        if ($scope) {
            [$facSet, $specSet, $courseSet] = $scope;
            $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        }
        $byUnit = $q->orderBy('id')->get()->groupBy(fn (TimetableCard $c) => $this->weeklyUnitKey($c));
        $ovsAll = $this->unitOverrides(TimetableCard::where('board_id', $board->id)->where('training_type', '!=', 'lecture')->select('id')->get());
        $days = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $out = [];
        foreach ($byUnit as $cards) {
            if ($cards->count() < 2) {
                continue;
            }
            $first = $cards->first();
            $total = $this->cardGrid($board, $first)['weeks'];
            foreach ($weeks ?: range(1, $total) as $w) {
                if ($w > $total) {
                    continue;
                }
                $pieces = array_filter($this->unitPieces($cards, (int) $w, false, $ovsAll), fn ($p) => $p['active']);
                $placed = array_filter($pieces, fn ($p) => $p['day']);
                $partial = $placed && count($placed) < count($pieces);
                $split = !$partial && in_array('split', $kinds, true) ? $this->unitSplitInfo($pieces) : null;
                if (!($partial && in_array('partial', $kinds, true)) && !$split) {
                    continue;
                }
                $placedHalf = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), $placed));
                $restHalf = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), array_diff_key($pieces, $placed)));
                $day = (int) reset($placed)['day'];
                $out[] = ['unit' => $this->weeklyUnitKey($first), 'kind' => $split ? 'split' : 'partial',
                    'card_ids' => array_values(array_map('intval', array_keys($pieces))),
                    'faculty' => $first->faculty_name, 'specialty' => $first->specialty_name, 'course' => (int) $first->course,
                    'group' => $first->group_name, 'subject' => $first->subject_name, 'teacher' => $first->teacher_name,
                    'week' => (int) $w, 'day' => $day, 'day_name' => $days[$day - 1] ?? ($day . '-kun'),
                    'placed_hours' => $placedHalf, 'remaining_hours' => $restHalf]
                    + ($split ? ['days' => $split['days'], 'days_text' => $this->unitDaysText($board, $pieces)] : []);
            }
        }

        return $out;
    }

    /** Joylangan bo'laklar kunlar bo'yicha: "Seshanba 08:30–09:50; Chorshanba 10:00–13:20" (kun ichida ketma-ket bo'laklar — bitta oraliq). */
    private function unitDaysText(TimetableBoard $board, array $pieces): string
    {
        $names = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $byDay = [];
        foreach ($pieces as $p) {
            if ($p['active'] && $p['day'] && $p['pair']) {
                $byDay[(int) $p['day']][] = [(int) $p['pair'] - 1, (int) $p['pair'] - 1 + $p['card']->lenHalf()];
            }
        }
        ksort($byDay);
        $out = [];
        foreach ($byDay as $d => $ranges) {
            usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
            $segs = [];
            foreach ($ranges as $r) {
                if ($segs && $segs[count($segs) - 1][1] === $r[0]) {
                    $segs[count($segs) - 1][1] = $r[1];
                } else {
                    $segs[] = $r;
                }
            }
            $out[] = ($names[$d - 1] ?? ($d . '-kun')) . ' ' . implode(', ', array_map(fn ($r) => $this->halfRangeTime($board, $r), $segs));
        }

        return implode('; ', $out);
    }

    /** Artisan buyrug'i (timetable:partial-units) uchun: qisman joylangan ($split — bo'lingan) birliklar. */
    public function partialUnitsFor(TimetableBoard $board, array $weeks = [], bool $split = false): array
    {
        $list = $this->partialUnitList($board, array_map('intval', $weeks), null, $split ? ['split'] : ['partial']);

        return $list;
    }

    /** GET boards/{board}/partial-units?week=N[&split=1] — qisman joylangan (split=1 — bo'lingan ham) birliklar ro'yxati. */
    public function partialUnits(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['week' => 'nullable|integer|min:1|max:30', 'faculty_names' => 'nullable|array', 'specialty_names' => 'nullable|array',
            'courses' => 'nullable|array', 'split' => 'nullable|boolean']);
        $scope = array_filter([$data['faculty_names'] ?? null, $data['specialty_names'] ?? null, $data['courses'] ?? null]) ? $this->scopeSets($data) : null;
        $list = $this->partialUnitList($board, !empty($data['week']) ? [(int) $data['week']] : [], $scope,
            $request->boolean('split') ? ['partial', 'split'] : ['partial']);

        return response()->json(['count' => count($list), 'items' => $list]);
    }

    /**
     * POST boards/{board}/unit-fix {week, action: complete|clear|merge, card_ids?}: qisman joylanganlarni yaxlit joylash — qolgan bo'laklar
     * joylangan qismning ketidan (sig'masa — oldidan) yopishtiriladi; bo'lmasa o'tkazib yuboriladi va sababi qaytadi; clear —
     * birlikning hamma bo'lagi shu haftada joydan olinadi; merge ("Yaxlitlash", faqat aniq birlik — card_ids majburiy, ommaviy emas) —
     * bo'lingan birlik bo'laklari eng ko'p soati turgan kunga ketma-ket (unitMergePlan); sig'masa — hech narsa o'zgarmaydi, sababi bilan.
     */
    public function unitFix(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['week' => 'required|integer|min:1|max:30', 'action' => 'required|in:complete,clear,merge',
            'card_ids' => 'nullable|array', 'card_ids.*' => 'integer', 'faculty_names' => 'nullable|array', 'specialty_names' => 'nullable|array',
            'courses' => 'nullable|array']);
        if ($denied = $this->weeklyLayerDenied($request, 'internal')) {
            return $denied;
        }
        $week = (int) $data['week'];
        $merge = $data['action'] === 'merge';
        if ($merge && empty($data['card_ids'])) {
            return response()->json(['error' => 'Yaxlitlash — faqat tanlangan dars uchun (card_ids).'], 422);
        }
        $scope = array_filter([$data['faculty_names'] ?? null, $data['specialty_names'] ?? null, $data['courses'] ?? null]) ? $this->scopeSets($data) : null;
        $only = !empty($data['card_ids']) ? array_flip(array_map('intval', $data['card_ids'])) : null;
        $done = 0;
        $failed = [];
        foreach ($this->partialUnitList($board, [$week], $scope, $merge ? ['split'] : ['partial']) as $u) {
            if ($only !== null && !array_intersect_key(array_flip($u['card_ids']), $only)) {
                continue;
            }
            $cards = TimetableCard::whereIn('id', $u['card_ids'])->orderBy('id')->get();
            $card = $cards->first();
            // Kafedra mudiri — faqat o'z kafedrasi darslari (unplaceAll kabi): boshqa kafedraniki o'tkazib yuboriladi; aniq so'ralgan
            // (card_ids) bo'lsa — sababi bilan.
            if ($cards->contains(fn ($x) => $this->weeklyLayerDenied($request, 'internal', $x) !== null)) {
                if ($only !== null) {
                    $failed[] = ['group' => $u['group'], 'subject' => $u['subject'], 'day' => $u['day_name'], 'error' => 'Bu fan sizning kafedrangizga tegishli emas.',
                        'card_ids' => $u['card_ids'], 'unit' => $u['unit']];
                }
                continue;
            }
            if ($data['action'] === 'clear') {
                $plan = [];
                foreach ($this->unitPieces($cards, $week) as $id => $p) {
                    if ($p['active'] && $p['day']) {
                        $plan[$id] = null;
                    }
                }
                $this->unitWrite($cards, $week, null, $plan);
                $done++;
                continue;
            }
            $result = $merge ? $this->unitMergePlan($card, $cards, $week) : $this->unitCompletePlan($card, $cards, $week);
            if ($result[0]) {
                $failed[] = ['group' => $u['group'], 'subject' => $u['subject'], 'day' => $u['day_name'], 'error' => implode(' · ', $result[0]),
                    'card_ids' => $u['card_ids'], 'unit' => $u['unit']];
                continue;
            }
            $this->unitWrite($cards, $week, $result[2], $result[1]);
            $done++;
        }

        return response()->json(['ok' => true, 'done' => $done, 'failed' => $failed]);
    }

    /**
     * Qisman joylangan birlikni to'ldirish rejasi: joylangan bo'laklar kunida, avval ularning boshidan (birlik o'z joyida
     * qoladi), keyin birlik oxiri joylangan qism boshiga to'g'ri keladigan joydan. [xatolar, plan, kun].
     */
    private function unitCompletePlan(TimetableCard $card, Collection $cards, int $week): array
    {
        $pieces = array_filter($this->unitPieces($cards, $week), fn ($p) => $p['active']);
        $placed = array_filter($pieces, fn ($p) => $p['day']);
        $day = (int) reset($placed)['day'];
        $start = min(array_column($placed, 'pair'));
        $placedHalf = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), $placed));
        $total = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), $pieces));
        $first = null;
        foreach (array_unique([$start, $start + $placedHalf - $total]) as $at) {
            if ($at < 1) {
                continue;
            }
            [$errors, $plan] = $this->unitPlanAt($card, $cards, $week, $day, $at);
            if (!$errors) {
                return [[], $plan, $day];
            }
            $first ??= $errors;
        }

        return [$first ?? ['joy topilmadi'], [], $day];
    }

    /**
     * Bo'lingan birlikni yaxlitlash rejasi: kun — bo'laklar eng ko'p soati turgan kun (teng bo'lsa — haftaning oldingi kuni); avval shu
     * kundagi birinchi bo'lak boshidan (shu kundagi bo'laklar joyida, qolganlari ketidan), sig'masa — blok oxiri shu kundagi oxirgi bo'lak
     * oxiriga to'g'ri keladigan joydan (qolganlari oldidan). Bandlik unitPlanAt bilan (o'z bo'laklari hisobga olinmaydi). [xatolar, plan, kun].
     */
    private function unitMergePlan(TimetableCard $card, Collection $cards, int $week): array
    {
        $pieces = array_filter($this->unitPieces($cards, $week), fn ($p) => $p['active'] && $p['day'] && $p['pair']);
        $hours = [];
        foreach ($pieces as $p) {
            $hours[(int) $p['day']] = ($hours[(int) $p['day']] ?? 0) + $p['card']->lenHalf();
        }
        ksort($hours);
        $day = (int) array_search(max($hours), $hours, true);
        $sorted = array_values($pieces);
        usort($sorted, fn ($a, $b) => [(int) $a['day'], (int) $a['pair'], $a['card']->id] <=> [(int) $b['day'], (int) $b['pair'], $b['card']->id]);
        $on = array_values(array_filter($sorted, fn ($p) => (int) $p['day'] === $day));
        $other = array_values(array_filter($sorted, fn ($p) => (int) $p['day'] !== $day));
        $ids = fn (array $list) => array_map(fn ($p) => (int) $p['card']->id, $list);
        $total = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), $pieces));
        $start = (int) $on[0]['pair'];
        $end = max(array_map(fn ($p) => (int) $p['pair'] + $p['card']->lenHalf(), $on));
        $first = null;
        foreach ([[$start, array_merge($ids($on), $ids($other))], [$end - $total, array_merge($ids($other), $ids($on))]] as [$at, $order]) {
            if ($at < 1) {
                continue;
            }
            [$errors, $plan] = $this->unitPlanAt($card, $cards, $week, $day, $at, false, null, $order);
            if (!$errors) {
                return [[], $plan, $day];
            }
            $first ??= $errors;
        }

        return [$first ?? ['joy topilmadi'], [], $day];
    }
}

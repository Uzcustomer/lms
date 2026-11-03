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
     */
    private function unitPlanAt(TimetableCard $card, Collection $cards, int $week, int $day, int $pair, bool $external = false): array
    {
        $board = $card->board;
        $pieces = $this->unitPieces($cards, $week, $external);
        [$chain, $total] = $this->unitChain($pieces);
        if (!$chain) {
            return [['Bu haftada bu dars o\'tilmaydi.'], []];
        }
        $ignore = array_fill_keys($cards->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $rows = (int) $this->cardGrid($board, $card)['pairs'];
        $check = function (TimetableCard $piece, int $at) use ($week, $day, $ignore, $external): array {
            if ($external) {
                return $this->findExternalConflicts($piece, $day, $at, array_keys($ignore));
            }
            $state = $this->calendarConflictState($piece) + ['ignore' => $ignore];
            $state['ignore'] = $ignore;

            return $week ? $this->findWeekConflicts($piece, $week, $day, $at, 0, $state) : $this->findConflicts($piece, $day, $at, 0, $state);
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

    /** Birlik bo'laklarini yozish: [id => pair|null] (null — joydan olish). */
    private function unitWrite(Collection $cards, int $week, ?int $day, array $plan, bool $external = false): void
    {
        DB::transaction(function () use ($cards, $week, $day, $plan, $external) {
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
                        ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]);
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
     * POST cards/{card}/unit-place {week?, day?, pair?, layer?}: birlikni yaxlit joylash / surish (day + pair — birinchi bo'lak
     * boshlanishi) yoki olib tashlash (day/pair yo'q). Sig'masa — 422, hech narsa o'zgarmaydi.
     */
    public function unitPlace(Request $request, TimetableCard $card)
    {
        $grid = $this->cardGrid($card->board, $card);
        $data = $request->validate([
            'week' => 'nullable|integer|min:0|max:30',
            'day' => 'nullable|integer|min:1|max:' . $grid['days'],
            'pair' => 'nullable|integer|min:1|max:' . max(1, (int) $grid['pairs']),
            'layer' => 'nullable|in:external,internal',
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
        [$errors, $plan] = $this->unitPlanAt($card, $cards, $week, (int) $data['day'], (int) $data['pair'], $external);
        if ($errors) {
            return response()->json(['error' => implode(' · ', $errors)], 422);
        }
        $this->unitWrite($cards, $week, (int) $data['day'], $plan, $external);

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
     * Qisman joylangan birliklar: tanlangan hafta(lar)da bir qismi joylangan, qolgani joylanmagan. Har biri: guruh, fan, hafta,
     * kun, joylangan va qolgan soat, karta id lari. $weeks bo'sh — doskaning barcha haftalari.
     */
    private function partialUnitList(TimetableBoard $board, array $weeks = [], ?array $scope = null): array
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
                if (!$placed || count($placed) === count($pieces)) {
                    continue;
                }
                $placedHalf = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), $placed));
                $restHalf = array_sum(array_map(fn ($p) => $p['card']->lenHalf(), array_diff_key($pieces, $placed)));
                $day = (int) reset($placed)['day'];
                $out[] = ['unit' => $this->weeklyUnitKey($first), 'card_ids' => array_values(array_map('intval', array_keys($pieces))),
                    'faculty' => $first->faculty_name, 'specialty' => $first->specialty_name, 'course' => (int) $first->course,
                    'group' => $first->group_name, 'subject' => $first->subject_name, 'teacher' => $first->teacher_name,
                    'week' => (int) $w, 'day' => $day, 'day_name' => $days[$day - 1] ?? ($day . '-kun'),
                    'placed_hours' => $placedHalf, 'remaining_hours' => $restHalf];
            }
        }

        return $out;
    }

    /** Artisan buyrug'i (timetable:partial-units) uchun: qisman joylangan birliklar. */
    public function partialUnitsFor(TimetableBoard $board, array $weeks = []): array
    {
        return $this->partialUnitList($board, array_map('intval', $weeks));
    }

    /** GET boards/{board}/partial-units?week=N — qisman joylangan birliklar ro'yxati. */
    public function partialUnits(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['week' => 'nullable|integer|min:1|max:30', 'faculty_names' => 'nullable|array', 'specialty_names' => 'nullable|array',
            'courses' => 'nullable|array']);
        $scope = array_filter([$data['faculty_names'] ?? null, $data['specialty_names'] ?? null, $data['courses'] ?? null]) ? $this->scopeSets($data) : null;
        $list = $this->partialUnitList($board, !empty($data['week']) ? [(int) $data['week']] : [], $scope);

        return response()->json(['count' => count($list), 'items' => $list]);
    }

    /**
     * POST boards/{board}/unit-fix {week, action: complete|clear, card_ids?}: qisman joylanganlarni yaxlit joylash — qolgan bo'laklar
     * joylangan qismning ketidan (sig'masa — oldidan) yopishtiriladi; bo'lmasa o'tkazib yuboriladi va sababi qaytadi; clear —
     * birlikning hamma bo'lagi shu haftada joydan olinadi.
     */
    public function unitFix(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['week' => 'required|integer|min:1|max:30', 'action' => 'required|in:complete,clear',
            'card_ids' => 'nullable|array', 'card_ids.*' => 'integer', 'faculty_names' => 'nullable|array', 'specialty_names' => 'nullable|array',
            'courses' => 'nullable|array']);
        if ($denied = $this->weeklyLayerDenied($request, 'internal')) {
            return $denied;
        }
        $week = (int) $data['week'];
        $scope = array_filter([$data['faculty_names'] ?? null, $data['specialty_names'] ?? null, $data['courses'] ?? null]) ? $this->scopeSets($data) : null;
        $only = !empty($data['card_ids']) ? array_flip(array_map('intval', $data['card_ids'])) : null;
        $done = 0;
        $failed = [];
        foreach ($this->partialUnitList($board, [$week], $scope) as $u) {
            if ($only !== null && !array_intersect_key(array_flip($u['card_ids']), $only)) {
                continue;
            }
            $cards = TimetableCard::whereIn('id', $u['card_ids'])->orderBy('id')->get();
            $card = $cards->first();
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
            $result = $this->unitCompletePlan($card, $cards, $week);
            if ($result[0]) {
                $failed[] = ['group' => $u['group'], 'subject' => $u['subject'], 'day' => $u['day_name'], 'error' => implode(' · ', $result[0])];
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
}

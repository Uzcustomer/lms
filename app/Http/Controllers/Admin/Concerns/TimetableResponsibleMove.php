<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Http\Controllers\Admin\TimetableChangeRequestController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Services\Timetable\TimetableChangeApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fan mas'uli (faol rol — fan_masuli): mas'ul fani kartasini HAFTALIK ichki jadvalda shu haftaga ko'chirish — kun, boshlanish
 * (para chegarasi), xona. Gibrid qoida (rejim har doim serverda aniqlanadi, mijozga ishonilmaydi):
 *   - "fix" — dars shu haftada to'qnashuvda (qizil: o'qituvchi / guruh band, amaliyot ma'ruza bilan ustma-ust; sariq ham: xona band,
 *     xona sig'maydi yoki nofaol) yoki joylanmagan VA yangi joy to'liq toza → darhol (Takliflar bajaruvchisi runSuggestionActions),
 *     o'zgarishlar tarixiga "To'qnashuvni o'qituvchi hal qildi (fan mas'uli)" bilan, fan kafedrasi mudiriga xabar;
 *   - "request" — to'qnashuvsiz joylashgan dars → jadval o'zgarmaydi, Takliflar so'rovi (SUGGESTION_ROUTE, sabab majburiy):
 *     kafedra mudiri → registrator ofisi; har bosqichda huquq (karta hamon mas'ul fanda) va bandlik qayta tekshiriladi;
 *   - yangi joy band bo'lsa — hech qaysi rejim yo'q: 422, aniq sabab bilan.
 * Faqat shu hafta (hafta o'zgarishi; shablon va kartaning o'z xonasi o'zgarmaydi), ko'p bo'lakli dars (2 + 2 soat) — yaxlit, bo'laklar
 * tanlangan paradan ketma-ket. Ma'ruza — faqat kafedra mudiri va adminlar. Bugungi va o'tgan kunlar (eski va yangi) — yopiq.
 * Xona: berilsa — faol, sig'adi, auditoriumAllowedForCard; berilmasa — har bo'lak shu haftadagi o'z xonasida (u nofaol yoki sig'masa —
 * "Xona tanlang"); xonasiz dars — xona tanlash majburiy. Xonalar ro'yxati check javobida (admin.timetable.auditoriums / teachers fan
 * mas'uliga ochilmaydi). "Joylash uchun tanlash" (joylanmagan / to'qnashuvdagi mas'ul fan kartasi): grid — shu haftaning barcha kataklari
 * holati va 5 ta eng yaxshi joy bitta so'rov bilan (responsibleMoveGrid); tanlangan katak — shu check / saqlash orqali.
 */
trait TimetableResponsibleMove
{
    /** POST cards/{card}/responsible-move/check {week, day, pair, auditorium_code?} → {mode: fix|request|null, message, conflicts, rooms}. */
    public function responsibleMoveCheck(Request $request, TimetableCard $card)
    {
        $ev = $this->responsibleMoveEvaluate($request, $card, true);

        return response()->json($ev['body'], $ev['status']);
    }

    /**
     * GET cards/{card}/responsible-move/grid?week= — "Joylash uchun tanlash" (fan mas'uli) kataklari bitta so'rov bilan: har kun × boshlanish
     * qatori (yarim-slot) uchun butun birlik (2 + 2 soat — bo'laklari ketma-ket) shu qatordan boshlab: {state, reason, causes, best_room}.
     * state: ok — guruh va o'qituvchi bo'sh, mos bo'sh xona bor (best_room — yaqinlik tartibidagi birinchisi, hozirgi xona ustun);
     * teacher — o'qituvchi band (haftalik dars — unitPlanAt / weekClashes bilan bir xil, sikl darsi ham); group — guruh band; room — vaqt
     * bo'sh, lekin mos bo'sh xona yo'q (xona tanlash majburiy); closed — bugun / o'tgan kun, kun oxiriga sig'maydi yoki dars umuman
     * o'zgartirilmaydi (huquq, kutilayotgan so'rov, hozirgi joyi o'tgan kunda — Takliflar qoidasi). best — 5 ta eng yaxshi bo'sh joy.
     * Shu haftada bo'sh (ok) joy yo'q bo'lsa — ahead: keyingi 3 hafta uchun faqat bo'sh joylar soni [{week, ok}] (yengil hisob).
     * Huquq — check / saqlash bilan bir xil (responsibleMoveAuth). Yakuniy tekshiruv (bronlar, blok qoidasi va h.k.) — saqlashda.
     */
    public function responsibleMoveGrid(Request $request, TimetableCard $card)
    {
        [$actor, $board, $keys, $cards] = $this->responsibleMoveAuth($request, $card);
        $grid = $this->cardGrid($board, $card);
        $week = (int) $request->validate(['week' => 'required|integer|min:1|max:' . max(1, (int) $grid['weeks'])])['week'];
        $this->roomRankReset();
        $out = $this->responsibleGridFor($board, $card, $cards, $week, $actor, $keys);

        return response()->json($out, isset($out['error']) ? 422 : 200);
    }

    /**
     * responsibleMoveGrid hisobi (yuqoridagi tavsif). $light — faqat bo'sh (ok) joylar soni: ['ok_count' => n] (keyingi haftalar uchun;
     * eng yaxshi joylar va keyingi haftalar hisoblanmaydi).
     */
    private function responsibleGridFor(TimetableBoard $board, TimetableCard $card, \Illuminate\Support\Collection $cards, int $week, Teacher $actor, array $keys,
        bool $light = false): array
    {
        $grid = $this->cardGrid($board, $card);
        [$days, $rows] = [(int) $grid['days'], (int) $grid['pairs']];
        [$chain, $total] = $this->unitChain($this->unitPieces($cards, $week));
        if (!$chain) {
            return $light ? ['ok_count' => 0]
                : ['ok' => false, 'error' => "Bu haftada bu dars o'tilmaydi.", 'message' => "Bu haftada bu dars o'tilmaydi."];
        }
        $first = $chain[0][0];
        $monday = $this->weekStartDate($board, $card, $week);
        $today = self::proposalToday();
        $dates = [];
        for ($d = 1; $d <= $days; $d++) {
            $dates[$d] = $monday->copy()->addDays($d - 1)->toDateString();
        }
        $names = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $multi = count($chain) > 1;
        $actsAt = fn (int $d, int $p) => array_merge($multi ? array_map(fn ($x) => ['op' => 'cancel', 'card_id' => (int) $x[0]->id, 'week' => $week], $chain) : [],
            array_map(fn ($x) => ['op' => 'move', 'card_id' => (int) $x[0]->id, 'week' => $week, 'day' => $d, 'pair' => $p + $x[1]], $chain));

        // Dars umuman o'zgartirilmaydimi (huquq, kafedra, kutilayotgan so'rov, hozirgi joyi o'tgan kunda) — Takliflar qoidasi birinchi ochiq
        // kun bilan sinab (bu qoidalar katakka bog'liq emas; yangi sana — har katakda alohida).
        $open = array_keys(array_filter($dates, fn ($dt) => $dt > $today));
        $closedAll = !$open ? "Bu haftada ochiq kun yo'q — bugungi va o'tgan kunlar yopiq." : null;
        if ($open) {
            $rights = ['level' => 'teacher', 'view' => false, 'tid' => (int) $actor->id, 'kafedra' => null, 'keys' => $keys];
            $chk = $this->suggestionApplyCheck($rights, $board, ['card_id' => (int) $card->id, 'actions' => $actsAt($open[0], 1)]);
            if ($chk['mode'] === null) {
                $closedAll = (string) $chk['why'];
            }
        }

        // Bandlik: haftalik darslar (har bo'lak — unitPlanAt dagidek holat, birlikning o'z bo'laklarisiz) va shu hafta sanalaridagi barcha
        // darslar yozuvlari (xona bandligi, sikl darsidagi o'qituvchi, "eng yaxshi joy" mezonlari) — bir marta.
        $ignore = array_fill_keys($cards->pluck('id')->map(fn ($id) => (int) $id)->all(), true);
        $states = [];
        foreach ($chain as [$piece]) {
            $states[$piece->id] = $this->calendarConflictState($piece);
            $states[$piece->id]['ignore'] = $ignore;
        }
        $byDate = [];
        if (!$closedAll) {
            $dateSet = array_flip(array_values($dates));
            [$units, $calendar] = $this->placedCycleUnits($board);
            foreach (array_merge($this->weeklyOccupancy($board, $this->weeklyStateOn($board, array_values($dates), PHP_INT_MAX, $ignore), $dateSet),
                $this->cycleOccupancy($board, $units, $calendar, $dateSet)) as $e) {
                if (!isset($ignore[(int) $e['card']]) && empty($e['ph'])) {
                    $byDate[$e['date']][] = $e;
                }
            }
        }

        // Xonalar: har bo'lak o'qituvchisiga ruxsat etilgan, faol, sig'adigan; yaqinlik tartibi (rankRooms, $proximity), hozirgi xona — birinchi.
        // "Kafedra mudiri xonasi" — vaqtinchalik belgi, taklif qilinmaydi.
        $base = $this->roomRankBase();
        $ov = TimetableCardOverride::where('card_id', $first->id)->where('week', $week)->first();
        $current = (string) ($ov?->auditorium_code ?: $first->auditorium_code);
        $fits = fn ($r) => !preg_match('/kafedra\s+mudiri/iu', (string) $r->name) && collect(array_column($chain, 0))->every(fn ($c) =>
            $this->auditoriumAllowedForCard($r, $c, $base['teacher']) && $this->suggestionRoomProblem($board, $c, $r) === null);
        $ranked = [];
        foreach ($this->rankRooms($first, $base['active']->filter($fits), $current !== '' ? $current : null, $this->roomHabitForCard($board, $first), true) as [$r, $why]) {
            $item = ['code' => (string) $r->code, 'name' => (string) ($r->name ?: $r->code), 'why' => (string) $r->code === $current ? 'hozirgi xona' : $why,
                'free_always' => $this->isSharedRoom((string) $r->name)];
            (string) $r->code === $current ? array_unshift($ranked, $item) : $ranked[] = $item;
        }
        $overlaps = fn (array $e, int $from, int $to) => (int) $e['from'] < $to && $from < (int) $e['to'];

        $groupKeys = array_map(fn ($g) => $this->groupScopeKey($first) . '|' . $g, $this->cardGroupsOf($first));
        $cells = [];
        $okCells = [];
        foreach ($dates as $d => $date) {
            for ($p = 1; $p <= $rows; $p++) {
                $key = $d . '-' . $p;
                if ($closedAll !== null) {
                    $cells[$key] = ['state' => 'closed', 'reason' => $closedAll];
                    continue;
                }
                if ($date <= $today) {
                    $cells[$key] = ['state' => 'closed', 'reason' => "Bugungi va o'tgan kunlar — yopiq (" . Carbon::parse($date)->format('d.m') . ').'];
                    continue;
                }
                if ($p + $total - 1 > $rows) {
                    $cells[$key] = ['state' => 'closed', 'reason' => 'Kun oxiri: ' . $this->unitHours($total) . " sig'maydi."];
                    continue;
                }
                $teacher = [];
                $group = [];
                $causes = [];
                foreach ($chain as [$piece, $off]) {
                    $at = $p + $off;
                    foreach ($this->weekClashes($piece, $week, $d, $at, 0, $states[$piece->id], null) as $cl) {
                        foreach ($cl['errors'] as $err) {
                            $kind = str_starts_with($err, "O'qituvchi band") ? 'teacher' : (str_starts_with($err, 'Guruh band') ? 'group' : null);
                            if ($kind === null) {
                                continue;   // xona — quyida alohida (boshqa xona tanlanadi)
                            }
                            if ($kind === 'teacher') {
                                $teacher[$err] = true;
                            } else {
                                $group[$err] = true;
                            }
                            $id = (int) $cl['card']->id;
                            $causes[$id] = ($causes[$id] ?? null) === 'teacher' ? 'teacher' : $kind;
                        }
                    }
                    // Sikl darsi bilan o'qituvchi bandligi (haftalik weekClashes ko'rmaydi).
                    if ($piece->teacher_id) {
                        foreach ($byDate[$date] ?? [] as $e) {
                            if (($e['kind'] ?? '') === 'cycle' && (int) $e['teacher_id'] === (int) $piece->teacher_id && $overlaps($e, $at, $at + $piece->lenHalf())) {
                                $teacher["O'qituvchi band (sikl): " . $piece->teacher_name . ' — ' . $e['label']] = true;
                            }
                        }
                    }
                }
                if ($teacher || $group) {
                    $cells[$key] = ['state' => $teacher ? 'teacher' : 'group', 'reason' => implode("\n", array_keys($teacher + $group)), 'causes' => $causes];
                    continue;
                }
                $busyRooms = [];
                foreach ($byDate[$date] ?? [] as $e) {
                    if ($e['room'] && empty($e['free_room']) && $overlaps($e, $p, $p + $total)) {
                        $busyRooms[(string) $e['room']] = $e['label'];
                    }
                }
                $best = null;
                foreach ($ranked as $i => $r) {
                    if ($r['free_always'] || !isset($busyRooms[$r['code']])) {
                        $best = ['code' => $r['code'], 'name' => $r['name'], 'why' => $r['why'], 'rank' => $i];
                        break;
                    }
                }
                if (!$best) {
                    $cells[$key] = ['state' => 'room', 'reason' => "Vaqt bo'sh, lekin mos bo'sh xona yo'q" . ($current !== '' && isset($busyRooms[$current])
                        ? ' (hozirgi xona band: ' . $busyRooms[$current] . ')' : '') . ' — xona tanlang.'];
                    continue;
                }
                $cells[$key] = ['state' => 'ok', 'reason' => null, 'best_room' => array_diff_key($best, ['rank' => 1])];
                $okCells[] = [$d, $p, $best];
            }
        }

        if ($light) {
            return ['ok_count' => count($okCells)];
        }
        // Bo'sh joy yo'q — keyingi 3 hafta (semestr ichida) bo'sh joylari soni: "11-hafta (5 joy), 12-hafta (3 joy)".
        $ahead = [];
        if (!$okCells) {
            for ($w = $week + 1; $w <= min($week + 3, (int) $grid['weeks']); $w++) {
                $ahead[] = ['week' => $w, 'ok' => $this->responsibleGridFor($board, $card, $cards, $w, $actor, $keys, true)['ok_count']];
            }
        }

        return ['ok' => true, 'week' => $week, 'days' => $days, 'pairs' => $rows, 'len' => $total,
            'parts' => array_map(fn ($x) => ['card_id' => (int) $x[0]->id, 'len_half' => $x[0]->lenHalf(), 'offset' => $x[1]], $chain),
            'closed' => $closedAll, 'cells' => $cells, 'ahead' => $ahead,
            'best' => $this->responsibleBestPlaces($board, $first, $chain, $okCells, $byDate, $dates, $groupKeys, $names, $total)];
    }

    /**
     * 5 ta eng yaxshi bo'sh joy (ok kataklardan, vaqti bir-biriga tegmaydigan): avval guruhning shu kungi boshqa darslariga yaqini (oraliq
     * kam; shu kuni darsi yo'q kun — oxirida), so'ng o'qituvchining shu kungi bandligi kam, so'ng yaqin xona (yaqinlik tartibidagi o'rni).
     */
    private function responsibleBestPlaces(TimetableBoard $board, TimetableCard $first, array $chain, array $okCells, array $byDate, array $dates,
        array $groupKeys, array $names, int $total): array
    {
        $scored = [];
        foreach ($okCells as [$d, $p, $room]) {
            $gap = null;
            $teacherHours = 0;
            foreach ($byDate[$dates[$d]] ?? [] as $e) {
                if (($e['kind'] ?? '') === 'weekly' && array_intersect((array) ($e['groups'] ?? []), $groupKeys)) {
                    $g = max(0, max((int) $e['from'] - ($p + $total), $p - (int) $e['to']));
                    $gap = $gap === null ? $g : min($gap, $g);
                }
                if ($first->teacher_id && (int) $e['teacher_id'] === (int) $first->teacher_id) {
                    $teacherHours += max(0, (int) $e['to'] - (int) $e['from']);
                }
            }
            $scored[] = ['d' => $d, 'p' => $p, 'room' => $room, 'gap' => $gap, 'teacher' => $teacherHours,
                'key' => [$gap ?? PHP_INT_MAX, $teacherHours, $room['rank'], $d, $p]];
        }
        usort($scored, fn ($a, $b) => $a['key'] <=> $b['key']);
        $out = [];
        $taken = [];
        $last = end($chain);
        foreach ($scored as $s) {
            if (count($out) >= 5) {
                break;
            }
            foreach ($taken[$s['d']] ?? [] as [$a, $b]) {
                if ($s['p'] < $b && $a < $s['p'] + $total) {
                    continue 2;   // shu kunning boshqa tanlangan joyi bilan ustma-ust — xilma-xil variantlar
                }
            }
            $taken[$s['d']][] = [$s['p'], $s['p'] + $total];
            $from = $this->rangeFor($first, $s['p'], 0);
            $to = $this->rangeFor($last[0], $s['p'] + $last[1], 0);
            $why = [$s['gap'] === null ? "guruhning shu kuni boshqa darsi yo'q" : ($s['gap'] === 0 ? 'guruhning shu kungi darsiga ulashgan'
                : 'guruh darsidan ' . $this->unitHours($s['gap']) . ' oraliq'),
                $s['teacher'] ? "o'qituvchida shu kuni " . $this->unitHours($s['teacher']) . ' dars' : "o'qituvchi shu kuni bo'sh"];
            if ($s['room']['why']) {
                $why[] = 'xona — ' . $s['room']['why'];
            }
            $out[] = ['day' => $s['d'], 'pair' => $s['p'], 'date' => $dates[$s['d']],
                'label' => ($names[$s['d'] - 1] ?? $s['d'] . '-kun') . ', ' . Carbon::parse($dates[$s['d']])->format('d.m') . ' ' . $this->halfRangeTime($board, [$from[0], $to[1]]),
                'room' => array_diff_key($s['room'], ['rank' => 1]), 'why' => implode(' · ', $why)];
        }

        return $out;
    }

    /** POST cards/{card}/responsible-move {week, day, pair, auditorium_code?, reason?}: "fix" — darhol, "request" — so'rov. */
    public function responsibleMove(Request $request, TimetableCard $card)
    {
        $ev = $this->responsibleMoveEvaluate($request, $card, false);
        if ($ev['body']['mode'] === null) {
            return response()->json($ev['body'] + ['error' => $ev['body']['message']], $ev['status']);
        }
        $ctx = $ev['ctx'];
        $board = $ctx['board'];
        $payload = ['week' => $ctx['week'], 'card_id' => (int) $card->id, 'type' => 'move', 'title' => "Vaqt va xonani o'zgartirish (fan mas'uli)",
            'actions' => $ctx['acts']];
        if ($ev['body']['mode'] === 'request') {
            $reason = trim((string) $request->input('reason', ''));
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => "So'rov sababini kiriting."]);
            }
            // Takliflar so'rovi — faol rol (fan mas'uli) huquqi bilan, tekshiruvdagidek: kafedra mudiri → registrator ofisi (boshqa
            // rollari — admin / kafedra mudiri — zanjirni o'zgartirmaydi); har bosqichda qayta tekshiruv, tasdiqdan keyin qo'llash.
            $res = app(TimetableChangeRequestController::class)->proposeResponsibleSuggestion($request, $board, $payload, $reason,
                app(TimetableChangeApproval::class));
            $j = $res->getData(true);

            return response()->json($j + ['mode' => 'request'], $res->getStatusCode());
        }

        // "fix": tarix yozuvi amaldan OLDIN (qayerdan → qayerga), so'ng doska qulfi ostida qayta baholash va bajarish.
        $svc = app(TimetableChangeApproval::class);
        $actor = $this->timetableActor($request);
        $draft = null;
        try {
            $draft = $svc->draft($this->responsibleSubRequest($board, $payload), $actor, $board, 'fan_masuli');
        } catch (\Throwable $e) {
            Log::warning('[ResponsibleMove] tarix: ' . $e->getMessage());
        }
        $error = null;
        try {
            DB::transaction(function () use ($request, $card, $board, &$error) {
                TimetableBoard::whereKey($board->id)->lockForUpdate()->first();
                // Parallel o'zgarish: qulf ostida qayta — rejim hamon "fix" va yangi joy toza bo'lishi kerak.
                $again = $this->responsibleMoveEvaluate($request, $card, false);
                if ($again['body']['mode'] !== 'fix') {
                    $error = $again['body']['mode'] === 'request'
                        ? "Dars endi to'qnashuvsiz — o'zgarish so'rov orqali (sababini kiriting)." : $again['body']['message'];
                    throw new \RuntimeException('responsible-move-failed');
                }
                if ($error = $this->runSuggestionActions($board, $again['ctx']['acts'])) {
                    throw new \RuntimeException('responsible-move-failed');
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'responsible-move-failed') {
                throw $e;
            }
        }
        if ($error !== null) {
            return response()->json(['ok' => false, 'mode' => null, 'error' => $error, 'message' => $error], 422);
        }
        $note = self::teacherFixNote() . " (fan mas'uli)";
        if ($draft) {
            try {
                $draft['summary'] = trim((string) $draft['summary']) . ' — ' . $note;
                $draft['details'] = (array) ($draft['details'] ?? []) + ['flags' => [$note]];
                $svc->record($draft);
            } catch (\Throwable $e) {
                Log::warning('[ResponsibleMove] tarix: ' . $e->getMessage());
            }
        }
        $notified = $this->teacherConflictFixDone($request, $board, $ctx['acts']);

        return response()->json(['ok' => true, 'mode' => 'fix', 'fixed' => true, 'notified' => $notified,
            'message' => "To'qnashuvdagi dars darhol joylandi (faqat shu hafta)."]);
    }

    /**
     * Kiritma, huquq, bo'laklar, xona va rejim. Qaytaradi: ['status', 'body' => [ok, mode, message, conflicts, rooms, old, new, parts],
     * 'ctx' => [board, week, acts, ...]]. $withRooms — xonalar ro'yxati (check oynasi uchun).
     */
    /**
     * Huquq (check, saqlash va grid uchun bir xil): faol rol — fan mas'uli (Teacher); ma'ruza — rad; birlikning barcha bo'laklari mas'ul
     * fanida. Qaytaradi: [actor, board, keys, unit kartalari].
     */
    private function responsibleMoveAuth(Request $request, TimetableCard $card): array
    {
        $actor = $this->timetableActor($request);
        abort_unless($this->timetableActiveRole($request) === 'fan_masuli' && $actor instanceof Teacher, 403,
            "Vaqt va xonani o'zgartirish oynasi — fan mas'uli uchun.");
        $board = TimetableBoard::findOrFail($card->board_id);
        $card->setRelation('board', $board);
        abort_if($card->training_type === 'lecture', 403, "Ma'ruzani surish — faqat kafedra mudiri va adminlar.");
        $keys = $this->responsibleSubjectKeysFor((int) $actor->id);
        $cards = $this->weeklyUnitCards($card);
        foreach ($cards as $c) {
            $c->setRelation('board', $board);
            abort_unless($keys && $this->responsibleMatch($keys, $c), 403, "Faqat mas'ul faningizdagi darsni o'zgartira olasiz.");
        }

        return [$actor, $board, $keys, $cards];
    }

    private function responsibleMoveEvaluate(Request $request, TimetableCard $card, bool $withRooms): array
    {
        [$actor, $board, $keys, $cards] = $this->responsibleMoveAuth($request, $card);
        $grid = $this->cardGrid($board, $card);
        $data = $request->validate([
            'week' => 'required|integer|min:1|max:' . max(1, (int) $grid['weeks']),
            'day' => 'required|integer|min:1|max:' . max(1, (int) $grid['days']),
            'pair' => 'required|integer|min:1|max:' . max(1, (int) $grid['pairs']),
            'auditorium_code' => 'nullable|string|max:50',
            'reason' => 'nullable|string|max:1000',
        ]);
        $week = (int) $data['week'];
        $day = (int) $data['day'];
        $pair = (int) $data['pair'];
        $no = fn (int $status, string $msg, array $conflicts = [], array $extra = []) => ['status' => $status,
            'body' => ['ok' => false, 'mode' => null, 'message' => $msg, 'conflicts' => $conflicts ?: [$msg]] + $extra,
            'ctx' => ['board' => $board, 'week' => $week, 'acts' => []]];

        // Bo'laklar: shu haftada o'tiladiganlari, tanlangan paradan ketma-ket (unitChain tartibi).
        [$chain] = $this->unitChain($this->unitPieces($cards, $week));
        if (!$chain) {
            return $no(422, "Bu haftada bu dars o'tilmaydi.");
        }
        $moves = [];
        foreach ($chain as [$piece, $off]) {
            $p = $pair + $off;
            if ($p + $piece->lenHalf() - 1 > (int) $grid['pairs']) {
                return $no(422, 'Tanlangan vaqt jadval chegarasidan tashqarida (dars ' . $this->unitHours(array_sum(array_map(fn ($x) => $x[0]->lenHalf(), $chain)))
                    . ').');
            }
            $moves[] = [$piece, $p];
        }
        $parts = collect(array_map(fn ($m) => $m[0], $moves));
        $date = $this->weekStartDate($board, $card, $week)->addDays($day - 1)->toDateString();
        $labels = $this->responsibleMoveLabels($board, $moves, $week, $day);
        $info = ['old' => $labels['old'], 'new' => $labels['new'], 'date' => $date,
            'parts' => array_map(fn ($m) => ['card_id' => (int) $m[0]->id, 'pair' => $m[1], 'len_half' => $m[0]->lenHalf()], $moves)];
        $rooms = [];
        if ($withRooms) {
            // Bo'laklarning shu haftadagi xonalari (har xil bo'lsa — "har bo'lak o'z xonasida" va har biri ro'yxatda oddiy qator).
            $this->roomRankReset();
            $info['part_rooms'] = $this->responsiblePartRooms($board, $parts, $week);
            $rooms = $this->responsibleMoveRooms($board, $moves, $week, $day, $date, $info['part_rooms']);
        }

        // Xona: berilgan — faol, sig'adi, o'qituvchiga ruxsat; berilmagan — har bo'lak o'z (shu haftadagi) xonasida, u yaroqli bo'lishi kerak.
        $room = null;
        $code = trim((string) ($data['auditorium_code'] ?? ''));
        $roomErrors = [];
        if ($code !== '') {
            $room = Auditorium::where('code', $code)->first();
            if (!$room) {
                $roomErrors[] = 'Xona topilmadi.';
            } else {
                $map = $this->auditoriumTeacherMap();
                foreach ($parts as $c) {
                    if (!$this->auditoriumAllowedForCard($room, $c, $map)) {
                        // Boshqa o'qituvchiga biriktirilgan xona — kimga biriktirilgani bilan ("Bu xona OSTANAQULOV SH. F. ga biriktirilgan.").
                        $owner = $this->responsibleRoomOwner($room, $map, $this->responsibleRoomOwnerNames($map, [$room]));
                        $roomErrors[] = $owner ? 'Bu xona ' . $owner['short'] . ' ga biriktirilgan.'
                            : 'Bu xona darsning o‘qituvchisiga biriktirilmagan: ' . ($room->name ?: $room->code) . '.';
                        break;
                    }
                    if ($msg = $this->suggestionRoomProblem($board, $c, $room)) {
                        $roomErrors[] = ucfirst($msg);
                        break;
                    }
                }
            }
        } else {
            foreach ($parts as $c) {
                $ov = TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->first();
                $cur = (string) ($ov?->auditorium_code ?: $c->auditorium_code);
                // Xonasiz dars xonasiz qolmasin: joylash / ko'chirishda xona tanlash majburiy (grid'da — sariq katak).
                if ($cur === '') {
                    $roomErrors[] = "Xona tanlang: darsga xona biriktirilmagan.";
                    break;
                }
                $curRoom = Auditorium::where('code', $cur)->first();
                $msg = $curRoom ? $this->suggestionRoomProblem($board, $c, $curRoom) : 'xona ro‘yxatda yo‘q';
                if ($msg) {
                    $roomErrors[] = 'Xona tanlang: hozirgi xona ' . ($curRoom ? ($curRoom->name ?: $curRoom->code) : $cur) . ' — ' . preg_replace('/^xona nofaol — boshqa xona tanlang \(.*\)\.$/u', 'nofaol', $msg) . '.';
                    break;
                }
            }
        }
        if ($roomErrors) {
            return $no(422, implode(' ', $roomErrors), $roomErrors, $info + ['rooms' => $rooms]);
        }
        // Ko'p bo'lakli dars: avval hamma bo'lak shu haftada bo'shatiladi ("cancel"), so'ng ketma-ket qo'yiladi — aks holda bo'lak o'z
        // blokining eski joyi bilan to'qnashardi (bir kunda surilganda, masalan 1 va 3 → 3 va 5). "cancel" shu haftaning xonasini o'chiradi —
        // xona tanlanmagan bo'lsa, bo'lakning shu haftadagi xonasi qayta beriladi; yakuniy holat runSuggestionActions oxirida tekshiriladi.
        $multi = count($moves) > 1;
        $acts = $multi ? array_map(fn ($m) => ['op' => 'cancel', 'card_id' => (int) $m[0]->id, 'week' => $week], $moves) : [];
        foreach ($moves as [$c, $p]) {
            $code = $room ? (string) $room->code
                : ($multi ? (string) (TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->value('auditorium_code') ?? '') : '');
            $acts[] = ['op' => 'move', 'card_id' => (int) $c->id, 'week' => $week, 'day' => $day, 'pair' => $p] + ($code !== '' ? ['auditorium_code' => $code] : []);
        }

        // Huquq, sana (eski va yangi — bugun / o'tgan yopiq), kartadagi kutilayotgan so'rov — Takliflar qoidasi (o'qituvchi darajasi).
        $rights = ['level' => 'teacher', 'view' => false, 'tid' => (int) $actor->id, 'kafedra' => null, 'keys' => $keys];
        $chk = $this->suggestionApplyCheck($rights, $board, ['card_id' => (int) $card->id, 'actions' => $acts]);
        if ($chk['mode'] === null) {
            return $no($chk['code'], (string) $chk['why'], [], $info + ['rooms' => $rooms]);
        }
        // Rejim: shu haftada to'qnashuvda (qizil yoki sariq) yoki joylanmagan bo'lsa — "fix", aks holda — "request" (yangi joy holatidan oldin).
        $needsFix = $parts->contains(fn (TimetableCard $c) => $this->responsibleNeedsFix($board, $c, $week));
        // Yangi joy to'liq toza (barcha bo'laklar ko'chgan holatda, sinab — rollback): guruh, o'qituvchi, xona, sig'im, bronlar, blok yaxlitligi.
        $errors = $this->responsibleMoveErrors($board, $moves, $week, $day, $room);
        if ($errors) {
            return $no(422, implode(' · ', $errors), $errors, $info + ['rooms' => $rooms]);
        }
        $mode = $needsFix ? 'fix' : 'request';

        return ['status' => 200, 'body' => ['ok' => true, 'mode' => $mode, 'conflicts' => [], 'rooms' => $rooms,
            'message' => $mode === 'fix' ? "Darhol qo'llanadi (to'qnashuvni tuzatish, faqat shu hafta)."
                : "So'rov kafedra mudiri va registrator ofisiga yuboriladi (dars to'qnashuvsiz joylashgan)."] + $info,
            'ctx' => ['board' => $board, 'week' => $week, 'acts' => $acts]];
    }

    /**
     * Dars shu haftada "to'qnashuvda" (fan mas'uli oqimi): joylanmagan (bekor qilinmagan) yoki qizil (weeklyLessonNeedsFix — o'qituvchi /
     * guruh band, sikl bilan o'qituvchi band) yoki sariq: xona band (haftalik dars yoki sikl darsi bilan), hozirgi xona sig'maydi yoki nofaol.
     */
    private function responsibleNeedsFix(TimetableBoard $board, TimetableCard $card, int $week): bool
    {
        if ($this->weeklyLessonNeedsFix($board, $card, $week)) {
            return true;
        }
        $ov = TimetableCardOverride::where('card_id', $card->id)->where('week', $week)->first();
        if ($ov && $ov->cancelled) {
            return false;
        }
        $place = $this->suggestionPlace($card, $week);
        if (!$place) {
            return false;
        }
        foreach ((array) ($this->weeklyConflicts($board, null, [(int) $card->id => true], $week, true)[$card->id][$week]['items'] ?? []) as $it) {
            if (($it['kind'] ?? '') === 'room_busy') {
                return true;
            }
        }
        $date = $this->weekStartDate($board, $card, $week)->addDays($place[0] - 1)->toDateString();
        foreach ($this->weeklyProposalBusy($card, $week, $place[0], $place[1], $date) as $m) {
            if (str_starts_with($m, 'Xona ')) {
                return true;
            }
        }
        $code = (string) ($ov?->auditorium_code ?: $card->auditorium_code);
        $room = $code !== '' ? Auditorium::where('code', $code)->first() : null;

        return $code !== '' && (!$room || $this->suggestionRoomProblem($board, $card, $room) !== null);
    }

    /** Yangi joy xatolari: barcha bo'laklar ko'chgan holatda (sinab — rollback) agendaWeekErrors, amaliy dars yaxlitligi (birlik). */
    private function responsibleMoveErrors(TimetableBoard $board, array $moves, int $week, int $day, ?Auditorium $room): array
    {
        $orig = [];
        $sugMoves = [];
        foreach ($moves as [$c, $p]) {
            $orig[(int) $c->id] = $this->suggestionPlace($c, $week);
            $sugMoves[] = [$c, $week, $day, $p];
        }
        foreach ($moves as [$c]) {
            if ($why = $this->teacherFixBlockPart($board, $c, $week, $orig[(int) $c->id], $sugMoves)) {
                return [$why];
            }
        }
        $errors = [];
        $snap = $this->unitSnapshot(array_map(fn ($m) => [$m[0], $week], $moves));
        DB::beginTransaction();
        try {
            foreach ($moves as [$c, $p]) {
                TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week],
                    ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]
                        + ($room ? ['auditorium_code' => $room->code, 'auditorium_name' => $room->name] : []));
            }
            // Amaliy dars yaxlit (birlik): yakuniy holatda bo'laklar bitta kunda, ketma-ket.
            $errors = $snap ? $this->unitIntegrityErrors($snap) : [];
            foreach ($moves as [$c, $p]) {
                [$e, $roomCode] = $this->agendaWeekErrors($board, $c, $week, $day, $p, $room);
                // Umumiy (raqamsiz) xona — sig'im tekshirilmaydi (umumiy bajaruvchidagi suggestionRoomProblem bilan bir xil).
                $eff = $room ?? ($roomCode !== '' ? Auditorium::where('code', $roomCode)->first() : null);
                if ($eff && $this->isSharedRoom((string) $eff->name)) {
                    $e = array_values(array_filter($e, fn ($m) => !str_starts_with((string) $m, "Xona sig'maydi")));
                }
                $errors = array_merge($errors, $e);
            }
        } finally {
            DB::rollBack();
        }

        return array_values(array_unique($errors));
    }

    /**
     * Tanlangan vaqt uchun xonalar (check oynasi) — cheklovsiz (~650 xona; front avval 50 tasini chizadi, qidiruv — hammasidan): barcha faol
     * xonalar (nofaol — chiqmaydi). Tartib — hozirgi (shu haftadagi) xona eng tepada, so'ng bo'shlari, so'ng band / sig'maydiganlari, oxirida —
     * boshqa o'qituvchiga biriktirilganlari; har guruh ichida yaqinlik (rankRooms, $proximity: bino → bo'lim → qavat, so'ng ballar).
     * Tanlab bo'lmaydigan xona turi (kind): assigned — bo'lak o'qituvchisiga ruxsat etilmagan (auditorium_teacher: boshqa o'qituvchiga
     * biriktirilgan, umumiy emas; note — "Biriktirilgan: OSTANAQULOV SH. F.", note_full — to'liq F.I.Sh.), busy — boshqa dars bilan band
     * (bandlik — barcha bo'laklar shu vaqtga ko'chgan holatda, sinab: unitBusy, haftalik va sikl darslari bilan; haftalik dars xonani to'liq
     * band qiladi), capacity — sig'maydi (talabalar xona sig'imidan ko'p); band va sig'maydigan — busy, cap bilan.
     * Har band xonada tuzilgan ro'yxat busy_items: [{teacher ("UMAROV J. A."), teacher_full, groups[], subject, time ("08:30–11:20"), date,
     * dates, card_ids, view, week}] — front matnni parse qilmaydi; cap: {volume, students, over}.
     */
    private function responsibleMoveRooms(TimetableBoard $board, array $moves, int $week, int $day, string $date, array $partRooms = []): array
    {
        $base = $this->roomRankBase();
        $parts = collect(array_map(fn ($m) => $m[0], $moves));
        $first = $parts->first();
        $ov = TimetableCardOverride::where('card_id', $first->id)->where('week', $week)->first();
        $current = (string) ($ov?->auditorium_code ?: $first->auditorium_code);
        // Bo'laklar har xil xonada: "hozirgi xona" qatori yo'q (birinchi qator — "har bo'lak o'z xonasida"), har bo'lak xonasi — oddiy qator,
        // boshqa o'qituvchiga biriktirilgani ham (kulrang, sababi bilan); tanlansa — barcha bo'laklar shu xonaga.
        $mixed = count($partRooms) > 1;
        $ok = fn ($r) => $parts->every(fn (TimetableCard $c) => $this->auditoriumAllowedForCard($r, $c, $base['teacher']));
        // Barcha faol xonalar: ruxsatsizlari ham ro'yxatda (kimga biriktirilgani bilan, tanlab bo'lmaydi) — "nega yo'q" degan savol qolmasin.
        $rooms = $base['active'];
        $busy = ['rooms' => [], 'items' => ['rooms' => []]];
        DB::beginTransaction();
        try {
            foreach ($moves as [$c, $p]) {
                TimetableCardOverride::updateOrCreate(['card_id' => $c->id, 'week' => $week], ['day' => $day, 'pair' => $p, 'start_half' => 0, 'cancelled' => false]);
            }
            $busy = $this->unitBusy($board, TimetableCard::whereIn('id', $parts->pluck('id'))->get()->each(fn ($c) => $c->setRelation('board', $board)),
                [$date => true], true);
        } catch (\Throwable $e) {
            Log::warning('[ResponsibleMove] xona bandligi: ' . $e->getMessage());
        } finally {
            DB::rollBack();
        }
        $times = $this->responsibleBusyTimes($board);
        // Biriktirilgan xonalar egalari (kimga biriktirilgani — note va title uchun): faqat ro'yxatdagi tanlab bo'lmaydiganlari.
        $ownerNames = $this->responsibleRoomOwnerNames($base['teacher'], $rooms->reject($ok)->all());
        $out = [];
        foreach ($this->rankRooms($first, $rooms, $current !== '' ? $current : null, $this->roomHabitForCard($board, $first), true) as [$r, $why]) {
            $code = (string) $r->code;
            $assigned = !$ok($r);
            // Biriktirilgan xona — faqat shu holat (bandlik va sig'im ko'rsatilmaydi: tanlab bo'lmaydi).
            $b = $assigned ? null : ($busy['rooms'][$code] ?? null);
            // Sig'im: xona talabalarga kichik (har bo'lak bo'yicha; umumiy xona va bandlik tekshirilmaydigan karta — tekshirilmaydi).
            $cap = null;
            foreach ($assigned ? [] : $parts as $c) {
                if ($this->suggestionRoomProblem($board, $c, $r) !== null) {
                    $n = $this->cardStudents($c);
                    $cap = ['volume' => (int) $r->volume, 'students' => $n, 'over' => $n - (int) $r->volume];
                    break;
                }
            }
            $items = array_map(fn ($it) => [
                'teacher' => self::teacherShortName($it['by_teacher'] ?? null), 'teacher_full' => $it['by_teacher'] ?? null,
                'groups' => (array) ($it['by_groups'] ?? []), 'subject' => $it['subject'] ?? null,
                'time' => $this->responsibleBusyTime($times, $it), 'date' => $it['dates'][0] ?? null, 'dates' => $it['dates'] ?? [],
                'card_ids' => $it['card_ids'] ?? [], 'view' => $it['view'] ?? 'week', 'week' => $it['week'] ?? null,
            ], $b !== null ? (array) ($busy['items']['rooms'][$code] ?? []) : []);
            $kind = $assigned ? 'assigned' : ($b !== null ? 'busy' : ($cap ? 'capacity' : null));
            // Biriktirilgan: qisqa nomi — qatorda ("Biriktirilgan: OSTANAQULOV SH. F."), to'liq F.I.Sh. — title'da (note_full).
            $owner = $kind === 'assigned' ? $this->responsibleRoomOwner($r, $base['teacher'], $ownerNames) : null;
            $out[] = ['code' => $code, 'name' => (string) ($r->name ?: $r->code), 'volume' => (int) $r->volume, 'why' => $kind === null ? $why : null,
                'building_name' => (string) ($r->building_name ?? ''), 'auditorium_type_name' => (string) ($r->auditorium_type_name ?? ''),
                'current' => !$mixed && $code === $current, 'free' => $kind === null, 'kind' => $kind,
                'note' => $kind === 'assigned' ? ($owner ? 'Biriktirilgan: ' . $owner['short'] : "Boshqa o'qituvchiga biriktirilgan") : null,
                'note_full' => $owner ? 'Biriktirilgan: ' . $owner['full'] : null,
                'busy' => $b !== null ? self::roomBusyText('Band: ', (string) $b) : null, 'busy_items' => $items, 'cap' => $cap];
        }
        // Barqaror saralash (yaqinlik tartibi guruh ichida saqlanadi): hozirgi → bo'sh → band / sig'maydigan → biriktirilgan.
        $rank = fn (array $x) => [$x['current'] ? 0 : 1, $x['free'] ? 0 : ($x['kind'] === 'assigned' ? 2 : 1)];
        usort($out, fn ($a, $b) => $rank($a) <=> $rank($b));

        return $out;
    }

    /**
     * Bo'laklarning shu haftadagi xonalari (takrorsiz, bo'laklar tartibida): [{code, name, problem}]; problem — o'z bo'lagi uchun yaroqsizligi
     * ("nofaol", "sig'maydi", "Biriktirilgan: OSTANAQULOV SH. F." — kimga biriktirilgani (nomi topilmasa — "boshqa o'qituvchiga
     * biriktirilgan"), "ro'yxatda yo'q") yoki null. Xonasiz bo'lak — kirmaydi.
     */
    private function responsiblePartRooms(TimetableBoard $board, $parts, int $week): array
    {
        $base = $this->roomRankBase();
        $out = [];
        foreach ($parts as $c) {
            $ov = TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->first();
            $code = (string) ($ov?->auditorium_code ?: $c->auditorium_code);
            if ($code === '' || isset($out[$code]) && $out[$code]['problem'] !== null) {
                continue;
            }
            $r = $base['all'][$code] ?? null;
            $owner = $r && !$this->auditoriumAllowedForCard($r, $c, $base['teacher'])
                ? ($this->responsibleRoomOwner($r, $base['teacher'], $this->responsibleRoomOwnerNames($base['teacher'], [$r])) ?? ['short' => null]) : null;
            $problem = !$r ? "ro'yxatda yo'q" : (!$r->active ? 'nofaol'
                : ($owner !== null ? ($owner['short'] ? 'Biriktirilgan: ' . $owner['short'] : "boshqa o'qituvchiga biriktirilgan")
                    : ($this->suggestionRoomProblem($board, $c, $r) !== null ? "sig'maydi" : null)));
            $out[$code] = ['code' => $code, 'name' => $r ? (string) ($r->name ?: $code) : $code, 'problem' => $problem];
        }

        return array_values($out);
    }

    /** Bandlik vaqti jadvallari: row — qo'ng'iroq jadvali qatori (haftalik dars), hour — kunning akademik soati (sikl darsi, hemisSlotTimes). */
    private function responsibleBusyTimes(TimetableBoard $board): array
    {
        $bell = $board->bell_schedule ?: TimetableBoard::defaultBellSchedule((int) $board->pairs_per_day);
        $rows = [];
        foreach (array_values(array_filter((array) $bell, fn ($it) => ($it['type'] ?? 'pair') === 'pair' && !empty($it['start']) && !empty($it['end']))) as $i => $it) {
            $rows[$i + 1] = [substr((string) $it['start'], 0, 5), substr((string) $it['end'], 0, 5)];
        }

        return ['row' => $rows, 'hour' => $this->hemisSlotTimes($board)];
    }

    /** "08:30–11:20": bandlik yozuvining hour_from .. hour_to (oxiridan keyingi) oralig'i; aniqlanmasa — bo'sh. */
    private function responsibleBusyTime(array $times, array $it): string
    {
        $t = $times[($it['hour_unit'] ?? 'row') === 'hour' ? 'hour' : 'row'];
        $from = (int) ($it['hour_from'] ?? 0);
        $to = (int) ($it['hour_to'] ?? 0) - 1;

        return isset($t[$from], $t[$to]) ? $t[$from][0] . '–' . $t[$to][1] : '';
    }

    /**
     * "UMAROV JASUR AKMALOVICH" → "UMAROV J. A." (familiya + bosh harflar; "o'g'li" / "qizi" tashlanadi); bo'sh — null. O'zbek qo'sh
     * harflari — bitta harf: "OSTANAQULOV SHAXBOZ FARXODOVICH" → "OSTANAQULOV SH. F." (Sh, Ch; O', G' — tutuq belgisi bilan).
     */
    private static function teacherShortName(?string $full): ?string
    {
        $w = preg_split('/\s+/u', trim((string) $full), -1, PREG_SPLIT_NO_EMPTY);
        if (!$w) {
            return null;
        }
        $rest = array_filter(array_slice($w, 1), fn ($x) => !preg_match("/^(o['ʻ‘’`]?g['ʻ‘’`]?li|ugli|qizi|kizi)$/iu", $x));
        $initial = fn (string $x) => preg_match("/^(sh|ch|[og]['ʻ‘’`])/iu", $x, $m)
            ? mb_strtoupper(preg_replace("/['ʻ‘’`]/u", "'", $m[1])) : mb_strtoupper(mb_substr($x, 0, 1));

        return trim($w[0] . ' ' . implode(' ', array_map(fn ($x) => $initial($x) . '.', $rest)));
    }

    /**
     * Xonalar egalari (auditorium_teacher, umumiy emas): [o'qituvchi id => to'liq F.I.Sh.] — faqat $rooms xonalariga biriktirilganlar,
     * bitta so'rov. $map — auditoriumTeacherMap (roomRankBase()['teacher']).
     */
    private function responsibleRoomOwnerNames(array $map, iterable $rooms): array
    {
        $ids = [];
        foreach ($rooms as $r) {
            $a = $map[(string) $r->id] ?? null;
            if ($a && !$a['is_general'] && $a['teacher_id']) {
                $ids[(int) $a['teacher_id']] = true;
            }
        }

        return $ids ? Teacher::whereIn('id', array_keys($ids))->pluck('full_name', 'id')->map(fn ($n) => trim((string) $n))->all() : [];
    }

    /** Xona biriktirilgan o'qituvchi: ['short' => "OSTANAQULOV SH. F.", 'full' => to'liq F.I.Sh.]; umumiy / biriktirilmagan / nomi yo'q — null. */
    private function responsibleRoomOwner(Auditorium $r, array $map, array $names): ?array
    {
        $a = $map[(string) $r->id] ?? null;
        $full = $a && !$a['is_general'] && $a['teacher_id'] ? ($names[(int) $a['teacher_id']] ?? '') : '';

        return $full !== '' ? ['short' => self::teacherShortName($full), 'full' => $full] : null;
    }

    /** Eski va yangi joy matni: "Seshanba, 10.11.2026 10:00–11:20" (ko'p bo'lakli — birinchi bo'lak boshidan oxirgisi oxirigacha). */
    private function responsibleMoveLabels(TimetableBoard $board, array $moves, int $week, int $day): array
    {
        $first = $moves[0][0];
        $old = $this->suggestionSlot($board, $first, $week);
        $names = $board->day_names ?: TimetableBoard::DEFAULT_DAY_NAMES;
        $last = end($moves);
        $from = $this->rangeFor($first, $moves[0][1], 0);
        $to = $this->rangeFor($last[0], $last[1], 0);
        $date = $this->weekStartDate($board, $first, $week)->addDays($day - 1);

        return ['old' => $old['text'] . ($old['room'] !== '' ? ', xona ' . $old['room'] : ''),
            'new' => ($names[$day - 1] ?? $day . '-kun') . ', ' . $date->format('d.m.Y') . ' ' . $this->halfRangeTime($board, [$from[0], $to[1]])];
    }

    /** Takliflar route'i (SUGGESTION_ROUTE) bilan ichki so'rov — tarix yozuvi (draft) uchun. */
    private function responsibleSubRequest(TimetableBoard $board, array $payload): Request
    {
        $sub = Request::create('/', 'POST', $payload);
        $route = (new \Illuminate\Routing\Route('POST', '/', []))->name(self::SUGGESTION_ROUTE);
        $route->bind($sub);
        $route->setParameter('board', $board);
        $sub->setRouteResolver(fn () => $route);

        return $sub;
    }
}

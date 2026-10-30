<?php

/*
 * Bandlik va haftalik to'qnashuvlarning ESKI (butun doska, Eloquent modellar) hisobi — yangi yengil versiya bilan natija
 * aynan bir xilligini tekshirish uchun namuna. Kontroller ichida (yopiq metodlariga kirish uchun) bajariladi.
 */

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\TimetableBoard;
use Illuminate\Support\Carbon;

if (!function_exists('legacyUnitBusy')) {
    function legacyUnitBusy(TimetableBoard $board, $unitCards): array
    {
        $ctl = app(TimetableController::class);

        return Closure::bind(function () use ($board, $unitCards) {
            $ids = array_flip($unitCards->pluck('id')->map(fn ($id) => (int) $id)->all());
            $state = $this->occupancyState($board->id);
            [$units, $calendar] = $this->cycleInternalUnits($board);
            $entries = array_merge($this->weeklyOccupancy($board, $state),
                $this->cycleOccupancy($board, array_values(array_filter($units, fn ($u) => $u['placement'] !== null)), $calendar));
            $mine = [];
            $others = [];
            foreach ($entries as $e) {
                if (isset($ids[$e['card']])) {
                    $mine[] = $e;
                } else {
                    $others[$e['date']][] = $e;
                }
            }
            $hits = ['teachers' => [], 'rooms' => []];
            $peak = [];
            $unknown = [];
            $exclusive = [];
            foreach ($mine as $m) {
                $over = array_filter($others[$m['date']] ?? [], fn ($o) => $o['from'] < $m['to'] && $m['from'] < $o['to']);
                $inRoom = [];
                foreach ($over as $o) {
                    if ($o['teacher_id']) {
                        $hits['teachers'][$o['teacher_id']][$o['label']][$m['date']] = true;
                    }
                    if ($o['room'] && !$o['free_room']) {
                        $hits['rooms'][$o['room']][$o['label']][$m['date']] = true;
                        $inRoom[$o['room']][] = $o;
                        if ($m['kind'] === 'weekly' || $o['kind'] === 'weekly') {
                            $exclusive[$o['room']] = true;
                        }
                    }
                }
                foreach ($inRoom as $code => $list) {
                    foreach (array_unique(array_column($list, 'from')) as $t) {
                        $t = max($t, $m['from']);
                        $loads = array_column(array_filter($list, fn ($o) => $o['from'] <= $t && $t < $o['to']), 'load', 'load_key');
                        $peak[$code] = max($peak[$code] ?? 0, array_sum($loads));
                        $unknown[$code] = ($unknown[$code] ?? false) || in_array(0, $loads, true);
                    }
                }
            }
            $volumes = $hits['rooms'] ? Auditorium::whereIn('code', array_keys($hits['rooms']))->pluck('volume', 'code')->all() : [];
            $mineStudents = (int) $unitCards->max('students');
            foreach (array_keys($hits['rooms']) as $code) {
                $vol = (int) ($volumes[$code] ?? 0);
                $over = isset($exclusive[$code]) || $vol <= 0 || $mineStudents <= 0 || !empty($unknown[$code]) || ($peak[$code] ?? 0) + $mineStudents > $vol;
                if (!$over) {
                    unset($hits['rooms'][$code]);
                }
            }
            $fmt = fn (string $d) => Carbon::parse($d)->format('d.m');
            foreach ($hits as $type => $byId) {
                foreach ($byId as $id => $byLabel) {
                    $parts = [];
                    foreach ($byLabel as $label => $dates) {
                        $ds = array_keys($dates);
                        sort($ds);
                        $parts[] = $label . ', ' . $fmt($ds[0]) . (count($ds) > 1 ? '–' . $fmt(end($ds)) : '');
                    }
                    $hits[$type][$id] = implode('; ', $parts);
                }
            }

            return $hits;
        }, $ctl, TimetableController::class)();
    }

    /** Eski weeklyConflicts (butun doska holati) — natija karta va hafta bo'yicha tartiblangan. */
    function legacyWeeklyConflicts(TimetableBoard $board): array
    {
        $ctl = app(TimetableController::class);

        return Closure::bind(function () use ($board) {
            $state = $this->occupancyState($board->id);
            $buckets = [];
            $placed = [];
            foreach ($this->weeklyOccupancy($board, $state) as $e) {
                $placed[$e['card']][$e['week']] = [$e['day'], $e['pair'], $e['sh'], $e['date']];
                $x = [$e['from'], $e['to'], $e['card'], $e['week']];
                if ($e['teacher_id']) {
                    $buckets['t|' . $e['teacher_id'] . '|' . $e['date']][] = $x;
                }
                if ($e['room']) {
                    $buckets['r|' . $e['room'] . '|' . $e['date']][] = $x;
                }
                foreach ($e['groups'] as $g) {
                    $buckets['g|' . $g . '|' . $e['date']][] = $x;
                }
            }
            $cand = [];
            foreach ($buckets as $list) {
                $n = count($list);
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        [$af, $at, $aid, $aw] = $list[$i];
                        [$bf, $bt, $bid, $bw] = $list[$j];
                        if ($aid !== $bid && $af < $bt && $bf < $at) {
                            $cand[$aid . '|' . $aw][$bid] = true;
                            $cand[$bid . '|' . $bw][$aid] = true;
                        }
                    }
                }
            }
            $out = [];
            foreach ($cand as $key => $others) {
                [$id, $w] = array_map('intval', explode('|', $key));
                [$d, $p, $sh, $date] = $placed[$id][$w];
                $narrow = ['cards' => array_intersect_key($state['cards'], $others + [$id => true])] + $state;
                $errs = $this->findWeekConflicts($state['cards'][$id], $w, $d, $p, $sh, $narrow);
                if ($errs) {
                    $out[$id][$w] = ['text' => implode('; ', $errs), 'date' => $date, 'day' => $d, 'pair' => $p];
                }
            }
            ksort($out);
            foreach ($out as &$weeks) {
                ksort($weeks);
            }

            return $out;
        }, $ctl, TimetableController::class)();
    }

    /**
     * Doskadagi har bir dars birligi (biriktirish oynasidagidek: fakultet, yo'nalish, kurs, fan, tur, guruh/oqim) va har bir karta
     * uchun yangi unitBusy eski bilan aynan bir xil; haftalik to'qnashuvlar ham. Farq bo'lsa — qaysi birlikda.
     */
    function assertBusyMatchesLegacy(TimetableBoard $board): int
    {
        $ctl = app(TimetableController::class);
        $busy = new ReflectionMethod($ctl, 'unitBusy');
        $weekly = new ReflectionMethod($ctl, 'weeklyConflicts');
        $units = [];
        foreach (\App\Models\TimetableCard::where('board_id', $board->id)->orderBy('id')->get() as $c) {
            $k = implode('|', [$c->faculty_name, $c->specialty_name, $c->course, $c->subject_name, $c->training_type,
                $c->training_type === 'lecture' ? $c->oqim_label : $c->group_name]);
            $units[$k][] = $c->id;
            $units['#' . $c->id] = [$c->id];
        }
        foreach ($units as $k => $ids) {
            $cards = \App\Models\TimetableCard::whereIn('id', $ids)->orderBy('id')->get();
            request()->attributes->remove('timetable.cycle_units.' . $board->id);
            expect($busy->invoke($ctl, $board, $cards))->toBe(legacyUnitBusy($board, $cards), 'birlik: ' . $k);
        }
        expect($weekly->invoke($ctl, $board))->toBe(legacyWeeklyConflicts($board));

        return count($units);
    }
}

<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Smenani almashtiring" maslahati (sikl kartasi to'qnashuvlar oynasi, o'qituvchi bandligi ikki sikl amaliyoti orasida): guruhchaning
 * har bir smena varianti (cycleShiftOptions) blok sanalarida xotirada sinab ko'riladi — boshqa sikl darslari bilan (cycleConflicts:
 * o'qituvchi, guruh, xona) va haftalik darslar bilan (o'qituvchi, guruh, xona). Natija:
 *   free    — "IV para (13:30) ga o'tkazing — bo'sh";
 *   partial — eng kam to'qnashuvlisi: "13:30 ga o'tkazilsa faqat 19.11 (payshanba) qoladi: pp/2024-01b, Farmakologiya";
 *   none    — hech biri yaxshilamaydi: "boshqa o'qituvchi biriktiring".
 * Faqat o'qiydi; oynada bir marta (ochilganda) so'raladi.
 */
trait TimetableCycleShiftAdvice
{
    private const SHIFT_DOW = [1 => 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'];
    /** "Qoladi" matnida ko'rsatiladigan sanalar va darslar soni (qolgani "+N ta"). */
    private const SHIFT_LIST_DATES = 4;
    private const SHIFT_LIST_WITH = 3;

    public function cycleShiftAdvice(Request $request, TimetableBoard $board)
    {
        $data = $request->validate(['key' => 'required|string|max:500']);
        [$units, $calendar] = $this->placedCycleUnits($board);
        $i = null;
        foreach ($units as $k => $u) {
            if ($u['key'] === $data['key']) {
                $i = $k;
                break;
            }
        }
        abort_if($i === null, 404, 'Sikl bloki topilmadi.');

        return response()->json(['advice' => $this->shiftAdviceFor($board, $units, $calendar, $i)]);
    }

    /** Birlik uchun maslahat: ['kind' => free|partial|none, 'text', 'start', 'label', 'remaining' => [['date', 'with' => [...]], ...]]. */
    private function shiftAdviceFor(TimetableBoard $board, array $units, array $calendar, int $i): array
    {
        $u = $units[$i];
        $none = ['kind' => 'none', 'text' => "boshqa o'qituvchi biriktiring", 'start' => null, 'label' => null, 'remaining' => []];
        if ($u['placement'] === null) {
            return $none;
        }
        // Faqat shu blok sanalariga tegadigan birliklar (butun doska emas — har variant uchun cycleConflicts tez). Blok oxiri — ish
        // kunlari bo'yicha (kalendarda yakshanba va bayramlar ham bor: from + days haqiqiy oxiri emas).
        $span = fn (array $x) => $this->cycleUnitSpan($calendar, $x);
        [$uFrom, $uTo] = $span($u);
        $near = [];
        foreach ($units as $k => $x) {
            $xs = $k === $i ? null : $span($x);
            if ($k === $i || ($xs !== null && $uFrom !== null && $xs[0] <= $uTo && $uFrom <= $xs[1])) {
                $near[] = $x;
            }
        }
        $self = array_search($u['key'], array_column($near, 'key'), true);
        $cur = (int) (((array) (((array) ($u['placement']->lecture_slots ?? []))['shift_g'] ?? []))[(string) $u['unit']] ?? 0);
        $now = $this->shiftRemaining($board, $near, $calendar, $self);
        $best = null;
        foreach ($this->cycleShiftOptions($board, $this->cycleShiftLimit($board, (string) $u['unit'], (string) $u['subject'])) as $opt) {
            if ((int) $opt['start'] === max(1, $cur)) {
                continue;
            }
            $trial = $near;
            $slots = (array) ($u['placement']->lecture_slots ?? []);
            $slots['shift_g'] = array_replace((array) ($slots['shift_g'] ?? []), [(string) $u['unit'] => (int) $opt['start']]);
            $trial[$self]['placement'] = (object) array_replace((array) $u['placement'], ['lecture_slots' => $slots]);
            $rem = $this->shiftRemaining($board, $trial, $calendar, $self);
            if (!$rem) {
                return ['kind' => 'free', 'text' => $opt['label'] . " ga o'tkazing — bo'sh", 'start' => (int) $opt['start'], 'label' => $opt['label'],
                    'remaining' => []];
            }
            if ($best === null || count($rem) < count($best[1])) {
                $best = [$opt, $rem];
            }
        }
        if ($best === null || count($best[1]) >= count($now)) {
            return $none;
        }
        [$opt, $rem] = $best;
        $time = $this->cycleRowTime($board, (int) $opt['start']);
        $dates = $this->shiftShortList(array_map(fn ($d) => Carbon::parse($d)->format('d.m') . ' (' . self::SHIFT_DOW[Carbon::parse($d)->dayOfWeekIso] . ')',
            array_keys($rem)), self::SHIFT_LIST_DATES, ', ');
        // "Qoladi:" — faqat shu o'qituvchining boshqa amaliy darslari (ma'ruza oqimi guruhlari emas); guruh/xona bandligi — faqat sanasi.
        $with = $this->shiftShortList(array_values(array_unique(array_merge([], ...array_values($rem)))), self::SHIFT_LIST_WITH, '; ');

        return ['kind' => 'partial', 'text' => "{$time} ga o'tkazilsa faqat {$dates} qoladi" . ($with !== '' ? ": {$with}" : ''),
            'start' => (int) $opt['start'], 'label' => $opt['label'], 'remaining' => array_map(fn ($d) => ['date' => $d, 'with' => $rem[$d]], array_keys($rem))];
    }

    /** Uzun ro'yxat: dastlabki $max tasi va "+N ta". */
    private function shiftShortList(array $items, int $max, string $sep): string
    {
        return count($items) <= $max ? implode($sep, $items) : implode($sep, array_slice($items, 0, $max)) . ' +' . (count($items) - $max) . ' ta';
    }

    /**
     * Birlikning kelgusi to'qnashuvlari qoladigan sanalar: sana => [shu o'qituvchining boshqa amaliy darsi ("guruh, fan"), ...]
     * (tartiblangan). Sana — har qanday to'qnashuv qolsa: sikl darslari bilan — cycleConflicts (o'qituvchi, guruh, xona); haftalik
     * darslar bilan — amaliy darslari vaqtida o'qituvchi, xona yoki guruh band. Ro'yxatga faqat shu o'qituvchining amaliy darslari
     * kiradi: ma'ruza (oqim guruhlari) va boshqa o'qituvchilar darslari (guruh/xona bandligi) — faqat sana bo'lib qoladi.
     */
    private function shiftRemaining(TimetableBoard $board, array $units, array $calendar, int $self): array
    {
        $today = self::proposalToday();
        $u = $units[$self];
        $out = [];
        foreach ($this->cycleConflicts($board, $units, $calendar)[$u['key']]['items'] ?? [] as $it) {
            if (!empty($it['past']) || $it['date_from'] === null) {
                continue;
            }
            foreach ($this->cycleDatesBetween($calendar, $it['date_from'], $it['date_to'], $units[$self]) as $d) {
                $out[$d] ??= [];
            }
        }
        // Shu birlikning amaliy darslari (smena shularga ta'sir qiladi).
        $all = $this->cycleOccupancy($board, $units, $calendar);
        $isPractice = fn (array $e) => str_starts_with((string) $e['load_key'], 'c');
        $mine = array_values(array_filter($all, fn ($e) => $e['unit'] === $self && $e['date'] >= $today && $isPractice($e)));
        // Sikl: shu vaqtda o'qituvchining boshqa sikl amaliyoti (boshqa dars — umumiy "A + B" kartaning o'zi emas, cycleConflicts kabi).
        foreach ($mine as $m) {
            foreach ($all as $o) {
                if ($m['teacher_id'] && $o['unit'] !== $self && $o['lesson'] !== $m['lesson'] && $o['date'] === $m['date']
                    && $o['teacher_id'] === $m['teacher_id'] && $isPractice($o) && $o['from'] < $m['to'] && $m['from'] < $o['to']) {
                    $out[$m['date']][] = $units[$o['unit']]['group'] . ', ' . $units[$o['unit']]['subject'];
                }
            }
        }
        // Haftalik darslar: shu birlikning amaliy darslari vaqtida.
        if ($mine) {
            $dates = array_flip(array_column($mine, 'date'));
            $ids = array_flip(array_map(fn ($c) => (int) $c->id, $u['cards']));
            $scope = $u['cards'] ? $this->groupScopeKey($u['cards'][0]) : '';
            $groups = array_flip(array_map(fn ($g) => $scope . '|' . $g['group'], $this->focusUnitGroups($u)));
            $state = $this->weeklyStateOn($board, array_keys($dates), max(array_column($mine, 'to')), $ids);
            $weekly = $this->weeklyOccupancy($board, $state, $dates);
            // Haftalik qator (to'liq parali doskada bir qator = 2 akademik soat) → sikl soati.
            $rh = max(1, $this->cycleRowHours($board));
            foreach ($mine as $m) {
                foreach ($weekly as $o) {
                    [$oFrom, $oTo] = [($o['from'] - 1) * $rh + 1, ($o['to'] - 1) * $rh + 1];
                    if ($o['date'] !== $m['date'] || !($oFrom < $m['to'] && $m['from'] < $oTo)) {
                        continue;
                    }
                    $teacher = $m['teacher_id'] && $o['teacher_id'] === $m['teacher_id'];
                    $clash = $teacher
                        || ($m['room'] && !$m['free_room'] && $o['room'] === $m['room'] && !$o['free_room'])
                        || array_intersect_key($groups, array_flip((array) ($o['groups'] ?? [])));
                    if ($clash) {
                        $out[$m['date']] ??= [];
                        if ($teacher && (string) (($state['cards'][$o['card']] ?? null)?->training_type) !== 'lecture') {
                            $out[$m['date']][] = (string) $o['label'];
                        }
                    }
                }
            }
        }
        ksort($out);

        return array_map(fn ($l) => array_values(array_unique($l)), $out);
    }

    /** Birlik bloki: [birinchi, oxirgi] kalendar indeksi — ish kunlari (workIndices) bo'yicha; joylanmagan — [null, null]. */
    private function cycleUnitSpan(array $calendar, array $u): array
    {
        [, $workIndices] = $calendar;
        $pos = $u['from'] !== null ? array_search((int) $u['from'], $workIndices, true) : false;
        if ($pos === false) {
            return [null, null];
        }
        $last = $workIndices[min(count($workIndices) - 1, $pos + max(1, (int) $u['days']) - 1)];

        return [(int) $u['from'], (int) $last];
    }

    /** Kalendar (o'quv kunlari) bo'yicha birlik blokidagi [from, to] oralig'idagi sanalar. */
    private function cycleDatesBetween(array $calendar, string $from, ?string $to, array $u): array
    {
        [$dates, $workIndices] = $calendar;
        $pos = array_search((int) $u['from'], $workIndices, true);
        $out = [];
        for ($k = 0; $pos !== false && $k < (int) $u['days']; $k++) {
            $ci = $workIndices[$pos + $k] ?? null;
            $d = $ci !== null && isset($dates[$ci]) ? $dates[$ci]->toDateString() : null;
            if ($d !== null && $d >= $from && $d <= ($to ?? $from)) {
                $out[] = $d;
            }
        }

        return $out;
    }
}

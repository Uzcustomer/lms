<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;

/**
 * Sikl jadvali: "Faqat ma'ruza" surilganda yangi ma'ruza vaqtini to'sib turgan amaliyotni joylash (cycle-lecture-slots, cycle-time-proposal).
 * Usul (practice_place):
 *   keep — amaliyotga tegilmaydi (eski xatti-harakat: kesishsa — xato);
 *   swap — o'rin almashish (sukut, front): amaliyot ma'ruzaning bo'shagan joyiga (amaliy boshi = ma'ruzaning eski boshi), sig'masa yoki
 *          band bo'lsa — ma'ruzadan keyin yoki oldin (asl joyiga yaqinrog'i birinchi);
 *   after — ma'ruza tugashidan keyingi birinchi para chegarasidan;
 *   gap — ma'ruzadan keyin practice_gap para bo'sh qoldirib (I para ma'ruza, 1 para oraliq — amaliy III paradan);
 *   pair — tanlangan paradan (practice_pair).
 * Hamma usulda: kun chegarasi, yangi ma'ruza bilan kesishmaslik, o'qituvchi / xona / guruh bandligi (band bo'lsa — keyingi variant);
 * kun o'zgarmaydi, faqat soat; amaliy davomiyligi o'zgarmaydi; ma'ruza bilan kesishmaydigan amaliyot joyida qoladi.
 */
trait TimetableCyclePracticeSwap
{
    /** So'rovdagi usul: ['how' => keep|swap|after|gap|pair, 'gap' => para soni, 'pair' => para raqami]. */
    private function cyclePracticePlace(array $data): array
    {
        $how = (string) ($data['practice_place'] ?? '');

        return ['how' => in_array($how, ['swap', 'after', 'gap', 'pair'], true) ? $how : 'keep',
            'gap' => max(0, (int) ($data['practice_gap'] ?? 0)), 'pair' => max(1, (int) ($data['practice_pair'] ?? 1))];
    }

    /**
     * Amaliyot uchun nomzod boshlanishlar (tartib bo'yicha; kun chegarasi va yangi ma'ruza bilan kesishuv tekshirilgan): [[soat, usul], ...]
     * va hech biri bo'lmasa — sabab. $p0, $plen — amaliyotning hozirgi boshi va davomiyligi; $l0 — ma'ruzaning eski boshi; [$h, $h + $len) —
     * ma'ruzaning yangi joyi. Para chegarasi — qo'ng'iroq jadvali (to'liq parada 1 para = 2 soat qatori).
     */
    private function cyclePracticeCandidates(TimetableBoard $board, array $place, int $p0, int $plen, int $l0, int $h, int $len): array
    {
        $grid = $this->cycleGridHours($board);
        $rph = max(1, $this->cycleRowHours($board));
        $end = $h + $len;
        // Keyingi para chegarasi (>= x) va oldingisi (<= x).
        $after = $end + (($rph - (($end - 1) % $rph)) % $rph);
        $fit = fn (int $s) => $s >= 1 && $s + $plen - 1 <= $grid;
        $clear = fn (int $s) => !($s < $end && $h < $s + $plen);
        $time = fn (int $s) => $this->cycleHoursTime($board, $s, $s + $plen);
        switch ($place['how']) {
            case 'after':
            case 'gap':
            case 'pair':
                $s = match ($place['how']) {
                    'after' => $after,
                    'gap' => $after + $place['gap'] * $rph,
                    default => ($place['pair'] - 1) * $rph + 1,
                };
                if (!$fit($s)) {
                    return [[], "{$plen} soat " . ($s >= 1 ? $s . '-soatdan' : '') . " kun oxirigacha sig'maydi (kunda {$grid} soat)"];
                }
                if (!$clear($s)) {
                    return [[], 'tanlangan vaqt ' . $time($s) . " yangi ma'ruza bilan ustma-ust"];
                }

                return [[[$s, $place['how']]], null];
        }
        $list = [[$l0, 'swap']];
        $alts = [[$after, 'after']];
        if ($h - $plen >= 1) {
            $b = $h - $plen;
            $alts[] = [$b - (($b - 1) % $rph), 'before'];
        }
        // Asl joyiga yaqinrog'i birinchi (teng bo'lsa — keyin).
        usort($alts, fn ($a, $b) => abs($a[0] - $p0) <=> abs($b[0] - $p0) ?: ($a[1] === 'after' ? -1 : 1));
        $out = [];
        foreach (array_merge($list, $alts) as $c) {
            if ($fit($c[0]) && $clear($c[0]) && !isset($out[$c[0]])) {
                $out[$c[0]] = $c;
            }
        }

        return $out ? [array_values($out), null]
            : [[], "{$plen} soat ma'ruzaning eski joyiga ham, undan keyin ham, oldin ham sig'maydi"];
    }

    /**
     * Amaliyot qayerga ketgani — qisqa izoh: o'rin almashish — izohsiz; sig'madi / band bo'lgani uchun keyinga yoki oldinga; boshqa usullar.
     */
    private static function cycleSwapNote(array $w): string
    {
        $dir = ['after' => 'keyinga', 'before' => 'oldinga'][$w['how']] ?? null;
        if ($dir && !empty($w['why'])) {
            return ($w['why'] === 'busy' ? 'band' : "sig'madi") . ' — ' . $dir;
        }

        return ['swap' => '', 'after' => "ma'ruzadan keyin", 'before' => "ma'ruzadan oldin", 'gap' => 'oraliq bilan', 'pair' => 'tanlangan para',
            'pin' => 'surilgan'][$w['how']] ?? (string) $w['how'];
    }

    /** "d2/d23-08a 10:00 → 08:30, d2/d23-09b 10:00 → 12:00 (sig'madi — keyinga)" — so'rov tavsifi va xabarlar uchun. */
    private function cycleSwapsText(TimetableBoard $board, array $swaps): string
    {
        $t = $this->hemisSlotTimes($board);

        return implode(', ', array_map(function ($w) use ($t) {
            $note = self::cycleSwapNote($w);

            return $w['unit'] . ' ' . ($t[$w['from']][0] ?? $w['from'] . '-soat') . ' → ' . ($t[$w['to']][0] ?? $w['to'] . '-soat')
                . ($note !== '' ? ' (' . $note . ')' : '') . (!empty($w['busy']) ? ' — band' : '');
        }, $swaps));
    }
}

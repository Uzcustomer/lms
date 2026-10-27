<?php

namespace App\Services\HemisSync;

/**
 * HEMIS dars jadvalini LMS ichki jadvali va KTR bilan solishtirish va tuzatish rejasi (sof mantiq, DB'siz).
 *
 * Bitta guruhning barcha fan birliklari (guruh + HEMIS fani) birga ko'riladi — qo'shiladigan darslar
 * guruhning bo'sh vaqtiga tushishi kerak.
 *
 * Qoidalar (registrator ofisi bilan kelishilgan):
 *  - Balans fan + guruh bo'yicha: har dars turining SOATI va har qismning dars SONI (kunlar) KTR'ga teng
 *    bo'lishi kerak. Qismlar: L — ma'ruza, A — ma'ruzadan boshqa hamma tur (amaliy, laboratoriya,
 *    seminar, klinik...). A qismida bir kun — jurnalda bitta ustun (turlar aralash bo'lsa ham).
 *  - Bugun va undan oldingi kunlarga tegilmaydi; tuzatish ertadan boshlab, iloji boricha joriy haftada.
 *  - Faqat soat yetmasa — shu kundagi dars davomiga qo'shiladi (yangi kun = yangi jurnal ustuni).
 *  - Soni yetmasa — yangi kunga (shu qism darsi yo'q kunga) qo'yiladi.
 *  - Soat ortiqcha — keyingi darslar qisqartiriladi, lekin dars kamida 1 soat (yarim para) qoladi.
 *  - Tur almashtirish (amaliy ↔ laboratoriya) kun va soatni o'zgartirmaydi.
 *  - Kelajak (ertadan) darslari har safar barcha maydonlar bo'yicha solishtiriladi: sana, para vaqti,
 *    tur, o'qituvchi, xona — qo'shish / o'zgartirish / o'chirish amallari chiqadi.
 *
 * Soat birligi — akademik soat = yarim para = bitta yarim-slot (40 daqiqa).
 */
class SchedulePlanner
{
    /** Qo'shimcha dars qo'yiladigan eng oxirgi yarim-slot (17:10–17:50 uchun HEMIS'da yarim para yo'q). */
    private int $maxSlot;

    /**
     * @param array  $slotTimes yarim-slot => [boshlanish 'HH:MM', tugash 'HH:MM'] (1..12)
     * @param string $today     'Y-m-d' — shu kun va undan oldingisi o'zgartirilmaydi
     * @param array  $hemisPairs HEMIS'da mavjud para vaqtlari: ['HH:MM-HH:MM' => true]; bo'sh — tekshirilmaydi
     */
    public function __construct(private array $slotTimes, private string $today, private array $hemisPairs = [])
    {
        $this->maxSlot = min(11, max(1, (int) max(array_keys($slotTimes ?: [11 => 0]))));
    }

    /**
     * @param array $units  shu guruhning fan birliklari (formati — README pastda)
     * @param array $busy   boshqa (rejada yo'q) darslar bandligi: ['Y-m-d' => [slot => true]]
     * @param array $workDates ertadan boshlab dars kunlari: ['Y-m-d' => hafta raqami] (sana tartibida)
     *
     * Birlik (unit):
     *  key, subject_id, subject_name, base (bo'laklarsiz normallashgan nom),
     *  codes: [kod => 'L'|'A'], code_names: [kod => nom],
     *  ktr: [[kod => soat], ...] (KTR qatorlari tartibida, 1-qator = 1-hafta) yoki null,
     *  current_week: joriy hafta raqami,
     *  hemis: [[id, date, start, end, code, employee_id, employee_name, auditorium_code, auditorium_name]],
     *  plan:  [[date, week, slot, len, part, employee_id, employee_name, auditorium_code, auditorium_name]],
     *  fallback_employee: [id, name] | null — o'qituvchi biriktirilmasa (kafedra mudiri),
     *  fallback_room: [code, name] | null — xona biriktirilmasa ("Kafedra mudiri xonasi").
     */
    public function planGroup(array $units, array $busy, array $workDates): array
    {
        // Guruh bandligi: sana => slot => [[unitKey, part, base], ...]
        $occ = [];
        foreach ($busy as $date => $slots) {
            foreach ($slots as $slot => $_) {
                $occ[$date][(int) $slot][] = ['', '', ''];
            }
        }

        $state = [];
        foreach ($units as $u) {
            $st = $this->initUnit($u);
            foreach ($st['blocks'] as $b) {
                foreach ($b['slots'] as $s => $_) {
                    $occ[$b['date']][$s][] = [$u['key'], $b['part'], $u['base']];
                }
            }
            $state[$u['key']] = $st;
        }

        $out = [];
        foreach ($units as $u) {
            $out[] = $this->planUnit($u, $state[$u['key']], $occ, $workDates);
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------

    private function partOf(array $u, string $code): string
    {
        return $u['codes'][$code] ?? 'A';
    }

    private function hoursOf(string $start, string $end): int
    {
        [$h1, $m1] = array_map('intval', explode(':', substr($start, 0, 5)));
        [$h2, $m2] = array_map('intval', explode(':', substr($end, 0, 5)));

        return max(1, (int) round((($h2 * 60 + $m2) - ($h1 * 60 + $m1)) / 40));
    }

    /** Kodlar tartibi: KTR'da ko'p soatli kod birinchi (asosiy tur). */
    private function codesByPart(array $u, string $part): array
    {
        $tot = [];
        foreach ($u['codes'] as $code => $p) {
            if ($p === $part) {
                $tot[(string) $code] = 0;
            }
        }
        foreach ((array) ($u['ktr'] ?? []) as $row) {
            foreach ((array) $row as $code => $h) {
                if (isset($tot[(string) $code])) {
                    $tot[(string) $code] += (int) $h;
                }
            }
        }
        uksort($tot, fn ($a, $b) => [$tot[$b], (int) $a] <=> [$tot[$a], (int) $b]);

        return array_map('strval', array_keys($tot));
    }

    private function initUnit(array $u): array
    {
        // Kelajak (ertadan) reja darslari — kun + qism bo'yicha bloklar; har yarim-slotning turi va egasi.
        $blocks = [];
        foreach ((array) $u['plan'] as $p) {
            if ($p['date'] <= $this->today) {
                continue;
            }
            $k = $p['date'] . '|' . $p['part'];
            $blocks[$k] ??= ['date' => $p['date'], 'week' => (int) $p['week'], 'part' => $p['part'], 'slots' => [], 'new' => false];
            for ($i = 0; $i < max(1, (int) $p['len']); $i++) {
                $blocks[$k]['slots'][(int) $p['slot'] + $i] = [
                    'code' => null,
                    'emp' => $p['employee_id'] ?? null, 'emp_name' => $p['employee_name'] ?? null,
                    'room' => $p['auditorium_code'] ?? null, 'room_name' => $p['auditorium_name'] ?? null,
                ];
            }
        }
        foreach ($blocks as &$b) {
            ksort($b['slots']);
        }
        unset($b);

        // Har haftada A (va L) yarim-slotlariga KTR qatoridagi turlar navbat bilan beriladi.
        // KTR yo'q bo'lsa turlar taqsimoti noma'lum — HEMIS'dagi turi saqlanadi: o'sha vaqtdagi dars,
        // bo'lmasa o'sha kundagi, bo'lmasa shu qismda eng ko'p uchragan tur (o'zboshimcha "asosiy" tur emas).
        $hemisCode = null;
        if ($u['ktr'] === null) {
            $rowsByPart = [];
            foreach ((array) $u['hemis'] as $r) {
                if (isset($u['codes'][(string) $r['code']])) {
                    $rowsByPart[$u['codes'][(string) $r['code']]][] = $r;
                }
            }
            $hemisCode = function (string $part, string $date, int $slot) use ($rowsByPart): ?string {
                [$from, $to] = $this->slotTimes[$slot] ?? ['', ''];
                $rows = $rowsByPart[$part] ?? [];
                $sameDay = array_values(array_filter($rows, fn ($r) => $r['date'] === $date));
                foreach ($sameDay as $r) {
                    if (substr($r['start'], 0, 5) <= $from && $to <= substr($r['end'], 0, 5)) {
                        return (string) $r['code'];
                    }
                }
                if ($sameDay) {
                    return (string) $sameDay[0]['code'];
                }
                $freq = array_count_values(array_map(fn ($r) => (string) $r['code'], $rows));
                arsort($freq);

                return $freq ? (string) array_key_first($freq) : null;
            };
        }
        $byWeekPart = [];
        foreach ($blocks as $k => $b) {
            $byWeekPart[$b['week'] . '|' . $b['part']][] = $k;
        }
        foreach ($byWeekPart as $wp => $keys) {
            [$week, $part] = explode('|', $wp);
            $order = $this->codesByPart($u, $part);
            $primary = $order[0] ?? null;
            $queue = [];
            $row = (array) (($u['ktr'] ?? [])[(int) $week - 1] ?? []);
            foreach ($order as $code) {
                for ($i = 0; $i < (int) ($row[$code] ?? 0); $i++) {
                    $queue[] = $code;
                }
            }
            usort($keys, fn ($a, $b) => strcmp($blocks[$a]['date'], $blocks[$b]['date']));
            foreach ($keys as $k) {
                foreach ($blocks[$k]['slots'] as $s => &$slot) {
                    $slot['code'] = array_shift($queue) ?? ($hemisCode ? $hemisCode($part, $blocks[$k]['date'], $s) : null) ?? $primary;
                }
                unset($slot);
            }
        }

        return ['blocks' => array_values(array_filter($blocks, fn ($b) => $b['slots'] && $this->blockCodesOk($b)))];
    }

    private function blockCodesOk(array $b): bool
    {
        foreach ($b['slots'] as $s) {
            if ($s['code'] === null) {
                return false;
            }
        }

        return true;
    }

    private function isFree(array $occ, string $unitKey, string $base, string $part, string $date, int $slot): bool
    {
        if ($slot < 1 || $slot > $this->maxSlot) {
            return false;
        }
        foreach ($occ[$date][$slot] ?? [] as [$k, $p, $bs]) {
            // Bo'lak qo'shnisi (bir xil asosiy nom, bir xil qism, boshqa HEMIS fani) parallel tura oladi.
            if ($k === '' || $k === $unitKey || $p !== $part || $bs !== $base) {
                return false;
            }
        }

        return true;
    }

    private function occAdd(array &$occ, array $u, string $part, string $date, int $slot): void
    {
        $occ[$date][$slot][] = [$u['key'], $part, $u['base']];
    }

    private function occRemove(array &$occ, array $u, string $date, int $slot): void
    {
        foreach ($occ[$date][$slot] ?? [] as $i => [$k]) {
            if ($k === $u['key']) {
                unset($occ[$date][$slot][$i]);
                break;
            }
        }
    }

    private function sortBlocks(array &$blocks): void
    {
        usort($blocks, fn ($a, $b) => strcmp($a['date'], $b['date']) ?: strcmp($a['part'], $b['part']));
    }

    /** Hisob: KTR, o'tgan (HEMIS, bugungacha), kelajak (reja) — soat (kod) va son (qism). */
    private function tally(array $u, array $blocks): array
    {
        $ktrH = [];
        $ktrC = ['L' => 0, 'A' => 0];
        $ktrCurH = [];
        $ktrCurC = ['L' => 0, 'A' => 0];
        foreach ((array) ($u['ktr'] ?? []) as $i => $row) {
            $sum = ['L' => 0, 'A' => 0];
            foreach ((array) $row as $code => $h) {
                $code = (string) $code;
                if (!isset($u['codes'][$code])) {
                    continue;
                }
                $ktrH[$code] = ($ktrH[$code] ?? 0) + (int) $h;
                $sum[$this->partOf($u, $code)] += (int) $h;
                if ($i + 1 <= (int) ($u['current_week'] ?? 0)) {
                    $ktrCurH[$code] = ($ktrCurH[$code] ?? 0) + (int) $h;
                }
            }
            foreach ($sum as $p => $h) {
                if ($h > 0) {
                    $ktrC[$p]++;
                    if ($i + 1 <= (int) ($u['current_week'] ?? 0)) {
                        $ktrCurC[$p]++;
                    }
                }
            }
        }

        $pastH = [];
        $pastDays = ['L' => [], 'A' => []];
        foreach ((array) $u['hemis'] as $r) {
            if ($r['date'] > $this->today) {
                continue;
            }
            $code = (string) $r['code'];
            if (!isset($u['codes'][$code])) {
                continue;
            }
            $pastH[$code] = ($pastH[$code] ?? 0) + $this->hoursOf($r['start'], $r['end']);
            $pastDays[$this->partOf($u, $code)][$r['date']] = true;
        }

        $futH = [];
        $futC = ['L' => 0, 'A' => 0];
        foreach ($blocks as $b) {
            $futC[$b['part']]++;
            foreach ($b['slots'] as $s) {
                $futH[$s['code']] = ($futH[$s['code']] ?? 0) + 1;
            }
        }

        $D = [];
        foreach ($u['codes'] as $code => $_) {
            $code = (string) $code;
            $D[$code] = ($ktrH[$code] ?? 0) - ($pastH[$code] ?? 0) - ($futH[$code] ?? 0);
        }
        $C = [];
        foreach (['L', 'A'] as $p) {
            $C[$p] = $ktrC[$p] - count($pastDays[$p]) - $futC[$p];
        }

        return [
            'ktr_hours' => $ktrH, 'ktr_count' => $ktrC,
            'ktr_now_hours' => $ktrCurH, 'ktr_now_count' => $ktrCurC,
            'past_hours' => $pastH, 'past_count' => array_map('count', $pastDays),
            'future_hours' => $futH, 'future_count' => $futC,
            'hours_diff' => $D, 'count_diff' => $C,
        ];
    }

    private function planUnit(array $u, array $st, array &$occ, array $workDates): array
    {
        $blocks = $st['blocks'];
        $this->sortBlocks($blocks);
        $before = $this->tally($u, $blocks);
        $fixes = [];
        $notes = [];

        if ($u['ktr'] === null) {
            $notes[] = 'KTR kiritilmagan — balans tekshirilmadi, faqat jadval solishtirildi.';
        } elseif (empty($u['mirror'])) {   // sikl fani (mirror) — HEMIS ichki sikl jadvaliga aynan moslanadi, tuzatishsiz
            $this->fixSwaps($u, $blocks, $fixes);
            $this->fixCountSurplus($u, $blocks, $occ, $fixes, $notes);
            $this->fixCountDeficit($u, $blocks, $occ, $workDates, $fixes, $notes);
            $this->fixHoursDeficit($u, $blocks, $occ, $fixes, $notes);
            $this->fixHoursSurplus($u, $blocks, $occ, $fixes, $notes);
            $this->fixSwaps($u, $blocks, $fixes);
        }
        $this->sortBlocks($blocks);
        $after = $this->tally($u, $blocks);

        $records = $this->records($u, $blocks);
        $actions = $this->diffActions($u, $records);

        return [
            'key' => $u['key'],
            'subject_id' => $u['subject_id'],
            'subject_name' => $u['subject_name'],
            'code_names' => $u['code_names'],
            'codes' => $u['codes'],
            'before' => $before,
            'after' => $after,
            'fixes' => $fixes,
            'notes' => $notes,
            'records' => $records,
            'actions' => $actions,
        ];
    }

    private function tallyD(array $u, array $blocks): array
    {
        return $this->tally($u, $blocks)['hours_diff'];
    }

    private function codeName(array $u, string $code): string
    {
        return (string) ($u['code_names'][$code] ?? $code);
    }

    // --- 1) Tur almashtirish: ortiqcha tur → yetmagan tur (shu qism ichida), kun/soat o'zgarmaydi.
    private function fixSwaps(array $u, array &$blocks, array &$fixes): void
    {
        $D = $this->tallyD($u, $blocks);
        foreach (['L', 'A'] as $part) {
            $codes = $this->codesByPart($u, $part);
            foreach ($codes as $neg) {
                foreach ($codes as $pos) {
                    if ($neg === $pos) {
                        continue;
                    }
                    foreach ($blocks as &$b) {
                        if ($b['part'] !== $part) {
                            continue;
                        }
                        $n = 0;
                        foreach ($b['slots'] as $s => &$slot) {
                            if ($D[$neg] < 0 && $D[$pos] > 0 && $slot['code'] === $neg) {
                                $slot['code'] = $pos;
                                $D[$neg]++;
                                $D[$pos]--;
                                $n++;
                            }
                        }
                        unset($slot);
                        if ($n) {
                            $fixes[] = ['type' => 'swap', 'date' => $b['date'],
                                'text' => $n . ' soat ' . $this->codeName($u, $neg) . ' → ' . $this->codeName($u, $pos) . ' (kun va soat o\'zgarmaydi)'];
                        }
                    }
                    unset($b);
                }
            }
        }
    }

    // --- 2) Kunlar soni ortiqcha: eng yaqin kundagi darsni boshqa kunning dars davomiga qo'shish.
    private function fixCountSurplus(array $u, array &$blocks, array &$occ, array &$fixes, array &$notes): void
    {
        foreach (['L', 'A'] as $part) {
            $C = $this->tally($u, $blocks)['count_diff'][$part];
            while ($C < 0) {
                $done = false;
                // Avval faqat shu haftadagi kunlar birlashtiriladi (haftada KTR'dagidan ko'p kun — ortiqcha kun aynan shu);
                // shunday hafta qolmasagina boshqa haftaga (eng yaqin kun). Aks holda ortiqchasi yo'q haftaning kuni
                // olib tashlanib, butun semestr bo'ylab zanjir surilishlar chiqardi.
                foreach ([true, false] as $sameWeekOnly) {
                foreach ($blocks as $xi => $x) {
                    if ($x['part'] !== $part) {
                        continue;
                    }
                    $h = count($x['slots']);
                    // Nishon: shu haftadagi, so'ng eng yaqin kun.
                    $targets = array_keys(array_filter($blocks, fn ($y, $yi) => $yi !== $xi && $y['part'] === $part
                        && (!$sameWeekOnly || $y['week'] === $x['week']), ARRAY_FILTER_USE_BOTH));
                    usort($targets, fn ($a, $b) => [(int) ($blocks[$a]['week'] !== $x['week']), abs(strtotime($blocks[$a]['date']) - strtotime($x['date']))]
                        <=> [(int) ($blocks[$b]['week'] !== $x['week']), abs(strtotime($blocks[$b]['date']) - strtotime($x['date']))]);
                    foreach ($targets as $yi) {
                        $y = $blocks[$yi];
                        $slots = array_keys($y['slots']);
                        $last = max($slots);
                        $first = min($slots);
                        $cands = [range($last + 1, $last + $h), range($first - $h, $first - 1)];
                        // O'sha soatlar boshqa kunda ham bo'sh bo'lsa — dars o'z vaqtida qoladi (faqat kuni o'zgaradi).
                        $own = array_keys($x['slots']);
                        usort($cands, fn ($a, $b) => (int) ($b === $own) <=> (int) ($a === $own));
                        foreach ($cands as $range) {
                            if (array_filter($range, fn ($s) => !$this->isFree($occ, $u['key'], $u['base'], $part, $y['date'], $s))) {
                                continue;
                            }
                            foreach ($x['slots'] as $s => $_) {
                                $this->occRemove($occ, $u, $x['date'], $s);
                            }
                            $src = array_values($x['slots']);
                            foreach ($range as $i => $s) {
                                $blocks[$yi]['slots'][$s] = $src[$i];
                                $this->occAdd($occ, $u, $part, $y['date'], $s);
                            }
                            ksort($blocks[$yi]['slots']);
                            $fixes[] = ['type' => 'merge_day', 'date' => $x['date'],
                                'text' => ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . " kunlari ortiqcha: {$x['date']} dagi {$h} soat {$y['date']} dagi dars davomiga ko'chirildi"];
                            unset($blocks[$xi]);
                            $blocks = array_values($blocks);
                            $done = true;
                            break 4;
                        }
                    }
                }
                }
                if (!$done) {
                    $notes[] = ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' kunlari ' . (-$C) . ' ta ortiqcha — avtomatik birlashtirib bo\'lmadi.';
                    break;
                }
                $C++;
            }
        }
    }

    /** Kun uchun boshlanish yarim-slotlari: odatdagi dars vaqti, so'ng para boshlari, so'ng qolganlari. */
    private function startCandidates(array $blocks, string $part, int $h): array
    {
        $pref = [];
        foreach ($blocks as $b) {
            if ($b['part'] === $part) {
                $pref[min(array_keys($b['slots']))] = true;
            }
        }
        $c = array_keys($pref);
        for ($s = 1; $s <= $this->maxSlot; $s += 2) {
            $c[] = $s;
        }
        if ($h === 1) {
            for ($s = 2; $s <= $this->maxSlot; $s += 2) {
                $c[] = $s;
            }
        }

        return array_values(array_unique($c));
    }

    private function nearestAttrs(array $blocks, string $part, string $date): ?array
    {
        $best = null;
        $bestD = PHP_INT_MAX;
        foreach ($blocks as $b) {
            $d = abs(strtotime($b['date']) - strtotime($date)) + ($b['part'] === $part ? 0 : 10 ** 9);
            if ($d < $bestD) {
                $bestD = $d;
                $best = reset($b['slots']);
            }
        }

        return $best;
    }

    private function bestPositiveCode(array $u, array $D, string $part): string
    {
        $order = $this->codesByPart($u, $part);
        $best = $order[0] ?? array_key_first($u['codes']);
        foreach ($order as $c) {
            if (($D[$c] ?? 0) > ($D[$best] ?? 0)) {
                $best = $c;
            }
        }

        return (string) $best;
    }

    // --- 3) Kunlar soni yetmaydi: shu qism darsi yo'q eng yaqin kunga 1–2 soatlik dars.
    private function fixCountDeficit(array $u, array &$blocks, array &$occ, array $workDates, array &$fixes, array &$notes): void
    {
        foreach (['L', 'A'] as $part) {
            if (!$this->codesByPart($u, $part)) {
                continue;
            }
            $t = $this->tally($u, $blocks);
            $C = $t['count_diff'][$part];
            while ($C > 0) {
                $D = $this->tally($u, $blocks)['hours_diff'];
                $debt = 0;
                foreach ($this->codesByPart($u, $part) as $c) {
                    $debt += max(0, $D[$c]);
                }
                $h = min(2, max(1, $debt));
                $placed = false;
                foreach ($workDates as $date => $week) {
                    if ($date <= $this->today) {
                        continue;
                    }
                    if (array_filter($blocks, fn ($b) => $b['date'] === $date && $b['part'] === $part)) {
                        continue;
                    }
                    foreach ($this->startCandidates($blocks, $part, $h) as $s0) {
                        if ($h === 2 && $s0 % 2 === 0) {
                            continue;
                        }
                        $range = range($s0, $s0 + $h - 1);
                        if (array_filter($range, fn ($s) => !$this->isFree($occ, $u['key'], $u['base'], $part, $date, $s))) {
                            continue;
                        }
                        $attrs = $this->nearestAttrs($blocks, $part, $date) ?? ['emp' => null, 'emp_name' => null, 'room' => null, 'room_name' => null];
                        $code = $this->bestPositiveCode($u, $D, $part);
                        $slots = [];
                        foreach ($range as $s) {
                            $slots[$s] = ['code' => $code] + $attrs;
                            $this->occAdd($occ, $u, $part, $date, $s);
                        }
                        $blocks[] = ['date' => $date, 'week' => (int) $week, 'part' => $part, 'slots' => $slots, 'new' => true];
                        $this->sortBlocks($blocks);
                        $fixes[] = ['type' => 'new_day', 'date' => $date,
                            'text' => ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . " soni yetmaydi: {$date} ga {$h} soat " . $this->codeName($u, $code) . ' qo\'shildi'
                                . ($debt < $h ? ' (soati boshqa darsdan olinadi)' : '')];
                        $placed = true;
                        break 2;
                    }
                }
                if (!$placed) {
                    $notes[] = ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' soni ' . $C . ' ta kam — bo\'sh kun topilmadi.';
                    break;
                }
                $C--;
            }
        }
    }

    // --- 4) Soat yetmaydi (soni to'g'ri): eng yaqin darslar davomiga 1 soatdan qo'shiladi.
    private function fixHoursDeficit(array $u, array &$blocks, array &$occ, array &$fixes, array &$notes): void
    {
        foreach (['L', 'A'] as $part) {
            $added = [];
            $D = $this->tallyD($u, $blocks);
            $codes = $this->codesByPart($u, $part);
            $debt = function () use (&$D, $codes) {
                return array_sum(array_map(fn ($c) => max(0, $D[$c] ?? 0), $codes));
            };
            while ($debt() > 0) {
                $ok = false;
                foreach ($blocks as $i => $b) {
                    if ($b['part'] !== $part) {
                        continue;
                    }
                    $slots = array_keys($b['slots']);
                    // Avval chala parani to'ldirish (oxiri toq yarim-slot), so'ng keyingi yarim-slot, so'ng oldingisi.
                    foreach ([max($slots) + 1, min($slots) - 1] as $s) {
                        if (!$this->isFree($occ, $u['key'], $u['base'], $part, $b['date'], $s)) {
                            continue;
                        }
                        $code = $this->bestPositiveCode($u, $D, $part);
                        $attrs = $b['slots'][$s > max($slots) ? max($slots) : min($slots)];
                        $blocks[$i]['slots'][$s] = ['code' => $code] + $attrs;
                        ksort($blocks[$i]['slots']);
                        $this->occAdd($occ, $u, $part, $b['date'], $s);
                        $D[$code]--;
                        $added[$b['date'] . '|' . $code] = ($added[$b['date'] . '|' . $code] ?? 0) + 1;
                        $ok = true;
                        break 2;
                    }
                }
                if (!$ok) {
                    $notes[] = ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' ' . $debt() . ' soat kam — dars davomida bo\'sh vaqt topilmadi.';
                    break;
                }
            }
            foreach ($added as $k => $n) {
                [$date, $code] = explode('|', $k);
                $fixes[] = ['type' => 'extend', 'date' => $date, 'text' => "{$date} dagi dars davomiga {$n} soat " . $this->codeName($u, $code) . ' qo\'shildi'];
            }
        }
    }

    // --- 5) Soat ortiqcha: eng yaqin darslar qisqartiriladi, har dars kamida 1 soat qoladi.
    private function fixHoursSurplus(array $u, array &$blocks, array &$occ, array &$fixes, array &$notes): void
    {
        $D = $this->tallyD($u, $blocks);
        foreach ($D as $code => $d) {
            $code = (string) $code;
            $removed = [];
            foreach ($blocks as $i => $b) {
                while ($D[$code] < 0 && count($blocks[$i]['slots']) > 1) {
                    $slot = null;
                    foreach (array_reverse(array_keys($blocks[$i]['slots'])) as $s) {
                        if ($blocks[$i]['slots'][$s]['code'] === $code) {
                            $slot = $s;
                            break;
                        }
                    }
                    if ($slot === null) {
                        break;
                    }
                    unset($blocks[$i]['slots'][$slot]);
                    $this->occRemove($occ, $u, $b['date'], $slot);
                    $D[$code]++;
                    $removed[$b['date']] = ($removed[$b['date']] ?? 0) + 1;
                }
            }
            foreach ($removed as $date => $n) {
                $fixes[] = ['type' => 'shrink', 'date' => $date, 'text' => "{$date} dagi " . $this->codeName($u, $code) . " {$n} soat kamaytirildi (dars kamida 1 soat qoladi)"];
            }
            if ($D[$code] < 0) {
                $notes[] = $this->codeName($u, $code) . ' ' . (-$D[$code]) . ' soat ortiqcha — qisqartirib bo\'lmadi (har dars kamida 1 soat).';
            }
        }
    }

    // --- HEMIS yozuvlari: yarim-slotlar para chegarasida bo'linadi (to'liq para yoki yarim para).
    private function records(array $u, array $blocks): array
    {
        $fbEmp = $u['fallback_employee'] ?? null;
        $fbRoom = $u['fallback_room'] ?? null;
        $out = [];
        foreach ($blocks as $b) {
            $slots = $b['slots'];
            ksort($slots);
            $keys = array_keys($slots);
            for ($i = 0; $i < count($keys); $i++) {
                $s = $keys[$i];
                $a = $slots[$s];
                $len = 1;
                $nx = $keys[$i + 1] ?? null;
                if ($s % 2 === 1 && $nx === $s + 1 && $this->sameAttrs($a, $slots[$nx])) {
                    $len = 2;
                    $i++;
                }
                $start = $this->slotTimes[$s][0] ?? '';
                $end = $this->slotTimes[$s + $len - 1][1] ?? '';
                $flags = [];
                $emp = $a['emp'];
                $empName = $a['emp_name'];
                if (!$emp) {
                    [$emp, $empName] = $fbEmp ?: [null, null];
                    $flags[] = 'mudir';
                }
                $room = $a['room'];
                $roomName = $a['room_name'];
                if (!$room) {
                    [$room, $roomName] = $fbRoom ?: [null, null];
                    $flags[] = 'mudir_xonasi';
                }
                $blocked = [];
                if (!$emp) {
                    // Mudir topilgan, lekin HEMIS xodim id'si yo'q — nomini aytamiz.
                    $blocked[] = ($fbEmp && !empty($fbEmp[1]))
                        ? 'kafedra mudiri ' . $fbEmp[1] . ' ning HEMIS xodim id\'si LMS\'da yo\'q'
                        : 'o\'qituvchi ham, kafedra mudiri ham topilmadi (mark\'da shu kafedraga «Kafedra mudiri» roli berilgan xodim yo\'q)';
                }
                if (!$room) $blocked[] = '"Kafedra mudiri xonasi" auditoriyasi topilmadi';
                if ($this->hemisPairs && !isset($this->hemisPairs[$start . '-' . $end])) {
                    $blocked[] = "HEMIS'da {$start}-{$end} para vaqti yo'q";
                }
                $out[] = [
                    'date' => $b['date'], 'start' => $start, 'end' => $end, 'code' => $a['code'],
                    'employee_id' => $emp, 'employee_name' => $empName,
                    'auditorium_code' => $room, 'auditorium_name' => $roomName,
                    'flags' => $flags, 'blocked' => $blocked,
                ];
            }
        }

        return $out;
    }

    private function sameAttrs(array $a, array $b): bool
    {
        return $a['code'] === $b['code'] && (string) $a['emp'] === (string) $b['emp'] && (string) $a['room'] === (string) $b['room'];
    }

    /** Kelajak (ertadan) HEMIS yozuvlari ↔ kerakli yozuvlar: create / update / delete. */
    private function diffActions(array $u, array $records): array
    {
        $existing = array_values(array_filter((array) $u['hemis'], fn ($r) => $r['date'] > $this->today && isset($u['codes'][(string) $r['code']])));
        $want = $records;
        $actions = [];
        $k = fn ($r) => $r['date'] . '|' . substr($r['start'], 0, 5) . '|' . substr($r['end'], 0, 5) . '|' . $r['code'];

        $pairs = [];   // [kerakli yozuv, HEMIS yozuvi]
        $pairUp = function (callable $key) use (&$existing, &$want, &$pairs) {
            foreach ($want as $wi => $w) {
                foreach ($existing as $ei => $e) {
                    if ($key($w) === $key($e)) {
                        $pairs[] = [$w, $e];
                        unset($want[$wi], $existing[$ei]);
                        continue 2;
                    }
                }
            }
        };
        // Moslash tartibi: aynan bir xil yozuv; so'ng shu kunning shu turidagi dars (vaqti surilgan — "vaqt"
        // o'zgartiriladi: ma'ruza ma'ruzaga, seminar seminarga); so'ng shu vaqtdagi boshqa turdagi dars ("tur").
        // Shunda darslar surilganda o'chirish + qo'shish o'rniga bitta "o'zgartirish" chiqadi.
        $pairUp($k);
        $pairUp(fn ($r) => $r['date'] . '|' . $r['code']);
        $pairUp(fn ($r) => $r['date'] . '|' . substr($r['start'], 0, 5));
        $seq = $this->orderMoves($pairs, $want, $existing);

        foreach ($pairs as $pi => [$w, $e]) {
            $changes = [];
            if (substr($e['start'], 0, 5) !== $w['start'] || substr($e['end'], 0, 5) !== $w['end']) {
                $changes[] = 'vaqt: ' . substr($e['start'], 0, 5) . '-' . substr($e['end'], 0, 5) . ' → ' . $w['start'] . '-' . $w['end'];
            }
            if ((string) $e['code'] !== (string) $w['code']) {
                $changes[] = 'tur: ' . $this->codeName($u, (string) $e['code']) . ' → ' . $this->codeName($u, (string) $w['code']);
            }
            if ($w['employee_id'] && (string) $e['employee_id'] !== (string) $w['employee_id']) {
                $changes[] = 'o\'qituvchi: ' . ($e['employee_name'] ?: '—') . ' → ' . $w['employee_name'];
            }
            if ($w['auditorium_code'] && (string) $e['auditorium_code'] !== (string) $w['auditorium_code']) {
                $changes[] = 'xona: ' . ($e['auditorium_name'] ?: '—') . ' → ' . $w['auditorium_name'];
            }
            if ($changes) {
                $actions[] = ['op' => 'update', 'hemis_id' => $e['id']] + $w + ['changes' => $changes] + (isset($seq[$pi]) ? ['seq' => $seq[$pi]] : []);
            }
        }

        foreach ($want as $w) {
            $actions[] = ['op' => 'create', 'hemis_id' => null] + $w + ['changes' => []];
        }
        foreach ($existing as $e) {
            $actions[] = ['op' => 'delete', 'hemis_id' => $e['id'], 'date' => $e['date'],
                'start' => substr($e['start'], 0, 5), 'end' => substr($e['end'], 0, 5), 'code' => (string) $e['code'],
                'employee_id' => $e['employee_id'], 'employee_name' => $e['employee_name'],
                'auditorium_code' => $e['auditorium_code'], 'auditorium_name' => $e['auditorium_name'],
                'flags' => [], 'blocked' => [], 'changes' => []];
        }
        usort($actions, fn ($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);

        return $actions;
    }

    /**
     * Vaqti o'zgaradigan juftlarning HEMIS'ga yozilish navbati ('seq'). Guruhda bir vaqtda ikki dars turmaydi —
     * dars hali bo'shamagan vaqtga ko'chirilmasin: yangi vaqti boshqa darsning hozirgi (HEMIS) vaqti bilan
     * kesishsa, o'sha dars oldin suriladi. Darslar o'rin almashsa (aylana) navbat yordam bermaydi: vaqti aynan
     * mos bo'lsa juftlar almashtiriladi (vaqt emas, "tur" o'zgaradi), aks holda dars o'chirib qayta qo'shiladi.
     */
    private function orderMoves(array &$pairs, array &$want, array &$existing): array
    {
        $t = fn (array $r) => [substr($r['start'], 0, 5), substr($r['end'], 0, 5)];
        $moving = function (int $i) use (&$pairs, $t) {
            return isset($pairs[$i]) && $t($pairs[$i][0]) !== $t($pairs[$i][1]);
        };
        $left = array_values(array_filter(array_keys($pairs), $moving));
        $seq = [];
        $n = 0;
        while ($left) {
            $after = [];   // i => j: i ning yangi vaqti j ning hozirgi vaqti bilan kesishadi (avval j)
            foreach ($left as $i) {
                $to = $t($pairs[$i][0]);
                foreach ($left as $j) {
                    $from = $t($pairs[$j][1]);
                    if ($i !== $j && $pairs[$i][0]['date'] === $pairs[$j][1]['date'] && $to[0] < $from[1] && $from[0] < $to[1]) {
                        $after[$i] = $j;
                    }
                }
            }
            $free = array_values(array_filter($left, fn ($i) => !isset($after[$i])));
            if ($free) {
                foreach ($free as $i) {
                    $seq[$i] = ++$n;
                }
                $left = array_values(array_diff($left, $free));
                continue;
            }
            // Hammasi kutmoqda — aylana: undagi bir darsni topamiz.
            $seen = [];
            for ($i = $left[0]; !isset($seen[$i]); $i = $after[$i]) {
                $seen[$i] = true;
            }
            $j = $after[$i];
            [$wi, $ei] = $pairs[$i];
            [$wj, $ej] = $pairs[$j];
            if ($t($wi) === $t($ej)) {
                $pairs[$i] = [$wi, $ej];
                $pairs[$j] = [$wj, $ei];
            } else {
                unset($pairs[$i]);
                $want[] = $wi;
                $existing[] = $ei;
            }
            $left = array_values(array_filter($left, $moving));
        }

        return $seq;
    }
}

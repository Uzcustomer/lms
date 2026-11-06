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
 *  - Faqat soat yetmasa — dars davomiga qo'shiladi (yangi kun = yangi jurnal ustuni): avval har hafta o'z KTR
 *    soatigacha o'sha haftaning darsiga, qolgani darslarga navbat bilan 1 soatdan — bitta kunga yig'ilmaydi.
 *  - Soni yetmasa — yangi kunga (shu qism darsi yo'q kunga) qo'yiladi.
 *  - Soat ortiqcha — keyingi darslar qisqartiriladi, lekin dars kamida 1 soat (yarim para) qoladi.
 *  - Tur almashtirish (amaliy ↔ laboratoriya) kun va soatni o'zgartirmaydi.
 *  - Kelajak (ertadan) darslari har safar barcha maydonlar bo'yicha solishtiriladi: sana, para vaqti,
 *    tur, o'qituvchi, xona — qo'shish / o'zgartirish / o'chirish amallari chiqadi.
 *  - Sikl fani (4–6-kurs, birlikda cycle = true): darslar har kuni, KTR qatori = SIKL KUNI (hafta emas). Balans butun blok
 *    (sikl davri) bo'yicha: HEMIS bugungacha + ertadan blok oxirigacha = KTR jami (har tur soati, har qism kunlari — ma'ruza ham).
 *    Tuzatish faqat blok ichida — birlikning work_dates (ertadan keyingi sikl kunlari); blokdan tashqariga dars qo'yilmaydi.
 *    "Har hafta o'z KTR soatigacha" — siklda "har sikl kuni o'z KTR soatigacha"; ortiqcha kun — eng yaqin kunga birlashtiriladi.
 *    Sig'masa (blokda bo'sh soat yo'q, kun oxiri, HEMIS para vaqti yo'q) — tuzatilmaydi, izohda ogohlantirish. Faqat HEMIS'ga
 *    yoziladi: LMS ichki sikl jadvali o'zgarmaydi.
 *
 * Haftalik fan (mode = weekly — "Har hafta", alternate — "Hafta almashinuvi"):
 *  - BALANS CHEGARASI H (har qism L/A uchun alohida): ertangi haftadan boshlab KTR'da soati bor haftalar ketma-ket ko'riladi —
 *    LMS ichki jadvalida shu qism joylangan (karta faol, kuni va parasi bor) bo'lsa davom etadi, KTR'da soati 0 hafta uzluksizlikni
 *    buzmaydi; birinchi joylanmagan haftadan oldingisi — H ("Hemisga yozish" muddatidan oshmaydi). Balans: KTR 1..H ↔ HEMIS bugungacha
 *    + LMS ertadan H-hafta oxirigacha; H dan keyingi haftalar balansga kirmaydi va tuzatilmaydi (LMS'da joylangani — LMS'dagidek yoziladi).
 *  - Ertadan muddatgacha mark asosiy manba: HEMIS'dagi dars LMS'da yo'q bo'lsa — o'chirish (sababi: lms_unplaced — hafta LMS'da
 *    joylanmagan; week_surplus — "Har hafta" hafta limitidan oshgan; not_in_plan — LMS rejasida shu kuni yo'q).
 *  - "Har hafta" (weekly): har hafta darslar soni va soati KTR'dagi shu hafta qatoridan oshmaydi (LMS rejasidan kelgan darslar va soat
 *    tuzatishlari ham); oshgani olib tashlanadi / qisqartiriladi, HEMIS'dagisi — o'chirishga (sababi bilan).
 *  - Soni yetmasa — BALANSLASH DARSI (jurnal ustunlari soni KTR bilan teng bo'lishi uchun): ertadan sana tartibida birinchi mos kun —
 *    guruhning LMS'da boshqa darsi bor (talaba keladi) va shu fanning shu qismi yo'q kun. Bandlik (guruh, o'qituvchi, xona) tekshirilmaydi,
 *    guruh bandligiga qo'shilmaydi; vaqti — fanning shu qismdagi eng yaqin LMS darsi boshlanishi (yo'q bo'lsa — guruhning o'sha kundagi
 *    birinchi darsi), faqat HEMIS para vaqti va kun chegarasi tekshiriladi. H dan keyingi kunga ham tushadi — hozir yozilmaydi.
 *    Hafta limitidan istisno. Yozuvda balance: true, balance_week — darsi o'tilmagan KTR haftasi (eng eskisi birinchi).
 *
 * Soat birligi — akademik soat = yarim para = bitta yarim-slot (40 daqiqa).
 */
class SchedulePlanner
{
    /** Qo'shimcha dars qo'yiladigan eng oxirgi yarim-slot (17:10–17:50 uchun HEMIS'da yarim para yo'q). */
    private int $maxSlot;

    /** Guruhning LMS dars kunlari (talaba keladi): sana => o'sha kundagi birinchi yarim-slot (sana tartibida) — balanslash darsi uchun. */
    private array $groupDays = [];

    /**
     * @param array  $slotTimes yarim-slot => [boshlanish 'HH:MM', tugash 'HH:MM'] (1..12)
     * @param string $today     'Y-m-d' — shu kun va undan oldingisi o'zgartirilmaydi
     * @param array  $hemisPairs HEMIS'da mavjud para vaqtlari: ['HH:MM-HH:MM' => true]; bo'sh — tekshirilmaydi
     */
    public function __construct(private array $slotTimes, private string $today, private array $hemisPairs = [], private array $inactiveRooms = [])
    {
        $this->maxSlot = min(11, max(1, (int) max(array_keys($slotTimes ?: [11 => 0]))));
    }

    /**
     * @param array $units  shu guruhning fan birliklari (formati — README pastda)
     * @param array $busy   boshqa (rejada yo'q) darslar bandligi: ['Y-m-d' => [slot => true]]
     * @param array $workDates ertadan boshlab dars kunlari: ['Y-m-d' => hafta raqami] (sana tartibida)
     * @param array $groupLessons guruhning LMS ichki jadvalidagi boshqa darslari (birliklar rejasidan tashqari ham — boshqa fanlar,
     *                           sikl fanlari): ['Y-m-d' => [slot => true]] — balanslash darsi faqat guruh keladigan kunga qo'yiladi
     *
     * Birlik (unit):
     *  key, subject_id, subject_name, base (bo'laklarsiz normallashgan nom),
     *  codes: [kod => 'L'|'A'], code_names: [kod => nom],
     *  ktr: [[kod => soat], ...] (KTR qatorlari tartibida, 1-qator = 1-hafta) yoki null,
     *  current_week: joriy hafta raqami,
     *  hemis: [[id, date, start, end, code, employee_id, employee_name, auditorium_code, auditorium_name]],
     *  plan:  [[date, week, slot, len, part, employee_id, employee_name, auditorium_code, auditorium_name]],
     *  fallback_employee: [id, name] | null — o'qituvchi biriktirilmasa (kafedra mudiri),
     *  fallback_room: [code, name] | null — xona biriktirilmasa ("Kafedra mudiri xonasi"),
     *  cycle: true — sikl fani (ktr qatori, plan.week va current_week — sikl kuni), work_dates: ['Y-m-d' => sikl kuni] — ertadan
     *  keyingi blok kunlari (tuzatish faqat shu kunlarga), block_from / block_to — blok chegarasi (ogohlantirish matni uchun).
     *  mode: 'weekly' ("Har hafta") | 'alternate' ("Hafta almashinuvi") | 'cycle' — berilmasa cycle bayrog'idan,
     *  until_week / until_date — "Hemisga yozish" muddati (haftalik fan balansi shu haftagacha; null — semestr oxirigacha),
     *  week_starts: [hafta => dushanba 'Y-m-d'] — o'tgan HEMIS darslarining haftasi (hafta limiti, "N-hafta darsi o'rniga").
     */
    public function planGroup(array $units, array $busy, array $workDates, array $groupLessons = []): array
    {
        // Guruh keladigan kunlar: boshqa darslar va birliklar rejasi (ertadan) — sana => birinchi yarim-slot.
        $days = [];
        foreach ($groupLessons as $date => $slots) {
            foreach ((array) $slots as $s => $_) {
                $days[$date] = min($days[$date] ?? PHP_INT_MAX, (int) $s);
            }
        }
        foreach ($units as $u) {
            foreach ((array) $u['plan'] as $p) {
                $days[$p['date']] = min($days[$p['date']] ?? PHP_INT_MAX, (int) $p['slot']);
            }
        }
        $this->groupDays = array_filter($days, fn ($d) => $d > $this->today, ARRAY_FILTER_USE_KEY);
        ksort($this->groupDays);

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

    /** Fan rejimi: weekly ("Har hafta"), alternate ("Hafta almashinuvi"), cycle (sikl). */
    private function modeOf(array $u): string
    {
        $m = (string) ($u['mode'] ?? '');

        return in_array($m, ['weekly', 'alternate', 'cycle'], true) ? $m : (!empty($u['cycle']) ? 'cycle' : 'weekly');
    }

    /** Qism (L/A) balans chegarasi H — hafta raqami; null — chegarasiz (sikl yoki muddat berilmagan). */
    private function hzWeek(array $u, string $part): ?int
    {
        return isset($u['_hz'][$part]) ? (int) $u['_hz'][$part]['week'] : null;
    }

    /** Qism balans chegarasining oxirgi kuni (H-hafta yakshanbasi); null — chegarasiz. */
    private function hzEnd(array $u, string $part): ?string
    {
        return isset($u['_hz'][$part]) ? $u['_hz'][$part]['end'] : null;
    }

    /** LMS ichki jadvalida shu qism joylangan haftalar (karta faol, kuni va parasi bor — reja yozuvi bor). */
    private function placedWeeks(array $u, string $part): array
    {
        $out = [];
        foreach ((array) $u['plan'] as $p) {
            if ($p['part'] === $part) {
                $out[(int) $p['week']] = true;
            }
        }

        return $out;
    }

    /**
     * Balans chegarasi (haftalik fan, har qism): ertangi haftadan boshlab KTR'da soati bor haftalar — LMS'da joylangan bo'lsa davom,
     * soati 0 hafta uzluksizlikni buzmaydi; birinchi joylanmagan haftadan oldingisi — H (muddat — until_week — dan oshmaydi; 0/null —
     * KTR oxirigacha). Qaytaradi: week (H), end (H-hafta oxiri), unplaced (H dan muddatgacha joylanmagan haftalar); null — chegarasiz.
     */
    private function boundary(array $u, string $part): ?array
    {
        if ($this->modeOf($u) === 'cycle' || !array_key_exists('until_week', $u)) {
            return null;
        }
        $ktrN = count((array) ($u['ktr'] ?? []));
        $cap = ($u['until_week'] ?? null) !== null && (int) $u['until_week'] > 0 ? (int) $u['until_week'] : $ktrN;
        $placed = $this->placedWeeks($u, $part);
        $start = $this->weekOf($u, date('Y-m-d', strtotime($this->today . ' +1 day'))) ?? max(1, (int) ($u['current_week'] ?? 0));
        $H = min($start - 1, $cap);
        for ($w = $start; $w <= $cap; $w++) {
            if ($this->ktrPartHours($u, $w, $part) > 0 && !isset($placed[$w])) {
                break;
            }
            $H = $w;
        }
        $unplaced = [];
        for ($w = $H + 1; $w <= $cap; $w++) {
            if ($this->ktrPartHours($u, $w, $part) > 0 && !isset($placed[$w])) {
                $unplaced[] = $w;
            }
        }
        $starts = (array) ($u['week_starts'] ?? []);
        $end = isset($starts[$H]) ? date('Y-m-d', strtotime($starts[$H] . ' +6 days'))
            : (isset($starts[$H + 1]) ? date('Y-m-d', strtotime($starts[$H + 1] . ' -1 day')) : ($u['until_date'] ?? null));

        return ['week' => max(0, $H), 'end' => $end, 'unplaced' => $unplaced];
    }

    /** Sananing haftasi (week_starts bo'yicha); aniqlanmasa — null. */
    private function weekOf(array $u, string $date): ?int
    {
        $w = null;
        foreach ((array) ($u['week_starts'] ?? []) as $week => $mon) {
            if ((string) $mon <= $date && ($w === null || (int) $week > $w)) {
                $w = (int) $week;
            }
        }

        return $w;
    }

    /** KTR qatoridagi shu qism soati (n-hafta, 1 dan). */
    private function ktrPartHours(array $u, int $week, string $part): int
    {
        $h = 0;
        foreach ((array) (($u['ktr'] ?? [])[$week - 1] ?? []) as $code => $x) {
            if (isset($u['codes'][(string) $code]) && $this->partOf($u, (string) $code) === $part) {
                $h += (int) $x;
            }
        }

        return $h;
    }

    /** O'tgan (bugungacha) HEMIS darslari haftalar bo'yicha: [hafta => ['days' => [sana => true], 'hours' => soat]] (shu qism). */
    private function pastByWeek(array $u, string $part): array
    {
        $out = [];
        foreach ((array) $u['hemis'] as $r) {
            $code = (string) $r['code'];
            if ($r['date'] > $this->today || !isset($u['codes'][$code]) || $this->partOf($u, $code) !== $part
                || ($w = $this->weekOf($u, $r['date'])) === null) {
                continue;
            }
            $out[$w]['days'][$r['date']] = true;
            $out[$w]['hours'] = ($out[$w]['hours'] ?? 0) + $this->hoursOf($r['start'], $r['end']);
        }

        return $out;
    }

    /** Fanning shu qismdagi sanaga eng yaqin LMS darsining boshlanish yarim-sloti (o'tgan kunlar ham); yo'q bo'lsa — null. */
    private function usualStart(array $u, string $part, string $date): ?int
    {
        $best = null;
        $bestD = PHP_INT_MAX;
        foreach ((array) $u['plan'] as $p) {
            if ($p['part'] !== $part) {
                continue;
            }
            $d = abs(strtotime($p['date']) - strtotime($date));
            if ($d < $bestD) {
                $bestD = $d;
                $best = (int) $p['slot'];
            }
        }

        return $best;
    }

    /** Yarim-slotlar [s0, s0+h) kun chegarasiga sig'adi va HEMIS'da shunday para vaqti bor. */
    private function slotRangeOk(int $s0, int $h): bool
    {
        if ($s0 < 1 || $s0 + $h - 1 > $this->maxSlot || !isset($this->slotTimes[$s0], $this->slotTimes[$s0 + $h - 1])) {
            return false;
        }

        return !$this->hemisPairs || isset($this->hemisPairs[$this->slotTimes[$s0][0] . '-' . $this->slotTimes[$s0 + $h - 1][1]]);
    }

    /**
     * Darsi o'tilmagan KTR haftalari (eng eskisi birinchi): balans davrida shu qism KTR'da bor, lekin HEMIS'da (bugungacha) ham,
     * rejada (ertadan, balanslash darslarisiz) ham shu haftada dars yo'q.
     */
    private function missingWeeks(array $u, array $blocks, string $part): array
    {
        if (empty($u['week_starts'])) {
            return [];
        }
        $past = $this->pastByWeek($u, $part);
        $planned = [];
        foreach ($blocks as $b) {
            if ($b['part'] === $part && empty($b['balance'])) {
                $planned[(int) $b['week']] = true;
            }
        }
        $out = [];
        $last = $this->hzWeek($u, $part) ?? count((array) $u['ktr']);
        for ($w = 1; $w <= $last; $w++) {
            if ($this->ktrPartHours($u, $w, $part) > 0 && empty($past[$w]['days']) && !isset($planned[$w])) {
                $out[] = $w;
            }
        }

        return $out;
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
                    // "Vaqtincha kafedra mudirida" (LMS): asl o'qituvchi nomi; bandlik tekshirilmaydi.
                    'ph' => $p['placeholder'] ?? null,
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
        // Haftalik fan: KTR va HEMIS faqat balans chegarasigacha (har qism — o'z H'i); reja bloklari — chaqiruvchi beradi (chegara ichidagilar
        // + balanslash darslari).
        $lastWeek = ['L' => $this->hzWeek($u, 'L'), 'A' => $this->hzWeek($u, 'A')];
        $endDate = ['L' => $this->hzEnd($u, 'L'), 'A' => $this->hzEnd($u, 'A')];
        foreach ((array) ($u['ktr'] ?? []) as $i => $row) {
            $sum = ['L' => 0, 'A' => 0];
            foreach ((array) $row as $code => $h) {
                $code = (string) $code;
                if (!isset($u['codes'][$code]) || ($lastWeek[$this->partOf($u, $code)] !== null && $i + 1 > $lastWeek[$this->partOf($u, $code)])) {
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
            $code = (string) $r['code'];
            if ($r['date'] > $this->today || !isset($u['codes'][$code])
                || (($e = $endDate[$this->partOf($u, $code)]) !== null && $r['date'] > $e)) {
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
        $mode = $this->modeOf($u);
        // Haftalik fan: har qism balans chegarasi (H); undan keyingi darslar balansga kirmaydi va tuzatilmaydi (LMS'dagidek yoziladi).
        $u['_hz'] = [];
        foreach (['L', 'A'] as $part) {
            if (($b = $this->boundary($u, $part)) !== null) {
                $u['_hz'][$part] = $b;
            }
        }
        $blocks = $st['blocks'];
        $isOut = fn ($b) => ($e = $this->hzEnd($u, $b['part'])) !== null && $b['date'] > $e;
        $outside = array_values(array_filter($blocks, $isOut));
        $blocks = array_values(array_filter($blocks, fn ($b) => !$isOut($b)));
        $this->sortBlocks($blocks);
        $before = $this->tally($u, $blocks);
        $fixes = [];
        $notes = [];

        if ($u['ktr'] === null) {
            $notes[] = 'KTR kiritilmagan — balans tekshirilmadi, faqat jadval solishtirildi.';
        } else {
            // Sikl fani — faqat o'z bloki kunlari (work_dates); haftalik — guruhning umumiy dars kunlari.
            $this->fixSwaps($u, $blocks, $fixes);
            $this->fixCountSurplus($u, $blocks, $occ, $fixes, $notes);
            if ($mode === 'weekly') {
                $this->fixWeekLimit($u, $blocks, $occ, $fixes);
            }
            if ($mode === 'cycle' && $this->tally($u, $blocks)['count_diff']['A'] <= 0) {
                // Siklda kun — bitta: amaliy soat kamomadi avval o'z kunlari davomiga (izohsiz, sinov), so'ng ma'ruza yangi kunga —
                // aks holda yangi ma'ruza amaliy davomini egallab, amaliy "sig'madi" bo'lardi. Amaliy kuni ham kam bo'lsa — qilinmaydi:
                // yangi amaliy kun uchun soat qolishi kerak (aks holda mavjud kun uzayib, ma'ruzaga joy qolmasdi).
                $silent = [];
                $this->fixHoursDeficit($u, $blocks, $occ, $fixes, $silent, ['A']);
            }
            if ($mode === 'cycle') {
                $this->fixCountDeficit($u, $blocks, $occ, (array) ($u['work_dates'] ?? []), $fixes, $notes);
            } else {
                $this->fixCountBalance($u, $blocks, $outside, $workDates, $fixes, $notes);
            }
            $this->fixHoursDeficit($u, $blocks, $occ, $fixes, $notes);
            $this->fixHoursSurplus($u, $blocks, $occ, $fixes, $notes);
            $this->fixSwaps($u, $blocks, $fixes);
        }
        $this->sortBlocks($blocks);
        $after = $this->tally($u, $blocks);

        $all = array_merge($blocks, $outside);
        $this->sortBlocks($all);
        $records = $this->records($u, $all);
        $actions = $this->diffActions($u, $records);
        $parts = array_values(array_unique(array_values($u['codes'])));
        sort($parts);

        return [
            'key' => $u['key'],
            'subject_id' => $u['subject_id'],
            'subject_name' => $u['subject_name'],
            'code_names' => $u['code_names'],
            'codes' => $u['codes'],
            'mode' => $mode,
            // Balans davri (haftalik fan): har qism — 1..H; LMS'da joylanmagan haftalar (H dan muddatgacha) — balanslanmadi, kutiladi.
            'balance_weeks' => $mode !== 'cycle' ? array_combine($parts, array_map(fn ($p) => $this->hzWeek($u, $p) ?? count((array) ($u['ktr'] ?? [])), $parts)) : null,
            'unplaced_weeks' => $mode !== 'cycle' ? array_filter(array_combine($parts, array_map(fn ($p) => $u['_hz'][$p]['unplaced'] ?? [], $parts))) : [],
            'before' => $before,
            'after' => $after,
            'fixes' => $fixes,
            'notes' => $notes,
            'records' => $records,
            'actions' => $actions,
        ];
    }

    /**
     * Sikl fani ogohlantirishi (blok chegarasi bilan): kamomad — "Sikl bloki oxirigacha sig'madi: …" (frontendda qizil),
     * ortiqcha — "Sikl blokida ortiqcha: …" (sariq).
     */
    private function cycleNote(array $u, bool $deficit, string $what): string
    {
        $fmt = fn ($d) => $d ? date('d.m', strtotime((string) $d)) : '?';
        $block = 'blok ' . $fmt($u['block_from'] ?? null) . '–' . $fmt($u['block_to'] ?? null);

        return $deficit
            ? "Sikl bloki oxirigacha sig'madi: {$what} ({$block}, qolgan kunlar band)"
            : "Sikl blokida ortiqcha: {$what} ({$block})";
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
                // olib tashlanib, butun semestr bo'ylab zanjir surilishlar chiqardi. Siklda "hafta" — sikl kuni (bir kunda
                // bir qism — bitta blok), shuning uchun darhol shu kunga eng yaqin kunga birlashtiriladi (blok ichida).
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
                    $notes[] = !empty($u['cycle'])
                        ? $this->cycleNote($u, false, ($part === 'L' ? 'ma\'ruza' : 'amaliy') . ' kunlari ' . (-$C) . ' ta ortiqcha — birlashtirib bo\'lmadi')
                        : ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' kunlari ' . (-$C) . ' ta ortiqcha — avtomatik birlashtirib bo\'lmadi.';
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
            // "Vaqtincha kafedra mudirida" — faqat o'sha hafta uchun: boshqa sanaga qo'shiladigan dars uning o'qituvchi/xonasini olmaydi
            // (boshqa blok bo'lmasa — oladi, lekin belgisiz: mudir bandligi tekshiriladi).
            $ph = (reset($b['slots'])['ph'] ?? null) !== null;
            $d = abs(strtotime($b['date']) - strtotime($date)) + ($b['part'] === $part ? 0 : 10 ** 9) + ($ph ? 10 ** 10 : 0);
            if ($d < $bestD) {
                $bestD = $d;
                $best = reset($b['slots']);
            }
        }

        return $best === null ? null : ['ph' => null] + $best;
    }

    /** Sikl fani: LMS rejasidagi (o'tgan kunlar ham) shu qismning sanaga eng yaqin darsi — o'qituvchi va xona. */
    private function nearestPlanAttrs(array $u, string $part, string $date): ?array
    {
        $best = null;
        $bestD = PHP_INT_MAX;
        foreach ((array) $u['plan'] as $p) {
            if ($p['part'] !== $part || !empty($p['placeholder'])) {
                continue;
            }
            $d = abs(strtotime($p['date']) - strtotime($date));
            if ($d < $bestD) {
                $bestD = $d;
                $best = ['emp' => $p['employee_id'] ?? null, 'emp_name' => $p['employee_name'] ?? null,
                    'room' => $p['auditorium_code'] ?? null, 'room_name' => $p['auditorium_name'] ?? null, 'ph' => null];
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

    // --- 3) Kunlar soni yetmaydi: shu qism darsi yo'q eng yaqin kunga 1–2 soatlik dars. Sikl fanida — faqat blok ichidagi
    //        (work_dates) kunga; ma'ruza ham (o'qituvchi/xona — eng yaqin ma'ruzadan, nearestAttrs).
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
                        // Sikl fani: shu qismning ertangi darsi bo'lmasa — eng yaqin (o'tgan kunlar ham) LMS rejadagi shu qism darsidan
                        // (ma'ruza — ma'ruza o'qituvchisi va xonasi, amaliydan emas).
                        $attrs = (!empty($u['cycle']) && !array_filter($blocks, fn ($b) => $b['part'] === $part) ? $this->nearestPlanAttrs($u, $part, $date) : null)
                            ?? $this->nearestAttrs($blocks, $part, $date) ?? ['emp' => null, 'emp_name' => null, 'room' => null, 'room_name' => null];
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
                    $notes[] = !empty($u['cycle'])
                        ? $this->cycleNote($u, true, ($part === 'L' ? 'ma\'ruza' : 'amaliy') . ' kunlari ' . $C . ' ta kam')
                        : ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' soni ' . $C . ' ta kam — bo\'sh kun topilmadi.';
                    break;
                }
                $C--;
            }
        }
    }

    // --- 2b) "Har hafta" fan: har hafta darslar soni va soati KTR'dagi shu hafta qatoridan oshmaydi (HEMIS bugungacha + reja ertadan,
    //         muddatgacha; balanslash darsi — istisno). Ortiqcha dars olib tashlanadi (HEMIS'dagisi — o'chirishga): avval HEMIS'da yo'q
    //         kun, so'ng eng kechi; ortiqcha soat — eng kechki darslar qisqartiriladi (dars kamida 1 soat qoladi).
    private function fixWeekLimit(array $u, array &$blocks, array &$occ, array &$fixes): void
    {
        $ktrWeeks = count((array) ($u['ktr'] ?? []));
        $drop = [];
        foreach (['L', 'A'] as $part) {
            if (!$this->codesByPart($u, $part)) {
                continue;
            }
            $past = $this->pastByWeek($u, $part);
            $hemisDays = [];
            foreach ((array) $u['hemis'] as $r) {
                if (isset($u['codes'][(string) $r['code']]) && $this->partOf($u, (string) $r['code']) === $part) {
                    $hemisDays[$r['date']] = true;
                }
            }
            $byWeek = [];
            foreach ($blocks as $i => $b) {
                if ($b['part'] === $part && empty($b['balance'])) {
                    $byWeek[(int) $b['week']][] = $i;
                }
            }
            $what = $part === 'L' ? 'ma\'ruza' : 'amaliy';
            foreach ($byWeek as $week => $idx) {
                if ($week < 1 || $week > $ktrWeeks) {
                    continue;   // KTR'da qatori yo'q hafta — limit yo'q (umumiy balans hal qiladi)
                }
                $kH = $this->ktrPartHours($u, $week, $part);
                $kC = $kH > 0 ? 1 : 0;
                $pastC = count($past[$week]['days'] ?? []);
                $n = $pastC + count($idx);
                $extra = $n - $kC;
                if ($extra > 0) {
                    usort($idx, fn ($a, $b) => [isset($hemisDays[$blocks[$a]['date']]) ? 1 : 0, $blocks[$b]['date']]
                        <=> [isset($hemisDays[$blocks[$b]['date']]) ? 1 : 0, $blocks[$a]['date']]);
                    foreach (array_splice($idx, 0, min($extra, count($idx))) as $i) {
                        foreach ($blocks[$i]['slots'] as $sl => $_) {
                            $this->occRemove($occ, $u, $blocks[$i]['date'], $sl);
                        }
                        $drop[$i] = true;
                        $fixes[] = ['type' => 'week_limit', 'date' => $blocks[$i]['date'],
                            'text' => "{$week}-hafta: KTR {$kC} dars, jadvalda {$n} — {$blocks[$i]['date']} dagi {$what} olib tashlandi (Har hafta: hafta limiti)"];
                    }
                }
                $total = ($past[$week]['hours'] ?? 0) + array_sum(array_map(fn ($i) => count($blocks[$i]['slots']), $idx));
                $was = $total;
                $cut = [];
                usort($idx, fn ($a, $b) => strcmp($blocks[$b]['date'], $blocks[$a]['date']));
                foreach ($idx as $i) {
                    while ($total > $kH && count($blocks[$i]['slots']) > 1) {
                        $sl = max(array_keys($blocks[$i]['slots']));
                        unset($blocks[$i]['slots'][$sl]);
                        $this->occRemove($occ, $u, $blocks[$i]['date'], $sl);
                        $cut[$blocks[$i]['date']] = ($cut[$blocks[$i]['date']] ?? 0) + 1;
                        $total--;
                    }
                }
                foreach ($cut as $date => $k) {
                    $fixes[] = ['type' => 'week_limit', 'date' => $date,
                        'text' => "{$week}-hafta: KTR {$kH} soat, jadvalda {$was} — {$date} dagi dars {$k} soat qisqartirildi (Har hafta: hafta limiti)"];
                }
            }
        }
        if ($drop) {
            $blocks = array_values(array_diff_key($blocks, $drop));
        }
    }

    // --- 3b) Haftalik fan: dars soni yetmaydi — BALANSLASH DARSI (yangi jurnal ustuni). Ertadan sana tartibida birinchi mos kun:
    //         guruhning LMS'da boshqa darsi bor (talaba keladi) va shu fanning shu qismi yo'q. Bandlik (guruh, o'qituvchi, xona, ustma-ust)
    //         tekshirilmaydi, guruh bandligiga (occ) qo'shilmaydi; vaqti — fanning shu qismdagi eng yaqin LMS darsi boshlanishi, yo'q
    //         bo'lsa — guruhning o'sha kundagi birinchi darsi; faqat HEMIS para vaqti va kun chegarasi tekshiriladi. Muddat bilan
    //         cheklanmaydi (keyingi kun — hozir yozilmaydi), hafta limitidan istisno.
    private function fixCountBalance(array $u, array &$blocks, array $outside, array $workDates, array &$fixes, array &$notes): void
    {
        foreach (['L', 'A'] as $part) {
            $hzEnd = $this->hzEnd($u, $part);
            $codes = $this->codesByPart($u, $part);
            if (!$codes) {
                continue;
            }
            $C = $this->tally($u, $blocks)['count_diff'][$part];
            $missing = $C > 0 ? $this->missingWeeks($u, $blocks, $part) : [];
            $name = $part === 'L' ? 'ma\'ruza' : 'amaliy qism';
            while ($C > 0) {
                $week = array_shift($missing);
                $D = $this->tallyD($u, $blocks);
                $debt = array_sum(array_map(fn ($c) => max(0, $D[$c] ?? 0), $codes));
                $h = min(2, max(1, $debt));
                $pick = null;
                foreach ($this->groupDays as $date => $first) {
                    if (array_filter(array_merge($blocks, $outside), fn ($b) => $b['date'] === $date && $b['part'] === $part)) {
                        continue;
                    }
                    foreach (array_unique(array_filter([$this->usualStart($u, $part, $date), $first], fn ($x) => $x !== null)) as $s0) {
                        if ($this->slotRangeOk((int) $s0, $h)) {
                            $pick = [$date, (int) $s0];
                            break 2;
                        }
                    }
                }
                if ($pick === null) {
                    $notes[] = 'Balanslash: ' . $name . ' — ' . ($week ? "{$week}-hafta darsi uchun " : '')
                        . 'mos kun topilmadi (guruhning dars kunlarida shu qism yo\'q kun qolmadi).';
                    break;
                }
                [$date, $s0] = $pick;
                $attrs = $this->nearestPlanAttrs($u, $part, $date) ?? $this->nearestAttrs($blocks, $part, $date)
                    ?? ['emp' => null, 'emp_name' => null, 'room' => null, 'room_name' => null, 'ph' => null];
                $code = $this->bestPositiveCode($u, $D, $part);
                $slots = [];
                for ($sl = $s0; $sl < $s0 + $h; $sl++) {
                    $slots[$sl] = ['code' => $code] + $attrs;
                }
                // Balans chegarasidan keyingi kun — rejada, lekin hozir yozilmaydi (o'sha haftalar LMS'da joylanishini kutadi).
                $later = $hzEnd !== null && $date > $hzEnd;
                $blocks[] = ['date' => $date, 'week' => $this->weekOf($u, $date) ?? (int) ($workDates[$date] ?? 0), 'part' => $part,
                    'slots' => $slots, 'new' => true, 'balance' => true, 'balance_week' => $week, 'balance_later' => $later];
                $this->sortBlocks($blocks);
                $fixes[] = ['type' => 'balance_day', 'date' => $date, 'week' => $week, 'later' => $later,
                    'text' => 'Balanslash: ' . $name . ' soni yetmaydi' . ($week ? " ({$week}-hafta darsi o'rniga)" : '')
                        . ": {$date} ga {$h} soat qo'shildi" . ($debt < $h ? ' (soati boshqa darsdan olinadi)' : '')];
                $C--;
            }
        }
    }

    // --- 4) Soat yetmaydi (soni to'g'ri). Avval har hafta (siklda — har sikl kuni) o'z KTR soatigacha — shu haftaning darsi davomiga
    //        (masalan LMS kartasi 2 soat, KTR 4 soat bo'lsa har haftaga 2 soatdan); qolgan kamomad
    //        darslarga navbat bilan 1 soatdan (bitta kunga yig'ilmaydi).
    private function fixHoursDeficit(array $u, array &$blocks, array &$occ, array &$fixes, array &$notes, array $parts = ['L', 'A']): void
    {
        // "Har hafta" fan: haftaning jami soati (HEMIS bugungacha + reja) KTR qatoridan oshmaydi.
        $weekly = $this->modeOf($u) === 'weekly';
        $ktrWeeks = count((array) ($u['ktr'] ?? []));
        foreach ($parts as $part) {
            $added = [];
            $D = $this->tallyD($u, $blocks);
            $codes = $this->codesByPart($u, $part);
            $past = $weekly ? $this->pastByWeek($u, $part) : [];
            $debt = function () use (&$D, $codes) {
                return array_sum(array_map(fn ($c) => max(0, $D[$c] ?? 0), $codes));
            };
            $weekFull = function (int $week) use (&$blocks, $u, $part, $past, $weekly, $ktrWeeks): bool {
                if (!$weekly || $week < 1 || $week > $ktrWeeks) {
                    return false;
                }
                $have = $past[$week]['hours'] ?? 0;
                foreach ($blocks as $b) {
                    if ($b['part'] === $part && (int) $b['week'] === $week && empty($b['balance'])) {
                        $have += count($b['slots']);
                    }
                }

                return $have >= $this->ktrPartHours($u, $week, $part);
            };
            $extend = function (int $i) use (&$blocks, &$occ, &$D, &$added, $u, $part, $weekFull): bool {
                $b = $blocks[$i];
                // Balanslash darsi uzaytirilmaydi; "Har hafta" fanda hafta limiti to'lgan bo'lsa — shu haftaga qo'shilmaydi.
                if (!empty($b['balance']) || $weekFull((int) $b['week'])) {
                    return false;
                }
                $slots = array_keys($b['slots']);
                // Avval keyingi yarim-slot (chala parani to'ldirish ham shu), so'ng oldingisi.
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

                    return true;
                }

                return false;
            };

            // a) Hafta bo'yicha: shu haftaning KTR soati — shu haftadagi darslar davomiga.
            if ($u['ktr'] !== null && $debt() > 0) {
                $byWeek = [];
                foreach ($blocks as $i => $b) {
                    if ($b['part'] === $part && empty($b['balance'])) {
                        $byWeek[(int) $b['week']][] = $i;
                    }
                }
                ksort($byWeek);
                $short = [];
                foreach ($byWeek as $week => $idx) {
                    $row = (array) ($u['ktr'][$week - 1] ?? []);
                    $need = array_sum(array_map(fn ($c) => (int) ($row[$c] ?? 0), $codes));
                    // "Har hafta": shu haftada bugungacha o'tilgan soat ham hisobda.
                    $have = ($past[$week]['hours'] ?? 0) + array_sum(array_map(fn ($i) => count($blocks[$i]['slots']), $idx));
                    if ($need > $have) {
                        $short[] = $week . (!empty($u['cycle']) ? '-kun' : '-hafta') . ': KTR ' . $need . ' soat, jadvalda ' . $have;
                    }
                    while ($have < $need && $debt() > 0) {
                        $ok = false;
                        foreach ($idx as $i) {
                            if ($extend($i)) {
                                $ok = true;
                                $have++;
                                break;
                            }
                        }
                        if (!$ok) {
                            break;
                        }
                    }
                }
                if ($short) {
                    $notes[] = ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ': LMS ichki jadvalidagi dars KTR soatidan qisqa ('
                        . implode('; ', array_slice($short, 0, 6)) . (count($short) > 6 ? '; …' : '')
                        . (!empty($u['cycle']) ? ') — sikl kun rejasini (KTR) tekshiring.' : ') — kartochka soatini KTR bo\'yicha tuzating.');
                }
            }

            // b) Qolgan kamomad — darslarga navbat bilan 1 soatdan.
            while ($debt() > 0) {
                $ok = false;
                foreach ($blocks as $i => $b) {
                    if ($b['part'] === $part && $debt() > 0 && $extend($i)) {
                        $ok = true;
                    }
                }
                if (!$ok) {
                    $notes[] = !empty($u['cycle'])
                        ? $this->cycleNote($u, true, ($part === 'L' ? 'ma\'ruza' : 'amaliy') . ' ' . $debt() . ' soat kam')
                        : ($part === 'L' ? 'Ma\'ruza' : 'Amaliy qism') . ' ' . $debt() . ' soat kam — '
                            . ($weekly ? 'muddatgacha darslar davomiga qo\'shib bo\'lmadi (har hafta KTR soatidan oshmaydi, bo\'sh vaqt yo\'q).'
                                : 'dars davomida bo\'sh vaqt topilmadi.');
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
                if (!empty($b['balance'])) {
                    continue;   // balanslash darsi o'z soatida qoladi
                }
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
                $notes[] = !empty($u['cycle'])
                    ? $this->cycleNote($u, false, mb_strtolower($this->codeName($u, $code)) . ' ' . (-$D[$code]) . ' soat ortiqcha — qisqartirib bo\'lmadi (har dars kamida 1 soat)')
                    : $this->codeName($u, $code) . ' ' . (-$D[$code]) . ' soat ortiqcha — qisqartirib bo\'lmadi (har dars kamida 1 soat).';
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
                $ph = $a['ph'] ?? null;
                if ($ph !== null) {
                    // Vaqtincha kafedra mudirida: mudir va mudir xonasi bandligi tekshirilmaydi (to'siq emas).
                    $flags = ['mudir', 'mudir_xonasi', 'vaqtincha'];
                }
                if (!$emp && $ph === null) {
                    [$emp, $empName] = $fbEmp ?: [null, null];
                    $flags[] = 'mudir';
                }
                $room = $a['room'];
                $roomName = $a['room_name'];
                if (!$room && $ph === null) {
                    [$room, $roomName] = $fbRoom ?: [null, null];
                    $flags[] = 'mudir_xonasi';
                }
                $blocked = [];
                if (!$emp && $ph !== null) {
                    $blocked[] = 'kafedra mudirining HEMIS xodim id\'si yo\'q';
                } elseif (!$emp) {
                    // Mudir topilgan, lekin HEMIS xodim id'si yo'q — nomini aytamiz.
                    $blocked[] = ($fbEmp && !empty($fbEmp[1]))
                        ? 'kafedra mudiri ' . $fbEmp[1] . ' ning HEMIS xodim id\'si LMS\'da yo\'q'
                        : 'o\'qituvchi ham, kafedra mudiri ham topilmadi (mark\'da shu kafedraga «Kafedra mudiri» roli berilgan xodim yo\'q)';
                }
                if (!$room) $blocked[] = '"Kafedra mudiri xonasi" auditoriyasi topilmadi';
                // LMS kartasidagi xona nofaol (auditoriums.active = false) — HEMIS'ga yozilmaydi, avval xonani almashtirish kerak.
                if ($room && isset($this->inactiveRooms[(string) $room])) {
                    $blocked[] = "LMS'dagi xona nofaol" . ($roomName ? ' («' . $roomName . '»)' : '') . ' — xonani almashtiring';
                }
                if ($this->hemisPairs && !isset($this->hemisPairs[$start . '-' . $end])) {
                    $blocked[] = "HEMIS'da {$start}-{$end} para vaqti yo'q";
                }
                $out[] = [
                    'date' => $b['date'], 'start' => $start, 'end' => $end, 'code' => $a['code'],
                    'employee_id' => $emp, 'employee_name' => $empName,
                    'auditorium_code' => $room, 'auditorium_name' => $roomName,
                    'flags' => array_values(array_unique($flags)), 'blocked' => $blocked,
                ] + ($ph !== null ? ['ph_orig' => (string) $ph] : [])
                    // Balanslash darsi: bandlik tekshirilmaydi (guruhlararo ham), "N-hafta darsi o'rniga".
                    + (!empty($b['balance']) ? ['balance' => true, 'balance_week' => $b['balance_week'] ?? null] + (!empty($b['balance_later']) ? ['balance_later' => true] : []) : []);
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
                'flags' => [], 'blocked' => [], 'changes' => []] + $this->deleteReason($u, $e);
        }
        usort($actions, fn ($a, $b) => [$a['date'], $a['start']] <=> [$b['date'], $b['start']]);

        return $actions;
    }

    /**
     * O'chirish sababi (haftalik fan): lms_unplaced — shu hafta LMS'da shu qism joylanmagan (KTR'da soati bor); week_surplus — "Har hafta"
     * hafta limitidan oshgan; not_in_plan — LMS rejasida shu kuni yo'q. Sikl fani — sababsiz (avvalgidek).
     */
    private function deleteReason(array $u, array $e): array
    {
        $code = (string) $e['code'];
        if ($this->modeOf($u) === 'cycle' || !isset($u['codes'][$code])) {
            return [];
        }
        $part = $this->partOf($u, $code);
        $week = $this->weekOf($u, $e['date']);
        if ($week !== null && $u['ktr'] !== null && $this->ktrPartHours($u, $week, $part) > 0 && !isset($this->placedWeeks($u, $part)[$week])) {
            return ['reason_code' => 'lms_unplaced', 'reason' => "LMSda joylanmagan ({$week}-hafta)", 'week' => $week];
        }
        if (($why = $this->weekSurplusReason($u, $e)) !== null) {
            return ['reason_code' => 'week_surplus', 'reason' => $why];
        }

        return ['reason_code' => 'not_in_plan', 'reason' => 'LMS rejasida yo\'q'];
    }

    /**
     * "Har hafta" fan: o'chiriladigan HEMIS darsi haftasida HEMIS'dagi shu qism kunlari KTR'dagidan ko'p bo'lsa — sababi
     * ("8-hafta: KTR 1 dars, HEMIS 2 — ortiqcha"); aks holda null.
     */
    private function weekSurplusReason(array $u, array $e): ?string
    {
        $code = (string) $e['code'];
        if ($this->modeOf($u) !== 'weekly' || $u['ktr'] === null || !isset($u['codes'][$code])
            || ($week = $this->weekOf($u, $e['date'])) === null || $week > count((array) $u['ktr'])) {
            return null;
        }
        $part = $this->partOf($u, $code);
        $days = [];
        foreach ((array) $u['hemis'] as $r) {
            if (isset($u['codes'][(string) $r['code']]) && $this->partOf($u, (string) $r['code']) === $part && $this->weekOf($u, $r['date']) === $week) {
                $days[$r['date']] = true;
            }
        }
        $kC = $this->ktrPartHours($u, $week, $part) > 0 ? 1 : 0;

        return count($days) > $kC ? "{$week}-hafta: KTR {$kC} dars, HEMIS " . count($days) . ' — ortiqcha' : null;
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

<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableCyclePlacement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sikl jadvali: bir nechta kunni bitta surish (cycle-lecture-slots, day_indexes). Har tanlangan sikl kunida surilayotgan qism —
 * mode bo'yicha: both — kun boshi (ma'ruza va amaliy birga), lecture — oqim ma'ruzasi (amaliy joyida), practice — amaliy (guruhcha
 * qatorida shu guruhcha, oqim qatorida — oqimdagi hammasi). Yangi soat: delta — har kunning o'z boshidan (sudrash), yoki start_hour —
 * hammasiga bir xil; step=jump — ↑ ↓ qadami (cycleDayJump: qo'shni ma'ruza / amaliy bilan o'rin almashish yoki 1 soat). day_modes — har
 * kunning o'z rejimi (ma'ruza va amaliy aralash tanlov). Bitta tranzaksiya: har kun alohida sinab ko'riladi (savepoint), natija kun bo'yicha:
 *   ok — o'zgaradi; conflict — bandlik (o'qituvchi / xona / guruhning haftalik darsi) — force bo'lsa baribir qo'llanadi;
 *   nofit — kun oxiriga sig'maydi; blocked — ma'ruza va amaliy ustma-ust va h.k. (force ham qo'llamaydi);
 *   past — o'tgan kun (hamma uchun) yoki bugun (admin, superadmin, registrator ofisidan boshqalar uchun; xodimga bugungi dars boshlangan,
 *   hatto tugagan bo'lsa ham ochiq — TimetableStaffToday); same — vaqt o'zgarmaydi;
 *   skip — bu kuni shu turdagi dars yo'q.
 * dry_run — faqat natija (hech narsa saqlanmaydi). Qo'llanganda — qaytarish kaliti (undo): oldingi qiymatlar serverda saqlanadi, Ctrl+Z
 * bitta so'rov bilan tiklaydi (jadval keyin o'zgarmagan bo'lsa). Guruhcha smenalari (shift_g) va tanlanmagan kunlar o'zgarmaydi.
 */
trait TimetableCycleDaysMove
{
    private const CYCLE_UNDO_TTL_MINUTES = 120;

    private function cycleDaysMove(Request $request, TimetableBoard $board, TimetableCyclePlacement $placement, array $data, string $mode)
    {
        if (!isset($data['delta']) && !isset($data['start_hour'])) {
            return response()->json(['error' => 'Yangi soat (start_hour) yoki siljish (delta) kerak.'], 422);
        }
        $delta = isset($data['delta']) ? (int) $data['delta'] : null;
        if ($delta === 0) {
            return response()->json(['error' => 'Vaqt o‘zgarmaydi (siljish 0 soat).'], 422);
        }
        // ↑ ↓ (step=jump): delta — faqat yo'nalish (±1); har kunda qadam o'zicha (cycleDayJump): yo'nalishda qo'shni turgan ikkinchi qism
        // (ma'ruza ↔ amaliy) bilan o'rin almashish yoki 1 soat.
        $jumpDir = ($data['step'] ?? null) === 'jump' ? (int) ($delta <=> 0) : 0;
        if (($data['step'] ?? null) === 'jump' && ($delta === null || abs($delta) !== 1)) {
            return response()->json(['error' => 'Qadam (step=jump) uchun siljish +1 yoki -1 bo‘lishi kerak.'], 422);
        }
        // Har kunning o'z rejimi (day_modes: sikl kuni => both | lecture | practice; berilmagan kun — mode): ma'ruza va amaliy aralash tanlov
        // bitta so'rovda — bitta tranzaksiya (hammasi yoki hech narsa), bitta qaytarish kaliti, tasdiq doskasida bitta so'rov.
        $modes = array_map('strval', (array) ($data['day_modes'] ?? []));
        $dry = $request->boolean('dry_run');
        $force = !$dry && $request->boolean('force');
        // Bugungi dars — faqat admin, superadmin va registrator ofisi (boshlangan, tugagan bo'lsa ham; yangi vaqt — bugunning istalgan
        // parasi); o'tgan kun — hamma uchun yopiq.
        $todayOk = $this->staffTodayOk($request);
        $today = self::proposalToday();
        $todayMoved = false;
        $grid = $this->cycleGridHours($board);
        $sub = trim((string) ($data['sub_group'] ?? ''));
        $days = array_values(array_unique(array_map('intval', (array) $data['day_indexes'])));
        sort($days);
        $beforeSlots = (array) ($placement->fresh()->lecture_slots ?? []);
        $out = [];
        $applied = 0;
        DB::beginTransaction();
        try {
            foreach ($days as $k) {
                $placement = $placement->fresh() ?? $placement;
                $units = $this->cycleDayUnits($board, $placement, $data, $k);
                $row = ['day_index' => $k, 'date' => null, 'status' => 'skip', 'why' => 'Bu kun blokda yo‘q.', 'from' => null, 'to' => null,
                    'applied' => false];
                if (!$units) {
                    $out[] = $row;
                    continue;
                }
                $date = reset($units)['date'];
                $dayMode = in_array($modes[$k] ?? '', ['both', 'lecture', 'practice'], true) ? $modes[$k] : $mode;
                $row['mode'] = $dayMode;
                [$part, $span, $why, $rel, $starts] = array_pad($this->cycleDayMovingPart($units, $dayMode, $sub, (array) ($placement->lecture_slots ?? []), $k), 5, null);
                if (!$part) {
                    $out[] = array_merge($row, ['date' => $date, 'why' => $why]);
                    continue;
                }
                $jump = $jumpDir ? $this->cycleDayJump($units, $dayMode, $sub, $jumpDir) : null;
                $h = $jump ? $part[0] + $jump['delta'] : ($delta !== null ? $part[0] + $delta : (int) $data['start_hour']);
                $shift = $h - $part[0];
                $row = array_merge($row, ['date' => $date, 'from' => $part[0], 'to' => $h, 'len' => $part[1] - $part[0], 'jump' => (bool) ($jump['swap'] ?? false)]);
                // Yangi joy: $rel — yangi boshdan nisbiy oraliq ("Faqat amaliy" — hammasi bitta $h dan); false — oldindan aytib bo'lmaydi
                // (guruhcha qatoridagi smenali amaliy), kunga sig'ishi yozuvdan keyingi holat bo'yicha; aks holda oraliq siljiydi.
                [$lo, $hi] = $rel === false ? [$h, null] : ($rel !== null ? [$h + $rel[0], $h + $rel[1]] : [$span[0] + $shift, $span[1] + $shift]);
                $row['today'] = $date === $today;
                if ($date < $today || ($date === $today && !$todayOk)) {
                    $out[] = array_merge($row, ['status' => 'past', 'why' => $date < $today ? 'O‘tgan kun.' : self::todayStaffOnlyText()]);
                    continue;
                }
                // "Faqat amaliy" — hamma guruhcha amaliyoti allaqachon shu soatda bo'lsagina o'zgarmaydi (oqim qatorida har xil soatda bo'lishi mumkin).
                if ($starts !== null ? !array_filter($starts, fn ($st) => $st !== $h) : $shift === 0) {
                    $out[] = array_merge($row, ['status' => 'same', 'why' => 'Vaqt o‘zgarmaydi.']);
                    continue;
                }
                if ($lo < 1 || ($hi !== null && $hi - 1 > $grid)) {
                    $out[] = array_merge($row, ['status' => 'nofit', 'why' => 'Kunga sig‘maydi: ' . ($hi !== null ? ($hi - $lo) . ' soat ' : '')
                        . $lo . '-soatdan boshlab bo‘lmaydi (kunda ' . $grid . ' soat).']);
                    continue;
                }
                $p = array_merge($data, ['day_index' => $k, 'start_hour' => $h]);
                DB::beginTransaction();   // shu kun — savepoint
                try {
                    // Sakrash: ma'ruza — qo'shni amaliyot aniq soatga (pin); amaliy — ma'ruza bilan o'rin almashish (aniq soatga).
                    $e = match ($dayMode) {
                        'lecture' => $this->cycleLectureDayErrors($board, $placement, $p, $units, true, $jump['pin'] ?? []),
                        'practice' => (isset($jump['lecture_at']) || $request->boolean('lecture_swap')
                            ? $this->cyclePracticeLectureSwap($board, $placement, $p, $units, $sub, $k, $h, $jump['lecture_at'] ?? null) : null)
                            ?? $this->cyclePracticeDayErrors($board, $units, $sub, $k, $h),
                        default => $this->cycleBothDayWrite($board, $placement, $p, $units, true),
                    };
                } catch (\Throwable $ex) {
                    DB::rollBack();
                    throw $ex;
                }
                $row['swaps'] = $e['swaps'] ?? [];
                $row['lecture'] = $e['lecture'] ?? null;
                // Oldindan tekshirilmagan kun (guruhcha qatoridagi smenali amaliy): "Birga" yozuvidan keyingi xato — faqat kun oxiriga sig'maslik
                // (kun rejasi ma'ruza va amaliyni ustma-ust qo'ymaydi) — "sig'maydi".
                $fitOnly = $rel === false && $e['hard'];
                if ($e['hard'] || ($e['busy'] && !$force)) {
                    DB::rollBack();
                    $row = array_merge($row, $e['hard'] ? ['status' => $fitOnly ? 'nofit' : 'blocked', 'why' => implode(' · ', $e['hard'])]
                        : ['status' => 'conflict', 'why' => implode(' · ', $e['busy'])]);
                } else {
                    DB::commit();
                    $applied++;
                    $todayMoved = $todayMoved || $date === $today;
                    $row = array_merge($row, ['status' => $e['busy'] ? 'conflict' : 'ok', 'why' => $e['busy'] ? implode(' · ', $e['busy']) : null,
                        'applied' => !$dry]);
                }
                request()->attributes->remove('timetable.cycle_units.' . $board->id);
                $out[] = $row;
            }
            $after = $applied ? (array) ($placement->fresh()->lecture_slots ?? []) : null;
            if ($dry || !$applied) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $ex) {
            DB::rollBack();
            throw $ex;
        } finally {
            request()->attributes->remove('timetable.cycle_units.' . $board->id);
        }
        // Tasdiqlangan so'rov qayta bajarilganda (TimetableChangeApproval) birorta kun o'zgarmasa — xato: so'rov "tasdiqlandi" bo'lib,
        // o'zgarish xabarlari ketmasin (sana o'tib ketgan yoki joy band bo'lib qolgan). To'g'ridan-to'g'ri so'rovda — kun bo'yicha natija.
        if (!$dry && !$applied && $request->attributes->has('timetable.change_replay')) {
            return response()->json(['error' => 'Hech bir kun o‘zgarmadi: ' . implode('; ', array_map(fn ($r) => ($r['date'] ? Carbon::parse($r['date'])->format('d.m')
                : ($r['day_index'] + 1) . '-kun') . ' — ' . ($r['why'] ?: $r['status']), $out)), 'days' => $out], 422);
        }
        $undo = null;
        $todayMoved = $todayMoved && !$dry;
        if ($todayMoved) {
            $this->markTodayMoved($request);
        }
        if (!$dry && $applied) {
            $undo = Str::random(32);
            Cache::put($this->cycleUndoKey($board, $undo), ['placement' => (int) $placement->id, 'before' => $beforeSlots,
                'after' => md5(json_encode($after))], now()->addMinutes(self::CYCLE_UNDO_TTL_MINUTES));
        }

        return response()->json(['ok' => true, 'dry_run' => $dry, 'mode' => $mode, 'delta' => $delta, 'days' => $out,
            'applied' => $dry ? 0 : $applied, 'undo' => $undo, 'today_moved' => $todayMoved]
            // Bugungi kun ham o'zgaradi (yoki o'zgardi) — HEMIS eslatmasi (tasdiq oynasi va natija).
            + (collect($out)->contains(fn ($r) => !empty($r['today']) && in_array($r['status'], ['ok', 'conflict'], true)) ? ['hemis_hint' => self::todayHemisHint()] : []));
    }

    /**
     * Sikl kunida surilayotgan qism: [qism [boshi, oxiri) — delta shu boshidan hisoblanadi, kun bo'yicha suriladigan oraliq [boshi, oxiri)
     * (kunga sig'ishi va "boshlanganmi" uchun), sabab (qism yo'q bo'lsa)]. "Birga": boshi — qatorning kun boshi (oqim — starts, guruhcha —
     * starts_g, aks holda oqimniki; front — dp.start), oraliq — suriladigan guruhchalar (guruhcha qatorida o'zi, oqimda — shu kunni o'zi
     * surmaganlar) ma'ruza va amaliysi. Ma'ruza — oqimniki; amaliy — guruhcha qatorida o'ziniki, oqimda — birinchi guruhchaniki; amaliyda
     * 4-qiymat — yangi boshdan nisbiy yangi oraliq, yozuvdan keyingi jadval oddiy surishdan farq qilsa (amaliy — hammasi bitta yangi
     * soatdan, eng uzun amaliy bo'yicha) yoki false — oldindan aytib bo'lmaydi ("Birga" guruhcha qatoridagi smenali amaliy: kunga sig'ishi
     * yozuvdan keyin), 5-qiymat — har guruhcha amaliy boshi ("o'zgarmaydi" — hammasi yangi soatda bo'lsagina).
     */
    private function cycleDayMovingPart(array $units, string $mode, string $sub, array $slots, int $k): array
    {
        $date = reset($units)['date'];
        $own = (array) ($slots['starts_g'] ?? []);
        $mine = fn ($unit) => $sub !== '' ? (string) $unit === $sub : true;
        $lec = null;
        $prc = [];
        foreach ($units as $unit => $x) {
            $lec ??= $x['lec'][$date] ?? null;
            if ($mine($unit) && ($r = $x['prac'][$date] ?? null)) {
                $prc[] = $r;
            }
        }
        if ($mode === 'lecture') {
            return $lec ? [$lec, $lec, null] : [null, null, 'Bu kuni ma’ruza yo‘q.'];
        }
        if ($mode === 'practice') {
            return $prc ? [$prc[0], [min(array_column($prc, 0)), max(array_column($prc, 1))], null,
                [0, max(array_map(fn ($r) => $r[1] - $r[0], $prc))], array_map(fn ($r) => (int) $r[0], $prc)] : [null, null, 'Bu kuni amaliy dars yo‘q.'];
        }
        $flowStart = (int) (((array) ($slots['starts'] ?? []))[$k] ?? 1);
        $base = max(1, $sub !== '' ? (int) (((array) ($own[$sub] ?? []))[$k] ?? $flowStart) : $flowStart);
        $ranges = [];
        $fixed = false;
        foreach ($units as $unit => $x) {
            if ($sub !== '' ? (string) $unit !== $sub : isset(((array) ($own[$unit] ?? []))[$k])) {
                continue;
            }
            // Smenasi qotirilgan amaliy (shift_g, shu kunga ps_g yo'q) oqim qatorida kun boshi bilan surilmaydi — oraliqqa kirmaydi
            // (cycleBothDayWrite). Shu kunni o'zi surgan guruhcha (starts_g) yuqorida o'tkazib yuborilgan; guruhcha qatorida esa surish starts_g
            // yozadi va cycleStarts smenani o'chiradi — amaliy kun boshiga ergashadi, shuning uchun oraliqda qoladi.
            $stays = $sub === '' && (int) (((array) ($slots['shift_g'] ?? []))[$unit] ?? 0) > 0
                && (int) ((((array) ($slots['ps_g'] ?? []))[$unit] ?? [])[$k] ?? 0) <= 0;
            $fixed = $fixed || ($stays && isset($x['prac'][$date]));
            array_push($ranges, ...array_filter([$x['lec'][$date] ?? null, $stays ? null : ($x['prac'][$date] ?? null)]));
        }
        if (!$ranges) {
            return [null, null, $fixed ? 'Bu kuni faqat smenada qotirilgan amaliy — kun boshi bilan surilmaydi.' : 'Bu kuni dars yo‘q.'];
        }
        // Guruhcha qatori, smena bor, shu kunga ps_g yo'q: yozuv starts_g ni qo'yadi (amaliy smenadan ma'ruzaga ergashadi) yoki oqim soatiga
        // qaytsa o'chiradi (smena qayta yoqiladi) — yangi jadvalni oldindan aytib bo'lmaydi: kunga sig'ishi yozuvdan keyin tekshiriladi.
        $deferred = $sub !== '' && isset($units[$sub]) && (int) (((array) ($slots['shift_g'] ?? []))[$sub] ?? 0) > 0
            && (int) ((((array) ($slots['ps_g'] ?? []))[$sub] ?? [])[$k] ?? 0) <= 0;

        return [[$base, $base + 1], [min(array_column($ranges, 0)), max(array_column($ranges, 1))], null, $deferred ? false : null];
    }

    /**
     * ↑ ↓ qadami (step=jump) bitta sikl kunida: surilayotgan qism yo'nalishda ikkinchi qism bilan yonma-yon bo'lsa — o'rin almashadi:
     * ikkinchi qism butunligicha (bo'linmasdan) birinchisining joyiga, birinchisi uning ortidan — ikkalasi avvalgi oraliqda, tartibi teskari
     * (para chegarasiga tekislanmaydi). Masalan, ma'ruza 1–2, amaliy 3–6: ma'ruza ↓ — amaliy 1–4, ma'ruza 5–6; yana ↓ (ostida bo'sh) —
     * ma'ruza 6–7. Yonma-yon bo'lmasa — 1 soat. Qo'shni amaliyot — shu qatorniki (guruhcha qatorida — o'zi, oqimda — hammasi; amaliy
     * rejimida — birinchisi, cycleDayMovingPart kabi). "Birga" — doim 1 soat.
     * Natija: ['delta' => ma'ruza / amaliy boshining siljishi, 'swap' => o'rin almashadimi, 'pin' => [guruhcha => amaliyot yangi boshi]
     * (ma'ruza), 'lecture_at' => ma'ruza yangi boshi (amaliy) | yo'q].
     */
    private function cycleDayJump(array $units, string $mode, string $sub, int $dir): array
    {
        $step = ['delta' => $dir, 'swap' => false, 'pin' => []];
        if ($mode === 'both') {
            return $step;
        }
        $date = reset($units)['date'];
        $lec = null;
        $prc = [];
        foreach ($units as $unit => $x) {
            $lec ??= $x['lec'][$date] ?? null;
            if (($sub === '' || (string) $unit === $sub) && ($r = $x['prac'][$date] ?? null)) {
                $prc[(string) $unit] = [(int) $r[0], (int) $r[1]];
            }
        }
        if (!$lec || !$prc) {
            return $step;
        }
        [$l0, $l1] = [(int) $lec[0], (int) $lec[1]];
        if ($mode === 'lecture') {
            $adj = array_filter($prc, fn ($r) => $dir > 0 ? $r[0] === $l1 : $r[1] === $l0);
            if (!$adj) {
                return $step;
            }
            $plen = max(array_map(fn ($r) => $r[1] - $r[0], $adj));
            $h = $dir > 0 ? $l0 + $plen : $l0 - $plen;
            // Amaliyot: pastga — ma'ruzaning eski boshiga; tepaga — yangi ma'ruzadan keyin.
            $at = $dir > 0 ? $l0 : $h + ($l1 - $l0);

            return ['delta' => $h - $l0, 'swap' => true, 'pin' => array_map(fn () => $at, $adj)];
        }
        [$p0, $p1] = reset($prc);
        if ($dir > 0 ? $l0 !== $p1 : $l1 !== $p0) {
            return $step;
        }
        // Amaliy: pastga — ma'ruza amaliyotning eski boshiga, amaliy uning ortidan; tepaga — amaliy ma'ruza boshiga, ma'ruza uning ortidan.
        $h = $dir > 0 ? $p0 + ($l1 - $l0) : $l0;

        return ['delta' => $h - $p0, 'swap' => true, 'pin' => [], 'lecture_at' => $dir > 0 ? $p0 : $l0 + ($p1 - $p0)];
    }

    /**
     * "Faqat amaliy" bitta sikl kuni: guruhcha qatorida shu guruhcha, oqim qatorida — shu kuni amaliy darsi bor barcha guruhchalar
     * yangi soatga (ps_g); hard — kun oxiri va ma'ruza bilan ustma-ust, busy — bandlik (cyclePracticeBusyErrors). Yozuv — chaqiruvchi
     * tranzaksiyasida.
     */
    private function cyclePracticeDayErrors(TimetableBoard $board, array $units, string $sub, int $k, int $h): array
    {
        $units = array_filter($units, fn ($x, $unit) => ($sub === '' || (string) $unit === $sub) && isset($x['prac'][$x['date']]), ARRAY_FILTER_USE_BOTH);
        if (!$units) {
            return ['hard' => ['Bu kuni amaliy dars yo‘q.'], 'busy' => []];
        }
        $grid = $this->cycleGridHours($board);
        $hard = [];
        foreach ($units as $x) {
            $d = $x['date'];
            [$from, $to] = $x['prac'][$d];
            $len = $to - $from;
            if ($h < 1 || $h + $len - 1 > $grid) {
                $hard[] = 'Amaliy dars (' . $len . ' soat) kun oxirigacha sig‘maydi: ' . $h . '-soatdan boshlab bo‘lmaydi.';
            }
            if (!$this->isNonBlocking($x['u'], $board) && ($l = $x['lec'][$d] ?? null) && $h < $l[1] && $l[0] < $h + $len) {
                $hard[] = 'Ma’ruza vaqti bilan ustma-ust (' . Carbon::parse($d)->format('d.m') . ', ma’ruza ' . $this->cycleHoursTime($board, $l[0], $l[1]) . ').';
            }
        }
        if ($hard) {
            return ['hard' => array_values(array_unique($hard)), 'busy' => []];
        }
        $list = array_values($units);
        $days = [];
        foreach ($units as $unit => $x) {
            $days[(string) $unit][$k] = $x['date'];
        }
        $this->writeCyclePracticeStarts($list, $days, $h);

        return ['hard' => [], 'busy' => $this->cyclePracticeBusyErrors($board, $list, $days, $h)];
    }

    /**
     * "Faqat amaliy" ma'ruza ustiga (lecture_swap): amaliyot yangi soatga ($h), ma'ruza esa amaliyotning bo'shagan joyiga (sig'masa — yangi
     * amaliyotdan keyin yoki oldin, para chegarasidan). Oqimning boshqa guruhchalari amaliyoti yangi ma'ruza bilan kesishsa — practice_place
     * usuli bo'yicha (cycleLectureDayErrors). Amaliyot ma'ruza bilan kesishmasa — null (odatdagi "Faqat amaliy"). Natija: hard / busy / swaps
     * va lecture {from, to, len}. $lecAt — ma'ruzaning aniq yangi boshi (↑ ↓ sakrashi, cycleDayJump): birinchi bo'lib sinaladi.
     */
    private function cyclePracticeLectureSwap(TimetableBoard $board, TimetableCyclePlacement $placement, array $p, array $units, string $sub, int $k, int $h,
        ?int $lecAt = null): ?array
    {
        $mine = array_filter($units, fn ($x, $unit) => ($sub === '' || (string) $unit === $sub) && isset($x['prac'][$x['date']])
            && !$this->isNonBlocking($x['u'], $board), ARRAY_FILTER_USE_BOTH);
        $lec = null;
        foreach ($units as $x) {
            $lec ??= $x['lec'][$x['date']] ?? null;
        }
        if (!$mine || !$lec) {
            return null;
        }
        $first = reset($mine);
        $p0 = (int) $first['prac'][$first['date']][0];
        $end = $h + max(array_map(fn ($x) => (int) $x['prac'][$x['date']][1] - (int) $x['prac'][$x['date']][0], $mine));
        [$l0, $llen] = [(int) $lec[0], (int) $lec[1] - (int) $lec[0]];
        if (!($h < $l0 + $llen && $l0 < $end)) {
            return null;
        }
        $grid = $this->cycleGridHours($board);
        $rph = max(1, $this->cycleRowHours($board));
        $up = $end + (($rph - (($end - 1) % $rph)) % $rph);
        $down = $h - $llen >= 1 ? ($h - $llen) - (($h - $llen - 1) % $rph) : 0;
        $at = null;
        foreach (array_merge($lecAt !== null ? [$lecAt] : [], [$p0, $up, $down]) as $c) {
            if ($c >= 1 && $c + $llen - 1 <= $grid && !($c < $end && $h < $c + $llen) && $c !== $l0) {
                $at = $c;
                break;
            }
        }
        if ($at === null) {
            return ['hard' => ["Ma’ruza bilan o‘rin almashib bo‘lmaydi: ma’ruza ({$llen} soat) uchun shu kunda joy yo‘q."], 'busy' => [], 'swaps' => [],
                'lecture' => null];
        }
        $pin = array_map(fn () => $h, $mine);
        $e = $this->cycleLectureDayErrors($board, $placement, array_merge($p, ['start_hour' => $at]), $units, true, $pin);

        return $e + ['lecture' => ['from' => $l0, 'to' => $at, 'len' => $llen]];
    }

    /** Oxirgi ko'p kunli surishni qaytarish: jadval keyin o'zgarmagan bo'lsa — oldingi qiymatlar (bitta so'rov). */
    private function cycleDaysUndo(TimetableBoard $board, TimetableCyclePlacement $placement, string $token)
    {
        $key = $this->cycleUndoKey($board, $token);
        $snap = Cache::get($key);
        if (!is_array($snap) || (int) ($snap['placement'] ?? 0) !== (int) $placement->id) {
            return response()->json(['error' => 'Qaytarish muddati o‘tgan yoki bu blokka tegishli emas.'], 410);
        }
        $placement = $placement->fresh();
        if (md5(json_encode((array) ($placement->lecture_slots ?? []))) !== (string) ($snap['after'] ?? '')) {
            return response()->json(['error' => 'Blok surilgandan keyin yana o‘zgargan — qaytarib bo‘lmaydi.'], 409);
        }
        $placement->update(['lecture_slots' => (array) $snap['before']]);
        Cache::forget($key);
        request()->attributes->remove('timetable.cycle_units.' . $board->id);

        return response()->json(['ok' => true, 'undone' => true]);
    }

    private function cycleUndoKey(TimetableBoard $board, string $token): string
    {
        return 'timetable:cycle-undo:' . $board->id . ':' . $token;
    }
}

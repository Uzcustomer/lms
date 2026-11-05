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
 * hammasiga bir xil. Bitta tranzaksiya: har kun alohida sinab ko'riladi (savepoint), natija kun bo'yicha:
 *   ok — o'zgaradi; conflict — bandlik (o'qituvchi / xona / guruhning haftalik darsi) — force bo'lsa baribir qo'llanadi;
 *   nofit — kun oxiriga sig'maydi; blocked — ma'ruza va amaliy ustma-ust va h.k. (force ham qo'llamaydi);
 *   past — o'tgan kun yoki bugungi boshlangan dars (bugun — faqat admin / registrator, hali boshlanmagan dars); same — vaqt o'zgarmaydi;
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
        $dry = $request->boolean('dry_run');
        $force = !$dry && $request->boolean('force');
        // Bugungi dars — faqat admin va registrator ofisi, hali boshlanmagan bo'lsa (yangi vaqt ham kelgusida).
        $todayOk = in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true);
        $today = self::proposalToday();
        $now = Carbon::now('Asia/Tashkent')->format('H:i');
        $times = $this->hemisSlotTimes($board);
        $started = fn (int $h) => ($times[$h][0] ?? '00:00') <= $now;
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
                [$part, $span, $why, $rel, $starts] = array_pad($this->cycleDayMovingPart($units, $mode, $sub, (array) ($placement->lecture_slots ?? []), $k), 5, null);
                if (!$part) {
                    $out[] = array_merge($row, ['date' => $date, 'why' => $why]);
                    continue;
                }
                $h = $delta !== null ? $part[0] + $delta : (int) $data['start_hour'];
                $shift = $h - $part[0];
                $row = array_merge($row, ['date' => $date, 'from' => $part[0], 'to' => $h, 'len' => $part[1] - $part[0]]);
                // Yangi joy: $rel — yangi boshdan nisbiy oraliq (yozuvdan keyingi jadval surishdan farq qilsa: "Faqat amaliy" — hammasi bitta
                // $h dan, guruhcha qatoridagi smenali amaliy — ma'ruzadan keyinga); aks holda oraliq siljiydi.
                [$lo, $hi] = $rel !== null ? [$h + $rel[0], $h + $rel[1]] : [$span[0] + $shift, $span[1] + $shift];
                if ($date < $today || ($date === $today && (!$todayOk || $started(min($span[0], $lo))))) {
                    $out[] = array_merge($row, ['status' => 'past', 'why' => $date < $today ? 'O‘tgan kun.'
                        : ($todayOk ? 'Bugungi dars boshlangan (yoki yangi vaqt o‘tib ketgan).' : 'Bugungi darsni ko‘chirib bo‘lmaydi.')]);
                    continue;
                }
                // "Faqat amaliy" — hamma guruhcha amaliyoti allaqachon shu soatda bo'lsagina o'zgarmaydi (oqim qatorida har xil soatda bo'lishi mumkin).
                if ($starts !== null ? !array_filter($starts, fn ($st) => $st !== $h) : $shift === 0) {
                    $out[] = array_merge($row, ['status' => 'same', 'why' => 'Vaqt o‘zgarmaydi.']);
                    continue;
                }
                if ($lo < 1 || $hi - 1 > $grid) {
                    $out[] = array_merge($row, ['status' => 'nofit', 'why' => 'Kunga sig‘maydi: ' . ($hi - $lo) . ' soat '
                        . $lo . '-soatdan boshlab bo‘lmaydi (kunda ' . $grid . ' soat).']);
                    continue;
                }
                $p = array_merge($data, ['day_index' => $k, 'start_hour' => $h]);
                DB::beginTransaction();   // shu kun — savepoint
                try {
                    $e = match ($mode) {
                        'lecture' => $this->cycleLectureDayErrors($board, $placement, $p, $units, true),
                        'practice' => ($request->boolean('lecture_swap') ? $this->cyclePracticeLectureSwap($board, $placement, $p, $units, $sub, $k, $h) : null)
                            ?? $this->cyclePracticeDayErrors($board, $units, $sub, $k, $h),
                        default => $this->cycleBothDayWrite($board, $placement, $p, $units, true),
                    };
                } catch (\Throwable $ex) {
                    DB::rollBack();
                    throw $ex;
                }
                // Birga ko'chgan amaliyot / ma'ruza ham: bugun — boshlanmagan (eski va yangi vaqti) bo'lishi kerak.
                $also = [];
                foreach (array_merge($e['swaps'] ?? [], array_filter([$e['lecture'] ?? null])) as $w) {
                    array_push($also, (int) $w['from'], (int) $w['to']);
                }
                if (!$e['hard'] && $date === $today && $also && $started(min($also))) {
                    $e['hard'] = ['Bugun birga ko‘chadigan dars boshlangan (yoki yangi vaqti o‘tib ketgan).'];
                }
                $row['swaps'] = $e['swaps'] ?? [];
                $row['lecture'] = $e['lecture'] ?? null;
                if ($e['hard'] || ($e['busy'] && !$force)) {
                    DB::rollBack();
                    $row = array_merge($row, $e['hard'] ? ['status' => 'blocked', 'why' => implode(' · ', $e['hard'])]
                        : ['status' => 'conflict', 'why' => implode(' · ', $e['busy'])]);
                } else {
                    DB::commit();
                    $applied++;
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
        if (!$dry && $applied) {
            $undo = Str::random(32);
            Cache::put($this->cycleUndoKey($board, $undo), ['placement' => (int) $placement->id, 'before' => $beforeSlots,
                'after' => md5(json_encode($after))], now()->addMinutes(self::CYCLE_UNDO_TTL_MINUTES));
        }

        return response()->json(['ok' => true, 'dry_run' => $dry, 'mode' => $mode, 'delta' => $delta, 'days' => $out,
            'applied' => $dry ? 0 : $applied, 'undo' => $undo]);
    }

    /**
     * Sikl kunida surilayotgan qism: [qism [boshi, oxiri) — delta shu boshidan hisoblanadi, kun bo'yicha suriladigan oraliq [boshi, oxiri)
     * (kunga sig'ishi va "boshlanganmi" uchun), sabab (qism yo'q bo'lsa)]. "Birga": boshi — qatorning kun boshi (oqim — starts, guruhcha —
     * starts_g, aks holda oqimniki; front — dp.start), oraliq — suriladigan guruhchalar (guruhcha qatorida o'zi, oqimda — shu kunni o'zi
     * surmaganlar) ma'ruza va amaliysi. Ma'ruza — oqimniki; amaliy — guruhcha qatorida o'ziniki, oqimda — birinchi guruhchaniki; amaliyda
     * 4-qiymat — yangi boshdan nisbiy yangi oraliq, yozuvdan keyingi jadval oddiy surishdan farq qilsa (amaliy — hammasi bitta yangi
     * soatdan, eng uzun amaliy bo'yicha; "Birga" guruhcha qatoridagi smenali amaliy — ma'ruzadan keyinga), 5-qiymat — har guruhcha amaliy
     * boshi ("o'zgarmaydi" — hammasi yangi soatda bo'lsagina).
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
        // Guruhcha qatori, smena bor, shu kunga ps_g yo'q: surish starts_g yozadi, cycleStarts smenani o'chiradi — amaliy ma'ruzadan keyin
        // (kun boshi + ma'ruza). Yangi joy — kun boshidan ma'ruza + amaliy (o'z kun boshi bo'lsa ham shunday — amaliy allaqachon ergashadi).
        $rel = null;
        $x = $sub !== '' ? ($units[$sub] ?? null) : null;
        if ($x && (int) (((array) ($slots['shift_g'] ?? []))[$sub] ?? 0) > 0 && (int) ((((array) ($slots['ps_g'] ?? []))[$sub] ?? [])[$k] ?? 0) <= 0) {
            $len = fn ($r) => $r ? (int) $r[1] - (int) $r[0] : 0;
            $rel = [0, $len($x['lec'][$date] ?? null) + $len($x['prac'][$date] ?? null)];
        }

        return [[$base, $base + 1], [min(array_column($ranges, 0)), max(array_column($ranges, 1))], null, $rel];
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
     * va lecture {from, to, len}.
     */
    private function cyclePracticeLectureSwap(TimetableBoard $board, TimetableCyclePlacement $placement, array $p, array $units, string $sub, int $k, int $h): ?array
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
        foreach ([$p0, $up, $down] as $c) {
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

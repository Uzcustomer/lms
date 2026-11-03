<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * "Takliflar": tanlangan haftada joylanmagan har bir karta (va joyi bor, lekin ichki jadvalda to'qnashuvli karta — panel va
 * hisoblagichdagidek, weeklyConflicts) uchun uni sig'dirish yo'llari, tartib bo'yicha:
 *   1) shu kunning bo'sh vaqti (shu fanning joylashgan qismiga ulab);
 *   2) xona band — shu vaqtda bo'sh boshqa xona (faqat shu hafta);
 *   3) to'sib turgan amaliy darsni (o'qituvchi/guruh/xona) shu haftaning boshqa vaqtiga surish;
 *   4) shu haftaning boshqa kuni;
 *   5) boshqa hafta (karta o'tilmaydigan haftaga, shu hafta bekor qilinadi — KTR taqsimoti o'zgaradi);
 *   6) eng oxirida — shu kafedraning shu vaqtda bo'sh o'qituvchisini biriktirish.
 * Hech narsa yozilmaydi: har bir taklifda bajariladigan amallar (`actions`) qaytariladi, ularni foydalanuvchi
 * "Qo'llash" bilan oddiy endpointlar orqali bajaradi (har birida server qayta tekshiradi).
 * To'qnashuvli karta: maqsad — hozirgi joyi; xona to'qnashuvida birinchi — shu vaqtda bo'sh boshqa xona (shu hafta), so'ng
 * ikkinchi tomonning (to'sayotgan kartaning) boshqa xonasi, keyin 1, 3–6-qadamlar.
 * Ma'ruza vaqti tashqi jadvaldagi kun/vaqt (ma'ruza xonalari umumiy): uni siljitadigan takliflar (ma'ruzaning o'zini
 * yoki to'sib turgan ma'ruzani surish) ogohlantirish bilan oxirroqda — o'qituvchi almashtirishdan oldin — beriladi.
 */
trait TimetableWeekSuggestions
{
    /** Tekshiruv uchun: optimizatsiyadan oldingi yo'l (indekssiz findWeekConflicts, keshsiz, 6-qadam har hafta alohida). */
    private bool $weekSuggestionsLegacy = false;

    public function weekSuggestions(Request $request, TimetableBoard $board)
    {
        $this->roomRankReset();
        // Huquq — foydalanuvchining barcha rollaridan eng kengi; oynada faqat qamrovidagi kartalar (server filtrlaydi).
        $rights = $this->suggestionRights($this->timetableActor($request));
        if ($rights['level'] === null && !$rights['view']) {
            abort(403, 'Takliflar sizning rolingiz uchun ochiq emas.');
        }
        $data = $request->validate([
            'week'              => 'required|integer|min:1|max:60',
            'faculty_names'     => 'nullable|array',
            'faculty_names.*'   => 'nullable|string|max:255',
            'specialty_names'   => 'nullable|array',
            'specialty_names.*' => 'string|max:255',
            'courses'           => 'nullable|array',
            'courses.*'         => 'integer|min:1|max:7',
            'type'              => 'nullable|in:all,lecture,practice',
            'limit'             => 'nullable|integer|min:1|max:300',
            // Bitta karta uchun to'liq takliflar (vaqt byudjeti tugab, qisqartirilgan karta bosilganda).
            'card_id'           => 'nullable|integer',
        ]);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        $W = (int) $data['week'];
        [$facSet, $specSet, $courseSet] = $this->scopeSets($data);
        $q = TimetableCard::where('board_id', $board->id);
        $this->applyScopeToQuery($q, $facSet, $specSet, $courseSet);
        if (in_array($data['type'] ?? 'all', ['lecture', 'practice'], true)) {
            $q->where('training_type', $data['type']);
        }
        $scopeIds = $q->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Sikl fanlari haftalik panjarada emas (ularning kunlari sikl kalendarida) — joylanmaganlar qatoriga kirmaydi.
        $cycleKeys = $this->cycleSubjectKeys($board);
        $isCycle = function (TimetableCard $c) use ($cycleKeys): bool {
            $k = $this->specKey($c->specialty_name) . '|' . (int) $c->course . '|';
            $base = $this->cycleSubjectVariantBase((string) $c->subject_name);

            return isset($cycleKeys[$k . $this->normSubject((string) $c->subject_name)])
                || ($base !== null && isset($cycleKeys[$k . $this->normSubject($base)]));
        };

        $state = $this->occupancyState($board->id);
        $state['__v'] = 0;   // holat versiyasi: rejadagi har bir ko'chirish — yangi versiya (tekshiruv keshi kaliti)
        $eff = function (TimetableCard $c, int $w) use (&$state): ?array {
            $ov = $state['ov'][(int) $c->id][$w] ?? null;
            if ($ov) {
                return $ov->cancelled || !$ov->day || !$ov->pair ? null : [(int) $ov->day, (int) $ov->pair];
            }

            return $c->day && $c->pair ? [(int) $c->day, (int) $c->pair] : null;
        };
        $cancelled = fn (TimetableCard $c, int $w) => (bool) (($state['ov'][(int) $c->id][$w] ?? null)?->cancelled);

        $unplaced = [];
        $placedIds = [];
        foreach ($scopeIds as $id) {
            $c = $state['cards'][$id] ?? null;
            if (!$c || $isCycle($c) || $cancelled($c, $W) || !$this->suggestionInScope($rights, $c)) {
                continue;
            }
            if ($eff($c, $W)) {
                $placedIds[$id] = true;
            } else {
                $unplaced[] = $c;
            }
        }
        // Joyi bor, lekin shu haftada to'qnashuvli kartalar (panel va hisoblagichdagi internalConflicts mantig'i — yengil variant).
        $conflicts = $placedIds ? $this->weeklyConflicts($board, $state, $placedIds, $W) : [];
        $conflicted = [];
        foreach ($conflicts as $id => $weeks) {
            if (isset($weeks[$W])) {
                $conflicted[] = $state['cards'][$id];
            }
        }
        $onlyCard = isset($data['card_id']) ? (int) $data['card_id'] : null;
        if ($onlyCard !== null) {
            $unplaced = array_values(array_filter($unplaced, fn ($c) => (int) $c->id === $onlyCard));
            $conflicted = array_values(array_filter($conflicted, fn ($c) => (int) $c->id === $onlyCard));
        }
        $limit = (int) ($data['limit'] ?? 150);
        $unplacedTotal = count($unplaced);
        $conflictTotal = count($conflicted);
        $total = $unplacedTotal + $conflictTotal;
        $conflictIds = array_flip(array_map(fn ($c) => (int) $c->id, $conflicted));
        $unplaced = array_slice(array_merge($unplaced, $conflicted), 0, $limit);

        $baseSubj = fn (?string $s) => trim(preg_replace('/\s*\([abc]\)\s*$/u', '', (string) $s));
        $isBolak = fn (TimetableCard $c) => (bool) preg_match('/\([abc]\)\s*$/u', (string) $c->subject_name);
        $sibKey = function (TimetableCard $c) use ($baseSubj) {
            $g = $c->occupiedGroups();
            sort($g);

            return $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course . '|' . implode(',', $g) . '|' . $baseSubj($c->subject_name);
        };
        $bySib = [];
        foreach ($state['cards'] as $c) {
            if ($c->training_type !== 'lecture') {
                $bySib[$sibKey($c)][] = $c;
            }
        }
        $dayNames = $board->day_names ?? null;
        $dayName = function (int $d) use ($dayNames) {
            $def = [1 => 'Dushanba', 2 => 'Seshanba', 3 => 'Chorshanba', 4 => 'Payshanba', 5 => 'Juma', 6 => 'Shanba', 7 => 'Yakshanba'];
            $names = is_array($dayNames) ? array_values($dayNames) : [];

            return $names[$d - 1] ?? ($def[$d] ?? $d . '-kun');
        };
        $slotText = fn (int $d, int $p, int $len) => $dayName($d) . ', ' . intdiv($p + 1, 2) . '-para' . ($p % 2 ? '' : ' (2-yarmi)')
            . ($len > 2 ? ' + ' . ($len - 2) . ' soat' : '');
        // Tekshiruv: faqat shu kundagi kartalar (findWeekConflictsIndexed); natija so'rov ichida eslab qolinadi — karta, o'qituvchi,
        // xona, hafta, kun, para va holat versiyasi bo'yicha.
        $memo = [];
        $check = $this->weekSuggestionsLegacy
            ? fn (TimetableCard $c, int $w, int $d, int $p, array $st, ?string $room = null) => $this->findWeekConflicts($c, $w, $d, $p, 0, $st, $room)
            : function (TimetableCard $c, int $w, int $d, int $p, array $st, ?string $room = null) use (&$memo) {
                $k = ($st['__v'] ?? 0) . '|' . $c->id . '|' . $c->teacher_id . '|' . $c->auditorium_code . '|' . $w . '|' . $d . '|' . $p . '|' . $room;

                return $memo[$k] ??= $this->findWeekConflictsIndexed($c, $w, $d, $p, 0, $st, $room);
            };
        $ver = 0;
        $withMove = function (array $st, TimetableCard $c, int $w, ?int $d, ?int $p, bool $cancel = false, ?array $room = null) use (&$ver) {
            // weekOverride xona berilmagan ko'chirishda shu haftaga berilgan xonani saqlaydi (bekor qilishda tozalaydi).
            $prev = $cancel ? null : ($st['ov'][(int) $c->id][$w] ?? null);
            $o = new TimetableCardOverride();
            $o->forceFill(['card_id' => $c->id, 'week' => $w, 'day' => $d, 'pair' => $p, 'start_half' => 0, 'cancelled' => $cancel,
                'auditorium_code' => $room ? $room[0] : $prev?->auditorium_code, 'auditorium_name' => $room ? $room[1] : $prev?->auditorium_name]);
            $this->stateSetOverride($st, $o);
            $st['__v'] = ++$ver;

            return $st;
        };
        // Shu vaqtda to'sib turgan kartalar: [karta, sabablar]. Faqat to'qnashuv nomzodlari (kun va o'qituvchi/xona/guruh indeksi).
        $blockers = function (TimetableCard $c, int $w, int $d, int $p, array $st) use ($eff): array {
            $out = [];
            $my = [$p - 1, $p - 1 + $c->lenHalf()];
            $myRoom = ($st['ov'][(int) $c->id][$w] ?? null)?->auditorium_code ?: $c->auditorium_code;
            foreach ($this->weekSuggestionsLegacy ? $st['cards'] : array_intersect_key($st['cards'], $this->clashCandidates($c, $d, $myRoom, $st)) as $o) {
                if ((int) $o->id === (int) $c->id) {
                    continue;
                }
                $ov = $st['ov'][(int) $o->id][$w] ?? null;
                $pl = $ov ? ($ov->cancelled || !$ov->day ? null : [(int) $ov->day, (int) $ov->pair]) : ($o->day && $o->pair ? [(int) $o->day, (int) $o->pair] : null);
                if (!$pl || $pl[0] !== $d || !$this->halfOverlap($my, [$pl[1] - 1, $pl[1] - 1 + $o->lenHalf()])) {
                    continue;
                }
                $why = $this->clashWith($c, $myRoom, $o, $ov?->auditorium_code ?: $o->auditorium_code, $ov?->auditorium_name ?: $o->auditorium_name);
                if ($why) {
                    $out[] = [$o, $why];
                }
            }

            return $out;
        };
        $grids = [];
        $gridOf = function (TimetableCard $c) use (&$grids, $board) {
            $k = $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course . '|' . $this->cardCurriculumId($c);

            return $grids[$k] ??= $this->cardGrid($board, $c);
        };
        // Dars butun hisoblanadi: bir guruhning bir fandan shu kundagi ketma-ket amaliy kartalari (2 + 2, 2 + 1 soat)
        // bitta blok — ko'chirilsa hammasi birga, bir kunda ketma-ket ko'chadi.
        $effIn = function (TimetableCard $x, int $w, array $st): ?array {
            $ov = $st['ov'][(int) $x->id][$w] ?? null;
            if ($ov) {
                return $ov->cancelled || !$ov->day || !$ov->pair ? null : [(int) $ov->day, (int) $ov->pair];
            }

            return $x->day && $x->pair ? [(int) $x->day, (int) $x->pair] : null;
        };
        // Bir kundagi [karta, boshlanish] ro'yxatidan ketma-ket zanjirlar (bir kunda shu fanning ikki alohida darsi bo'lishi mumkin).
        $chainsOf = function (array $list): array {
            usort($list, fn ($a, $b) => $a[1] <=> $b[1]);
            $chains = [];
            foreach ($list as [$y, $p]) {
                $last = $chains ? $chains[count($chains) - 1] : null;
                if ($last && $p <= $last['end']) {
                    $chains[count($chains) - 1]['cards'][] = $y;
                    $chains[count($chains) - 1]['end'] = max($last['end'], $p + $y->lenHalf());
                } else {
                    $chains[] = ['cards' => [$y], 'start' => $p, 'end' => $p + $y->lenHalf()];
                }
            }

            return $chains;
        };
        $blockOf = function (TimetableCard $x, int $w, array $st) use ($effIn, $bySib, $sibKey, $isBolak, $chainsOf): array {
            $pl = $effIn($x, $w, $st);
            if (!$pl || $x->training_type === 'lecture' || $isBolak($x)) {
                return [$x];
            }
            $list = [];
            foreach ($bySib[$sibKey($x)] ?? [] as $y) {
                $py = $effIn($y, $w, $st);
                if ($py && $py[0] === $pl[0]) {
                    $list[] = [$y, $py[1]];
                }
            }
            // Faqat x turgan ketma-ket zanjir.
            foreach ($chainsOf($list) as $ch) {
                foreach ($ch['cards'] as $y) {
                    if ((int) $y->id === (int) $x->id) {
                        return $ch['cards'];
                    }
                }
            }

            return [$x];
        };
        // Blokni (d, s) dan boshlab ketma-ket qo'yib ko'radi: avval blok kartalari o'z joyidan "ko'tariladi".
        $placeBlock = function (array $cards, int $w, int $d, int $s, array $st, int $maxPair) use ($withMove, $check): ?array {
            $len = array_sum(array_map(fn ($x) => $x->lenHalf(), $cards));
            if ($s < 1 || $s + $len - 1 > $maxPair) {
                return null;
            }
            // Haftalik xona ko'tarishda yo'qolmasin: blok tuzish uchun avval "reset" qilinsa ham, rejada xona hisobga olinadi.
            $keep = [];
            foreach ($cards as $x) {
                $prev = $st['ov'][(int) $x->id][$w] ?? null;
                $keep[(int) $x->id] = [$prev?->auditorium_code, $prev?->auditorium_name];
                // "reset" kabi: shu haftaga yozuvi o'chadi va karta shablondagi (barcha haftalar) joyiga qaytadi (bo'lsa) —
                // ketma-ket ko'chirishda keyingi kartalar o'sha joyda turgan holda tekshiriladi.
                $this->stateSetOverride($st, null, (int) $x->id, $w);
            }
            $p = $s;
            foreach ($cards as $x) {
                $st = $withMove($st, $x, $w, $d, $p, false, $keep[(int) $x->id][0] ? $keep[(int) $x->id] : null);
                if ($check($x, $w, $d, $p, $st)) {
                    return null;
                }
                $p += $x->lenHalf();
            }

            return $st;
        };
        // Blok uchun joy: avval $prefDay (yaqin vaqtdan), keyin (ruxsat bo'lsa) boshqa kunlar. [kun, boshlanish, holat]
        // Boshqa kunga ko'chirilayotgan dars: o'sha kunda shu guruhning shu fandan shu turdagi darsi bormi (blokning
        // o'zidan tashqari). Bor bo'lsa — u kunga taklif qilinmaydi (kunlik darslar soni buzilmasin).
        $dayIdx = [];
        $lessonKey = fn (TimetableCard $x) => $this->groupScopeKey($x) . '|' . $x->training_type . '|' . mb_strtolower($baseSubj($x->subject_name));
        $sameLessonOnDay = function (array $cards, int $w, int $d) use (&$dayIdx, &$state, $effIn, $lessonKey): bool {
            if (!isset($dayIdx[$w])) {
                $dayIdx[$w] = [];
                foreach ($state['cards'] as $y) {
                    if ($py = $effIn($y, $w, $state)) {
                        $dayIdx[$w][$py[0]][$lessonKey($y)][] = $y;
                    }
                }
            }
            $ids = array_map(fn ($x) => (int) $x->id, $cards);
            $groups = array_merge(...array_map(fn ($x) => $x->occupiedGroups(), $cards));
            foreach ($dayIdx[$w][$d][$lessonKey($cards[0])] ?? [] as $y) {
                if (!in_array((int) $y->id, $ids, true) && array_intersect($y->occupiedGroups(), $groups)) {
                    return true;
                }
            }

            return false;
        };
        // $notDay — shu kun o'tkazib yuboriladi ("to'siqni boshqa kunga": faqat boshqa kunlar).
        $searchBlock = function (array $cards, int $w, array $st, int $prefDay, int $prefPair, int $maxDay, int $maxPair, bool $otherDays, ?array $skip = null,
            ?int $notDay = null) use ($placeBlock, $sameLessonOnDay): ?array {
            $len = array_sum(array_map(fn ($x) => $x->lenHalf(), $cards));
            $days = range(1, $maxDay);
            usort($days, fn ($a, $b) => ($a !== $prefDay) <=> ($b !== $prefDay) ?: $a <=> $b);
            foreach ($days as $d) {
                if ((!$otherDays && $d !== $prefDay) || $d === $notDay) {
                    continue;
                }
                if ($d !== $prefDay && $sameLessonOnDay($cards, $w, $d)) {
                    continue;
                }
                $ps = range(1, max(1, $maxPair - $len + 1));
                usort($ps, fn ($a, $b) => abs($a - $prefPair) <=> abs($b - $prefPair) ?: $a <=> $b);
                foreach ($ps as $s) {
                    // $skip — bitta [kun, boshlanish] yoki ularning ro'yxati (topilgan variantlar takrorlanmasin).
                    if ($skip && ($skip === [$d, $s] || in_array([$d, $s], $skip, true))) {
                        continue;
                    }
                    if ($st2 = $placeBlock($cards, $w, $d, $s, $st, $maxPair)) {
                        return [$d, $s, $st2];
                    }
                }
            }

            return null;
        };
        $blockActs = function (array $cards, int $w, int $d, int $s) use (&$state): array {
            // Blok bir necha karta bo'lsa — avval eski joylari bo'shatiladi, keyin ketma-ket qo'yiladi
            // (aks holda karta o'z blokining eski joyi bilan to'qnashadi).
            $acts = [];
            if (count($cards) > 1) {
                foreach ($cards as $x) {
                    $acts[] = ['op' => 'reset', 'card_id' => (int) $x->id, 'week' => $w];
                }
            }
            $p = $s;
            foreach ($cards as $x) {
                // "reset" shu haftaga berilgan xonani ham o'chiradi — ko'chirishda qayta beriladi.
                $room = count($cards) > 1 ? ($state['ov'][(int) $x->id][$w] ?? null)?->auditorium_code : null;
                $acts[] = ['op' => 'move', 'card_id' => (int) $x->id, 'week' => $w, 'day' => $d, 'pair' => $p] + ($room ? ['auditorium_code' => $room] : []);
                $p += $x->lenHalf();
            }

            return $acts;
        };
        $blockText = function (array $cards) use ($slotText): string {
            $x = $cards[0];
            $h = array_sum(array_map(fn ($y) => $y->lenHalf(), $cards));

            return '«' . $x->subject_name . '» (' . implode(', ', $x->occupiedGroups()) . ($x->teacher_name ? ', ' . $x->teacher_name : '')
                . ($h > 1 ? ', ' . $h . ' soat' : '') . ')';
        };
        $teachersOf = [];
        // 6-qadam: o'qituvchining barcha haftalardagi darslari (sana => [[boshi, oxiri, karta]]) — o'qituvchi bo'yicha bir marta.
        $byTeacher = null;
        $teacherOcc = [];
        $weekDates = [];
        $dateOf = function (TimetableCard $x, int $w, int $d) use (&$weekDates, $board): ?string {
            $k = $x->faculty_name . '|' . $x->specialty_name . '|' . (int) $x->course . '|' . $this->cardCurriculumId($x) . '|' . $w;
            $weekDates[$k] ??= (function () use ($board, $x, $w) {
                $monday = $this->weekStartDate($board, $x, $w);

                return array_map(fn ($i) => $monday->copy()->addDays($i)->toDateString(), range(0, 6));
            })();

            return $weekDates[$k][$d - 1] ?? null;
        };
        $occOf = function (int $tid) use (&$byTeacher, &$teacherOcc, &$state, $effIn, $gridOf, $dateOf): array {
            if (!isset($teacherOcc[$tid])) {
                if ($byTeacher === null) {
                    $byTeacher = [];
                    foreach ($state['cards'] as $o) {
                        if ($o->teacher_id) {
                            $byTeacher[(int) $o->teacher_id][] = $o;
                        }
                    }
                }
                $occ = [];
                foreach ($byTeacher[$tid] ?? [] as $o) {
                    $g = $gridOf($o);
                    for ($w = 1; $w <= (int) $g['weeks']; $w++) {
                        // findWeekConflicts kabi: kalendar kunlaridan tashqaridagi kun hisobga olinmaydi.
                        if (($pl = $effIn($o, $w, $state)) && $pl[0] <= (int) $g['days'] && ($date = $dateOf($o, $w, $pl[0]))) {
                            $occ[$date][] = [$pl[1] - 1, $pl[1] - 1 + $o->lenHalf(), (int) $o->id];
                        }
                    }
                }
                $teacherOcc[$tid] = $occ;
            }

            return $teacherOcc[$tid];
        };
        // Bo'shliq ("oyna"): ta'sirlangan guruhlar (karta guruhi, surilgan to'siq dars guruhi) kunida birinchi va oxirgi dars
        // orasidagi bo'sh yarim-slotlar. Katta tanaffus (11:20–12:00) qator emas — bo'shliq hisoblanmaydi. Guruh kun indeksi
        // (shu hafta): "qamrov|guruh" => kun => [karta id => [boshi, oxiri)].
        $gIdx = null;
        $gKeys = fn (TimetableCard $x) => array_map(fn ($g) => $this->groupScopeKey($x) . '|' . mb_strtolower(trim((string) $g)), $x->occupiedGroups());
        $gapsOf = function (array $ranges): int {
            $rows = [];
            foreach ($ranges as [$a, $b]) {
                for ($r = $a; $r < $b; $r++) {
                    $rows[$r] = true;
                }
            }

            return $rows ? max(array_keys($rows)) - min(array_keys($rows)) + 1 - count($rows) : 0;
        };
        // Taklif amallaridan keyin: [bo'shliq o'zgarishi (yarim-slot), karta guruhning mavjud darsiga yopishganmi].
        $gapEffect = function (array $actions, TimetableCard $main) use (&$gIdx, &$state, $W, $effIn, $gKeys, $gapsOf): array {
            if ($gIdx === null) {
                $gIdx = [];
                foreach ($state['cards'] as $x) {
                    if ($pl = $effIn($x, $W, $state)) {
                        foreach ($gKeys($x) as $gk) {
                            $gIdx[$gk][$pl[0]][(int) $x->id] = [$pl[1], $pl[1] + $x->lenHalf()];
                        }
                    }
                }
            }
            $to = [];
            foreach ($actions as $a) {
                if ((int) ($a['week'] ?? $W) !== $W || !in_array($a['op'] ?? '', ['move', 'reset', 'cancel'], true)) {
                    continue;
                }
                $x = $state['cards'][(int) $a['card_id']] ?? null;
                if (!$x) {
                    continue;
                }
                $to[(int) $x->id] = match ($a['op']) {
                    'move' => [(int) $a['day'], (int) $a['pair']],
                    'reset' => $x->day && $x->pair ? [(int) $x->day, (int) $x->pair] : null,
                    default => null,
                };
            }
            $touched = [];
            foreach ($to as $id => $pl) {
                $x = $state['cards'][$id];
                $old = $effIn($x, $W, $state);
                foreach ($gKeys($x) as $gk) {
                    if ($old) {
                        $touched[$gk][$old[0]] = true;
                    }
                    if ($pl) {
                        $touched[$gk][$pl[0]] = true;
                    }
                }
            }
            $delta = 0;
            $touch = false;
            foreach ($touched as $gk => $days) {
                foreach (array_keys($days) as $d) {
                    $before = $gIdx[$gk][$d] ?? [];
                    $rest = array_diff_key($before, $to);
                    $after = $rest;
                    foreach ($to as $id => $pl) {
                        if ($pl && $pl[0] === $d && in_array($gk, $gKeys($state['cards'][$id]), true)) {
                            $after[$id] = [$pl[1], $pl[1] + $state['cards'][$id]->lenHalf()];
                        }
                    }
                    $delta += $gapsOf($after) - $gapsOf($before);
                    $mainTo = $to[(int) $main->id] ?? null;
                    if ($mainTo && $mainTo[0] === $d && in_array($gk, $gKeys($main), true)) {
                        $m = [$mainTo[1], $mainTo[1] + $main->lenHalf()];
                        foreach ($rest as [$a, $b]) {
                            if ($b === $m[0] || $a === $m[1]) {
                                $touch = true;
                            }
                        }
                    }
                }
            }

            return [$delta, $touch];
        };
        // Takliflar tartibi: tur bo'yicha (1. ikkalasini ketma-ket, 2. shu kun boshqa vaqt, 3. boshqa xona, 4. to'siqni shu kunda
        // surish, 5. boshqa kun, … oxirida to'siqni boshqa kunga); tur ichida — bo'shliq qo'shmaydigan yoki kamaytiradigan, keyin
        // guruh darsiga yopishgan, keyin qo'shiladigan bo'shliq kamroq. Har taklifga 'gap' (yarim-slot) belgisi.
        // Parallel to'plam: shu kartaning bo'lak qo'shnilari (bolakSiblings — tur, qamrov, oqim, talaba kesishuvi), joyi bilan.
        $bolakLetter = fn (TimetableCard $x) => preg_match('/\((\p{L})\)\s*$/u', (string) $x->subject_name, $m) ? mb_strtolower($m[1]) : '?';
        $parSets = [];
        $parallelSet = function (TimetableCard $c) use (&$parSets, &$state, $baseSubj, $cancelled, $W): array {
            $k = (int) $c->id;
            if (!isset($parSets[$k])) {
                $parSets[$k] = [];
                foreach ($state['cards'] as $x) {
                    // Shu haftada ataylab bekor qilingan a'zo — to'plamga (va "birga joylash" amallariga) kirmaydi.
                    if ((int) $x->id !== $k && !$cancelled($x, $W) && $x->training_type === $c->training_type && $this->groupScopeKey($x) === $this->groupScopeKey($c)
                        && mb_strtolower($baseSubj($x->subject_name)) === mb_strtolower($baseSubj($c->subject_name)) && $this->bolakSiblings($c, $x)) {
                        $parSets[$k][] = $x;
                    }
                }
            }

            return $parSets[$k];
        };
        // To'plamda eng ko'p uchragan tashqi joy [kun, para] | null.
        $parallelCommonExt = function (array $list): ?array {
            $cnt = [];
            foreach ($list as $x) {
                if ($x->ext_day && $x->ext_pair) {
                    $cnt[(int) $x->ext_day . '|' . (int) $x->ext_pair] = ($cnt[(int) $x->ext_day . '|' . (int) $x->ext_pair] ?? 0) + 1;
                }
            }
            if (!$cnt) {
                return null;
            }
            arsort($cnt);

            return array_map('intval', explode('|', (string) array_key_first($cnt)));
        };
        $tierOf = ['parallel' => -1, 'pair' => 0, 'slot' => 1, 'room' => 2, 'blocker' => 3, 'day' => 4, 'week' => 5, 'lecture' => 6, 'teacher' => 7, 'blocker_day' => 8];
        $rankSugs = function (array $sugs) use (&$c, $gapEffect, $tierOf): array {
            foreach ($sugs as $i => &$sg) {
                [$sg['gap'], $sg['touch']] = $gapEffect($sg['actions'], $c);
                $sg['_i'] = $i;
            }
            unset($sg);
            usort($sugs, fn ($a, $b) => [$tierOf[$a['type']] ?? 9, $a['gap'] > 0 ? ($a['touch'] ? 1 : 2) : 0, $a['gap'], $a['_i']]
                <=> [$tierOf[$b['type']] ?? 9, $b['gap'] > 0 ? ($b['touch'] ? 1 : 2) : 0, $b['gap'], $b['_i']]);

            return array_map(function ($sg) {
                unset($sg['_i']);

                return $sg;
            }, $sugs);
        };
        // Vaqt byudjeti: tugasa — qolgan kartalarga faqat tez qadamlar (bo'sh vaqt, shu kunning boshqa vaqti, xona).
        $deadline = microtime(true) + (float) config('timetable.week_suggestions_budget', 40);
        $partialN = 0;

        $result = [];
        foreach ($unplaced as $c) {
            $full = $onlyCard !== null || microtime(true) < $deadline;
            $grid = $gridOf($c);
            $maxPair = (int) $grid['pairs'];
            $maxDay = (int) $grid['days'];
            $len = $c->lenHalf();
            $lecture = $c->training_type === 'lecture';
            $sugs = [];
            $late = [];   // ma'ruza vaqtini o'zgartiradigan takliflar — oxirroqda (o'qituvchi almashtirishdan oldin)
            $add = function (string $type, string $title, array $actions, string $note = '') use (&$sugs) {
                $sugs[] = compact('type', 'title', 'actions', 'note');
            };
            $addLate = function (string $type, string $title, array $actions, string $note = '') use (&$late) {
                $late[] = compact('type', 'title', 'actions', 'note');
            };
            $lecNote = 'Ma\'ruza vaqti tashqi jadvaldan farq qiladi — ma\'ruza xonasi va boshqa oqimlarni tekshiring';
            $mv = fn (TimetableCard $x, int $w, int $d, int $p, ?string $room = null) =>
                ['op' => 'move', 'card_id' => (int) $x->id, 'week' => $w, 'day' => $d, 'pair' => $p] + ($room ? ['auditorium_code' => $room] : []);

            $isConflict = isset($conflictIds[(int) $c->id]);
            // Maqsad: shu fanning shu haftada joylashgan qismi yonida, aks holda tashqi jadvaldagi joyi; to'qnashuvli karta — hozirgi joyi.
            $targets = [];
            $sibs = [];
            if (!$lecture && !$isBolak($c)) {
                foreach ($bySib[$sibKey($c)] ?? [] as $x) {
                    if ((int) $x->id !== (int) $c->id && ($pl = $eff($x, $W))) {
                        $sibs[] = [$x, $pl];
                    }
                }
            }
            if ($isConflict) {
                $targets = [$eff($c, $W)];
            } elseif ($sibs) {
                $d = $sibs[0][1][0];
                $sameDay = array_filter($sibs, fn ($s) => $s[1][0] === $d);
                $lo = min(array_map(fn ($s) => $s[1][1], $sameDay));
                $hi = max(array_map(fn ($s) => $s[1][1] + $s[0]->lenHalf(), $sameDay));
                $targets = array_values(array_filter([[$d, $hi], [$d, $lo - $len]], fn ($t) => $t[1] >= 1 && $t[1] + $len - 1 <= $maxPair));
            } elseif ($c->ext_day && $c->ext_pair) {
                $targets = [[(int) $c->ext_day, (int) $c->ext_pair]];
            }
            // Parallel bo'laklar ("Fan (a)", "(b)", "(c)" — guruhning turli talabalari, bir vaqtda yonma-yon).
            $par = !$lecture && $isBolak($c) ? $parallelSet($c) : [];
            if ($par) {
                // Tashqi joyi yo'q (yoki to'plamda umumiy emas) — to'plamda eng ko'p uchragan tashqi joy.
                if (!$isConflict && !$sibs && !($c->ext_day && $c->ext_pair) && ($common = $parallelCommonExt(array_merge([$c], $par)))) {
                    $targets = [$common];
                }
                // 0) Bir a'zosi shu haftada joylangan — o'sha vaqtda yonma-yon (eng ko'p uchragan boshlanish).
                $placedPar = array_values(array_filter(array_map(fn ($x) => ($pl = $eff($x, $W)) ? [$x, $pl] : null, $par)));
                if ($placedPar && !$isConflict) {
                    $cnt = [];
                    foreach ($placedPar as [$x, $pl]) {
                        $cnt[$pl[0] . '|' . $pl[1]][] = $x;
                    }
                    uasort($cnt, fn ($a, $b) => count($b) <=> count($a));
                    $k = array_key_first($cnt);
                    [$pd, $pp] = array_map('intval', explode('|', $k));
                    if (!$check($c, $W, $pd, $pp, $state)) {
                        $add('parallel', "O'sha vaqtda yonma-yon: " . $slotText($pd, $pp, $len) . ' (' . implode(', ', array_map($bolakLetter, $cnt[$k])) . ' bilan birga)',
                            [$mv($c, $W, $pd, $pp)], 'Parallel bo\'lak — guruhning boshqa talabalari, guruh to\'qnashuvi emas');
                    }
                } elseif (!$placedPar && !$isConflict && ($targets || $parallelCommonExt(array_merge([$c], $par)))) {
                    // To'plam to'liq joylanmagan — hammasini birga (har biri o'z ustunida, bir xil boshlanish va davomiylik): bitta taklif.
                    // Joy — to'plamda eng ko'p uchragan tashqi joy (umumiy bo'lmasa ham).
                    $all = array_merge([$c], $par);
                    usort($all, fn ($a, $b) => strcmp($bolakLetter($a), $bolakLetter($b)));
                    [$pd, $pp0] = $parallelCommonExt($all) ?? $targets[0];
                    $ps = range(1, max(1, $maxPair - $len + 1));
                    usort($ps, fn ($a, $b) => abs($a - $pp0) <=> abs($b - $pp0) ?: $a <=> $b);
                    foreach ($ps as $pp) {
                        $st = $state;
                        $ok = true;
                        foreach ($all as $x) {
                            $st = $withMove($st, $x, $W, $pd, $pp);
                            if ($check($x, $W, $pd, $pp, $st)) {
                                $ok = false;
                                break;
                            }
                        }
                        if ($ok) {
                            $n = count($all);
                            $add('parallel', ($n === 2 ? 'Ikkalasini' : ($n === 3 ? 'Uchalasini' : 'Hammasini (' . $n . ' ta)')) . ' birga joylash: '
                                . $slotText($pd, $pp, $len) . ' (' . implode(' · ', array_map($bolakLetter, $all)) . ')',
                                array_map(fn ($x) => $mv($x, $W, $pd, $pp), $all), 'Har biri o\'z ustunida, bir xil boshlanish va davomiylik');
                            break;
                        }
                    }
                }
            }
            if (!$targets) {
                $result[] = $this->suggestionCard($c, $W, 'Kartaning tashqi jadvalda joyi yo\'q va shu fanning boshqa qismi ham joylashmagan — qaysi vaqtga qo\'yish noma\'lum.', $rankSugs($sugs));
                continue;
            }
            [$td, $tp] = $targets[0];
            $why0 = [];
            $freeFound = false;
            foreach ($isConflict ? [] : $targets as [$d, $p]) {
                $e = $check($c, $W, $d, $p, $state);
                if (!$e) {
                    $add('slot', 'Bo\'sh: ' . $slotText($d, $p, $len), [$mv($c, $W, $d, $p)]);
                    $freeFound = true;
                    break;
                }
                $why0 = $why0 ?: $e;
            }
            $reason = $why0 ? implode('; ', $why0) : '';
            $clashShow = [];   // to'qnashuvli karta: har to'sib turgan dars — "Ko'rsatish" ma'lumoti (matn bilan birga)
            if ($isConflict) {
                // Sabab: kim bilan, nima band (xona / o'qituvchi / guruh) va qachon. Xona to'qnashuvi — birinchi bo'lib bo'sh xona:
                // shu kartaga, so'ng ikkinchi tomonga (uning o'z kalendaridagi haftasi bilan).
                $clashes = $this->weekClashes($c, $W, $td, $tp, 0, ['cards' => array_intersect_key($state['cards'],
                    $this->clashCandidates($c, $td, ($state['ov'][(int) $c->id][$W] ?? null)?->auditorium_code ?: $c->auditorium_code, $state))] + $state, null);
                $reason = implode('; ', array_unique(array_merge(...array_map(fn ($x) => $this->clashTexts($x, $dayName), $clashes))))
                    ?: ($conflicts[(int) $c->id][$W]['text'] ?? $reason);
                $clashShow = array_map(fn ($x) => $this->focusFromClash($c, $x, $board, $W), $clashes);
                $roomOnly = $clashes && !array_filter($clashes, fn ($x) => (bool) array_filter($x['errors'], fn ($m) => !str_starts_with($m, 'Auditoriya band')));
                if ($roomOnly) {
                    foreach ($this->freeRoomsFor($c, $W, $td, $tp, $state, $check, 3) as [$r, $why]) {
                        $add('room', 'Xonani almashtirish: ' . self::sugRoomText($r, $why) . ' — ' . $slotText($td, $tp, $len),
                            [$mv($c, $W, $td, $tp, $r->code)], 'Faqat shu hafta');
                    }
                    foreach ($clashes as $x) {
                        $o = $x['card'];
                        $inScope = in_array((int) $o->id, $scopeIds, true);
                        foreach ($this->freeRoomsFor($o, (int) $x['week'], $x['day'], $x['pair'], $state, $check, 2) as [$r, $why]) {
                            // Faqat shu ko'chirishdan keyin karta to'qnashuvsiz bo'lsa (shu xonada boshqa dars ham bo'lsa — yo'q).
                            if ($check($c, $W, $td, $tp, $withMove($state, $o, (int) $x['week'], $x['day'], $x['pair'], false, [$r->code, $r->name]))) {
                                continue;
                            }
                            $add('room', 'Ikkinchi tomon — «' . $o->subject_name . '» (' . implode(', ', $o->occupiedGroups()) . ') xonasini almashtirish: '
                                . self::sugRoomText($r, $why) . ' — ' . $slotText($x['day'], $x['pair'], $o->lenHalf()),
                                [$mv($o, (int) $x['week'], $x['day'], $x['pair'], $r->code)],
                                'Faqat shu hafta' . ($inScope ? '' : '; boshqa yo\'nalish: ' . trim($o->specialty_name . ', ' . $o->course . '-kurs')));
                        }
                    }
                }
            }
            // Shu darsning butun bloki (karta ulanadigan, maqsad joyiga tutash ketma-ket qism + shu karta) — ko'chirilsa
            // birga ko'chadi. Shu kundagi shu fanning boshqa, alohida darsi blokka kirmaydi.
            $cb = [];
            $dayParts = [];
            foreach ($sibs as [$x, $pl]) {
                if ($pl[0] === $td) {
                    $dayParts[] = [$x, $pl[1]];
                }
            }
            foreach ($chainsOf($dayParts) as $ch) {
                if ($ch['end'] === $tp || $ch['start'] === $tp + $len) {
                    $cb = $ch['cards'];
                    break;
                }
            }
            $cbStart = $cb ? min(array_map(fn ($x) => $eff($x, $W)[1], $cb)) : null;
            $cb[] = $c;
            $cbLen = array_sum(array_map(fn ($x) => $x->lenHalf(), $cb));

            // 0) Bir-birini to'sgan ikki dars (bitta o'qituvchi yoki bitta xona, shu kunda): ikkalasini shu kunda ketma-ket — birinchi
            // taklif. Har ikkala dars yaxlit (blok bilan); tartib — avval shu karta yoki avval ikkinchisi, asl vaqtiga yaqin.
            if (!$freeFound && !$lecture) {
                $bl = $blockers($c, $W, $td, $tp, $state);
                $mutual = count($bl) === 1 && !array_filter($bl[0][1], fn ($m) => !str_starts_with($m, 'O\'qituvchi band') && !str_starts_with($m, 'Auditoriya band'));
                $ob = $mutual ? $blockOf($bl[0][0], $W, $state) : [];
                $obFrom = $ob ? $eff($ob[0], $W) : null;
                if ($ob && $obFrom && $obFrom[0] === $td && $ob[0]->training_type !== 'lecture'
                    && !array_intersect(array_map(fn ($y) => (int) $y->id, $ob), array_map(fn ($y) => (int) $y->id, $cb))) {
                    $obLen = array_sum(array_map(fn ($y) => $y->lenHalf(), $ob));
                    $pref = min($cbStart ?? $tp, $obFrom[1]);
                    $found = null;
                    $ps = range(1, max(1, $maxPair - $cbLen - $obLen + 1));
                    usort($ps, fn ($a, $b) => abs($a - $pref) <=> abs($b - $pref) ?: $a <=> $b);
                    foreach ($ps as $s0) {
                        foreach ([array_merge($cb, $ob), array_merge($ob, $cb)] as $both) {
                            if ($placeBlock($both, $W, $td, $s0, $state, $maxPair)) {
                                $found = [$both, $s0];
                                break 2;
                            }
                        }
                    }
                    if ($found) {
                        [$both, $s0] = $found;
                        $firstIsMine = (int) $both[0]->id === (int) $cb[0]->id;
                        [$aList, $bList] = $firstIsMine ? [$cb, $ob] : [$ob, $cb];
                        $aLen = array_sum(array_map(fn ($y) => $y->lenHalf(), $aList));
                        $bLen = array_sum(array_map(fn ($y) => $y->lenHalf(), $bList));
                        $add('pair', 'Ikkalasini shu kunda ketma-ket: ' . $blockText($aList) . ' ' . $slotText($td, $s0, $aLen) . '; '
                            . $blockText($bList) . ' ' . $slotText($td, $s0 + $aLen, $bLen), $blockActs($both, $W, $td, $s0),
                            'Faqat shu hafta; ikkala dars ham yaxlit');
                    }
                }
            }

            // 1) Shu kunning boshqa vaqti — kartaning o'zi, butun dars (joylashgan qismi bilan birga), yarim para qadami bilan. Kunning
            // barcha boshlanishlari sinab ko'riladi; 3 tasi tanlanadi: avval bo'shliq qo'shmaydigan / kamaytiradigan, keyin guruh darsiga
            // yopishgan, keyin qo'shiladigan bo'shliq kamroq, so'ng asl vaqtiga yaqin.
            if (!$freeFound) {
                $pref = $cbStart ?? $tp;
                $cands = [];
                for ($s0 = 1; $s0 + $cbLen - 1 <= $maxPair; $s0++) {
                    if (!$placeBlock($cb, $W, $td, $s0, $state, $maxPair)) {
                        continue;
                    }
                    $acts = $blockActs($cb, $W, $td, $s0);
                    [$g, $t] = $gapEffect($acts, $c);
                    $cands[] = [$s0, $acts, $g, $t];
                }
                usort($cands, fn ($x, $y) => [$x[2] > 0 ? ($x[3] ? 1 : 2) : 0, $x[2], abs($x[0] - $pref), $x[0]]
                    <=> [$y[2] > 0 ? ($y[3] ? 1 : 2) : 0, $y[2], abs($y[0] - $pref), $y[0]]);
                foreach (array_slice($cands, 0, 3) as [$s0, $acts]) {
                    if ($lecture) {
                        $addLate('lecture', 'Ma\'ruzani shu kunning boshqa vaqtiga: ' . $slotText($td, $s0, $len), $acts, $lecNote);
                    } else {
                        $add('slot', (count($cb) > 1 ? 'Butun darsni (' . $cbLen . ' soat) shu kunning boshqa vaqtiga: ' : 'Shu kun, boshqa vaqt: ')
                            . $slotText($td, $s0, $cbLen), $acts);
                    }
                }
            }

            // 2) Xona band — shu vaqtda bo'sh boshqa xona (faqat shu hafta uchun). To'qnashuvli karta uchun — yuqorida, birinchi.
            foreach ($isConflict ? [] : $targets as [$d, $p]) {
                $bl = $blockers($c, $W, $d, $p, $state);
                if (!$bl) {
                    continue;
                }
                $onlyRoom = !array_filter($bl, fn ($b) => (bool) array_filter($b[1], fn ($m) => !str_starts_with($m, 'Auditoriya band')));
                if (!$onlyRoom) {
                    continue;
                }
                // Bo'sh xonalar — alifbo emas, yaqinlik bo'yicha (rankRooms): avval odatdagi / o'qituvchi / kafedra / shu bino xonasi.
                $roomBase = $this->roomRankBase();
                $need = $this->cardStudents($c);
                $fit = $roomBase['active']->filter(fn ($r) => $r->code !== $c->auditorium_code
                    && !($r->volume && $need && (int) $r->volume < $need) && $this->auditoriumAllowedForCard($r, $c, $roomBase['teacher']));
                $n = 0;
                foreach ($this->rankRooms($c, $fit, $c->auditorium_code, $this->roomHabitFromState((int) $c->board_id, $state)) as [$r, $why]) {
                    $tmp = clone $c;
                    $tmp->auditorium_code = $r->code;
                    $tmp->auditorium_name = $r->name;
                    if ($check($tmp, $W, $d, $p, $state)) {
                        continue;
                    }
                    $add('room', 'Xonani almashtirish: ' . self::sugRoomText($r, $why) . ' — ' . $slotText($d, $p, $len),
                        [$mv($c, $W, $d, $p, $r->code)], 'Faqat shu hafta uchun');
                    if (++$n >= 3) {
                        break;
                    }
                }
                break;
            }

            if (!$full) {
                // Byudjet tugadi: og'ir qadamlar (3–6) hisoblanmaydi — kartani bosib, faqat shu karta uchun to'liq olish mumkin.
                foreach ($late as $x) {
                    $sugs[] = $x;
                }
                $partialN++;
                $result[] = $this->suggestionCard($c, $W, $reason, $rankSugs($sugs), true, $isConflict, $clashShow);
                continue;
            }

            // 3) To'sib turgan darsni surish — to'sib turgan dars ham butun blok bo'lib ko'chadi. Avval shu kunning boshqa vaqtiga
            // ("Darsni surish" — kun o'zgarmaydi), keyin boshqa kunga ("To'siqni boshqa kunga" — takliflar oxirida).
            $blockerPlan = function (int $d, int $p, bool $sameDay) use ($c, $W, &$state, $blockers, $withMove, $blockOf, $eff, $gridOf, $searchBlock, $check): ?array {
                $bl = $blockers($c, $W, $d, $p, $state);
                if (!$bl || count($bl) > 2) {
                    return null;
                }
                $st = $withMove($state, $c, $W, $d, $p);
                $plan = [];
                $movesLecture = false;
                $seen = [];
                foreach ($bl as [$b]) {
                    if (isset($seen[(int) $b->id])) {
                        continue;
                    }
                    $bb = $blockOf($b, $W, $state);
                    foreach ($bb as $y) {
                        $seen[(int) $y->id] = true;
                    }
                    if (in_array((int) $c->id, array_map(fn ($y) => (int) $y->id, $bb), true)) {
                        return null;
                    }
                    $movesLecture = $movesLecture || $b->training_type === 'lecture';
                    $from = $eff($bb[0], $W);
                    $bgrid = $gridOf($bb[0]);
                    $f = $sameDay
                        ? $searchBlock($bb, $W, $st, $from[0], $from[1], (int) $bgrid['days'], (int) $bgrid['pairs'], false, $from)
                        : $searchBlock($bb, $W, $st, $from[0], $from[1], (int) $bgrid['days'], (int) $bgrid['pairs'], true, null, $from[0]);
                    if (!$f) {
                        return null;
                    }
                    $st = $f[2];
                    $plan[] = [$bb, $from, [$f[0], $f[1]]];
                }

                return $plan && !$check($c, $W, $d, $p, $st) ? [$plan, $movesLecture] : null;
            };
            foreach ([true, false] as $sameDay) {
                $nb = 0;
                foreach ($targets as [$d, $p]) {
                    if (!($bp = $blockerPlan($d, $p, $sameDay))) {
                        continue;
                    }
                    [$plan, $movesLecture] = $bp;
                    $txt = [];
                    $acts = [];
                    foreach ($plan as [$bb, $from, $to]) {
                        $h = array_sum(array_map(fn ($y) => $y->lenHalf(), $bb));
                        $txt[] = $blockText($bb) . ' ' . $slotText($from[0], $from[1], $h) . ' → ' . $slotText($to[0], $to[1], $h);
                        array_push($acts, ...$blockActs($bb, $W, $to[0], $to[1]));
                    }
                    $acts[] = $mv($c, $W, $d, $p);
                    if ($movesLecture) {
                        $addLate('lecture', 'To\'sib turgan ma\'ruzani surish: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                            'Faqat shu hafta. ' . $lecNote);
                    } elseif ($sameDay) {
                        $add('blocker', 'To\'siqni shu kunda surish: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                            'Faqat shu hafta; ikkala dars ham yaxlit');
                    } else {
                        $addLate('blocker_day', 'To\'siqni boshqa kunga: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                            'Faqat shu hafta; dars butun ko\'chadi');
                    }
                    if (++$nb >= 2) {
                        break;
                    }
                }
            }

            // 4) Shu haftaning boshqa kuni — butun dars birga (ma'ruza uchun — oxirroqda, ogohlantirish bilan).
            {
                $n = 0;
                for ($d = 1; $d <= $maxDay && $n < 2; $d++) {
                    if ($d === $td) {
                        continue;
                    }
                    if ($sameLessonOnDay($cb, $W, $d)) {
                        continue;
                    }
                    $f = $searchBlock($cb, $W, $state, $d, $cbStart ?? $tp, $maxDay, $maxPair, false, null);
                    if (!$f) {
                        continue;
                    }
                    if ($lecture) {
                        $addLate('lecture', 'Ma\'ruzani boshqa kunga: ' . $slotText($f[0], $f[1], $len), $blockActs($cb, $W, $f[0], $f[1]), $lecNote);
                    } else {
                        $add('day', (count($cb) > 1 ? 'Butun darsni (' . $cbLen . ' soat) boshqa kunga: ' : 'Boshqa kun: ') . $slotText($f[0], $f[1], $cbLen),
                            $blockActs($cb, $W, $f[0], $f[1]));
                    }
                    $n++;
                }

                // 5) Boshqa hafta: karta o'tilmaydigan haftaga, tashqi (yoki shu) vaqtida.
                // Darsning boshqa qismi shu haftada joylashgan bo'lsa — faqat bir qismini boshqa haftaga o'tkazib bo'lmaydi.
                $n = $lecture || count($cb) > 1 ? 2 : 0;
                $weeks = range(1, (int) $grid['weeks']);
                usort($weeks, fn ($a, $b) => abs($a - $W) <=> abs($b - $W) ?: $a <=> $b);
                foreach ($weeks as $w2) {
                    if ($w2 === $W || !$cancelled($c, $w2)) {
                        continue;
                    }
                    if ($check($c, $w2, $td, $tp, $state)) {
                        continue;
                    }
                    // Avval yangi haftaga ko'chiriladi (server qayta tekshirib rad etishi mumkin), keyin shu hafta bekor
                    // qilinadi: ko'chirish rad etilsa, dars ikkala haftadan ham yo'qolib qolmaydi.
                    $add('week', $w2 . '-haftaga o\'tkazish: ' . $slotText($td, $tp, $len),
                        [$mv($c, $w2, $td, $tp), ['op' => 'cancel', 'card_id' => (int) $c->id, 'week' => $W]],
                        'KTR taqsimoti o\'zgaradi: ' . $W . '-haftada o\'tilmaydi, ' . $w2 . '-haftada o\'tiladi');
                    if (++$n >= 2) {
                        break;
                    }
                }
            }

            foreach ($late as $x) {
                $sugs[] = $x;
            }
            // 6) Eng oxirida — shu kafedraning shu vaqtda bo'sh o'qituvchisi (butun karta uchun).
            if ($c->kafedra_name && count($cb) === 1) {
                $teachersOf[$c->kafedra_name] ??= $this->kafedraTeachers((string) $c->kafedra_name);
                $placedWeeks = [];
                for ($w2 = 1; $w2 <= (int) $grid['weeks']; $w2++) {
                    if ($w2 !== $W && ($pl = $eff($c, $w2))) {
                        $placedWeeks[$w2] = $pl;
                    }
                }
                $n = 0;
                foreach ($teachersOf[$c->kafedra_name] as $t) {
                    if ((int) $t->id === (int) $c->teacher_id) {
                        continue;
                    }
                    $tmp = clone $c;
                    $tmp->teacher_id = $t->id;
                    $tmp->teacher_name = $t->short_name ?: $t->full_name;
                    if ($check($tmp, $W, $td, $tp, $state)) {
                        continue;
                    }
                    // O'qituvchi butun semestr uchun almashadi — karta joylashgan boshqa haftalarda ham bo'sh bo'lishi kerak:
                    // shu o'qituvchining barcha darslari (indeks) ichidan karta vaqti bilan kesishgani bitta o'tishda qidiriladi.
                    $busyElsewhere = false;
                    $occ = $this->weekSuggestionsLegacy ? null : $occOf((int) $t->id);
                    foreach ($placedWeeks as $w2 => [$d2, $p2]) {
                        $busy = $occ === null
                            ? (bool) array_filter($check($tmp, $w2, $d2, $p2, $state), fn ($m) => str_starts_with($m, 'O\'qituvchi band'))
                            : (bool) array_filter($occ[$dateOf($c, $w2, $d2)] ?? [], fn ($x) => $x[2] !== (int) $c->id && $x[0] < $p2 - 1 + $len && $p2 - 1 < $x[1]);
                        if ($busy) {
                            $busyElsewhere = true;
                            break;
                        }
                    }
                    if ($busyElsewhere) {
                        continue;
                    }
                    $add('teacher', 'O\'qituvchini almashtirish: ' . ($t->short_name ?: $t->full_name) . ' — ' . $slotText($td, $tp, $len),
                        [['op' => 'teacher', 'card_id' => (int) $c->id, 'teacher_id' => (int) $t->id], $mv($c, $W, $td, $tp)],
                        'O\'qituvchi butun semestr uchun almashadi');
                    if (++$n >= 3) {
                        break;
                    }
                }
            }

            $result[] = $this->suggestionCard($c, $W, $reason, $rankSugs($sugs), false, $isConflict, $clashShow);
        }

        // Har taklif: shu foydalanuvchi qo'llay oladimi (qamrov, ma'ruza / o'qituvchi almashtirish, sana, kutilayotgan so'rov) va qanday
        // (direct — darhol; registrar — so'rov registratorga; department — kafedra mudiri → registrator).
        $pendingCards = $rights['level'] && $rights['level'] !== 'admin' ? $this->pendingCardRequests($board->id) : [];
        // Xona sig'imi (1.4, haftalik karta va biriktirish oynasi bilan bir xil matn) — karta xonasi bilan qo'yiladi, shuning uchun
        // joylanmagan karta uchun ham; ogohlantirish (takliflar o'zgarmaydi).
        $caps = $result ? $this->weeklyCapacityWarnings($board, $W, array_flip(array_column($result, 'id')), false) : [];
        $cardOf = fn (int $id) => $state['cards'][$id] ?? null;
        $ovOf = fn (int $id, int $w) => $state['ov'][$id][$w] ?? null;
        foreach ($result as &$rc) {
            $rc['cap_warn'] = $caps[$rc['id']]['line'] ?? null;
            foreach ($rc['suggestions'] as &$sg) {
                $chk = $this->suggestionApplyCheck($rights, $board, ['week' => $W, 'card_id' => $rc['id'],
                    'actions' => array_map(fn ($a) => $a + ['week' => $W], $sg['actions'])], $cardOf, $ovOf, true, $pendingCards);
                $sg['can_apply'] = $chk['mode'] !== null;
                $sg['mode'] = $chk['mode'];
                $sg['why'] = $chk['why'];
            }
            unset($sg);
        }
        unset($rc);

        return response()->json(['week' => $W, 'total' => $total, 'unplaced_total' => $unplacedTotal, 'conflict_total' => $conflictTotal,
            'shown' => count($result), 'cards' => $result,
            'apply_mode' => $rights['level'] === 'admin' ? 'direct' : ($rights['level'] ? 'request' : null),
            'partial' => $partialN > 0, 'message' => $partialN ? $partialN . ' ta karta uchun to\'liq takliflar hisoblanmadi' : null]);
    }

    private function suggestionCard(TimetableCard $c, int $week, string $reason, array $sugs, bool $partial = false, bool $conflict = false,
        array $clashes = []): array
    {
        return [
            // true — vaqt byudjeti tugab, faqat tez qadamlar (1–2) hisoblangan; ?card_id= bilan to'liq olinadi.
            'partial' => $partial,
            // true — joyi bor, lekin shu haftada to'qnashuvli (reason — kim bilan).
            'conflict' => $conflict,
            'id' => (int) $c->id,
            'subject' => $c->subject_name,
            'type' => $c->training_type,
            'groups' => $c->occupiedGroups(),
            'teacher' => $c->teacher_name,
            'room' => $c->auditorium_name,
            'faculty' => $c->faculty_name,
            'course' => (int) $c->course,
            'week' => $week,
            'reason' => $reason,
            // To'qnashuvli karta: to'sib turgan darslar — tuzilgan ("Ko'rsatish": guruhlar, hafta, kun/para, kartalar).
            'clashes' => $clashes,
            'suggestions' => $sugs,
        ];
    }

    /**
     * Shu vaqtda (hafta, kun, para) karta uchun bo'sh boshqa xonalar — faol, sig'imi yetadi, kartaning o'qituvchisiga ruxsat
     * (auditoriumAllowedForCard); tekshiruv shu haftaga xos xona bilan (week-override auditorium_code). Ko'pi bilan $max ta —
     * alifbo emas, yaqinlik bo'yicha (rankRooms: odat, o'qituvchi, kafedra, bino/qavat, sig'im): [[xona, sabab|null], ...].
     * Avval arzon filtr va saralash, so'ng bandlik tekshiruvi shu tartibda — $max topilguncha.
     */
    private function freeRoomsFor(TimetableCard $c, int $w, int $d, int $p, array $state, \Closure $check, int $max): array
    {
        $base = $this->roomRankBase();
        $cur = ($state['ov'][(int) $c->id][$w] ?? null)?->auditorium_code ?: $c->auditorium_code;
        $need = $this->cardStudents($c);   // sig'im — ogohlantirish (weeklyCapacityWarnings) bilan bir xil talabalar soni
        $fit = $base['active']->filter(fn ($r) => $r->code !== $cur && !($r->volume && $need && (int) $r->volume < $need)
            && $this->auditoriumAllowedForCard($r, $c, $base['teacher']));
        $out = [];
        foreach ($this->rankRooms($c, $fit, $cur, $this->roomHabitFromState((int) $c->board_id, $state)) as [$r, $why]) {
            if ($check($c, $w, $d, $p, $state, $r->code)) {
                continue;
            }
            $out[] = [$r, $why];
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /** Taklif matnidagi xona: "216 (30 o'rin) (kafedra xonasi)". */
    private static function sugRoomText(Auditorium $r, ?string $why): string
    {
        return $r->name . ($r->volume ? ' (' . $r->volume . ' o\'rin)' : '') . ($why ? ' (' . $why . ')' : '');
    }

    /** weekClashes yozuvi — "Xona 215 band: p/p25-02b · Odam anatomiyasi · Juma V para" (o'qituvchi / guruh ham). */
    private function clashTexts(array $x, \Closure $dayName): array
    {
        $o = $x['card'];
        $roman = [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        $para = intdiv((int) $x['pair'] + 1, 2);
        $tail = implode(', ', $o->occupiedGroups()) . ' · ' . $o->subject_name . ' · ' . $dayName((int) $x['day']) . ' ' . ($roman[$para] ?? $para) . ' para';
        $out = [];
        foreach ($x['errors'] as $m) {
            $out[] = match (true) {
                str_starts_with($m, 'Auditoriya band') => 'Xona ' . ($x['room_name'] ?: $x['room']) . ' band: ' . $tail,
                str_starts_with($m, 'O\'qituvchi band') => 'O\'qituvchi ' . ($o->teacher_name ?: '—') . ' band: ' . $tail,
                default => 'Guruh ' . (preg_match('/^Guruh band: (.*?) — /u', $m, $g) ? $g[1] : '') . ' band: ' . $tail,
            };
        }

        return $out;
    }

    /** Kafedra o'qituvchilari (o'qituvchilar ro'yxatidagi kafedra filtri bilan bir xil). */
    private function kafedraTeachers(string $kafedra)
    {
        $q = Teacher::query()->whereNotNull('full_name')->where(function ($w) use ($kafedra) {
            $w->where('department', 'like', '%' . $kafedra . '%');
            if (Schema::hasTable('teacher_employments')) {
                $w->orWhereIn('id', $this->employmentTeacherIdsByDepartmentName($kafedra));
            }
        });
        if (Schema::hasColumn('teachers', 'status')) {
            $q->where('status', true);
        }

        return $q->orderBy('full_name')->limit(80)->get(['id', 'full_name', 'short_name']);
    }
}

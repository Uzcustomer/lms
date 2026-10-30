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
 * "Takliflar": tanlangan haftada joylanmagan har bir karta uchun uni sig'dirish yo'llari, tartib bo'yicha:
 *   1) shu kunning bo'sh vaqti (shu fanning joylashgan qismiga ulab);
 *   2) xona band — shu vaqtda bo'sh boshqa xona (faqat shu hafta);
 *   3) to'sib turgan amaliy darsni (o'qituvchi/guruh/xona) shu haftaning boshqa vaqtiga surish;
 *   4) shu haftaning boshqa kuni;
 *   5) boshqa hafta (karta o'tilmaydigan haftaga, shu hafta bekor qilinadi — KTR taqsimoti o'zgaradi);
 *   6) eng oxirida — shu kafedraning shu vaqtda bo'sh o'qituvchisini biriktirish.
 * Hech narsa yozilmaydi: har bir taklifda bajariladigan amallar (`actions`) qaytariladi, ularni foydalanuvchi
 * "Qo'llash" bilan oddiy endpointlar orqali bajaradi (har birida server qayta tekshiradi).
 * Ma'ruza vaqti tashqi jadvaldagi kun/vaqt (ma'ruza xonalari umumiy): uni siljitadigan takliflar (ma'ruzaning o'zini
 * yoki to'sib turgan ma'ruzani surish) ogohlantirish bilan oxirroqda — o'qituvchi almashtirishdan oldin — beriladi.
 */
trait TimetableWeekSuggestions
{
    /** Tekshiruv uchun: optimizatsiyadan oldingi yo'l (indekssiz findWeekConflicts, keshsiz, 6-qadam har hafta alohida). */
    private bool $weekSuggestionsLegacy = false;

    public function weekSuggestions(Request $request, TimetableBoard $board)
    {
        if (!in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'], true)) {
            abort(403, 'Takliflar faqat jadval tuzuvchilar uchun.');
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
        foreach ($scopeIds as $id) {
            $c = $state['cards'][$id] ?? null;
            if (!$c || $isCycle($c) || $cancelled($c, $W) || $eff($c, $W)) {
                continue;
            }
            $unplaced[] = $c;
        }
        $onlyCard = isset($data['card_id']) ? (int) $data['card_id'] : null;
        if ($onlyCard !== null) {
            $unplaced = array_values(array_filter($unplaced, fn ($c) => (int) $c->id === $onlyCard));
        }
        $limit = (int) ($data['limit'] ?? 150);
        $total = count($unplaced);
        $unplaced = array_slice($unplaced, 0, $limit);

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
            ? fn (TimetableCard $c, int $w, int $d, int $p, array $st) => $this->findWeekConflicts($c, $w, $d, $p, 0, $st)
            : function (TimetableCard $c, int $w, int $d, int $p, array $st) use (&$memo) {
                $k = ($st['__v'] ?? 0) . '|' . $c->id . '|' . $c->teacher_id . '|' . $c->auditorium_code . '|' . $w . '|' . $d . '|' . $p;

                return $memo[$k] ??= $this->findWeekConflictsIndexed($c, $w, $d, $p, 0, $st);
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
        $searchBlock = function (array $cards, int $w, array $st, int $prefDay, int $prefPair, int $maxDay, int $maxPair, bool $otherDays, ?array $skip = null) use ($placeBlock, $sameLessonOnDay): ?array {
            $len = array_sum(array_map(fn ($x) => $x->lenHalf(), $cards));
            $days = range(1, $maxDay);
            usort($days, fn ($a, $b) => ($a !== $prefDay) <=> ($b !== $prefDay) ?: $a <=> $b);
            foreach ($days as $d) {
                if (!$otherDays && $d !== $prefDay) {
                    continue;
                }
                if ($d !== $prefDay && $sameLessonOnDay($cards, $w, $d)) {
                    continue;
                }
                $ps = range(1, max(1, $maxPair - $len + 1));
                usort($ps, fn ($a, $b) => abs($a - $prefPair) <=> abs($b - $prefPair) ?: $a <=> $b);
                foreach ($ps as $s) {
                    if ($skip && $skip === [$d, $s]) {
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
        $rooms = null;
        $roomMap = null;
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

            // Maqsad: shu fanning shu haftada joylashgan qismi yonida, aks holda tashqi jadvaldagi joyi.
            $targets = [];
            $sibs = [];
            if (!$lecture && !$isBolak($c)) {
                foreach ($bySib[$sibKey($c)] ?? [] as $x) {
                    if ((int) $x->id !== (int) $c->id && ($pl = $eff($x, $W))) {
                        $sibs[] = [$x, $pl];
                    }
                }
            }
            if ($sibs) {
                $d = $sibs[0][1][0];
                $sameDay = array_filter($sibs, fn ($s) => $s[1][0] === $d);
                $lo = min(array_map(fn ($s) => $s[1][1], $sameDay));
                $hi = max(array_map(fn ($s) => $s[1][1] + $s[0]->lenHalf(), $sameDay));
                $targets = array_values(array_filter([[$d, $hi], [$d, $lo - $len]], fn ($t) => $t[1] >= 1 && $t[1] + $len - 1 <= $maxPair));
            } elseif ($c->ext_day && $c->ext_pair) {
                $targets = [[(int) $c->ext_day, (int) $c->ext_pair]];
            }
            if (!$targets) {
                $result[] = $this->suggestionCard($c, $W, 'Kartaning tashqi jadvalda joyi yo\'q va shu fanning boshqa qismi ham joylashmagan — qaysi vaqtga qo\'yish noma\'lum.', []);
                continue;
            }
            [$td, $tp] = $targets[0];
            $why0 = [];
            foreach ($targets as [$d, $p]) {
                $e = $check($c, $W, $d, $p, $state);
                if (!$e) {
                    $add('slot', 'Bo\'sh: ' . $slotText($d, $p, $len), [$mv($c, $W, $d, $p)]);
                    break;
                }
                $why0 = $why0 ?: $e;
            }
            $reason = $why0 ? implode('; ', $why0) : '';
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

            // 1) Shu kunning boshqa bo'sh vaqti — butun dars (joylashgan qismi bilan birga) ko'chadi.
            if (!$sugs) {
                $n = 0;
                $skip = null;
                while ($n < 2 && ($f = $searchBlock($cb, $W, $state, $td, $cbStart ?? $tp, $maxDay, $maxPair, false, $skip))) {
                    [$d, $s0] = $f;
                    $title = (count($cb) > 1 ? 'Butun darsni (' . $cbLen . ' soat) shu kunning boshqa vaqtiga: ' : 'Shu kun, boshqa vaqt: ') . $slotText($d, $s0, $cbLen);
                    if ($lecture) {
                        $addLate('lecture', 'Ma\'ruzani shu kunning boshqa vaqtiga: ' . $slotText($d, $s0, $len), $blockActs($cb, $W, $d, $s0), $lecNote);
                    } else {
                        $add('slot', $title, $blockActs($cb, $W, $d, $s0));
                    }
                    $n++;
                    // keyingi variant — boshqa boshlanish (blok qidiruvda o'z joyidan ko'tariladi, shuning uchun
                    // topilgan joyni holatda band qilib bo'lmaydi — u o'tkazib yuboriladi)
                    $skip = [$d, $s0];
                }
            }

            // 2) Xona band — shu vaqtda bo'sh boshqa xona (faqat shu hafta uchun).
            foreach ($targets as [$d, $p]) {
                $bl = $blockers($c, $W, $d, $p, $state);
                if (!$bl) {
                    continue;
                }
                $onlyRoom = !array_filter($bl, fn ($b) => (bool) array_filter($b[1], fn ($m) => !str_starts_with($m, 'Auditoriya band')));
                if (!$onlyRoom) {
                    continue;
                }
                $rooms ??= Schema::hasTable('auditoriums') ? Auditorium::where('active', true)->orderBy('name')->get() : collect();
                $roomMap ??= $this->auditoriumTeacherMap();
                $n = 0;
                foreach ($rooms as $r) {
                    if ($r->code === $c->auditorium_code || ($r->volume && $c->students && (int) $r->volume < (int) $c->students)
                        || !$this->auditoriumAllowedForCard($r, $c, $roomMap)) {
                        continue;
                    }
                    $tmp = clone $c;
                    $tmp->auditorium_code = $r->code;
                    $tmp->auditorium_name = $r->name;
                    if ($check($tmp, $W, $d, $p, $state)) {
                        continue;
                    }
                    $add('room', 'Xonani almashtirish: ' . $r->name . ($r->volume ? ' (' . $r->volume . ' o\'rin)' : '') . ' — ' . $slotText($d, $p, $len),
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
                $result[] = $this->suggestionCard($c, $W, $reason, $sugs, true);
                continue;
            }

            // 3) To'sib turgan darsni shu haftaning boshqa vaqtiga surish — to'sib turgan dars ham butun blok bo'lib ko'chadi.
            $nb = 0;
            foreach ($targets as [$d, $p]) {
                $bl = $blockers($c, $W, $d, $p, $state);
                if (!$bl || count($bl) > 2) {
                    continue;
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
                        $plan = null;
                        break;
                    }
                    $movesLecture = $movesLecture || $b->training_type === 'lecture';
                    $from = $eff($bb[0], $W);
                    $bgrid = $gridOf($bb[0]);
                    $f = $searchBlock($bb, $W, $st, $from[0], $from[1], (int) $bgrid['days'], (int) $bgrid['pairs'], true, $from);
                    if (!$f) {
                        $plan = null;
                        break;
                    }
                    $st = $f[2];
                    $plan[] = [$bb, $from, [$f[0], $f[1]]];
                }
                if (!$plan || $check($c, $W, $d, $p, $st)) {
                    continue;
                }
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
                } else {
                    $add('blocker', 'To\'sib turgan darsni surish: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                        'Faqat shu hafta; dars butun ko\'chadi');
                }
                if (++$nb >= 2) {
                    break;
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

            $result[] = $this->suggestionCard($c, $W, $reason, $sugs);
        }

        return response()->json(['week' => $W, 'total' => $total, 'shown' => count($result), 'cards' => $result,
            'partial' => $partialN > 0, 'message' => $partialN ? $partialN . ' ta karta uchun to\'liq takliflar hisoblanmadi' : null]);
    }

    private function suggestionCard(TimetableCard $c, int $week, string $reason, array $sugs, bool $partial = false): array
    {
        return [
            // true — vaqt byudjeti tugab, faqat tez qadamlar (1–2) hisoblangan; ?card_id= bilan to'liq olinadi.
            'partial' => $partial,
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
            'suggestions' => $sugs,
        ];
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

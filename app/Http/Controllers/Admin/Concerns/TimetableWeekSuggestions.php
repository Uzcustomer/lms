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
        $check = fn (TimetableCard $c, int $w, int $d, int $p, array $st) => $this->findWeekConflicts($c, $w, $d, $p, 0, $st);
        $withMove = function (array $st, TimetableCard $c, int $w, ?int $d, ?int $p, bool $cancel = false) {
            // weekOverride xona berilmagan ko'chirishda shu haftaga berilgan xonani saqlaydi (bekor qilishda tozalaydi).
            $prev = $cancel ? null : ($st['ov'][(int) $c->id][$w] ?? null);
            $o = new TimetableCardOverride();
            $o->forceFill(['card_id' => $c->id, 'week' => $w, 'day' => $d, 'pair' => $p, 'start_half' => 0, 'cancelled' => $cancel,
                'auditorium_code' => $prev?->auditorium_code, 'auditorium_name' => $prev?->auditorium_name]);
            $this->stateSetOverride($st, $o);

            return $st;
        };
        // Shu vaqtda to'sib turgan kartalar: [karta, sabablar].
        $blockers = function (TimetableCard $c, int $w, int $d, int $p, array $st) use ($eff): array {
            $out = [];
            $my = [$p - 1, $p - 1 + $c->lenHalf()];
            foreach ($st['cards'] as $o) {
                if ((int) $o->id === (int) $c->id) {
                    continue;
                }
                $ov = $st['ov'][(int) $o->id][$w] ?? null;
                $pl = $ov ? ($ov->cancelled || !$ov->day ? null : [(int) $ov->day, (int) $ov->pair]) : ($o->day && $o->pair ? [(int) $o->day, (int) $o->pair] : null);
                if (!$pl || $pl[0] !== $d || !$this->halfOverlap($my, [$pl[1] - 1, $pl[1] - 1 + $o->lenHalf()])) {
                    continue;
                }
                $myRoom = ($st['ov'][(int) $c->id][$w] ?? null)?->auditorium_code ?: $c->auditorium_code;
                $why = $this->clashWith($c, $myRoom, $o, $ov?->auditorium_code ?: $o->auditorium_code, $ov?->auditorium_name ?: $o->auditorium_name);
                if ($why) {
                    $out[] = [$o, $why];
                }
            }

            return $out;
        };
        $grids = [];
        $gridOf = function (TimetableCard $c) use (&$grids, $board) {
            $k = $c->faculty_name . '|' . $c->specialty_name . '|' . $c->course;

            return $grids[$k] ??= $this->gridFor($board, $c->faculty_name, (string) $c->specialty_name, (int) $c->course);
        };
        $rooms = null;
        $roomMap = null;
        $teachersOf = [];

        $result = [];
        foreach ($unplaced as $c) {
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

            // 1) Shu kunning boshqa bo'sh vaqti (ma'ruzadan tashqari).
            if (!$sugs) {
                $cand = range(1, max(1, $maxPair - $len + 1));
                usort($cand, fn ($a, $b) => abs($a - $tp) <=> abs($b - $tp) ?: $a <=> $b);
                $n = 0;
                foreach ($cand as $p) {
                    if (in_array([$td, $p], $targets, true) || $check($c, $W, $td, $p, $state)) {
                        continue;
                    }
                    if ($lecture) {
                        $addLate('lecture', 'Ma\'ruzani shu kunning boshqa vaqtiga: ' . $slotText($td, $p, $len), [$mv($c, $W, $td, $p)], $lecNote);
                    } else {
                        $add('slot', 'Shu kun, boshqa vaqt: ' . $slotText($td, $p, $len), [$mv($c, $W, $td, $p)],
                            $sibs ? 'Shu fanning boshqa qismidan ajralib qoladi' : '');
                    }
                    if (++$n >= 2) {
                        break;
                    }
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

            // 3) To'sib turgan amaliy darsni shu haftaning boshqa vaqtiga surish.
            $nb = 0;
            foreach ($targets as [$d, $p]) {
                $bl = $blockers($c, $W, $d, $p, $state);
                if (!$bl || count($bl) > 2) {
                    continue;
                }
                $movesLecture = (bool) array_filter($bl, fn ($b) => $b[0]->training_type === 'lecture');
                $st = $withMove($state, $c, $W, $d, $p);
                $plan = [];
                foreach ($bl as [$b]) {
                    $bpl = $eff($b, $W);
                    $found = null;
                    $days = range(1, $maxDay);
                    usort($days, fn ($a, $x) => ($a !== $bpl[0]) <=> ($x !== $bpl[0]) ?: $a <=> $x);
                    foreach ($days as $bd) {
                        $ps = range(1, max(1, $maxPair - $b->lenHalf() + 1));
                        usort($ps, fn ($a, $x) => abs($a - $bpl[1]) <=> abs($x - $bpl[1]) ?: $a <=> $x);
                        foreach ($ps as $bp) {
                            if ([$bd, $bp] === $bpl) {
                                continue;
                            }
                            $st2 = $withMove($st, $b, $W, $bd, $bp);
                            if (!$check($b, $W, $bd, $bp, $st2)) {
                                $found = [$bd, $bp];
                                break 2;
                            }
                        }
                    }
                    if (!$found) {
                        $plan = null;
                        break;
                    }
                    $st = $withMove($st, $b, $W, $found[0], $found[1]);
                    $plan[] = [$b, $bpl, $found];
                }
                if (!$plan) {
                    continue;
                }
                $st3 = $state;
                foreach ($plan as [$b, , $f]) {
                    $st3 = $withMove($st3, $b, $W, $f[0], $f[1]);
                }
                if ($check($c, $W, $d, $p, $st3)) {
                    continue;
                }
                $txt = [];
                $acts = [];
                foreach ($plan as [$b, $from, $to]) {
                    $txt[] = '«' . $b->subject_name . '» (' . implode(', ', $b->occupiedGroups()) . ($b->teacher_name ? ', ' . $b->teacher_name : '') . ') '
                        . $slotText($from[0], $from[1], $b->lenHalf()) . ' → ' . $slotText($to[0], $to[1], $b->lenHalf());
                    $acts[] = $mv($b, $W, $to[0], $to[1]);
                }
                $acts[] = $mv($c, $W, $d, $p);
                if ($movesLecture) {
                    $addLate('lecture', 'To\'sib turgan ma\'ruzani surish: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                        'Faqat shu hafta. ' . $lecNote);
                } else {
                    $add('blocker', 'To\'sib turgan darsni surish: ' . implode('; ', $txt) . ' — keyin bu karta ' . $slotText($d, $p, $len), $acts,
                        'Faqat shu hafta');
                }
                if (++$nb >= 2) {
                    break;
                }
            }

            // 4) Shu haftaning boshqa kuni (ma'ruza uchun — oxirroqda, ogohlantirish bilan).
            {
                $n = 0;
                for ($d = 1; $d <= $maxDay && $n < 2; $d++) {
                    if ($d === $td) {
                        continue;
                    }
                    $ps = range(1, max(1, $maxPair - $len + 1));
                    usort($ps, fn ($a, $b) => abs($a - $tp) <=> abs($b - $tp) ?: $a <=> $b);
                    foreach ($ps as $p) {
                        if (!$check($c, $W, $d, $p, $state)) {
                            if ($lecture) {
                                $addLate('lecture', 'Ma\'ruzani boshqa kunga: ' . $slotText($d, $p, $len), [$mv($c, $W, $d, $p)], $lecNote);
                            } else {
                                $add('day', 'Boshqa kun: ' . $slotText($d, $p, $len), [$mv($c, $W, $d, $p)]);
                            }
                            $n++;
                            break;
                        }
                    }
                }

                // 5) Boshqa hafta: karta o'tilmaydigan haftaga, tashqi (yoki shu) vaqtida.
                $n = $lecture ? 2 : 0;
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
            if ($c->kafedra_name) {
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
                    // O'qituvchi butun semestr uchun almashadi — karta joylashgan boshqa haftalarda ham bo'sh bo'lishi kerak.
                    $busyElsewhere = false;
                    foreach ($placedWeeks as $w2 => [$d2, $p2]) {
                        if (array_filter($check($tmp, $w2, $d2, $p2, $state), fn ($m) => str_starts_with($m, 'O\'qituvchi band'))) {
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

        return response()->json(['week' => $W, 'total' => $total, 'shown' => count($result), 'cards' => $result]);
    }

    private function suggestionCard(TimetableCard $c, int $week, string $reason, array $sugs): array
    {
        return [
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

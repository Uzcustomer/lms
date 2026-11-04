<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Qoida "Guruhchalar birga o'tadi" (joint_groups): qoida fanlari (masalan Psixologiya — o'quv rejada amaliy, lekin seminar kabi)
 * amaliy darsi bir guruhning guruhchalari bilan BIRGA — bitta karta (group_names = guruhchalar, group_name = "01a + 01b"),
 * bitta o'qituvchi, bir vaqtda.
 *
 * - To'plam: 'guruh' rejimi — guruhcha nomidan oxirgi harf olib tashlangani (p/st26-01a → p/st26-01, "(o'z)/(rus)" hisobga
 *   olinmaydi — baseGroupKey); params.pairs — qo'lda juftlik (ortib qolgan yoki talabasi kam guruhcha): undagi guruhchalar guruh
 *   rejimidan chiqariladi va faqat shu juftlikda birlashadi.
 * - Qamrov: qoida scopes ("yo'nalish · kurs") va subjects; faqat amaliy kartalar. Ma'ruza, bo'lakli fanlar ("Fan (a)") va sikl
 *   fanlari — kirmaydi.
 * - Qo'llash (apply-joint): faqat yakka guruhcha kartalari birlashadi; qoidaga mos kelmaydigan birlashgan karta — ogohlantirish.
 *   Bo'laklar (len_half, weeks) mos bo'lishi kerak; o'qituvchilar har xil bo'lsa — birlashtirilmaydi. Birlashgan karta birinchi
 *   kartaning (id bo'yicha) joyi, xonasi va hafta yozuvlari bilan; shu joyda to'qnashuv bo'lsa — panelga qaytadi.
 * - Ajratish (split-joint): teskari amal — har guruhchaga alohida karta (o'qituvchi, joy, xona, hafta yozuvlari ko'chadi).
 * - Kartalar yaratilganda (assembleRows) faol qoida bo'yicha boshidanoq birlashgan holda.
 */
trait TimetableJointGroups
{
    /** Kartalar yaratilganda qoida bo'yicha birlashtirilgan amaliy to'plamlari: [[fakultet, yo'nalish, kurs, fan, [guruhchalar]], ...]. */
    private array $jointGenerated = [];

    /** Faol "Guruhchalar birga o'tadi" qoidalari (ro'yxat tartibida). */
    private function jointRules(TimetableBoard $board)
    {
        return Schema::hasTable('timetable_rules')
            ? TimetableRule::where('board_id', $board->id)->where('active', true)->where('condition', 'joint_groups')
                ->orderBy('position')->orderBy('id')->get()
            : collect();
    }

    /** Qoida shu fakultet · yo'nalish · kurs · fanga tegishlimi (fanlar va qamrov bo'sh — barchasi; qamrov — ruleScopeMatches). */
    private function jointRuleCovers(TimetableRule $rule, ?string $faculty, string $specialty, int $course, string $subject): bool
    {
        $subjects = (array) ($rule->subjects ?: []);
        if ($subjects && !in_array($this->normSubject($subject), array_map(fn ($s) => $this->normSubject((string) $s), $subjects), true)) {
            return false;
        }

        return $this->ruleScopeMatches((array) ($rule->scopes ?: []), $faculty, $specialty, $course);
    }

    /** Fan shu qoida uchun mos emas: bo'lakli ("Fan (a)") yoki sikl fani. */
    private function jointSkippedSubject(array $cycleKeys, string $specialty, int $course, string $subject): bool
    {
        if ($this->cycleSubjectVariantBase($subject) !== null) {
            return true;
        }

        return isset($cycleKeys[$this->specKey($specialty) . '|' . $course . '|' . $this->normSubject($subject)]);
    }

    /** Qo'lda juftliklar: [[guruhcha, ...], ...] (kamida 2 ta, takrorsiz). */
    private static function jointPairs(TimetableRule $rule): array
    {
        $out = [];
        foreach ((array) (($rule->params ?: [])['pairs'] ?? []) as $p) {
            $p = array_values(array_unique(array_filter(array_map(fn ($g) => trim((string) $g), (array) $p), fn ($g) => $g !== '')));
            if (count($p) >= 2) {
                $out[] = $p;
            }
        }

        return $out;
    }

    /** Saqlashda qo'lda juftliklar: bo'sh/takror guruhchalar olib tashlanadi, bir guruhcha faqat birinchi juftlikda qoladi. */
    private function jointPairsClean(array $pairs): array
    {
        $seen = [];
        $out = [];
        foreach ($pairs as $p) {
            $p = array_values(array_filter(array_unique(array_map(fn ($g) => trim((string) $g), (array) $p)),
                fn ($g) => $g !== '' && !isset($seen[$this->groupNameKey($g)])));
            if (count($p) >= 2) {
                foreach ($p as $g) {
                    $seen[$this->groupNameKey($g)] = true;
                }
                $out[] = $p;
            }
        }

        return $out;
    }

    /** Guruhcha qaysi to'plamga tushadi: "p|i" (i-juftlik) yoki "g|asos guruh". */
    private function jointSetKey(TimetableRule $rule, string $group): string
    {
        $k = $this->groupNameKey($group);
        foreach (self::jointPairs($rule) as $i => $pair) {
            if (in_array($k, array_map(fn ($g) => $this->groupNameKey($g), $pair), true)) {
                return 'p|' . $i;
            }
        }

        return 'g|' . $this->baseGroupKey($group);
    }

    /** Kartalar yaratilganda: shu fan uchun faol qoida (birinchisi) yoki null. */
    private function jointRuleForRow($rules, array $cycleKeys, ?string $faculty, string $specialty, int $course, string $subject): ?TimetableRule
    {
        if ($this->jointSkippedSubject($cycleKeys, $specialty, $course, $subject)) {
            return null;
        }
        foreach ($rules as $rule) {
            if ($this->jointRuleCovers($rule, $faculty, $specialty, $course, $subject)) {
                return $rule;
            }
        }

        return null;
    }

    /** Kartalar yaratilganda amaliy qatorlari to'plamlari (practiceBundles o'rniga): guruh bo'yicha va qo'lda juftliklar. */
    private function jointBundles(TimetableRule $rule, array $practiceRows): array
    {
        $sets = [];
        foreach ($practiceRows as $gr) {
            $gn = trim((string) ($gr['name'] ?? ''));
            if ($gn !== '') {
                $sets[$this->jointSetKey($rule, $gn)][] = $gr;
            }
        }
        foreach ($sets as &$rows) {
            usort($rows, fn ($a, $b) => strnatcasecmp((string) $a['name'], (string) $b['name']));
        }
        unset($rows);

        return array_values($sets);
    }

    private function jointDenied(Request $request)
    {
        return in_array($this->timetableActiveRole($request), ['admin', 'superadmin', 'registrator_ofisi'], true)
            ? null : response()->json(['error' => 'Bu amal faqat admin va registrator ofisi uchun.'], 403);
    }

    /** Qoida qamrovidagi amaliy kartalar (bo'lakli va sikl fanlarisiz), id tartibida. */
    private function jointScopeCards(TimetableBoard $board, TimetableRule $rule)
    {
        $cycleKeys = $this->cycleSubjectKeys($board);

        return TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->orderBy('id')->get()
            ->filter(fn (TimetableCard $c) => !$this->jointSkippedSubject($cycleKeys, (string) $c->specialty_name, (int) $c->course, (string) $c->subject_name)
                && $this->jointRuleCovers($rule, $c->faculty_name, (string) $c->specialty_name, (int) $c->course, (string) $c->subject_name))
            ->values();
    }

    /** Guruhchalar qisqa yorlig'i: "01a + 01b" (umumiy prefiks olib tashlanadi). */
    private static function jointShortLabel(array $groups): string
    {
        return implode(' + ', array_map(fn ($g) => preg_match('/(\d+[^\d\s]*)(\s*\(.*\))?\s*$/u', (string) $g, $m) ? $m[1] : (string) $g, $groups));
    }

    /** POST boards/{board}/rules/{rule}/apply-joint {dry_run?} — qoida bo'yicha guruhchalar kartalarini birlashtirish. */
    public function applyJointGroups(Request $request, TimetableBoard $board, TimetableRule $rule)
    {
        abort_unless((int) $rule->board_id === (int) $board->id && $rule->condition === 'joint_groups', 404);
        if ($denied = $this->jointDenied($request)) {
            return $denied;
        }
        $dry = $request->boolean('dry_run');
        if (!$dry) {
            $this->snapshotBefore($request, $board, 'before_joint_groups');
        }
        $hasOv = Schema::hasTable('timetable_card_overrides');
        $cards = $this->jointScopeCards($board, $rule);
        // To'plam: fakultet · yo'nalish · kurs · fan · (guruh | qo'lda juftlik).
        $sets = [];
        foreach ($cards as $c) {
            $base = mb_strtolower(trim((string) $c->faculty_name)) . '|' . $this->specKey((string) $c->specialty_name) . '|' . (int) $c->course
                . '|' . $this->normSubject((string) $c->subject_name) . '|';
            $groups = $c->occupiedGroups();
            if (count($groups) === 1) {
                $sets[$base . $this->jointSetKey($rule, (string) $groups[0])]['single'][(string) $groups[0]][] = $c;
            } else {
                foreach (array_unique(array_map(fn ($g) => $this->jointSetKey($rule, (string) $g), $groups)) as $sk) {
                    $sets[$base . $sk]['joint'][$c->id] = $c;
                }
            }
        }
        $list = [];
        $merged = 0;
        $removed = 0;
        $warnedJoint = [];   // bir nechta to'plamga tushgan noto'g'ri birlashgan karta — bir marta
        DB::beginTransaction();
        try {
            foreach ($sets as $set) {
                $single = $set['single'] ?? [];
                uksort($single, 'strnatcasecmp');
                $groups = array_keys($single);
                $sample = $single ? reset($single)[0] : reset($set['joint']);
                $subject = (string) $sample->subject_name;
                // Qoidaga mos kelmaydigan birlashgan karta (masalan avtomatik juftlashdan 01b + 02a) — tegilmaydi.
                foreach ($set['joint'] ?? [] as $j) {
                    $jg = $j->occupiedGroups();
                    natcasesort($jg);
                    $keys = array_unique(array_map(fn ($g) => $this->jointSetKey($rule, (string) $g), $jg));
                    if (count($keys) > 1 && !isset($warnedJoint[$j->id])) {
                        $warnedJoint[$j->id] = true;
                        $list[] = ['subject' => $subject, 'type' => 'practice', 'groups' => self::jointShortLabel(array_values($jg)), 'cards_from' => [$j->id],
                            'card_to' => null, 'placed_at' => null, 'warning' => $subject . ' · ' . self::jointShortLabel(array_values($jg)) . ': qoidaga mos emas — avval ajrating'];
                    }
                }
                if (count($groups) < 2) {
                    continue;   // birlashtiradigan yakka guruhcha yo'q (allaqachon birlashgan yoki bitta guruhcha)
                }
                $label = $subject . ' · ' . self::jointShortLabel($groups);
                // Qo'lda juftlik turli oqimda — kartalar yaratishda (oqim bo'yicha) birlashmaydi; qo'llash ham bir xil natija bersin.
                $oqims = array_unique(array_map(fn ($l) => mb_strtolower(trim((string) $l[0]->oqim_label)), $single));
                if (count($oqims) > 1) {
                    $list[] = ['subject' => $subject, 'type' => 'practice', 'groups' => self::jointShortLabel($groups), 'cards_from' => [], 'card_to' => [],
                        'placed_at' => [], 'warning' => $label . ': juftlikdagi guruhchalar turli oqimda — birlashtirilmadi (bir oqim ichida juftlang)'];
                    continue;
                }
                $row = ['subject' => $subject, 'type' => 'practice', 'groups' => self::jointShortLabel($groups), 'cards_from' => [], 'card_to' => [],
                    'placed_at' => [], 'warning' => null];
                // Bo'laklar: har guruhchada (len_half, weeks) bo'yicha saralangan ro'yxat — bir xil bo'lishi kerak.
                $pieces = [];
                $sig = [];
                foreach ($single as $g => $list0) {
                    usort($list0, fn ($a, $b) => [(int) $a->len_half, (int) $a->weeks, $a->id] <=> [(int) $b->len_half, (int) $b->weeks, $b->id]);
                    $pieces[$g] = $list0;
                    $sig[$g] = implode('+', array_map(fn ($c) => $c->lenHalf() . ($c->weeks ? '×' . (int) $c->weeks : ''), $list0));
                    array_push($row['cards_from'], ...array_map(fn ($c) => $c->id, $list0));
                }
                if (count(array_unique($sig)) > 1) {
                    $row['warning'] = $label . ': kartalar soni/soati mos emas ('
                        . implode(', ', array_map(fn ($g) => self::jointShortLabel([$g]) . ': ' . $sig[$g], array_keys($sig))) . ') — birlashtirilmadi';
                    $list[] = $row;
                    continue;
                }
                $teachers = [];
                foreach ($pieces as $l) {
                    foreach ($l as $c) {
                        if ($c->teacher_id) {
                            $teachers[(int) $c->teacher_id] = (string) $c->teacher_name;
                        }
                    }
                }
                if (count($teachers) > 1) {
                    $row['warning'] = $label . ': o\'qituvchilar har xil (' . implode(', ', array_unique($teachers)) . ') — birlashtirilmadi';
                    $list[] = $row;
                    continue;
                }
                $notes = [];
                $n = count(reset($pieces));
                for ($i = 0; $i < $n; $i++) {
                    $part = array_map(fn ($l) => $l[$i], $pieces);
                    usort($part, fn ($a, $b) => $a->id <=> $b->id);
                    $keep = $part[0];
                    $students = array_sum(array_map(fn ($c) => (int) $c->students, $part));
                    $teacher = collect($part)->first(fn ($c) => $c->teacher_id);
                    $keep->update(['group_name' => implode(' + ', $groups), 'group_names' => $groups, 'students' => max(1, $students)]
                        + ($teacher && !$keep->teacher_id ? ['teacher_id' => $teacher->teacher_id, 'teacher_name' => $teacher->teacher_name] : []));
                    foreach (array_slice($part, 1) as $c) {
                        if ($hasOv) {
                            TimetableCardOverride::where('card_id', $c->id)->delete();
                        }
                        $c->delete();
                        $removed++;
                    }
                    $row['card_to'][] = $keep->id;
                    // Joy: birinchi kartaniki (shablon joyi va hafta bo'yicha ko'chirilgan joylari); shu joylarda to'qnashuv (o'qituvchi,
                    // xona, guruh, sig'im) bo'lsa — butunlay panelga qaytadi (shablon joyi ham, hafta joylari ham olinadi).
                    $keep = $keep->fresh();
                    $why = $keep->day && $keep->pair ? $this->findConflicts($keep, (int) $keep->day, (int) $keep->pair, (int) $keep->start_half) : [];
                    $moved = $hasOv ? TimetableCardOverride::where('card_id', $keep->id)->where('cancelled', false)->whereNotNull('day')->whereNotNull('pair')->get() : collect();
                    foreach ($moved as $ov) {
                        foreach ($this->findWeekConflicts($keep, (int) $ov->week, (int) $ov->day, (int) $ov->pair, (int) $ov->start_half) as $e) {
                            $why[] = $ov->week . '-hafta: ' . $e;
                        }
                    }
                    if ($why) {
                        $keep->update(['day' => null, 'pair' => null, 'start_half' => 0]);
                        foreach ($moved as $ov) {
                            $ov->delete();
                        }
                        $notes[] = $label . ': birinchi karta joyida to\'qnashuv: ' . implode(' · ', array_slice(array_values(array_unique($why)), 0, 3)) . ' — panelga qaytarildi';
                        $row['placed_at'][] = null;
                    } else {
                        $row['placed_at'][] = $keep->day && $keep->pair ? ['day' => (int) $keep->day, 'pair' => (int) $keep->pair] : null;
                    }
                }
                $merged++;
                $row['warning'] = $notes ? implode('; ', array_unique($notes)) : null;
                $list[] = $row;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        // Sinov (dry_run) — xuddi shu amal tranzaksiyada bajarilib, qaytariladi (to'qnashuv tekshiruvi ham haqiqiy).
        $dry ? DB::rollBack() : DB::commit();

        return response()->json(['ok' => true, 'dry_run' => $dry, 'merged' => $merged, 'removed' => $dry ? 0 : $removed, 'list' => array_slice($list, 0, 500)]);
    }

    /** POST boards/{board}/rules/{rule}/split-joint {dry_run?} — qoida qamrovidagi birlashgan kartalarni guruhchalarga ajratish. */
    public function splitJointGroups(Request $request, TimetableBoard $board, TimetableRule $rule)
    {
        abort_unless((int) $rule->board_id === (int) $board->id && $rule->condition === 'joint_groups', 404);
        if ($denied = $this->jointDenied($request)) {
            return $denied;
        }
        $dry = $request->boolean('dry_run');
        if (!$dry) {
            $this->snapshotBefore($request, $board, 'before_split_joint_groups');
        }
        $cards = $this->jointScopeCards($board, $rule)->filter(fn (TimetableCard $c) => count($c->occupiedGroups()) > 1)->values();
        $hasOv = Schema::hasTable('timetable_card_overrides');
        $ovCols = $hasOv ? array_values(array_intersect(['week', 'day', 'pair', 'start_half', 'cancelled', 'auditorium_code', 'auditorium_name'],
            Schema::getColumnListing('timetable_card_overrides'))) : [];
        $list = [];
        $created = 0;
        DB::transaction(function () use ($cards, $dry, $hasOv, $ovCols, &$list, &$created) {
            foreach ($cards as $card) {
                $names = array_values($card->occupiedGroups());
                $list[] = ['subject' => (string) $card->subject_name, 'type' => 'practice', 'groups' => self::jointShortLabel($names), 'cards_from' => [$card->id],
                    'card_to' => count($names) . ' ta karta', 'placed_at' => $card->day && $card->pair ? ['day' => (int) $card->day, 'pair' => (int) $card->pair] : null,
                    'warning' => null];
                if ($dry) {
                    continue;
                }
                $counts = array_map(fn ($n) => $this->activeStudentCountFor((string) $n, $card->faculty_name), $names);
                $fallback = (int) max(1, round($card->students / max(1, count($names))));
                $ovs = $hasOv ? DB::table('timetable_card_overrides')->where('card_id', $card->id)->get() : collect();
                foreach ($names as $i => $name) {
                    $attrs = ['group_name' => $name, 'group_names' => null, 'students' => max(1, $counts[$i] ?: $fallback)];
                    if ($i === 0) {
                        $card->update($attrs);
                        continue;
                    }
                    // O'qituvchi, joy va xona har guruhchaga ko'chadi (bir vaqtda, bitta o'qituvchi — ajratilgach ham shu holat).
                    $copy = $card->replicate();
                    $copy->fill($attrs);
                    $copy->save();
                    $created++;
                    foreach ($ovs as $o) {
                        $r = ['card_id' => $copy->id, 'created_at' => now(), 'updated_at' => now()];
                        foreach ($ovCols as $col) {
                            $r[$col] = $o->{$col};
                        }
                        DB::table('timetable_card_overrides')->insert($r);
                    }
                }
            }
        });

        return response()->json(['ok' => true, 'dry_run' => $dry, 'cards' => $cards->count(), 'created' => $created, 'list' => array_slice($list, 0, 500)]);
    }

    /**
     * Kartalar yaratilgach: qoida bo'yicha birlashtirib yaratilgan to'plamlarda guruhchalarning avvalgi (yaratishdan oldingi)
     * amaliy kartalari turli o'qituvchida bo'lgan bo'lsa — ogohlantirish (o'qituvchi tiklanishida bittasi olinadi).
     * $before — [fakultet|yo'nalish|kurs|fan|guruhcha => [o'qituvchi id => F.I.Sh.]].
     */
    private function jointTeacherWarnings(array $before): array
    {
        $out = [];
        foreach ($this->jointGenerated as [$fac, $spec, $course, $subject, $names]) {
            $teachers = [];
            foreach ($names as $g) {
                $teachers += $before[$this->jointCardKey((string) $fac, (string) $spec, (int) $course, (string) $subject, (string) $g)] ?? [];
            }
            if (count($teachers) > 1) {
                $out[] = $subject . ' · ' . self::jointShortLabel($names) . ': o\'qituvchilar har xil edi (' . implode(', ', array_unique($teachers))
                    . ') — birlashgan kartada tekshiring';
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Kartalar yaratilganda: qoida bo'yicha birlashgan karta (group_name "01a + 01b") biriktiruv kaliti guruhchalarnikidan farq qiladi —
     * guruhchalarning saqlangan o'qituvchi/xonasi birlashgan birlikka o'tkaziladi (o'qituvchi — guruhchalarda bir xil bo'lsa; xona —
     * birinchisi). restoreAssignments chaqiradi; $kept — [unit kalit => [meta, teacher_id, teacher_name, auditorium_code, auditorium_name]].
     */
    private function jointMapAssignments(array $kept): array
    {
        foreach ($this->jointGenerated as [$fac, $spec, $course, $subject, $names]) {
            $card = fn (?string $f, string $g) => (new TimetableCard())->forceFill(['faculty_name' => $f, 'specialty_name' => $spec, 'course' => (int) $course,
                'subject_name' => $subject, 'training_type' => 'practice', 'group_name' => $g]);
            $joint = $card($fac ?: null, implode(' + ', $names));
            $jk = $this->unitKeyHash($joint);
            if (isset($kept[$jk])) {
                continue;
            }
            $teachers = [];
            $room = null;
            foreach ($names as $g) {
                $a = $kept[$this->unitKeyHash($card($fac ?: null, (string) $g))] ?? $kept[$this->unitKeyHash($card(null, (string) $g))] ?? null;
                if (!$a) {
                    continue;
                }
                if (!empty($a['teacher_id'])) {
                    $teachers[(int) $a['teacher_id']] = (string) $a['teacher_name'];
                }
                $room ??= !empty($a['auditorium_code']) ? [$a['auditorium_code'], $a['auditorium_name']] : null;
            }
            if (!$teachers && !$room) {
                continue;
            }
            $one = count($teachers) === 1;
            $kept[$jk] = ['meta' => $this->unitMeta($joint), 'teacher_id' => $one ? array_key_first($teachers) : null,
                'teacher_name' => $one ? reset($teachers) : null, 'auditorium_code' => $room[0] ?? null, 'auditorium_name' => $room[1] ?? null];
        }

        return $kept;
    }

    private function jointCardKey(string $fac, string $spec, int $course, string $subject, string $group): string
    {
        return mb_strtolower(trim($fac)) . '|' . $this->specKey($spec) . '|' . $course . '|' . $this->normSubject($subject) . '|' . $this->groupNameKey($group);
    }

    /** Yaratishdan oldingi amaliy kartalar o'qituvchilari (jointTeacherWarnings uchun) — faqat faol qoida bo'lsa. */
    private function jointTeachersBefore(TimetableBoard $board): array
    {
        if ($this->jointRules($board)->isEmpty()) {
            return [];
        }
        $out = [];
        foreach (TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->whereNotNull('teacher_id')
            ->get(['faculty_name', 'specialty_name', 'course', 'subject_name', 'group_name', 'group_names', 'teacher_id', 'teacher_name']) as $c) {
            foreach ($c->occupiedGroups() as $g) {
                $out[$this->jointCardKey((string) $c->faculty_name, (string) $c->specialty_name, (int) $c->course, (string) $c->subject_name, (string) $g)][(int) $c->teacher_id]
                    = (string) $c->teacher_name;
            }
        }

        return $out;
    }
}

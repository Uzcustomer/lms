<?php

namespace App\Services\Timetable;

use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
use App\Models\TimetableCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Doska versiyalari: nusxa olish, eskilarini tozalash va nusxadan joylashuvni tiklash.
 *
 * Tiklash kartaning id'siga emas, dars birligiga qarab ishlaydi (fakultet, yo'nalish, kurs, fan, tur,
 * guruh / oqim): "Kartochkalarni yaratish" kartani o'chirib yangisini qo'shsa ham (soat yoki hafta soni
 * o'zgargan), yangi karta eski kartaning joyini oladi. Kartalar bo'linishi o'zgargan bo'lsa (3 soatlik
 * bitta karta → 1 + 2 soatlik ikki karta) — yangi kartalar eski kartalar egallagan soatlarga ketma-ket
 * joylanadi. Faqat joylanmagan (ichki ham, tashqi ham bo'sh) kartalar tiklanadi — keyin qo'yilganlariga tegilmaydi.
 */
class BoardSnapshots
{
    public const CARD_COLS = ['id', 'specialty_name', 'course', 'faculty_name', 'oqim_label', 'lang', 'training_type', 'group_name',
        'group_names', 'subject_name', 'kafedra_name', 'teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name',
        'day', 'pair', 'start_half', 'len_half', 'weeks', 'ext_day', 'ext_pair'];

    public const OV_COLS = ['card_id', 'week', 'day', 'pair', 'start_half', 'cancelled', 'auditorium_code', 'auditorium_name'];

    public static function ready(): bool
    {
        return Schema::hasTable('timetable_board_snapshots');
    }

    /** Doskaning hozirgi holati (kartalar, haftalik o'zgartirishlar, sikl joylashuvlari). */
    public function collect(TimetableBoard $board, ?string $connection = null): array
    {
        $db = $connection ? DB::connection($connection) : DB::connection();
        $cardCols = array_values(array_filter(self::CARD_COLS, fn ($c) => $db->getSchemaBuilder()->hasColumn('timetable_cards', $c)));
        $cards = $db->table('timetable_cards')->where('board_id', $board->id)->get($cardCols)->map(fn ($r) => (array) $r)->all();
        $ids = array_column($cards, 'id');
        $overrides = [];
        if ($db->getSchemaBuilder()->hasTable('timetable_card_overrides')) {
            $ovCols = array_values(array_filter(self::OV_COLS, fn ($c) => $db->getSchemaBuilder()->hasColumn('timetable_card_overrides', $c)));
            foreach (array_chunk($ids, 1000) as $chunk) {
                foreach ($db->table('timetable_card_overrides')->whereIn('card_id', $chunk)->get($ovCols) as $o) {
                    $overrides[] = (array) $o;
                }
            }
        }
        $cycle = $db->getSchemaBuilder()->hasTable('timetable_cycle_placements')
            ? $db->table('timetable_cycle_placements')->where('board_id', $board->id)->get()->map(fn ($r) => (array) $r)->all()
            : [];

        return ['cards' => $cards, 'overrides' => $overrides, 'cycle_placements' => $cycle];
    }

    /** Nusxa oladi. Xato bo'lsa amalni to'xtatmaydi (null). */
    public function take(TimetableBoard $board, string $reason, ?string $by = null, ?array $data = null): ?TimetableBoardSnapshot
    {
        if (!self::ready()) {
            return null;
        }
        try {
            $data ??= $this->collect($board);
            $placed = count(array_filter($data['cards'], fn ($c) => (!empty($c['day']) && !empty($c['pair'])) || (!empty($c['ext_day']) && !empty($c['ext_pair']))));

            return TimetableBoardSnapshot::create([
                'board_id' => $board->id, 'reason' => mb_substr($reason, 0, 50), 'created_by_name' => $by,
                'cards_count' => count($data['cards']), 'placed_count' => $placed, 'overrides_count' => count($data['overrides']),
                'payload' => base64_encode(gzencode(json_encode($data, JSON_UNESCAPED_UNICODE), 6)),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TimetableSnapshot] ' . $board->id . ' ' . $reason . ': ' . $e->getMessage());

            return null;
        }
    }

    /** Eskilarini tozalash: avtomatik nusxalar $days kundan keyin o'chadi, qo'lda olinganlari qoladi. */
    public function prune(int $days = 30): int
    {
        if (!self::ready()) {
            return 0;
        }

        return TimetableBoardSnapshot::where('reason', '!=', 'manual')->where('created_at', '<', now()->subDays($days))->delete();
    }

    private function norm($v): string
    {
        $v = mb_strtolower(trim((string) $v));
        $v = str_replace(["'", '’', 'ʻ', 'ʼ', '`', '´', '‘'], '', $v);

        return preg_replace('/\s+/u', ' ', $v);
    }

    /** Dars birligi kaliti (karta id'siga bog'liq emas). */
    public function unitKey(array $c): string
    {
        $names = $c['group_names'] ?? null;
        if (is_string($names)) {
            $names = json_decode($names, true);
        }
        $names = array_map(fn ($g) => $this->norm($g), (array) ($names ?: []));
        sort($names);
        $scope = ($c['training_type'] ?? '') === 'lecture'
            ? 'L|' . $this->norm($c['oqim_label'] ?? '') . '|' . $this->norm($c['lang'] ?? '') . '|' . implode('+', $names)
            : 'P|' . $this->norm($c['group_name'] ?? '') . '|' . implode('+', $names);

        return implode('¦', [$this->norm($c['faculty_name'] ?? ''), $this->norm($c['specialty_name'] ?? ''), (int) ($c['course'] ?? 0),
            $this->norm($c['subject_name'] ?? ''), (string) ($c['training_type'] ?? ''), $scope]);
    }

    /**
     * "Kartochkalar" sinxronlashidan keyin: shu sinxronlashda yaratilgan (avval bo'lmagan) kartalar eski kartaning
     * joyini oladi. Avvaldan joylanmagan, saqlangan kartalarga tegilmaydi — ular ataylab bo'sh qoldirilgan bo'lishi mumkin.
     */
    public function keepPlacementOfRecreated(TimetableBoard $board, array $before, ?callable $conflicts = null): array
    {
        return $this->restorePlacement($board, $before, false, ['only_new' => true], false, $conflicts);
    }

    /**
     * Nusxadagi joylashuvni hozirgi joylanmagan kartalarga tiklaydi.
     * $scope: faculty_names / specialty_names / courses (ixtiyoriy); only_new — faqat nusxada yo'q (yangi) kartalar.
     * $conflicts(karta, 'internal'|'external'|'week', hafta, kun, para, start_half, haftalik yozuv xonasi): to'qnashuvlar
     * matni — qo'lda joylashtirishdagi tekshiruv; to'qnashgan joylashuv yozilmaydi. Qaytaradi: hisobot.
     */
    public function restorePlacement(TimetableBoard $board, array $old, bool $dryRun, array $scope = [], bool $takeSnapshot = true, ?callable $conflicts = null, ?callable $progress = null): array
    {
        $lenOf = fn (array $c) => max(1, (int) ($c['len_half'] ?? 2));
        $inScope = function (array $c) use ($scope): bool {
            if (!empty($scope['faculty_names']) && !in_array((string) ($c['faculty_name'] ?? ''), $scope['faculty_names'], true)) {
                return false;
            }
            if (!empty($scope['specialty_names']) && !in_array((string) ($c['specialty_name'] ?? ''), $scope['specialty_names'], true)) {
                return false;
            }

            return empty($scope['courses']) || in_array((int) ($c['course'] ?? 0), array_map('intval', $scope['courses']), true);
        };
        $oldByUnit = [];
        foreach ((array) ($old['cards'] ?? []) as $c) {
            $oldByUnit[$this->unitKey($c)][] = $c;
        }
        $oldIds = !empty($scope['only_new']) ? array_flip(array_map('intval', array_column((array) ($old['cards'] ?? []), 'id'))) : [];
        $oldOv = [];
        foreach ((array) ($old['overrides'] ?? []) as $o) {
            $oldOv[(int) $o['card_id']][(int) $o['week']] = $o;
        }

        $current = $this->collect($board);
        $curOv = [];
        foreach ($current['overrides'] as $o) {
            $curOv[(int) $o['card_id']][(int) $o['week']] = $o;
        }
        $curById = [];
        foreach ($current['cards'] as $c) {
            $curById[(int) $c['id']] = $c;
        }
        $curByUnit = [];
        foreach ($current['cards'] as $c) {
            if ($inScope($c)) {
                $curByUnit[$this->unitKey($c)][] = $c;
            }
        }

        $cardUpdates = [];     // id => [col => value]
        $ovInserts = [];       // [card_id, week, day, pair, start_half]
        $weekDone = [];        // karta id => true: kamida bitta haftalik joy tiklandi
        $exampleCards = [];
        $stats = ['units' => 0, 'cards' => 0, 'internal' => 0, 'external' => 0, 'week_moves' => 0, 'teacher_room' => 0,
            'no_source' => 0, 'source_unplaced' => 0, 'conflicts' => 0, 'examples' => [], 'conflict_examples' => []];

        $valid = fn (array $o) => empty($o['cancelled']) && !empty($o['day']) && !empty($o['pair']);
        // Haftalik ko'chirish (ichki jadval): eski kartaning shu haftadagi joyi, xuddi shu siljish bilan.
        // Hafta yangi kartada bekor qilingan yoki allaqachon joylangan bo'lsa — tegilmaydi (kartaning o'z haftalari).
        $addWeek = function (array $c, int $w, array $o, int $offset) use (&$ovInserts, &$weekDone, $curOv): void {
            $mine = $curOv[(int) $c['id']][$w] ?? null;
            if ($mine && (!empty($mine['cancelled']) || !empty($mine['day']))) {
                return;
            }
            $ovInserts[] = ['card_id' => $c['id'], 'week' => $w, 'day' => (int) $o['day'], 'pair' => (int) $o['pair'] + $offset,
                'start_half' => (int) ($o['start_half'] ?? 0), 'auditorium_code' => $o['auditorium_code'] ?? null,
                'auditorium_name' => $o['auditorium_name'] ?? null, 'exists' => (bool) $mine];
            $weekDone[(int) $c['id']] = true;
        };
        // O'qituvchi/xona — hozir bo'sh bo'lsa, eski kartadan.
        $teacherRoom = function (array $c, array $oldCard) use (&$cardUpdates, &$stats): void {
            if (empty($c['teacher_id']) && !empty($oldCard['teacher_id']) && !isset($cardUpdates[$c['id']]['teacher_id'])) {
                $cardUpdates[$c['id']]['teacher_id'] = $oldCard['teacher_id'];
                $cardUpdates[$c['id']]['teacher_name'] = $oldCard['teacher_name'];
                $stats['teacher_room']++;
            }
            if (empty($c['auditorium_code']) && !empty($oldCard['auditorium_code']) && !isset($cardUpdates[$c['id']]['auditorium_code'])) {
                $cardUpdates[$c['id']]['auditorium_code'] = $oldCard['auditorium_code'];
                $cardUpdates[$c['id']]['auditorium_name'] = $oldCard['auditorium_name'];
            }
        };
        // Shablonsiz (faqat haftalarda) joylashgan eski kartaning hamma haftalik joylari.
        $copyWeeks = function (array $c, array $oldCard) use ($oldOv, $valid, $addWeek, $teacherRoom): void {
            foreach ($oldOv[(int) $oldCard['id']] ?? [] as $w => $o) {
                if ($valid($o)) {
                    $addWeek($c, (int) $w, $o, 0);
                }
            }
            $teacherRoom($c, $oldCard);
        };

        foreach ($curByUnit as $key => $cards) {
            $todo = array_values(array_filter($cards, fn ($c) => empty($c['day']) && empty($c['ext_day']) && !isset($oldIds[(int) $c['id']])));
            if (!$todo) {
                continue;
            }
            $src = $oldByUnit[$key] ?? [];
            if (!$src) {
                $stats['no_source'] += count($todo);
                continue;
            }
            $stats['units']++;
            $sig = fn (array $list) => implode(',', array_map(fn ($c) => $lenOf($c) . 'x' . (int) ($c['weeks'] ?? 0), $list));
            $byLen = fn (array $a, array $b) => [$lenOf($b), (int) ($b['weeks'] ?? 0), $a['id']] <=> [$lenOf($a), (int) ($a['weeks'] ?? 0), $b['id']];
            usort($todo, $byLen);
            usort($src, $byLen);
            // Nusxada ham bor (saqlanib qolgan) karta — faqat o'z eski joyiga. Qayta yaratilgan kartalar — hozir yo'q
            // (o'chirilgan) eski kartalar joyiga: hozir ham bor kartaning eski joyi o'shaniki (u ko'chirilgan bo'lsa ham).
            $srcById = [];
            foreach ($src as $sc) {
                $srcById[(int) $sc['id']] = $sc;
            }
            $fresh = array_values(array_filter($todo, fn ($c) => !isset($srcById[(int) $c['id']])));
            $gone = array_values(array_filter($src, fn ($sc) => !isset($curById[(int) $sc['id']])));
            // Bir xil bo'linish (soat va haftalar bir xil) — kartama-karta; aks holda eski soatlar ichiga ketma-ket.
            $same = count($fresh) === count($gone) && $sig($fresh) === $sig($gone);
            // Shablon joyi yo'q, faqat haftalarda joylangan o'chirilgan kartalar (ichki jadval).
            $weekOnly = array_values(array_filter($gone, fn ($sc) => empty($sc['day']) || empty($sc['pair'])));

            foreach (['internal' => ['day', 'pair'], 'external' => ['ext_day', 'ext_pair']] as $layer => [$dCol, $pCol]) {
                // Eski kartalar egallagan bo'laklar (eski karta id'si bo'yicha); hozir ham bor kartaniki — band.
                $segs = [];
                foreach ($src as $sc) {
                    if (!empty($sc[$dCol]) && !empty($sc[$pCol])) {
                        $alive = isset($curById[(int) $sc['id']]);
                        $segs[(int) $sc['id']] = ['d' => (int) $sc[$dCol], 'p' => (int) $sc[$pCol], 'len' => $lenOf($sc),
                            'used' => $alive ? $lenOf($sc) : 0, 'alive' => $alive, 'card' => $sc];
                    }
                }
                uasort($segs, fn ($a, $b) => [$a['d'], $a['p']] <=> [$b['d'], $b['p']]);
                $place = [];      // [karta, bo'lak, siljish]
                $weekFrom = [];   // kartama-karta: shablonsiz eski karta (faqat haftalik joylari)
                foreach ($todo as $c) {
                    if (isset($segs[(int) $c['id']])) {
                        $place[] = [$c, (int) $c['id'], 0];
                    }
                }
                foreach ($fresh as $i => $c) {
                    $l = $lenOf($c);
                    $seg = null;
                    if ($same) {
                        $sid = (int) $gone[$i]['id'];
                        if (!isset($segs[$sid])) {
                            $weekFrom[] = [$c, $gone[$i]];
                            continue;
                        }
                        $seg = $sid;
                    } else {
                        foreach ($segs as $k => $sg) {
                            if (!$sg['alive'] && $sg['len'] - $sg['used'] >= $l) {
                                $seg = $k;
                                break;
                            }
                        }
                        // Sig'madi — avval hafta-hafta joylangan eski kartalar o'rniga (pastda), ular bo'lmasa
                        // o'chirilgan kartalarning oxirgi bo'lagi davomiga.
                        if ($seg === null && $layer === 'internal' && $weekOnly) {
                            continue;
                        }
                        if ($seg === null) {
                            foreach ($segs as $k => $sg) {
                                if (!$sg['alive']) {
                                    $seg = $k;
                                }
                            }
                        }
                    }
                    if ($seg === null) {
                        continue;
                    }
                    $place[] = [$c, $seg, $same ? 0 : $segs[$seg]['used']];
                    $segs[$seg]['used'] += $l;
                }
                foreach ($place as [$c, $seg, $offset]) {
                    $cardUpdates[$c['id']][$dCol] = $segs[$seg]['d'];
                    $cardUpdates[$c['id']][$pCol] = $segs[$seg]['p'] + $offset;
                    if ($layer === 'internal') {
                        $oldCard = $segs[$seg]['card'];
                        $cardUpdates[$c['id']]['start_half'] = (int) ($oldCard['start_half'] ?? 0);
                        $teacherRoom($c, $oldCard);
                        foreach ($oldOv[(int) $oldCard['id']] ?? [] as $w => $o) {
                            if ($valid($o)) {
                                $addWeek($c, (int) $w, $o, $offset);
                            }
                        }
                    }
                }
                if ($layer !== 'internal') {
                    continue;
                }
                // Shablonsiz, hafta-hafta joylangan eski kartalar (ichki jadvalda faqat haftalik joy): saqlanib qolgan
                // karta — o'z haftalariga; qayta yaratilganlari — har haftada o'chirilgan kartalar joyiga ketma-ket.
                foreach ($todo as $c) {
                    if (isset($srcById[(int) $c['id']]) && !isset($segs[(int) $c['id']])) {
                        $copyWeeks($c, $srcById[(int) $c['id']]);
                    }
                }
                foreach ($weekFrom as [$c, $oldCard]) {
                    $copyWeeks($c, $oldCard);
                }
                $placed = array_flip(array_map(fn ($x) => (int) $x[0]['id'], $place));
                $rest = $same ? [] : array_values(array_filter($fresh, fn ($c) => !isset($placed[(int) $c['id']])));
                $weeks = [];
                foreach ($rest ? $weekOnly : [] as $sc) {
                    foreach ($oldOv[(int) $sc['id']] ?? [] as $w => $o) {
                        if ($valid($o)) {
                            $weeks[(int) $w] = true;
                        }
                    }
                }
                ksort($weeks);
                foreach (array_keys($weeks) as $w) {
                    $wsegs = [];
                    foreach ($weekOnly as $sc) {
                        $o = $oldOv[(int) $sc['id']][$w] ?? null;
                        if ($o && $valid($o)) {
                            $wsegs[] = ['d' => (int) $o['day'], 'p' => (int) $o['pair'], 'len' => $lenOf($sc), 'used' => 0, 'ov' => $o, 'card' => $sc];
                        }
                    }
                    usort($wsegs, fn ($a, $b) => [$a['d'], $a['p']] <=> [$b['d'], $b['p']]);
                    foreach ($rest as $c) {
                        $l = $lenOf($c);
                        $k = null;
                        foreach ($wsegs as $j => $sg) {
                            if ($sg['len'] - $sg['used'] >= $l) {
                                $k = $j;
                                break;
                            }
                        }
                        $k ??= array_key_last($wsegs);
                        $addWeek($c, $w, $wsegs[$k]['ov'], $wsegs[$k]['used']);
                        $wsegs[$k]['used'] += $l;
                        $teacherRoom($c, $wsegs[$k]['card']);
                    }
                }
                foreach ($todo as $c) {
                    if (!isset($cardUpdates[$c['id']]['day']) && !isset($weekDone[(int) $c['id']])) {
                        $stats['source_unplaced']++;
                    }
                }
            }
            if (count($exampleCards) < 15) {
                $exampleCards[] = $todo[0];
            }
        }

        // Yozish: har joylashuv hozirgi jadval va oldin tiklanganlar bilan to'qnashuvga tekshiriladi — to'qnashsa,
        // yozilmaydi (karta joylanmagan qoladi) va hisobotga tushadi. Sinov ham xuddi shunday hisoblanadi, oxirida bekor qilinadi.
        $applied = [];
        if ($cardUpdates || $ovInserts) {
            if (!$dryRun && $takeSnapshot) {
                $this->take($board, 'before_restore', null, $current);
            }
            $level = DB::transactionLevel();
            DB::beginTransaction();
            try {
                $applied = $this->applyRestore($cardUpdates, $ovInserts, $conflicts, $stats, $progress);
                $dryRun ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack($level);
                throw $e;
            }
        }
        foreach ($exampleCards as $c0) {
            $u = $applied[(int) $c0['id']] ?? [];
            $stats['examples'][] = ($c0['faculty_name'] ?? '') . ' · ' . $c0['course'] . '-kurs · ' . $c0['subject_name'] . ' · '
                . ($c0['group_name'] ?: $c0['oqim_label']) . ' → ichki ' . (($u['day'] ?? null) ? $u['day'] . '/' . $u['pair'] : (!empty($u['weeks']) ? 'haftalarda' : '—'))
                . ', tashqi ' . (($u['ext_day'] ?? null) ? $u['ext_day'] . '/' . $u['ext_pair'] : '—');
        }

        return ['dry_run' => $dryRun] + $stats;
    }

    /**
     * Rejadagi joylashuvlarni ketma-ket yozadi; har biri oldin to'qnashuvga tekshiriladi (oldin yozilganlar ham hisobga
     * kiradi). Qaytaradi: karta id => yozilgan joylashuv (day/pair, ext_day/ext_pair, weeks).
     */
    private function applyRestore(array $cardUpdates, array $ovInserts, ?callable $conflicts, array &$stats, ?callable $progress = null): array
    {
        // Jarayon: (bajarildi, jami) — buyruq satrida ko'rsatkich uchun.
        $total = count($cardUpdates) + count($ovInserts);
        $done = 0;
        $tick = function () use (&$done, $total, $progress): void {
            $done++;
            if ($progress && ($done % 50 === 0 || $done === $total)) {
                $progress($done, $total);
            }
        };
        $clash = function (TimetableCard $card, string $what, ?int $week, int $day, int $pair, int $startHalf, array $room = []) use ($conflicts, &$stats): bool {
            $errors = $conflicts ? (array) $conflicts($card, $what, $week, $day, $pair, $startHalf, $room) : [];
            if (!$errors) {
                return false;
            }
            $stats['conflicts']++;
            if (count($stats['conflict_examples']) < 15) {
                $where = ['internal' => 'ichki', 'external' => 'tashqi', 'week' => $week . '-hafta'][$what] ?? $what;
                $stats['conflict_examples'][] = $card->course . '-kurs · ' . $card->subject_name . ' · ' . ($card->group_name ?: $card->oqim_label)
                    . " → {$where} {$day}/{$pair}: " . implode(' · ', $errors);
            }

            return true;
        };
        $applied = [];
        foreach ($cardUpdates as $id => $cols) {
            $tick();
            $card = TimetableCard::find($id);
            if (!$card) {
                continue;
            }
            // O'qituvchi/xona avval (tekshiruv shular bilan), so'ng joylashuv.
            $card->forceFill(array_diff_key($cols, array_flip(['day', 'pair', 'start_half', 'ext_day', 'ext_pair'])));
            if (isset($cols['day'])) {
                if ($clash($card, 'internal', null, (int) $cols['day'], (int) $cols['pair'], (int) ($cols['start_half'] ?? 0))) {
                    unset($cols['day'], $cols['pair'], $cols['start_half']);
                } else {
                    $stats['internal']++;
                    $applied[$id]['day'] = $cols['day'];
                    $applied[$id]['pair'] = $cols['pair'];
                }
            }
            if (isset($cols['ext_day'])) {
                if ($clash($card, 'external', null, (int) $cols['ext_day'], (int) $cols['ext_pair'], 0)) {
                    unset($cols['ext_day'], $cols['ext_pair']);
                } else {
                    $stats['external']++;
                    $applied[$id]['ext_day'] = $cols['ext_day'];
                    $applied[$id]['ext_pair'] = $cols['ext_pair'];
                }
            }
            if ($cols) {
                DB::table('timetable_cards')->where('id', $id)->update($cols + ['updated_at' => now()]);
            }
        }
        $ovRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
        foreach ($ovInserts as $o) {
            $tick();
            $card = TimetableCard::find($o['card_id']);
            if (!$card) {
                continue;
            }
            // Tekshiruv shu haftaning xonasi bilan (nusxadagi, kartanikidan farq qilishi mumkin) — yozilishidan oldin.
            $room = $ovRoom ? ['auditorium_code' => $o['auditorium_code'], 'auditorium_name' => $o['auditorium_name']] : [];
            if ($clash($card, 'week', (int) $o['week'], (int) $o['day'], (int) $o['pair'], (int) $o['start_half'], $room)) {
                continue;
            }
            $row = ['day' => $o['day'], 'pair' => $o['pair'], 'start_half' => $o['start_half'], 'cancelled' => false, 'updated_at' => now()] + $room;
            if ($o['exists']) {
                DB::table('timetable_card_overrides')->where('card_id', $o['card_id'])->where('week', $o['week'])->update($row);
            } else {
                DB::table('timetable_card_overrides')->insert($row + ['card_id' => $o['card_id'], 'week' => $o['week'], 'created_at' => now()]);
            }
            $stats['week_moves']++;
            $applied[(int) $o['card_id']]['weeks'] = true;
        }
        $stats['cards'] = count($applied);

        return $applied;
    }
}

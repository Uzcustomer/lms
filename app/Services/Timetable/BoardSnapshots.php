<?php

namespace App\Services\Timetable;

use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
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
     * Nusxadagi joylashuvni hozirgi joylanmagan kartalarga tiklaydi.
     * $scope: faculty_names / specialty_names / courses (ixtiyoriy). Qaytaradi: hisobot.
     */
    public function restorePlacement(TimetableBoard $board, array $old, bool $dryRun, array $scope = [], bool $takeSnapshot = true): array
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
        $stats = ['units' => 0, 'cards' => 0, 'internal' => 0, 'external' => 0, 'week_moves' => 0, 'teacher_room' => 0,
            'no_source' => 0, 'source_unplaced' => 0, 'examples' => []];

        foreach ($curByUnit as $key => $cards) {
            $todo = array_values(array_filter($cards, fn ($c) => empty($c['day']) && empty($c['ext_day'])));
            if (!$todo) {
                continue;
            }
            $src = $oldByUnit[$key] ?? [];
            if (!$src) {
                $stats['no_source'] += count($todo);
                continue;
            }
            // Hozir joylashgan kartalari bo'lsa (birlikning bir qismi qo'yilgan) — faqat qolganlari; eski kartalardan
            // ham shu birlikda hozir joyi bor kartalar egallagan soatlar chiqarilmaydi (oddiylik: hammasi beriladi).
            $stats['units']++;
            // Bir xil bo'linish (soat va haftalar bir xil) — kartama-karta; aks holda eski soatlar ichiga ketma-ket.
            $sig = fn (array $list) => implode(',', array_map(fn ($c) => $lenOf($c) . 'x' . (int) ($c['weeks'] ?? 0), $list));
            $byLen = fn (array $a, array $b) => [$lenOf($b), (int) ($b['weeks'] ?? 0), $a['id']] <=> [$lenOf($a), (int) ($a['weeks'] ?? 0), $b['id']];
            usort($todo, $byLen);
            usort($src, $byLen);
            $same = count($todo) === count($src) && $sig($todo) === $sig($src);

            foreach (['internal' => ['day', 'pair'], 'external' => ['ext_day', 'ext_pair']] as $layer => [$dCol, $pCol]) {
                // Eski kartalar egallagan bo'laklar: [kun, boshlanish, uzunlik, eski karta].
                $segs = [];
                foreach ($src as $s) {
                    if (!empty($s[$dCol]) && !empty($s[$pCol])) {
                        // Eski karta hozir ham shu joyda bo'lsa — bo'lak band (yangi karta uning ustiga qo'yilmaydi).
                        $still = $curById[(int) $s['id']] ?? null;
                        $busy = $still && (int) ($still[$dCol] ?? 0) === (int) $s[$dCol] && (int) ($still[$pCol] ?? 0) === (int) $s[$pCol];
                        $segs[] = ['d' => (int) $s[$dCol], 'p' => (int) $s[$pCol], 'len' => $lenOf($s), 'used' => $busy ? $lenOf($s) : 0, 'card' => $s];
                    }
                }
                if (!$segs) {
                    if ($layer === 'internal') {
                        $stats['source_unplaced'] += count($todo);
                    }
                    continue;
                }
                usort($segs, fn ($a, $b) => [$a['d'], $a['p']] <=> [$b['d'], $b['p']]);
                foreach ($todo as $i => $c) {
                    $l = $lenOf($c);
                    $seg = null;
                    if ($same) {
                        // Kartama-karta: shu tartibdagi eski karta.
                        $s = $src[$i];
                        foreach ($segs as $k => $sg) {
                            if ($sg['card']['id'] === $s['id']) {
                                $seg = $k;
                            }
                        }
                    } else {
                        foreach ($segs as $k => $sg) {
                            if ($sg['len'] - $sg['used'] >= $l) {
                                $seg = $k;
                                break;
                            }
                        }
                        // Sig'madi — oxirgi bo'lak davomiga.
                        $seg ??= array_key_last($segs);
                    }
                    if ($seg === null) {
                        continue;
                    }
                    $offset = $same ? 0 : $segs[$seg]['used'];
                    $segs[$seg]['used'] += $l;
                    $day = $segs[$seg]['d'];
                    $pair = $segs[$seg]['p'] + $offset;
                    $cardUpdates[$c['id']][$dCol] = $day;
                    $cardUpdates[$c['id']][$pCol] = $pair;
                    $stats[$layer]++;
                    if ($layer === 'internal') {
                        $oldCard = $segs[$seg]['card'];
                        $cardUpdates[$c['id']]['start_half'] = (int) ($oldCard['start_half'] ?? 0);
                        // O'qituvchi/xona — hozir bo'sh bo'lsa.
                        if (empty($c['teacher_id']) && !empty($oldCard['teacher_id'])) {
                            $cardUpdates[$c['id']]['teacher_id'] = $oldCard['teacher_id'];
                            $cardUpdates[$c['id']]['teacher_name'] = $oldCard['teacher_name'];
                            $stats['teacher_room']++;
                        }
                        if (empty($c['auditorium_code']) && !empty($oldCard['auditorium_code'])) {
                            $cardUpdates[$c['id']]['auditorium_code'] = $oldCard['auditorium_code'];
                            $cardUpdates[$c['id']]['auditorium_name'] = $oldCard['auditorium_name'];
                        }
                        // Haftalik ko'chirishlar (ichki jadval): eski kartaning shu haftadagi joyi, xuddi shu siljish bilan.
                        // Hafta yangi kartada bekor qilingan bo'lsa — tegilmaydi (yangi kartaning o'z haftalari).
                        foreach ($oldOv[(int) $oldCard['id']] ?? [] as $w => $o) {
                            if (!empty($o['cancelled']) || empty($o['day']) || empty($o['pair'])) {
                                continue;
                            }
                            $mine = $curOv[(int) $c['id']][$w] ?? null;
                            if ($mine && (!empty($mine['cancelled']) || !empty($mine['day']))) {
                                continue;
                            }
                            $ovInserts[] = ['card_id' => $c['id'], 'week' => $w, 'day' => (int) $o['day'], 'pair' => (int) $o['pair'] + $offset,
                                'start_half' => (int) ($o['start_half'] ?? 0), 'auditorium_code' => $o['auditorium_code'] ?? null,
                                'auditorium_name' => $o['auditorium_name'] ?? null, 'exists' => (bool) $mine];
                            $stats['week_moves']++;
                        }
                    }
                }
            }
            if (count($stats['examples']) < 15) {
                $c0 = $todo[0];
                $u = $cardUpdates[$c0['id']] ?? [];
                $stats['examples'][] = ($c0['faculty_name'] ?? '') . ' · ' . $c0['course'] . '-kurs · ' . $c0['subject_name'] . ' · '
                    . ($c0['group_name'] ?: $c0['oqim_label']) . ' → ichki ' . (($u['day'] ?? null) ? $u['day'] . '/' . $u['pair'] : '—')
                    . ', tashqi ' . (($u['ext_day'] ?? null) ? $u['ext_day'] . '/' . $u['ext_pair'] : '—');
            }
        }
        $stats['cards'] = count($cardUpdates);

        if (!$dryRun && ($cardUpdates || $ovInserts)) {
            if ($takeSnapshot) {
                $this->take($board, 'before_restore', null, $current);
            }
            $ovRoom = Schema::hasColumn('timetable_card_overrides', 'auditorium_code');
            DB::transaction(function () use ($cardUpdates, $ovInserts, $ovRoom) {
                foreach ($cardUpdates as $id => $cols) {
                    DB::table('timetable_cards')->where('id', $id)->update($cols + ['updated_at' => now()]);
                }
                foreach ($ovInserts as $o) {
                    $row = ['day' => $o['day'], 'pair' => $o['pair'], 'start_half' => $o['start_half'], 'cancelled' => false,
                        'updated_at' => now()] + ($ovRoom ? ['auditorium_code' => $o['auditorium_code'], 'auditorium_name' => $o['auditorium_name']] : []);
                    if ($o['exists']) {
                        DB::table('timetable_card_overrides')->where('card_id', $o['card_id'])->where('week', $o['week'])->update($row);
                    } else {
                        DB::table('timetable_card_overrides')->insert($row + ['card_id' => $o['card_id'], 'week' => $o['week'], 'created_at' => now()]);
                    }
                }
            });
        }

        return ['dry_run' => $dryRun] + $stats;
    }
}

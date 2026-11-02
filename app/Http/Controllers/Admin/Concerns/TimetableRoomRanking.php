<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Auditorium;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Services\Timetable\KafedraResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bo'sh xonalarni karta uchun yaqinlik bo'yicha saralash (Takliflar va biriktirish oynalari). Alifbo emas — ball:
 *   1) shu fan + shu guruh/oqim doskada odatda o'tadigan xona (kartalar va hafta-override'lardagi xona chastotasi);
 *   2) kartaning o'qituvchisiga biriktirilgan xona (auditorium_teacher, umumiy emas);
 *   3) fan kafedrasining xonasi (auditoriums.department_hemis_id / department_name — kafedra nomi bo'yicha, KafedraResolver guruhi);
 *   4) hozirgi xona bilan bir bino (building_id), bino ichida — qavati yaqinrog'i (nomdagi raqamdan);
 *   5) qolganlari — sig'imi talabalar soniga eng yaqin (ortiqcha katta zal oxirida).
 * Har mezon o'zidan pastdagilarning hammasidan ustun (8 / 4 / 2 / 1). Xaritalar so'rov davomida bir marta quriladi.
 */
trait TimetableRoomRanking
{
    /** @var array{all: array<string, Auditorium>, active: \Illuminate\Support\Collection, teacher: array}|null */
    private ?array $roomRankBase = null;

    /** @var array<int, array<string, array<string, int>>> doska => odat kaliti => [xona kodi => marta] (Takliflar holatidan) */
    private array $roomHabitByBoard = [];

    /** @var array<string, array<string, true>> kafedra nomi => [xona kodi => true] */
    private array $roomKafedraCodes = [];

    public const ROOM_WHY = [
        'habit' => 'shu fan odatda shu yerda',
        'teacher' => "o'qituvchiga biriktirilgan",
        'kafedra' => 'kafedra xonasi',
        'building' => 'shu bino',
    ];

    /**
     * Keshni tozalash — har so'rov boshida: controller nusxasi route'da saqlanadi (testlar, uzoq yashovchi worker), xaritalar
     * eskirmasin.
     */
    private function roomRankReset(): void
    {
        $this->roomRankBase = null;
        $this->roomHabitByBoard = [];
        $this->roomKafedraCodes = [];
    }

    /** Barcha xonalar (kod bo'yicha), faollari (nom tartibida) va xona–o'qituvchi xaritasi — so'rovda bir marta. */
    private function roomRankBase(): array
    {
        if ($this->roomRankBase === null) {
            $all = Schema::hasTable('auditoriums') ? Auditorium::orderBy('name')->get() : collect();
            $this->roomRankBase = [
                'all' => $all->keyBy(fn ($r) => (string) $r->code)->all(),
                'active' => $all->filter(fn ($r) => (bool) $r->active)->values(),
                'teacher' => $this->auditoriumTeacherMap(),
            ];
        }

        return $this->roomRankBase;
    }

    /** Odat kaliti: yo'nalish doirasi + fan + tur + guruh(cha)lar (ma'ruza — oqim guruhlari; guruhsiz — oqim nomi). */
    private function roomHabitKey(object $c): string
    {
        $norm = fn ($v) => trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $v)));
        $groups = array_values(array_unique(array_filter(array_map(
            fn ($g) => $norm(preg_replace('/\s*\([^)]*\)\s*/u', ' ', (string) $g)), $this->cardGroupsOf($c)))));
        sort($groups);

        return implode('|', [$norm($c->faculty_name ?? ''), $norm($c->specialty_name ?? ''), (int) ($c->course ?? 0), $norm($c->subject_name ?? ''),
            ($c->training_type ?? '') === 'lecture' ? 'L' : 'A', $groups ? implode(',', $groups) : 'o:' . $norm($c->oqim_label ?? '')]);
    }

    /**
     * Odat xaritasi: kalit => [xona => marta]. Karta xonasi — u o'tadigan haftalar soni (boshqa xonaga ko'chirilgan haftalardan
     * tashqari), hafta-override xonasi — har biri 1.
     */
    private function roomHabitMap(iterable $cards, array $ov): array
    {
        $h = [];
        foreach ($cards as $c) {
            $k = $this->roomHabitKey($c);
            $moved = 0;
            foreach ($ov[(int) $c->id] ?? [] as $o) {
                $code = (string) ($o->auditorium_code ?? '');
                if ($code !== '') {
                    $h[$k][$code] = ($h[$k][$code] ?? 0) + 1;
                    $moved++;
                }
            }
            $code = (string) ($c->auditorium_code ?? '');
            if ($code !== '') {
                $h[$k][$code] = ($h[$k][$code] ?? 0) + max(1, (int) ($c->weeks ?? 1) - $moved);
            }
        }

        return $h;
    }

    /** Takliflar: odat xaritasi doskaning yuklangan holatidan (kartalar va override'lar) — qo'shimcha so'rovsiz, bir marta. */
    private function roomHabitFromState(int $boardId, array $state): array
    {
        return $this->roomHabitByBoard[$boardId] ??= $this->roomHabitMap($state['cards'] ?? [], $state['ov'] ?? []);
    }

    /** Biriktirish oynalari: faqat shu fan va turdagi (yo'nalish, kurs) kartalar va ularning override'lari — kichik so'rov. */
    private function roomHabitForCard(TimetableBoard $board, TimetableCard $c): array
    {
        $cards = TimetableCard::where('board_id', $board->id)->where('subject_name', $c->subject_name)
            ->where('training_type', $c->training_type)->where('specialty_name', $c->specialty_name)->where('course', $c->course)->get();
        $ov = [];
        if ($cards->isNotEmpty() && Schema::hasTable('timetable_card_overrides')) {
            foreach (DB::table('timetable_card_overrides')->whereIn('card_id', $cards->pluck('id')->all())->whereNotNull('auditorium_code')
                ->get(['card_id', 'week', 'auditorium_code']) as $o) {
                $ov[(int) $o->card_id][(int) $o->week] = $o;
            }
        }

        return $this->roomHabitMap($cards, $ov);
    }

    /** Kafedra xonalari (kafedra nomi bo'yicha; bir xil nomli asosiy/o'rindoshlik yozuvlari — bitta kafedra). */
    private function kafedraRoomCodes(?string $kafedra): array
    {
        $key = KafedraResolver::nameKey($kafedra);
        if ($key === '') {
            return [];
        }
        if (!isset($this->roomKafedraCodes[$key])) {
            $idx = null;
            $resolver = null;
            $byDept = [];
            $codes = [];
            foreach ($this->roomRankBase()['all'] as $code => $r) {
                $d = (int) ($r->department_hemis_id ?? 0);
                if ($d > 0) {
                    if (!isset($byDept[$d])) {
                        $idx ??= $this->kafedraNamesIndex();
                        $resolver ??= app(KafedraResolver::class);
                        $names = [];
                        foreach ($resolver->group($d) as $id) {
                            array_push($names, ...($idx[$id] ?? []));
                        }
                        $byDept[$d] = $this->sameKafedra($kafedra, array_values(array_filter($names)));
                    }
                    if ($byDept[$d]) {
                        $codes[(string) $code] = true;
                        continue;
                    }
                }
                if (trim((string) ($r->department_name ?? '')) !== '' && $this->sameKafedra($kafedra, (string) $r->department_name)) {
                    $codes[(string) $code] = true;
                }
            }
            $this->roomKafedraCodes[$key] = $codes;
        }

        return $this->roomKafedraCodes[$key];
    }

    /** Qavat — nomdagi birinchi 3+ xonali raqamdan ("423" → 4, "2-215" → 2, "1203" → 12); aniqlanmasa — null. */
    private static function roomFloor(?string $name): ?int
    {
        return preg_match('/(\d{3,})/', (string) $name, $m) ? intdiv((int) $m[1], 100) : null;
    }

    /**
     * Xonalarni karta uchun saralaydi: [[xona, sabab|null], ...]. $currentCode — kartaning hozirgi (band) xonasi (bino/qavat uchun),
     * $habit — roomHabitMap natijasi. Sig'imi yetmaydigan yoki o'qituvchiga ruxsat etilmagan xonalar (ro'yxatdan chiqarilmagan bo'lsa) —
     * oxirida.
     */
    private function rankRooms(object $c, iterable $rooms, ?string $currentCode, array $habit): array
    {
        $base = $this->roomRankBase();
        $habitHere = $habit[$this->roomHabitKey($c)] ?? [];
        $kafedra = $this->kafedraRoomCodes($c->kafedra_name ?? null);
        $cur = $currentCode !== null && $currentCode !== '' ? ($base['all'][(string) $currentCode] ?? null) : null;
        $curBuilding = $cur && (string) $cur->building_id !== '' ? (string) $cur->building_id : null;
        $curFloor = $cur ? self::roomFloor($cur->name) : null;
        $students = (int) ($c->students ?? 0);
        $teacherId = (int) ($c->teacher_id ?? 0);
        $card = $c instanceof TimetableCard ? $c : null;
        $rows = [];
        foreach ($rooms as $r) {
            $code = (string) $r->code;
            $r = $base['all'][$code] ?? $r;   // to'liq yozuv (id, bino, kafedra) — qisqa select bilan berilgan bo'lsa ham
            $asg = $base['teacher'][(string) $r->id] ?? null;
            $f = [
                'habit' => (int) ($habitHere[$code] ?? 0) > 0,
                'teacher' => $teacherId > 0 && $asg && !$asg['is_general'] && (int) $asg['teacher_id'] === $teacherId,
                'kafedra' => isset($kafedra[$code]),
                'building' => $curBuilding !== null && (string) $r->building_id === $curBuilding,
            ];
            $vol = (int) $r->volume;
            $fits = !($vol > 0 && $students > 0 && $vol < $students) && (!$card || $this->auditoriumAllowedForCard($r, $card, $base['teacher']));
            $floor = $f['building'] && $curFloor !== null ? self::roomFloor($r->name) : null;
            $rows[] = [$r, $f, [
                $fits ? 0 : 1,
                -(($f['habit'] ? 8 : 0) + ($f['teacher'] ? 4 : 0) + ($f['kafedra'] ? 2 : 0) + ($f['building'] ? 1 : 0)),
                -(int) ($habitHere[$code] ?? 0),
                $floor !== null ? abs($floor - $curFloor) : PHP_INT_MAX,
                $vol > 0 && $students > 0 ? ($vol >= $students ? $vol - $students : PHP_INT_MAX) : PHP_INT_MAX - 1,
            ]];
        }
        usort($rows, fn ($x, $y) => ($x[2] <=> $y[2]) ?: strnatcasecmp((string) $x[0]->name, (string) $y[0]->name));

        return array_map(fn ($row) => [$row[0], ($k = array_key_first(array_filter($row[1]))) !== null ? self::ROOM_WHY[$k] : null], $rows);
    }

    /**
     * Biriktirish oynasi uchun reyting: xonalar kodlari tartibda va sabablari. Dars birligi kartalaridan vakil: o'qituvchi —
     * birinchisi biriktirilgan, talabalar — ma'ruzada yig'indisi (oqim), amaliyda eng kattasi; hozirgi xona — biriktirilgani.
     *
     * @return array{order: string[], why: array<string, string>}
     */
    protected function roomRankForUnit(TimetableBoard $board, $cards, iterable $rooms): array
    {
        $first = $cards->first();
        if (!$first) {
            return ['order' => [], 'why' => []];
        }
        $rep = clone $first;
        $rep->teacher_id = $cards->pluck('teacher_id')->filter()->first();
        $rep->students = $first->training_type === 'lecture' ? (int) $cards->sum('students') : (int) $cards->max('students');
        $current = $cards->pluck('auditorium_code')->filter()->first();
        $order = [];
        $why = [];
        foreach ($this->rankRooms($rep, $rooms, $current, $this->roomHabitForCard($board, $first)) as [$r, $w]) {
            $order[] = (string) $r->code;
            if ($w !== null) {
                $why[(string) $r->code] = $w;
            }
        }

        return ['order' => $order, 'why' => $why];
    }
}

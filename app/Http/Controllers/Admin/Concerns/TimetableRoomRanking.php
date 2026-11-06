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
 *   3) fan kafedrasining xonasi (auditoriums.department_hemis_id / department_name — kafedra nomi bo'yicha, KafedraResolver guruhi).
 *      HEMIS xonalar ro'yxatida (import:auditoriums) kafedra yo'q — maydon faqat kafedra mudiri LMS'da qo'shgan xonalarda; bo'sh
 *      bo'lsa mezon sababsiz o'tkazib yuboriladi;
 *   4) shu fan + tur + yo'nalish/kurs bo'yicha boshqa guruhlar ishlatgan xona (chastota) — "shu fan xonasi";
 *   5) hozirgi xona bilan bir bino: nomdagi blok/bo'lim prefiksi (vergulgacha: "Tibbiyot klasteri, 437") bir xili avval, so'ng
 *      building_id bir xili (faqat hozirgi xona faol bo'lsa — faolsiz eski xonaning binosi bo'yicha keng tavsiya berilmaydi);
 *      bino ichida qavati yaqinrog'i (nomdagi raqamdan);
 *   6) qolganlari — sig'imi talabalar soniga eng yaqin (ortiqcha katta zal oxirida).
 * Har mezon o'zidan pastdagilarning hammasidan ustun (16 / 8 / 4 / 2 / 1). Har daraja ichida xona turi mos kelmagani oxirida
 * (amaliy darsga "Ma'ruza" turidagi zal, ma'ruzaga — boshqa turdagi xona). Xaritalar so'rov davomida bir marta quriladi.
 *
 * Yaqinlik rejimi ($proximity — fan mas'uli "Vaqt va xonani o'zgartirish" oynasi; boshqa oynalar tartibi o'zgarmaydi): avval hozirgi
 * xonaga yaqinlik pog'onasi — shu bino + bo'lim + qavat → shu bino + bo'lim → shu bino + qavat → shu bino → boshqa / noma'lum bino;
 * pog'ona ichida yuqoridagi ballar (odat, o'qituvchi, kafedra, fan), so'ng qavat farqi (faqat shu bino ichida), so'ng sig'im yaqinligi.
 * Bino, bo'lim va qavat — xona nomidan (roomBuilding / roomSection / roomFloor(…, true)); bino nomda bo'lmasa — building_id.
 */
trait TimetableRoomRanking
{
    /** @var array{all: array<string, Auditorium>, active: \Illuminate\Support\Collection, teacher: array}|null */
    private ?array $roomRankBase = null;

    /** @var array<int, array{g: array, s: array}> doska => odat xaritalari (guruh / fan darajasi) — Takliflar holatidan */
    private array $roomHabitByBoard = [];

    /** @var array<string, array<string, true>> kafedra nomi => [xona kodi => true] */
    private array $roomKafedraCodes = [];

    public const ROOM_WHY = [
        'habit' => 'shu fan odatda shu yerda',
        'teacher' => "o'qituvchiga biriktirilgan",
        'kafedra' => 'kafedra xonasi',
        'subject' => 'shu fan xonasi',
        'building' => 'shu bino',
        'section' => "shu bo'lim",
        'floor' => 'shu qavat',
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

    /** Fan darajasidagi odat kaliti: yo'nalish doirasi + fan + tur (guruhlarsiz — shu fanning boshqa guruhlari ham). */
    private function roomSubjectKey(object $c): string
    {
        $norm = fn ($v) => trim((string) preg_replace('/\s+/u', ' ', mb_strtolower((string) $v)));

        return implode('|', [$norm($c->faculty_name ?? ''), $norm($c->specialty_name ?? ''), (int) ($c->course ?? 0), $norm($c->subject_name ?? ''),
            ($c->training_type ?? '') === 'lecture' ? 'L' : 'A']);
    }

    /**
     * Odat xaritalari: 'g' — guruh kaliti => [xona => marta], 's' — fan kaliti (shu fan, tur, yo'nalish/kurs, barcha guruhlar)
     * => [xona => marta]. Karta xonasi — u o'tadigan haftalar soni (boshqa xonaga ko'chirilgan haftalardan tashqari), hafta-override
     * xonasi — har biri 1.
     */
    private function roomHabitMap(iterable $cards, array $ov): array
    {
        $h = ['g' => [], 's' => []];
        $add = function (object $c, string $code, int $n) use (&$h) {
            foreach (['g' => $this->roomHabitKey($c), 's' => $this->roomSubjectKey($c)] as $lvl => $k) {
                $h[$lvl][$k][$code] = ($h[$lvl][$k][$code] ?? 0) + $n;
            }
        };
        foreach ($cards as $c) {
            $moved = 0;
            foreach ($ov[(int) $c->id] ?? [] as $o) {
                $code = (string) ($o->auditorium_code ?? '');
                if ($code !== '') {
                    $add($c, $code, 1);
                    $moved++;
                }
            }
            $code = (string) ($c->auditorium_code ?? '');
            if ($code !== '') {
                $add($c, $code, max(1, (int) ($c->weeks ?? 1) - $moved));
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

    /** Blok/bo'lim prefiksi — nomning birinchi vergulgacha qismi ("Tibbiyot klasteri, 437" → "tibbiyot klasteri"); vergulsiz — null. */
    private static function roomPrefix(?string $name): ?string
    {
        $name = (string) $name;
        if (!str_contains($name, ',')) {
            return null;
        }
        $p = KafedraResolver::nameKey(explode(',', $name, 2)[0]);

        return $p !== '' ? $p : null;
    }

    /** Xona turi: 'L' — ma'ruza zali (auditorium_type_name), 'A' — boshqa ma'lum tur, null — tur noma'lum. */
    private static function roomKind(?string $type): ?string
    {
        $t = KafedraResolver::nameKey($type);
        if ($t === '') {
            return null;
        }

        return str_contains($t, "ma'ruza") || str_contains($t, 'maruza') || str_contains($t, 'лекц') || str_contains($t, 'lecture') ? 'L' : 'A';
    }

    /**
     * Qavat — nomdagi birinchi 3+ xonali raqamdan ("423" → 4, "2-215" → 2, "1203" → 12); aniqlanmasa — null. $explicit — avval nomda
     * aniq yozilgani: "N-qavat" / "N-etaj" ("-1-qavat" — podval), "podval" — 0 (podval va manfiy qavat bir xil); yo'q bo'lsa — raqamdan
     * ("2-qavat, 305-auditoriya" → 2, "404-amaliyot xonasi" → 4). Boshqa oynalar (biriktirish, Takliflar) — eski qoida ($explicit = false).
     */
    private static function roomFloor(?string $name, bool $explicit = false): ?int
    {
        if ($explicit) {
            $n = KafedraResolver::nameKey($name);
            if (preg_match('/(?<![\d-])(-?\d+)\s*-\s*(?:qavat|etaj)/u', $n, $m)) {
                return max(0, (int) $m[1]);
            }
            if (preg_match('/\bpodval\b/u', $n)) {
                return 0;
            }
        }

        return preg_match('/(\d{3,})/', (string) $name, $m) ? intdiv((int) $m[1], 100) : null;
    }

    /** Xona nomi bo'laklari — vergul bo'yicha (vergulsiz — butun nom bitta bo'lak), normallashtirilgan (kichik harf, tutuq belgilari bir xil). */
    private static function roomParts(?string $name): array
    {
        return array_values(array_filter(array_map('trim', explode(',', KafedraResolver::nameKey($name))), fn ($p) => $p !== ''));
    }

    /**
     * Bino — nomdagi "N-bino" yoki bo'lak boshidan "… binosi" / "… klasteri" gacha (normallashtirilgan; xonaga oid qolgan matnsiz — vergulsiz
     * "3-bino 2-qavat 201-xona" → "3-bino", "Tibbiyot klasteri 8 ma'ruza zali" → "tibbiyot klasteri"); yo'q bo'lsa — null (building_id ishlatiladi).
     */
    private static function roomBuilding(?string $name): ?string
    {
        foreach (self::roomParts($name) as $p) {
            if (preg_match('/(?:^|\s)(\d+)\s*-\s*bino(?:\s|$)/u', $p, $m)) {
                return $m[1] . '-bino';
            }
            if (preg_match('/^(.*?(?:binosi|klasteri))(?:\s|$)/u', $p, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Bo'lim — "N-bo'lim" (raqam bo'yicha) yoki "<nom> bo'limi" (shifoxona bo'limi; vergul bo'lagida yoki qavs ichida — "23-xona (Suyak-bo'g'im
     * kasalliklari bo'limi)"), tutuq belgilari farqsiz; yo'q bo'lsa — null.
     */
    private static function roomSection(?string $name): ?string
    {
        $n = KafedraResolver::nameKey($name);
        if (preg_match("/(?<![\d-])(\d+)\s*-\s*bo'lim/u", $n, $m)) {
            return 'n:' . (int) $m[1];
        }
        if (preg_match("/([^,()]*?bo'limi)/u", $n, $m) && trim($m[1]) !== "bo'limi") {
            return 's:' . trim($m[1]);
        }

        return null;
    }

    /** Yaqinlik rejimida bir binomi: ikkala nomda bino bo'lsa — nom bo'yicha (building_id bir xil bo'lsa ham); aks holda — building_id (ikkalasida). */
    private static function roomSameBuilding(?string $curName, string $curId, Auditorium $r): bool
    {
        $rName = self::roomBuilding($r->name);
        if ($curName !== null && $rName !== null) {
            return $curName === $rName;
        }

        return $curId !== '' && (string) $r->building_id !== '' && (string) $r->building_id === $curId;
    }

    /**
     * Xonalarni karta uchun saralaydi: [[xona, sabab|null], ...]. $currentCode — kartaning hozirgi (band) xonasi (bino/qavat uchun),
     * $habit — roomHabitMap natijasi. Sig'imi yetmaydigan yoki o'qituvchiga ruxsat etilmagan xonalar (ro'yxatdan chiqarilmagan bo'lsa) —
     * oxirida.
     */
    private function rankRooms(object $c, iterable $rooms, ?string $currentCode, array $habit, bool $proximity = false): array
    {
        $base = $this->roomRankBase();
        $habitHere = $habit['g'][$this->roomHabitKey($c)] ?? [];
        $subjectHere = $habit['s'][$this->roomSubjectKey($c)] ?? [];
        $kafedra = $this->kafedraRoomCodes($c->kafedra_name ?? null);
        $cur = $currentCode !== null && $currentCode !== '' ? ($base['all'][(string) $currentCode] ?? null) : null;
        // Bino: nom prefiksi — har doim; building_id (juda keng bo'lishi mumkin) — faqat hozirgi xona faol bo'lsa.
        $curPrefix = $cur ? self::roomPrefix($cur->name) : null;
        $curBuilding = $cur && $cur->active && (string) $cur->building_id !== '' ? (string) $cur->building_id : null;
        $curFloor = $cur ? self::roomFloor($cur->name) : null;
        // Yaqinlik rejimi: hozirgi xona — nuqta (faolligidan qat'i nazar); bino nomi / building_id, bo'lim, aniq qavat.
        $px = $proximity && $cur ? ['b' => self::roomBuilding($cur->name), 'bid' => (string) $cur->building_id, 's' => self::roomSection($cur->name),
            'f' => self::roomFloor($cur->name, true)] : null;
        $lesson = ($c->training_type ?? '') === 'lecture' ? 'L' : 'A';
        $students = (int) ($c->students ?? 0);
        $cardBoard = null;
        if ($c instanceof TimetableCard) $cardBoard = $c->board;
        elseif (!empty($c->board_id)) $cardBoard = TimetableBoard::find($c->board_id);
        $infoOnly = $cardBoard && $this->isNonBlocking($c, $cardBoard);
        $teacherId = (int) ($c->teacher_id ?? 0);
        $card = $c instanceof TimetableCard ? $c : null;
        $rows = [];
        foreach ($rooms as $r) {
            $code = (string) $r->code;
            $r = $base['all'][$code] ?? $r;   // to'liq yozuv (id, bino, kafedra) — qisqa select bilan berilgan bo'lsa ham
            $asg = $base['teacher'][(string) $r->id] ?? null;
            $samePrefix = $curPrefix !== null && self::roomPrefix($r->name) === $curPrefix;
            $f = [
                'habit' => (int) ($habitHere[$code] ?? 0) > 0,
                'teacher' => $teacherId > 0 && $asg && !$asg['is_general'] && (int) $asg['teacher_id'] === $teacherId,
                'kafedra' => isset($kafedra[$code]),
                'subject' => (int) ($subjectHere[$code] ?? 0) > 0,
                'building' => $samePrefix || ($curBuilding !== null && (string) $r->building_id === $curBuilding),
            ];
            $vol = (int) $r->volume;
            $fits = ($infoOnly || !($vol > 0 && $students > 0 && $vol < $students)) && (!$card || $this->auditoriumAllowedForCard($r, $card, $base['teacher']));
            $floor = $f['building'] && $curFloor !== null ? self::roomFloor($r->name) : null;
            $kind = self::roomKind($r->auditorium_type_name ?? null);
            if ($proximity) {
                $sameB = $px && self::roomSameBuilding($px['b'], $px['bid'], $r);
                $rFloor = $sameB ? self::roomFloor($r->name, true) : null;
                $sameS = $sameB && $px['s'] !== null && self::roomSection($r->name) === $px['s'];
                $sameF = $sameB && $px['f'] !== null && $rFloor === $px['f'];
                $tier = !$sameB ? 4 : ($sameS && $sameF ? 0 : ($sameS ? 1 : ($sameF ? 2 : 3)));
                $f['building'] = false;
                $rows[] = [$r, $f, [
                    $fits ? 0 : 1,
                    $tier,
                    -(($f['habit'] ? 16 : 0) + ($f['teacher'] ? 8 : 0) + ($f['kafedra'] ? 4 : 0) + ($f['subject'] ? 2 : 0)),
                    $kind !== null && $kind !== $lesson ? 1 : 0,
                    -(int) ($habitHere[$code] ?? 0),
                    -(int) ($subjectHere[$code] ?? 0),
                    $rFloor !== null && $px['f'] !== null ? abs($rFloor - $px['f']) : PHP_INT_MAX,
                    $vol > 0 && $students > 0 ? ($vol >= $students ? $vol - $students : PHP_INT_MAX) : PHP_INT_MAX - 1,
                ], [0 => 'floor', 1 => 'section', 2 => 'floor', 3 => 'building'][$tier] ?? null];
                continue;
            }
            $rows[] = [$r, $f, [
                $fits ? 0 : 1,
                -(($f['habit'] ? 16 : 0) + ($f['teacher'] ? 8 : 0) + ($f['kafedra'] ? 4 : 0) + ($f['subject'] ? 2 : 0) + ($f['building'] ? 1 : 0)),
                $kind !== null && $kind !== $lesson ? 1 : 0,   // shu daraja ichida tur mos kelmasa — oxirida
                -(int) ($habitHere[$code] ?? 0),
                -(int) ($subjectHere[$code] ?? 0),
                $samePrefix ? 0 : 1,
                $floor !== null ? abs($floor - $curFloor) : PHP_INT_MAX,
                $vol > 0 && $students > 0 ? ($vol >= $students ? $vol - $students : PHP_INT_MAX) : PHP_INT_MAX - 1,
            ]];
        }
        usort($rows, fn ($x, $y) => ($x[2] <=> $y[2]) ?: strnatcasecmp((string) $x[0]->name, (string) $y[0]->name));

        // Sabab: yaqinlik rejimida — eng yaqin pog'ona ("shu qavat" / "shu bo'lim" / "shu bino") va birinchi ball ("shu qavat · shu fan
        // odatda shu yerda"); aks holda — birinchi ball (bino ham).
        return array_map(fn ($row) => [$row[0], implode(' · ', array_filter([isset($row[3]) ? self::ROOM_WHY[$row[3]] : null,
            ($k = array_key_first(array_filter($row[1]))) !== null ? self::ROOM_WHY[$k] : null])) ?: null], $rows);
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
